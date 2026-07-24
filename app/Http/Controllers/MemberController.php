<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmDeleteRequest;
use App\Http\Requests\HealthRecordRequest;
use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\TournamentRequest;
use App\Http\Requests\UpdateGoalRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Http\Requests\UploadImageRequest;
use App\Http\Requests\StoreCertificationRequest;
use App\Http\Requests\StoreWorkHistoryRequest;
use App\Models\Attendance;
use App\Models\ClubEventRegistration;
use App\Models\Goal;
use App\Models\Invoice;
use App\Models\MemberCertification;
use App\Models\MemberWorkHistory;
use App\Models\Membership;
use App\Models\Person;
use App\Models\SkillAcquisition;
use App\Models\TournamentEvent;
use App\Models\User;
use App\Models\UserRelationship;
use App\Services\KinshipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class MemberController extends Controller
{
    use \App\Traits\BuildsMemberPayments;
    use \App\Traits\ComputesAttendanceStats;
    use \App\Traits\StoresBase64Images;

    /**
     * Display a listing of members (family dashboard).
     *
     * Sourced from the kinship graph (App\Services\KinshipService) rather than
     * the legacy user_relationships table, so this list always matches what
     * the Family Tree shows — a relative added via any tree flow (spouse,
     * parent, existing person, etc.) appears here too, not just dependents
     * registered through the old guardian/dependent flow.
     *
     * @return \Illuminate\View\View
     */
    public function index(KinshipService $kin)
    {
        $user = Auth::user();

        $kin->syncGuardianship($user);   // mirror any legacy guardian rows into the graph
        $me = $kin->personFor($user);
        $data = $kin->neighborhood($me, $me);

        $nodesById = collect($data['nodes'])
            ->reject(fn ($n) => $n['is_focus'] ?? false)
            ->filter(fn ($n) => $n['account'] ?? false)   // only real, linked accounts have a profile to open
            ->keyBy('id');

        $peopleById = Person::whereIn('id', $nodesById->keys())->with('user')->get()->keyBy('id');

        // Every node (not just the account-linked ones listed as cards) so edge
        // labels can name the "other side" even when it's a non-account relative.
        $allNodesById = collect($data['nodes'])->keyBy('id');
        $edgesFor = function (int $personId) use ($data, $allNodesById) {
            $out = [];
            foreach ($data['parentEdges'] as $e) {
                if ($e['p'] === $personId && $allNodesById->has($e['c'])) {
                    $out[] = ['edge_type' => 'parent', 'edge_id' => $e['id'], 'label' => 'Parent of', 'name' => $allNodesById[$e['c']]['name'], 'status' => $e['status']];
                }
                if ($e['c'] === $personId && $allNodesById->has($e['p'])) {
                    $out[] = ['edge_type' => 'parent', 'edge_id' => $e['id'], 'label' => 'Child of', 'name' => $allNodesById[$e['p']]['name'], 'status' => $e['status']];
                }
            }
            foreach ($data['unions'] as $u) {
                if ($u['a'] === $personId || $u['b'] === $personId) {
                    $otherId = $u['a'] === $personId ? $u['b'] : $u['a'];
                    if ($allNodesById->has($otherId)) {
                        $out[] = ['edge_type' => 'union', 'edge_id' => $u['id'], 'label' => 'Spouse of', 'name' => $allNodesById[$otherId]['name'], 'status' => $u['status']];
                    }
                }
            }

            return $out;
        };

        $dependents = $nodesById->map(function ($node) use ($peopleById, $edgesFor) {
            $u = $peopleById->get($node['id'])?->user;

            return $u ? (object) [
                'dependent' => $u,
                'relationship_type' => $node['label'],
                'person_id' => $node['id'],
                'edges' => $edgesFor($node['id']),
            ] : null;
        })
            ->filter()
            ->unique(fn ($r) => $r->dependent->id)
            ->sortBy(fn ($r) => $r->dependent->full_name)
            ->values();

        // Mobile and desktop have genuinely different layouts — separate files.
        $isMobile = (bool) request()->attributes->get('is_mobile', false);
        $view = $isMobile && view()->exists('family.mobile.index') ? 'family.mobile.index' : 'family.index';

        // The mobile shell labels its header from $shellTitle for routes that
        // aren't in its own nav list.
        return view($view, compact('user', 'dependents'))
            ->with('shellTitle', __('family.title'));
    }

    /**
     * Show the form for creating a new member.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('family.create');
    }

    /**
     * Store a newly created member in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(StoreMemberRequest $request)
    {
        $validated = $request->validated();

        $guardian = Auth::user();

        // Use FamilyService to create the dependent
        $familyService = app(\App\Services\FamilyService::class);
        $dependent = $familyService->createDependent($guardian, $validated);

        return redirect()->route('members.index')
            ->with('success', 'Member added successfully.');
    }

    /**
     * Display the specified member's profile.
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function show($uuid)
    {
        $user = Auth::user();

        // Get the member to display by UUID
        $member = User::where('uuid', $uuid)->firstOrFail();
        $id = $member->id;

        // Check if user is super-admin or viewing their own profile
        $isSuperAdmin = $user->hasRole('super-admin');
        $isOwnProfile = $user->id == $id;

        // Check if the current user is a club admin for any club this member belongs to
        $isClubAdminOfMember = false;
        if (! $isSuperAdmin && ! $isOwnProfile && $user->isClubAdmin()) {
            $memberTenantIds = Membership::where('user_id', $member->id)->pluck('tenant_id');
            $isClubAdminOfMember = $memberTenantIds->contains(fn ($tenantId) => $user->isClubAdmin($tenantId));
        }

        $canResetPassword = $isSuperAdmin || $isOwnProfile || $isClubAdminOfMember;
        // Auto-generate (regenerate) a password — super-admin only, any account.
        $canRegeneratePassword = $isSuperAdmin && ! $isOwnProfile;

        // Role-based edit matrix for the profile page:
        //  • basic/personal info → self, guardian/parent, or super-admin
        //  • health / tournament / attendance / billing → club staff (admin of
        //    the member's club) or super-admin
        // A guardian is anyone who reaches this page without being self, a club
        // admin, or super-admin (the family relationship is enforced below).
        $isGuardian = ! $isSuperAdmin && ! $isOwnProfile && ! $isClubAdminOfMember;
        $canEditBasic = $isOwnProfile || $isGuardian || $isSuperAdmin;
        $canManageMember = $isClubAdminOfMember || $isSuperAdmin;

        // Billing & other sensitive sections are a STRICTLY private matter. Visible
        // ONLY to:
        //   • the member themselves            (their own financial data)
        //   • their real guardian / parent     (a confirmed family relationship)
        //   • a super-admin
        //   • an owner/admin/staff of a club this member is affiliated to
        // Every other viewer (fellow members, unrelated users) is blocked. The
        // guardian check is an explicit relationship lookup — never inferred — so
        // no other viewer can ever slip through.
        $isRealGuardian = ! $isOwnProfile && UserRelationship::where('guardian_user_id', $user->id)
            ->where('dependent_user_id', $member->id)
            ->exists();
        $canViewSensitive = $isOwnProfile || $isSuperAdmin || $isRealGuardian || $isClubAdminOfMember;

        // For super-admin, own profile, or club admin of this member — create a mock relationship
        // For regular users, verify family relationship exists
        if ($isSuperAdmin || $isOwnProfile || $isClubAdminOfMember) {
            $relationship = (object) [
                'dependent' => $member,
                'relationship_type' => $isOwnProfile ? 'self' : 'admin_view',
                'guardian_user_id' => $user->id,
                'dependent_user_id' => $member->id,
            ];
        } else {
            // Regular user - must have family relationship
            $relationship = UserRelationship::where('guardian_user_id', $user->id)
                ->where('dependent_user_id', $id)
                ->with('dependent')
                ->firstOrFail();
        }

        // Fetch health data for the member
        $latestHealthRecord = $relationship->dependent->healthRecords()->latest('recorded_at')->first();
        $healthRecords = $relationship->dependent->healthRecords()->orderBy('recorded_at', 'desc')->paginate(10);
        $comparisonRecords = $relationship->dependent->healthRecords()->orderBy('recorded_at', 'desc')->take(2)->get();

        // Weight-reading history with the running difference vs the previous reading.
        // Built oldest→newest to compute deltas, then reversed for newest-first display.
        $weightHistory = $relationship->dependent->healthRecords()
            ->whereNotNull('weight')
            ->orderBy('recorded_at')
            ->get(['id', 'weight', 'recorded_at']);
        $prevWeight = null;
        foreach ($weightHistory as $rec) {
            $rec->weight_delta = $prevWeight !== null ? round((float) $rec->weight - (float) $prevWeight, 1) : null;
            $prevWeight = (float) $rec->weight;
        }
        $weightHistory = $weightHistory->reverse()->values();

        // Fetch invoices for the member
        $invoices = Invoice::where('student_user_id', $relationship->dependent->id)
            ->orWhere('payer_user_id', $relationship->dependent->id)
            ->with(['student', 'tenant'])
            ->get();

        // Unified payment history (invoices + club-package subscriptions).
        $payments = $this->buildMemberPayments($relationship->dependent, $invoices);

        // Fetch tournament data for the member
        $tournamentEvents = $relationship->dependent->tournamentEvents()
            ->with(['performanceResults', 'notesMedia', 'clubAffiliation.tenant', 'verifiedByTenant'])
            ->orderBy('date', 'desc')
            ->get();

        // Calculate award counts. The HERO badges must reflect only authentic
        // medals: club-attested achievements (below) + tournament results a club
        // has VERIFIED. Self-reported / pending claims are tallied separately so
        // they stay visible on the owner's profile without being laundered into
        // the headline number (see the authenticity plan).
        $countMedals = fn ($events) => [
            'special' => $events->flatMap->performanceResults->where('medal_type', 'special')->count(),
            '1st' => $events->flatMap->performanceResults->where('medal_type', '1st')->count(),
            '2nd' => $events->flatMap->performanceResults->where('medal_type', '2nd')->count(),
            '3rd' => $events->flatMap->performanceResults->where('medal_type', '3rd')->count(),
        ];
        $awardCounts = $countMedals($tournamentEvents->where('verification_status', 'verified'));
        $selfReportedCounts = $countMedals($tournamentEvents->where('verification_status', '!==', 'verified'));

        // Get unique sports for filter
        $sports = $tournamentEvents->pluck('sport')->unique()->sort()->values();

        // Club achievements this member is featured in (linked as an athlete). Scoped to the
        // member's clubs; we filter by the linked user_id stored on each athlete entry.
        $member = $relationship->dependent;
        $memberClubIds = $member->memberClubs()->pluck('tenants.id');
        $awardedAchievements = $memberClubIds->isEmpty()
            ? collect()
            : \App\Models\ClubAchievement::whereIn('tenant_id', $memberClubIds)
                ->where('status', 'active')
                ->orderByDesc('achievement_date')
                ->with('tenant:id,club_name,slug,translations')
                ->get()
                ->map(function ($a) use ($member) {
                    $athletes = is_array($a->athletes) ? $a->athletes : [];
                    $mine = collect($athletes)->first(fn ($x) => is_array($x) && (int) ($x['user_id'] ?? 0) === (int) $member->id);
                    $a->member_award = $mine['role'] ?? null;

                    return $mine ? $a : null;
                })
                ->filter()
                ->values();

        // Fold the member's club-achievement medals into the award tally so the
        // top badges reflect every medal they've earned (a single award entry may
        // mention more than one medal, e.g. "Gold & Silver").
        foreach ($awardedAchievements as $a) {
            $r = mb_strtolower($a->member_award ?? '');
            if (str_contains($r, 'gold')) {
                $awardCounts['1st']++;
            }
            if (str_contains($r, 'silver')) {
                $awardCounts['2nd']++;
            }
            if (str_contains($r, 'bronze')) {
                $awardCounts['3rd']++;
            }
        }

        // Fetch goals data for the member
        $goals = $relationship->dependent->goals()->orderBy('created_at', 'desc')->get();
        $activeGoalsCount = $goals->where('status', 'active')->count();
        $completedGoalsCount = $goals->where('status', 'completed')->count();
        $successRate = $goals->count() > 0 ? round(($completedGoalsCount / $goals->count()) * 100) : 0;

        // Attendance log (manual entries via the desktop "Add attendance record" feature) — still
        // used for the entry list, but the summary numbers below no longer derive from it.
        $attendanceRecords = $relationship->dependent->attendanceRecords()->orderBy('session_datetime', 'desc')->get();

        // Attendance summary: total sessions = the package's scheduled classes across the
        // subscription period; completed = trainer-marked class_attendances for those classes.
        [
            'sessionsCompleted' => $sessionsCompleted,
            'noShows' => $noShows,
            'totalSessions' => $totalSessions,
            'attendanceRate' => $attendanceRate,
            'scheduleSessions' => $scheduleSessions,
        ] = $this->computeAttendanceStats($relationship->dependent);

        // Challenge (duel) record — win rate across completed challenges.
        $memberId = $relationship->dependent->id;
        $challengesTotal = \App\Models\Duel::where('status', 'completed')
            ->where(fn ($q) => $q->where('challenger_id', $memberId)->orWhere('opponent_id', $memberId))
            ->count();
        $challengeWins = \App\Models\Duel::where('status', 'completed')->where('winner_id', $memberId)->count();
        $challengeWinRate = $challengesTotal > 0 ? round(($challengeWins / $challengesTotal) * 100) : 0;

        // Full challenge (duel) list for the profile's Challenges tab, so a visitor
        // can browse this person's head-to-head history — not just the win rate.
        $memberChallenges = \App\Models\Duel::where(fn ($q) => $q->where('challenger_id', $memberId)->orWhere('opponent_id', $memberId))
            ->with([
                'challenger:id,uuid,full_name,profile_picture,gender,updated_at',
                'opponent:id,uuid,full_name,profile_picture,gender,updated_at',
            ])
            ->latest()
            ->get()
            ->map(function ($d) use ($memberId) {
                $isChallenger = $d->challenger_id === $memberId;
                $rival = $isChallenger ? $d->opponent : $d->challenger;
                $rivalName = $rival?->full_name ?? $d->opponent_name ?? $d->opponent_handle ?? __('member.opponent');

                $result = null;
                if ($d->status === 'completed') {
                    $result = $d->winner_id === null ? 'draw' : ($d->winner_id === $memberId ? 'won' : 'lost');
                }

                return (object) [
                    'id' => $d->id,
                    'discipline' => $d->discipline,
                    'metric' => $d->metric,
                    'format' => \App\Models\Duel::formatLabel($d->format),
                    'type' => $d->type,
                    'status' => $d->status,
                    'stake' => (int) $d->stake_points,
                    'date' => $d->completed_at ?? $d->deadline ?? $d->created_at,
                    'rival_name' => $rivalName,
                    'rival_uuid' => $rival?->uuid,
                    'rival_avatar' => $rival && $rival->profile_picture
                        ? asset('storage/'.$rival->profile_picture).'?v='.optional($rival->updated_at)->timestamp
                        : null,
                    'rival_gender' => $rival?->gender,
                    'result' => $result,
                    'my_score' => $isChallenger ? $d->challenger_score : $d->opponent_score,
                    'rival_score' => $isChallenger ? $d->opponent_score : $d->challenger_score,
                ];
            });

        // Fetch affiliations data for the member with enhanced relationships
        $clubAffiliations = $relationship->dependent->clubAffiliations()
            ->with([
                'tenant:id,slug,country,club_name',
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

        // Add icon_class to media items for JavaScript
        $clubAffiliations->each(function ($affiliation) {
            $affiliation->affiliationMedia->each(function ($media) {
                $media->icon_class = $media->icon_class;
            });
        });

        // Calculate summary stats
        $totalAffiliations = $clubAffiliations->count();
        $distinctSkills = $clubAffiliations->flatMap->skillAcquisitions->pluck('skill_name')->unique()->count();
        $totalMembershipDuration = $clubAffiliations->sum('duration_in_months');

        // Get all unique skills for filter dropdown
        $allSkills = $clubAffiliations->flatMap(function ($affiliation) {
            return $affiliation->skillAcquisitions->pluck('skill_name');
        })->unique()->sort()->values();

        // Count total instructors
        $totalInstructors = $clubAffiliations->flatMap(function ($affiliation) {
            return $affiliation->skillAcquisitions->pluck('instructor');
        })->filter()->unique('id')->count();

        // Joined club events
        $joinedEventRegistrations = ClubEventRegistration::where('user_id', $relationship->dependent->id)
            ->with(['event.tenant'])
            ->orderBy('registered_at', 'desc')
            ->get();

        // Free-form personal event-participation log (member-owned entries).
        $memberEventLog = $relationship->dependent->memberEvents()
            ->orderBy('event_date', 'desc')
            ->get();

        $certifications = $relationship->dependent->certifications()
            ->orderByRaw('issue_date IS NULL, issue_date DESC')
            ->orderBy('created_at', 'desc')
            ->get();

        $workHistory = $relationship->dependent->workHistory()
            ->orderByRaw('end_date IS NULL DESC')   // current roles first
            ->orderBy('start_date', 'desc')
            ->get();

        $isMobile = (bool) request()->attributes->get('is_mobile');
        $memberView = $isMobile
            ? 'components-templates.member.mobile.show'
            : 'components-templates.member.show';

        return view($memberView, [
            // Render the mobile profile inside the personal-mobile shell so it
            // keeps the persistent top bar + bottom tabs (matches the in-shell
            // member view served by PersonalMobileController@show).
            'inShell' => $isMobile,
            'shellTitle' => $relationship->dependent->full_name,
            'relationship' => $relationship,
            'latestHealthRecord' => $latestHealthRecord,
            'healthRecords' => $healthRecords,
            'comparisonRecords' => $comparisonRecords,
            'weightHistory' => $weightHistory,
            'invoices' => $invoices,
            'payments' => $payments,
            'tournamentEvents' => $tournamentEvents,
            'awardCounts' => $awardCounts,
            'selfReportedCounts' => $selfReportedCounts,
            'sports' => $sports,
            'awardedAchievements' => $awardedAchievements,
            'goals' => $goals,
            'activeGoalsCount' => $activeGoalsCount,
            'completedGoalsCount' => $completedGoalsCount,
            'successRate' => $successRate,
            'attendanceRecords' => $attendanceRecords,
            'sessionsCompleted' => $sessionsCompleted,
            'noShows' => $noShows,
            'totalSessions' => $totalSessions,
            'attendanceRate' => $attendanceRate,
            'scheduleSessions' => $scheduleSessions,
            'challengeWinRate' => $challengeWinRate,
            'challengesTotal' => $challengesTotal,
            'challengeWins' => $challengeWins,
            'memberChallenges' => $memberChallenges,
            'clubAffiliations' => $clubAffiliations,
            'totalAffiliations' => $totalAffiliations,
            'distinctSkills' => $distinctSkills,
            'totalMembershipDuration' => $totalMembershipDuration,
            'allSkills' => $allSkills,
            'totalInstructors' => $totalInstructors,
            'user' => $relationship->dependent,
            'joinedEventRegistrations' => $joinedEventRegistrations,
            'memberEventLog' => $memberEventLog,
            'certifications' => $certifications,
            'workHistory' => $workHistory,
            'allClubs' => \App\Models\Tenant::orderBy('club_name')->get(['id', 'club_name', 'address', 'logo']),
            'canResetPassword' => $canResetPassword,
            'canRegeneratePassword' => $canRegeneratePassword,
            'canEditBasic' => $canEditBasic,
            'canManageMember' => $canManageMember,
            'canViewSensitive' => $canViewSensitive,
            // Who may settle an outstanding bill (upload proof for review): the
            // member themselves, their real guardian, or a super-admin. Club admins
            // use the approve-payment flow instead, so they only view here.
            'canSettleBills' => $isOwnProfile || $isSuperAdmin || $isRealGuardian,
        ]);
    }

    /**
     * Reset the password for a member.
     * Authorized for: super-admin, club admin of member's club, and the member themselves.
     */
    public function resetPassword(Request $request, $id)
    {
        $user = Auth::user();
        $member = User::findOrFail($id);

        $isSuperAdmin = $user->hasRole('super-admin');
        $isOwnProfile = $user->id == $id;

        $isClubAdminOfMember = false;
        if (! $isSuperAdmin && ! $isOwnProfile && $user->isClubAdmin()) {
            $memberTenantIds = Membership::where('user_id', $member->id)->pluck('tenant_id');
            $isClubAdminOfMember = $memberTenantIds->contains(fn ($tenantId) => $user->isClubAdmin($tenantId));
        }

        if (! $isSuperAdmin && ! $isOwnProfile && ! $isClubAdminOfMember) {
            abort(403);
        }

        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $member->update([
            'password' => Hash::make($request->password),
        ]);

        // An admin (or the member themselves) setting a password is an authoritative
        // provisioning act. Without a verified email the login flow logs the user
        // straight back out ("email not verified"), so the new password would appear
        // not to work. Mark the email verified so the credentials are usable.
        if ($member->email && ! $member->hasVerifiedEmail()) {
            $member->markEmailAsVerified();
        }

        return response()->json(['message' => 'Password has been reset successfully.']);
    }

    /**
     * Generate a brand-new strong password for a member, set it, email it to
     * the member, and return the plaintext once so the admin can copy/share it.
     *
     * Super-admin only — this bypasses all relationship checks ("no matter what").
     */
    public function regeneratePassword($id)
    {
        abort_unless(Auth::user()->hasRole('super-admin'), 403);

        $member = User::findOrFail($id);

        // Strong random password (letters, numbers, symbols).
        $newPassword = \Illuminate\Support\Str::password(16);

        $member->update(['password' => Hash::make($newPassword)]);

        // Provisioning a password implies the account should be usable immediately;
        // without a verified email the login flow bounces the member back out, making
        // the generated password look broken. Mark it verified.
        if ($member->email && ! $member->hasVerifiedEmail()) {
            $member->markEmailAsVerified();
        }

        // Email the new password to the member (best-effort — the admin still
        // sees it on screen, so a mail failure must not fail the request).
        $emailed = false;
        if (! empty($member->email)) {
            try {
                \Illuminate\Support\Facades\Mail::to($member->email)
                    ->send(new \App\Mail\GeneratedPasswordEmail($member, $newPassword));
                $emailed = true;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json([
            'success' => true,
            'password' => $newPassword,
            'emailed' => $emailed,
            'email' => $member->email,
            'message' => $emailed
                ? 'New password generated and emailed to the member.'
                : 'New password generated. Email could not be sent — share it manually.',
        ]);
    }

    /**
     * Show the form for editing the specified member.
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function edit($id)
    {
        $user = Auth::user();

        // Check if user is super-admin or viewing their own profile
        $isSuperAdmin = $user->hasRole('super-admin');
        $isOwnProfile = $user->id == $id;

        // Get the member to edit
        $member = User::findOrFail($id);

        // For super-admin or own profile, create a mock relationship
        // For regular users, verify family relationship exists
        if ($isSuperAdmin || $isOwnProfile) {
            $relationship = (object) [
                'dependent' => $member,
                'relationship_type' => $isOwnProfile ? 'self' : 'admin_view',
                'guardian_user_id' => $user->id,
                'dependent_user_id' => $member->id,
                'is_billing_contact' => false,
            ];
        } else {
            // Regular user - must have family relationship
            $relationship = UserRelationship::where('guardian_user_id', $user->id)
                ->where('dependent_user_id', $id)
                ->with('dependent')
                ->firstOrFail();
        }

        return view('components-templates.member.edit', compact('relationship'));
    }

    /**
     * Update the specified member in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(UpdateMemberRequest $request, $id)
    {
        $validated = $request->validated();

        $user = Auth::user();

        // Check if user is super-admin or updating their own profile
        $isSuperAdmin = $user->hasRole('super-admin');
        $isOwnProfile = $user->id == $id;

        // For regular users, verify family relationship exists
        if (! $isSuperAdmin && ! $isOwnProfile) {
            $relationship = UserRelationship::where('guardian_user_id', $user->id)
                ->where('dependent_user_id', $id)
                ->firstOrFail();
        }

        // Process social links - convert from array of objects to associative array
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

        $member = User::findOrFail($id);

        // Handle profile picture removal
        if ($request->input('remove_profile_picture') == '1') {
            // Delete the profile picture file if it exists
            if ($member->profile_picture && Storage::disk('public')->exists($member->profile_picture)) {
                Storage::disk('public')->delete($member->profile_picture);
            }

            // Also check for old format profile pictures
            $extensions = ['png', 'jpg', 'jpeg', 'webp'];
            foreach ($extensions as $ext) {
                $path = 'images/profiles/profile_'.$member->id.'.'.$ext;
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }

            // Set profile_picture to null
            $member->profile_picture = null;
        }

        // Process emergency contacts from JSON hidden input
        $emergencyContacts = collect(json_decode($request->input('emergency_contacts_json', '[]'), true) ?? [])
            ->filter(fn ($c) => ! empty($c['name']) || ! empty($c['phone']))
            ->map(fn ($c) => [
                'name' => trim($c['name'] ?? ''),
                'relationship' => $c['relationship'] ?? '',
                'phone_code' => $c['phone_code'] ?? '',
                'phone' => trim($c['phone'] ?? ''),
            ])
            ->values()
            ->all();

        // Process health conditions from JSON hidden input
        $healthConditions = collect(json_decode($request->input('health_conditions_json', '[]'), true) ?? [])
            ->filter(fn ($c) => ! empty($c['condition']))
            ->map(fn ($c) => [
                'condition' => trim($c['condition']),
                'noted_at' => $c['noted_at'] ?? now()->format('Y-m-d'),
                'notes' => trim($c['notes'] ?? ''),
            ])
            ->values()
            ->all();

        // Process documents from JSON hidden input (file_path already set by upload-document endpoint)
        $documents = collect(json_decode($request->input('documents_json', '[]'), true) ?? [])
            ->filter(fn ($d) => ! empty($d['type']) || ! empty($d['number']))
            ->map(fn ($d) => [
                'type' => trim($d['type'] ?? ''),
                'number' => trim($d['number'] ?? ''),
                'file_path' => $d['file_path'] ?? null,
                'file_name' => $d['file_name'] ?? null,
                'file_url' => $d['file_url'] ?? null,
                'uploaded_at' => $d['uploaded_at'] ?? now()->format('Y-m-d'),
            ])
            ->values()
            ->all();

        $member->update([
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'mobile' => $mobile,
            'gender' => $validated['gender'],
            'marital_status' => $validated['marital_status'] ?? null,
            'birthdate' => $validated['birthdate'],
            'blood_type' => $validated['blood_type'],
            'nationality' => $validated['nationality'],
            'social_links' => $socialLinks,
            'motto' => $validated['motto'],
            'profile_picture_is_public' => $request->has('profile_picture_is_public') ? true : false,
            'emergency_contacts' => $emergencyContacts,
            'health_conditions' => $healthConditions,
            'documents' => $documents,
        ]);

        // Update relationship if it exists (not for admin or own profile)
        if (! $isSuperAdmin && ! $isOwnProfile && isset($relationship)) {
            $relationship->update([
                'relationship_type' => $validated['relationship_type'] ?? $relationship->relationship_type,
                'is_billing_contact' => $validated['is_billing_contact'] ?? false,
            ]);
        }

        // Return JSON for AJAX requests
        if ($request->wantsJson() || $request->ajax()) {
            // Get the updated profile picture URL
            $profilePictureUrl = null;
            if ($member->profile_picture && file_exists(public_path('storage/'.$member->profile_picture))) {
                $profilePictureUrl = asset('storage/'.$member->profile_picture);
            } else {
                $extensions = ['png', 'jpg', 'jpeg', 'webp'];
                foreach ($extensions as $ext) {
                    $path = 'storage/images/profiles/profile_'.$member->id.'.'.$ext;
                    if (file_exists(public_path($path))) {
                        $profilePictureUrl = asset($path);
                        break;
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Member updated successfully.',
                'profile_picture_url' => $profilePictureUrl,
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

        return redirect()->route('member.show', $member->uuid)
            ->with('success', 'Member updated successfully.');
    }

    /**
     * Upload profile picture for a member.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadPicture(UploadImageRequest $request, $id)
    {

        try {
            $user = Auth::user();

            // Check if user is super-admin or uploading their own picture
            $isSuperAdmin = $user->hasRole('super-admin');
            $isOwnProfile = $user->id == $id;

            // For regular users, verify family relationship exists
            if (! $isSuperAdmin && ! $isOwnProfile) {
                UserRelationship::where('guardian_user_id', $user->id)
                    ->where('dependent_user_id', $id)
                    ->firstOrFail();
            }

            $member = User::findOrFail($id);

            // Validate + store the base64 image with a server-assigned extension
            // (real MIME sniffed from the bytes; PHP/HTML/SVG rejected).
            $fullPath = $this->storeBase64Image($request->image, $request->folder, $request->filename);
            if ($fullPath === null) {
                return response()->json(['success' => false, 'message' => 'Invalid or unsupported image.'], 422);
            }

            // Delete old profile picture if exists
            if ($member->profile_picture && Storage::disk('public')->exists($member->profile_picture)) {
                Storage::disk('public')->delete($member->profile_picture);
            }

            // Update member's profile_picture field
            $member->update(['profile_picture' => $fullPath]);

            return response()->json([
                'success' => true,
                'path' => $fullPath,
                'url' => asset('storage/'.$fullPath),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove a member's profile picture.
     */
    public function removeProfilePicture($id)
    {
        $user = Auth::user();
        $isSuperAdmin = $user->hasRole('super-admin');
        $isOwnProfile = $user->id == $id;

        if (! $isSuperAdmin && ! $isOwnProfile) {
            UserRelationship::where('guardian_user_id', $user->id)
                ->where('dependent_user_id', $id)
                ->firstOrFail();
        }

        $member = User::findOrFail($id);

        if ($member->profile_picture && Storage::disk('public')->exists($member->profile_picture)) {
            Storage::disk('public')->delete($member->profile_picture);
        }

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
     * Toggle the public/private visibility of a member's profile picture.
     */
    public function updateProfilePictureVisibility(\Illuminate\Http\Request $request, $id)
    {
        $request->validate(['is_public' => 'required|boolean']);

        $user = Auth::user();
        $isSuperAdmin = $user->hasRole('super-admin');
        $isOwnProfile = $user->id == $id;

        if (! $isSuperAdmin && ! $isOwnProfile) {
            UserRelationship::where('guardian_user_id', $user->id)
                ->where('dependent_user_id', $id)
                ->firstOrFail();
        }

        $member = User::findOrFail($id);
        $member->update(['profile_picture_is_public' => $request->boolean('is_public')]);

        return response()->json(['success' => true, 'is_public' => $member->profile_picture_is_public]);
    }

    /**
     * Upload an identity document file for a member.
     */
    public function uploadDocument(\Illuminate\Http\Request $request, $id)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp,gif,bmp,tiff',
            ]);

            $user = Auth::user();
            $isSuperAdmin = $user->hasRole('super-admin');
            $isOwnProfile = $user->id == $id;

            if (! $isSuperAdmin && ! $isOwnProfile) {
                UserRelationship::where('guardian_user_id', $user->id)
                    ->where('dependent_user_id', $id)
                    ->firstOrFail();
            }

            $path = $request->file('file')->store('documents/'.$id, 'public');

            return response()->json([
                'success' => true,
                'path' => $path,
                'url' => asset('storage/'.$path),
                'file_name' => $request->file('file')->getClientOriginalName(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Permanently delete a document file from storage for a member.
     */
    public function deleteDocument(\Illuminate\Http\Request $request, $id)
    {
        try {
            $request->validate(['file_path' => 'required|string']);

            $user = Auth::user();
            $isSuperAdmin = $user->hasRole('super-admin');
            $isOwnProfile = $user->id == $id;

            if (! $isSuperAdmin && ! $isOwnProfile) {
                UserRelationship::where('guardian_user_id', $user->id)
                    ->where('dependent_user_id', $id)
                    ->firstOrFail();
            }

            $filePath = $request->input('file_path');

            // Restrict deletion to files within that member's documents directory
            if (! str_starts_with($filePath, 'documents/'.$id.'/')) {
                return response()->json(['success' => false, 'message' => 'Unauthorized file path.'], 403);
            }

            if (Storage::disk('public')->exists($filePath)) {
                Storage::disk('public')->delete($filePath);
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a health record for the specified member.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function storeHealth(HealthRecordRequest $request, $id)
    {
        $validated = $request->validated();

        // Auto-derive BMI when both weight & height are present and BMI wasn't supplied.
        if (empty($validated['bmi']) && ! empty($validated['weight']) && ! empty($validated['height'])) {
            $heightM = (float) $validated['height'] / 100;
            if ($heightM > 0) {
                $validated['bmi'] = round((float) $validated['weight'] / ($heightM * $heightM), 1);
            }
        }

        $user = Auth::user();

        // Check if user is super-admin or adding health for themselves
        $isSuperAdmin = $user->hasRole('super-admin');
        $isOwnProfile = $user->id == $id;

        // For regular users, verify family relationship exists
        if (! $isSuperAdmin && ! $isOwnProfile) {
            UserRelationship::where('guardian_user_id', $user->id)
                ->where('dependent_user_id', $id)
                ->firstOrFail();
        }

        $member = User::findOrFail($id);

        // Check for duplicate date
        $existing = $member->healthRecords()->where('recorded_at', $validated['recorded_at'])->first();
        if ($existing) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => __('member.health_duplicate_date')], 422);
            }

            return redirect()->back()
                ->with('error', 'A health record already exists for this date.');
        }

        $record = $member->healthRecords()->create($validated);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('member.weight_added'),
                'record' => [
                    'id' => $record->id,
                    'weight' => is_null($record->weight) ? null : (float) $record->weight,
                    'height' => is_null($record->height) ? null : (float) $record->height,
                    'bmi' => is_null($record->bmi) ? null : (float) $record->bmi,
                    'recorded_at' => optional($record->recorded_at)->format('Y-m-d'),
                    'recorded_label' => optional($record->recorded_at)->format('d M Y'),
                ],
            ]);
        }

        return redirect()->back()->withFragment('health')
            ->with('success', 'Health record added successfully.');
    }

    /**
     * Update a health record for the specified member.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @param  int  $recordId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateHealth(HealthRecordRequest $request, $id, $recordId)
    {
        $validated = $request->validated();

        $user = Auth::user();

        // Check if user is super-admin or updating their own health
        $isSuperAdmin = $user->hasRole('super-admin');
        $isOwnProfile = $user->id == $id;

        // For regular users, verify family relationship exists
        if (! $isSuperAdmin && ! $isOwnProfile) {
            UserRelationship::where('guardian_user_id', $user->id)
                ->where('dependent_user_id', $id)
                ->firstOrFail();
        }

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
     * Store a tournament record for the specified member.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeTournament(TournamentRequest $request, $id)
    {
        $validated = $request->validated();

        $user = Auth::user();

        // Check if user is super-admin or adding tournament for themselves
        $isSuperAdmin = $user->hasRole('super-admin');
        $isOwnProfile = $user->id == $id;

        // For regular users, verify family relationship exists
        if (! $isSuperAdmin && ! $isOwnProfile) {
            UserRelationship::where('guardian_user_id', $user->id)
                ->where('dependent_user_id', $id)
                ->firstOrFail();
        }

        // Create the tournament event
        $tournament = TournamentEvent::create([
            'user_id' => $id,
            'club_affiliation_id' => $validated['club_affiliation_id'] ?? null,
            'title' => $validated['title'],
            'type' => $validated['type'],
            'sport' => $validated['sport'],
            'date' => $validated['date'],
            'time' => $validated['time'] ?? null,
            'location' => $validated['location'] ?? null,
            'participants_count' => $validated['participants_count'] ?? null,
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

        // Optional supporting evidence — real-byte sniff, SVG rejected, private disk.
        // Support only; it never verifies the claim (see AchievementVerificationService).
        if (! empty($validated['evidence'])) {
            $owner = User::find($id);
            $folder = 'people/'.($owner?->uuid ?? $id).'/achievements/'.$tournament->uuid;
            $path = $this->storeBase64Image($validated['evidence'], $folder, 'evidence', 'local');
            if ($path === null) {
                $tournament->delete();

                return response()->json(['success' => false, 'message' => __('Invalid or unsupported evidence image.')], 422);
            }
            $tournament->forceFill(['evidence_path' => $path])->save();
        }

        $tournament->load(['clubAffiliation', 'performanceResults', 'notesMedia']);

        return response()->json([
            'success' => true,
            'message' => 'Tournament record added successfully',
            'tournament' => $this->tournamentPayload($tournament),
        ]);
    }

    /**
     * Member (or guardian/super-admin) asks the named club to verify a self-claimed
     * tournament. Status transitions live in AchievementVerificationService — this
     * controller only authorizes and delegates.
     */
    public function requestTournamentVerification(Request $request, $id, string $uuid, \App\Services\AchievementVerificationService $service)
    {
        $this->authorizeMemberWrite(Auth::user(), (int) $id);

        $tournament = TournamentEvent::where('uuid', $uuid)
            ->where('user_id', $id)
            ->with('clubAffiliation.tenant')
            ->firstOrFail();

        if (! $tournament->clubAffiliation?->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => __('Link this record to a club on the platform to request verification.'),
            ], 422);
        }

        $service->requestVerification($tournament, Auth::user());

        return response()->json([
            'success' => true,
            'message' => __('Verification requested from the club.'),
            'verification' => $this->verificationPayload($tournament->fresh('verifiedByTenant')),
        ]);
    }

    /**
     * Stream a claim's private evidence image to an authorized viewer:
     * the member / their guardian / super-admin, or an admin of the named club.
     */
    public function tournamentEvidence($id, string $uuid)
    {
        $tournament = TournamentEvent::where('uuid', $uuid)
            ->where('user_id', $id)
            ->with('clubAffiliation')
            ->firstOrFail();

        abort_unless($tournament->evidence_path, 404);

        $user = Auth::user();
        $canView = $user->hasRole('super-admin')
            || (int) $user->id === (int) $id
            || UserRelationship::where('guardian_user_id', $user->id)->where('dependent_user_id', $id)->exists()
            || (($t = $tournament->clubAffiliation?->tenant_id) && $user->isClubAdmin((int) $t));

        abort_unless($canView, 403);
        abort_unless(Storage::disk('local')->exists($tournament->evidence_path), 404);

        return response()->file(Storage::disk('local')->path($tournament->evidence_path));
    }

    /**
     * Shared authorization ladder for member self-service writes:
     * super-admin → own profile → confirmed guardian.
     */
    private function authorizeMemberWrite(User $user, int $memberId): void
    {
        if ($user->hasRole('super-admin') || (int) $user->id === $memberId) {
            return;
        }

        UserRelationship::where('guardian_user_id', $user->id)
            ->where('dependent_user_id', $memberId)
            ->firstOrFail();
    }

    /**
     * Verification block for in-place UI patching (No-Reload rule).
     */
    private function verificationPayload(TournamentEvent $tournament): array
    {
        return [
            'status' => $tournament->verification_status,
            'method' => $tournament->verification_method,
            'verified_club' => $tournament->verifiedByTenant?->tr('club_name') ?? $tournament->verifiedByTenant?->club_name,
            'can_request' => (bool) $tournament->clubAffiliation?->tenant_id
                && ! in_array($tournament->verification_status, [TournamentEvent::STATUS_VERIFIED, TournamentEvent::STATUS_PENDING], true),
            'request_url' => route('member.tournament.request-verification', [$tournament->user_id, $tournament->uuid]),
            'evidence_url' => $tournament->evidence_path
                ? route('member.tournament.evidence', [$tournament->user_id, $tournament->uuid])
                : null,
        ];
    }

    /**
     * Build a JSON-friendly payload for a tournament event for in-place rendering.
     */
    private function tournamentPayload(TournamentEvent $tournament): array
    {
        return [
            'id' => $tournament->id,
            'uuid' => $tournament->uuid,
            'title' => $tournament->title,
            'type' => $tournament->type,
            'type_label' => ucfirst($tournament->type),
            'sport' => $tournament->sport,
            'date' => optional($tournament->date)->format('M j, Y'),
            'time' => optional($tournament->time)->format('H:i'),
            'location' => $tournament->location,
            'participants_count' => $tournament->participants_count,
            'verification' => $this->verificationPayload($tournament),
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

    /**
     * Update the specified goal.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $goalId
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateGoal(UpdateGoalRequest $request, $goalId)
    {
        $user = Auth::user();

        // Find the goal
        $goal = Goal::findOrFail($goalId);

        // Check if user is super-admin
        $isSuperAdmin = $user->hasRole('super-admin');

        // Check if user is authorized to update this goal
        if (! $isSuperAdmin && $goal->user_id !== $user->id) {
            // Check if user is guardian of the goal owner
            $relationship = UserRelationship::where('guardian_user_id', $user->id)
                ->where('dependent_user_id', $goal->user_id)
                ->first();

            if (! $relationship) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }
        }

        // Validate the request
        $validated = $request->validated();

        // Closing a goal for the first time requires an "after" proof photo — once
        // completed_at is stamped it never resets, so re-saving an already-completed
        // goal (e.g. tweaking current_progress_value) doesn't demand a new photo.
        $isFirstCompletion = $validated['status'] === 'completed' && $goal->status !== 'completed';
        if ($isFirstCompletion) {
            if (empty($validated['after_proof'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attach an "after" photo to close this goal.',
                ], 422);
            }

            $path = $this->storeBase64Image($validated['after_proof'], 'goal-proofs', 'goal_after_'.$goal->id.'_'.time());
            if (! $path) {
                return response()->json(['success' => false, 'message' => 'Please upload a valid image (JPG or PNG).'], 422);
            }

            $goal->after_proof = $path;
            $goal->completed_at = now();
        }

        // Update the goal
        $goal->update([
            'current_progress_value' => $validated['current_progress_value'],
            'status' => $validated['status'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Goal updated successfully',
            'goal' => [
                'id' => $goal->id,
                'title' => $goal->title,
                'description' => $goal->description,
                'unit' => $goal->unit,
                'status' => $goal->status,
                'current_progress_value' => $goal->current_progress_value,
                'target_value' => $goal->target_value,
                'progress_percentage' => $goal->progress_percentage,
                'priority_level' => $goal->priority_level,
                'before_proof' => $goal->before_proof ? asset('storage/'.$goal->before_proof) : null,
                'after_proof' => $goal->after_proof ? asset('storage/'.$goal->after_proof) : null,
                'completed_at' => optional($goal->completed_at)->format('M j, Y'),
                'days_taken' => $goal->days_taken,
            ],
        ]);
    }

    /**
     * Store a new goal for the specified member.
     */
    public function storeGoal(\App\Http\Requests\StoreGoalRequest $request, $id)
    {
        $user = Auth::user();

        // Authorize: super-admin, own profile, or guardian of the member.
        $isSuperAdmin = $user->hasRole('super-admin');
        if (! $isSuperAdmin && $user->id != $id) {
            UserRelationship::where('guardian_user_id', $user->id)
                ->where('dependent_user_id', $id)
                ->firstOrFail();
        }

        // Ensure the target user exists (and is a real member row).
        User::findOrFail($id);

        $validated = $request->validated();

        $beforeProof = $this->storeBase64Image($validated['before_proof'], 'goal-proofs', 'goal_before_'.$id.'_'.time());
        if (! $beforeProof) {
            return response()->json(['success' => false, 'message' => 'Please upload a valid image (JPG or PNG).'], 422);
        }

        $goal = Goal::create([
            'user_id' => $id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'start_date' => $validated['start_date'] ?? now()->toDateString(),
            'target_date' => $validated['target_date'],
            'current_progress_value' => $validated['current_progress_value'] ?? 0,
            'target_value' => $validated['target_value'],
            'status' => 'active',
            'priority_level' => $validated['priority_level'] ?? 'medium',
            'unit' => $validated['unit'],
            'icon_type' => $validated['icon_type'] ?? 'bi-bullseye',
            'before_proof' => $beforeProof,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Goal created successfully',
            'goal' => [
                'id' => $goal->id,
                'title' => $goal->title,
                'description' => $goal->description,
                'unit' => $goal->unit,
                'status' => $goal->status,
                'current_progress_value' => (float) $goal->current_progress_value,
                'target_value' => (float) $goal->target_value,
                'progress_percentage' => round($goal->progress_percentage, 1),
                'priority_level' => $goal->priority_level,
                'icon_type' => $goal->icon_type,
                'target_date' => optional($goal->target_date)->format('M j, Y'),
                'before_proof' => asset('storage/'.$goal->before_proof),
            ],
        ]);
    }

    /**
     * Store a new attendance record for the specified member.
     */
    public function storeAttendance(\App\Http\Requests\StoreAttendanceRequest $request, $id)
    {
        $user = Auth::user();

        $isSuperAdmin = $user->hasRole('super-admin');
        if (! $isSuperAdmin && $user->id != $id) {
            UserRelationship::where('guardian_user_id', $user->id)
                ->where('dependent_user_id', $id)
                ->firstOrFail();
        }

        User::findOrFail($id);

        $validated = $request->validated();

        $record = Attendance::create([
            'member_id' => $id,
            'session_type' => $validated['session_type'],
            'trainer_name' => $validated['trainer_name'] ?? null,
            'session_datetime' => $validated['session_datetime'],
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Attendance record added',
            'record' => [
                'id' => $record->id,
                'session_type' => $record->session_type,
                'trainer_name' => $record->trainer_name,
                'status' => $record->status,
                'status_label' => $record->status === 'completed' ? 'Completed' : 'No Show',
                'notes' => $record->notes,
                'date' => $record->session_datetime->format('M j, Y'),
                'time' => $record->session_datetime->format('g:i A'),
            ],
        ]);
    }

    /**
     * Store a new free-form event-participation log entry for the member.
     */
    public function storeMemberEvent(\App\Http\Requests\StoreMemberEventRequest $request, $id)
    {
        $user = Auth::user();

        $isSuperAdmin = $user->hasRole('super-admin');
        if (! $isSuperAdmin && $user->id != $id) {
            UserRelationship::where('guardian_user_id', $user->id)
                ->where('dependent_user_id', $id)
                ->firstOrFail();
        }

        User::findOrFail($id);

        $validated = $request->validated();

        $event = \App\Models\MemberEvent::create([
            'user_id' => $id,
            'title' => $validated['title'],
            'event_date' => $validated['event_date'],
            'location' => $validated['location'] ?? null,
            'role' => $validated['role'] ?? null,
            'result' => $validated['result'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Event added to log',
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'location' => $event->location,
                'role' => $event->role,
                'result' => $event->result,
                'notes' => $event->notes,
                'date' => $event->event_date->format('M j, Y'),
                'day' => $event->event_date->format('D'),
                'day_num' => $event->event_date->format('d'),
                'month' => $event->event_date->format('M'),
            ],
        ]);
    }

    // ===================================================================
    // Certifications — member-owned, self-managed (super-admin / self / guardian)
    // ===================================================================

    /**
     * Store a new certification for the member.
     */
    public function storeCertification(StoreCertificationRequest $request, $id)
    {
        $this->authorizeMemberWrite(Auth::user(), (int) $id);
        $member = User::findOrFail($id);

        $validated = $request->validated();

        $imagePath = null;
        if (! empty($validated['image'])) {
            $imagePath = $this->storeBase64Image(
                $validated['image'],
                'people/'.$member->uuid.'/certifications',
                'cert_'.time()
            );
            if ($imagePath === null) {
                return response()->json(['success' => false, 'message' => 'Invalid or unsupported image.'], 422);
            }
        }

        $cert = MemberCertification::create([
            'user_id' => $member->id,
            'title' => $validated['title'],
            'issuer' => $validated['issuer'] ?? null,
            'issue_date' => $validated['issue_date'] ?? null,
            'expiry_date' => $validated['expiry_date'] ?? null,
            'credential_id' => $validated['credential_id'] ?? null,
            'credential_url' => $validated['credential_url'] ?? null,
            'image_path' => $imagePath,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Certification added',
            'certification' => $this->certificationPayload($cert),
        ]);
    }

    /**
     * Update an existing certification.
     */
    public function updateCertification(StoreCertificationRequest $request, $certificationId)
    {
        $cert = MemberCertification::findOrFail($certificationId);
        $this->authorizeMemberWrite(Auth::user(), (int) $cert->user_id);

        $validated = $request->validated();

        if (! empty($validated['image'])) {
            $newPath = $this->storeBase64Image(
                $validated['image'],
                'people/'.$cert->user->uuid.'/certifications',
                'cert_'.time()
            );
            if ($newPath === null) {
                return response()->json(['success' => false, 'message' => 'Invalid or unsupported image.'], 422);
            }
            // Replace: drop the old file only after a successful store.
            if ($cert->image_path && $cert->image_path !== $newPath) {
                Storage::disk('public')->delete($cert->image_path);
            }
            $cert->image_path = $newPath;
        }

        $cert->fill([
            'title' => $validated['title'],
            'issuer' => $validated['issuer'] ?? null,
            'issue_date' => $validated['issue_date'] ?? null,
            'expiry_date' => $validated['expiry_date'] ?? null,
            'credential_id' => $validated['credential_id'] ?? null,
            'credential_url' => $validated['credential_url'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Certification updated',
            'certification' => $this->certificationPayload($cert),
        ]);
    }

    /**
     * Delete a certification (its photo is purged first by the model trait).
     */
    public function destroyCertification($certificationId)
    {
        $cert = MemberCertification::findOrFail($certificationId);
        $this->authorizeMemberWrite(Auth::user(), (int) $cert->user_id);

        $cert->delete();

        return response()->json(['success' => true, 'message' => 'Certification removed', 'id' => (int) $certificationId]);
    }

    // ===================================================================
    // Work history — member-owned, self-managed
    // ===================================================================

    /**
     * Store a new work-history entry for the member.
     */
    public function storeWorkHistory(StoreWorkHistoryRequest $request, $id)
    {
        $this->authorizeMemberWrite(Auth::user(), (int) $id);
        $member = User::findOrFail($id);

        $validated = $request->validated();

        $work = MemberWorkHistory::create([
            'user_id' => $member->id,
            'title' => $validated['title'],
            'organization' => $validated['organization'],
            'employment_type' => $validated['employment_type'] ?? null,
            'location' => $validated['location'] ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Work experience added',
            'work' => $this->workPayload($work),
        ]);
    }

    /**
     * Update an existing work-history entry.
     */
    public function updateWorkHistory(StoreWorkHistoryRequest $request, $workId)
    {
        $work = MemberWorkHistory::findOrFail($workId);
        $this->authorizeMemberWrite(Auth::user(), (int) $work->user_id);

        $validated = $request->validated();

        $work->fill([
            'title' => $validated['title'],
            'organization' => $validated['organization'],
            'employment_type' => $validated['employment_type'] ?? null,
            'location' => $validated['location'] ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
            'description' => $validated['description'] ?? null,
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Work experience updated',
            'work' => $this->workPayload($work),
        ]);
    }

    /**
     * Delete a work-history entry.
     */
    public function destroyWorkHistory($workId)
    {
        $work = MemberWorkHistory::findOrFail($workId);
        $this->authorizeMemberWrite(Auth::user(), (int) $work->user_id);

        $work->delete();

        return response()->json(['success' => true, 'message' => 'Work experience removed', 'id' => (int) $workId]);
    }

    /**
     * Serialisable certification block for in-place UI patching (No-Reload rule).
     */
    private function certificationPayload(MemberCertification $cert): array
    {
        return [
            'id' => $cert->id,
            'title' => $cert->title,
            'issuer' => $cert->issuer,
            'issue_date' => optional($cert->issue_date)->format('Y-m-d'),
            'issue_label' => optional($cert->issue_date)->format('M Y'),
            'expiry_date' => optional($cert->expiry_date)->format('Y-m-d'),
            'expiry_label' => optional($cert->expiry_date)->format('M Y'),
            'expired' => $cert->isExpired(),
            'credential_id' => $cert->credential_id,
            'credential_url' => $cert->credential_url,
            'image' => $cert->image_path ? asset('storage/'.$cert->image_path) : null,
            'notes' => $cert->notes,
        ];
    }

    /**
     * Serialisable work-history block for in-place UI patching (No-Reload rule).
     */
    private function workPayload(MemberWorkHistory $work): array
    {
        return [
            'id' => $work->id,
            'title' => $work->title,
            'organization' => $work->organization,
            'employment_type' => $work->employment_type,
            'location' => $work->location,
            'start_date' => optional($work->start_date)->format('Y-m-d'),
            'end_date' => optional($work->end_date)->format('Y-m-d'),
            'start_label' => optional($work->start_date)->format('M Y'),
            'end_label' => $work->end_date ? $work->end_date->format('M Y') : null,
            'current' => $work->isCurrent(),
            'description' => $work->description,
        ];
    }

    /**
     * Confirm and remove the specified member from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function confirmDelete(ConfirmDeleteRequest $request, $id)
    {
        $validated = $request->validated();

        $user = Auth::user();

        // Check if user is super-admin
        $isSuperAdmin = $user->hasRole('super-admin');

        // Prevent deleting own account
        if ($user->id == $id) {
            return redirect()->back()
                ->with('error', 'You cannot delete your own account.');
        }

        // For regular users, verify family relationship exists
        if (! $isSuperAdmin) {
            UserRelationship::where('guardian_user_id', $user->id)
                ->where('dependent_user_id', $id)
                ->firstOrFail();
        }

        $member = User::findOrFail($id);

        // Verify the confirmation name matches
        if ($validated['confirm_name'] !== $member->full_name) {
            return redirect()->back()
                ->with('error', 'Confirmation name does not match. Account deletion cancelled.');
        }

        $memberName = $member->full_name;
        $member->delete();

        // Redirect based on user type
        if ($isSuperAdmin) {
            return redirect()->route('admin.platform.members')
                ->with('success', $memberName.' has been removed successfully.');
        }

        return redirect()->route('members.index')
            ->with('success', 'Member removed successfully.');
    }

    // ─── Affiliation CRUD ────────────────────────────────────────────────────────

    private function authorizeForMember(int $id): void
    {
        $user = Auth::user();
        if ($user->hasRole('super-admin') || $user->id == $id) {
            return;
        }
        UserRelationship::where('guardian_user_id', $user->id)
            ->where('dependent_user_id', $id)
            ->firstOrFail();
    }

    public function storeAffiliation(\Illuminate\Http\Request $request, $id)
    {
        $this->authorizeForMember($id);

        $validated = $request->validate([
            'tenant_id' => 'nullable|exists:tenants,id',
            'club_name' => 'required_without:tenant_id|nullable|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'coaches' => 'nullable|string|max:1000',
        ]);

        // If a platform club is selected, pull its data
        $tenant = null;
        if (! empty($validated['tenant_id'])) {
            $tenant = \App\Models\Tenant::findOrFail($validated['tenant_id']);
        }

        $coaches = null;
        if (! empty($validated['coaches'])) {
            $coaches = array_values(array_filter(array_map('trim', explode(',', $validated['coaches']))));
        }

        $member = User::findOrFail($id);
        $affiliation = $member->clubAffiliations()->create([
            'tenant_id' => $tenant?->id,
            'club_name' => $tenant ? $tenant->club_name : $validated['club_name'],
            'logo' => $tenant?->logo ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
            'location' => $tenant ? ($tenant->address ?? $validated['location']) : ($validated['location'] ?? null),
            'description' => $validated['description'] ?? null,
            'coaches' => $coaches,
        ]);

        $logoUrl = null;
        if ($tenant?->logo) {
            $logoUrl = asset('storage/'.$tenant->logo);
        } elseif ($affiliation->logo) {
            $logoUrl = filter_var($affiliation->logo, FILTER_VALIDATE_URL)
                ? $affiliation->logo
                : asset('storage/'.$affiliation->logo);
        }

        return response()->json([
            'success' => true,
            'message' => 'Affiliation added successfully.',
            'id' => $affiliation->id,
            'affiliation' => [
                'id' => $affiliation->id,
                'club_name' => $affiliation->club_name,
                'logo_url' => $logoUrl,
                'location' => $affiliation->location,
                'description' => $affiliation->description,
                'coaches' => is_array($affiliation->coaches) ? implode(', ', $affiliation->coaches) : '',
                'start_date' => $affiliation->start_date?->format('Y-m-d'),
                'end_date' => $affiliation->end_date?->format('Y-m-d'),
                'start_label' => $affiliation->start_date?->format('M Y'),
                'end_label' => $affiliation->end_date ? $affiliation->end_date->format('M Y') : 'Present',
                'is_ongoing' => ! $affiliation->end_date,
                'formatted_duration' => $affiliation->formatted_duration,
            ],
        ]);
    }

    public function updateAffiliation(\Illuminate\Http\Request $request, $id, $affiliationId)
    {
        $this->authorizeForMember($id);

        $validated = $request->validate([
            'club_name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'coaches' => 'nullable|string|max:1000',
        ]);

        $coaches = null;
        if (! empty($validated['coaches'])) {
            $coaches = array_values(array_filter(array_map('trim', explode(',', $validated['coaches']))));
        }

        $member = User::findOrFail($id);
        $affiliation = $member->clubAffiliations()->findOrFail($affiliationId);
        $affiliation->update([
            'club_name' => $validated['club_name'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
            'location' => $validated['location'] ?? null,
            'description' => $validated['description'] ?? null,
            'coaches' => $coaches,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Affiliation updated successfully.',
            'affiliation' => [
                'id' => $affiliation->id,
                'club_name' => $affiliation->club_name,
                'location' => $affiliation->location,
                'description' => $affiliation->description,
                'coaches' => is_array($affiliation->coaches) ? implode(', ', $affiliation->coaches) : '',
                'start_date' => $affiliation->start_date?->format('Y-m-d'),
                'end_date' => $affiliation->end_date?->format('Y-m-d'),
                'start_label' => $affiliation->start_date?->format('M Y'),
                'end_label' => $affiliation->end_date ? $affiliation->end_date->format('M Y') : 'Present',
                'is_ongoing' => ! $affiliation->end_date,
                'formatted_duration' => $affiliation->formatted_duration,
            ],
        ]);
    }

    public function destroyAffiliation($id, $affiliationId)
    {
        $this->authorizeForMember($id);

        $member = User::findOrFail($id);
        $affiliation = $member->clubAffiliations()->findOrFail($affiliationId);
        $affiliation->skillAcquisitions()->delete();
        $affiliation->affiliationMedia()->delete();
        $affiliation->delete();

        return response()->json(['success' => true, 'message' => 'Affiliation deleted successfully.']);
    }

    /**
     * Activities a member can attribute a skill to for a given affiliation:
     * the named platform club's real activities, else global-catalog suggestions.
     */
    public function affiliationActivities($id, $affiliationId)
    {
        $this->authorizeForMember((int) $id);

        $member = User::findOrFail($id);
        $affiliation = $member->clubAffiliations()->findOrFail($affiliationId);

        // The whole global directory, for the picker's "All activities" group. Only a
        // real club activity can carry an activity_id (provenance is scoped to the
        // affiliation's club by storeAffiliationSkill's validation), so catalog rows
        // are name-only suggestions.
        $catalog = \App\Models\ActivityCatalog::where('is_active', true)
            ->orderByDesc('usage_count')->orderBy('name')
            ->get(['id', 'name', 'translations'])
            ->map(fn ($a) => ['id' => null, 'name' => $a->tr('name') ?? $a->name])
            ->values();

        if ($affiliation->tenant_id) {
            $activities = \App\Models\ClubActivity::where('tenant_id', $affiliation->tenant_id)
                ->get(['id', 'name', 'translations'])
                ->map(fn ($a) => ['id' => $a->id, 'name' => $a->tr('name') ?? $a->name])
                ->values();

            // Don't offer a catalog duplicate of something the club already runs —
            // the club row is the one worth picking (it carries the id).
            $clubNames = $activities->pluck('name')->map(fn ($n) => mb_strtolower($n))->all();
            $suggestions = $catalog->reject(fn ($a) => in_array(mb_strtolower($a['name']), $clubNames, true))->values();

            return response()->json([
                'linked' => true,
                'activities' => $activities,
                'suggestions' => $suggestions,
                'affiliation' => $this->affiliationBounds($affiliation),
            ]);
        }

        // Off-platform club → free-text only, with the directory as suggestions.
        return response()->json([
            'linked' => false,
            'activities' => collect(),
            'suggestions' => $catalog,
            'affiliation' => $this->affiliationBounds($affiliation),
        ]);
    }

    public function storeAffiliationSkill(\Illuminate\Http\Request $request, $id, $affiliationId)
    {
        $this->authorizeForMember($id);

        $member = User::findOrFail($id);
        $affiliation = $member->clubAffiliations()->findOrFail($affiliationId);

        // A skill cannot predate the affiliation it belongs to, and cannot run past its
        // end. Enforced server-side — the modal mirrors these bounds only as affordances.
        $affStart = $affiliation->start_date?->toDateString();
        $affEnd = $affiliation->end_date?->toDateString();
        $latest = $affEnd && $affEnd < now()->toDateString() ? $affEnd : now()->toDateString();

        $validated = $request->validate([
            'skill_name' => 'required|string|max:255',
            'proficiency_level' => 'required|in:beginner,intermediate,advanced,expert',
            'start_date' => array_values(array_filter([
                'nullable', 'date', 'required_with:end_date',
                $affStart ? 'after_or_equal:'.$affStart : null,
                'before_or_equal:'.$latest,
            ])),
            // Span is expressed EITHER as an end date OR as a number of months — one of
            // the two is required so a skill always has a duration to show.
            'end_date' => array_values(array_filter([
                'nullable', 'date', 'required_without:duration_months', 'after:start_date',
                $affEnd ? 'before_or_equal:'.$affEnd : null,
            ])),
            'duration_months' => 'nullable|integer|min:1|max:600|required_without:end_date',
            'notes' => 'nullable|string|max:500',
            // Provenance: the activity that produced the skill. A real club activity
            // (scoped to THIS affiliation's club) or a free-text name for off-platform.
            'activity_id' => [
                'nullable',
                \Illuminate\Validation\Rule::exists('club_activities', 'id')->where('tenant_id', $affiliation->tenant_id),
            ],
            'activity_name' => 'nullable|string|max:255',
            'instructor_id' => [
                'nullable',
                \Illuminate\Validation\Rule::exists('club_instructors', 'id')->where('tenant_id', $affiliation->tenant_id),
            ],
        ]);

        $skill = $affiliation->skillAcquisitions()->create([
            'user_id' => $member->id,
            'skill_name' => $validated['skill_name'],
            'activity_id' => $validated['activity_id'] ?? null,
            'activity_name' => $validated['activity_name'] ?? null,
            'instructor_id' => $validated['instructor_id'] ?? null,
            'proficiency_level' => $validated['proficiency_level'],
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            // formatted_duration reads duration_months, so derive it when the member
            // expressed the span as an end date instead.
            'duration_months' => $validated['duration_months']
                ?? $this->monthsBetween($validated['start_date'] ?? null, $validated['end_date'] ?? null),
            'notes' => $validated['notes'] ?? null,
            'icon' => 'bi-star',
        ]);
        // status defaults to self_reported (HasVerificationState); never client-set.

        return response()->json([
            'success' => true,
            'message' => 'Skill added successfully.',
            'id' => $skill->id,
            'skill' => $this->skillPayload($skill->fresh(['activity'])),
        ]);
    }

    /** Date window a skill on this affiliation must fall inside (mirrored by the picker). */
    private function affiliationBounds(\App\Models\ClubAffiliation $affiliation): array
    {
        $end = $affiliation->end_date?->toDateString();

        return [
            'start_date' => $affiliation->start_date?->toDateString(),
            'end_date' => $end,
            'club_name' => $affiliation->club_name,
            // Latest day a skill may start: the affiliation's end if it already closed,
            // otherwise today (a skill cannot start in the future).
            'max_start' => $end && $end < now()->toDateString() ? $end : now()->toDateString(),
        ];
    }

    /** Whole months between two ISO dates, floored at 1 (a span always reads as >= 1 month). */
    private function monthsBetween(?string $start, ?string $end): int
    {
        if (! $start || ! $end) {
            return 1;
        }

        return max(1, (int) round(\Carbon\Carbon::parse($start)->floatDiffInMonths(\Carbon\Carbon::parse($end))));
    }

    /** JSON shape for a skill row (provenance + verification), for in-place rendering. */
    private function skillPayload(SkillAcquisition $skill): array
    {
        return [
            'id' => $skill->id,
            'uuid' => $skill->uuid,
            'skill_name' => $skill->skill_name,
            'activity' => $skill->activity?->tr('name') ?? $skill->activity_name,
            'proficiency_level' => $skill->proficiency_level,
            'formatted_duration' => $skill->formatted_duration,
            'start_label' => $skill->start_date ? $skill->start_date->format('M Y') : null,
            'badge_color' => $skill->proficiency_level == 'expert' ? 'danger' : ($skill->proficiency_level == 'advanced' ? 'warning' : ($skill->proficiency_level == 'intermediate' ? 'info' : 'secondary')),
            'verification' => [
                'status' => $skill->verification_status,
                'verified_club' => $skill->verifiedByTenant?->tr('club_name') ?? $skill->verifiedByTenant?->club_name,
                'can_request' => (bool) $skill->clubAffiliation?->tenant_id
                    && ! in_array($skill->verification_status, [SkillAcquisition::STATUS_VERIFIED, SkillAcquisition::STATUS_PENDING], true),
                'request_url' => route('member.skill.request-verification', [$skill->user_id, $skill->club_affiliation_id ?? 0, $skill->uuid]),
            ],
        ];
    }

    /**
     * Member (or guardian/super-admin) asks the named club to verify a self-claimed skill.
     */
    public function requestSkillVerification($id, $affiliationId, string $uuid, \App\Services\AchievementVerificationService $service)
    {
        $this->authorizeMemberWrite(Auth::user(), (int) $id);

        $skill = SkillAcquisition::where('uuid', $uuid)
            ->where('user_id', $id)
            ->with('clubAffiliation.tenant')
            ->firstOrFail();

        if (! $skill->clubAffiliation?->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => __('Link this skill to a club on the platform to request verification.'),
            ], 422);
        }

        $service->requestVerification($skill, Auth::user());

        return response()->json([
            'success' => true,
            'message' => __('Verification requested from the club.'),
            'verification' => [
                'status' => $skill->verification_status,
                'verified_club' => $skill->verifiedByTenant?->tr('club_name') ?? $skill->verifiedByTenant?->club_name,
            ],
        ]);
    }

    public function destroyAffiliationSkill($id, $affiliationId, $skillId)
    {
        $this->authorizeForMember($id);

        $member = User::findOrFail($id);
        $affiliation = $member->clubAffiliations()->findOrFail($affiliationId);
        $affiliation->skillAcquisitions()->findOrFail($skillId)->delete();

        return response()->json(['success' => true, 'message' => 'Skill removed successfully.']);
    }

    public function storeAffiliationMedia(\Illuminate\Http\Request $request, $id, $affiliationId)
    {
        $this->authorizeForMember($id);

        $validated = $request->validate([
            'media_type' => 'required|in:certificate,photo,video,document',
            'title' => 'required|string|max:255',
            'media_url' => 'required|string|max:500',
            'description' => 'nullable|string|max:500',
        ]);

        $member = User::findOrFail($id);
        $affiliation = $member->clubAffiliations()->findOrFail($affiliationId);
        $media = $affiliation->affiliationMedia()->create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Media added successfully.',
            'id' => $media->id,
            'media' => [
                'id' => $media->id,
                'title' => $media->title,
                'full_url' => $media->full_url,
                'icon_class' => $media->icon_class,
            ],
        ]);
    }

    public function destroyAffiliationMedia($id, $affiliationId, $mediaId)
    {
        $this->authorizeForMember($id);

        $member = User::findOrFail($id);
        $affiliation = $member->clubAffiliations()->findOrFail($affiliationId);
        $media = $affiliation->affiliationMedia()->findOrFail($mediaId);

        if (! filter_var($media->media_url, FILTER_VALIDATE_URL)) {
            Storage::disk('public')->delete($media->media_url);
        }

        $media->delete();

        return response()->json(['success' => true, 'message' => 'Media removed successfully.']);
    }

    // ─── End Affiliation CRUD ─────────────────────────────────────────────────────

    /**
     * Remove the specified member from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($id)
    {
        $user = Auth::user();

        // Check if user is super-admin
        $isSuperAdmin = $user->hasRole('super-admin');

        // Prevent deleting own account
        if ($user->id == $id) {
            return redirect()->back()
                ->with('error', 'You cannot delete your own account.');
        }

        // For regular users, verify family relationship exists
        if (! $isSuperAdmin) {
            UserRelationship::where('guardian_user_id', $user->id)
                ->where('dependent_user_id', $id)
                ->firstOrFail();
        }

        $member = User::findOrFail($id);

        $ownedClubs = \App\Models\Tenant::where('owner_user_id', $member->id)->pluck('club_name');
        if ($ownedClubs->isNotEmpty()) {
            return redirect()->back()
                ->with('error', 'Cannot delete this account. They are the owner of the following club(s): '.$ownedClubs->join(', ').'. Transfer ownership first.');
        }

        $memberName = $member->full_name;

        // Redirect based on user type
        if ($isSuperAdmin) {
            return redirect()->route('admin.platform.members')
                ->with('success', $memberName.' has been removed successfully.');
        }

        return redirect()->route('members.index')
            ->with('success', 'Member removed successfully.');
    }
}
