<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PerkRequest;
use App\Models\ClubPerk;
use App\Models\Tenant;
use App\Traits\HandlesClubAuthorization;
use Illuminate\Support\Facades\Storage;

class ClubPerkController extends Controller
{
    use HandlesClubAuthorization;

    public function perks(Tenant $club)
    {
        $this->authorizeClub($club);
        $perks = ClubPerk::where('tenant_id', $club->id)->orderBy('sort_order')->orderBy('id')->get();
        return view('admin.club.perks.index', compact('club', 'perks'));
    }

    public function storePerk(PerkRequest $request, Tenant $club)
    {
        $this->authorizeClub($club);

        $imagePath = null;
        if ($request->filled('image') && str_starts_with($request->image, 'data:image')) {
            $imageParts  = explode(';base64,', $request->image);
            $extension   = explode('image/', $imageParts[0])[1];
            $imageBinary = base64_decode($imageParts[1]);
            $folder      = $request->input('image_folder', 'perks/' . $club->slug);
            $filename    = $request->input('image_filename', 'perk_' . time());
            $imagePath   = $folder . '/' . $filename . '.' . $extension;
            Storage::disk('public')->put($imagePath, $imageBinary);
        }

        ClubPerk::create([
            'tenant_id'   => $club->id,
            'title'       => $request->title,
            'description' => $request->description,
            'badge'       => $request->badge,
            'image_path'  => $imagePath,
            'icon'        => $request->icon ?: 'bi-gift',
            'bg_from'     => $request->bg_from ?: '#f59e0b',
            'bg_to'       => $request->bg_to   ?: '#f97316',
            'perk_type'   => $request->perk_type,
            'perk_value'  => $request->perk_value,
            'status'      => $request->status,
            'sort_order'  => $request->sort_order ?? 0,
        ]);

        return back()->with('success', 'Perk created successfully.');
    }

    public function updatePerk(PerkRequest $request, Tenant $club, ClubPerk $perk)
    {
        $this->authorizeClub($club);
        abort_if($perk->tenant_id !== $club->id, 403);

        $data = $request->only(['title', 'description', 'badge', 'icon', 'bg_from', 'bg_to', 'perk_type', 'perk_value', 'status', 'sort_order']);

        if ($request->filled('image') && str_starts_with($request->image, 'data:image')) {
            if ($perk->image_path) {
                Storage::disk('public')->delete($perk->image_path);
            }
            $imageParts         = explode(';base64,', $request->image);
            $extension          = explode('image/', $imageParts[0])[1];
            $imageBinary        = base64_decode($imageParts[1]);
            $folder             = $request->input('image_folder', 'perks/' . $club->slug);
            $filename           = $request->input('image_filename', 'perk_' . time());
            $data['image_path'] = $folder . '/' . $filename . '.' . $extension;
            Storage::disk('public')->put($data['image_path'], $imageBinary);
        }

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
}
