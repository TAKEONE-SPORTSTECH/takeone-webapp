<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\ClubFacility;
use App\Models\ClubInstructor;
use App\Models\ClubActivity;
use App\Models\ClubEvent;
use App\Models\ClubTimelinePost;
use App\Models\ClubPerk;
use App\Models\ClubAchievement;
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
            'events' => ClubEvent::where('tenant_id', $clubId)->where('is_archived', false)->count(),
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
            'caption' => 'nullable|string|max:255',
        ]);

        $nextOrder = ClubGalleryImage::where('tenant_id', $clubId)->max('display_order') + 1;

        // Handle base64 image from cropper (form mode)
        if ($request->filled('image_data') && str_starts_with($request->image_data, 'data:image')) {
            $imageData = $request->image_data;
            $imageParts = explode(';base64,', $imageData);
            $imageTypeAux = explode('image/', $imageParts[0]);
            $extension = $imageTypeAux[1];
            $imageBinary = base64_decode($imageParts[1]);

            $folder = 'clubs/' . $clubId . '/gallery';
            $filename = 'gallery_' . time() . '_' . uniqid() . '.' . $extension;
            $fullPath = $folder . '/' . $filename;

            Storage::disk('public')->put($fullPath, $imageBinary);

            ClubGalleryImage::create([
                'tenant_id' => $clubId,
                'image_path' => $fullPath,
                'caption' => $request->caption,
                'uploaded_by' => auth()->id(),
                'display_order' => $nextOrder,
            ]);
        }
        // Handle traditional file upload (fallback)
        elseif ($request->hasFile('images')) {
            $request->validate(['images.*' => 'required|image|max:5120']);
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

        return back()->with('success', 'Image uploaded successfully.');
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
            'maps_url' => 'nullable|url|max:500',
            'is_available' => 'nullable|boolean',
        ]);

        $data = [
            'tenant_id' => $clubId,
            'name' => $request->name,
            'address' => $request->address,
            'gps_lat' => $request->latitude,
            'gps_long' => $request->longitude,
            'maps_url' => $request->maps_url,
            'is_available' => $request->has('is_available'),
        ];

        $paths = $this->saveFacilityBase64Images($request->input('facility_images_base64', []), $clubId);
        if ($paths) $data['images'] = $paths;

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
            'maps_url' => 'nullable|url|max:500',
            'is_available' => 'nullable|boolean',
            'facility_images' => 'nullable|array',
            'facility_images.*' => 'image|max:4096',
        ]);

        $facility = ClubFacility::where('tenant_id', $clubId)->findOrFail($facilityId);

        $data = $request->only(['name', 'address', 'gps_lat', 'gps_long', 'maps_url']);
        $data['is_available'] = $request->has('is_available');

        $kept     = json_decode($request->input('keep_images', '[]'), true) ?: [];
        $newPaths = $this->saveFacilityBase64Images($request->input('facility_images_base64', []), $clubId);
        $data['images'] = array_merge($kept, $newPaths);

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

    private function saveFacilityBase64Images(array $base64List, int $clubId): array
    {
        $paths = [];
        foreach ($base64List as $base64) {
            if (!str_starts_with($base64, 'data:image')) continue;
            [$meta, $imageData] = explode(',', $base64, 2);
            preg_match('/image\/(\w+)/', $meta, $m);
            $ext  = $m[1] ?? 'jpg';
            $path = 'clubs/' . $clubId . '/facilities/' . uniqid('facility_') . '.' . $ext;
            Storage::disk('public')->put($path, base64_decode($imageData));
            $paths[] = $path;
        }
        return $paths;
    }

    private function saveEventBase64Images(array $base64List, int $clubId): array
    {
        $paths = [];
        foreach ($base64List as $base64) {
            if (!str_starts_with($base64, 'data:image')) continue;
            [$meta, $imageData] = explode(',', $base64, 2);
            preg_match('/image\/(\w+)/', $meta, $m);
            $ext  = $m[1] ?? 'jpg';
            $path = 'clubs/' . $clubId . '/events/' . uniqid('event_') . '.' . $ext;
            Storage::disk('public')->put($path, base64_decode($imageData));
            $paths[] = $path;
        }
        return $paths;
    }

    private function saveAchievementBase64Images(array $base64List, int $clubId): array
    {
        $paths = [];
        foreach ($base64List as $base64) {
            if (!str_starts_with($base64, 'data:image')) continue;
            [$meta, $imageData] = explode(',', $base64, 2);
            preg_match('/image\/(\w+)/', $meta, $m);
            $ext  = $m[1] ?? 'jpg';
            $path = 'clubs/' . $clubId . '/achievements/' . uniqid('ach_') . '.' . $ext;
            Storage::disk('public')->put($path, base64_decode($imageData));
            $paths[] = $path;
        }
        return $paths;
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

        // Save trainer profile data to the User (shared across all club positions)
        User::where('id', $userId)->update([
            'bio'              => $bio ?: null,
            'skills'           => !empty($skills) ? $skills : null,
            'experience_years' => $experienceYears ?: null,
        ]);

        // Create the instructor record (club-specific data only)
        ClubInstructor::create([
            'tenant_id' => $clubId,
            'user_id'   => $userId,
            'role'      => $role,
        ]);

        return back()->with('success', 'Instructor added successfully.');
    }

    public function destroyInstructor(Tenant $club, ClubInstructor $instructor)
    {
        $this->authorizeClub($club);

        if ($instructor->tenant_id !== $club->id) {
            abort(403);
        }

        // Only the ClubInstructor (hiring) record is deleted.
        // Any ClubMemberSubscription (package enrollment) the user holds in this club
        // is unaffected — they remain a registered member with their subscription intact.
        $instructor->delete();

        return response()->json(['success' => true, 'message' => 'Instructor removed from club successfully.']);
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
     * Events management
     */
    public function events(Tenant $club)
    {
        $this->authorizeClub($club);
        $events = ClubEvent::where('tenant_id', $club->id)
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();
        $facilities = ClubFacility::where('tenant_id', $club->id)->orderBy('name')->get();
        return view('admin.club.events.index', compact('club', 'events', 'facilities'));
    }

    public function storeEvent(Request $request, Tenant $club)
    {
        $this->authorizeClub($club);

        $request->validate([
            'title'        => 'required|string|max:255',
            'date'         => 'required|date',
            'end_date'     => 'nullable|date|after_or_equal:date',
            'start_time'   => 'required',
            'end_time'     => 'nullable',
            'location'     => 'nullable|string|max:255',
            'level'        => 'nullable|string|max:255',
            'description'  => 'nullable|string',
            'max_capacity'       => 'nullable|integer|min:1',
            'cancel_within_days' => 'nullable|integer|min:1|max:365',
            'tags'               => 'nullable|string',
            'color'              => 'nullable|string|max:20',
        ]);

        $data = $request->only([
            'title', 'date', 'end_date', 'start_time', 'end_time', 'location', 'level',
            'description', 'max_capacity', 'cancel_within_days', 'color',
        ]);
        $data['tenant_id'] = $club->id;
        $data['status']    = 'active';

        if ($request->filled('tags')) {
            $data['tags'] = array_values(array_filter(array_map('trim', explode(',', $request->input('tags')))));
        } else {
            $data['tags'] = null;
        }

        $images = $this->saveEventBase64Images($request->input('event_images_base64', []), $club->id);
        $data['images'] = $images ?: null;

        ClubEvent::create($data);

        return back()->with('success', 'Event created successfully.');
    }

    public function updateEvent(Request $request, Tenant $club, $eventId)
    {
        $this->authorizeClub($club);
        $event = ClubEvent::where('tenant_id', $club->id)->findOrFail($eventId);

        $request->validate([
            'title'        => 'required|string|max:255',
            'date'         => 'required|date',
            'end_date'     => 'nullable|date|after_or_equal:date',
            'start_time'   => 'required',
            'end_time'     => 'nullable',
            'location'     => 'nullable|string|max:255',
            'level'        => 'nullable|string|max:255',
            'description'  => 'nullable|string',
            'max_capacity'       => 'nullable|integer|min:1',
            'cancel_within_days' => 'nullable|integer|min:1|max:365',
            'tags'               => 'nullable|string',
            'color'              => 'nullable|string|max:20',
        ]);

        $data = $request->only([
            'title', 'date', 'end_date', 'start_time', 'end_time', 'location', 'level',
            'description', 'max_capacity', 'cancel_within_days', 'color',
        ]);

        if ($request->filled('tags')) {
            $data['tags'] = array_values(array_filter(array_map('trim', explode(',', $request->input('tags')))));
        } else {
            $data['tags'] = null;
        }

        // Keep existing images, append newly cropped ones
        $keepImages = json_decode($request->input('keep_images', '[]'), true) ?: [];
        $newImages  = $this->saveEventBase64Images($request->input('event_images_base64', []), $club->id);
        $data['images'] = array_merge($keepImages, $newImages) ?: null;

        $event->update($data);

        return back()->with('success', 'Event updated successfully.');
    }

    public function destroyEvent(Tenant $club, $eventId)
    {
        $this->authorizeClub($club);
        $event = ClubEvent::where('tenant_id', $club->id)->findOrFail($eventId);
        $event->delete();

        return response()->json(['success' => true, 'message' => 'Event deleted successfully.']);
    }

    public function archiveEvent(Tenant $club, $eventId)
    {
        $this->authorizeClub($club);
        $event = ClubEvent::where('tenant_id', $club->id)->findOrFail($eventId);
        $event->update(['is_archived' => !$event->is_archived]);

        $msg = $event->is_archived ? 'Event archived.' : 'Event unarchived.';
        return back()->with('success', $msg);
    }

    /**
     * Timeline management
     */
    public function timeline(Tenant $club)
    {
        $this->authorizeClub($club);
        $posts = ClubTimelinePost::where('tenant_id', $club->id)
            ->withCount(['likes', 'comments'])
            ->orderBy('posted_at', 'desc')
            ->get();

        return view('admin.club.timeline.index', compact('club', 'posts'));
    }

    public function storeTimelinePost(Request $request, Tenant $club)
    {
        $this->authorizeClub($club);

        $request->validate([
            'body'      => 'required|string',
            'category'  => 'required|string|max:100',
            'image'     => 'nullable|image|max:5120',
            'posted_at' => 'required|date',
            'status'    => 'required|in:published,draft',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('timeline/' . $club->slug, 'public');
        }

        ClubTimelinePost::create([
            'tenant_id' => $club->id,
            'body'      => $request->body,
            'category'  => $request->category,
            'image_path'=> $imagePath,
            'posted_at' => $request->posted_at,
            'status'    => $request->status,
        ]);

        return back()->with('success', 'Post created successfully.');
    }

    public function updateTimelinePost(Request $request, Tenant $club, ClubTimelinePost $post)
    {
        $this->authorizeClub($club);
        abort_if($post->tenant_id !== $club->id, 403);

        $request->validate([
            'body'      => 'required|string',
            'category'  => 'required|string|max:100',
            'image'     => 'nullable|image|max:5120',
            'posted_at' => 'required|date',
            'status'    => 'required|in:published,draft',
        ]);

        $data = $request->only(['body', 'category', 'posted_at', 'status']);

        if ($request->hasFile('image')) {
            // Delete old image
            if ($post->image_path) {
                Storage::disk('public')->delete($post->image_path);
            }
            $data['image_path'] = $request->file('image')->store('timeline/' . $club->slug, 'public');
        }

        if ($request->boolean('remove_image') && $post->image_path) {
            Storage::disk('public')->delete($post->image_path);
            $data['image_path'] = null;
        }

        $post->update($data);

        return back()->with('success', 'Post updated successfully.');
    }

    public function destroyTimelinePost(Tenant $club, ClubTimelinePost $post)
    {
        $this->authorizeClub($club);
        abort_if($post->tenant_id !== $club->id, 403);

        if ($post->image_path) {
            Storage::disk('public')->delete($post->image_path);
        }
        $post->delete();

        return response()->json(['success' => true, 'message' => 'Post deleted successfully.']);
    }

    /**
     * Perks management
     */
    public function perks(Tenant $club)
    {
        $this->authorizeClub($club);
        $perks = ClubPerk::where('tenant_id', $club->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('admin.club.perks.index', compact('club', 'perks'));
    }

    public function storePerk(Request $request, Tenant $club)
    {
        $this->authorizeClub($club);

        $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'badge'       => 'required|string|max:50',
            'icon'        => 'nullable|string|max:60',
            'bg_from'     => 'nullable|string|max:20',
            'bg_to'       => 'nullable|string|max:20',
            'perk_type'   => 'required|in:code,qr',
            'perk_value'  => 'nullable|string|max:1000',
            'status'      => 'required|in:active,inactive',
            'sort_order'  => 'nullable|integer|min:0',
        ]);

        $imagePath = null;
        if ($request->filled('image') && str_starts_with($request->image, 'data:image')) {
            $imageData   = $request->image;
            $imageParts  = explode(';base64,', $imageData);
            $extension   = explode('image/', $imageParts[0])[1];
            $imageBinary = base64_decode($imageParts[1]);
            $folder      = $request->input('image_folder', 'perks/' . $club->slug);
            $filename    = $request->input('image_filename', 'perk_' . time());
            $imagePath   = $folder . '/' . $filename . '.' . $extension;
            Storage::disk('public')->put($imagePath, $imageBinary);
        }

        ClubPerk::create([
            'tenant_id'  => $club->id,
            'title'      => $request->title,
            'description'=> $request->description,
            'badge'      => $request->badge,
            'image_path' => $imagePath,
            'icon'       => $request->icon ?: 'bi-gift',
            'bg_from'    => $request->bg_from ?: '#f59e0b',
            'bg_to'      => $request->bg_to   ?: '#f97316',
            'perk_type'  => $request->perk_type,
            'perk_value' => $request->perk_value,
            'status'     => $request->status,
            'sort_order' => $request->sort_order ?? 0,
        ]);

        return back()->with('success', 'Perk created successfully.');
    }

    public function updatePerk(Request $request, Tenant $club, ClubPerk $perk)
    {
        $this->authorizeClub($club);
        abort_if($perk->tenant_id !== $club->id, 403);

        $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'badge'       => 'required|string|max:50',
            'icon'        => 'nullable|string|max:60',
            'bg_from'     => 'nullable|string|max:20',
            'bg_to'       => 'nullable|string|max:20',
            'perk_type'   => 'required|in:code,qr',
            'perk_value'  => 'nullable|string|max:1000',
            'status'      => 'required|in:active,inactive',
            'sort_order'  => 'nullable|integer|min:0',
        ]);

        $data = $request->only([
            'title', 'description', 'badge', 'icon', 'bg_from', 'bg_to',
            'perk_type', 'perk_value', 'status', 'sort_order',
        ]);

        // New image from cropper (base64)
        if ($request->filled('image') && str_starts_with($request->image, 'data:image')) {
            if ($perk->image_path) {
                Storage::disk('public')->delete($perk->image_path);
            }
            $imageData   = $request->image;
            $imageParts  = explode(';base64,', $imageData);
            $extension   = explode('image/', $imageParts[0])[1];
            $imageBinary = base64_decode($imageParts[1]);
            $folder      = $request->input('image_folder', 'perks/' . $club->slug);
            $filename    = $request->input('image_filename', 'perk_' . time());
            $data['image_path'] = $folder . '/' . $filename . '.' . $extension;
            Storage::disk('public')->put($data['image_path'], $imageBinary);
        }

        // Explicit removal of existing image
        if ($request->boolean('remove_image') && $perk->image_path) {
            Storage::disk('public')->delete($perk->image_path);
            $data['image_path'] = null;
        }

        $perk->update($data);

        return back()->with('success', 'Perk updated successfully.');
    }

    public function destroyPerk(Tenant $club, ClubPerk $perk)
    {
        $this->authorizeClub($club);
        abort_if($perk->tenant_id !== $club->id, 403);

        if ($perk->image_path) {
            Storage::disk('public')->delete($perk->image_path);
        }
        $perk->delete();

        return response()->json(['success' => true, 'message' => 'Perk deleted successfully.']);
    }

    // -------------------------------------------------------------------------
    // Achievements
    // -------------------------------------------------------------------------

    public function achievements(Tenant $club)
    {
        $this->authorizeClub($club);
        $achievements = ClubAchievement::where('tenant_id', $club->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('admin.club.achievements.index', compact('club', 'achievements'));
    }

    public function storeAchievement(Request $request, Tenant $club)
    {
        $this->authorizeClub($club);

        $request->validate([
            'title'            => 'required|string|max:255',
            'short_title'      => 'nullable|string|max:255',
            'type_icon'        => 'nullable|string|max:10',
            'description'      => 'nullable|string|max:2000',
            'location'         => 'nullable|string|max:255',
            'achievement_date' => 'nullable|date',
            'date_label'       => 'nullable|string|max:60',
            'medals_gold'      => 'nullable|integer|min:0',
            'medals_silver'    => 'nullable|integer|min:0',
            'medals_bronze'    => 'nullable|integer|min:0',
            'bouts_count'      => 'nullable|integer|min:0',
            'wins_count'       => 'nullable|integer|min:0',
            'category'         => 'nullable|string|max:255',
            'chips'            => 'nullable|string',
            'athletes'         => 'nullable|string',
            'tag'              => 'required|string|max:60',
            'tag_icon'         => 'nullable|string|max:60',
            'bg_from'          => 'nullable|string|max:20',
            'bg_to'            => 'nullable|string|max:20',
            'status'           => 'required|in:active,inactive',
            'sort_order'       => 'nullable|integer|min:0',
        ]);

        $images = $this->saveAchievementBase64Images($request->input('achievement_images_base64', []), $club->id);

        ClubAchievement::create([
            'tenant_id'        => $club->id,
            'title'            => $request->title,
            'short_title'      => $request->short_title,
            'type_icon'        => $request->type_icon,
            'description'      => $request->description,
            'location'         => $request->location,
            'achievement_date' => $request->achievement_date,
            'date_label'       => $request->date_label,
            'medals_gold'      => $request->medals_gold ?? 0,
            'medals_silver'    => $request->medals_silver ?? 0,
            'medals_bronze'    => $request->medals_bronze ?? 0,
            'bouts_count'      => $request->bouts_count ?? 0,
            'wins_count'       => $request->wins_count ?? 0,
            'category'         => $request->category,
            'chips'            => $request->chips ? json_decode($request->chips, true) : null,
            'athletes'         => $request->athletes ? json_decode($request->athletes, true) : null,
            'tag'              => $request->tag,
            'tag_icon'         => $request->tag_icon ?: 'bi-trophy',
            'image_path'       => null,
            'images'           => $images ?: null,
            'bg_from'          => $request->bg_from ?: '#f59e0b',
            'bg_to'            => $request->bg_to   ?: '#f97316',
            'status'           => $request->status,
            'sort_order'       => $request->sort_order ?? 0,
        ]);

        return back()->with('success', 'Achievement created successfully.');
    }

    public function updateAchievement(Request $request, Tenant $club, ClubAchievement $achievement)
    {
        $this->authorizeClub($club);
        abort_if($achievement->tenant_id !== $club->id, 403);

        $request->validate([
            'title'            => 'required|string|max:255',
            'short_title'      => 'nullable|string|max:255',
            'type_icon'        => 'nullable|string|max:10',
            'description'      => 'nullable|string|max:2000',
            'location'         => 'nullable|string|max:255',
            'achievement_date' => 'nullable|date',
            'date_label'       => 'nullable|string|max:60',
            'medals_gold'      => 'nullable|integer|min:0',
            'medals_silver'    => 'nullable|integer|min:0',
            'medals_bronze'    => 'nullable|integer|min:0',
            'bouts_count'      => 'nullable|integer|min:0',
            'wins_count'       => 'nullable|integer|min:0',
            'category'         => 'nullable|string|max:255',
            'chips'            => 'nullable|string',
            'athletes'         => 'nullable|string',
            'tag'              => 'required|string|max:60',
            'tag_icon'         => 'nullable|string|max:60',
            'bg_from'          => 'nullable|string|max:20',
            'bg_to'            => 'nullable|string|max:20',
            'status'           => 'required|in:active,inactive',
            'sort_order'       => 'nullable|integer|min:0',
        ]);

        $data = $request->only([
            'title', 'short_title', 'type_icon', 'description',
            'location', 'achievement_date', 'date_label',
            'medals_gold', 'medals_silver', 'medals_bronze',
            'bouts_count', 'wins_count', 'category',
            'tag', 'tag_icon', 'bg_from', 'bg_to', 'status', 'sort_order',
        ]);
        $data['chips']    = $request->chips    ? json_decode($request->chips, true)    : null;
        $data['athletes'] = $request->athletes ? json_decode($request->athletes, true) : null;

        $kept     = json_decode($request->input('keep_extra_images', '[]'), true) ?: [];
        $newExtra = $this->saveAchievementBase64Images($request->input('achievement_images_base64', []), $club->id);
        $data['images'] = array_merge($kept, $newExtra) ?: null;

        // If image_path is no longer in the keep list, delete and clear it
        if ($achievement->image_path && !in_array($achievement->image_path, $kept)) {
            Storage::disk('public')->delete($achievement->image_path);
            $data['image_path'] = null;
        }

        $achievement->update($data);

        return back()->with('success', 'Achievement updated successfully.');
    }

    public function destroyAchievement(Tenant $club, ClubAchievement $achievement)
    {
        $this->authorizeClub($club);
        abort_if($achievement->tenant_id !== $club->id, 403);

        foreach ($achievement->images ?? [] as $imgPath) {
            Storage::disk('public')->delete($imgPath);
        }
        if ($achievement->image_path) {
            Storage::disk('public')->delete($achievement->image_path);
        }
        $achievement->delete();

        return response()->json(['success' => true, 'message' => 'Achievement deleted successfully.']);
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
                'id'      => $instructor->id,
                'user_id' => $instructor->user_id,
                'name'    => $instructor->user?->full_name ?? $instructor->user?->name ?? 'Unknown',
                'image'   => $instructor->user?->profile_picture ?? null,
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
                $facilityId = $schedule['facilityId'] ?? null;
                $facilityName = $schedule['facilityName'] ?? null;

                foreach ($days as $day) {
                    // Days can be strings or objects {value, name} from ScheduleTimePicker
                    $dayValue = is_array($day) ? ($day['value'] ?? $day['name'] ?? '') : $day;
                    $activitySchedules[$activityId][] = [
                        'day' => $dayValue,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'facility_id' => $facilityId,
                        'facility_name' => $facilityName,
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
                $facilityId = $schedule['facilityId'] ?? null;
                $facilityName = $schedule['facilityName'] ?? null;

                foreach ($days as $day) {
                    // Days can be strings or objects {value, name} from ScheduleTimePicker
                    $dayValue = is_array($day) ? ($day['value'] ?? $day['name'] ?? '') : $day;
                    $activitySchedules[$activityId][] = [
                        'day' => $dayValue,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'facility_id' => $facilityId,
                        'facility_name' => $facilityName,
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
        $subscriptions = ClubMemberSubscription::where('tenant_id', $clubId)
            ->with('package')
            ->get()
            ->groupBy('user_id');
        return view('admin.club.members.index', compact('club', 'members', 'packages', 'subscriptions'));
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
            'pending' => \App\Models\ClubMemberSubscription::where('tenant_id', $clubId)
                ->where('payment_status', 'unpaid')
                ->sum('amount_due'),
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
            'maps_url' => 'nullable|url|max:500',
            'logo' => 'nullable',
            'favicon' => 'nullable',
            'cover_image' => 'nullable',
            'settings' => 'nullable|array',
            'social_links' => 'nullable|array',
            'social_links.*.platform' => 'required_with:social_links.*.url|string',
            'social_links.*.url' => 'required_with:social_links.*.platform|url',
        ]);

        $data = $request->only([
            'club_name', 'slogan', 'description', 'enrollment_fee',
            'commercial_reg_number', 'vat_reg_number', 'vat_percentage',
            'email', 'country', 'currency', 'timezone', 'slug', 'address',
            'gps_lat', 'gps_long', 'maps_url', 'owner_name', 'owner_email'
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

        // Sync social links
        if ($request->has('social_links')) {
            $club->socialLinks()->delete();
            foreach ($request->social_links as $link) {
                if (!empty($link['platform']) && !empty($link['url'])) {
                    $club->socialLinks()->create([
                        'platform' => $link['platform'],
                        'url' => $link['url'],
                    ]);
                }
            }
        } else {
            // Tab was submitted but no links — clear them all
            $club->socialLinks()->delete();
        }

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
        $now = now();
        $start = $now->copy()->subMonths(11)->startOfMonth();

        $rows = ClubTransaction::where('tenant_id', $clubId)
            ->where('transaction_date', '>=', $start)
            ->selectRaw("strftime('%Y-%m', transaction_date) as month_key, type, SUM(amount) as total")
            ->groupBy('month_key', 'type')
            ->get()
            ->groupBy('month_key');

        $data = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = $now->copy()->subMonths($i);
            $key = $date->format('Y-m');
            $monthRows = $rows->get($key, collect());

            $income   = (float) $monthRows->firstWhere('type', 'income')?->total ?? 0;
            $expenses = (float) $monthRows->firstWhere('type', 'expense')?->total ?? 0;
            $refunds  = (float) $monthRows->firstWhere('type', 'refund')?->total ?? 0;

            $data[] = [
                'month'    => $date->format('M'),
                'income'   => $income,
                'expenses' => $expenses,
                'profit'   => $income - $expenses - $refunds,
            ];
        }

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

    /**
     * Create a new platform user and immediately transfer ownership to them.
     */
    public function createOwner(Request $request, Tenant $club)
    {
        $this->authorizeClub($club);

        $request->validate([
            'full_name'   => 'required|string|max:255',
            'email'       => 'nullable|email',
            'gender'      => 'required|in:m,f',
            'birthdate'   => 'required|date',
            'nationality' => 'required|string|max:100',
            'password'    => 'required|string|min:8',
        ]);

        // Restore soft-deleted user if email matches, otherwise create new
        $newOwner = null;
        if ($request->filled('email')) {
            $newOwner = User::withTrashed()->where('email', $request->email)->first();
        }

        if ($newOwner && $newOwner->trashed()) {
            $newOwner->restore();
            $newOwner->update([
                'full_name'   => $request->full_name,
                'name'        => $request->full_name,
                'password'    => bcrypt($request->password),
                'gender'      => $request->gender,
                'birthdate'   => $request->birthdate,
                'nationality' => $request->nationality,
                'blood_type'  => $request->blood_type,
                'mobile'      => $request->mobile ? ['code' => $request->mobile_code ?? '+973', 'number' => $request->mobile] : null,
            ]);
        } elseif ($newOwner) {
            // Active user with same email — block creation
            return response()->json(['success' => false, 'message' => 'An active account with this email already exists. Use "Link Existing Member" instead.'], 422);
        } else {
            $newOwner = User::create([
                'full_name'   => $request->full_name,
                'name'        => $request->full_name,
                'email'       => $request->email,
                'password'    => bcrypt($request->password),
                'gender'      => $request->gender,
                'birthdate'   => $request->birthdate,
                'nationality' => $request->nationality,
                'blood_type'  => $request->blood_type,
                'mobile'      => $request->mobile ? ['code' => $request->mobile_code ?? '+973', 'number' => $request->mobile] : null,
            ]);
        }

        $oldOwner = $club->owner;

        // Point club to new owner
        $club->update(['owner_user_id' => $newOwner->id]);

        // Give old owner club-admin role
        if ($oldOwner && $oldOwner->id !== $newOwner->id) {
            $alreadyAdmin = \DB::table('user_roles')
                ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                ->where('user_roles.user_id', $oldOwner->id)
                ->where('user_roles.tenant_id', $club->id)
                ->where('roles.slug', 'club-admin')
                ->exists();
            if (!$alreadyAdmin) {
                $oldOwner->assignRole('club-admin', $club->id);
            }
        }

        // Give new owner club-admin role
        $newOwner->assignRole('club-admin', $club->id);

        // Add new owner as free club member
        ClubMemberSubscription::create([
            'tenant_id'      => $club->id,
            'type'           => 'owner',
            'user_id'        => $newOwner->id,
            'package_id'     => null,
            'start_date'     => now()->toDateString(),
            'end_date'       => null,
            'status'         => 'active',
            'payment_status' => 'paid',
            'amount_paid'    => 0,
            'amount_due'     => 0,
            'notes'          => 'Owner membership',
        ]);

        // Ensure owner appears in the members list
        Membership::firstOrCreate(
            ['tenant_id' => $club->id, 'user_id' => $newOwner->id],
            ['status' => 'active']
        );

        return response()->json([
            'success'  => true,
            'message'  => 'New owner account created and ownership transferred successfully.',
            'redirect' => route('admin.club.details', $club->slug),
        ]);
    }

    /**
     * Transfer club ownership to an existing member or a newly created user.
     */
    public function transferOwnership(Request $request, Tenant $club)
    {
        $this->authorizeClub($club);

        $mode = $request->input('mode'); // 'existing' or 'new'

        if ($mode === 'existing') {
            $request->validate(['user_id' => 'required|integer|exists:users,id']);
            $newOwner = User::findOrFail($request->user_id);
        } else {
            $request->validate([
                'full_name' => 'required|string|max:255',
                'email'     => 'required|email|unique:users,email',
                'password'  => 'required|string|min:8',
            ]);
            $newOwner = User::create([
                'full_name' => $request->full_name,
                'name'      => $request->full_name,
                'email'     => $request->email,
                'password'  => bcrypt($request->password),
                'gender'    => 'm',
            ]);
        }

        $oldOwner = $club->owner;

        // 1. Point club to new owner
        $club->update(['owner_user_id' => $newOwner->id]);

        // 2. Give old owner club-admin role (if they exist and aren't already club-admin)
        if ($oldOwner && $oldOwner->id !== $newOwner->id) {
            $alreadyAdmin = \DB::table('user_roles')
                ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                ->where('user_roles.user_id', $oldOwner->id)
                ->where('user_roles.tenant_id', $club->id)
                ->where('roles.slug', 'club-admin')
                ->exists();

            if (!$alreadyAdmin) {
                $oldOwner->assignRole('club-admin', $club->id);
            }
        }

        // 3. Give new owner club-admin role
        $alreadyAdmin = \DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $newOwner->id)
            ->where('user_roles.tenant_id', $club->id)
            ->where('roles.slug', 'club-admin')
            ->exists();

        if (!$alreadyAdmin) {
            $newOwner->assignRole('club-admin', $club->id);
        }

        // 4. Add new owner as a free club member (no package)
        $alreadyOwner = ClubMemberSubscription::where('tenant_id', $club->id)
            ->where('user_id', $newOwner->id)
            ->where('type', 'owner')
            ->exists();

        if (!$alreadyOwner) {
            ClubMemberSubscription::create([
                'tenant_id'      => $club->id,
                'type'           => 'owner',
                'user_id'        => $newOwner->id,
                'package_id'     => null,
                'start_date'     => now()->toDateString(),
                'end_date'       => null,
                'status'         => 'active',
                'payment_status' => 'paid',
                'amount_paid'    => 0,
                'amount_due'     => 0,
                'notes'          => 'Owner membership',
            ]);
        }

        // Ensure owner appears in the members list
        Membership::firstOrCreate(
            ['tenant_id' => $club->id, 'user_id' => $newOwner->id],
            ['status' => 'active']
        );

        return response()->json([
            'success' => true,
            'message' => 'Ownership transferred successfully.',
            'owner'   => [
                'name'   => $newOwner->full_name ?? $newOwner->name,
                'email'  => $newOwner->email,
                'mobile' => $newOwner->formatted_mobile ?? '',
            ],
        ]);
    }
}
