<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInstructorRequest;
use App\Http\Requests\Admin\UpdateInstructorRequest;
use App\Http\Requests\UploadImageRequest;
use App\Models\ClubInstructor;
use App\Models\ClubTransaction;
use App\Models\Tenant;
use App\Models\User;
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
            ->orderBy('sort_order')->orderBy('id')
            ->with('user')->get();

        // Every package class+schedule slot in this club, with its current assignee.
        $packageSlots = $this->clubPackageSlots($club->id);
        $slotCountByInstructor = $packageSlots->whereNotNull('instructor_id')
            ->groupBy('instructor_id')->map->count();

        return view(\App\Support\ClubView::pick('instructors'),
            compact('club', 'instructors', 'packageSlots', 'slotCountByInstructor'));
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

        $instructor = ClubInstructor::create([
            'tenant_id' => $clubId,
            'user_id' => $userId,
            'role' => $role,
            'sort_order' => (int) ClubInstructor::where('tenant_id', $clubId)->max('sort_order') + 1,
        ] + $this->compensationData($request));

        $this->applyTranslations($instructor, $request);

        $this->assignPackageSlots($instructor, $request, $clubId);
        $this->syncInstructorWageExpense($instructor);

        return back()->with('success', 'Instructor added successfully.');
    }

    public function updateInstructor(UpdateInstructorRequest $request, Tenant $club, ClubInstructor $instructor)
    {
        $this->authorizeClub($club);

        if ($instructor->tenant_id !== $club->id) {
            abort(403);
        }

        $instructor->update(['role' => $request->role] + $this->compensationData($request));
        $this->applyTranslations($instructor, $request);
        $this->assignPackageSlots($instructor, $request, $club->id);
        $this->syncInstructorWageExpense($instructor);

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

        if ($request->filled('photo') && str_starts_with($request->input('photo'), 'data:image')) {
            $photoPath = $this->storeBase64Image($request->input('photo'), 'users/'.$instructor->user_id, 'profile_'.time());
            $instructor->user->update(['profile_picture' => $photoPath]);
        } elseif ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('users/'.$instructor->user_id, 'public');
            $instructor->user->update(['profile_picture' => $photoPath]);
        }

        return back()->with('success', 'Instructor updated successfully.');
    }

    public function destroyInstructor(Tenant $club, ClubInstructor $instructor)
    {
        $this->authorizeClub($club);

        if ($instructor->tenant_id !== $club->id) {
            abort(403);
        }

        $instructor->delete();

        return response()->json(['success' => true, 'message' => 'Instructor removed from club successfully.']);
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
     * Normalise the volunteer/paid compensation fields from the request.
     */
    private function compensationData(Request $request): array
    {
        $paid = $request->input('compensation_type') === ClubInstructor::COMPENSATION_PAID;

        return [
            'compensation_type' => $paid ? ClubInstructor::COMPENSATION_PAID : ClubInstructor::COMPENSATION_VOLUNTEER,
            'wage_amount' => $paid ? $request->input('wage_amount') : null,
            'wage_period' => $paid ? $request->input('wage_period') : null,
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
     * Reflect a paid instructor's wage into the club's Financials as a monthly expense.
     * Keyed per instructor + month so re-saving updates rather than duplicates. Only a
     * monthly wage maps to a concrete recurring figure; session/hourly rates depend on
     * usage and are not auto-posted here. Future months would be created by a scheduled job.
     */
    private function syncInstructorWageExpense(ClubInstructor $instructor): void
    {
        $month = now()->format('Y-m');
        $ref = "wage:instructor:{$instructor->id}:{$month}";
        $monthly = $instructor->monthlyWageCost();

        if ($monthly === null) {
            ClubTransaction::where('tenant_id', $instructor->tenant_id)
                ->where('reference_number', $ref)
                ->delete();

            return;
        }

        $name = $instructor->user->full_name ?? $instructor->user->name ?? 'Instructor';

        ClubTransaction::updateOrCreate(
            ['tenant_id' => $instructor->tenant_id, 'reference_number' => $ref],
            [
                'user_id' => $instructor->user_id,
                'type' => 'expense',
                'category' => 'Instructor Wage',
                'amount' => $monthly,
                'payment_method' => 'cash',
                'description' => 'Monthly instructor wage — '.$name,
                'transaction_date' => now()->startOfMonth()->toDateString(),
            ]
        );
    }
}
