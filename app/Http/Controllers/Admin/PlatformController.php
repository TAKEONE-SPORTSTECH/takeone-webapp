<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Models\HealthRecord;
use App\Models\Invoice;
use App\Models\TournamentEvent;
use App\Models\Goal;
use App\Models\Attendance;
use App\Models\ClubAffiliation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class PlatformController extends Controller
{
    /**
     * Display the platform admin dashboard.
     */
    public function index()
    {
        // Get all clubs with counts
        $clubs = Tenant::with(['owner'])
            ->withCount(['members', 'packages', 'instructors'])
            ->latest()
            ->get();

        $clubsCount = $clubs->count();

        return view('admin.platform.index', compact('clubs', 'clubsCount'));
    }

    /**
     * Display all clubs management page.
     */
    public function clubs(Request $request)
    {
        $search = $request->input('search');

        $clubs = Tenant::with(['owner', 'members'])
            ->withCount(['members', 'packages', 'instructors'])
            ->when($search, function ($query, $search) {
                $query->where('club_name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(12);

        return view('admin.platform.clubs.index', compact('clubs', 'search'));
    }

    /**
     * Display all members management page.
     */
    public function members(Request $request)
    {
        $search = $request->input('search');

        $members = User::with(['memberClubs', 'dependents'])
            ->withCount('memberClubs')
            ->when($search, function ($query, $search) {
                $query->where('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('nationality', 'like', "%{$search}%")
                    ->orWhereRaw("JSON_EXTRACT(mobile, '$.number') LIKE ?", ["%{$search}%"]);
            })
            ->latest()
            ->paginate(20);

        return view('admin.platform.members.index', compact('members', 'search'));
    }

    /**
     * Show create club form.
     */
    public function createClub()
    {
        $users = User::orderBy('full_name')->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'mobile' => $user->mobile_formatted,
                'profile_picture' => $user->profile_picture
                    ? asset('storage/' . $user->profile_picture)
                    : null,
            ];
        });
        return view('admin.platform.clubs.add', compact('users'));
    }

    /**
     * Store a new club.
     */
    public function storeClub(Request $request)
    {
        $validated = $request->validate([
            'owner_user_id' => 'required|exists:users,id',
            'club_name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:tenants,slug',
            'email' => 'nullable|email',
            'phone_code' => 'nullable|string',
            'phone_number' => 'nullable|string',
            'currency' => 'nullable|string|max:3',
            'timezone' => 'nullable|string',
            'country' => 'nullable|string',
            'address' => 'nullable|string',
            'gps_lat' => 'nullable|numeric|between:-90,90',
            'gps_long' => 'nullable|numeric|between:-180,180',
            'logo' => 'nullable',
            'cover_image' => 'nullable',
        ]);

        // Handle phone as JSON
        if ($request->filled('phone_code') && $request->filled('phone_number')) {
            $validated['phone'] = [
                'code' => $request->phone_code,
                'number' => $request->phone_number,
            ];
        }

        // Handle logo - base64 from cropper (form mode)
        if ($request->filled('logo') && str_starts_with($request->logo, 'data:image')) {
            $imageData = $request->logo;
            $imageParts = explode(";base64,", $imageData);
            $imageTypeAux = explode("image/", $imageParts[0]);
            $extension = $imageTypeAux[1];
            $imageBinary = base64_decode($imageParts[1]);

            $folder = $request->input('logo_folder', 'clubs/logos');
            $filename = $request->input('logo_filename', 'logo_' . time());
            $fullPath = $folder . '/' . $filename . '.' . $extension;

            Storage::disk('public')->put($fullPath, $imageBinary);
            $validated['logo'] = $fullPath;
        }
        // Handle logo - traditional file upload
        elseif ($request->hasFile('logo')) {
            $validated['logo'] = $request->file('logo')->store('clubs/logos', 'public');
        } else {
            unset($validated['logo']);
        }

        // Handle cover image - base64 from cropper (form mode)
        if ($request->filled('cover_image') && str_starts_with($request->cover_image, 'data:image')) {
            $imageData = $request->cover_image;
            $imageParts = explode(";base64,", $imageData);
            $imageTypeAux = explode("image/", $imageParts[0]);
            $extension = $imageTypeAux[1];
            $imageBinary = base64_decode($imageParts[1]);

            $folder = $request->input('cover_image_folder', 'clubs/covers');
            $filename = $request->input('cover_image_filename', 'cover_' . time());
            $fullPath = $folder . '/' . $filename . '.' . $extension;

            Storage::disk('public')->put($fullPath, $imageBinary);
            $validated['cover_image'] = $fullPath;
        }
        // Handle cover image - traditional file upload
        elseif ($request->hasFile('cover_image')) {
            $validated['cover_image'] = $request->file('cover_image')->store('clubs/covers', 'public');
        } else {
            unset($validated['cover_image']);
        }

        $club = Tenant::create($validated);

        // Assign club-admin role to owner
        $owner = User::find($validated['owner_user_id']);
        $owner->assignRole('club-admin', $club->id);

        return redirect()->route('admin.platform.clubs')
            ->with('success', 'Club created successfully!');
    }

    /**
     * Show edit club form.
     */
    public function editClub(Tenant $club)
    {
        $users = User::orderBy('full_name')->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'mobile' => $user->mobile_formatted,
                'profile_picture' => $user->profile_picture
                    ? asset('storage/' . $user->profile_picture)
                    : null,
            ];
        });
        return view('admin.platform.clubs.edit', compact('club', 'users'));
    }

    /**
     * Update a club.
     */
    public function updateClub(Request $request, Tenant $club)
    {
        $validated = $request->validate([
            'owner_user_id' => 'required|exists:users,id',
            'club_name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:tenants,slug,' . $club->id,
            'email' => 'nullable|email',
            'phone_code' => 'nullable|string',
            'phone_number' => 'nullable|string',
            'currency' => 'nullable|string|max:3',
            'timezone' => 'nullable|string',
            'country' => 'nullable|string',
            'address' => 'nullable|string',
            'gps_lat' => 'nullable|numeric|between:-90,90',
            'gps_long' => 'nullable|numeric|between:-180,180',
            'logo' => 'nullable|image|max:2048',
            'cover_image' => 'nullable|image|max:2048',
        ]);

        // Handle phone as JSON
        if ($request->filled('phone_code') && $request->filled('phone_number')) {
            $validated['phone'] = [
                'code' => $request->phone_code,
                'number' => $request->phone_number,
            ];
        }

        // Handle logo upload
        if ($request->hasFile('logo')) {
            // Delete old logo
            if ($club->logo) {
                Storage::disk('public')->delete($club->logo);
            }
            $validated['logo'] = $request->file('logo')->store('clubs/logos', 'public');
        }

        // Handle cover image upload
        if ($request->hasFile('cover_image')) {
            // Delete old cover
            if ($club->cover_image) {
                Storage::disk('public')->delete($club->cover_image);
            }
            $validated['cover_image'] = $request->file('cover_image')->store('clubs/covers', 'public');
        }

        $club->update($validated);

        return redirect()->route('admin.platform.clubs')
            ->with('success', 'Club updated successfully!');
    }

    /**
     * Delete a club.
     */
    public function destroyClub(Tenant $club)
    {
        // Delete associated files
        if ($club->logo) {
            Storage::disk('public')->delete($club->logo);
        }
        if ($club->cover_image) {
            Storage::disk('public')->delete($club->cover_image);
        }
        if ($club->favicon) {
            Storage::disk('public')->delete($club->favicon);
        }

        // Delete club (cascade will handle related records)
        $club->delete();

        return redirect()->route('admin.platform.clubs')
            ->with('success', 'Club deleted successfully!');
    }

    /**
     * Display database backup page.
     */
    public function backup()
    {
        return view('admin.platform.backup.index');
    }

    /**
     * Download database backup as JSON.
     */
    public function downloadBackup()
    {
        $tables = [
            'users',
            'user_relationships',
            'tenants',
            'memberships',
            'invoices',
            'roles',
            'permissions',
            'role_permission',
            'user_roles',
            'club_facilities',
            'club_instructors',
            'club_activities',
            'club_packages',
            'club_package_activities',
            'club_member_subscriptions',
            'club_transactions',
            'club_gallery_images',
            'club_social_links',
            'club_bank_accounts',
            'club_messages',
            'club_reviews',
            'health_records',
            'tournament_events',
            'performance_results',
            'goals',
            'attendance_records',
            'club_affiliations',
            'skill_acquisitions',
        ];

        $backup = [];
        foreach ($tables as $table) {
            $backup[$table] = DB::table($table)->get()->toArray();
        }

        $filename = 'takeone_backup_' . date('Y-m-d_H-i-s') . '.json';

        return response()->json($backup, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Restore database from JSON backup.
     */
    public function restoreBackup(Request $request)
    {
        $request->validate([
            'backup_file' => 'required|file|mimes:json',
        ]);

        $file = $request->file('backup_file');
        $content = file_get_contents($file->getRealPath());
        $backup = json_decode($content, true);

        if (!$backup) {
            return back()->with('error', 'Invalid backup file format.');
        }

        DB::beginTransaction();
        try {
            // Disable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            foreach ($backup as $table => $records) {
                // Truncate table
                DB::table($table)->truncate();

                // Insert records in chunks
                if (!empty($records)) {
                    $chunks = array_chunk($records, 100);
                    foreach ($chunks as $chunk) {
                        DB::table($table)->insert($chunk);
                    }
                }
            }

            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            DB::commit();

            return back()->with('success', 'Database restored successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            return back()->with('error', 'Restore failed: ' . $e->getMessage());
        }
    }

    /**
     * Export all authentication users.
     */
    public function exportAuthUsers()
    {
        $users = User::select('id', 'full_name', 'email', 'password', 'created_at')
            ->get()
            ->toArray();

        $filename = 'auth_users_' . date('Y-m-d_H-i-s') . '.json';

        return response()->json($users, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Upload club logo via AJAX (cropper).
     */
    public function uploadClubLogo(Request $request, Tenant $club)
    {
        $request->validate([
            'image' => 'required',
            'folder' => 'required|string',
            'filename' => 'required|string',
        ]);

        try {
            // Handle base64 image from cropper
            $imageData = $request->image;
            $imageParts = explode(";base64,", $imageData);
            $imageTypeAux = explode("image/", $imageParts[0]);
            $extension = $imageTypeAux[1];
            $imageBinary = base64_decode($imageParts[1]);

            $folder = trim($request->folder, '/');
            $fileName = $request->filename . '.' . $extension;
            $fullPath = $folder . '/' . $fileName;

            // Delete old logo if exists
            if ($club->logo && Storage::disk('public')->exists($club->logo)) {
                Storage::disk('public')->delete($club->logo);
            }

            // Store in the public disk
            Storage::disk('public')->put($fullPath, $imageBinary);

            // Update club's logo field
            $club->update(['logo' => $fullPath]);

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
     * Upload club cover image via AJAX (cropper).
     */
    public function uploadClubCover(Request $request, Tenant $club)
    {
        $request->validate([
            'image' => 'required',
            'folder' => 'required|string',
            'filename' => 'required|string',
        ]);

        try {
            // Handle base64 image from cropper
            $imageData = $request->image;
            $imageParts = explode(";base64,", $imageData);
            $imageTypeAux = explode("image/", $imageParts[0]);
            $extension = $imageTypeAux[1];
            $imageBinary = base64_decode($imageParts[1]);

            $folder = trim($request->folder, '/');
            $fileName = $request->filename . '.' . $extension;
            $fullPath = $folder . '/' . $fileName;

            // Delete old cover if exists
            if ($club->cover_image && Storage::disk('public')->exists($club->cover_image)) {
                Storage::disk('public')->delete($club->cover_image);
            }

            // Store in the public disk
            Storage::disk('public')->put($fullPath, $imageBinary);

            // Update club's cover_image field
            $club->update(['cover_image' => $fullPath]);

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
     * Display the specified member's profile.
     */
    public function showMember($id)
    {
        $member = User::findOrFail($id);

        // Fetch health data
        $latestHealthRecord = $member->healthRecords()->latest('recorded_at')->first();
        $healthRecords = $member->healthRecords()->orderBy('recorded_at', 'desc')->paginate(10);
        $comparisonRecords = $member->healthRecords()->orderBy('recorded_at', 'desc')->take(2)->get();

        // Fetch invoices
        $invoices = Invoice::where('student_user_id', $member->id)
            ->orWhere('payer_user_id', $member->id)
            ->with(['student', 'tenant'])
            ->get();

        // Fetch tournament data
        $tournamentEvents = $member->tournamentEvents()
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
        $goals = $member->goals()->orderBy('created_at', 'desc')->get();
        $activeGoalsCount = $goals->where('status', 'active')->count();
        $completedGoalsCount = $goals->where('status', 'completed')->count();
        $successRate = $goals->count() > 0 ? round(($completedGoalsCount / $goals->count()) * 100) : 0;

        // Fetch attendance data
        $attendanceRecords = $member->attendanceRecords()->orderBy('session_datetime', 'desc')->get();
        $sessionsCompleted = $attendanceRecords->where('status', 'completed')->count();
        $noShows = $attendanceRecords->where('status', 'no_show')->count();
        $totalSessions = $attendanceRecords->count();
        $attendanceRate = $totalSessions > 0 ? round(($sessionsCompleted / $totalSessions) * 100, 1) : 0;

        // Fetch affiliations data
        $clubAffiliations = $member->clubAffiliations()
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

        // Add icon_class to media items
        $clubAffiliations->each(function($affiliation) {
            $affiliation->affiliationMedia->each(function($media) {
                $media->icon_class = $media->icon_class;
            });
        });

        // Calculate summary stats
        $totalAffiliations = $clubAffiliations->count();
        $distinctSkills = $clubAffiliations->flatMap->skillAcquisitions->pluck('skill_name')->unique()->count();
        $totalMembershipDuration = $clubAffiliations->sum('duration_in_months');

        // Get all unique skills
        $allSkills = $clubAffiliations->flatMap(function($affiliation) {
            return $affiliation->skillAcquisitions->pluck('skill_name');
        })->unique()->sort()->values();

        // Count total instructors
        $totalInstructors = $clubAffiliations->flatMap(function($affiliation) {
            return $affiliation->skillAcquisitions->pluck('instructor');
        })->filter()->unique('id')->count();

        // Create a mock relationship for the view
        $relationship = (object)[
            'dependent' => $member,
            'relationship_type' => 'admin_view',
            'guardian_user_id' => Auth::id(),
            'dependent_user_id' => $member->id,
        ];

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
            'user' => $member,
        ]);
    }

    /**
     * Show the form for editing a member.
     */
    public function editMember($id)
    {
        $member = User::findOrFail($id);

        // Create a mock relationship for the view
        $relationship = (object)[
            'dependent' => $member,
            'relationship_type' => 'admin_view',
            'guardian_user_id' => Auth::id(),
            'dependent_user_id' => $member->id,
            'is_billing_contact' => false,
        ];

        return view('family.edit', compact('relationship'));
    }

    /**
     * Update a member.
     */
    public function updateMember(Request $request, $id)
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
        ]);

        // Process social links
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
        ]);

        // Return JSON for AJAX requests
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Member updated successfully.'
            ]);
        }

        return redirect()->route('admin.platform.members.show', $id)
            ->with('success', 'Member updated successfully.');
    }

    /**
     * Delete a member.
     */
    public function destroyMember($id)
    {
        // Prevent deleting own account
        if (Auth::id() == $id) {
            return redirect()->back()
                ->with('error', 'You cannot delete your own account.');
        }

        $member = User::findOrFail($id);
        $memberName = $member->full_name;
        $member->delete();

        return redirect()->route('admin.platform.members')
            ->with('success', $memberName . ' has been removed successfully.');
    }

    /**
     * Upload member profile picture.
     */
    public function uploadMemberPicture(Request $request, $id)
    {
        $request->validate([
            'image' => 'required',
            'folder' => 'required|string',
            'filename' => 'required|string',
        ]);

        try {
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
     * Store a health record for a member.
     */
    public function storeMemberHealth(Request $request, $id)
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
     * Update a health record for a member.
     */
    public function updateMemberHealth(Request $request, $id, $recordId)
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
     * Store a tournament record for a member.
     */
    public function storeMemberTournament(Request $request, $id)
    {
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
}
