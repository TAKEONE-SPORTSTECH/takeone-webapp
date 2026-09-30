<?php

namespace App\Ranks\Controllers;

use App\Clubs\Concerns\HandlesClubAuthorization;
use App\Clubs\Models\ClubActivity;
use App\Clubs\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Members\Models\User;
use App\Ranks\Models\ActivityPackage;
use App\Ranks\Support\ActivityPackages;
use App\Ranks\Models\Grading;
use App\Ranks\Support\Eligibility;
use App\Ranks\Support\Gradings;
use App\Ranks\Support\MemberRanks;
use App\Sports\Package\SportPackages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Ranks & grading in the club admin workspace.
 *
 * Everything here is reached THROUGH a ranked activity (its ActivityPackage,
 * addressed by uuid) and re-checked against the club in the URL, so a link from
 * one club can never be used to read or write another's.
 */
class RanksController extends Controller
{
    use HandlesClubAuthorization;

    public function __construct(private ActivityPackages $links, private MemberRanks $ranks, private Eligibility $eligibility, private Gradings $gradings) {}

    /** Off unless switched on — checked per request (a controller instance is reused). */
    private function enabled(): void
    {
        abort_unless(config('ranks.enabled'), 404);
    }

    /** The hub: every activity the club teaches, and what its sport brings. */
    public function index(Request $request, Tenant $club)
    {
        $this->enabled();
        $user = $request->user();
        $packs = app(SportPackages::class);

        foreach (ClubActivity::where('tenant_id', $club->id)->whereNotIn('id', ActivityPackage::select('activity_id'))->get() as $missing) {
            $this->links->autoLink($missing);
        }

        $links = ActivityPackage::whereIn('activity_id', ClubActivity::where('tenant_id', $club->id)->select('id'))->with('activity')->get();
        $admin = $this->canManageClub($club);

        $activities = $links->filter(fn ($l) => $l->activity && ($admin || $this->ranks->canManage($user, $l)))
            ->map(fn (ActivityPackage $l) => $this->activityPayload($l))->sortBy([['ranked', 'desc'], ['name', 'asc']])->values();

        abort_if(! $admin && $activities->isEmpty(), 403);

        $choices = collect($packs->sports())->flatMap(fn ($s) => collect($packs->styles($s))
            ->filter(fn ($b) => $b->hasRanks())
            ->map(fn ($b) => ['sport' => $s, 'style' => $b->style, 'label' => $b->styleLabel]))->values();

        $isMobile = (bool) $request->attributes->get('is_mobile');

        return view($isMobile ? 'ranks::mobile.index' : 'ranks::desktop.index', [
            'club' => $club,
            'today' => now()->toDateString(),
            'ranksPayload' => [
                'activities' => $activities,
                'choices' => $choices,
                'canChoose' => $admin,
                'today' => now()->toDateString(),
                'statusLabels' => collect(['eligible', 'nearly', 'not_yet', 'no_rules', 'top', 'unranked'])
                    ->mapWithKeys(fn ($k) => [$k => __('ranks::messages.status_'.$k)])->all(),
                'defaultGradingTitle' => __('ranks::messages.grading_default_title'),
                'words' => ['finishTitle' => __('ranks::messages.finish_title'), 'finishConfirm' => __('ranks::messages.finish_confirm'), 'finish' => __('ranks::messages.finish_grading'),
                    'classes' => __('ranks::messages.classes'), 'months' => __('ranks::messages.months'), 'years' => __('ranks::messages.years'), 'next' => __('ranks::messages.next'),
                    'eligible' => __('ranks::messages.eligible_count', ['n' => '__N__'])],
                'groupLabels' => collect(['kids', 'adult', 'master', 'geup', 'poom', 'dan', 'kyu'])
                    ->mapWithKeys(fn ($g) => [$g => __('ranks::messages.group_'.$g)])->all(),
                'urls' => [
                    'set' => route('admin.club.ranks.set', [$club->slug, '__L__', '__M__']),
                    'history' => route('admin.club.ranks.history', [$club->slug, '__L__', '__M__']),
                    'package' => route('admin.club.ranks.package', [$club->slug, '__L__']),
                    'requirements' => route('admin.club.ranks.requirements', [$club->slug, '__L__']),
                    'gradings' => route('admin.club.ranks.gradings.store', [$club->slug, '__L__']),
                    'grading' => route('admin.club.ranks.gradings.show', [$club->slug, '__L__', '__G__']),
                ],
            ],
        ]);
    }

