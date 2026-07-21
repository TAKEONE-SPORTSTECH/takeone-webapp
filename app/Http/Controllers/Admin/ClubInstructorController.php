<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInstructorRequest;
use App\Http\Requests\Admin\UpdateInstructorRequest;
use App\Http\Requests\UploadImageRequest;
use App\Models\ClubInstructor;
use App\Models\ClubRecurringExpense;
use App\Models\ClubTransaction;
use App\Models\Role;
use App\Models\SkillAcquisition;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RecurringExpenseService;
use App\Support\ClubCache;
use App\Traits\HandlesClubAuthorization;
use App\Traits\PersistsTranslations;
use App\Traits\StoresBase64Images;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ClubInstructorController extends Controller
{
    use HandlesClubAuthorization, PersistsTranslations, StoresBase64Images;

    public function instructors(Tenant $club)
    {
        $this->authorizeClub($club);
        $instructors = ClubInstructor::where('tenant_id', $club->id)
            ->where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')
            ->with('user')->get();

        // Every package class+schedule slot in this club, with its current assignee.
        $packageSlots = $this->clubPackageSlots($club->id);
        $slotCountByInstructor = $packageSlots->whereNotNull('instructor_id')
            ->groupBy('instructor_id')->map->count();

        // Assignable roles for the "Manage Access" action in the 3-dot menu — every
        // role a club-admin may assign (excludes the platform super-admin role, the
        // implicit "member" default, and per-member custom-permission pseudo-roles).
        $availableRoles = Role::whereNotIn('slug', ['super-admin', 'member'])
            ->where('slug', 'not like', 'member-%')
            ->orderBy('id')->get(['id', 'slug', 'name']);

        return view(\App\Support\ClubView::pick('instructors'),
            compact('club', 'instructors', 'packageSlots', 'slotCountByInstructor', 'availableRoles'));
    }

    /**
     * Persist a new rank order. `order` is an array of instructor ids, top (rank 1) first.
     */
    public function reorderInstructors(Request $request, Tenant $club)
    {
        $this->authorizeClub($club);

        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer',
        ]);

        foreach (array_values($validated['order']) as $position => $instructorId) {
            ClubInstructor::where('tenant_id', $club->id)->where('id', $instructorId)
                ->update(['sort_order' => $position]);
        }

        return response()->json(['success' => true]);
    }

    public function storeInstructor(StoreInstructorRequest $request, Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;
        $creationType = $request->input('creation_type', 'new');

        if ($creationType === 'new') {

            $user = User::create([
                'name' => $request->name,
                'full_name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt($request->password),
                'mobile' => ($request->country_code ?? '+973').$request->phone,
                'gender' => $request->gender,
                'birthdate' => $request->birthdate,
                'nationality' => $request->nationality,
            ]);

            if ($request->filled('photo') && str_starts_with($request->input('photo'), 'data:image')) {
                $photoPath = $this->storeBase64Image($request->input('photo'), 'users/'.$user->id, 'profile_'.time());
                $user->update(['profile_picture' => $photoPath]);
            } elseif ($request->hasFile('photo')) {
                $photoPath = $request->file('photo')->store('users/'.$user->id, 'public');
                $user->update(['profile_picture' => $photoPath]);
            }

            $userId = $user->id;
            $role = $request->specialty;
            $experienceYears = $request->experience;
            try {
                $skills = $request->skills ? json_decode($request->skills, true, 512, JSON_THROW_ON_ERROR) : [];
            } catch (\JsonException) {
                return back()->withErrors(['skills' => 'Invalid skills format.']);
            }
            $bio = $request->bio;

        } else {

            $userId = $request->selected_member_id;
            $role = $request->specialty_existing;
            $experienceYears = $request->experience_existing;
            try {
                $skills = $request->skills_existing ? json_decode($request->skills_existing, true, 512, JSON_THROW_ON_ERROR) : [];
            } catch (\JsonException) {
                return back()->withErrors(['skills' => 'Invalid skills format.']);
            }
            $bio = $request->bio_existing;
        }

        User::where('id', $userId)->update([
            'bio' => $bio ?: null,
            'skills' => ! empty($skills) ? $skills : null,
            'experience_years' => $experienceYears ?: null,
        ]);
        // Unified skills: mirror the admin-set certifications into provenance-backed
        // (verified) SkillAcquisition rows, which is what every profile now reads.
        $this->syncCertificationSkills((int) $userId, (int) $clubId, $skills ?: []);

        // `role` (the staff member's specialty/title) is NOT NULL — the specialty
        // field is optional in the form, so fall back to the staff type when blank.
        $role = trim((string) $role) ?: ucfirst($request->input('staff_type', 'instructor'));

        $instructor = ClubInstructor::create([
            'tenant_id' => $clubId,
            'user_id' => $userId,
            'role' => $role,
            'staff_type' => $request->input('staff_type', 'instructor'),
            'sort_order' => (int) ClubInstructor::where('tenant_id', $clubId)->max('sort_order') + 1,
        ] + $this->compensationData($request));

        $this->applyTranslations($instructor, $request);

        $this->assignPackageSlots($instructor, $request, $clubId);
        $this->syncInstructorRecurringExpense($instructor);

        ClubCache::flushStats($clubId);

        return back()->with('success', 'Instructor added successfully.');
    }

    public function updateInstructor(UpdateInstructorRequest $request, Tenant $club, ClubInstructor $instructor)
    {
        $this->authorizeClub($club);

        if ($instructor->tenant_id !== $club->id) {
            abort(403);
        }

        $staffType = $request->input('staff_type', $instructor->staff_type ?: 'instructor');
        $instructor->update([
            // role is NOT NULL — keep the existing value or fall back to staff type when blank.
            'role' => trim((string) $request->role) ?: ($instructor->role ?: ucfirst($staffType)),
            'staff_type' => $staffType,
        ] + $this->compensationData($request, $instructor));
        $this->applyTranslations($instructor, $request);
        $this->assignPackageSlots($instructor, $request, $club->id);
        $this->syncInstructorRecurringExpense($instructor);

        try {
            $skills = $request->skills ? json_decode($request->skills, true, 512, JSON_THROW_ON_ERROR) : null;
        } catch (\JsonException) {
            return back()->withErrors(['skills' => 'Invalid skills format.']);
        }
        $userUpdate = [
            'experience_years' => $request->experience ?: null,
            'skills' => ! empty($skills) ? $skills : null,
            'bio' => $request->bio ?: null,
        ];
        if ($request->filled('name')) {
            $userUpdate['name'] = $request->name;
            $userUpdate['full_name'] = $request->name;
        }
        $instructor->user->update($userUpdate);
        if ($skills !== null) {
            $this->syncCertificationSkills((int) $instructor->user_id, (int) $club->id, $skills ?: []);
        }

        if ($request->filled('photo') && str_starts_with($request->input('photo'), 'data:image')) {
            $photoPath = $this->storeBase64Image($request->input('photo'), 'users/'.$instructor->user_id, 'profile_'.time());
            $instructor->user->update(['profile_picture' => $photoPath]);
        } elseif ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('users/'.$instructor->user_id, 'public');
            $instructor->user->update(['profile_picture' => $photoPath]);
        }

        return back()->with('success', 'Instructor updated successfully.');
    }

    /**
     * Remove a staff member from the active roster. This is a soft-terminate, not a
     * delete: any money still owed for the unpaid stretch since their last wage
     * payment is posted as a one-time settlement expense, their recurring wage rule
     * is paused, and the record itself is kept (is_active = false) so wage history
     * and the settlement transaction stay linked and queryable.
     */
    public function destroyInstructor(Tenant $club, ClubInstructor $instructor)
    {
        $this->authorizeClub($club);

        if ($instructor->tenant_id !== $club->id) {
            abort(403);
        }

        $settlement = $this->calculateSettlement($instructor);

        if ($settlement['amount'] > 0) {
            $name = $instructor->user->full_name ?? $instructor->user->name ?? 'Staff member';

            ClubTransaction::create([
                'tenant_id' => $club->id,
                'user_id' => $instructor->user_id,
                'instructor_id' => $instructor->id,
                'type' => 'expense',
                'category' => 'salaries',
                'amount' => $settlement['amount'],
                'payment_method' => $instructor->recurringExpense?->payment_method ?? 'bank_transfer',
                'description' => "Final settlement — {$name} ({$settlement['days']} days)",
                'transaction_date' => now()->toDateString(),
            ]);
        }

        // Pause the wage rule and prorate this month's already-posted wage down to the
        // days actually worked, so leaving on the 5th doesn't cost the club a full month.
        ClubRecurringExpense::where('tenant_id', $club->id)
            ->where('instructor_id', $instructor->id)
            ->get()
            ->each(function (ClubRecurringExpense $rule) {
                $rule->update(['is_active' => false]);
                app(RecurringExpenseService::class)->stopForCurrentMonth($rule);
            });

        $instructor->update(['is_active' => false]);

        ClubCache::flushStats($club->id);

        $message = $settlement['amount'] > 0
            ? 'Staff member removed. Final settlement of '.$club->currency.' '.number_format($settlement['amount'], 2).' recorded.'
            : 'Staff member removed from club successfully.';

        return response()->json(['success' => true, 'message' => $message, 'settlement_amount' => $settlement['amount']]);
    }

    /**
     * Preview (no writes) the settlement that terminating this staff member would post,
     * so the confirm dialog can show the admin the amount before they commit.
     */
    public function terminationPreview(Tenant $club, ClubInstructor $instructor)
    {
        $this->authorizeClub($club);
        abort_if($instructor->tenant_id !== $club->id, 403);

        $settlement = $this->calculateSettlement($instructor);

        return response()->json([
            'success' => true,
            'settlement_amount' => $settlement['amount'],
            'days' => $settlement['days'],
            'daily_rate' => $settlement['daily_rate'],
            'currency' => $club->currency,
        ]);
    }

    public function uploadInstructorPhoto(UploadImageRequest $request, Tenant $club, $instructorId)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

        try {
            $instructor = ClubInstructor::where('tenant_id', $clubId)->with('user')->findOrFail($instructorId);

            if (! $instructor->user) {
                return response()->json(['success' => false, 'message' => 'Instructor has no linked user'], 400);
            }

            // Validate + store the base64 image with a server-assigned extension
            // (real MIME sniffed from the bytes; PHP/HTML/SVG rejected).
            $fullPath = $this->storeBase64Image($request->image, $request->folder, $request->filename);
            if ($fullPath === null) {
                return response()->json(['success' => false, 'message' => 'Invalid or unsupported image.'], 422);
            }

            if ($instructor->user->profile_picture && $instructor->user->profile_picture !== $fullPath && Storage::disk('public')->exists($instructor->user->profile_picture)) {
                Storage::disk('public')->delete($instructor->user->profile_picture);
            }

            $instructor->user->update(['profile_picture' => $fullPath]);

            return response()->json([
                'success' => true,
                'path' => $fullPath,
                'url' => asset('storage/'.$fullPath),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Normalise the volunteer/paid compensation fields from the request. `paid_since`
     * marks when this staff member actually started being paid — NOT their hire date —
     * so a volunteer converted to paid only owes settlement from that conversion point,
     * not retroactively from whenever they originally joined. Pass the pre-update
     * $instructor on edits so an already-paid staff member keeps their existing
     * Mirror an instructor's admin-set certification skills into verified,
     * provenance-backed SkillAcquisition rows (the single source every profile now
     * reads). Certifications are user-owned with no affiliation, attributed to this club.
     */
    private function syncCertificationSkills(int $userId, int $clubId, array $skills): void
    {
        $names = collect($skills)->map(fn ($s) => trim((string) $s))->filter()->unique()->values();

        // Drop this club's admin-managed certifications no longer in the list.
        SkillAcquisition::where('user_id', $userId)
            ->whereNull('club_affiliation_id')
            ->where('verification_method', 'club_confirm')
            ->where('verified_by_tenant_id', $clubId)
            ->whereNotIn('skill_name', $names->all())
            ->delete();

        foreach ($names as $name) {
            SkillAcquisition::firstOrCreate(
                ['user_id' => $userId, 'club_affiliation_id' => null, 'skill_name' => $name],
                [
                    'proficiency_level' => 'advanced', 'duration_months' => 1, 'icon' => 'bi-patch-check',
                    'verification_status' => SkillAcquisition::STATUS_VERIFIED, 'verification_method' => 'club_confirm',
                    'verified_by_tenant_id' => $clubId, 'verified_at' => now(),
                ],
            );
        }
    }

    /**
     * paid_since instead of it being reset on every save.
     */
    private function compensationData(Request $request, ?ClubInstructor $instructor = null): array
    {
        $paid = $request->input('compensation_type') === ClubInstructor::COMPENSATION_PAID;
        $wasPaid = $instructor?->compensation_type === ClubInstructor::COMPENSATION_PAID;

        $paidSince = match (true) {
            ! $paid => null,
            $wasPaid && $instructor->paid_since => $instructor->paid_since,
            default => now(),
        };

        return [
            'compensation_type' => $paid ? ClubInstructor::COMPENSATION_PAID : ClubInstructor::COMPENSATION_VOLUNTEER,
            'wage_amount' => $paid ? $request->input('wage_amount') : null,
            'wage_period' => $paid ? $request->input('wage_period') : null,
            'paid_since' => $paidSince,
        ];
    }

    /**
     * Every package class+schedule slot (a row in club_package_activities) for the club,
     * with package/activity names, a human schedule label, and the current assignee.
     */
    private function clubPackageSlots(int $clubId)
    {
        return DB::table('club_package_activities as pa')
            ->join('club_packages as p', 'p.id', '=', 'pa.package_id')
            ->join('club_activities as a', 'a.id', '=', 'pa.activity_id')
            ->leftJoin('club_instructors as ci', 'ci.id', '=', 'pa.instructor_id')
            ->leftJoin('users as u', 'u.id', '=', 'ci.user_id')
            ->where('p.tenant_id', $clubId)
            ->orderBy('p.name')->orderBy('a.name')
            ->get([
                'pa.id', 'pa.instructor_id', 'pa.schedule',
                'p.id as package_id', 'p.name as package_name',
                'a.id as activity_id', 'a.name as activity_name',
                'u.full_name as instructor_name',
            ])
            ->map(function ($row) {
                $row->schedule_label = $this->formatSlotSchedule($row->schedule);

                return $row;
            });
    }

    private function formatSlotSchedule(?string $json): ?string
    {
        try {
            $entries = json_decode($json ?? '[]', true, 512, JSON_THROW_ON_ERROR) ?: [];
        } catch (\JsonException) {
            $entries = [];
        }

        $parts = [];
        foreach ($entries as $e) {
            $day = ucfirst((string) ($e['day'] ?? ''));
            $start = substr((string) ($e['start_time'] ?? ''), 0, 5);
            $end = substr((string) ($e['end_time'] ?? ''), 0, 5);
            $label = trim($day.' '.(($start && $end) ? "{$start}–{$end}" : ''));
            if ($label !== '') {
                $parts[$label] = true;
            }
        }

        return $parts ? implode(', ', array_keys($parts)) : null;
    }

    /**
     * Assign this instructor to the selected package class/schedule slots. Selected slots get
     * instructor_id = this instructor (reassigning if needed); slots that were this instructor's
     * but are no longer selected get cleared. Mirrors the taught activities for consistency.
     * Only runs when the form actually submitted the package_slots field.
     */
    private function assignPackageSlots(ClubInstructor $instructor, Request $request, int $clubId): void
    {
        // Only instructors teach package classes — secretaries/operators/cleaners/
        // other staff must never hold a slot. Clear any stale assignment (e.g. a
        // former instructor converted to another staff type) and ignore whatever
        // was submitted, regardless of UI state.
        if (($instructor->staff_type ?: 'instructor') !== 'instructor') {
            DB::table('club_package_activities')
                ->where('instructor_id', $instructor->id)
                ->update(['instructor_id' => null, 'updated_at' => now()]);
            $instructor->activities()->detach();

            return;
        }

        if (! $request->has('package_slots')) {
            return;
        }

        $clubSlotIds = DB::table('club_package_activities as pa')
            ->join('club_packages as p', 'p.id', '=', 'pa.package_id')
            ->where('p.tenant_id', $clubId)
            ->pluck('pa.id');

        $selected = collect($request->input('package_slots', []))
            ->filter()->map(fn ($i) => (int) $i)
            ->intersect($clubSlotIds)->values();

        if ($selected->isNotEmpty()) {
            DB::table('club_package_activities')->whereIn('id', $selected)
                ->update(['instructor_id' => $instructor->id, 'updated_at' => now()]);

            $activityIds = DB::table('club_package_activities')->whereIn('id', $selected)
                ->pluck('activity_id')->unique()->all();
            $instructor->activities()->syncWithoutDetaching($activityIds);
        }

        // Clear slots that used to be this instructor's but were deselected.
        DB::table('club_package_activities')
            ->whereIn('id', $clubSlotIds)
            ->whereNotIn('id', $selected->isNotEmpty() ? $selected : [0])
            ->where('instructor_id', $instructor->id)
            ->update(['instructor_id' => null, 'updated_at' => now()]);
    }

    /**
     * Reflect a paid+monthly staff member's wage as a recurring expense rule, so the
     * existing daily `expenses:process-recurring` cron auto-posts it to the ledger
     * every month — no separate payroll scheduling needed. Keyed per instructor so
     * re-saving updates the same rule rather than duplicating it. Session/hourly
     * rates and volunteers have no fixed recurring figure; any existing rule for
     * them is paused (not deleted — keeps last_run_at history for settlement math).
     */
    private function syncInstructorRecurringExpense(ClubInstructor $instructor): void
    {
        $monthly = $instructor->monthlyWageCost();

        if ($monthly === null) {
            ClubRecurringExpense::where('tenant_id', $instructor->tenant_id)
                ->where('instructor_id', $instructor->id)
                ->get()
                ->each(function (ClubRecurringExpense $rule) {
                    $rule->update(['is_active' => false]);
                    app(RecurringExpenseService::class)->stopForCurrentMonth($rule);
                });

            return;
        }

        $name = $instructor->user->full_name ?? $instructor->user->name ?? 'Staff member';
        $existingDay = ClubRecurringExpense::where('tenant_id', $instructor->tenant_id)
            ->where('instructor_id', $instructor->id)
            ->value('day_of_month');

        $rule = ClubRecurringExpense::updateOrCreate(
            ['tenant_id' => $instructor->tenant_id, 'instructor_id' => $instructor->id],
            [
                'description' => ucfirst($instructor->staff_type ?: 'instructor').' wage — '.$name,
                'amount' => $monthly,
                'category' => 'salaries',
                'payment_method' => 'bank_transfer',
                'day_of_month' => $existingDay ?? ($instructor->paid_since ?? $instructor->created_at)->day,
                'is_active' => true,
            ]
        );

        // A wage counts against the month it covers, so post it now rather than waiting
        // for the due day — otherwise the club's net looks profitable until payday.
        app(RecurringExpenseService::class)->postForCurrentMonth($rule);
    }

    /**
     * Pro-rated payout still owed to a paid+monthly staff member, for the stretch of
     * time since their last posted wage payment (or since they started being paid,
     * if never paid yet). Deliberately uses `paid_since`, NOT the hire date — a
     * volunteer converted to paid should only be owed settlement from the point
     * they actually started being paid, not retroactively for their entire
     * volunteer tenure. Staff who aren't paid-monthly (volunteer, session/hourly)
     * have nothing automatically owed since there's no fixed recurring figure to
     * prorate.
     *
     * @return array{amount: float, days: int, daily_rate: float}
     */
    private function calculateSettlement(ClubInstructor $instructor): array
    {
        if ($instructor->monthlyWageCost() === null) {
            return ['amount' => 0.0, 'days' => 0, 'daily_rate' => 0.0];
        }

        $recurring = $instructor->recurringExpense;

        // This month's wage is already in the ledger (posted at the start of the month
        // and pro-rated down to the last worked day on termination), so there is
        // nothing left to settle — settling again would pay them twice.
        if ($recurring && app(RecurringExpenseService::class)->postedThisMonth($recurring)) {
            return ['amount' => 0.0, 'days' => 0, 'daily_rate' => 0.0];
        }

        $periodStart = $recurring?->last_run_at
            ? \Carbon\Carbon::parse($recurring->last_run_at)->addDay()->startOfDay()
            : ($instructor->paid_since ?? $instructor->created_at)->copy()->startOfDay();

        $today = now()->startOfDay();
        $days = $periodStart->greaterThan($today) ? 0 : $periodStart->diffInDays($today) + 1;
        $dailyRate = (float) $instructor->wage_amount / now()->daysInMonth;
        $amount = round($dailyRate * $days, 2);

        return ['amount' => $amount, 'days' => $days, 'daily_rate' => round($dailyRate, 2)];
    }
}
