<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\ClubFacility;
use App\Models\ClubInstructor;
use App\Models\ClubActivity;
use App\Models\ClubPackage;
use App\Models\Membership;
use App\Models\ClubGalleryImage;
use App\Models\ClubTransaction;
use App\Models\ClubMemberSubscription;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ClubAdminController extends Controller
{
    /**
     * Get club and verify access
     */
    private function getClub($clubId)
    {
        $club = Tenant::findOrFail($clubId);

        // TODO: Add proper authorization check
        // For now, allow super-admin or club owner
        $user = Auth::user();
        if (!$user->isSuperAdmin() && $club->owner_user_id !== $user->id) {
            abort(403, 'Unauthorized access to this club.');
        }

        return $club;
    }

    /**
     * Dashboard overview
     */
    public function dashboard($clubId)
    {
        $club = $this->getClub($clubId);

        $stats = [
            'members' => Membership::where('tenant_id', $clubId)->where('status', 'active')->count(),
            'activities' => ClubActivity::where('tenant_id', $clubId)->count(),
            'packages' => ClubPackage::where('tenant_id', $clubId)->count(),
            'instructors' => ClubInstructor::where('tenant_id', $clubId)->count(),
            'rating' => $club->reviews()->avg('rating') ?? 0,
        ];

        // Monthly financial data for chart
        $monthlyFinancials = $this->getMonthlyFinancials($clubId);

        // Expiring subscriptions (next 30 days)
        $expiringSubscriptions = collect(); // TODO: Implement when subscription model is ready

        return view('admin.club.dashboard.index', compact('club', 'stats', 'monthlyFinancials', 'expiringSubscriptions'));
    }

    /**
     * Club details
     */
    public function details($clubId)
    {
        $club = $this->getClub($clubId);

        // Load relationships
        $club->load([
            'owner',
            'facilities',
            'instructors',
            'activities',
            'packages.activities',
            'galleryImages',
            'reviews.user',
            'socialLinks',
            'subscriptions'
        ]);

        // Calculate stats
        $activeMembersCount = $club->subscriptions()->where('status', 'active')->count();
        $reviews = $club->reviews()->with('user')->latest()->get();
        $averageRating = $reviews->avg('rating') ?? 0;

        return view('admin.club.details.index', compact('club', 'activeMembersCount', 'reviews', 'averageRating'));
    }

    /**
     * Gallery management
     */
    public function gallery($clubId)
    {
        $club = $this->getClub($clubId);
        $images = ClubGalleryImage::where('tenant_id', $clubId)->latest()->get();
        return view('admin.club.gallery.index', compact('club', 'images'));
    }

    public function uploadGallery(Request $request, $clubId)
    {
        $club = $this->getClub($clubId);

        $request->validate([
            'images.*' => 'required|image|max:5120',
            'caption' => 'nullable|string|max:255',
        ]);

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('clubs/' . $clubId . '/gallery', 'public');
                ClubGalleryImage::create([
                    'tenant_id' => $clubId,
                    'image_path' => $path,
                    'caption' => $request->caption,
                    'uploaded_by' => auth()->id(),
                ]);
            }
        }

        return back()->with('success', 'Images uploaded successfully.');
    }

    /**
     * Facilities management
     */
    public function facilities($clubId)
    {
        $club = $this->getClub($clubId);
        $facilities = ClubFacility::where('tenant_id', $clubId)->get();
        return view('admin.club.facilities.index', compact('club', 'facilities'));
    }

    public function storeFacility(Request $request, $clubId)
    {
        $club = $this->getClub($clubId);

        $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'is_available' => 'nullable|boolean',
            'image' => 'nullable',
        ]);

        $data = [
            'tenant_id' => $clubId,
            'name' => $request->name,
            'address' => $request->address,
            'gps_lat' => $request->latitude,
            'gps_long' => $request->longitude,
            'is_available' => $request->has('is_available'),
        ];

        // Handle base64 image from cropper (form mode)
        if ($request->filled('image') && str_starts_with($request->image, 'data:image')) {
            $imageData = $request->image;
            $imageParts = explode(";base64,", $imageData);
            $imageTypeAux = explode("image/", $imageParts[0]);
            $extension = $imageTypeAux[1];
            $imageBinary = base64_decode($imageParts[1]);

            $folder = $request->input('image_folder', 'clubs/' . $clubId . '/facilities');
            $filename = $request->input('image_filename', 'facility_' . time());
            $fullPath = $folder . '/' . $filename . '.' . $extension;

            Storage::disk('public')->put($fullPath, $imageBinary);
            $data['photo'] = $fullPath;
        }
        // Handle traditional file upload
        elseif ($request->hasFile('image')) {
            $data['photo'] = $request->file('image')->store('clubs/' . $clubId . '/facilities', 'public');
        }

        ClubFacility::create($data);

        return back()->with('success', 'Facility added successfully.');
    }

    /**
     * Get a single facility for editing (JSON response)
     */
    public function getFacility($clubId, $facilityId)
    {
        $club = $this->getClub($clubId);
        $facility = ClubFacility::where('tenant_id', $clubId)->findOrFail($facilityId);

        return response()->json([
            'success' => true,
            'data' => $facility
        ]);
    }

    /**
     * Update a facility
     */
    public function updateFacility(Request $request, $clubId, $facilityId)
    {
        $club = $this->getClub($clubId);

        $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'gps_lat' => 'nullable|numeric',
            'gps_long' => 'nullable|numeric',
            'is_available' => 'nullable|boolean',
        ]);

        $facility = ClubFacility::where('tenant_id', $clubId)->findOrFail($facilityId);

        $data = $request->only(['name', 'address', 'gps_lat', 'gps_long']);
        $data['is_available'] = $request->has('is_available');

        // Handle base64 image from cropper
        if ($request->filled('image') && str_starts_with($request->image, 'data:image')) {
            $imageData = $request->image;
            $imageParts = explode(";base64,", $imageData);
            $imageTypeAux = explode("image/", $imageParts[0]);
            $extension = $imageTypeAux[1];
            $imageBinary = base64_decode($imageParts[1]);

            $folder = 'clubs/' . $clubId . '/facilities';
            $filename = 'facility_' . $facilityId . '_' . time() . '.' . $extension;
            $fullPath = $folder . '/' . $filename;

            // Delete old image if exists
            if ($facility->photo && Storage::disk('public')->exists($facility->photo)) {
                Storage::disk('public')->delete($facility->photo);
            }

            Storage::disk('public')->put($fullPath, $imageBinary);
            $data['photo'] = $fullPath;
        }
        // Handle traditional file upload
        elseif ($request->hasFile('image')) {
            // Delete old image if exists
            if ($facility->photo && Storage::disk('public')->exists($facility->photo)) {
                Storage::disk('public')->delete($facility->photo);
            }
            $data['photo'] = $request->file('image')->store('clubs/' . $clubId . '/facilities', 'public');
        }

        $facility->update($data);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Facility updated successfully.',
                'data' => $facility
            ]);
        }

        return back()->with('success', 'Facility updated successfully.');
    }

    /**
     * Delete a facility
     */
    public function destroyFacility($clubId, $facilityId)
    {
        $club = $this->getClub($clubId);
        $facility = ClubFacility::where('tenant_id', $clubId)->findOrFail($facilityId);

        // Delete image if exists
        if ($facility->photo && Storage::disk('public')->exists($facility->photo)) {
            Storage::disk('public')->delete($facility->photo);
        }

        $facility->delete();

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Facility deleted successfully.'
            ]);
        }

        return back()->with('success', 'Facility deleted successfully.');
    }

    /**
     * Instructors management
     */
    public function instructors($clubId)
    {
        $club = $this->getClub($clubId);
        $instructors = ClubInstructor::where('tenant_id', $clubId)->with('user')->get();
        return view('admin.club.instructors.index', compact('club', 'instructors'));
    }

    public function storeInstructor(Request $request, $clubId)
    {
        $club = $this->getClub($clubId);
        $creationType = $request->input('creation_type', 'new');

        if ($creationType === 'new') {
            // Validate for new user creation
            $request->validate([
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:6',
                'name' => 'required|string|max:255',
                'phone' => 'required|string',
                'gender' => 'required|in:male,female',
                'birthdate' => 'required|date',
                'nationality' => 'required|string',
                'specialty' => 'nullable|string|max:255',
                'experience' => 'nullable|integer|min:0',
                'skills' => 'nullable|string',
                'bio' => 'nullable|string',
            ]);

            // Create the user first
            $user = User::create([
                'name' => $request->name,
                'full_name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt($request->password),
                'mobile' => ($request->country_code ?? '+973') . $request->phone,
                'gender' => $request->gender,
                'birthdate' => $request->birthdate,
                'nationality' => $request->nationality,
            ]);

            // Handle photo upload for user
            if ($request->hasFile('photo')) {
                $photoPath = $request->file('photo')->store('users/' . $user->id, 'public');
                $user->update(['profile_picture' => $photoPath]);
            }

            $userId = $user->id;
            $role = $request->specialty;
            $experienceYears = $request->experience;
            $skills = $request->skills ? json_decode($request->skills, true) : [];
            $bio = $request->bio;

        } else {
            // Validate for existing user
            $request->validate([
                'selected_member_id' => 'required|exists:users,id',
                'specialty_existing' => 'nullable|string|max:255',
                'experience_existing' => 'nullable|integer|min:0',
                'skills_existing' => 'nullable|string',
                'bio_existing' => 'nullable|string',
            ]);

            $userId = $request->selected_member_id;
            $role = $request->specialty_existing;
            $experienceYears = $request->experience_existing;
            $skills = $request->skills_existing ? json_decode($request->skills_existing, true) : [];
            $bio = $request->bio_existing;
        }

        // Create the instructor record
        ClubInstructor::create([
            'tenant_id' => $clubId,
            'user_id' => $userId,
            'role' => $role,
            'experience_years' => $experienceYears,
            'skills' => $skills,
            'bio' => $bio,
        ]);

        return back()->with('success', 'Instructor added successfully.');
    }

    /**
     * Activities management
     */
    public function activities($clubId)
    {
        $club = $this->getClub($clubId);
        $activities = ClubActivity::where('tenant_id', $clubId)->with('facility')->get();
        $facilities = ClubFacility::where('tenant_id', $clubId)->get();
        return view('admin.club.activities.index', compact('club', 'activities', 'facilities'));
    }

    public function storeActivity(Request $request, $clubId)
    {
        $club = $this->getClub($clubId);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
            'picture' => 'nullable|image|max:2048',
            'existing_picture_url' => 'nullable|string',
        ]);

        $data = $request->only(['name', 'description', 'notes']);
        $data['tenant_id'] = $clubId;

        // Handle picture upload
        if ($request->hasFile('picture')) {
            $data['picture_url'] = $request->file('picture')->store('clubs/' . $clubId . '/activities', 'public');
        }
        // Handle existing picture URL (when duplicating)
        elseif ($request->filled('existing_picture_url')) {
            // Extract the relative path from the full URL
            $existingUrl = $request->existing_picture_url;
            $storagePath = str_replace(asset('storage') . '/', '', $existingUrl);

            // Copy the existing file to a new location
            if (Storage::disk('public')->exists($storagePath)) {
                $extension = pathinfo($storagePath, PATHINFO_EXTENSION);
                $newPath = 'clubs/' . $clubId . '/activities/activity_' . time() . '.' . $extension;
                Storage::disk('public')->copy($storagePath, $newPath);
                $data['picture_url'] = $newPath;
            }
        }

        ClubActivity::create($data);

        return back()->with('success', 'Activity added successfully.');
    }

    public function updateActivity(Request $request, $clubId, $activityId)
    {
        $activity = ClubActivity::where('tenant_id', $clubId)->findOrFail($activityId);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
            'picture' => 'nullable|image|max:2048',
        ]);

        $data = $request->only(['name', 'description', 'notes']);

        // Handle picture upload
        if ($request->hasFile('picture')) {
            // Delete old picture if exists
            if ($activity->picture_url && Storage::disk('public')->exists($activity->picture_url)) {
                Storage::disk('public')->delete($activity->picture_url);
            }
            $data['picture_url'] = $request->file('picture')->store('clubs/' . $clubId . '/activities', 'public');
        }

        $activity->update($data);

        return back()->with('success', 'Activity updated successfully.');
    }

    public function destroyActivity($clubId, $activityId)
    {
        $activity = ClubActivity::where('tenant_id', $clubId)->findOrFail($activityId);

        // Delete picture if exists
        if ($activity->picture_url && Storage::disk('public')->exists($activity->picture_url)) {
            Storage::disk('public')->delete($activity->picture_url);
        }

        $activity->delete();

        return back()->with('success', 'Activity deleted successfully.');
    }

    /**
     * Packages management
     */
    public function packages($clubId)
    {
        $club = $this->getClub($clubId);
        $packages = ClubPackage::where('tenant_id', $clubId)->get();
        $facilities = ClubFacility::where('tenant_id', $clubId)->get();
        $activities = ClubActivity::where('tenant_id', $clubId)->get();
        $instructors = ClubInstructor::where('tenant_id', $clubId)->with('user')->get()->map(function ($instructor) {
            return [
                'id' => $instructor->id,
                'name' => $instructor->user?->full_name ?? $instructor->user?->name ?? 'Unknown',
            ];
        });
        return view('admin.club.packages.index', compact('club', 'packages', 'facilities', 'activities', 'instructors'));
    }

    public function storePackage(Request $request, $clubId)
    {
        $club = $this->getClub($clubId);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration_days' => 'required|integer|min:1',
            'is_popular' => 'boolean',
        ]);

        $data = $request->only(['name', 'description', 'price', 'duration_days']);
        $data['tenant_id'] = $clubId;
        $data['is_popular'] = $request->boolean('is_popular');

        ClubPackage::create($data);

        return back()->with('success', 'Package added successfully.');
    }

    /**
     * Members management
     */
    public function members($clubId)
    {
        $club = $this->getClub($clubId);
        $members = Membership::where('tenant_id', $clubId)
            ->with(['user', 'user.guardians.guardian'])
            ->paginate(20);
        $packages = ClubPackage::where('tenant_id', $clubId)->get();
        return view('admin.club.members.index', compact('club', 'members', 'packages'));
    }

    public function storeMember(Request $request, $clubId)
    {
        $this->getClub($clubId);

        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $addedCount = 0;
        foreach ($request->user_ids as $userId) {
            // Check if already a member
            $existingMembership = Membership::where('tenant_id', $clubId)
                ->where('user_id', $userId)
                ->first();

            if (!$existingMembership) {
                Membership::create([
                    'tenant_id' => $clubId,
                    'user_id' => $userId,
                    'status' => 'active',
                ]);
                $addedCount++;
            }
        }

        // Return JSON for AJAX requests
        if ($request->expectsJson() || $request->ajax()) {
            if ($addedCount > 0) {
                return response()->json(['success' => true, 'message' => "{$addedCount} member(s) added successfully.", 'count' => $addedCount]);
            }
            return response()->json(['success' => true, 'message' => 'Selected users are already members of this club.', 'count' => 0]);
        }

        if ($addedCount > 0) {
            return back()->with('success', "{$addedCount} member(s) added successfully.");
        }

        return back()->with('info', 'Selected users are already members of this club.');
    }

    public function searchUsers(Request $request, $clubId)
    {
        $club = $this->getClub($clubId);
        $query = $request->input('query');

        if (empty($query) || strlen($query) < 2) {
            return response()->json(['users' => []]);
        }

        // Search users by email, name, or phone
        $users = User::where(function ($q) use ($query) {
            $q->where('email', 'like', "%{$query}%")
              ->orWhere('name', 'like', "%{$query}%")
              ->orWhere('full_name', 'like', "%{$query}%")
              ->orWhere('mobile', 'like', "%{$query}%");
        })
        ->limit(20)
        ->get()
        ->map(function ($user) use ($clubId) {
            // Check if already a member
            $isMember = Membership::where('tenant_id', $clubId)
                ->where('user_id', $user->id)
                ->exists();

            // Get dependents (family members) - dependents() returns UserRelationship, need to get the dependent user
            $dependents = $user->dependents()->with('dependent')->get()->map(function ($relationship) use ($clubId, $user) {
                $dep = $relationship->dependent;
                if (!$dep) return null;

                $isDepMember = Membership::where('tenant_id', $clubId)
                    ->where('user_id', $dep->id)
                    ->exists();

                $relationshipType = $relationship->relationship_type;
                $isChild = in_array($relationshipType, ['son', 'daughter', 'child']);

                return [
                    'id' => $dep->id,
                    'name' => $dep->full_name ?? $dep->name,
                    'profile_picture' => $dep->profile_picture ? asset('storage/' . $dep->profile_picture) : null,
                    'gender' => $dep->gender,
                    'age' => $dep->birthdate ? \Carbon\Carbon::parse($dep->birthdate)->age : null,
                    'is_member' => $isDepMember,
                    'relationship_type' => ucfirst($relationshipType),
                    'is_child' => $isChild,
                    // Use guardian's contact info for children only
                    'guardian_name' => $isChild ? ($user->full_name ?? $user->name) : null,
                    'email' => $dep->email ?: ($isChild ? $user->email : null),
                    'mobile' => $dep->mobile ?: ($isChild ? $user->mobile : null),
                ];
            })->filter();

            return [
                'id' => $user->id,
                'name' => $user->full_name ?? $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'profile_picture' => $user->profile_picture ? asset('storage/' . $user->profile_picture) : null,
                'gender' => $user->gender,
                'age' => $user->birthdate ? \Carbon\Carbon::parse($user->birthdate)->age : null,
                'is_member' => $isMember,
                'dependents' => $dependents,
            ];
        });

        return response()->json(['users' => $users]);
    }

    /**
     * Roles management
     */
    public function roles($clubId)
    {
        $club = $this->getClub($clubId);

        // Get all members of this club with their user data
        $members = ClubMemberSubscription::where('tenant_id', $clubId)
            ->with('user')
            ->get()
            ->unique('user_id');

        // Get available roles (club-specific roles)
        $availableRoles = Role::whereIn('slug', ['club-admin', 'instructor', 'staff', 'moderator'])->get();

        // If no roles found in database, create default ones
        if ($availableRoles->isEmpty()) {
            $availableRoles = collect([
                (object) ['slug' => 'club-admin', 'name' => 'Club Admin', 'description' => 'Full access to all club settings, members, and financials'],
                (object) ['slug' => 'instructor', 'name' => 'Instructor', 'description' => 'Can manage activities, view members, and track attendance'],
                (object) ['slug' => 'staff', 'name' => 'Staff', 'description' => 'Limited access to member check-in and basic operations'],
            ]);
        }

        return view('admin.club.roles.index', compact('club', 'members', 'availableRoles'));
    }

    public function storeRole(Request $request, $clubId)
    {
        $club = $this->getClub($clubId);

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|string',
        ]);

        $user = User::findOrFail($request->user_id);
        $user->assignRole($request->role, $clubId);

        return back()->with('success', 'Role assigned successfully.');
    }

    public function destroyRole(Request $request, $clubId)
    {
        $club = $this->getClub($clubId);

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|string',
        ]);

        $user = User::findOrFail($request->user_id);
        $user->removeRole($request->role, $clubId);

        return back()->with('success', 'Role removed successfully.');
    }

    /**
     * Financials management
     */
    public function financials($clubId)
    {
        $club = $this->getClub($clubId);
        $transactions = ClubTransaction::where('tenant_id', $clubId)
            ->latest('transaction_date')
            ->paginate(20);

        $summary = [
            'total_income' => ClubTransaction::where('tenant_id', $clubId)
                ->where('type', 'income')
                ->sum('amount'),
            'total_expenses' => ClubTransaction::where('tenant_id', $clubId)
                ->where('type', 'expense')
                ->sum('amount'),
            'net_profit' => 0,
            'pending' => ClubTransaction::where('tenant_id', $clubId)
                ->where('status', 'pending')
                ->sum('amount'),
        ];
        $summary['net_profit'] = $summary['total_income'] - $summary['total_expenses'];

        return view('admin.club.financials.index', compact('club', 'transactions', 'summary'));
    }

    public function storeIncome(Request $request, $clubId)
    {
        $club = $this->getClub($clubId);

        $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'transaction_date' => 'required|date',
        ]);

        ClubTransaction::create([
            'tenant_id' => $clubId,
            'description' => $request->description,
            'amount' => $request->amount,
            'type' => 'income',
            'transaction_date' => $request->transaction_date,
            'status' => 'paid',
        ]);

        return back()->with('success', 'Income recorded successfully.');
    }

    public function storeExpense(Request $request, $clubId)
    {
        $club = $this->getClub($clubId);

        $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'transaction_date' => 'required|date',
        ]);

        ClubTransaction::create([
            'tenant_id' => $clubId,
            'description' => $request->description,
            'amount' => $request->amount,
            'type' => 'expense',
            'transaction_date' => $request->transaction_date,
            'status' => 'paid',
        ]);

        return back()->with('success', 'Expense recorded successfully.');
    }

    /**
     * Messages management
     */
    public function messages($clubId)
    {
        $club = $this->getClub($clubId);
        $conversations = collect(); // TODO: Implement messaging
        $members = Membership::where('tenant_id', $clubId)->with('user')->get();
        return view('admin.club.messages.index', compact('club', 'conversations', 'members'));
    }

    public function sendMessage(Request $request, $clubId)
    {
        $club = $this->getClub($clubId);

        // TODO: Implement message sending

        return back()->with('success', 'Message sent successfully.');
    }

    /**
     * Analytics
     */
    public function analytics($clubId)
    {
        $club = $this->getClub($clubId);

        $analytics = [
            'new_members' => 0,
            'new_members_change' => 0,
            'retention_rate' => 0,
            'retention_change' => 0,
            'avg_revenue' => 0,
            'total_checkins' => 0,
            'checkins_change' => 0,
            'monthly_members' => array_fill(0, 12, 0),
            'activity_labels' => ['No data'],
            'activity_data' => [100],
            'hourly_checkins' => array_fill(0, 9, 0),
        ];

        $popularPackages = ClubPackage::where('tenant_id', $clubId)
            ->withCount('subscriptions')
            ->orderByDesc('subscriptions_count')
            ->take(5)
            ->get();

        return view('admin.club.analytics.index', compact('club', 'analytics', 'popularPackages'));
    }

    /**
     * Update club details
     */
    public function update(Request $request, $clubId)
    {
        $club = $this->getClub($clubId);

        $request->validate([
            'club_name' => 'required|string|max:255',
            'slogan' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'enrollment_fee' => 'nullable|numeric|min:0',
            'commercial_reg_number' => 'nullable|string|max:100',
            'vat_reg_number' => 'nullable|string|max:100',
            'vat_percentage' => 'nullable|numeric|min:0|max:100',
            'email' => 'nullable|email|max:255',
            'country' => 'nullable|string|max:2',
            'phone_code' => 'nullable|string|max:10',
            'phone_number' => 'nullable|string|max:20',
            'currency' => 'nullable|string|max:3',
            'timezone' => 'nullable|string|max:50',
            'slug' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'gps_lat' => 'nullable|numeric',
            'gps_long' => 'nullable|numeric',
            'logo' => 'nullable|image|max:5120',
            'favicon' => 'nullable|image|max:2048',
            'cover_image' => 'nullable|image|max:10240',
            'settings' => 'nullable|array',
        ]);

        $data = $request->only([
            'club_name', 'slogan', 'description', 'enrollment_fee',
            'commercial_reg_number', 'vat_reg_number', 'vat_percentage',
            'email', 'country', 'currency', 'timezone', 'slug', 'address',
            'gps_lat', 'gps_long', 'owner_name', 'owner_email'
        ]);

        // Handle phone as JSON
        if ($request->filled('phone_code') || $request->filled('phone_number')) {
            $data['phone'] = [
                'code' => $request->phone_code,
                'number' => $request->phone_number,
            ];
        }

        // Handle settings
        if ($request->has('settings')) {
            $currentSettings = $club->settings ?? [];
            $data['settings'] = array_merge($currentSettings, $request->settings);
        }

        // Handle logo upload
        if ($request->hasFile('logo')) {
            // Delete old logo
            if ($club->logo && Storage::disk('public')->exists($club->logo)) {
                Storage::disk('public')->delete($club->logo);
            }
            $data['logo'] = $request->file('logo')->store('clubs/' . $clubId . '/branding', 'public');
        }

        // Handle favicon upload
        if ($request->hasFile('favicon')) {
            if ($club->favicon && Storage::disk('public')->exists($club->favicon)) {
                Storage::disk('public')->delete($club->favicon);
            }
            $data['favicon'] = $request->file('favicon')->store('clubs/' . $clubId . '/branding', 'public');
        }

        // Handle cover image upload
        if ($request->hasFile('cover_image')) {
            if ($club->cover_image && Storage::disk('public')->exists($club->cover_image)) {
                Storage::disk('public')->delete($club->cover_image);
            }
            $data['cover_image'] = $request->file('cover_image')->store('clubs/' . $clubId . '/branding', 'public');
        }

        $club->update($data);

        return back()->with('success', 'Club details updated successfully.');
    }

    /**
     * Delete club
     */
    public function destroy($clubId)
    {
        $club = $this->getClub($clubId);

        // Delete all related storage files
        $folders = [
            'clubs/' . $clubId . '/branding',
            'clubs/' . $clubId . '/gallery',
            'clubs/' . $clubId . '/facilities',
            'clubs/' . $clubId . '/instructors',
        ];

        foreach ($folders as $folder) {
            if (Storage::disk('public')->exists($folder)) {
                Storage::disk('public')->deleteDirectory($folder);
            }
        }

        // Delete the club (soft delete)
        $club->delete();

        return redirect()->route('admin.platform.clubs')->with('success', 'Club deleted successfully.');
    }

    /**
     * Store social link
     */
    public function storeSocialLink(Request $request, $clubId)
    {
        $club = $this->getClub($clubId);

        $request->validate([
            'platform' => 'required|string|max:100',
            'url' => 'required|string|max:500',
            'icon' => 'nullable|string|max:50',
        ]);

        $club->socialLinks()->create([
            'tenant_id' => $clubId,
            'platform' => $request->platform,
            'url' => $request->url,
            'icon' => $request->icon ?? 'link-45deg',
        ]);

        return back()->with('success', 'Social link added successfully.');
    }

    /**
     * Delete social link
     */
    public function destroySocialLink($clubId, $linkId)
    {
        $club = $this->getClub($clubId);

        $link = $club->socialLinks()->findOrFail($linkId);
        $link->delete();

        return back()->with('success', 'Social link deleted successfully.');
    }

    /**
     * Helper: Get monthly financial data for charts
     */
    private function getMonthlyFinancials($clubId)
    {
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $data = [];

        for ($i = 0; $i < 12; $i++) {
            $data[] = [
                'month' => $months[$i],
                'income' => 0,
                'expenses' => 0,
                'profit' => 0,
            ];
        }

        // TODO: Populate with actual transaction data

        return $data;
    }

    /**
     * Upload facility image via AJAX (cropper).
     */
    public function uploadFacilityImage(Request $request, $clubId, $facilityId)
    {
        $club = $this->getClub($clubId);

        $request->validate([
            'image' => 'required',
            'folder' => 'required|string',
            'filename' => 'required|string',
        ]);

        try {
            $facility = ClubFacility::where('tenant_id', $clubId)->findOrFail($facilityId);

            // Handle base64 image from cropper
            $imageData = $request->image;
            $imageParts = explode(";base64,", $imageData);
            $imageTypeAux = explode("image/", $imageParts[0]);
            $extension = $imageTypeAux[1];
            $imageBinary = base64_decode($imageParts[1]);

            $folder = trim($request->folder, '/');
            $fileName = $request->filename . '.' . $extension;
            $fullPath = $folder . '/' . $fileName;

            // Delete old image if exists
            if ($facility->photo && Storage::disk('public')->exists($facility->photo)) {
                Storage::disk('public')->delete($facility->photo);
            }

            // Store in the public disk
            Storage::disk('public')->put($fullPath, $imageBinary);

            // Update facility's photo field
            $facility->update(['photo' => $fullPath]);

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
     * Upload instructor photo via AJAX (cropper).
     */
    public function uploadInstructorPhoto(Request $request, $clubId, $instructorId)
    {
        $club = $this->getClub($clubId);

        $request->validate([
            'image' => 'required',
            'folder' => 'required|string',
            'filename' => 'required|string',
        ]);

        try {
            $instructor = ClubInstructor::where('tenant_id', $clubId)->with('user')->findOrFail($instructorId);

            if (!$instructor->user) {
                return response()->json(['success' => false, 'message' => 'Instructor has no linked user'], 400);
            }

            // Handle base64 image from cropper
            $imageData = $request->image;
            $imageParts = explode(";base64,", $imageData);
            $imageTypeAux = explode("image/", $imageParts[0]);
            $extension = $imageTypeAux[1];
            $imageBinary = base64_decode($imageParts[1]);

            $folder = trim($request->folder, '/');
            $fileName = $request->filename . '.' . $extension;
            $fullPath = $folder . '/' . $fileName;

            // Delete old photo if exists
            if ($instructor->user->profile_picture && Storage::disk('public')->exists($instructor->user->profile_picture)) {
                Storage::disk('public')->delete($instructor->user->profile_picture);
            }

            // Store in the public disk
            Storage::disk('public')->put($fullPath, $imageBinary);

            // Update user's profile picture
            $instructor->user->update(['profile_picture' => $fullPath]);

            return response()->json([
                'success' => true,
                'path' => $fullPath,
                'url' => asset('storage/' . $fullPath)
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
