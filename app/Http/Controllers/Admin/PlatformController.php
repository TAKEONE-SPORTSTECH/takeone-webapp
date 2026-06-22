<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RestoreBackupRequest;
use App\Http\Requests\Admin\StorePlatformMemberRequest;
use App\Http\Requests\HealthRecordRequest;
use App\Http\Requests\TournamentRequest;
use App\Http\Requests\UploadImageRequest;
use App\Models\Business;
use App\Models\ClubMemberSubscription;
use App\Models\Membership;
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
use Illuminate\Support\Facades\Hash;

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
     * Platform admin landing. Desktop keeps its existing behaviour (straight to
     * the clubs table); mobile gets a purpose-built dashboard with KPIs and
     * section navigation.
     */
    public function home(Request $request)
    {
        if (! $request->attributes->get('is_mobile')) {
            return redirect()->route('admin.platform.clubs');
        }

        $stats = [
            'clubs'             => Tenant::count(),
            'members'           => User::count(),
            'businesses'        => Business::count(),
            'businessesPending' => Business::where('status', Business::STATUS_PENDING)->count(),
        ];

        return view('admin.platform.mobile.index', compact('stats'));
    }

    /**
     * Display the audit log.
     */
    public function auditLog(Request $request)
    {
        $search   = $request->input('search');
        $logName  = $request->input('log_name');
        $event    = $request->input('event');
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');

        $logs = \Spatie\Activitylog\Models\Activity::with('causer')
            ->when($search, function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('subject_type', 'like', "%{$search}%")
                  ->orWhereHas('causer', fn($u) => $u->where('full_name', 'like', "%{$search}%")
                                                      ->orWhere('email', 'like', "%{$search}%"));
            })
            ->when($logName,  fn($q) => $q->where('log_name', $logName))
            ->when($event,    fn($q) => $q->where('event', $event))
            ->when($dateFrom, fn($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo,   fn($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $logNames = \Spatie\Activitylog\Models\Activity::distinct()->pluck('log_name')->filter()->sort()->values();

        $mobile = $request->attributes->get('is_mobile') && view()->exists('admin.platform.mobile.audit-log');

        return view($mobile ? 'admin.platform.mobile.audit-log' : 'admin.platform.audit-log.index', compact('logs', 'search', 'logName', 'event', 'dateFrom', 'dateTo', 'logNames'));
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

        $mobile = $request->attributes->get('is_mobile') && view()->exists('admin.platform.mobile.clubs');

        return view($mobile ? 'admin.platform.mobile.clubs' : 'admin.platform.clubs.index', compact('clubs', 'search'));
    }

    /**
     * Display all members management page.
     */
    public function members(Request $request)
    {
        $search = $request->input('search');

        $members = User::with(['memberClubs', 'dependents', 'guardians.guardian', 'latestHealthRecord'])
            ->withCount('memberClubs')
            ->when($search, function ($query, $search) {
                $query->where('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('nationality', 'like', "%{$search}%")
                    ->orWhere('gender', 'like', "%{$search}%")
                    ->orWhereRaw("JSON_EXTRACT(mobile, '$.number') LIKE ?", ["%{$search}%"]);
            })
            ->latest()
            ->paginate(20);

        $isMobile = (bool) $request->attributes->get('is_mobile');

        // AJAX search / pagination returns just the results region for in-place swap
        // (mobile gets its own compact card layout).
        if ($request->ajax()) {
            $resultsView = $isMobile && view()->exists('admin.platform.members._results-mobile')
                ? 'admin.platform.members._results-mobile'
                : 'admin.platform.members._results';

            return view($resultsView, compact('members', 'search'));
        }

        $mobile = $isMobile && view()->exists('admin.platform.mobile.members');

        return view($mobile ? 'admin.platform.mobile.members' : 'admin.platform.members.index', compact('members', 'search'));
    }

    public function memberPopup(User $user)
    {
        $membership = Membership::where('user_id', $user->id)->latest()->first();

        $subscriptions = ClubMemberSubscription::where('user_id', $user->id)
            ->where('type', 'regular')
            ->with(['package', 'package.tenant'])
            ->latest()
            ->get()
            ->map(function ($sub) {
                $clubName = $sub->package?->tenant?->name ?? 'N/A';
                return [
                    'id'             => $sub->id,
                    'package'        => ($sub->package?->name ?? 'N/A') . ' — ' . $clubName,
                    'currency'       => $sub->package?->currency ?? 'BHD',
                    'start_date'     => $sub->start_date?->format('M d, Y') ?? 'N/A',
                    'end_date'       => $sub->end_date?->format('M d, Y') ?? 'Ongoing',
                    'payment_status' => $sub->payment_status ?? 'pending',
                    'amount_due'     => number_format((float) ($sub->amount_due ?? 0), 2),
                    'amount_paid'    => number_format((float) ($sub->amount_paid ?? 0), 2),
                    'status'         => $sub->status,
                    'is_active'      => in_array($sub->status, ['active', 'pending']),
                    'has_proof'      => (bool) $sub->proof_of_payment,
                    'approve_url'    => $sub->package?->tenant
                        ? route('admin.club.subscriptions.approve-payment', [$sub->package->tenant->slug, $sub->id])
                        : null,
                    'proof_url'      => ($sub->proof_of_payment && $sub->package?->tenant)
                        ? route('admin.club.subscriptions.payment-proof', [$sub->package->tenant->slug, $sub->id])
                        : null,
                ];
            });

        $phone = is_array($user->mobile)
            ? trim(($user->mobile['code'] ?? '') . ' ' . ($user->mobile['number'] ?? ''))
            : ($user->mobile ?? '');

        return response()->json([
            'id'          => $user->id,
            'name'        => $user->full_name,
            'initial'     => mb_strtoupper(mb_substr($user->full_name ?? 'M', 0, 1, 'UTF-8'), 'UTF-8'),
            'has_picture' => (bool) $user->profile_picture,
            'picture_url' => $user->profile_picture
                ? asset('storage/' . $user->profile_picture) . '?v=' . $user->updated_at->timestamp
                : null,
            'gender'        => $user->gender ?? 'Male',
            'phone'         => $phone ?: 'N/A',
            'email'         => $user->email ?? 'N/A',
            'age'           => $user->age ? $user->age . ' years' : 'N/A',
            'since'         => $membership ? $membership->created_at->format('d/m/Y') : $user->created_at->format('d/m/Y'),
            'profile_url'     => route('member.show', $user->uuid),
            'subscriptions'   => $subscriptions,
            'context'         => 'platform',
            'enroll_data_url' => route('admin.platform.members.enroll-data', $user->id),
        ]);
    }

    public function memberEnrollData(User $user)
    {
        $clubs = Membership::where('user_id', $user->id)
            ->with('tenant')
            ->get()
            ->filter(fn($m) => $m->tenant !== null)
            ->map(fn($m) => [
                'id'           => $m->tenant->id,
                'name'         => $m->tenant->club_name,
                'slug'         => $m->tenant->slug,
                'packages_url' => route('admin.club.members.enroll-packages', [$m->tenant->slug, $user->id]),
                'enroll_url'   => route('admin.club.members.enroll', [$m->tenant->slug, $user->id]),
            ])
            ->values();

        return response()->json([
            'clubs'       => $clubs,
            'single_club' => $clubs->count() === 1,
        ]);
    }

    /**
     * Create a new platform member.
     */
    public function storeMember(StorePlatformMemberRequest $request)
    {

        $mobile = null;
        if ($request->filled('mobile_code') && $request->filled('mobile')) {
            $mobile = ['code' => $request->mobile_code, 'number' => $request->mobile];
        }

        $data = [
            'full_name'         => $request->full_name,
            'name'              => $request->full_name,
            'email'             => $request->email,
            'password'          => Hash::make($request->password),
            'gender'            => $request->gender,
            'birthdate'         => $request->birthdate,
            'nationality'       => $request->nationality,
            'blood_type'        => $request->blood_type,
            'mobile'            => $mobile,
            'marital_status'    => $request->marital_status,
            'motto'             => $request->motto,
            'email_verified_at' => null,
        ];

        $softDeleted = User::withTrashed()->where('email', $request->email)->whereNotNull('deleted_at')->first();
        if ($softDeleted) {
            $softDeleted->restore();
            $softDeleted->update($data);
            $user = $softDeleted;
        } else {
            $user = User::create($data);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'success'  => true,
            'message'  => 'Member created successfully!',
            'redirect' => route('admin.platform.members'),
        ]);
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
        $mobile = request()->attributes->get('is_mobile') && view()->exists('admin.platform.mobile.backup');

        return view($mobile ? 'admin.platform.mobile.backup' : 'admin.platform.backup.index');
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
    public function restoreBackup(RestoreBackupRequest $request)
    {

        $file    = $request->file('backup_file');
        $content = file_get_contents($file->getRealPath());

        try {
            $backup = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return back()->with('error', 'Invalid backup file format.');
        }

        if (empty($backup) || !is_array($backup)) {
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
    public function uploadClubLogo(UploadImageRequest $request, Tenant $club)
    {

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
    public function uploadClubCover(UploadImageRequest $request, Tenant $club)
    {

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
            'full_name'                  => 'required|string|max:255',
            'email'                      => 'nullable|email|max:255|unique:users,email,' . $id,
            'mobile_code'                => 'nullable|string|max:5',
            'mobile'                     => 'nullable|string|max:20',
            'gender'                     => 'required|in:Male,Female',
            'marital_status'             => 'nullable|in:single,married,divorced,widowed',
            'birthdate'                  => 'required|date',
            'blood_type'                 => 'nullable|string|max:10',
            'nationality'                => 'required|string|max:100',
            'social_links'               => 'nullable|array',
            'social_links.*.platform'    => 'required_with:social_links.*.url|string',
            'social_links.*.url'         => 'required_with:social_links.*.platform|url',
            'motto'                      => 'nullable|string|max:500',
            'emergency_contacts_json'    => 'nullable|string',
            'health_conditions_json'     => 'nullable|string',
            'documents_json'             => 'nullable|string',
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

        // Process the three JSON-encoded fields from the Medical & Contacts tab
        $emergencyContacts = collect(json_decode($request->input('emergency_contacts_json', '[]'), true) ?? [])
            ->filter(fn($c) => !empty($c['name']) || !empty($c['phone']))
            ->map(fn($c) => [
                'name'         => trim($c['name'] ?? ''),
                'relationship' => $c['relationship'] ?? '',
                'phone_code'   => $c['phone_code'] ?? '',
                'phone'        => trim($c['phone'] ?? ''),
            ])->values()->all();

        $healthConditions = collect(json_decode($request->input('health_conditions_json', '[]'), true) ?? [])
            ->filter(fn($c) => !empty($c['condition']))
            ->map(fn($c) => [
                'condition' => trim($c['condition']),
                'noted_at'  => $c['noted_at'] ?? now()->format('Y-m-d'),
                'notes'     => trim($c['notes'] ?? ''),
            ])->values()->all();

        $documents = collect(json_decode($request->input('documents_json', '[]'), true) ?? [])
            ->filter(fn($d) => !empty($d['type']) || !empty($d['number']))
            ->map(fn($d) => [
                'type'        => trim($d['type'] ?? ''),
                'number'      => trim($d['number'] ?? ''),
                'file_path'   => $d['file_path'] ?? null,
                'file_name'   => $d['file_name'] ?? null,
                'file_url'    => $d['file_url'] ?? null,
                'uploaded_at' => $d['uploaded_at'] ?? now()->format('Y-m-d'),
            ])->values()->all();

        $member = User::findOrFail($id);
        $member->update([
            'full_name'          => $validated['full_name'],
            'email'              => $validated['email'],
            'mobile'             => $mobile,
            'gender'             => $validated['gender'],
            'marital_status'     => $validated['marital_status'] ?? null,
            'birthdate'          => $validated['birthdate'],
            'blood_type'         => $validated['blood_type'],
            'nationality'        => $validated['nationality'],
            'social_links'       => $socialLinks,
            'motto'              => $validated['motto'],
            'emergency_contacts' => $emergencyContacts,
            'health_conditions'  => $healthConditions,
            'documents'          => $documents,
        ]);

        // Return JSON for AJAX requests
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Member updated successfully.',
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

        $ownedClubs = \App\Models\Tenant::where('owner_user_id', $member->id)->pluck('club_name');
        if ($ownedClubs->isNotEmpty()) {
            return redirect()->back()
                ->with('error', 'Cannot delete this account. They are the owner of the following club(s): ' . $ownedClubs->join(', ') . '. Transfer ownership first.');
        }

        $memberName = $member->full_name;
        $member->delete();

        return redirect()->route('admin.platform.members')
            ->with('success', $memberName . ' has been removed successfully.');
    }

    /**
     * Upload member profile picture.
     */
    public function uploadMemberPicture(UploadImageRequest $request, $id)
    {

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
    public function storeMemberHealth(HealthRecordRequest $request, $id)
    {
        $validated = $request->validated();

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
    public function updateMemberHealth(HealthRecordRequest $request, $id, $recordId)
    {
        $validated = $request->validated();

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
    public function storeMemberTournament(TournamentRequest $request, $id)
    {
        $validated = $request->validated();

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
}
