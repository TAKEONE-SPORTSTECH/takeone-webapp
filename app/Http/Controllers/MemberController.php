<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmDeleteRequest;
use App\Http\Requests\HealthRecordRequest;
use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\TournamentRequest;
use App\Http\Requests\UpdateGoalRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Http\Requests\UploadImageRequest;
use App\Models\User;
use App\Models\UserRelationship;
use App\Models\HealthRecord;
use App\Models\Invoice;
use App\Models\TournamentEvent;
use App\Models\Goal;
use App\Models\Attendance;
use App\Models\ClubAffiliation;
use App\Models\ClubEventRegistration;
use App\Models\Membership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class MemberController extends Controller
{
    /**
     * Display a listing of members (family dashboard).
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $user = Auth::user();
        $dependents = UserRelationship::where('guardian_user_id', $user->id)
            ->with('dependent')
            ->whereHas('dependent')
            ->get()
            ->sortBy(function($relationship) {
                return $relationship->dependent->full_name;
            });

        // Mobile and desktop have genuinely different layouts — separate files.
        $isMobile = (bool) request()->attributes->get('is_mobile', false);
        $view = $isMobile && view()->exists('family.mobile.index') ? 'family.mobile.index' : 'family.index';

        return view($view, compact('user', 'dependents'));
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
        if (!$isSuperAdmin && !$isOwnProfile && $user->isClubAdmin()) {
            $memberTenantIds = Membership::where('user_id', $member->id)->pluck('tenant_id');
            $isClubAdminOfMember = $memberTenantIds->contains(fn($tenantId) => $user->isClubAdmin($tenantId));
        }

        $canResetPassword = $isSuperAdmin || $isOwnProfile || $isClubAdminOfMember;
        // Auto-generate (regenerate) a password — super-admin only, any account.
        $canRegeneratePassword = $isSuperAdmin && !$isOwnProfile;

        // Role-based edit matrix for the profile page:
        //  • basic/personal info → self, guardian/parent, or super-admin
        //  • health / tournament / attendance / billing → club staff (admin of
        //    the member's club) or super-admin
        // A guardian is anyone who reaches this page without being self, a club
        // admin, or super-admin (the family relationship is enforced below).
        $isGuardian      = !$isSuperAdmin && !$isOwnProfile && !$isClubAdminOfMember;
        $canEditBasic    = $isOwnProfile || $isGuardian || $isSuperAdmin;
        $canManageMember = $isClubAdminOfMember || $isSuperAdmin;

        // For super-admin, own profile, or club admin of this member — create a mock relationship
        // For regular users, verify family relationship exists
        if ($isSuperAdmin || $isOwnProfile || $isClubAdminOfMember) {
            $relationship = (object)[
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

        // Fetch tournament data for the member
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
            if (str_contains($r, 'gold'))   $awardCounts['1st']++;
            if (str_contains($r, 'silver')) $awardCounts['2nd']++;
            if (str_contains($r, 'bronze')) $awardCounts['3rd']++;
        }

        // Fetch goals data for the member
        $goals = $relationship->dependent->goals()->orderBy('created_at', 'desc')->get();
        $activeGoalsCount = $goals->where('status', 'active')->count();
        $completedGoalsCount = $goals->where('status', 'completed')->count();
        $successRate = $goals->count() > 0 ? round(($completedGoalsCount / $goals->count()) * 100) : 0;

        // Fetch attendance data for the member
        $attendanceRecords = $relationship->dependent->attendanceRecords()->orderBy('session_datetime', 'desc')->get();
        $sessionsCompleted = $attendanceRecords->where('status', 'completed')->count();
        $noShows = $attendanceRecords->where('status', 'no_show')->count();
        $totalSessions = $attendanceRecords->count();
        $attendanceRate = $totalSessions > 0 ? round(($sessionsCompleted / $totalSessions) * 100, 1) : 0;

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
        $clubAffiliations->each(function($affiliation) {
            $affiliation->affiliationMedia->each(function($media) {
                $media->icon_class = $media->icon_class;
            });
        });

        // Calculate summary stats
        $totalAffiliations = $clubAffiliations->count();
        $distinctSkills = $clubAffiliations->flatMap->skillAcquisitions->pluck('skill_name')->unique()->count();
        $totalMembershipDuration = $clubAffiliations->sum('duration_in_months');

        // Get all unique skills for filter dropdown
        $allSkills = $clubAffiliations->flatMap(function($affiliation) {
            return $affiliation->skillAcquisitions->pluck('skill_name');
        })->unique()->sort()->values();

        // Count total instructors
        $totalInstructors = $clubAffiliations->flatMap(function($affiliation) {
            return $affiliation->skillAcquisitions->pluck('instructor');
        })->filter()->unique('id')->count();

        // Joined club events
        $joinedEventRegistrations = ClubEventRegistration::where('user_id', $relationship->dependent->id)
            ->with(['event.tenant'])
            ->orderBy('registered_at', 'desc')
            ->get();

        $isMobile = (bool) request()->attributes->get('is_mobile');
        $memberView = $isMobile
            ? 'components-templates.member.mobile.show'
            : 'components-templates.member.show';

        return view($memberView, [
            // Render the mobile profile inside the personal-mobile shell so it
            // keeps the persistent top bar + bottom tabs (matches the in-shell
            // member view served by PersonalMobileController@show).
            'inShell'      => $isMobile,
            'shellTitle'   => $relationship->dependent->full_name,
            'relationship' => $relationship,
            'latestHealthRecord' => $latestHealthRecord,
            'healthRecords' => $healthRecords,
            'comparisonRecords' => $comparisonRecords,
            'weightHistory' => $weightHistory,
            'invoices' => $invoices,
            'tournamentEvents' => $tournamentEvents,
            'awardCounts' => $awardCounts,
            'sports' => $sports,
            'awardedAchievements' => $awardedAchievements,
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
            'user' => $relationship->dependent,
            'joinedEventRegistrations' => $joinedEventRegistrations,
            'allClubs' => \App\Models\Tenant::orderBy('club_name')->get(['id', 'club_name', 'address', 'logo']),
            'canResetPassword' => $canResetPassword,
            'canRegeneratePassword' => $canRegeneratePassword,
            'canEditBasic' => $canEditBasic,
            'canManageMember' => $canManageMember,
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
        if (!$isSuperAdmin && !$isOwnProfile && $user->isClubAdmin()) {
            $memberTenantIds = Membership::where('user_id', $member->id)->pluck('tenant_id');
            $isClubAdminOfMember = $memberTenantIds->contains(fn($tenantId) => $user->isClubAdmin($tenantId));
        }

        if (!$isSuperAdmin && !$isOwnProfile && !$isClubAdminOfMember) {
            abort(403);
        }

        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $member->update([
            'password' => Hash::make($request->password),
        ]);

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

        // Email the new password to the member (best-effort — the admin still
        // sees it on screen, so a mail failure must not fail the request).
        $emailed = false;
        if (!empty($member->email)) {
            try {
                \Illuminate\Support\Facades\Mail::to($member->email)
                    ->send(new \App\Mail\GeneratedPasswordEmail($member, $newPassword));
                $emailed = true;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json([
            'success'  => true,
            'password' => $newPassword,
            'emailed'  => $emailed,
            'email'    => $member->email,
            'message'  => $emailed
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
            $relationship = (object)[
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
        if (!$isSuperAdmin && !$isOwnProfile) {
            $relationship = UserRelationship::where('guardian_user_id', $user->id)
                ->where('dependent_user_id', $id)
                ->firstOrFail();
        }

        // Process social links - convert from array of objects to associative array
        $socialLinks = [];
        if (isset($validated['social_links']) && is_array($validated['social_links'])) {
            foreach ($validated['social_links'] as $link) {
                if (!empty($link['platform']) && !empty($link['url'])) {
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
                $path = 'images/profiles/profile_' . $member->id . '.' . $ext;
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }

            // Set profile_picture to null
            $member->profile_picture = null;
        }

        // Process emergency contacts from JSON hidden input
        $emergencyContacts = collect(json_decode($request->input('emergency_contacts_json', '[]'), true) ?? [])
            ->filter(fn($c) => !empty($c['name']) || !empty($c['phone']))
            ->map(fn($c) => [
                'name'         => trim($c['name'] ?? ''),
                'relationship' => $c['relationship'] ?? '',
                'phone_code'   => $c['phone_code'] ?? '',
                'phone'        => trim($c['phone'] ?? ''),
            ])
            ->values()
            ->all();

        // Process health conditions from JSON hidden input
        $healthConditions = collect(json_decode($request->input('health_conditions_json', '[]'), true) ?? [])
            ->filter(fn($c) => !empty($c['condition']))
            ->map(fn($c) => [
                'condition' => trim($c['condition']),
                'noted_at'  => $c['noted_at'] ?? now()->format('Y-m-d'),
                'notes'     => trim($c['notes'] ?? ''),
            ])
            ->values()
            ->all();

        // Process documents from JSON hidden input (file_path already set by upload-document endpoint)
        $documents = collect(json_decode($request->input('documents_json', '[]'), true) ?? [])
            ->filter(fn($d) => !empty($d['type']) || !empty($d['number']))
            ->map(fn($d) => [
                'type'        => trim($d['type'] ?? ''),
                'number'      => trim($d['number'] ?? ''),
                'file_path'   => $d['file_path'] ?? null,
                'file_name'   => $d['file_name'] ?? null,
                'file_url'    => $d['file_url'] ?? null,
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
            'health_conditions'  => $healthConditions,
            'documents'          => $documents,
        ]);

        // Update relationship if it exists (not for admin or own profile)
        if (!$isSuperAdmin && !$isOwnProfile && isset($relationship)) {
            $relationship->update([
                'relationship_type' => $validated['relationship_type'] ?? $relationship->relationship_type,
                'is_billing_contact' => $validated['is_billing_contact'] ?? false,
            ]);
        }

        // Return JSON for AJAX requests
        if ($request->wantsJson() || $request->ajax()) {
            // Get the updated profile picture URL
            $profilePictureUrl = null;
            if ($member->profile_picture && file_exists(public_path('storage/' . $member->profile_picture))) {
                $profilePictureUrl = asset('storage/' . $member->profile_picture);
            } else {
                $extensions = ['png', 'jpg', 'jpeg', 'webp'];
                foreach ($extensions as $ext) {
                    $path = 'storage/images/profiles/profile_' . $member->id . '.' . $ext;
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
                    'full_name'          => $member->full_name,
                    'motto'              => $member->motto,
                    'nationality'        => $member->nationality,
                    'gender'             => $member->gender,
                    'marital_status'     => $member->marital_status,
                    'blood_type'         => $member->blood_type,
                    'age'                => $member->age,
                    'social_links'       => $member->social_links ?? [],
                    'emergency_contacts' => $member->emergency_contacts ?? [],
                    'health_conditions'  => $member->health_conditions ?? [],
                    'documents'          => $member->documents ?? [],
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
            if (!$isSuperAdmin && !$isOwnProfile) {
                UserRelationship::where('guardian_user_id', $user->id)
                    ->where('dependent_user_id', $id)
                    ->firstOrFail();
            }

            $member = User::findOrFail($id);

            // Handle base64 image from cropper
            $imageData = $request->image;
            $imageParts = explode(";base64,", $imageData);
            $imageTypeAux = explode("image/", $imageParts[0]);
            $extension = $imageTypeAux[1];
            $imageBinary = base64_decode($imageParts[1]);

            $folder = trim($request->folder, '/');
            $fileName = $request->filename . '.' . $extension;
            $fullPath = $folder . '/' . $fileName;

            // Delete old profile picture if exists
            if ($member->profile_picture && Storage::disk('public')->exists($member->profile_picture)) {
                Storage::disk('public')->delete($member->profile_picture);
            }

            // Store in the public disk
            Storage::disk('public')->put($fullPath, $imageBinary);

            // Update member's profile_picture field
            $member->update(['profile_picture' => $fullPath]);

            return response()->json([
                'success' => true,
                'path' => $fullPath,
                'url' => asset('storage/' . $fullPath)
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
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

            if (!$isSuperAdmin && !$isOwnProfile) {
                UserRelationship::where('guardian_user_id', $user->id)
                    ->where('dependent_user_id', $id)
                    ->firstOrFail();
            }

            $path = $request->file('file')->store('documents/' . $id, 'public');

            return response()->json([
                'success'   => true,
                'path'      => $path,
                'url'       => asset('storage/' . $path),
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

            if (!$isSuperAdmin && !$isOwnProfile) {
                UserRelationship::where('guardian_user_id', $user->id)
                    ->where('dependent_user_id', $id)
                    ->firstOrFail();
            }

            $filePath = $request->input('file_path');

            // Restrict deletion to files within that member's documents directory
            if (!str_starts_with($filePath, 'documents/' . $id . '/')) {
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

        $user = Auth::user();

        // Check if user is super-admin or adding health for themselves
        $isSuperAdmin = $user->hasRole('super-admin');
        $isOwnProfile = $user->id == $id;

        // For regular users, verify family relationship exists
        if (!$isSuperAdmin && !$isOwnProfile) {
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
                'record'  => [
                    'id'             => $record->id,
                    'weight'         => (float) $record->weight,
                    'recorded_at'    => optional($record->recorded_at)->format('Y-m-d'),
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
        if (!$isSuperAdmin && !$isOwnProfile) {
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
        if (!$isSuperAdmin && !$isOwnProfile) {
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
            'time' => $validated['time'],
            'location' => $validated['location'],
            'participants_count' => $validated['participants_count'],
        ]);

        // Create performance results
        if (isset($validated['performance_results'])) {
            foreach ($validated['performance_results'] as $resultData) {
                if (!empty($resultData['medal_type'])) {
                    $tournament->performanceResults()->create($resultData);
                }
            }
        }

        // Create notes and media
        if (isset($validated['notes_media'])) {
            foreach ($validated['notes_media'] as $noteData) {
                if (!empty($noteData['note_text']) || !empty($noteData['media_link'])) {
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
     *
     * @param  \App\Models\TournamentEvent  $tournament
     * @return array
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
        if (!$isSuperAdmin && $goal->user_id !== $user->id) {
            // Check if user is guardian of the goal owner
            $relationship = UserRelationship::where('guardian_user_id', $user->id)
                ->where('dependent_user_id', $goal->user_id)
                ->first();

            if (!$relationship) {
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
        if (!$isSuperAdmin) {
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
                ->with('success', $memberName . ' has been removed successfully.');
        }

        return redirect()->route('members.index')
            ->with('success', 'Member removed successfully.');
    }

    // ─── Affiliation CRUD ────────────────────────────────────────────────────────

    private function authorizeForMember(int $id): void
    {
        $user = Auth::user();
        if ($user->hasRole('super-admin') || $user->id == $id) return;
        UserRelationship::where('guardian_user_id', $user->id)
            ->where('dependent_user_id', $id)
            ->firstOrFail();
    }

    public function storeAffiliation(\Illuminate\Http\Request $request, $id)
    {
        $this->authorizeForMember($id);

        $validated = $request->validate([
            'tenant_id'   => 'nullable|exists:tenants,id',
            'club_name'   => 'required_without:tenant_id|nullable|string|max:255',
            'start_date'  => 'required|date',
            'end_date'    => 'nullable|date|after_or_equal:start_date',
            'location'    => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'coaches'     => 'nullable|string|max:1000',
        ]);

        // If a platform club is selected, pull its data
        $tenant = null;
        if (!empty($validated['tenant_id'])) {
            $tenant = \App\Models\Tenant::findOrFail($validated['tenant_id']);
        }

        $coaches = null;
        if (!empty($validated['coaches'])) {
            $coaches = array_values(array_filter(array_map('trim', explode(',', $validated['coaches']))));
        }

        $member = User::findOrFail($id);
        $affiliation = $member->clubAffiliations()->create([
            'tenant_id'   => $tenant?->id,
            'club_name'   => $tenant ? $tenant->club_name : $validated['club_name'],
            'logo'        => $tenant?->logo ?? null,
            'start_date'  => $validated['start_date'],
            'end_date'    => $validated['end_date'] ?? null,
            'location'    => $tenant ? ($tenant->address ?? $validated['location']) : ($validated['location'] ?? null),
            'description' => $validated['description'] ?? null,
            'coaches'     => $coaches,
        ]);

        $logoUrl = null;
        if ($tenant?->logo) {
            $logoUrl = asset('storage/' . $tenant->logo);
        } elseif ($affiliation->logo) {
            $logoUrl = filter_var($affiliation->logo, FILTER_VALIDATE_URL)
                ? $affiliation->logo
                : asset('storage/' . $affiliation->logo);
        }

        return response()->json([
            'success' => true,
            'message' => 'Affiliation added successfully.',
            'id' => $affiliation->id,
            'affiliation' => [
                'id'          => $affiliation->id,
                'club_name'   => $affiliation->club_name,
                'logo_url'    => $logoUrl,
                'location'    => $affiliation->location,
                'description' => $affiliation->description,
                'coaches'     => is_array($affiliation->coaches) ? implode(', ', $affiliation->coaches) : '',
                'start_date'  => $affiliation->start_date?->format('Y-m-d'),
                'end_date'    => $affiliation->end_date?->format('Y-m-d'),
                'start_label' => $affiliation->start_date?->format('M Y'),
                'end_label'   => $affiliation->end_date ? $affiliation->end_date->format('M Y') : 'Present',
                'is_ongoing'  => !$affiliation->end_date,
                'formatted_duration' => $affiliation->formatted_duration,
            ],
        ]);
    }

    public function updateAffiliation(\Illuminate\Http\Request $request, $id, $affiliationId)
    {
        $this->authorizeForMember($id);

        $validated = $request->validate([
            'club_name'   => 'required|string|max:255',
            'start_date'  => 'required|date',
            'end_date'    => 'nullable|date|after_or_equal:start_date',
            'location'    => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'coaches'     => 'nullable|string|max:1000',
        ]);

        $coaches = null;
        if (!empty($validated['coaches'])) {
            $coaches = array_values(array_filter(array_map('trim', explode(',', $validated['coaches']))));
        }

        $member = User::findOrFail($id);
        $affiliation = $member->clubAffiliations()->findOrFail($affiliationId);
        $affiliation->update([
            'club_name'   => $validated['club_name'],
            'start_date'  => $validated['start_date'],
            'end_date'    => $validated['end_date'] ?? null,
            'location'    => $validated['location'] ?? null,
            'description' => $validated['description'] ?? null,
            'coaches'     => $coaches,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Affiliation updated successfully.',
            'affiliation' => [
                'id'          => $affiliation->id,
                'club_name'   => $affiliation->club_name,
                'location'    => $affiliation->location,
                'description' => $affiliation->description,
                'coaches'     => is_array($affiliation->coaches) ? implode(', ', $affiliation->coaches) : '',
                'start_date'  => $affiliation->start_date?->format('Y-m-d'),
                'end_date'    => $affiliation->end_date?->format('Y-m-d'),
                'start_label' => $affiliation->start_date?->format('M Y'),
                'end_label'   => $affiliation->end_date ? $affiliation->end_date->format('M Y') : 'Present',
                'is_ongoing'  => !$affiliation->end_date,
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

    public function storeAffiliationSkill(\Illuminate\Http\Request $request, $id, $affiliationId)
    {
        $this->authorizeForMember($id);

        $validated = $request->validate([
            'skill_name'        => 'required|string|max:255',
            'proficiency_level' => 'required|in:beginner,intermediate,advanced,expert',
            'start_date'        => 'nullable|date',
            'duration_months'   => 'nullable|integer|min:1|max:600',
            'notes'             => 'nullable|string|max:500',
        ]);

        $member = User::findOrFail($id);
        $affiliation = $member->clubAffiliations()->findOrFail($affiliationId);
        $skill = $affiliation->skillAcquisitions()->create([
            'skill_name'        => $validated['skill_name'],
            'proficiency_level' => $validated['proficiency_level'],
            'start_date'        => $validated['start_date'] ?? null,
            'duration_months'   => $validated['duration_months'] ?? 1,
            'notes'             => $validated['notes'] ?? null,
            'icon'              => 'bi-star',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Skill added successfully.',
            'id' => $skill->id,
            'skill' => [
                'id'                => $skill->id,
                'skill_name'        => $skill->skill_name,
                'proficiency_level' => $skill->proficiency_level,
                'formatted_duration'=> $skill->formatted_duration,
                'start_label'       => $skill->start_date ? $skill->start_date->format('M Y') : null,
                'badge_color'       => $skill->proficiency_level == 'expert' ? 'danger' : ($skill->proficiency_level == 'advanced' ? 'warning' : ($skill->proficiency_level == 'intermediate' ? 'info' : 'secondary')),
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
            'media_type'  => 'required|in:certificate,photo,video,document',
            'title'       => 'required|string|max:255',
            'media_url'   => 'required|string|max:500',
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
                'id'         => $media->id,
                'title'      => $media->title,
                'full_url'   => $media->full_url,
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

        if (!filter_var($media->media_url, FILTER_VALIDATE_URL)) {
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
        if (!$isSuperAdmin) {
            UserRelationship::where('guardian_user_id', $user->id)
                ->where('dependent_user_id', $id)
                ->firstOrFail();
        }

        $member = User::findOrFail($id);

        $ownedClubs = \App\Models\Tenant::where('owner_user_id', $member->id)->pluck('club_name');
        if ($ownedClubs->isNotEmpty()) {
            return redirect()->back()
                ->with('error', 'Cannot delete this account. They are the owner of the following club(s): ' . $ownedClubs->join(', ') . '. Transfer ownership first.');
        }

        $memberName = $member->full_name;

        // Redirect based on user type
        if ($isSuperAdmin) {
            return redirect()->route('admin.platform.members')
                ->with('success', $memberName . ' has been removed successfully.');
        }

        return redirect()->route('members.index')
            ->with('success', 'Member removed successfully.');
    }
}
