<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserRelationship;
use App\Models\HealthRecord;
use App\Models\Invoice;
use App\Models\TournamentEvent;
use App\Models\Goal;
use App\Models\Attendance;
use App\Models\ClubAffiliation;
use App\Services\FamilyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class FamilyController extends Controller
{
    protected $familyService;

    public function __construct(FamilyService $familyService)
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
            ->sortBy(function($relationship) {
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

        // Fetch attendance data
        $attendanceRecords = $user->attendanceRecords()->orderBy('session_datetime', 'desc')->get();
        $sessionsCompleted = $attendanceRecords->where('status', 'completed')->count();
        $noShows = $attendanceRecords->where('status', 'no_show')->count();
        $totalSessions = $attendanceRecords->count();
        $attendanceRate = $totalSessions > 0 ? round(($sessionsCompleted / $totalSessions) * 100, 1) : 0;

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

        // Pass user directly and a flag to indicate it's the current user's profile
        return view('family.show', [
            'relationship' => (object)[
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
            'attendanceRate' => $attendanceRate,
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
    public function uploadProfilePicture(Request $request)
    {
        $request->validate([
            'image' => 'required',
            'folder' => 'required|string',
            'filename' => 'required|string',
        ]);

        try {
            $user = Auth::user();

            // Handle base64 image from cropper
            $imageData = $request->image;
            $imageParts = explode(";base64,", $imageData);
            $imageTypeAux = explode("image/", $imageParts[0]);
            $extension = $imageTypeAux[1];
            $imageBinary = base64_decode($imageParts[1]);

            $folder = trim($request->folder, '/');
            $fileName = $request->filename . '.' . $extension;
            $fullPath = $folder . '/' . $fileName;

            // Use public/storage directory directly (not through Storage facade)
            $publicStoragePath = public_path('storage');

            // Delete old profile picture if exists
            $oldPath = $user->profile_picture;
            if ($oldPath && file_exists($publicStoragePath . '/' . $oldPath)) {
                unlink($publicStoragePath . '/' . $oldPath);
            }

            // Also delete any existing files with different extensions
            $basePath = $folder . '/' . $request->filename;
            foreach (['png', 'jpg', 'jpeg', 'webp', 'gif'] as $ext) {
                $checkPath = $publicStoragePath . '/' . $basePath . '.' . $ext;
                if (file_exists($checkPath)) {
                    unlink($checkPath);
                }
            }

            // Ensure directory exists
            $fullDir = $publicStoragePath . '/' . $folder;
            if (!is_dir($fullDir)) {
                mkdir($fullDir, 0755, true);
            }

            // Save file directly to public/storage
            file_put_contents($publicStoragePath . '/' . $fullPath, $imageBinary);

            // Update user's profile_picture field (use save() for reliable persistence)
            $user->profile_picture = $fullPath;
            $user->save();

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
     * Update the current user's profile in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . Auth::id(),
            'mobile_code' => 'nullable|string|max:5',
            'mobile' => 'nullable|string|max:20',
            'gender' => 'required|in:m,f',
            'marital_status' => 'nullable|in:single,married,divorced,widowed',
            'birthdate' => 'required|date',
            'blood_type' => 'nullable|string|max:10',
            'nationality' => 'required|string|max:100',
            'social_links' => 'nullable|array',
            'social_links.*.platform' => 'required_with:social_links.*.url|string',
            'social_links.*.url' => 'required_with:social_links.*.platform|url',
            'motto' => 'nullable|string|max:500',
            'remove_profile_picture' => 'nullable|boolean',
            'profile_picture_is_public' => 'nullable|boolean',
        ]);

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
                $path = 'images/profiles/profile_' . $user->id . '.' . $ext;
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
                if (!empty($link['platform']) && !empty($link['url'])) {
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

        return redirect()->route('profile.show')
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
    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'gender' => 'required|in:m,f',
            'birthdate' => 'required|date',
            'blood_type' => 'nullable|string|max:10',
            'nationality' => 'required|string|max:100',
            'relationship_type' => 'required|string|max:50',
            'is_billing_contact' => 'boolean',
        ]);

        $guardian = Auth::user();
        $dependent = $this->familyService->createDependent($guardian, $validated);

        return redirect()->route('members.index')
            ->with('success', 'Family member added successfully.');
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

        // Fetch attendance data for the dependent
        $attendanceRecords = $relationship->dependent->attendanceRecords()->orderBy('session_datetime', 'desc')->get();
        $sessionsCompleted = $attendanceRecords->where('status', 'completed')->count();
        $noShows = $attendanceRecords->where('status', 'no_show')->count();
        $totalSessions = $attendanceRecords->count();
        $attendanceRate = $totalSessions > 0 ? round(($sessionsCompleted / $totalSessions) * 100, 1) : 0;

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
            'attendanceRate' => $attendanceRate,
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

        return view('family.edit', compact('relationship'));
    }

    /**
     * Update the specified family member in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:users,email,' . $id,
            'mobile_code' => 'nullable|string|max:5',
            'mobile' => 'nullable|string|max:20',
            'gender' => 'required|in:m,f',
            'marital_status' => 'nullable|in:single,married,divorced,widowed',
            'birthdate' => 'required|date',
            'blood_type' => 'nullable|string|max:10',
            'nationality' => 'required|string|max:100',
            'social_links' => 'nullable|array',
            'social_links.*.platform' => 'required_with:social_links.*.url|string',
            'social_links.*.url' => 'required_with:social_links.*.platform|url',
            'motto' => 'nullable|string|max:500',
            'relationship_type' => 'nullable|string|max:50',
            'is_billing_contact' => 'boolean',
            'remove_profile_picture' => 'nullable|boolean',
            'profile_picture_is_public' => 'nullable|boolean',
        ]);

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
                $path = 'images/profiles/profile_' . $dependent->id . '.' . $ext;
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
        if (!$isSuperAdmin && !$isOwnProfile && isset($relationship)) {
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
    public function uploadFamilyMemberPicture(Request $request, $id)
    {
        $request->validate([
            'image' => 'required',
            'folder' => 'required|string',
            'filename' => 'required|string',
        ]);

        try {
            $user = Auth::user();

            // Verify the family member belongs to the authenticated user
            $relationship = UserRelationship::where('guardian_user_id', $user->id)
                ->where('dependent_user_id', $id)
                ->firstOrFail();

            $familyMember = User::findOrFail($id);

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
            if ($familyMember->profile_picture && Storage::disk('public')->exists($familyMember->profile_picture)) {
                Storage::disk('public')->delete($familyMember->profile_picture);
            }

            // Store in the public disk (storage/app/public)
            Storage::disk('public')->put($fullPath, $imageBinary);

            // Update family member's profile_picture field
            $familyMember->update(['profile_picture' => $fullPath]);

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
     * Store a health record for the specified family member.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function storeHealth(Request $request, $id)
    {
        $validated = $request->validate([
            'recorded_at' => 'required|date',
            'height' => 'nullable|numeric|min:50|max:250',
            'weight' => 'nullable|numeric|min:0|max:999.9',
            'body_fat_percentage' => 'nullable|numeric|min:0|max:100',
            'bmi' => 'nullable|numeric|min:0|max:100',
            'body_water_percentage' => 'nullable|numeric|min:0|max:100',
            'muscle_mass' => 'nullable|numeric|min:0|max:999.9',
            'bone_mass' => 'nullable|numeric|min:0|max:999.9',
            'visceral_fat' => 'nullable|integer|min:0|max:50',
            'bmr' => 'nullable|integer|min:0|max:10000',
            'protein_percentage' => 'nullable|numeric|min:0|max:100',
            'body_age' => 'nullable|integer|min:0|max:150',
        ]);

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
    public function updateHealth(Request $request, $id, $recordId)
    {
        $validated = $request->validate([
            'recorded_at' => 'required|date',
            'height' => 'nullable|numeric|min:50|max:250',
            'weight' => 'nullable|numeric|min:0|max:999.9',
            'body_fat_percentage' => 'nullable|numeric|min:0|max:100',
            'bmi' => 'nullable|numeric|min:0|max:100',
            'body_water_percentage' => 'nullable|numeric|min:0|max:100',
            'muscle_mass' => 'nullable|numeric|min:0|max:999.9',
            'bone_mass' => 'nullable|numeric|min:0|max:999.9',
            'visceral_fat' => 'nullable|integer|min:0|max:50',
            'bmr' => 'nullable|integer|min:0|max:10000',
            'protein_percentage' => 'nullable|numeric|min:0|max:100',
            'body_age' => 'nullable|integer|min:0|max:150',
        ]);

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
    public function updateGoal(Request $request, $goalId)
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

            if (!$relationship) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }
        }

        // Validate the request
        $validated = $request->validate([
            'current_progress_value' => 'required|numeric|min:0',
            'status' => 'required|in:active,completed',
        ]);

        // Update the goal
        $goal->update($validated);

        return response()->json(['success' => true, 'message' => 'Goal updated successfully']);
    }

    /**
     * Store a new tournament participation record.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeTournament(Request $request, $id)
    {
        $user = Auth::user();

        // Check if user is authorized to add tournament for this dependent
        if ($user->id !== (int)$id) {
            $relationship = UserRelationship::where('guardian_user_id', $user->id)
                ->where('dependent_user_id', $id)
                ->first();

            if (!$relationship) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }
        }

        // Validate the request
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:championship,tournament,competition,exhibition',
            'sport' => 'required|string|max:100',
            'date' => 'required|date',
            'time' => 'nullable|date_format:H:i',
            'location' => 'nullable|string|max:255',
            'participants_count' => 'nullable|integer|min:1',
            'club_affiliation_id' => 'nullable|exists:club_affiliations,id',
            'performance_results' => 'nullable|array',
            'performance_results.*.medal_type' => 'nullable|in:special,1st,2nd,3rd',
            'performance_results.*.points' => 'nullable|numeric|min:0',
            'performance_results.*.description' => 'nullable|string|max:500',
            'notes_media' => 'nullable|array',
            'notes_media.*.note_text' => 'nullable|string|max:1000',
            'notes_media.*.media_link' => 'nullable|url',
        ]);

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
        if (!$isSuperAdmin) {
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
                ->with('success', $memberName . ' has been removed successfully.');
        }

        return redirect()->route('members.index')
            ->with('success', 'Family member removed successfully.');
    }
}
