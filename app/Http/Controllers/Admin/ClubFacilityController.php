<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFacilityRequest;
use App\Http\Requests\Admin\UpdateFacilityRequest;
use App\Http\Requests\UploadImageRequest;
use App\Models\ClubFacility;
use App\Models\Tenant;
use App\Traits\HandlesClubAuthorization;
use Illuminate\Support\Facades\Storage;

class ClubFacilityController extends Controller
{
    use HandlesClubAuthorization;

    public function facilities(Tenant $club)
    {
        $this->authorizeClub($club);
        $facilities = ClubFacility::where('tenant_id', $club->id)->get();
        return view('admin.club.facilities.index', compact('club', 'facilities'));
    }

    public function storeFacility(StoreFacilityRequest $request, Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

        $data = [
            'tenant_id'    => $clubId,
            'name'         => $request->name,
            'description'  => $request->description,
            'address'      => $request->address,
            'gps_lat'      => $request->latitude,
            'gps_long'     => $request->longitude,
            'maps_url'     => $request->maps_url,
            'is_available' => $request->has('is_available'),
        ];

        $paths = $this->saveFacilityBase64Images($request->input('facility_images_base64', []), $clubId);
        if ($paths) $data['images'] = $paths;

        ClubFacility::create($data);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Facility added successfully.']);
        }

        return back()->with('success', 'Facility added successfully.');
    }

    public function getFacility(Tenant $club, $facilityId)
    {
        $this->authorizeClub($club);
        $facility = ClubFacility::where('tenant_id', $club->id)->findOrFail($facilityId);

        return response()->json([
            'success' => true,
            'data'    => $facility,
        ]);
    }

    public function updateFacility(UpdateFacilityRequest $request, Tenant $club, $facilityId)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

        $facility = ClubFacility::where('tenant_id', $clubId)->findOrFail($facilityId);

        $data               = $request->only(['name', 'address', 'gps_lat', 'gps_long', 'maps_url']);
        $data['is_available'] = $request->has('is_available');

        try {
            $kept = json_decode($request->input('keep_images', '[]'), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return response()->json(['success' => false, 'message' => 'Invalid image data.'], 422);
        }
        $newPaths      = $this->saveFacilityBase64Images($request->input('facility_images_base64', []), $clubId);
        $data['images'] = array_merge($kept, $newPaths);

        $facility->update($data);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Facility updated successfully.',
                'data'    => $facility,
            ]);
        }

        return back()->with('success', 'Facility updated successfully.');
    }

    public function destroyFacility(Tenant $club, $facilityId)
    {
        $this->authorizeClub($club);
        $facility = ClubFacility::where('tenant_id', $club->id)->findOrFail($facilityId);

        if ($facility->photo && Storage::disk('public')->exists($facility->photo)) {
            Storage::disk('public')->delete($facility->photo);
        }

        $facility->delete();

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Facility deleted successfully.']);
        }

        return back()->with('success', 'Facility deleted successfully.');
    }

    public function uploadFacilityImage(UploadImageRequest $request, Tenant $club, $facilityId)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

        try {
            $facility = ClubFacility::where('tenant_id', $clubId)->findOrFail($facilityId);

            $imageData    = $request->image;
            $imageParts   = explode(';base64,', $imageData);
            $imageTypeAux = explode('image/', $imageParts[0]);
            $extension    = $imageTypeAux[1];
            $imageBinary  = base64_decode($imageParts[1]);

            $folder   = trim($request->folder, '/');
            $fullPath = $folder . '/' . $request->filename . '.' . $extension;

            if ($facility->photo && Storage::disk('public')->exists($facility->photo)) {
                Storage::disk('public')->delete($facility->photo);
            }

            Storage::disk('public')->put($fullPath, $imageBinary);
            $facility->update(['photo' => $fullPath]);

            return response()->json([
                'success' => true,
                'path'    => $fullPath,
                'url'     => asset('storage/' . $fullPath),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
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
}
