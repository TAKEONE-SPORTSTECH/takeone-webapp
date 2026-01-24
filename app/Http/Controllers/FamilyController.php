<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserRelationship;
use App\Models\HealthRecord;
use App\Models\Invoice;
use App\Models\TournamentEvent;
use App\Models\Goal;
use App\Services\FamilyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        return view('family.dashboard', compact('user', 'dependents'));
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
            ->with(['performanceResults', 'notesMedia'])
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
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
        ]);

        $user = Auth::user();

        if ($request->hasFile('image')) {
            $image = $request->file('image');

            // Generate unique filename
            $filename = 'profile_' . $user->id . '_' . time() . '.' . $image->getClientOriginalExtension();

            // Store in public/images/profiles
            $path = $image->storeAs('images/profiles', $filename, 'public');

            // Delete old profile picture if exists
            if ($user->profile_picture && \Storage::disk('public')->exists($user->profile_picture)) {
                \Storage::disk('public')->delete($user->profile_picture);
            }

            // Update user
            $user->update(['profile_picture' => $path]);

            return response()->json([
                'success' => true,
                'message' => 'Profile picture uploaded successfully.',
                'path' => $path,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No image file provided.',
        ], 400);
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
            'birthdate' => 'required|date',
            'blood_type' => 'nullable|string|max:10',
            'nationality' => 'required|string|max:100',
            'social_links' => 'nullable|array',
            'social_links.*.platform' => 'required_with:social_links.*.url|string',
            'social_links.*.url' => 'required_with:social_links.*.platform|url',
            'motto' => 'nullable|string|max:500',
        ]);

        $user = Auth::user();

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

        return redirect()->route('family.dashboard')
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
        $relationship = UserRelationship::where('guardian_user_id', $user->id)
            ->where('dependent_user_id', $id)
            ->with('dependent')
            ->firstOrFail();

        // Fetch health data for the dependent
        $latestHealthRecord = $relationship->dependent->healthRecords()->latest('recorded_at')->first();
        $healthRecords = $relationship->dependent->healthRecords()->orderBy('recorded_at', 'desc')->paginate(10);
        $comparisonRecords = $relationship->dependent->healthRecords()->orderBy('recorded_at', 'desc')->take(2)->get();

        // Fetch invoices for the dependent
        $invoices = Invoice::where('student_user_id', $relationship->dependent->id)->orWhere('payer_user_id', $relationship->dependent->id)->with(['student', 'tenant'])->get();

        // Fetch tournament data for the dependent
        $tournamentEvents = $relationship->dependent->tournamentEvents()
            ->with(['performanceResults', 'notesMedia'])
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

        return view('family.show', compact('relationship', 'latestHealthRecord', 'healthRecords', 'comparisonRecords', 'invoices', 'tournamentEvents', 'awardCounts', 'sports', 'goals', 'activeGoalsCount', 'completedGoalsCount', 'successRate'));
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
        $relationship = UserRelationship::where('guardian_user_id', $user->id)
            ->where('dependent_user_id', $id)
            ->with('dependent')
            ->firstOrFail();

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
            'birthdate' => 'required|date',
            'blood_type' => 'nullable|string|max:10',
            'nationality' => 'required|string|max:100',
            'social_links' => 'nullable|array',
            'social_links.*.platform' => 'required_with:social_links.*.url|string',
            'social_links.*.url' => 'required_with:social_links.*.platform|url',
            'motto' => 'nullable|string|max:500',
            'relationship_type' => 'required|string|max:50',
            'is_billing_contact' => 'boolean',
        ]);

        $user = Auth::user();
        $relationship = UserRelationship::where('guardian_user_id', $user->id)
            ->where('dependent_user_id', $id)
            ->firstOrFail();

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

        $dependent = User::findOrFail($id);
        $dependent->update([
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'mobile' => $mobile,
            'gender' => $validated['gender'],
            'birthdate' => $validated['birthdate'],
            'blood_type' => $validated['blood_type'],
            'nationality' => $validated['nationality'],
            'social_links' => $socialLinks,
            'motto' => $validated['motto'],
        ]);

        $relationship->update([
            'relationship_type' => $validated['relationship_type'],
            'is_billing_contact' => $validated['is_billing_contact'] ?? false,
        ]);

        return redirect()->route('family.dashboard')
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
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
        ]);

        $user = Auth::user();

        // Verify the family member belongs to the authenticated user
        $relationship = UserRelationship::where('guardian_user_id', $user->id)
            ->where('dependent_user_id', $id)
            ->firstOrFail();

        $familyMember = User::findOrFail($id);

        if ($request->hasFile('image')) {
            $image = $request->file('image');

            // Generate unique filename
            $filename = 'profile_' . $familyMember->id . '_' . time() . '.' . $image->getClientOriginalExtension();

            // Store in public/images/profiles
            $path = $image->storeAs('images/profiles', $filename, 'public');

            // Delete old profile picture if exists
            if ($familyMember->profile_picture && \Storage::disk('public')->exists($familyMember->profile_picture)) {
                \Storage::disk('public')->delete($familyMember->profile_picture);
            }

            // Update family member
            $familyMember->update(['profile_picture' => $path]);

            return response()->json([
                'success' => true,
                'message' => 'Profile picture uploaded successfully.',
                'path' => $path,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No image file provided.',
        ], 400);
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
     * Remove the specified family member from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $relationship = UserRelationship::where('guardian_user_id', $user->id)
            ->where('dependent_user_id', $id)
            ->firstOrFail();

        $dependent = User::findOrFail($id);
        $dependent->delete();

        return redirect()->route('family.dashboard')
            ->with('success', 'Family member removed successfully.');
    }
}
