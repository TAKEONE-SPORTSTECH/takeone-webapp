<?php

namespace App\Platform\Controllers;

use App\Events\Support\EntryPhoto;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RestoreBackupRequest;
use App\Members\Requests\StorePlatformMemberRequest;
use App\Members\Requests\HealthRecordRequest;
use App\Members\Requests\TournamentRequest;
use App\Http\Requests\UploadImageRequest;
use App\Members\Models\Attendance;
use App\Clubs\Models\Business;
use App\Clubs\Models\ClubMemberSubscription;
use App\Clubs\Models\Invoice;
use App\Members\Models\Membership;
use App\Clubs\Models\Tenant;
use App\Members\Models\TournamentEvent;
use App\Members\Models\User;
use App\Members\Support\MemberFilters;
use App\Traits\StoresBase64Images;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Support\StoragePath;

class PlatformController extends Controller
{
    use StoresBase64Images;

    /**
     * Display the platform admin dashboard.
     */
    public function index()
    {
        // Get all clubs with counts
        $clubs = Tenant::with(['owner'])
            ->withCount(['members', 'packages', 'instructors'])
            ->latest()
            ->get();

        $clubsCount = $clubs->count();

        return view('platform::index', compact('clubs', 'clubsCount'));
    }

    /**
     * Platform admin landing. Desktop keeps its existing behaviour (straight to
     * the clubs table); mobile gets a purpose-built dashboard with KPIs and
     * section navigation.
     */
    public function home(Request $request)
    {
        // Mobile keeps its dedicated dashboard — untouched.
        if ($request->attributes->get('is_mobile')) {
            $stats = [
                'clubs' => Tenant::count(),
                'members' => User::count(),
                'businesses' => Business::count(),
                'businessesPending' => Business::where('status', Business::STATUS_PENDING)->count(),
            ];

            return view('platform::mobile.index', compact('stats'));
        }

        // ── Desktop platform control-center dashboard ──
        $startOfThisMonth = now()->startOfMonth();
        $startOfLastMonth = now()->subMonthNoOverflow()->startOfMonth();

        // 12-month new-rows series for a model, aligned to calendar months.
        // Bucketed in PHP so it works across DB drivers (sqlite has no DATE_FORMAT).
        $monthly = function (string $model): array {
            $buckets = $model::query()
                ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
                ->pluck('created_at')
                ->reduce(function (array $acc, $date) {
                    $key = \Illuminate\Support\Carbon::parse($date)->format('Y-m');
                    $acc[$key] = ($acc[$key] ?? 0) + 1;

                    return $acc;
                }, []);

            $out = [];
            for ($i = 11; $i >= 0; $i--) {
                $out[] = (int) ($buckets[now()->subMonths($i)->format('Y-m')] ?? 0);
            }

            return $out;
        };

        $monthLabels = [];
        for ($i = 11; $i >= 0; $i--) {
            $monthLabels[] = now()->subMonths($i)->format('M');
        }

        $stats = [
            'clubs' => Tenant::count(),
            'members' => User::count(),
            'businesses' => Business::count(),
            'businessesPending' => Business::where('status', Business::STATUS_PENDING)->count(),
            'trainers' => \App\Clubs\Models\ClubInstructor::count(),
            'packages' => \App\Clubs\Models\ClubPackage::count(),
            'clubsThisMonth' => Tenant::where('created_at', '>=', $startOfThisMonth)->count(),
            'membersThisMonth' => User::where('created_at', '>=', $startOfThisMonth)->count(),
            'membersLastMonth' => User::whereBetween('created_at', [$startOfLastMonth, $startOfThisMonth])->count(),
        ];

        $series = [
            'labels' => $monthLabels,
            'members' => $monthly(User::class),
            'clubs' => $monthly(Tenant::class),
        ];

        $topClubs = Tenant::withCount('members')->orderByDesc('members_count')->take(5)->get();
        $recentClubs = Tenant::with('owner')->withCount('members')->latest()->take(5)->get();
        $recentMembers = User::latest()->take(6)->get(['id', 'uuid', 'full_name', 'gender', 'profile_picture', 'created_at', 'updated_at']);
        $pendingBusinesses = Business::with('owner')->withCount('clubs')
            ->where('status', Business::STATUS_PENDING)->latest()->take(4)->get();

        return view('platform::dashboard', compact(
            'stats', 'series', 'topClubs', 'recentClubs', 'recentMembers', 'pendingBusinesses'
        ));
    }

    /**
     * Platform-wide settings (super-admin).
     */
    public function settings()
    {
        $realtime = app(\Takeone\Realtime\RealtimeManager::class);
        $whatsapp = app(\App\Services\WhatsAppManager::class);

        return view('platform::settings', [
            'toastsEnabled'    => \App\Platform\Models\PlatformSetting::getBool('toasts_enabled', true),
            'realtimeSettings' => $realtime->adminSettings(),
            'realtimeStatus'   => $realtime->probe(),
            'whatsappSettings' => $whatsapp->adminSettings(),
            'cornerSides'      => \App\Sports\Combat\CornerSide::all(),
        ]);
    }

    /**
     * Which side the red corner stands on, per combat sport — every hall
     * screen and scoring table of that sport follows it. The sport declares a
     * default; this overrides it, in case that convention was got wrong.
     * `default` clears the override. Super-admin only (route group).
     */
    public function updateCornerSide(Request $request)
    {
        $sports = array_keys(app(\App\Sports\Combat\SportRegistry::class)->all());
        $data = $request->validate([
            'sport' => ['required', 'string', \Illuminate\Validation\Rule::in($sports)],
            'side'  => 'required|in:left,right,default',
        ]);

        \App\Sports\Combat\CornerSide::setOverride($data['sport'], $data['side'] === 'default' ? null : $data['side']);

        \Illuminate\Support\Facades\Log::info('Corner side changed', [
            'by' => $request->user()->id, 'sport' => $data['sport'], 'side' => $data['side'],
        ]);

        $row = collect(\App\Sports\Combat\CornerSide::all())->firstWhere('sport', $data['sport']);

        return response()->json([
            'success' => true,
            'message' => __('platform.corner_side_saved'),
            'corner'  => $row,
        ]);
    }

    public function updateSettings(Request $request)
    {
        \App\Platform\Models\PlatformSetting::set('toasts_enabled', $request->boolean('toasts_enabled'));

        return redirect()->route('admin.platform.settings')
            ->with('success', 'Settings updated.');
    }

    /**
     * DANGER ZONE — wipe the platform back to its clean baseline.
     *
     * Runs the `takeone:reset-baseline` command (SQLite backup → force-delete every
     * club/member/user-data → re-seed the TAKEONE SportsTech baseline). Keeps only the
     * activity catalog, roles/permissions and the super-admin user.
     *
     * Guardrails: super-admin group middleware + explicit re-check here, a tight
     * `reset-baseline` rate limit, and a required typed confirmation phrase.
     */
    public function resetBaseline(Request $request)
    {
        // Defense in depth — the route group already enforces role:super-admin,
        // but never rely on middleware alone for a catastrophic action.
        $actor = Auth::user();
        if (! $actor || ! $actor->hasRole('super-admin')) {
            abort(403);
        }

        // Require the exact confirmation phrase so this can never fire accidentally.
        $request->validate([
            'confirmation' => ['required', 'string', 'in:RESET'],
        ], [
            'confirmation.in' => 'Type RESET exactly to confirm.',
        ]);

        \Illuminate\Support\Facades\Log::warning('Platform baseline reset triggered from settings.', [
            'user_id' => $actor->id,
            'email'   => $actor->email,
            'ip'      => $request->ip(),
        ]);

        // The wipe + re-seed can take a while; don't let PHP time it out mid-way.
        @set_time_limit(0);

        try {
            $exit = \Illuminate\Support\Facades\Artisan::call('takeone:reset-baseline', ['--force' => true]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Platform baseline reset failed.', [
                'user_id' => $actor->id,
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Reset failed: '.$e->getMessage(),
            ], 500);
        }

        if ($exit !== 0) {
            return response()->json([
                'success' => false,
                'message' => 'Reset command exited with an error. Check the logs.',
            ], 500);
        }