    /** Set (or correct) a member's rank. JSON; patches the row in place. */
    public function setRank(Request $request, Tenant $club, ActivityPackage $link, User $member)
    {
        $this->enabled();
        $this->guardLink($request, $club, $link);
        abort_unless($this->ranks->memberIds($link)->contains($member->id), 404);

        $v = $request->validate([
            'belt' => 'required|string|max:40',
            'grade' => 'nullable|string|max:40',
            'since' => 'required|date|before_or_equal:today',
            'note' => 'nullable|string|max:500',
        ]);

        $row = $this->ranks->set($link, $member, $v['belt'], $v['grade'] ?? null, $v['since'], $request->user(), $v['note'] ?? null);

        $row = $this->eligibility->annotate($link, $this->ranks->ladder($link), [$row])[0];
        \App\Ranks\Ranks::forgetBadge($club);

        Log::info('Rank set', ['by' => $request->user()->id, 'activity' => $link->activity_id, 'member' => $member->id, 'belt' => $v['belt'], 'grade' => $v['grade'] ?? null]);

        return response()->json(['success' => true, 'message' => __('ranks::messages.saved'), 'member' => $row]);
    }

    /** A member's promotion history in the activity. */
    public function history(Request $request, Tenant $club, ActivityPackage $link, User $member)
    {
        $this->enabled();
        $this->guardLink($request, $club, $link);
        abort_unless($this->ranks->memberIds($link)->contains($member->id), 404);

        return response()->json(['success' => true, 'history' => $this->ranks->history($link, $member)]);
    }

    /** The club says which sport package an activity is (or that it is none). Admins only. */
    public function choosePackage(Request $request, Tenant $club, ActivityPackage $link)
    {
        $this->enabled();
        $this->authorizeClub($club);
        abort_unless($link->activity && (int) $link->activity->tenant_id === (int) $club->id, 404);

        $v = $request->validate(['sport' => 'nullable|string|max:32', 'style' => 'nullable|string|max:64']);
        $this->links->choose($link->activity, $v['sport'] ?: null, $v['style'] ?? null);
        $link->refresh();
        \App\Ranks\Ranks::forgetBadge($club);

        return response()->json([
            'success' => true,
            'message' => __('ranks::messages.package_saved'),
            'activity' => $this->activityPayload($link->load('activity')),
        ]);
    }

    /** A ranked activity's club requirements for each belt. */
    public function saveRequirements(Request $request, Tenant $club, ActivityPackage $link)
    {
        $this->enabled();
        $this->guardLink($request, $club, $link);
        $v = $request->validate([
            'rows' => 'present|array|max:60',
            'rows.*.min_classes' => 'nullable|integer|min:0|max:999',
            'rows.*.min_months' => 'nullable|integer|min:0|max:240',
            'rows.*.min_age' => 'nullable|integer|min:0|max:99',
        ]);
        $this->eligibility->save($link, $this->ranks->ladder($link), $v['rows']);
        \App\Ranks\Ranks::forgetBadge($club);

        Log::info('Grading requirements saved', ['by' => $request->user()->id, 'activity' => $link->activity_id]);

        return response()->json(['success' => true, 'message' => __('ranks::messages.requirements_saved'), 'activity' => $this->activityPayload($link->load('activity'))]);
    }

    // ─── Grading sessions ──────────────────────────────────────────────────

