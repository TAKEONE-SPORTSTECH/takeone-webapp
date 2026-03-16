<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreActivityRequest;
use App\Http\Requests\Admin\UpdateActivityRequest;
use App\Models\ClubActivity;
use App\Models\ClubFacility;
use App\Models\Tenant;
use App\Traits\HandlesClubAuthorization;
use App\Traits\StoresBase64Images;
use Illuminate\Support\Facades\Storage;

class ClubActivityController extends Controller
{
    use HandlesClubAuthorization, StoresBase64Images;

    public function activities(Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId     = $club->id;
        $activities = ClubActivity::where('tenant_id', $clubId)->with('facility')->get();
        $facilities = ClubFacility::where('tenant_id', $clubId)->get();
        return view('admin.club.activities.index', compact('club', 'activities', 'facilities'));
    }

    public function storeActivity(StoreActivityRequest $request, Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

        $data              = $request->only(['name', 'description', 'notes', 'duration_minutes']);
        $data['tenant_id'] = $clubId;

        if ($request->filled('picture') && str_starts_with($request->input('picture'), 'data:image')) {
            $data['picture_url'] = $this->storeBase64Image($request->input('picture'), 'clubs/' . $clubId . '/activities', 'activity_' . time());
        } elseif ($request->hasFile('picture')) {
            $data['picture_url'] = $request->file('picture')->store('clubs/' . $clubId . '/activities', 'public');
        } elseif ($request->filled('existing_picture_url')) {
            $storagePath = str_replace(asset('storage') . '/', '', $request->existing_picture_url);
            if (Storage::disk('public')->exists($storagePath)) {
                $extension           = pathinfo($storagePath, PATHINFO_EXTENSION);
                $newPath             = 'clubs/' . $clubId . '/activities/activity_' . time() . '.' . $extension;
                Storage::disk('public')->copy($storagePath, $newPath);
                $data['picture_url'] = $newPath;
            }
        }

        ClubActivity::create($data);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Activity added successfully.']);
        }

        return back()->with('success', 'Activity added successfully.');
    }

    public function updateActivity(UpdateActivityRequest $request, Tenant $club, $activityId)
    {
        $this->authorizeClub($club);
        $clubId   = $club->id;
        $activity = ClubActivity::where('tenant_id', $clubId)->findOrFail($activityId);

        $data = $request->only(['name', 'description', 'notes', 'duration_minutes']);

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

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Activity updated successfully.']);
        }

        return back()->with('success', 'Activity updated successfully.');
    }

    public function destroyActivity(Tenant $club, $activityId)
    {
        $this->authorizeClub($club);
        $activity = ClubActivity::where('tenant_id', $club->id)->findOrFail($activityId);

        if ($activity->picture_url && Storage::disk('public')->exists($activity->picture_url)) {
            Storage::disk('public')->delete($activity->picture_url);
        }

        $activity->delete();

        return back()->with('success', 'Activity deleted successfully.');
    }
}
