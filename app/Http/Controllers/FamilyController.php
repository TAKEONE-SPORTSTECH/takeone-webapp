<?php

namespace App\Http\Controllers;

use App\Http\Requests\HealthRecordRequest;
use App\Http\Requests\StoreFamilyMemberRequest;
use App\Http\Requests\TournamentRequest;
use App\Http\Requests\UpdateFamilyMemberRequest;
use App\Http\Requests\UpdateGoalRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\UploadImageRequest;
use App\Models\Attendance;
use App\Models\Goal;
use App\Models\Invoice;
use App\Models\TournamentEvent;
use App\Models\Person;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\UserRelationship;
use App\Services\FamilyService;
use App\Services\KinshipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class FamilyController extends Controller
{
    use \App\Traits\ComputesAttendanceStats;
    use \App\Traits\StoresBase64Images;

    protected $familyService;

    public function __construct(FamilyService $familyService, private readonly KinshipService $kin)
    {
        $this->familyService = $familyService;
    }

    /**
     * Display the family dashboard.
     *
     * @return \Illuminate\View\View
     */
    public function dashboard()
    {
        $user = Auth::user();
        $dependents = UserRelationship::where('guardian_user_id', $user->id)
            ->with('dependent')
            ->whereHas('dependent')
            ->get()
            ->sortBy(function ($relationship) {
                return $relationship->dependent->full_name;
            });

        return view('family.index', compact('user', 'dependents'));
    }

    /**
     * Display the current user's profile.
     *
     * @return \Illuminate\View\View
     */
    public function profile()
    {
        $user = Auth::user();

        // Fetch health data
        $latestHealthRecord = $user->healthRecords()->latest('recorded_at')->first();
        $healthRecords = $user->healthRecords()->orderBy('recorded_at', 'desc')->paginate(10);
        $comparisonRecords = $user->healthRecords()->orderBy('recorded_at', 'desc')->take(2)->get();

        // Fetch invoices
        $invoices = Invoice::where('student_user_id', $user->id)->orWhere('payer_user_id', $user->id)->with(['student', 'tenant'])->get();

        // Fetch tournament data
        $tournamentEvents = $user->tournamentEvents()
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
        $goals = $user->goals()->orderBy('created_at', 'desc')->get();
        $activeGoalsCount = $goals->where('status', 'active')->count();
        $completedGoalsCount = $goals->where('status', 'completed')->count();
        $successRate = $goals->count() > 0 ? round(($completedGoalsCount / $goals->count()) * 100) : 0;

        // Attendance log (manual entries via the "Add attendance record" feature) — still used
        // for the entry list, but the summary numbers below no longer derive from it.
        $attendanceRecords = $user->attendanceRecords()->orderBy('session_datetime', 'desc')->get();

        // Attendance summary: total sessions = the package's scheduled classes across the
        // subscription period; completed = trainer-marked class_attendances for those classes.
        [
            'sessionsCompleted' => $sessionsCompleted,
            'noShows' => $noShows,
            'totalSessions' => $totalSessions,
            'attendanceRate' => $attendanceRate,
            'scheduleSessions' => $scheduleSessions,
        ] = $this->computeAttendanceStats($user);

        // Fetch affiliations data with enhanced relationships
        $clubAffiliations = $user->clubAffiliations()
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

        // Pass user directly and a flag to indicate it's the current user's profile
        return view('family.show', [
            'relationship' => (object) [
                'dependent' => $user,
                'relationship_type' => 'self',
                'guardian_user_id' => $user->id,
                'dependent_user_id' => $user->id,
            ],
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
            'totalSessions' => $totalSessions,
            'attendanceRate' => $attendanceRate,
            'scheduleSessions' => $scheduleSessions,
            'clubAffiliations' => $clubAffiliations,
            'totalAffiliations' => $totalAffiliations,
            'distinctSkills' => $distinctSkills,
            'totalMembershipDuration' => $totalMembershipDuration,
            'allSkills' => $allSkills,
            'totalInstructors' => $totalInstructors,
            'user' => $user,
        ]);
    }

    /**
     * Show the form for editing the current user's profile.
     *
     * @return \Illuminate\View\View
     */
    public function editProfile()
    {
        $user = Auth::user();

        return view('family.profile-edit', compact('user'));
    }

    /**
     * Upload profile picture for the current user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadProfilePicture(UploadImageRequest $request)
    {

        try {
            $user = Auth::user();

            // Validate + store the base64 image via the Storage facade with a
            // server-assigned extension (real MIME sniffed; PHP/HTML/SVG rejected).
            // Replaces the previous raw file_put_contents path, which allowed
            // client-controlled extensions and directory traversal.
            $fullPath = $this->storeBase64Image($request->image, $request->folder, $request->filename);
            if ($fullPath === null) {
                return response()->json(['success' => false, 'message' => 'Invalid or unsupported image.'], 422);
            }

            // Delete old profile picture if it still exists at a different path.
            $oldPath = $user->profile_picture;
            if ($oldPath && $oldPath !== $fullPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }

            // Update user's profile_picture field (use save() for reliable persistence)
            $user->profile_picture = $fullPath;
            $user->save();

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
     * Update the current user's profile in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateProfile(UpdateProfileRequest $request)
    {
        $validated = $request->validated();

        $user = Auth::user();

        // Handle profile picture removal
        if ($request->input('remove_profile_picture') == '1') {
            // Delete the profile picture file if it exists
            if ($user->profile_picture && Storage::disk('public')->exists($user->profile_picture)) {
                Storage::disk('public')->delete($user->profile_picture);
            }

            // Also check for old format profile pictures
            $extensions = ['png', 'jpg', 'jpeg', 'webp'];
            foreach ($extensions as $ext) {
                $path = 'images/profiles/profile_'.$user->id.'.'.$ext;
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }

            // Set profile_picture to null
            $user->profile_picture = null;
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

        $validated['social_links'] = $socialLinks;

        // Process mobile
        $validated['mobile'] = [
            'code' => $validated['mobile_code'] ?? null,
            'number' => $validated['mobile'] ?? null,
        ];
        unset($validated['mobile_code']);

        // Handle profile picture visibility
        $validated['profile_picture_is_public'] = $request->has('profile_picture_is_public') ? true : false;

        $user->update($validated);

        return redirect()->route('member.show', $user->uuid)
            ->with('success', 'Profile updated successfully.');
    }

    /**
     * Show the form for creating a new family member.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('family.create');
    }

    /**
     * Store a newly created family member in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(StoreFamilyMemberRequest $request)
    {
        $validated = $request->validated();

        $guardian = Auth::user();
        $dependent = $this->familyService->createDependent($guardian, $validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Family member added successfully!',
                'redirect' => route('members.index'),
            ]);
        }

        return redirect()->route('members.index')
            ->with('success', 'Family member added successfully.');
    }

    /**
     * Exact-match auto-suggest while filling the manual "new member" form:
     * does this phone number already belong to a real account? Phone numbers
     * aren't unique in this system (families routinely share one), so this
     * can return more than one candidate — the caller lets the user pick.
     */
    public function lookup(Request $request)
    {
        $data = $request->validate([
            'mobile_code' => ['nullable', 'string', 'max:10'],
            'mobile' => ['required', 'string', 'max:20'],
        ]);

        $me = Auth::user();
        $exclude = $this->linkedUserIds($me);
        $exclude[] = $me->id;

        $matches = User::query()
            ->where('mobile->number', $data['mobile'])
            ->when(! empty($data['mobile_code']), fn ($q) => $q->where('mobile->code', $data['mobile_code']))
            ->whereNotIn('id', $exclude)
            ->limit(5)
            ->get(['id', 'full_name', 'name', 'profile_picture', 'updated_at']);

        return response()->json([
            'success' => true,
            'matches' => $matches->map(fn ($u) => $this->candidatePayload($u, __('member.matched_via_phone')))->values(),
        ]);
    }

    /**
     * Platform-wide search for an existing member to link as family, instead
     * of registering a duplicate account. Fuzzy name/guardian matches are
     * restricted to discoverable users (mirrors the People-search rule); an
     * exact phone/email match always surfaces — you already have to know the
     * exact value to find someone that way.
     */
    public function searchExisting(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json(['success' => true, 'results' => []]);
        }

        $me = Auth::user();
        $exclude = $this->linkedUserIds($me);
        $exclude[] = $me->id;

        $digits = preg_replace('/\D+/', '', $q);
        $looksLikeEmail = str_contains($q, '@');
        $hasDigits = $digits !== '' && strlen($digits) >= 6;

        $results = collect();

        if ($looksLikeEmail || $hasDigits) {
            $exact = User::query()
                ->whereNotIn('id', $exclude)
                ->where(function ($w) use ($q, $digits, $looksLikeEmail, $hasDigits) {
                    if ($looksLikeEmail) {
                        $w->orWhere('email', $q);
                    }
                    if ($hasDigits) {
                        $w->orWhere('mobile->number', $digits);
                    }
                })
                ->limit(10)
                ->get(['id', 'full_name', 'name', 'profile_picture', 'updated_at']);

            foreach ($exact as $u) {
                $results->push($this->candidatePayload($u, __('member.matched_via_contact')));
            }
        }

        $byName = User::query()
            ->where('is_discoverable', true)
            ->whereNotIn('id', $exclude)
            ->where('full_name', 'like', "%{$q}%")
            ->limit(15)
            ->get(['id', 'full_name', 'name', 'profile_picture', 'updated_at']);

        foreach ($byName as $u) {
            $results->push($this->candidatePayload($u, __('member.matched_via_name')));
        }

        $byGuardian = UserRelationship::query()
            ->whereHas('guardian', function ($g) use ($q, $digits, $hasDigits) {
                $g->where('full_name', 'like', "%{$q}%");
                if ($hasDigits) {
                    $g->orWhere('mobile->number', 'like', "%{$digits}%");
                }
            })
            ->whereHas('dependent', fn ($d) => $d->where('is_discoverable', true)->whereNotIn('id', $exclude))
            ->with(['dependent:id,full_name,name,profile_picture,updated_at', 'guardian:id,full_name,name'])
            ->limit(15)
            ->get();

        foreach ($byGuardian as $rel) {
            $guardianName = $rel->guardian->full_name ?: $rel->guardian->name;
            $results->push($this->candidatePayload($rel->dependent, __('member.matched_via_guardian', ['name' => $guardianName])));
        }

        return response()->json([
            'success' => true,
            'results' => $results->unique('id')->take(15)->values(),
        ]);
    }

    /**
     * Link an existing platform account as family (Parent / Child / Spouse —
     * the only relationships the family-tree graph can represent as a real
     * edge). Reuses the same KinshipService edge-creation the Family Tree's
     * "add relative" already uses, just resolving the counterpart from a
     * platform-wide search instead of requiring them to already be in the
     * viewer's graph.
     */
    public function linkExisting(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'type' => ['required', 'in:parent,child,spouse'],
        ]);

        $actor = Auth::user();
        if ((int) $data['user_id'] === (int) $actor->id) {
            return response()->json(['success' => false, 'message' => __('member.cannot_link_self')], 422);
        }

        $target = User::findOrFail($data['user_id']);
        $me = $this->kin->personFor($actor);
        $counterpart = $this->kin->personFor($target);

        // Only block a DIRECT duplicate (already your recorded parent/child/
        // spouse) — being reachable via someone else (e.g. your spouse's
        // child, before you're recorded as their other parent too) must not
        // block linking, that's exactly the case this action exists for.
        if ($this->kin->areDirectlyLinked($me, $counterpart)) {
            return response()->json(['success' => false, 'message' => __('member.already_linked')], 422);
        }

        try {
            $edge = match ($data['type']) {
                'parent' => $this->kin->addParent($me, $counterpart, $actor),
                'child' => $this->kin->addChild($me, $counterpart, $actor),
                'spouse' => $this->kin->addSpouse($me, $counterpart, $actor, ['state' => 'married']),
            };
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        // A minor with no phone/smartphone of their own can never log in to
        // confirm anything, so "wait for the child to confirm" is meaningless
        // for a managed dependent. But auto-confirming for ANYONE who merely
        // finds an existing managed-dependent child would let a stranger
        // claim someone else's kid as their own with zero review — so only
        // skip confirmation when there's genuinely no existing guardian yet
        // (the actor is registering as the first one) or the actor is
        // already one of the child's recognized guardians (recording an
        // already-established relationship, not asserting a new one).
        // Otherwise, route the approval to the EXISTING guardian(s) instead
        // of the child — they're the ones who can meaningfully act on it.
        if ($data['type'] === 'child' && $edge->status === 'pending') {
            $existingGuardianIds = UserRelationship::where('dependent_user_id', $target->id)->pluck('guardian_user_id');

            if ($existingGuardianIds->isEmpty() || $existingGuardianIds->contains($actor->id)) {
                $this->kin->confirm($edge, $actor);
            } else {
                foreach ($existingGuardianIds->unique() as $guardianId) {
                    UserNotification::notifyUser(
                        $guardianId,
                        'family_request',
                        __(':name wants to be added as a parent of :child.', ['name' => $actor->full_name, 'child' => $target->full_name]),
                        ['actor_id' => $actor->id, 'icon' => 'bi-diagram-3', 'action_url' => route('me.family')],
                    );
                }
            }
        } elseif ($edge->status === 'pending') {
            $this->kin->notifyCounterpartOfRequest($edge, $actor, $data['type']);
        }

        return response()->json([
            'success' => true,
            'message' => $edge->status === 'pending'
                ? __('member.link_request_sent')
                : __('member.link_added'),
            'status' => $edge->status,
        ]);
    }

    /** User ids already reachable (confirmed) in the viewer's family graph — used to exclude already-linked people from search/lookup results. */
    /**
     * User ids DIRECTLY linked to $me — used to exclude people from search/
     * lookup results since they're already your recorded parent/child/spouse.
     * Deliberately NOT the whole connectedComponent(): someone reachable only
     * via a spouse (e.g. their child, before you're linked as the other
     * parent too) must still be searchable — that's the whole point of this
     * action.
     */
    private function linkedUserIds(User $me): array
    {
        $person = $this->kin->personFor($me);
        $personIds = $this->kin->directPersonIds($person);

        return Person::whereIn('id', $personIds)->whereNotNull('user_id')->pluck('user_id')->all();
    }

    /** Minimal, safe candidate payload for search/lookup results — name + avatar only, never raw contact info. */
    private function candidatePayload(User $u, string $matchedVia): array
    {
        return [
            'id' => $u->id,
            'name' => $u->full_name ?: $u->name,
            'avatar' => $u->profile_picture ? asset('storage/'.$u->profile_picture).'?v='.optional($u->updated_at)->timestamp : null,
            'matched_via' => $matchedVia,
        ];
    }

    /**
     * Display the specified family member.
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function show($id)
    {
        $user = Auth::user();

        // Check if user is super-admin or viewing their own profile
        $isSuperAdmin = $user->hasRole('super-admin');
        $isOwnProfile = $user->id == $id;

        // Get the member to display
        $member = User::findOrFail($id);

        // For super-admin or own profile, create a mock relationship
        // For regular users, verify family relationship exists
        if ($isSuperAdmin || $isOwnProfile) {
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

        // Fetch health data for the dependent
        $latestHealthRecord = $relationship->dependent->healthRecords()->latest('recorded_at')->first();
        $healthRecords = $relationship->dependent->healthRecords()->orderBy('recorded_at', 'desc')->paginate(10);
        $comparisonRecords = $relationship->dependent->healthRecords()->orderBy('recorded_at', 'desc')->take(2)->get();

        // Fetch invoices for the dependent
        $invoices = Invoice::where('student_user_id', $relationship->dependent->id)->orWhere('payer_user_id', $relationship->dependent->id)->with(['student', 'tenant'])->get();

        // Fetch tournament data for the dependent
        $tournamentEvents = $relationship->dependent->tournamentEvents()
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

        // Fetch goals data for the dependent
        $goals = $relationship->dependent->goals()->orderBy('created_at', 'desc')->get();
        $activeGoalsCount = $goals->where('status', 'active')->count();
        $completedGoalsCount = $goals->where('status', 'completed')->count();
        $successRate = $goals->count() > 0 ? round(($completedGoalsCount / $goals->count()) * 100) : 0;

        // Attendance log (manual entries via the "Add attendance record" feature) — still used
        // for the entry list, but the summary numbers below no longer derive from it.
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

        // Fetch affiliations data for the dependent with enhanced relationships
        $clubAffiliations = $relationship->dependent->clubAffiliations()
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

        return view('family.show', [
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
            'totalSessions' => $totalSessions,
            'attendanceRate' => $attendanceRate,
            'scheduleSessions' => $scheduleSessions,
            'clubAffiliations' => $clubAffiliations,
            'totalAffiliations' => $totalAffiliations,
            'distinctSkills' => $distinctSkills,
            'totalMembershipDuration' => $totalMembershipDuration,
            'allSkills' => $allSkills,
            'totalInstructors' => $totalInstructors,
            'user' => $relationship->dependent,
        ]);
    }

    /**
     * Show the form for editing the specified family member.
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

        return view('family.edit', compact('relationship'));
    }

    /**
     * Update the specified family member in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(UpdateFamilyMemberRequest $request, $id)
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

        $dependent = User::findOrFail($id);

        // Handle profile picture removal
        if ($request->input('remove_profile_picture') == '1') {
            // Delete the profile picture file if it exists
            if ($dependent->profile_picture && Storage::disk('public')->exists($dependent->profile_picture)) {
                Storage::disk('public')->delete($dependent->profile_picture);
            }

            // Also check for old format profile pictures
            $extensions = ['png', 'jpg', 'jpeg', 'webp'];
            foreach ($extensions as $ext) {
                $path = 'images/profiles/profile_'.$dependent->id.'.'.$ext;
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }

            // Set profile_picture to null
            $dependent->profile_picture = null;
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

        $dependent->update([
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
        ]);

        // Update relationship if it exists (not for admin or own profile)
        if (! $isSuperAdmin && ! $isOwnProfile && isset($relationship)) {
            $relationship->update([
                'relationship_type' => $validated['relationship_type'] ?? $relationship->relationship_type,
                'is_billing_contact' => $validated['is_billing_contact'] ?? false,
            ]);
        }

        // Redirect based on user type
        if ($isSuperAdmin) {
            return redirect()->route('admin.platform.members')
                ->with('success', 'Member updated successfully.');
        }

        return redirect()->route('members.index')
            ->with('success', 'Family member updated successfully.');
    }

    /**
     * Upload profile picture for a family member.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadFamilyMemberPicture(UploadImageRequest $request, $id)
    {

        try {
            $user = Auth::user();

            // Verify the family member belongs to the authenticated user
            $relationship = UserRelationship::where('guardian_user_id', $user->id)
                ->where('dependent_user_id', $id)
                ->firstOrFail();

            $familyMember = User::findOrFail($id);

            // Validate + store the base64 image with a server-assigned extension
            // (real MIME sniffed from the bytes; PHP/HTML/SVG rejected).
            $fullPath = $this->storeBase64Image($request->image, $request->folder, $request->filename);
            if ($fullPath === null) {
                return response()->json(['success' => false, 'message' => 'Invalid or unsupported image.'], 422);
            }

            // Delete old profile picture if exists
            if ($familyMember->profile_picture && $familyMember->profile_picture !== $fullPath && Storage::disk('public')->exists($familyMember->profile_picture)) {
                Storage::disk('public')->delete($familyMember->profile_picture);
            }

            // Update family member's profile_picture field
            $familyMember->update(['profile_picture' => $fullPath]);

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
     * Store a health record for the specified family member.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function storeHealth(HealthRecordRequest $request, $id)
    {
        $validated = $request->validated();

        // Check that at least one metric is provided besides the date
        $metrics = array_filter([
            $validated['weight'] ?? null,
            $validated['body_fat_percentage'] ?? null,
            $validated['bmi'] ?? null,
            $validated['body_water_percentage'] ?? null,
            $validated['muscle_mass'] ?? null,
            $validated['bone_mass'] ?? null,
            $validated['visceral_fat'] ?? null,
            $validated['bmr'] ?? null,
            $validated['protein_percentage'] ?? null,
            $validated['body_age'] ?? null,
        ]);

        if (empty($metrics)) {
            return redirect()->back()
                ->with('error', 'Please provide at least one health metric besides the date.');
        }

        $user = Auth::user();

        // For self profile, allow without relationship check
        if ($id == $user->id) {
            $dependent = $user;
        } else {
            // Verify the family member belongs to the authenticated user
            $relationship = UserRelationship::where('guardian_user_id', $user->id)
                ->where('dependent_user_id', $id)
                ->firstOrFail();

            $dependent = User::findOrFail($id);
        }

        // Check for duplicate date
        $existing = $dependent->healthRecords()->where('recorded_at', $validated['recorded_at'])->first();
        if ($existing) {
            return redirect()->back()
                ->with('error', 'A health record already exists for this date. Please choose a different date.');
        }

        $dependent->healthRecords()->create($validated);

        return redirect()->back()->withFragment('health')
            ->with('success', 'Health record added successfully.');
    }

    /**
     * Update a health record for the specified family member.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @param  int  $recordId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateHealth(HealthRecordRequest $request, $id, $recordId)
    {
        $validated = $request->validated();

        // Check that at least one metric is provided besides the date
        $metrics = array_filter([
            $validated['weight'] ?? null,
            $validated['body_fat_percentage'] ?? null,
            $validated['bmi'] ?? null,
            $validated['body_water_percentage'] ?? null,
            $validated['muscle_mass'] ?? null,
            $validated['bone_mass'] ?? null,
            $validated['visceral_fat'] ?? null,
            $validated['bmr'] ?? null,
            $validated['protein_percentage'] ?? null,
            $validated['body_age'] ?? null,
        ]);

        if (empty($metrics)) {
            return redirect()->back()
                ->with('error', 'Please provide at least one health metric besides the date.');
        }

        $user = Auth::user();

        // For self profile, allow without relationship check
        if ($id == $user->id) {
            $dependent = $user;
        } else {
            // Verify the family member belongs to the authenticated user
            $relationship = UserRelationship::where('guardian_user_id', $user->id)
                ->where('dependent_user_id', $id)
                ->firstOrFail();

            $dependent = User::findOrFail($id);
        }

        // Find the health record
        $healthRecord = $dependent->healthRecords()->findOrFail($recordId);

        // Check for duplicate date (excluding current record)
        $existing = $dependent->healthRecords()
            ->where('recorded_at', $validated['recorded_at'])
            ->where('id', '!=', $recordId)
            ->first();
        if ($existing) {
            return redirect()->back()
                ->with('error', 'A health record already exists for this date. Please choose a different date.');
        }

        $healthRecord->update($validated);

        return redirect()->back()->withFragment('health')
            ->with('success', 'Health record updated successfully.');
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

        // Check if user is authorized to update this goal
        if ($goal->user_id !== $user->id) {
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

        // Update the goal
        $goal->update($validated);

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
            ],
        ]);
    }

    /**
     * Store a new tournament participation record.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeTournament(TournamentRequest $request, $id)
    {
        $user = Auth::user();

        // Check if user is authorized to add tournament for this dependent
        if ($user->id !== (int) $id) {
            $relationship = UserRelationship::where('guardian_user_id', $user->id)
                ->where('dependent_user_id', $id)
                ->first();

            if (! $relationship) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }
        }

        // Validate the request
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
            'date' => optional($tournament->date)->format('M j, Y'),
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

    /**
     * Remove the specified family member from storage.
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
            $relationship = UserRelationship::where('guardian_user_id', $user->id)
                ->where('dependent_user_id', $id)
                ->firstOrFail();
        }

        $dependent = User::findOrFail($id);
        $memberName = $dependent->full_name;
        $dependent->delete();

        // Redirect based on user type
        if ($isSuperAdmin) {
            return redirect()->route('admin.platform.members')
                ->with('success', $memberName.' has been removed successfully.');
        }

        return redirect()->route('members.index')
            ->with('success', 'Family member removed successfully.');
    }
}
