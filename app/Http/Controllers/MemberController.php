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
use Illuminate\Support\Facades\Auth;
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

        return view('family.index', compact('user', 'dependents'));
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

        return view('components-templates.member.show', [
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
            'user' => $relationship->dependent,
            'joinedEventRegistrations' => $joinedEventRegistrations,
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
                'profile_picture_url' => $profilePictureUrl
            ]);
        }

        return redirect()->route('member.show', $id)
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
            return redirect()->back()
                ->with('error', 'A health record already exists for this date.');
        }

        $member->healthRecords()->create($validated);

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

        return response()->json(['success' => true, 'message' => 'Tournament record added successfully']);
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

        return response()->json(['success' => true, 'message' => 'Goal updated successfully']);
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