        return response()->json([
            'success'  => true,
            'message'  => 'Platform reset to the clean baseline.',
            'redirect' => route('admin.platform.index'),
        ]);
    }

    public function updateWhatsAppSettings(Request $request, \App\Services\WhatsAppManager $whatsapp)
    {
        $data = $request->validate([
            'enabled'      => 'sometimes|boolean',
            'base_url'     => 'required|string|max:255|url',
            'session_name' => 'required|string|max:255',
            'api_key'      => 'nullable|string|max:255',
        ]);
        $data['enabled'] = $request->boolean('enabled');

        $whatsapp->save($data);

        if ($request->expectsJson()) {
            return response()->json([
                'success'  => true,
                'message'  => 'WhatsApp settings saved.',
                'settings' => $whatsapp->adminSettings(),
            ]);
        }

        return back()->with('success', 'WhatsApp settings saved.');
    }

    public function testWhatsAppConnection(\App\Services\WhatsAppManager $whatsapp)
    {
        $result = $whatsapp->probe();

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function sendTestWhatsAppMessage(Request $request, \App\Services\WhatsAppManager $whatsapp)
    {
        $data = $request->validate([
            'phone' => 'required|string|max:32',
        ]);

        $result = $whatsapp->sendTestMessage($whatsapp->sessionName(), $data['phone'], 'This is a test message from TAKEONE.');

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Display the audit log.
     */
    public function auditLog(Request $request)
    {
        $search = $request->input('search');
        $logName = $request->input('log_name');
        $event = $request->input('event');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $logs = \Spatie\Activitylog\Models\Activity::with('causer')
            ->when($search, function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('subject_type', 'like', "%{$search}%")
                    ->orWhereHas('causer', fn ($u) => $u->where('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            })
            ->when($logName, fn ($q) => $q->where('log_name', $logName))
            ->when($event, fn ($q) => $q->where('event', $event))
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $logNames = \Spatie\Activitylog\Models\Activity::distinct()->pluck('log_name')->filter()->sort()->values();

        $mobile = $request->attributes->get('is_mobile') && view()->exists('platform::mobile.audit-log');

        return view($mobile ? 'platform::mobile.audit-log' : 'platform::audit-log.index', compact('logs', 'search', 'logName', 'event', 'dateFrom', 'dateTo', 'logNames'));
    }

    /**
     * Display all clubs management page.
     */
    public function clubs(Request $request)
    {
        $search = $request->input('search');

        // Whitelisted sort options (key => closure). Anything else falls back to default.
        $sortOptions = [
            'newest' => fn ($q) => $q->latest(),
            'oldest' => fn ($q) => $q->oldest(),
            'name_asc' => fn ($q) => $q->orderBy('club_name'),
            'name_desc' => fn ($q) => $q->orderByDesc('club_name'),
            'members' => fn ($q) => $q->orderByDesc('members_count')->latest(),
            'packages' => fn ($q) => $q->orderByDesc('packages_count')->latest(),
        ];
        $sort = array_key_exists($request->input('sort'), $sortOptions) ? $request->input('sort') : 'newest';

        $query = Tenant::with(['owner', 'members'])
            ->withCount(['members', 'packages', 'instructors'])
            ->when($search, function ($query, $search) {
                $query->where('club_name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('owner', fn ($q) => $q->where('full_name', 'like', "%{$search}%"));
            });

        $sortOptions[$sort]($query);

        $clubs = $query->paginate(12)->withQueryString();

        // AJAX search / sort / pagination returns just the results region for in-place swap.
        if ($request->ajax()) {
            return view('platform::clubs._results', compact('clubs', 'search'));
        }

        $mobile = $request->attributes->get('is_mobile') && view()->exists('platform::mobile.clubs');

        return view($mobile ? 'platform::mobile.clubs' : 'platform::clubs.index', compact('clubs', 'search', 'sort'));
    }

    /**
     * Display all members management page.
     */
    public function members(Request $request)
    {
        $search = $request->input('search');

        // Whitelisted sort options (key => closure). Anything else falls back to default.
        $sortOptions = [
            'gender' => fn ($q) => $q->orderBy('gender')->latest(),
            'newest' => fn ($q) => $q->latest(),
            'oldest' => fn ($q) => $q->oldest(),
            'name_asc' => fn ($q) => $q->orderBy('full_name'),
            'name_desc' => fn ($q) => $q->orderByDesc('full_name'),
            'clubs' => fn ($q) => $q->orderByDesc('member_clubs_count')->latest(),
            'age_young' => fn ($q) => $q->orderByDesc('birthdate'),
            'age_old' => fn ($q) => $q->orderBy('birthdate'),
        ];
        $sort = array_key_exists($request->input('sort'), $sortOptions) ? $request->input('sort') : 'newest';

        // The advanced filter. Whitelisted inside MemberFilters — nothing the
        // client sends reaches the query except as a bound parameter.
        $filters = MemberFilters::from($request);

        $query = User::with(['memberClubs', 'dependents', 'guardians.guardian', 'latestHealthRecord'])
            ->withCount('memberClubs')
            ->when($search, function ($query, $search) {
                // Grouped: the search is a chain of ORs, and without its own
                // closure the first filter clause added below would be swallowed
                // by it and silently widen the result set.
                $query->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('nationality', 'like', "%{$search}%")
                        ->orWhere('gender', 'like', "%{$search}%")
                        ->orWhereRaw("JSON_EXTRACT(mobile, '$.number') LIKE ?", ["%{$search}%"])
                        ->orWhereHas('memberClubs', fn ($c) => $c->where('club_name', 'like', "%{$search}%"));
                });
            });

        $filters->apply($query);

        // Apply the chosen sort closure to the query.
        $sortOptions[$sort]($query);

        $members = $query->paginate(20)->withQueryString();

        // Everything the filter UI needs to draw itself, and the state to seed it.
        $filterState = $filters->toArray();
        $filterCount = $filters->count();
        $filterClubs = MemberFilters::clubOptions();
        $filterNationalities = MemberFilters::nationalityOptions();

        $isMobile = (bool) $request->attributes->get('is_mobile');

        // AJAX search / pagination returns just the results region for in-place swap
        // (mobile gets its own compact card layout).
        if ($request->ajax()) {
            $resultsView = $isMobile && view()->exists('platform::members._results-mobile')
                ? 'platform::members._results-mobile'
                : 'platform::members._results';

            return view($resultsView, compact('members', 'search', 'sort', 'filterCount'));
        }

        $mobile = $isMobile && view()->exists('platform::mobile.members');

        return view(
            $mobile ? 'platform::mobile.members' : 'platform::members.index',
            compact('members', 'search', 'sort', 'filterState', 'filterCount', 'filterClubs', 'filterNationalities')
        );
    }

    /**
     * Make a person a member of a club WITHOUT a package: somebody newly on the
     * platform who has trained at the club, in a sport, since long before it.
     * Creates (or re-activates) the membership and records the history as a
     * club affiliation dated from when they started — the platform's own
     * "member since" — with the sport as its skill. No subscription, no invoice.
     */
    /** Accounts that look like this member (same phone/email/name/birthday), or a search for any other. */
    public function mergeCandidates(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $user = User::findOrFail($id);
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 80);
        $merger = app(\App\Members\Support\AccountMerger::class);

        $rows = $q !== ''
            ? $merger->search($q, $user)->map(fn ($u) => ['user' => $u, 'reasons' => []])
            : $merger->candidates($user);

        return response()->json([
            'success' => true,
            'self' => $this->mergeSummary($user, $merger),
            'results' => $rows->map(fn ($r) => $this->mergeSummary($r['user'], $merger) + ['reasons' => $r['reasons']])->values(),
        ]);
    }

    /** Scan: every pair of accounts that look like one person, to examine before merging. */
    public function memberDuplicates(Request $request): \Illuminate\Http\JsonResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $merger = app(\App\Members\Support\AccountMerger::class);
        $pairs = $merger->scan(100);
        $users = User::whereIn('id', collect($pairs)->flatMap(fn ($p) => [$p['a'], $p['b']])->unique())->get()->keyBy('id');
        $summary = [];
        $one = function (int $id) use ($users, $merger, &$summary) {
            return $summary[$id] ??= $this->mergeSummary($users[$id], $merger);
        };

        return response()->json([
            'success' => true,
            'pairs' => collect($pairs)->filter(fn ($p) => isset($users[$p['a']], $users[$p['b']]))
                ->map(fn ($p) => ['key' => $p['a'].'-'.$p['b'], 'a' => $one($p['a']), 'b' => $one($p['b']), 'reasons' => $p['reasons'], 'strong' => $p['score'] >= 5])
                ->values(),
        ], 200, ['Cache-Control' => 'no-store, private']);
    }

    /** "Not the same person": the scan stops offering this pair. */
    public function dismissMemberDuplicate(Request $request): \Illuminate\Http\JsonResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $data = $request->validate(['a' => ['required', 'integer', 'different:b'], 'b' => ['required', 'integer']]);
        $a = User::findOrFail($data['a']);
        $b = User::findOrFail($data['b']);
        app(\App\Members\Support\AccountMerger::class)->dismiss($a, $b, $request->user());
        activity()->causedBy($request->user())->performedOn($a)->withProperties(['other' => $b->uuid])->log('platform.members_not_duplicate');

        return response()->json(['success' => true, 'message' => 'Marked as two different people.', 'pair' => min($a->id, $b->id).'-'.max($a->id, $b->id)]);
    }

    public function mergePreview(Request $request): \Illuminate\Http\JsonResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $data = $request->validate(['keep' => ['required', 'integer'], 'merge' => ['required', 'integer']]);
        $keep = User::findOrFail($data['keep']);
        $dup = User::findOrFail($data['merge']);
        $merger = app(\App\Members\Support\AccountMerger::class);
        $plan = $merger->plan($dup, $keep);

        return response()->json([
            'success' => true,
            'keep' => $this->mergeSummary($keep, $merger),
            'merge' => $this->mergeSummary($dup, $merger),
            'blockers' => $plan['blockers'],
            'warnings' => $plan['warnings'],
            // The one account both become: field by field, where each value comes from.
            'combined' => collect($merger->combine($dup, $keep))
                ->map(fn ($r) => \Illuminate\Support\Arr::except($r, ['combined']))->values(),
            'total' => $plan['total'],
            'counts' => collect($plan['counts'])->map(fn ($n, $t) => ['label' => \Illuminate\Support\Str::of($t)->replace('_', ' ')->ucfirst()->toString(), 'count' => $n])->values()->take(12),
        ]);
    }

    public function mergeMembers(Request $request): \Illuminate\Http\JsonResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $data = $request->validate([
            'keep' => ['required', 'integer', 'different:merge'],
            'merge' => ['required', 'integer'],
            'confirm' => ['required', 'string', 'in:MERGE'],
            // Where both accounts hold a different value: the fields to take from the other one.
            'take' => ['nullable', 'array', 'max:20'],
            'take.*' => ['string', 'in:'.implode(',', array_keys(\App\Members\Support\AccountMerger::FIELDS))],
        ], ['confirm.in' => 'Type MERGE to confirm.']);
        $keep = User::findOrFail($data['keep']);
        $dup = User::findOrFail($data['merge']);

        try {
            $log = app(\App\Members\Support\AccountMerger::class)->merge($dup, $keep, $request->user(), array_values(array_unique($data['take'] ?? [])));
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        $this->pushMembersRefresh();

        return response()->json([
            'success' => true,
            'message' => ($dup->full_name ?: $dup->name).' was merged into '.($keep->full_name ?: $keep->name).'.',
            'merge' => ['uuid' => $log->uuid, 'kept' => $keep->id, 'merged' => $dup->id, 'rows' => count($log->moved), 'removed' => count($log->removed)],
        ]);
    }

    public function undoMemberMerge(Request $request, string $uuid): \Illuminate\Http\JsonResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $log = \App\Members\Models\AccountMerge::where('uuid', $uuid)->firstOrFail();
        try {
            app(\App\Members\Support\AccountMerger::class)->undo($log);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Illuminate\Database\QueryException $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'The merge could not be undone automatically — the accounts have changed since. Restore from the backup instead.'], 422);
        }
        activity()->causedBy($request->user())->withProperties(['merge' => $log->uuid])->log('platform.members_merge_undone');
        $this->pushMembersRefresh();

        return response()->json(['success' => true, 'message' => 'Merge undone — both accounts are back as they were.', 'merge' => ['uuid' => $log->uuid, 'kept' => $log->kept_user_id, 'merged' => $log->merged_user_id]]);
    }

    /** Every other super-admin with the members list open re-reads it (refresh signal only). */
    private function pushMembersRefresh(): void
    {
        rescue(function () {
            $batch = User::whereHas('roles', fn ($q) => $q->where('slug', 'super-admin'))
                ->where('id', '!=', Auth::id())->pluck('id')
                ->map(fn ($uid) => ['topic' => \Takeone\Realtime\Support\Topics::user((int) $uid, 'members'), 'payload' => ['action' => 'refresh']])
                ->values()->all();
            if ($batch !== []) {
                \Realtime()->publishMany($batch);
            }
        }, report: true);
    }

    private function mergeSummary(User $u, \App\Members\Support\AccountMerger $merger): array
    {
        $clubs = DB::table('memberships')->join('tenants', 'tenants.id', '=', 'memberships.tenant_id')
            ->where('memberships.user_id', $u->id)->where('memberships.status', 'active')->limit(4)->pluck('tenants.club_name');

        return [
            'id' => $u->id,
            'name' => $u->full_name ?: $u->name,
            'photo' => \App\Members\Support\MemberLookup::photo($u),
            'email' => $u->email,
            'mobile' => is_array($u->mobile) && filled($u->mobile['number'] ?? null) ? trim(($u->mobile['code'] ?? '').' '.$u->mobile['number']) : null,
            'birthdate' => $u->birthdate?->format('d/m/Y'),
            'gender' => $u->gender,
            'nationality' => $u->nationality,
            'clubs' => $clubs->values(),
            'joined' => $u->created_at?->format('M Y'),
            'signs_in' => $merger->signsIn($u),
        ];
    }

    public function addClubMembership(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $user = User::findOrFail($id);
        $data = $request->validate([
            'club' => ['required', 'string', 'exists:tenants,slug'],
            'activity' => ['nullable', 'integer'],
            'since' => ['required', 'date_format:Y-m'],
        ]);
        $club = Tenant::where('slug', $data['club'])->firstOrFail();
        $since = \Carbon\Carbon::createFromFormat('!Y-m', $data['since'])->startOfMonth();
        if ($since->isFuture()) {
            return response()->json(['success' => false, 'message' => 'Member since cannot be in the future.'], 422);
        }
        $activity = ! empty($data['activity']) ? \App\Clubs\Models\ClubActivity::where('tenant_id', $club->id)->find($data['activity']) : null;
        if (! empty($data['activity']) && ! $activity) {
            return response()->json(['success' => false, 'message' => 'That sport is not one of this club\'s activities.'], 422);
        }

        $verify = app(\App\Members\Support\AchievementVerificationService::class);
        [$membership, $affiliation] = \Illuminate\Support\Facades\DB::transaction(function () use ($user, $club, $since, $activity, $verify, $request) {
            $membership = Membership::firstOrNew(['tenant_id' => $club->id, 'user_id' => $user->id]);
            $membership->status = 'active';
            $membership->save();

            // Their history at this club: the open affiliation, started at the earliest known date.
            $affiliation = \App\Clubs\Models\ClubAffiliation::where('member_id', $user->id)->where('tenant_id', $club->id)->whereNull('end_date')->first();
            if (! $affiliation) {
                $affiliation = \App\Clubs\Models\ClubAffiliation::create([
                    'member_id' => $user->id, 'tenant_id' => $club->id,
                    'club_name' => $club->getRawOriginal('club_name'), 'logo' => $club->logo,
                    'start_date' => $since->toDateString(),
                ]);
                $verify->recordByAdmin($affiliation, $request->user());
            } elseif (! $affiliation->start_date || $since->lt($affiliation->start_date)) {
                $affiliation->forceFill(['start_date' => $since->toDateString()])->save();
            }

            if ($activity) {
                $skill = \App\Members\Models\SkillAcquisition::where('club_affiliation_id', $affiliation->id)->where('activity_id', $activity->id)->first();
                if (! $skill) {
                    $skill = new \App\Members\Models\SkillAcquisition;
                    $skill->forceFill([
                        'club_affiliation_id' => $affiliation->id, 'user_id' => $user->id,
                        'activity_id' => $activity->id, 'activity_name' => $activity->getRawOriginal('name') ?? $activity->name,
                        'skill_name' => $activity->getRawOriginal('name') ?? $activity->name, 'start_date' => $since->toDateString(),
                        'duration_months' => max(1, (int) $since->diffInMonths(now())),
                    ])->save();
                    $verify->recordByAdmin($skill, $request->user());
                }
            }

            return [$membership, $affiliation];
        });

        activity()->causedBy($request->user())->performedOn($user)
            ->withProperties(['club' => $club->slug, 'since' => $since->format('Y-m'), 'activity' => $activity?->id])
            ->log('platform.member_added_to_club');
        rescue(fn () => \App\Members\Models\UserNotification::notifyUser((int) $user->id, 'club_membership_added',
            'You are a member of '.$club->club_name, [
                'actor_id' => $request->user()->id, 'tenant_id' => $club->id,
                'body' => 'Member since '.$since->format('F Y').($activity ? ' · '.$activity->name : '').'.',
                'icon' => 'bi-person-check',
            ]), report: true);

        return response()->json(['success' => true,
            'message' => ($user->full_name ?: $user->name).' is now a member of '.$club->club_name.' (since '.$since->format('M Y').').',
            'membership' => ['club' => $club->club_name, 'since' => $since->format('Y-m'), 'sport' => $activity?->name, 'status' => $membership->status]]);
    }

    public function memberPopup(User $user)
    {
        $membership = Membership::where('user_id', $user->id)->latest()->first();

        $subscriptions = ClubMemberSubscription::where('user_id', $user->id)
            ->where('type', 'regular')
            ->with(['package', 'package.tenant'])
            ->latest()
            ->get()
            ->map(function ($sub) {
                $clubName = $sub->package?->tenant?->name ?? 'N/A';

                return [
                    'id' => $sub->id,
                    'package' => ($sub->package?->name ?? 'N/A').' — '.$clubName,
                    'currency' => $sub->package?->currency ?? 'BHD',
                    'start_date' => $sub->start_date?->format('d M Y') ?? 'N/A',
                    'end_date' => $sub->end_date?->format('d M Y') ?? 'Ongoing',
                    'payment_status' => $sub->payment_status ?? 'pending',
                    'amount_due' => number_format((float) ($sub->amount_due ?? 0), 2),
                    'amount_paid' => number_format((float) ($sub->amount_paid ?? 0), 2),
                    'status' => $sub->status,
                    'is_active' => in_array($sub->status, ['active', 'pending']),
                    'has_proof' => (bool) $sub->proof_of_payment,
                    'approve_url' => $sub->package?->tenant
                        ? route('admin.club.subscriptions.approve-payment', [$sub->package->tenant->slug, $sub->id])
                        : null,
                    'proof_url' => ($sub->proof_of_payment && $sub->package?->tenant)
                        ? route('admin.club.subscriptions.payment-proof', [$sub->package->tenant->slug, $sub->id])
                        : null,
                ];
            });

        $phone = is_array($user->mobile)
            ? trim(($user->mobile['code'] ?? '').' '.($user->mobile['number'] ?? ''))
            : ($user->mobile ?? '');

        return response()->json([
            'id' => $user->id,
            'name' => $user->full_name,
            'initial' => mb_strtoupper(mb_substr($user->full_name ?? 'M', 0, 1, 'UTF-8'), 'UTF-8'),
            'has_picture' => (bool) $user->profile_picture,
            'picture_url' => $user->profile_picture
                ? file_url($user->profile_picture).'?v='.$user->updated_at->timestamp
                : null,
            'gender' => $user->gender ?? 'Male',
            // ISO-3166 alpha-2, which is what the flag is built from client-side.
            // Anything else (a full country name on an old row) is dropped rather
            // than rendered as two wrong letters.
            'nationality' => preg_match('/^[A-Za-z]{2}$/', (string) $user->nationality)
                ? mb_strtoupper($user->nationality)
                : null,
            'phone' => $phone ?: 'N/A',
            'email' => $user->email ?? 'N/A',
            'age' => $user->age ? $user->age.' years' : 'N/A',
            'since' => $membership ? $membership->created_at->format('d/m/Y') : $user->created_at->format('d/m/Y'),
            'profile_url' => route('member.show', $user->uuid),
            // The safe public profile — what everyone else sees of this person.
            // Null when this admin may not open it (a block either way), so the
            // popup hides the control rather than offering a link into a 403.
            'public_url' => $user->canViewPublicProfile(Auth::user())
                ? route('people.show', $user->uuid)
                : null,
            // Admin popup QR points to the member's management profile, not the public wall.
            'qr_url' => route('member.show', $user->uuid),
            'qr_svg_url' => route('qr.member.svg', ['user' => $user->id, 'target' => 'manage']),
            'qr_poster_url' => route('qr.member', ['user' => $user->id, 'target' => 'manage']),
            'subscriptions' => $subscriptions,
            'context' => 'platform',
            'enroll_data_url' => route('admin.platform.members.enroll-data', $user->id),
            // Manual email verification (super-admin — enforced by the admin route group).
            'verified' => $user->hasVerifiedEmail(),
            'verify_email_url' => route('admin.platform.members.verify-email', $user->id),
            // Delete account (super-admin only — platform context). Never own account.
            'delete_url' => ($user->id !== Auth::id())
                ? route('admin.platform.members.destroy', $user->id)
                : null,
        ]);
    }

    /**
     * Manually mark a user's email as verified so they can log in without the
     * email link. Super-admin only (enforced by the admin route group).
     */
    public function verifyMemberEmail(User $user)
    {
        if ($user->hasVerifiedEmail()) {
            return response()->json(['success' => true, 'message' => $user->full_name.' is already verified.']);
        }

        $user->markEmailAsVerified();

        return response()->json([
            'success' => true,
            'message' => $user->full_name.' has been verified and can now log in.',
        ]);
    }

    public function memberEnrollData(User $user)
    {
        $clubs = Membership::where('user_id', $user->id)
            ->with('tenant')
            ->get()
            ->filter(fn ($m) => $m->tenant !== null)
            ->map(fn ($m) => [
                'id' => $m->tenant->id,
                'name' => $m->tenant->club_name,
                'slug' => $m->tenant->slug,
                'packages_url' => route('admin.club.members.enroll-packages', [$m->tenant->slug, $user->id]),
                'enroll_url' => route('admin.club.members.enroll', [$m->tenant->slug, $user->id]),
            ])
            ->values();

        return response()->json([
            'clubs' => $clubs,
            'single_club' => $clubs->count() === 1,
        ]);
    }

    /**
     * Create a new platform member.
     */
    public function storeMember(StorePlatformMemberRequest $request)
    {

        $mobile = null;
        if ($request->filled('mobile_code') && $request->filled('mobile')) {
            $mobile = ['code' => $request->mobile_code, 'number' => $request->mobile];
        }

        $data = [
            'full_name' => $request->full_name,
            'name' => $request->full_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'gender' => $request->gender,
            'birthdate' => $request->birthdate,
            'nationality' => $request->nationality,
            'blood_type' => $request->blood_type ?? 'Unknown',   // column is NOT NULL (default 'Unknown'); the form makes it optional
            'mobile' => $mobile,
            'marital_status' => $request->marital_status,
            'motto' => $request->motto,
            // Admin-created accounts are trusted — mark verified, no email step.
            'email_verified_at' => now(),
        ];

        /*
         * Drop the keys nobody filled in, so the column defaults apply.
         *
         * users.blood_type is NOT NULL DEFAULT 'Unknown', and a default only takes
         * effect when the column is OMITTED from the insert — passing an explicit
         * NULL violates the constraint instead. So leaving blood type blank on this
         * form threw
         *
         *   SQLSTATE[23000]: NOT NULL constraint failed: users.blood_type
         *
         * and no member was created at all. Filtering nulls here fixes that for
         * every optional column at once, rather than one magic value at a time.
         *
         * full_name is exempt: it is genuinely required, and validation has already
         * refused a blank one, so it must not be silently dropped here.
         */
        $data = array_filter($data, fn ($value, $key) => $value !== null || $key === 'full_name', ARRAY_FILTER_USE_BOTH);

        $softDeleted = User::withTrashed()->where('email', $request->email)->whereNotNull('deleted_at')->first();
        if ($softDeleted) {
            $softDeleted->restore();
            $softDeleted->update($data);
            $user = $softDeleted;
        } else {
            $user = User::create($data);
        }

        return response()->json([
            'success' => true,
            'message' => 'Member created successfully!',
            'redirect' => route('admin.platform.members'),
        ]);
    }

    /**
     * Show create club form.
     */
    public function createClub()
    {
        $users = User::orderBy('full_name')->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'mobile' => $user->mobile_formatted,
                'profile_picture' => $user->profile_picture
                    ? file_url($user->profile_picture)
                    : null,
            ];
        });

        return view('platform::clubs.add', compact('users'));
    }

    /**
     * Store a new club.
     */
    public function storeClub(Request $request)
    {
        $validated = $request->validate([
            'owner_user_id' => 'required|exists:users,id',
            'club_name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:tenants,slug',
            'email' => 'nullable|email',
            'phone_code' => 'nullable|string',
            'phone_number' => 'nullable|string',
            'currency' => 'nullable|string|max:3',
            'timezone' => 'nullable|string',
            'country' => 'nullable|string',
            'address' => 'nullable|string',
            'registration_terms' => 'nullable|string|max:50000',
            'registration_requirements' => 'nullable|string|max:50000',
            'translations' => 'nullable|array',
            'translations.*.*' => 'nullable|string|max:50000',
            'gps_lat' => 'nullable|numeric|between:-90,90',
            'gps_long' => 'nullable|numeric|between:-180,180',
            'logo' => 'nullable',
            'cover_image' => 'nullable',
            'registration_splash_image' => 'nullable',
        ]);

        // Handle phone as JSON
        if ($request->filled('phone_code') && $request->filled('phone_number')) {
            $validated['phone'] = [
                'code' => $request->phone_code,
                'number' => $request->phone_number,
            ];
        }

        // Branding images. Each is either a base64 data-URI from the in-modal
        // cropper or a traditional file upload. Client-supplied folder/filename
        // fields are IGNORED — storeBase64Image() inspects the real bytes (finfo),
        // allowlists the MIME, and builds a server-controlled path. Path traversal
        // and disguised-payload uploads are not possible here.
        // Each image goes in THIS CLUB's own folder. It used to be a flat
        // `clubs/logos` / `clubs/covers` / `clubs/splash` — purpose first, owner
        // second — which makes "what belongs to this club?" unanswerable and
        // leaves branding scattered outside the club subtree. The slug is a
        // validated field on this request, so the club's folder is known even
        // though the row does not exist yet.
        $brandingFolder = StoragePath::clubBySlug((string) $validated['slug'], 'branding', $validated['country'] ?? null);

        foreach (['logo', 'cover_image', 'registration_splash_image'] as $field) {
            $folder = $brandingFolder;
            if ($request->filled($field) && str_starts_with((string) $request->input($field), 'data:image')
                && ($stored = $this->storeBase64Image($request->input($field), $folder, $field.'_'.Str::uuid()))) {
                $validated[$field] = $stored;
            } elseif ($request->hasFile($field)) {
                $request->validate([$field => 'image|mimes:jpg,jpeg,png,webp,gif|max:4096']);
                $validated[$field] = $request->file($field)->store($folder, 'public');
            } else {
                unset($validated[$field]);
            }
        }

        $club = Tenant::create($validated);

        // Persist non-default-locale content (e.g. Arabic terms) through the
        // model so rich-HTML fields are sanitised. 'translations' is not fillable,
        // so it was safely ignored by create() above.
        if (is_array($request->input('translations'))) {
            $club->setTranslations($request->input('translations'))->save();
        }

        // Assign club-admin role to owner
        $owner = User::find($validated['owner_user_id']);
        $owner->assignRole('club-admin', $club->id);

        return redirect()->route('admin.platform.clubs')
            ->with('success', 'Club created successfully!');
    }

    /**
     * Show edit club form.
     */
    public function editClub(Tenant $club)
    {
        $users = User::orderBy('full_name')->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'mobile' => $user->mobile_formatted,
                'profile_picture' => $user->profile_picture
                    ? file_url($user->profile_picture)
                    : null,
            ];
        });

        return view('platform::clubs.edit', compact('club', 'users'));
    }

    /**
     * Update a club.
     */
    public function updateClub(Request $request, Tenant $club)
    {
        $validated = $request->validate([
            'owner_user_id' => 'required|exists:users,id',
            'club_name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:tenants,slug,'.$club->id,
            'email' => 'nullable|email',
            'phone_code' => 'nullable|string',
            'phone_number' => 'nullable|string',
            'currency' => 'nullable|string|max:3',
            'timezone' => 'nullable|string',
            'country' => 'nullable|string',
            'address' => 'nullable|string',
            'gps_lat' => 'nullable|numeric|between:-90,90',
            'gps_long' => 'nullable|numeric|between:-180,180',
            'logo' => 'nullable|image|max:2048',
            'cover_image' => 'nullable|image|max:2048',
        ]);

        // Handle phone as JSON
        if ($request->filled('phone_code') && $request->filled('phone_number')) {
            $validated['phone'] = [
                'code' => $request->phone_code,
                'number' => $request->phone_number,
            ];
        }

        // Handle logo upload
        if ($request->hasFile('logo')) {
            // Delete old logo
            if ($club->logo) {
                Storage::disk('public')->delete($club->logo);
            }
            $validated['logo'] = $request->file('logo')->store(StoragePath::clubBranding($club), 'public');
        }

        // Handle cover image upload
        if ($request->hasFile('cover_image')) {
            // Delete old cover
            if ($club->cover_image) {
                Storage::disk('public')->delete($club->cover_image);
            }
            $validated['cover_image'] = $request->file('cover_image')->store(StoragePath::clubBranding($club), 'public');
        }

        $club->update($validated);

        return redirect()->route('admin.platform.clubs')
            ->with('success', 'Club updated successfully!');
    }

    /**
     * Delete a club.
     */
    public function destroyClub(Tenant $club)
    {
        // Delete associated files
        if ($club->logo) {
            Storage::disk('public')->delete($club->logo);
        }
        if ($club->cover_image) {
            Storage::disk('public')->delete($club->cover_image);
        }
        if ($club->favicon) {
            Storage::disk('public')->delete($club->favicon);
        }

        // Delete club (cascade will handle related records)
        $club->delete();

        return redirect()->route('admin.platform.clubs')
            ->with('success', 'Club deleted successfully!');
    }

    /**
     * Display database backup page.
     */
    /**
     * The error log, so a problem can be reported instead of described.
     *
     * There was no way to see what actually went wrong on this platform: an error
     * became "it broke", and the stack trace stayed on the server. This is the
     * same tool TAKEONE Play has — filter, level, tail, copy — so a failure can be
     * pasted somewhere useful the moment it happens.
     *
     * Reads the log file and nothing else. It writes nothing, and there is no
     * delete: a log a viewer can prune is not an audit trail.
     *
     * SECURITY. Log lines are the most sensitive text on the box — stack traces
     * carry file paths, request payloads carry personal data, and a careless
     * Log::info can carry a token. So:
     *   - super-admin only, enforced by the route group, never by this method;
     *   - read from a FIXED path, never one the request can influence, so no
     *     traversal is possible;
     *   - bounded output, so a huge log cannot be used to exhaust memory;
     *   - escaped on render (Blade default), because a log line contains whatever
     *     an attacker managed to get logged, including markup.
     */
    public function logs(Request $request)
    {
        $filter = trim((string) $request->query('filter', ''));
        $level = strtoupper(trim((string) $request->query('level', '')));
        $limit = (int) $request->query('limit', 200);

        // Bounded, and only from the set the form offers.
        $limit = in_array($limit, [50, 100, 200, 500, 1000], true) ? $limit : 200;
        $level = in_array($level, ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY', 'WARNING', 'INFO', 'DEBUG'], true) ? $level : '';

        // A fixed path. Nothing from the request reaches it.
        $path = storage_path('logs/laravel.log');

        $entries = [];
        $size = 0;
        $truncated = false;

        if (is_file($path) && is_readable($path)) {
            $size = filesize($path);

            /*
             * Read the TAIL, not the file.
             *
             * This log is already megabytes and only grows; file() would load all
             * of it into memory to show the last 200 lines. Seeking back from the
             * end keeps the page's cost flat no matter how large the log gets —
             * which matters because the page is most wanted on the worst day.
             */
            $entries = $this->tailLogEntries($path, $limit, $filter, $level, $truncated);
        }

        return view('platform::logs', [
            'entries' => $entries,
            'filter' => $filter,
            'level' => $level,
            'limit' => $limit,
            'logSize' => $size,
            'logPath' => $path,
            'truncated' => $truncated,
            'logMissing' => ! is_file($path),
        ]);
    }

    /**
     * The last matching log ENTRIES, newest first.
     *
     * An entry is not a line: a stack trace is dozens of lines belonging to one
     * error, and splitting on newlines turns one failure into forty rows of
     * gibberish. A new entry starts at a `[YYYY-MM-DD HH:MM:SS]` stamp; everything
     * after it belongs to it.
     *
     * @return array<int, array{stamp: string, level: string, message: string, trace: string}>
     */
    private function tailLogEntries(string $path, int $limit, string $filter, string $level, bool &$truncated): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return [];
        }

        // Enough to hold the requested entries in all but pathological cases, and
        // a hard ceiling either way.
        $window = min(filesize($path), max(512 * 1024, $limit * 4096));

        fseek($handle, -$window, SEEK_END);
        $chunk = (string) fread($handle, $window);
        fclose($handle);

        $truncated = $window < filesize($path);

        // The first stamp may be mid-entry after seeking; drop the partial head.
        $parts = preg_split('/\n(?=\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $chunk) ?: [];

        if ($truncated && count($parts) > 1) {
            array_shift($parts);
        }

        $out = [];

        foreach (array_reverse($parts) as $raw) {
            $raw = rtrim($raw);

            if ($raw === '') {
                continue;
            }

            if ($filter !== '' && stripos($raw, $filter) === false) {
                continue;
            }

            preg_match('/^\[([^\]]+)\]\s*(\S+?)\.(\w+):\s*(.*)$/s', $raw, $m);

            $entryLevel = strtoupper($m[3] ?? '');

            if ($level !== '' && $entryLevel !== $level) {
                continue;
            }

            // Head line and trace kept apart so the page can fold the trace away
            // rather than drowning the message in it.
            $body = $m[4] ?? $raw;
            $split = explode("
", $body, 2);

            $out[] = [
                'stamp' => $m[1] ?? '',
                'level' => $entryLevel ?: 'LOG',
                'message' => trim($split[0]),
                'trace' => trim($split[1] ?? ''),
            ];

            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    public function backup()
    {
        $mobile = request()->attributes->get('is_mobile') && view()->exists('platform::mobile.backup');

        return view($mobile ? 'platform::mobile.backup' : 'platform::backup.index');
    }

    /**
     * Download database backup as JSON.
     */
    public function downloadBackup()
    {
        $tables = [
            'users',
            'user_relationships',
            'tenants',
            'memberships',
            'invoices',
            'roles',
            'permissions',
            'role_permission',
            'user_roles',
            'club_facilities',
            'club_instructors',
            'club_activities',
            'club_packages',
            'club_package_activities',
            'club_member_subscriptions',
            'club_transactions',
            'club_gallery_images',
            'club_social_links',
            'club_bank_accounts',
            'club_messages',
            'club_reviews',
            'health_records',
            'tournament_events',
            'performance_results',
            'goals',
            'attendance_records',
            'club_affiliations',
            'skill_acquisitions',
        ];

        $backup = [];
        foreach ($tables as $table) {
            $backup[$table] = DB::table($table)->get()->toArray();
        }

        $filename = 'takeone_backup_'.date('Y-m-d_H-i-s').'.json';

        return response()->json($backup, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Restore database from JSON backup.
     */
    public function restoreBackup(RestoreBackupRequest $request)
    {

        $file = $request->file('backup_file');
        $content = file_get_contents($file->getRealPath());

        try {
            $backup = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return back()->with('error', 'Invalid backup file format.');
        }

        if (empty($backup) || ! is_array($backup)) {
            return back()->with('error', 'Invalid backup file format.');
        }

        DB::beginTransaction();
        try {
            // Disable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            foreach ($backup as $table => $records) {
                // Truncate table
                DB::table($table)->truncate();

                // Insert records in chunks
                if (! empty($records)) {
                    $chunks = array_chunk($records, 100);
                    foreach ($chunks as $chunk) {
                        DB::table($table)->insert($chunk);
                    }
                }
            }

            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            DB::commit();

            return back()->with('success', 'Database restored successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            return back()->with('error', 'Restore failed: '.$e->getMessage());
        }
    }

    /**
     * Export all authentication users.
     */
    public function exportAuthUsers()
    {
        $users = User::select('id', 'full_name', 'email', 'password', 'created_at')
            ->get()
            ->toArray();

        $filename = 'auth_users_'.date('Y-m-d_H-i-s').'.json';

        return response()->json($users, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Upload club logo via AJAX (cropper).
     */
    public function uploadClubLogo(UploadImageRequest $request, Tenant $club)
    {

        try {
            // Validate + store the base64 image with a server-assigned extension
            // (real MIME sniffed from the bytes; PHP/HTML/SVG rejected).
            // The destination is derived from the entity we just resolved and
            // authorised — never from the request. `folder`/`filename` used to
            // come straight from the caller; UploadImageRequest constrains their
            // CHARSET but not their TARGET, so any authenticated user could name
            // another member's folder and overwrite that person's picture.
            //
            // Existing files are untouched: every path is stored per row, so what
            // is already on disk keeps resolving where it is. Only new uploads
            // land in the documented structure.
            $fullPath = $this->storeBase64Image(
                $request->image,
                StoragePath::clubBranding($club),
                'logo_'.Str::random(24),
            );
            if ($fullPath === null) {
                return response()->json(['success' => false, 'message' => 'Invalid or unsupported image.'], 422);
            }

            // Delete old logo if exists
            if ($club->logo && $club->logo !== $fullPath && Storage::disk('public')->exists($club->logo)) {
                Storage::disk('public')->delete($club->logo);
            }

            // Update club's logo field
            $club->update(['logo' => $fullPath]);

            return response()->json([
                'success' => true,
                'path' => $fullPath,
                'url' => file_url($fullPath),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Upload club cover image via AJAX (cropper).
     */
    public function uploadClubCover(UploadImageRequest $request, Tenant $club)
    {

        try {
            // Validate + store the base64 image with a server-assigned extension
            // (real MIME sniffed from the bytes; PHP/HTML/SVG rejected).
            // The destination is derived from the entity we just resolved and
            // authorised — never from the request. `folder`/`filename` used to
            // come straight from the caller; UploadImageRequest constrains their
            // CHARSET but not their TARGET, so any authenticated user could name
            // another member's folder and overwrite that person's picture.
            //
            // Existing files are untouched: every path is stored per row, so what
            // is already on disk keeps resolving where it is. Only new uploads
            // land in the documented structure.
            $fullPath = $this->storeBase64Image(
                $request->image,
                StoragePath::clubBranding($club),
                'cover_'.Str::random(24),
            );
            if ($fullPath === null) {
                return response()->json(['success' => false, 'message' => 'Invalid or unsupported image.'], 422);
            }

            // Delete old cover if exists
            if ($club->cover_image && $club->cover_image !== $fullPath && Storage::disk('public')->exists($club->cover_image)) {
                Storage::disk('public')->delete($club->cover_image);
            }

            // Update club's cover_image field
            $club->update(['cover_image' => $fullPath]);

            return response()->json([
                'success' => true,
                'path' => $fullPath,
                'url' => file_url($fullPath),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified member's profile.
     */
    public function showMember($id)
    {
        $member = User::findOrFail($id);

        // Fetch health data
        $latestHealthRecord = $member->healthRecords()->latest('recorded_at')->first();
        $healthRecords = $member->healthRecords()->orderBy('recorded_at', 'desc')->paginate(10);
        $comparisonRecords = $member->healthRecords()->orderBy('recorded_at', 'desc')->take(2)->get();

        // Fetch invoices
        $invoices = Invoice::where('student_user_id', $member->id)
            ->orWhere('payer_user_id', $member->id)
            ->with(['student', 'tenant'])
            ->get();

        // Fetch tournament data
        $tournamentEvents = $member->tournamentEvents()
            ->with(['performanceResults', 'notesMedia', 'clubAffiliation'])
            ->orderBy('date', 'desc')
            ->get();

        // Calculate award counts
        $awardCounts = [
            'special' => $tournamentEvents->flatMap->performanceResults->where('medal_type', 'special')->count(),
            '1st' => $tournamentEvents->flatMap->performanceResults->where('medal_type', '1st')->count(),
            '2nd' => $tournamentEvents->flatMap->performanceResults->where('medal_type', '2nd')->count(),
            '3rd' => $tournamentEvents->flatMap->performanceResults->where('medal_type', '3rd')->count(),
        ];

        // Get unique sports for filter
        $sports = $tournamentEvents->pluck('sport')->unique()->sort()->values();

        // Fetch goals data
        $goals = $member->goals()->orderBy('created_at', 'desc')->get();
        $activeGoalsCount = $goals->where('status', 'active')->count();
        $completedGoalsCount = $goals->where('status', 'completed')->count();
        $successRate = $goals->count() > 0 ? round(($completedGoalsCount / $goals->count()) * 100) : 0;

        // Fetch attendance data
        $attendanceRecords = $member->attendanceRecords()->orderBy('session_datetime', 'desc')->get();
        $sessionsCompleted = $attendanceRecords->where('status', 'completed')->count();
        $noShows = $attendanceRecords->where('status', 'no_show')->count();
        $totalSessions = $attendanceRecords->count();
        $attendanceRate = $totalSessions > 0 ? round(($sessionsCompleted / $totalSessions) * 100, 1) : 0;

        // Fetch affiliations data
        $clubAffiliations = $member->clubAffiliations()
            ->with([
                'skillAcquisitions.package',
                'skillAcquisitions.activity',
                'skillAcquisitions.instructor.user',
                'affiliationMedia',
                'subscriptions.package.activities',
                'subscriptions.package.packageActivities.activity',
                'subscriptions.package.packageActivities.instructor.user',
            ])
            ->orderBy('start_date', 'desc')
            ->get();

        // Add icon_class to media items
        $clubAffiliations->each(function ($affiliation) {
            $affiliation->affiliationMedia->each(function ($media) {
                $media->icon_class = $media->icon_class;
            });
        });

        // Calculate summary stats
        $totalAffiliations = $clubAffiliations->count();
        $distinctSkills = $clubAffiliations->flatMap->skillAcquisitions->pluck('skill_name')->unique()->count();
        $totalMembershipDuration = $clubAffiliations->sum('duration_in_months');

        // Get all unique skills
        $allSkills = $clubAffiliations->flatMap(function ($affiliation) {
            return $affiliation->skillAcquisitions->pluck('skill_name');
        })->unique()->sort()->values();

        // Count total instructors
        $totalInstructors = $clubAffiliations->flatMap(function ($affiliation) {
            return $affiliation->skillAcquisitions->pluck('instructor');
        })->filter()->unique('id')->count();

        // Create a mock relationship for the view
        $relationship = (object) [
            'dependent' => $member,
            'relationship_type' => 'admin_view',
            'guardian_user_id' => Auth::id(),
            'dependent_user_id' => $member->id,
        ];

        return view('members::family.show', [
            // What the platform already knows about this member's clubs and
            // events — the two tabs otherwise show only what they typed in
            // themselves. Read-only, de-duplicated against the self-reported rows.
            'derivedAffiliations' => app(\App\Members\Support\ProfileHistory::class)->derivedAffiliations($relationship->dependent),
            'derivedTournaments'  => app(\App\Members\Support\ProfileHistory::class)->derivedTournaments($relationship->dependent),
            'relationship' => $relationship,
            'latestHealthRecord' => $latestHealthRecord,
            'healthRecords' => $healthRecords,
            'comparisonRecords' => $comparisonRecords,
            'invoices' => $invoices,
            'tournamentEvents' => $tournamentEvents,
            'awardCounts' => $awardCounts,
            'sports' => $sports,
            'goals' => $goals,
            'activeGoalsCount' => $activeGoalsCount,
            'completedGoalsCount' => $completedGoalsCount,
            'successRate' => $successRate,
            'attendanceRecords' => $attendanceRecords,
            'sessionsCompleted' => $sessionsCompleted,
            'noShows' => $noShows,
            'attendanceRate' => $attendanceRate,
            'clubAffiliations' => $clubAffiliations,
            'totalAffiliations' => $totalAffiliations,
            'distinctSkills' => $distinctSkills,
            'totalMembershipDuration' => $totalMembershipDuration,
            'allSkills' => $allSkills,
            'totalInstructors' => $totalInstructors,
            'user' => $member,
        ]);
    }

    /**
     * Show the form for editing a member.
     */
    public function editMember($id)
    {
        $member = User::findOrFail($id);

        // Create a mock relationship for the view
        $relationship = (object) [
            'dependent' => $member,
            'relationship_type' => 'admin_view',
            'guardian_user_id' => Auth::id(),
            'dependent_user_id' => $member->id,
            'is_billing_contact' => false,
        ];

        return view('members::family.edit', compact('relationship'));
    }

    /**
     * Update a member.
     */

    /**
     * 'required' or 'nullable' for the person details on an admin edit.
     *
     * Same rule as PersonFieldRules: staff entering somebody else are asked for a
     * name and nothing more, because a referee off a federation list or an athlete
     * off a paper sheet arrives without the rest, and an invented value is worse
     * than a blank. Their own profile stays strict.
     */
    private function personPresence($subjectId): string
    {
        $actor = Auth::user();

        if (! $actor) {
            return 'required';
        }

        if ((int) $actor->id === (int) $subjectId) {
            return 'required';
        }

        return $actor->entersPeopleOnBehalfOfOthers() ? 'nullable' : 'required';
    }

    public function updateMember(Request $request, $id)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:users,email,'.$id,
            'mobile_code' => 'nullable|string|max:5',
            'mobile' => 'nullable|string|max:20',
            /*
             * Presence follows WHO is filling the form, exactly as
             * App\Members\Requests\Concerns\PersonFieldRules does for every other
             * person form — this endpoint validates inline, so it has to make the
             * same decision rather than inherit it. A super admin editing somebody
             * else is the case that must not be asked for what it does not have.
             *
             * Birthdate is never required, of anyone.
             */
            'gender' => $this->personPresence($id).'|in:Male,Female',
            'marital_status' => 'nullable|in:single,married,divorced,widowed',
            'birthdate' => 'nullable|date',
            'blood_type' => 'nullable|string|max:10',
            'nationality' => $this->personPresence($id).'|string|max:100',
            'social_links' => 'nullable|array',
            'social_links.*.platform' => 'required_with:social_links.*.url|string',
            'social_links.*.url' => 'required_with:social_links.*.platform|url',
            'motto' => 'nullable|string|max:500',
            'emergency_contacts_json' => 'nullable|string',
            'health_conditions_json' => 'nullable|string',
            'documents_json' => 'nullable|string',
        ]);

        // Process social links
        $socialLinks = [];
        if (isset($validated['social_links']) && is_array($validated['social_links'])) {
            foreach ($validated['social_links'] as $link) {
                if (! empty($link['platform']) && ! empty($link['url'])) {
                    $socialLinks[$link['platform']] = $link['url'];
                }
            }
        }

        // Process mobile
        $mobile = [
            'code' => $validated['mobile_code'] ?? null,
            'number' => $validated['mobile'] ?? null,
        ];

        // Process the three JSON-encoded fields from the Medical & Contacts tab
        $emergencyContacts = collect(json_decode($request->input('emergency_contacts_json', '[]'), true) ?? [])
            ->filter(fn ($c) => ! empty($c['name']) || ! empty($c['phone']))
            ->map(fn ($c) => [
                'name' => trim($c['name'] ?? ''),
                'relationship' => $c['relationship'] ?? '',
                'phone_code' => $c['phone_code'] ?? '',
                'phone' => trim($c['phone'] ?? ''),
            ])->values()->all();

        $healthConditions = collect(json_decode($request->input('health_conditions_json', '[]'), true) ?? [])
            ->filter(fn ($c) => ! empty($c['condition']))
            ->map(fn ($c) => [
                'condition' => trim($c['condition']),
                'noted_at' => $c['noted_at'] ?? now()->format('Y-m-d'),
                'notes' => trim($c['notes'] ?? ''),
            ])->values()->all();

        $documents = collect(json_decode($request->input('documents_json', '[]'), true) ?? [])
            ->filter(fn ($d) => ! empty($d['type']) || ! empty($d['number']))
            ->map(fn ($d) => [
                'type' => trim($d['type'] ?? ''),
                'number' => trim($d['number'] ?? ''),
                'file_path' => $d['file_path'] ?? null,
                'file_name' => $d['file_name'] ?? null,
                // file_url is deliberately NOT stored. It is derived from
                // file_path at render time, so the row never carries a hostname
                // and never carries a link that bypasses authorization.
                'uploaded_at' => $d['uploaded_at'] ?? now()->format('Y-m-d'),
            ])->values()->all();

        $member = User::findOrFail($id);
        /*
         * Absent means "leave it alone"; blank means "clear it". Reading an
         * optional field unconditionally would let a request that simply omits it
         * wipe what was already stored — and would 500 on the missing key first.
         */
        $optional = [];
        foreach (['gender', 'birthdate', 'nationality', 'email', 'blood_type', 'motto', 'marital_status'] as $field) {
            if ($request->has($field)) {
                $optional[$field] = $validated[$field] ?? null;
            }
        }

        $member->update([
            'full_name' => $validated['full_name'],
            'mobile' => $mobile,
            'social_links' => $socialLinks,
            'emergency_contacts' => $emergencyContacts,
            'health_conditions' => $healthConditions,
            'documents' => $documents,
        ] + $optional);

        // Return JSON for AJAX requests
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Member updated successfully.',
                'member' => [
                    'full_name' => $member->full_name,
                    'motto' => $member->motto,
                    'nationality' => $member->nationality,
                    'gender' => $member->gender,
                    'marital_status' => $member->marital_status,
                    'blood_type' => $member->blood_type,
                    'age' => $member->age,
                    'social_links' => $member->social_links ?? [],
                    'emergency_contacts' => $member->emergency_contacts ?? [],
                    'health_conditions' => $member->health_conditions ?? [],
                    'documents' => $member->documents ?? [],
                ],
            ]);
        }

        return redirect()->route('admin.platform.members.show', $id)
            ->with('success', 'Member updated successfully.');
    }

    /**
     * Delete a member.
     */
    public function destroyMember($id)
    {
        $wantsJson = request()->wantsJson() || request()->ajax();

        // Prevent deleting own account
        if (Auth::id() == $id) {
            $msg = 'You cannot delete your own account.';

            return $wantsJson
                ? response()->json(['success' => false, 'message' => $msg], 422)
                : redirect()->back()->with('error', $msg);
        }

        $member = User::findOrFail($id);

        // Clubs, chains and federations alike (2026-10-03) — this used to ask
        // about clubs only.
        $ownedClubs = collect($member->ownedOrganisationNames());
        if ($ownedClubs->isNotEmpty()) {
            $msg = 'Cannot delete this account. They own: '.$ownedClubs->join(', ').'. Transfer ownership first.';

            return $wantsJson
                ? response()->json(['success' => false, 'message' => $msg], 422)
                : redirect()->back()->with('error', $msg);
        }

        $memberName = $member->full_name;
        $deletedId = $member->id;
        $member->delete();

        // Every other super-admin with the members list open re-reads it
        // (a refresh signal: nothing about the member travels).
        rescue(function () {
            $batch = User::whereHas('roles', fn ($q) => $q->where('slug', 'super-admin'))
                ->where('id', '!=', Auth::id())->pluck('id')
                ->map(fn ($uid) => ['topic' => \Takeone\Realtime\Support\Topics::user((int) $uid, 'members'), 'payload' => ['action' => 'refresh']])
                ->values()->all();
            if ($batch !== []) {
                \Realtime()->publishMany($batch);
            }
        }, report: true);

        $msg = $memberName.' has been deleted successfully.';

        return $wantsJson
            ? response()->json(['success' => true, 'message' => $msg, 'member' => ['id' => $deletedId]])
            : redirect()->route('admin.platform.members')->with('success', $msg);
    }

    /**
     * Upload member profile picture.
     */
    public function uploadMemberPicture(UploadImageRequest $request, $id)
    {

        try {
            $member = User::findOrFail($id);

            // Validate + store the base64 image with a server-assigned extension
            // (real MIME sniffed from the bytes; PHP/HTML/SVG rejected).
            // The destination is derived from the entity we just resolved and
            // authorised — never from the request. `folder`/`filename` used to
            // come straight from the caller; UploadImageRequest constrains their
            // CHARSET but not their TARGET, so any authenticated user could name
            // another member's folder and overwrite that person's picture.
            //
            // Existing files are untouched: every path is stored per row, so what
            // is already on disk keeps resolving where it is. Only new uploads
            // land in the documented structure.
            $fullPath = $this->storeBase64Image(
                $request->image,
                StoragePath::memberProfile($member),
                'profile_'.Str::random(24),
            );
            if ($fullPath === null) {
                return response()->json(['success' => false, 'message' => 'Invalid or unsupported image.'], 422);
            }

            // Only when nothing else still names the file: an event entry can
            // point AT a profile picture rather than carry a copy, and duplicate
            // accounts share one path. See App\Events\Support\EntryPhoto.
            if ($member->profile_picture !== $fullPath) {
                EntryPhoto::discardShared($member->profile_picture, $member->id);
            }

            // Update member's profile_picture field
            $member->update(['profile_picture' => $fullPath]);

            return response()->json([
                'success' => true,
                'path' => $fullPath,
                'url' => file_url($fullPath),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove a member's profile picture (super-admin).
     */
    public function removeMemberPicture($id)
    {
        $member = User::findOrFail($id);

        // The avatar is also one of the profile's pictures — drop that row first so
        // the picture viewer never points at a file this method is about to delete.
        // (The row's own trait purges the file, hence before the Storage delete.)
        if ($member->profile_picture) {
            $member->photos()->where('path', $member->profile_picture)->get()->each->delete();
        }

        // The row above has given up its claim; what remains is somebody ELSE
        // naming this file — an event entry, or a duplicate account.
        EntryPhoto::discardShared($member->profile_picture, $member->id);

        $extensions = ['png', 'jpg', 'jpeg', 'webp'];
        foreach ($extensions as $ext) {
            $path = 'images/profiles/profile_'.$member->id.'.'.$ext;
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

        $member->update(['profile_picture' => null]);

        return response()->json(['success' => true, 'message' => __('shared.photo_edit_modal_removed')]);
    }

    /**
     * Toggle the public/private visibility of a member's profile picture (super-admin).
     */
    public function updateMemberPictureVisibility(\Illuminate\Http\Request $request, $id)
    {
        $request->validate(['is_public' => 'required|boolean']);

        $member = User::findOrFail($id);
        $member->update(['profile_picture_is_public' => $request->boolean('is_public')]);

        return response()->json(['success' => true, 'is_public' => $member->profile_picture_is_public]);
    }

    /**
     * Store a health record for a member.
     */
    public function storeMemberHealth(HealthRecordRequest $request, $id)
    {
        $validated = $request->validated();

        $member = User::findOrFail($id);

        // Check for duplicate date
        $existing = $member->healthRecords()->where('recorded_at', $validated['recorded_at'])->first();
        if ($existing) {
            return redirect()->back()
                ->with('error', 'A health record already exists for this date.');
        }

        $member->healthRecords()->create($validated);

        return redirect()->back()->withFragment('health')
            ->with('success', 'Health record added successfully.');
    }

    /**
     * Update a health record for a member.
     */
    public function updateMemberHealth(HealthRecordRequest $request, $id, $recordId)
    {
        $validated = $request->validated();

        $member = User::findOrFail($id);
        $healthRecord = $member->healthRecords()->findOrFail($recordId);

        // Check for duplicate date (excluding current record)
        $existing = $member->healthRecords()
            ->where('recorded_at', $validated['recorded_at'])
            ->where('id', '!=', $recordId)
            ->first();
        if ($existing) {
            return redirect()->back()
                ->with('error', 'A health record already exists for this date.');
        }

        $healthRecord->update($validated);

        return redirect()->back()->withFragment('health')
            ->with('success', 'Health record updated successfully.');
    }

    /**
     * Store a tournament record for a member.
     */
    public function storeMemberTournament(TournamentRequest $request, $id)
    {
        $validated = $request->validated();

        // Create the tournament event
        $tournament = TournamentEvent::create([
            'user_id' => $id,
            'club_affiliation_id' => $validated['club_affiliation_id'] ?? null,
            'title' => $validated['title'],
            'type' => $validated['type'],
            'sport' => $validated['sport'],
            'date' => $validated['date'],
            'time' => $validated['time'],
            'location' => $validated['location'],
            'participants_count' => $validated['participants_count'],
        ]);

        // Create performance results
        if (isset($validated['performance_results'])) {
            foreach ($validated['performance_results'] as $resultData) {
                if (! empty($resultData['medal_type'])) {
                    $tournament->performanceResults()->create($resultData);
                }
            }
        }

        // Create notes and media
        if (isset($validated['notes_media'])) {
            foreach ($validated['notes_media'] as $noteData) {
                if (! empty($noteData['note_text']) || ! empty($noteData['media_link'])) {
                    $tournament->notesMedia()->create($noteData);
                }
            }
        }

        $tournament->load(['clubAffiliation', 'performanceResults', 'notesMedia']);

        return response()->json([
            'success' => true,
            'message' => 'Tournament record added successfully',
            'tournament' => $this->tournamentPayload($tournament),
        ]);
    }

    /**
     * Build a JSON-friendly payload for a tournament event for in-place rendering.
     */
    private function tournamentPayload(TournamentEvent $tournament): array
    {
        return [
            'id' => $tournament->id,
            'title' => $tournament->title,
            'type' => $tournament->type,
            'type_label' => ucfirst($tournament->type),
            'sport' => $tournament->sport,
            'date' => optional($tournament->date)->format('j M Y'),
            'time' => optional($tournament->time)->format('H:i'),
            'location' => $tournament->location,
            'participants_count' => $tournament->participants_count,
            'club_affiliation' => $tournament->clubAffiliation ? [
                'club_name' => $tournament->clubAffiliation->club_name,
                'location' => $tournament->clubAffiliation->location,
            ] : null,
            'performance_results' => $tournament->performanceResults->map(fn ($r) => [
                'medal_type' => $r->medal_type,
                'points' => $r->points,
                'description' => $r->description,
            ])->values()->all(),
            'notes_media' => $tournament->notesMedia->map(fn ($n) => [
                'note_text' => $n->note_text,
                'media_link' => $n->media_link,
            ])->values()->all(),
        ];
    }
}
