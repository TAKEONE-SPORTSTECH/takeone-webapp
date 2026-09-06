<?php

namespace App\Shop\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PerkRequest;
use App\Shop\Models\ClubPerk;
use App\Clubs\Models\Tenant;
use App\Traits\HandlesClubAuthorization;
use App\Traits\PersistsTranslations;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClubPerkController extends Controller
{
    use \App\Traits\StoresBase64Images;
    use HandlesClubAuthorization;
    use PersistsTranslations;

    public function perks(Tenant $club)
    {
        $this->authorizeClub($club);
        $perks = ClubPerk::where('tenant_id', $club->id)->orderBy('sort_order')->orderBy('id')->get();

        return view(\App\Support\ClubView::pick('perks', 'shop'), compact('club', 'perks'));
    }

    /**
     * Where this club's perk images live.
     *
     * Deliberately unchanged from the path these images have always used
     * (`perks/{slug}`, a documented legacy root) — moving it would orphan every
     * perk image already on disk, which is a bigger problem than the tidiness
     * it would buy. The point of this method is that the club decides the
     * folder and the request cannot.
     */
    private function perkFolder(Tenant $club): string
    {
        return 'perks/'.$club->slug;
    }

    public function storePerk(PerkRequest $request, Tenant $club)
    {
        $this->authorizeClub($club);

        $imagePath = null;
        if ($request->filled('image') && str_starts_with($request->image, 'data:image')) {
            // Destination is OURS, never the caller's.
            //
            // This used to read `image_folder`/`image_filename` from the request,
            // with no validation rule for either — so a club admin could write
            // anywhere on the public disk under any name, and overwrite another
            // club's logo or cover. No caller has ever sent those fields; the
            // defaults were always what ran, so ignoring them changes nothing
            // that works and closes the hole.
            $imagePath = $this->storeBase64Image(
                $request->image,
                $this->perkFolder($club),
                'perk_'.Str::random(24),
            );
            if ($imagePath === null) {
                return back()->withErrors(['image' => 'Invalid or unsupported image.']);
            }
        }

        $perk = ClubPerk::create([
            'tenant_id' => $club->id,
            'title' => $request->title,
            'description' => $request->description,
            'badge' => $request->badge,
            'image_path' => $imagePath,
            'icon' => $request->icon ?: 'bi-gift',
            'bg_from' => $request->bg_from ?: '#f59e0b',
            'bg_to' => $request->bg_to ?: '#f97316',
            'perk_type' => $request->perk_type,
            'perk_value' => $request->perk_value,
            'status' => $request->status,
            'sort_order' => $request->sort_order ?? 0,
        ]);

        $this->applyTranslations($perk, $request);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Perk created successfully.', 'perk' => $perk->fresh()]);
        }

        return back()->with('success', 'Perk created successfully.');
    }

    public function updatePerk(PerkRequest $request, Tenant $club, ClubPerk $perk)
    {
        $this->authorizeClub($club);
        abort_if($perk->tenant_id !== $club->id, 403);

        $data = $request->only(['title', 'description', 'badge', 'icon', 'bg_from', 'bg_to', 'perk_type', 'perk_value', 'status', 'sort_order']);

        if ($request->filled('image') && str_starts_with($request->image, 'data:image')) {
            // Server-chosen destination — see storePerk().
            $stored = $this->storeBase64Image(
                $request->image,
                $this->perkFolder($club),
                'perk_'.Str::random(24),
            );
            if ($stored === null) {
                return back()->withErrors(['image' => 'Invalid or unsupported image.']);
            }
            if ($perk->image_path) {
                Storage::disk('public')->delete($perk->image_path);
            }
            $data['image_path'] = $stored;
        }

        if ($request->boolean('remove_image') && $perk->image_path) {
            Storage::disk('public')->delete($perk->image_path);
            $data['image_path'] = null;
        }

        $perk->update($data);

        $this->applyTranslations($perk, $request);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Perk updated successfully.', 'perk' => $perk->fresh()]);
        }

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