    public function createGrading(Request $request, Tenant $club, ActivityPackage $link)
    {
        $this->enabled();
        $this->guardLink($request, $club, $link);
        $v = $request->validate([
            'title' => 'required|string|max:120',
            'held_on' => 'required|date',
            'candidates' => 'required|array|min:1|max:200',
            'candidates.*.uuid' => 'required|uuid',
            'candidates.*.belt' => 'required|string|max:40',
            'candidates.*.grade' => 'nullable|string|max:40',
        ]);
        $g = $this->gradings->create($link, $v['title'], $v['held_on'], $v['candidates'], $request->user());
        Log::info('Grading planned', ['by' => $request->user()->id, 'activity' => $link->activity_id, 'grading' => $g->id]);

        return response()->json(['success' => true, 'message' => __('ranks::messages.grading_saved'),
            'grading' => $this->gradings->detail($link, $g), 'activity' => $this->activityPayload($link->load('activity'))]);
    }

    public function showGrading(Request $request, Tenant $club, ActivityPackage $link, Grading $grading)
    {
        $this->enabled();
        $this->guardGrading($request, $club, $link, $grading);

        return response()->json(['success' => true, 'grading' => $this->gradings->detail($link, $grading)]);
    }

    public function recordGrading(Request $request, Tenant $club, ActivityPackage $link, Grading $grading)
    {
        $this->enabled();
        $this->guardGrading($request, $club, $link, $grading);
        $v = $request->validate([
            'results' => 'present|array|max:200',
            'results.*' => 'string|in:pending,passed,failed,absent',
            'finish' => 'boolean',
        ]);
        $g = $this->gradings->record($link, $grading, $v['results'], (bool) ($v['finish'] ?? false), $request->user());
        \App\Ranks\Ranks::forgetBadge($club);
        Log::info('Grading results recorded', ['by' => $request->user()->id, 'grading' => $g->id, 'finished' => $g->status === 'done']);

        return response()->json(['success' => true,
            'message' => $g->status === 'done' ? __('ranks::messages.grading_finished') : __('ranks::messages.grading_saved'),
            'grading' => $this->gradings->detail($link, $g), 'activity' => $this->activityPayload($link->load('activity'))]);
    }

    public function deleteGrading(Request $request, Tenant $club, ActivityPackage $link, Grading $grading)
    {
        $this->enabled();
        $this->guardGrading($request, $club, $link, $grading);
        abort_if($grading->status === 'done', 422, __('ranks::messages.grading_done_already'));
        $grading->delete();

        return response()->json(['success' => true, 'message' => __('ranks::messages.grading_deleted'), 'activity' => $this->activityPayload($link->load('activity'))]);
    }

    private function guardGrading(Request $request, Tenant $club, ActivityPackage $link, Grading $grading): void
    {
        $this->guardLink($request, $club, $link);
        abort_unless((int) $grading->activity_id === (int) $link->activity_id, 404);
    }

    /** Everything the page draws for one activity. */
    private function activityPayload(ActivityPackage $l): array
    {
        $book = $this->ranks->book($l);
        $ranked = (bool) $book?->hasRanks();
        $ladder = $ranked ? $this->ranks->ladder($l) : [];

        return [
            'uuid' => $l->uuid,
            'name' => $l->activity->name,
            'sport' => $l->sport ?: null,
            'style' => $l->style ?: null,
            'package' => $book?->styleLabel,
            'ranked' => $ranked,
            'matched_by' => $l->matched_by,
            'ladder' => $ladder,
            'requirements' => $ranked ? (object) $this->eligibility->requirements($l) : (object) [],
            'members' => $ranked ? $this->eligibility->annotate($l, $ladder, $this->ranks->roster($l)) : [],
            'gradings' => $ranked ? $this->gradings->list($l) : [],
        ];
    }

    /** The link belongs to this club, has ranks, and the viewer may manage it. One answer for every miss. */
    private function guardLink(Request $request, Tenant $club, ActivityPackage $link): void
    {
        abort_unless($link->activity && (int) $link->activity->tenant_id === (int) $club->id && $this->ranks->book($link)?->hasRanks(), 404);
        abort_unless($this->canManageClub($club) || $this->ranks->canManage($request->user(), $link), 403);
    }
}
