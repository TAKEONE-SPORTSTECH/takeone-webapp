<?php

namespace App\Ranks\Support;

use App\Members\Models\User;
use App\Ranks\Models\ActivityPackage;
use App\Ranks\Models\Grading;
use App\Ranks\Models\GradingCandidate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Grading sessions for a ranked activity: plan one, pick who is graded and for
 * which belt, record each result, and finish it — which promotes everyone who
 * passed, once, through MemberRanks::set() (the history points back here).
 */
final class Gradings
{
    public function __construct(private MemberRanks $ranks) {}

    /** @return array<int, array> newest first */
    public function list(ActivityPackage $link): array
    {
        return Grading::where('activity_id', $link->activity_id)->withCount([
            'candidates',
            'candidates as passed_count' => fn ($q) => $q->where('result', 'passed'),
        ])->orderByDesc('held_on')->orderByDesc('id')->limit(50)->get()
            ->map(fn (Grading $g) => [
                'uuid' => $g->uuid, 'title' => $g->title, 'held_on' => $g->held_on->toDateString(), 'status' => $g->status,
                'candidates' => $g->candidates_count, 'passed' => $g->passed_count,
            ])->all();
    }

    /** One grading with its candidates, ready to draw. */
    public function detail(ActivityPackage $link, Grading $g): array
    {
        $ladder = collect($this->ranks->ladder($link))->keyBy('value');
        $cands = $g->candidates()->with('user:id,uuid,full_name,name,gender,profile_picture')->get()
            ->map(fn (GradingCandidate $c) => [
                'uuid' => $c->user?->uuid,
                'name' => $c->user?->full_name ?: $c->user?->name,
                'photo' => $c->user?->profile_picture ? file_url($c->user->profile_picture) : \App\Members\Support\Avatar::placeholder($c->user?->gender),
                'target' => ['belt' => $c->target_belt, 'grade' => $c->target_grade,
                    'label' => ($ladder[$c->target_belt]['label'] ?? $c->target_belt).($c->target_grade ? ' · '.$c->target_grade : ''),
                    'icon' => $ladder[$c->target_belt]['icon'] ?? null],
                'result' => $c->result,
            ])->sortBy('name')->values()->all();

        return ['uuid' => $g->uuid, 'title' => $g->title, 'held_on' => $g->held_on->toDateString(), 'status' => $g->status, 'candidates' => $cands];
    }

    /**
     * Plan a grading. Candidates must train in the activity; their target belt
     * must be on the ladder.
     *
     * @param  array<int, array{uuid: string, belt: string, grade?: ?string}>  $candidates
     */
    public function create(ActivityPackage $link, string $title, string $heldOn, array $candidates, User $by): Grading
    {
        $ladder = collect($this->ranks->ladder($link))->keyBy('value');
        $members = $this->ranks->memberIds($link);
        $users = User::whereIn('uuid', collect($candidates)->pluck('uuid'))->pluck('id', 'uuid');

        $g = DB::transaction(function () use ($link, $title, $heldOn, $candidates, $by, $ladder, $members, $users) {
            $g = Grading::create(['activity_id' => $link->activity_id, 'title' => mb_substr(strip_tags($title), 0, 120), 'held_on' => Carbon::parse($heldOn)->toDateString(), 'status' => 'planned', 'created_by' => $by->id]);
            foreach ($candidates as $c) {
                $uid = $users[$c['uuid']] ?? null;
                $step = $ladder[$c['belt']] ?? null;
                if (! $uid || ! $members->contains($uid) || ! $step) {
                    continue;   // never graded: not in this activity, or not a belt of this sport
                }
                $grade = isset($c['grade']) && in_array($c['grade'], $step['grades'], true) ? $c['grade'] : null;
                GradingCandidate::firstOrCreate(['grading_id' => $g->id, 'user_id' => $uid], ['target_belt' => $c['belt'], 'target_grade' => $grade]);
            }

            return $g;
        });

        app(RankNotices::class)->invited($link, $g, $by);

        return $g;
    }

    /** Record results, and optionally finish — promoting every pass exactly once. */
    public function record(ActivityPackage $link, Grading $g, array $results, bool $finish, User $by): Grading
    {
        abort_if($g->status === 'done', 422, __('ranks::messages.grading_done_already'));
        $users = User::whereIn('uuid', array_keys($results))->pluck('id', 'uuid');

        DB::transaction(function () use ($link, $g, $results, $finish, $by, $users) {
            foreach ($results as $uuid => $result) {
                if (! in_array($result, GradingCandidate::RESULTS, true) || ! isset($users[$uuid])) {
                    continue;
                }
                GradingCandidate::where('grading_id', $g->id)->where('user_id', $users[$uuid])->update(['result' => $result]);
            }
            if (! $finish) {
                return;
            }
            $locked = Grading::whereKey($g->id)->lockForUpdate()->first();
            if ($locked->status === 'done') {
                return;
            }
            $on = $g->held_on->isFuture() ? now()->toDateString() : $g->held_on->toDateString();
            foreach (GradingCandidate::where('grading_id', $g->id)->where('result', 'passed')->with('user')->get() as $c) {
                if ($c->user) {
                    $this->ranks->set($link, $c->user, $c->target_belt, $c->target_grade, $on, $by, $g->title, $g->id);
                }
            }
            $locked->update(['status' => 'done', 'finished_at' => now()]);
        });

        return $g->fresh();
    }
}
