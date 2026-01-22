<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserRelationship;
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

        // Pass user directly and a flag to indicate it's the current user's profile
        return view('family.show', [
            'relationship' => (object)[
                'dependent' => $user,
                'relationship_type' => 'self',
                'guardian_user_id' => $user->id,
                'dependent_user_id' => $user->id,
            ]
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

        return view('family.show', compact('relationship'));
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
