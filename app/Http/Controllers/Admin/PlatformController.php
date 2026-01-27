<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

        return view('admin.platform.clubs', compact('clubs', 'search'));
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

        return view('admin.platform.members', compact('members', 'search'));
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
        return view('admin.platform.create-club', compact('users'));
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
        return view('admin.platform.edit-club', compact('club', 'users'));
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
        return view('admin.platform.backup');
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
}
