<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GalleryUploadRequest;
use App\Http\Requests\Admin\ReorderGalleryRequest;
use App\Http\Requests\Admin\SaveYoutubeUrlRequest;
use App\Models\ClubGalleryImage;
use App\Models\Tenant;
use App\Traits\HandlesClubAuthorization;
use Illuminate\Support\Facades\Storage;

class ClubGalleryController extends Controller
{
    use HandlesClubAuthorization;

    public function gallery(Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;
        $images = ClubGalleryImage::where('tenant_id', $clubId)->orderBy('display_order')->orderBy('id')->get();
        return view('admin.club.gallery.index', compact('club', 'images'));
    }

    public function uploadGallery(GalleryUploadRequest $request, Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

        $nextOrder = ClubGalleryImage::where('tenant_id', $clubId)->max('display_order') + 1;

        // Handle base64 image from cropper (form mode)
        if ($request->filled('image_data') && str_starts_with($request->image_data, 'data:image')) {
            $imageData    = $request->image_data;
            $imageParts   = explode(';base64,', $imageData);
            $imageTypeAux = explode('image/', $imageParts[0]);
            $extension    = $imageTypeAux[1];
            $imageBinary  = base64_decode($imageParts[1]);

            $folder   = 'clubs/' . $clubId . '/gallery';
            $filename = 'gallery_' . time() . '_' . uniqid() . '.' . $extension;
            $fullPath = $folder . '/' . $filename;

            Storage::disk('public')->put($fullPath, $imageBinary);

            ClubGalleryImage::create([
                'tenant_id'     => $clubId,
                'image_path'    => $fullPath,
                'caption'       => $request->caption,
                'uploaded_by'   => auth()->id(),
                'display_order' => $nextOrder,
            ]);
        }
        // Handle traditional file upload (fallback)
        elseif ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('clubs/' . $clubId . '/gallery', 'public');
                ClubGalleryImage::create([
                    'tenant_id'     => $clubId,
                    'image_path'    => $path,
                    'caption'       => $request->caption,
                    'uploaded_by'   => auth()->id(),
                    'display_order' => $nextOrder++,
                ]);
            }
        }

        return back()->with('success', 'Image uploaded successfully.');
    }

    public function reorderGallery(ReorderGalleryRequest $request, Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

        foreach ($request->order as $position => $imageId) {
            ClubGalleryImage::where('tenant_id', $clubId)->where('id', $imageId)
                ->update(['display_order' => $position]);
        }

        return response()->json(['success' => true]);
    }

    public function saveYoutubeUrl(SaveYoutubeUrlRequest $request, Tenant $club)
    {
        $this->authorizeClub($club);

        $club->update(['youtube_url' => $request->youtube_url ?: null]);

        return back()->with('success', 'YouTube video URL saved successfully.');
    }

    public function destroyGalleryImage(Tenant $club, $imageId)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;
        $image  = ClubGalleryImage::where('tenant_id', $clubId)->findOrFail($imageId);

        if ($image->image_path && Storage::disk('public')->exists($image->image_path)) {
            Storage::disk('public')->delete($image->image_path);
        }

        $image->delete();

        return response()->json(['success' => true]);
    }
}
