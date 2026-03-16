<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PackageRequest;
use App\Models\ClubActivity;
use App\Models\ClubFacility;
use App\Models\ClubInstructor;
use App\Models\ClubPackage;
use App\Models\Tenant;
use App\Traits\HandlesClubAuthorization;
use App\Traits\StoresBase64Images;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ClubPackageController extends Controller
{
    use HandlesClubAuthorization, StoresBase64Images;

    public function packages(Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId      = $club->id;
        $packages    = ClubPackage::where('tenant_id', $clubId)->with(['activities'])->get();
        $facilities  = ClubFacility::where('tenant_id', $clubId)->get();
        $activities  = ClubActivity::where('tenant_id', $clubId)->get();
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

    public function storePackage(PackageRequest $request, Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

        $data = [
            'tenant_id'        => $clubId,
            'name'             => $request->name,
            'description'      => $request->description,
            'price'            => $request->price,
            'duration_months'  => $request->duration_months,
            'gender'           => $request->gender_restriction ?? 'mixed',
            'age_min'          => $request->age_min,
            'age_max'          => $request->age_max,
            'is_active'        => true,
        ];

        if ($request->filled('image') && str_starts_with($request->input('image'), 'data:image')) {
            $data['cover_image'] = $this->storeBase64Image($request->input('image'), 'packages', 'package_' . time());
        } elseif ($request->hasFile('image')) {
            $data['cover_image'] = $request->file('image')->store('packages', 'public');
        }

        $package = ClubPackage::create($data);

        if ($request->schedules) {
            $package->activities()->sync($this->buildSyncData($request));
        }

        return back()->with('success', 'Package created successfully.');
    }

    public function updatePackage(PackageRequest $request, Tenant $club, $packageId)
    {
        $this->authorizeClub($club);
        $clubId  = $club->id;
        $package = ClubPackage::where('tenant_id', $clubId)->where('id', $packageId)->firstOrFail();

        $data = [
            'name'            => $request->name,
            'description'     => $request->description,
            'price'           => $request->price,
            'duration_months' => $request->duration_months,
            'gender'          => $request->gender_restriction ?? 'mixed',
            'age_min'         => $request->age_min,
            'age_max'         => $request->age_max,
        ];

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

        if ($request->schedules) {
            $package->activities()->sync($this->buildSyncData($request));
        } else {
            $package->activities()->detach();
        }

        return back()->with('success', 'Package updated successfully.');
    }

    public function destroyPackage(Tenant $club, $packageId)
    {
        $this->authorizeClub($club);
        $package = ClubPackage::where('tenant_id', $club->id)->where('id', $packageId)->firstOrFail();
        $package->delete();

        return back()->with('success', 'Package deleted successfully.');
    }

    private function buildSyncData(Request $request): array
    {
        try {
            $schedules          = json_decode($request->schedules ?? '[]', true, 512, JSON_THROW_ON_ERROR);
            $trainerAssignments = json_decode($request->trainer_assignments ?? '[]', true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $schedules          = [];
            $trainerAssignments = [];
        }

        $activitySchedules = [];
        foreach ($schedules as $schedule) {
            $activityId = $schedule['activityId'] ?? null;
            if (!$activityId) continue;

            $days        = $schedule['days']        ?? [];
            $startTime   = $schedule['startTime']   ?? '';
            $endTime     = $schedule['endTime']     ?? '';
            $facilityId  = $schedule['facilityId']  ?? null;
            $facilityName = $schedule['facilityName'] ?? null;

            foreach ($days as $day) {
                $dayValue = is_array($day) ? ($day['value'] ?? $day['name'] ?? '') : $day;
                $activitySchedules[$activityId][] = [
                    'day'           => $dayValue,
                    'start_time'    => $startTime,
                    'end_time'      => $endTime,
                    'facility_id'   => $facilityId,
                    'facility_name' => $facilityName,
                ];
            }
        }

        $syncData = [];
        foreach ($activitySchedules as $activityId => $scheduleEntries) {
            $syncData[$activityId] = [
                'instructor_id' => $trainerAssignments[$activityId] ?? null,
                'schedule'      => json_encode($scheduleEntries),
            ];
        }

        return $syncData;
    }
}
