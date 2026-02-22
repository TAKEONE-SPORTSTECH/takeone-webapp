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
     * Verify the authenticated user can access this club.
     * The Tenant model is already resolved by route binding (accepts slug or ID).
     */
    private function authorizeClub(Tenant $club): void
    {
        $user = Auth::user();
        if (!$user->isSuperAdmin() && $club->owner_user_id !== $user->id) {
            abort(403, 'Unauthorized access to this club.');
        }
    }

    /**
     * Dashboard overview
     */
    public function dashboard(Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

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
    public function details(Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

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
    public function gallery(Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;
        $images = ClubGalleryImage::where('tenant_id', $clubId)->orderBy('display_order')->orderBy('id')->get();
        return view('admin.club.gallery.index', compact('club', 'images'));
    }

    public function uploadGallery(Request $request, Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

        $request->validate([
            'images.*' => 'required|image|max:5120',
            'caption' => 'nullable|string|max:255',
        ]);

        if ($request->hasFile('images')) {
            $nextOrder = ClubGalleryImage::where('tenant_id', $clubId)->max('display_order') + 1;
            foreach ($request->file('images') as $image) {
                $path = $image->store('clubs/' . $clubId . '/gallery', 'public');
                ClubGalleryImage::create([
                    'tenant_id' => $clubId,
                    'image_path' => $path,
                    'caption' => $request->caption,
                    'uploaded_by' => auth()->id(),
                    'display_order' => $nextOrder++,
                ]);
            }
        }

        return back()->with('success', 'Images uploaded successfully.');
    }

    public function reorderGallery(Request $request, Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

        $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer',
        ]);

        foreach ($request->order as $position => $imageId) {
            ClubGalleryImage::where('tenant_id', $clubId)->where('id', $imageId)
                ->update(['display_order' => $position]);
        }

        return response()->json(['success' => true]);
    }

    public function saveYoutubeUrl(Request $request, Tenant $club)
    {
        $this->authorizeClub($club);

        $request->validate([
            'youtube_url' => 'nullable|url|max:500',
        ]);

        $club->update(['youtube_url' => $request->youtube_url ?: null]);

        return back()->with('success', 'YouTube video URL saved successfully.');
    }

    public function destroyGalleryImage(Tenant $club, $imageId)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;
        $image = ClubGalleryImage::where('tenant_id', $clubId)->findOrFail($imageId);

        if ($image->image_path && Storage::disk('public')->exists($image->image_path)) {
            Storage::disk('public')->delete($image->image_path);
        }

        $image->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Facilities management
     */
    public function facilities(Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;
        $facilities = ClubFacility::where('tenant_id', $clubId)->get();
        return view('admin.club.facilities.index', compact('club', 'facilities'));
    }

    public function storeFacility(Request $request, Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

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
    public function getFacility(Tenant $club, $facilityId)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;
        $facility = ClubFacility::where('tenant_id', $clubId)->findOrFail($facilityId);

        return response()->json([
            'success' => true,
            'data' => $facility
        ]);
    }

    /**
     * Update a facility
     */
    public function updateFacility(Request $request, Tenant $club, $facilityId)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

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
    public function destroyFacility(Tenant $club, $facilityId)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;
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
    public function instructors(Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;
        $instructors = ClubInstructor::where('tenant_id', $clubId)->with('user')->get();
        return view('admin.club.instructors.index', compact('club', 'instructors'));
    }

    public function storeInstructor(Request $request, Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;
        $creationType = $request->input('creation_type', 'new');

        if ($creationType === 'new') {
            // Validate for new user creation
            $request->validate([
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:6',
                'name' => 'required|string|max:255',
                'phone' => 'required|string',
                'gender' => 'required|in:m,f',
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

            // Handle photo upload for user (base64 from cropper or file upload)
            if ($request->filled('photo') && str_starts_with($request->input('photo'), 'data:image')) {
                $photoPath = $this->storeBase64Image($request->input('photo'), 'users/' . $user->id, 'profile_' . time());
                $user->update(['profile_picture' => $photoPath]);
            } elseif ($request->hasFile('photo')) {
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
    public function activities(Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;
        $activities = ClubActivity::where('tenant_id', $clubId)->with('facility')->get();
        $facilities = ClubFacility::where('tenant_id', $clubId)->get();
        return view('admin.club.activities.index', compact('club', 'activities', 'facilities'));
    }

    public function storeActivity(Request $request, Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
            'duration_minutes' => 'nullable|integer|min:1',
            'existing_picture_url' => 'nullable|string',
        ]);

        $data = $request->only(['name', 'description', 'notes', 'duration_minutes']);
        $data['tenant_id'] = $clubId;

        // Handle picture upload (base64 from cropper or file upload)
        if ($request->filled('picture') && str_starts_with($request->input('picture'), 'data:image')) {
            $data['picture_url'] = $this->storeBase64Image($request->input('picture'), 'clubs/' . $clubId . '/activities', 'activity_' . time());
        } elseif ($request->hasFile('picture')) {
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

    public function updateActivity(Request $request, Tenant $club, $activityId)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;
        $activity = ClubActivity::where('tenant_id', $clubId)->findOrFail($activityId);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
            'duration_minutes' => 'nullable|integer|min:1',
        ]);

        $data = $request->only(['name', 'description', 'notes', 'duration_minutes']);

        // Handle picture upload (base64 from cropper or file upload)
        if ($request->filled('picture') && str_starts_with($request->input('picture'), 'data:image')) {
            if ($activity->picture_url && Storage::disk('public')->exists($activity->picture_url)) {
                Storage::disk('public')->delete($activity->picture_url);
            }
            $data['picture_url'] = $this->storeBase64Image($request->input('picture'), 'clubs/' . $clubId . '/activities', 'activity_' . $activityId . '_' . time());
        } elseif ($request->hasFile('picture')) {
            if ($activity->picture_url && Storage::disk('public')->exists($activity->picture_url)) {
                Storage::disk('public')->delete($activity->picture_url);
            }
            $data['picture_url'] = $request->file('picture')->store('clubs/' . $clubId . '/activities', 'public');
        }

        $activity->update($data);

        return back()->with('success', 'Activity updated successfully.');
    }

    public function destroyActivity(Tenant $club, $activityId)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;
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
    public function packages(Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;
        $packages = ClubPackage::where('tenant_id', $clubId)
            ->with(['activities'])
            ->get();
        $facilities = ClubFacility::where('tenant_id', $clubId)->get();
        $activities = ClubActivity::where('tenant_id', $clubId)->get();
        $instructors = ClubInstructor::where('tenant_id', $clubId)->with('user')->get();
        $instructorsMap = $instructors->mapWithKeys(function ($instructor) {
            return [$instructor->id => [
                'id' => $instructor->id,
                'name' => $instructor->user?->full_name ?? $instructor->user?->name ?? 'Unknown',
                'image' => $instructor->user?->profile_picture ?? null,
            ]];
        });
        return view('admin.club.packages.index', compact('club', 'packages', 'facilities', 'activities', 'instructors', 'instructorsMap'));
    }

    public function storePackage(Request $request, Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration_months' => 'required|integer|min:1',
            'gender_restriction' => 'nullable|string|in:mixed,male,female',
            'age_min' => 'nullable|integer|min:0',
            'age_max' => 'nullable|integer|min:0',
        ]);

        $data = [
            'tenant_id' => $clubId,
            'name' => $request->name,
            'description' => $request->description,
            'price' => $request->price,
            'duration_months' => $request->duration_months,
            'gender' => $request->gender_restriction ?? 'mixed',
            'age_min' => $request->age_min,
            'age_max' => $request->age_max,
            'is_active' => true,
        ];

        // Handle image upload (base64 from cropper or file upload)
        if ($request->filled('image') && str_starts_with($request->input('image'), 'data:image')) {
            $data['cover_image'] = $this->storeBase64Image($request->input('image'), 'packages', 'package_' . time());
        } elseif ($request->hasFile('image')) {
            $data['cover_image'] = $request->file('image')->store('packages', 'public');
        }

        $package = ClubPackage::create($data);

        // Save activity-instructor assignments and schedules
        if ($request->schedules) {
            $schedules = json_decode($request->schedules, true) ?? [];
            $trainerAssignments = json_decode($request->trainer_assignments, true) ?? [];

            // Group schedules by activityId: each activity gets its own schedule entries
            $activitySchedules = [];
            foreach ($schedules as $schedule) {
                $activityId = $schedule['activityId'] ?? null;
                if (!$activityId) continue;

                $days = $schedule['days'] ?? [];
                $startTime = $schedule['startTime'] ?? '';
                $endTime = $schedule['endTime'] ?? '';

                foreach ($days as $day) {
                    // Days can be strings or objects {value, name} from ScheduleTimePicker
                    $dayValue = is_array($day) ? ($day['value'] ?? $day['name'] ?? '') : $day;
                    $activitySchedules[$activityId][] = [
                        'day' => $dayValue,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                    ];
                }
            }

            $syncData = [];
            foreach ($activitySchedules as $activityId => $scheduleEntries) {
                $syncData[$activityId] = [
                    'instructor_id' => $trainerAssignments[$activityId] ?? null,
                    'schedule' => json_encode($scheduleEntries),
                ];
            }

            $package->activities()->sync($syncData);
        }

        return back()->with('success', 'Package created successfully.');
    }

    /**
     * Update a package
     */
    public function updatePackage(Request $request, Tenant $club, $packageId)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

        $package = ClubPackage::where('tenant_id', $clubId)
            ->where('id', $packageId)
            ->firstOrFail();

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration_months' => 'required|integer|min:1',
            'gender_restriction' => 'nullable|string|in:mixed,male,female',
            'age_min' => 'nullable|integer|min:0',
            'age_max' => 'nullable|integer|min:0',
        ]);

        $data = [
            'name' => $request->name,
            'description' => $request->description,
            'price' => $request->price,
            'duration_months' => $request->duration_months,
            'gender' => $request->gender_restriction ?? 'mixed',
            'age_min' => $request->age_min,
            'age_max' => $request->age_max,
        ];

        // Handle image upload (base64 from cropper or file upload)
        if ($request->filled('image') && str_starts_with($request->input('image'), 'data:image')) {
            if ($package->cover_image && Storage::disk('public')->exists($package->cover_image)) {
                Storage::disk('public')->delete($package->cover_image);
            }
            $data['cover_image'] = $this->storeBase64Image($request->input('image'), 'packages', 'package_' . $packageId . '_' . time());
        } elseif ($request->hasFile('image')) {
            if ($package->cover_image && Storage::disk('public')->exists($package->cover_image)) {
                Storage::disk('public')->delete($package->cover_image);
            }
            $data['cover_image'] = $request->file('image')->store('packages', 'public');
        }

        $package->update($data);

        // Sync activity-instructor assignments and schedules
        if ($request->schedules) {
            $schedules = json_decode($request->schedules, true) ?? [];
            $trainerAssignments = json_decode($request->trainer_assignments, true) ?? [];

            // Group schedules by activityId
            $activitySchedules = [];
            foreach ($schedules as $schedule) {
                $activityId = $schedule['activityId'] ?? null;
                if (!$activityId) continue;

                $days = $schedule['days'] ?? [];
                $startTime = $schedule['startTime'] ?? '';
                $endTime = $schedule['endTime'] ?? '';

                foreach ($days as $day) {
                    // Days can be strings or objects {value, name} from ScheduleTimePicker
                    $dayValue = is_array($day) ? ($day['value'] ?? $day['name'] ?? '') : $day;
                    $activitySchedules[$activityId][] = [
                        'day' => $dayValue,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                    ];
                }
            }

            $syncData = [];
            foreach ($activitySchedules as $activityId => $scheduleEntries) {
                $syncData[$activityId] = [
                    'instructor_id' => $trainerAssignments[$activityId] ?? null,
                    'schedule' => json_encode($scheduleEntries),
                ];
            }

            $package->activities()->sync($syncData);
        } else {
            // No schedules submitted — clear all activity links
            $package->activities()->detach();
        }

        return back()->with('success', 'Package updated successfully.');
    }

    /**
     * Delete a package
     */
    public function destroyPackage(Tenant $club, $packageId)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

        $package = ClubPackage::where('tenant_id', $clubId)
            ->where('id', $packageId)
            ->firstOrFail();

        $package->delete();

        return back()->with('success', 'Package deleted successfully.');
    }

    /**
     * Members management
     */
    public function members(Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;
        $members = Membership::where('tenant_id', $clubId)
            ->with(['user', 'user.guardians.guardian'])
            ->paginate(20);
        $packages = ClubPackage::where('tenant_id', $clubId)->get();
        return view('admin.club.members.index', compact('club', 'members', 'packages'));
    }

    public function storeMember(Request $request, Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

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

    public function searchUsers(Request $request, Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;
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
    public function roles(Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

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

    public function storeRole(Request $request, Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|string',
        ]);

        $user = User::findOrFail($request->user_id);
        $user->assignRole($request->role, $clubId);

        return back()->with('success', 'Role assigned successfully.');
    }

    public function destroyRole(Request $request, Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

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
    public function financials(Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;
        $transactions = ClubTransaction::where('tenant_id', $clubId)
            ->latest('transaction_date')
            ->get();

        $summary = [
            'total_income' => ClubTransaction::where('tenant_id', $clubId)
                ->where('type', 'income')
                ->sum('amount'),
            'total_expenses' => ClubTransaction::where('tenant_id', $clubId)
                ->where('type', 'expense')
                ->sum('amount'),
            'refunds' => ClubTransaction::where('tenant_id', $clubId)
                ->where('type', 'refund')
                ->sum('amount'),
            'net_profit' => 0,
            'pending' => 0, // TODO: populate when payment_status column is added
        ];
        $summary['net_profit'] = $summary['total_income'] - $summary['total_expenses'] - $summary['refunds'];

        // Monthly data for the last 12 months (for chart)
        $monthlyData = [];
        $now = now();
        for ($i = 11; $i >= 0; $i--) {
            $date = $now->copy()->subMonths($i);
            $monthKey = $date->format('Y-m');
            $monthLabel = $date->format('M');

            $monthIncome = ClubTransaction::where('tenant_id', $clubId)
                ->where('type', 'income')
                ->whereYear('transaction_date', $date->year)
                ->whereMonth('transaction_date', $date->month)
                ->sum('amount');

            $monthExpenses = ClubTransaction::where('tenant_id', $clubId)
                ->where('type', 'expense')
                ->whereYear('transaction_date', $date->year)
                ->whereMonth('transaction_date', $date->month)
                ->sum('amount');

            $monthRefunds = ClubTransaction::where('tenant_id', $clubId)
                ->where('type', 'refund')
                ->whereYear('transaction_date', $date->year)
                ->whereMonth('transaction_date', $date->month)
                ->sum('amount');

            $monthlyData[] = [
                'month' => $monthLabel,
                'income' => (float) $monthIncome,
                'expenses' => (float) $monthExpenses,
                'refunds' => (float) $monthRefunds,
                'profit' => (float) ($monthIncome - $monthExpenses - $monthRefunds),
            ];
        }

        // Expense categories breakdown
        $expenseCategories = ClubTransaction::where('tenant_id', $clubId)
            ->where('type', 'expense')
            ->get()
            ->groupBy(fn($t) => $t->category ?? 'Other')
            ->map(fn($items, $cat) => [
                'category' => $cat,
                'items' => $items,
                'total' => $items->sum('amount'),
            ])
            ->values();

        return view('admin.club.financials.index', compact('club', 'transactions', 'summary', 'monthlyData', 'expenseCategories'));
    }

    public function storeIncome(Request $request, Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

        $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'transaction_date' => 'required|date',
            'category' => 'nullable|string|max:255',
            'payment_method' => 'nullable|in:cash,card,bank_transfer,online,other',
            'reference_number' => 'nullable|string|max:255',
        ]);

        ClubTransaction::create([
            'tenant_id' => $clubId,
            'description' => $request->description,
            'amount' => $request->amount,
            'type' => 'income',
            'category' => $request->category,
            'payment_method' => $request->payment_method ?? 'cash',
            'reference_number' => $request->reference_number,
            'transaction_date' => $request->transaction_date,
        ]);

        return back()->with('success', 'Income recorded successfully.');
    }

    public function storeExpense(Request $request, Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

        $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'transaction_date' => 'required|date',
            'category' => 'nullable|string|max:255',
            'payment_method' => 'nullable|in:cash,card,bank_transfer,online,other',
            'reference_number' => 'nullable|string|max:255',
        ]);

        ClubTransaction::create([
            'tenant_id' => $clubId,
            'description' => $request->description,
            'amount' => $request->amount,
            'type' => 'expense',
            'category' => $request->category,
            'payment_method' => $request->payment_method ?? 'cash',
            'reference_number' => $request->reference_number,
            'transaction_date' => $request->transaction_date,
        ]);

        return back()->with('success', 'Expense recorded successfully.');
    }

    public function updateTransaction(Request $request, Tenant $club, $transactionId)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;
        $transaction = ClubTransaction::where('tenant_id', $clubId)->findOrFail($transactionId);

        $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'transaction_date' => 'required|date',
            'type' => 'required|in:income,expense,refund',
            'category' => 'nullable|string|max:255',
            'payment_method' => 'nullable|in:cash,card,bank_transfer,online,other',
            'reference_number' => 'nullable|string|max:255',
        ]);

        $transaction->update($request->only([
            'description', 'amount', 'transaction_date', 'type',
            'category', 'payment_method', 'reference_number',
        ]));

        return back()->with('success', 'Transaction updated successfully.');
    }

    public function destroyTransaction(Tenant $club, $transactionId)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;
        $transaction = ClubTransaction::where('tenant_id', $clubId)->findOrFail($transactionId);
        $transaction->delete();

        return back()->with('success', 'Transaction deleted successfully.');
    }

    /**
     * Messages management
     */
    public function messages(Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;
        $conversations = collect(); // TODO: Implement messaging
        $members = Membership::where('tenant_id', $clubId)->with('user')->get();
        return view('admin.club.messages.index', compact('club', 'conversations', 'members'));
    }

    public function sendMessage(Request $request, Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

        // TODO: Implement message sending

        return back()->with('success', 'Message sent successfully.');
    }

    /**
     * Analytics
     */
    public function analytics(Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

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
    public function update(Request $request, Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

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
            'logo' => 'nullable',
            'favicon' => 'nullable',
            'cover_image' => 'nullable',
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

        // Handle logo upload (base64 from cropper or file upload)
        if ($request->filled('logo') && str_starts_with($request->input('logo'), 'data:image')) {
            if ($club->logo && Storage::disk('public')->exists($club->logo)) {
                Storage::disk('public')->delete($club->logo);
            }
            $data['logo'] = $this->storeBase64Image($request->input('logo'), 'clubs/' . $clubId . '/branding', 'logo_' . time());
        } elseif ($request->hasFile('logo')) {
            if ($club->logo && Storage::disk('public')->exists($club->logo)) {
                Storage::disk('public')->delete($club->logo);
            }
            $data['logo'] = $request->file('logo')->store('clubs/' . $clubId . '/branding', 'public');
        }

        // Handle favicon upload (base64 from cropper or file upload)
        if ($request->filled('favicon') && str_starts_with($request->input('favicon'), 'data:image')) {
            if ($club->favicon && Storage::disk('public')->exists($club->favicon)) {
                Storage::disk('public')->delete($club->favicon);
            }
            $data['favicon'] = $this->storeBase64Image($request->input('favicon'), 'clubs/' . $clubId . '/branding', 'favicon_' . time());
        } elseif ($request->hasFile('favicon')) {
            if ($club->favicon && Storage::disk('public')->exists($club->favicon)) {
                Storage::disk('public')->delete($club->favicon);
            }
            $data['favicon'] = $request->file('favicon')->store('clubs/' . $clubId . '/branding', 'public');
        }

        // Handle cover image upload (base64 from cropper or file upload)
        if ($request->filled('cover_image') && str_starts_with($request->input('cover_image'), 'data:image')) {
            if ($club->cover_image && Storage::disk('public')->exists($club->cover_image)) {
                Storage::disk('public')->delete($club->cover_image);
            }
            $data['cover_image'] = $this->storeBase64Image($request->input('cover_image'), 'clubs/' . $clubId . '/branding', 'cover_' . time());
        } elseif ($request->hasFile('cover_image')) {
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
    public function destroy(Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

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
    public function storeSocialLink(Request $request, Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

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
    public function destroySocialLink(Tenant $club, $linkId)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

        $link = $club->socialLinks()->findOrFail($linkId);
        $link->delete();

        return back()->with('success', 'Social link deleted successfully.');
    }

    /**
     * Helper: Store a base64 image from the cropper component.
     * Returns the stored path, or null if not a base64 image.
     */
    private function storeBase64Image(string $base64, string $folder, string $filenameBase): ?string
    {
        if (!str_starts_with($base64, 'data:image')) {
            return null;
        }

        $imageParts = explode(';base64,', $base64);
        $imageTypeAux = explode('image/', $imageParts[0]);
        $extension = $imageTypeAux[1] ?? 'png';
        $imageBinary = base64_decode($imageParts[1]);

        $fullPath = trim($folder, '/') . '/' . $filenameBase . '.' . $extension;
        Storage::disk('public')->put($fullPath, $imageBinary);

        return $fullPath;
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
    public function uploadFacilityImage(Request $request, Tenant $club, $facilityId)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

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
    public function uploadInstructorPhoto(Request $request, Tenant $club, $instructorId)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

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
