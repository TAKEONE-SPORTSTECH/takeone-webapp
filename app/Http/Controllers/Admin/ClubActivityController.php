<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreActivityRequest;
use App\Http\Requests\Admin\UpdateActivityRequest;
use App\Models\ClubActivity;
use App\Models\ClubActivityEquipment;
use App\Models\ClubFacility;
use App\Models\Tenant;
use App\Traits\HandlesClubAuthorization;
use App\Traits\PersistsTranslations;
use App\Traits\StoresBase64Images;
use Illuminate\Support\Facades\Storage;

class ClubActivityController extends Controller
{
    use HandlesClubAuthorization, PersistsTranslations, StoresBase64Images;

    public function activities(Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;
        $activities = ClubActivity::where('tenant_id', $clubId)->with('facility')->get();
        $facilities = ClubFacility::where('tenant_id', $clubId)->get();

        return view(\App\Support\ClubView::pick('activities'), compact('club', 'activities', 'facilities'));
    }

    public function storeActivity(StoreActivityRequest $request, Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;

        $data = $request->only(['name', 'description', 'notes', 'duration_minutes']);
        $data['tenant_id'] = $clubId;

        if ($request->filled('picture') && str_starts_with($request->input('picture'), 'data:image')) {
            $data['picture_url'] = $this->storeBase64Image($request->input('picture'), 'clubs/'.$clubId.'/activities', 'activity_'.time());
        } elseif ($request->hasFile('picture')) {
            $data['picture_url'] = $request->file('picture')->store('clubs/'.$clubId.'/activities', 'public');
        } elseif ($request->filled('existing_picture_url')) {
            $storagePath = str_replace(asset('storage').'/', '', $request->existing_picture_url);
            if (Storage::disk('public')->exists($storagePath)) {
                $extension = pathinfo($storagePath, PATHINFO_EXTENSION);
                $newPath = 'clubs/'.$clubId.'/activities/activity_'.time().'.'.$extension;
                Storage::disk('public')->copy($storagePath, $newPath);
                $data['picture_url'] = $newPath;
            }
        }

        $activity = ClubActivity::create($data);

        $this->applyTranslations($activity, $request);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Activity added successfully.',
                'activity' => $this->activityPayload($activity),
            ]);
        }

        return back()->with('success', 'Activity added successfully.');
    }

    public function updateActivity(UpdateActivityRequest $request, Tenant $club, $activityId)
    {
        $this->authorizeClub($club);
        $clubId = $club->id;
        $activity = ClubActivity::where('tenant_id', $clubId)->findOrFail($activityId);

        $data = $request->only(['name', 'description', 'notes', 'duration_minutes']);

        if ($request->filled('picture') && str_starts_with($request->input('picture'), 'data:image')) {
            if ($activity->picture_url && Storage::disk('public')->exists($activity->picture_url)) {
                Storage::disk('public')->delete($activity->picture_url);
            }
            $data['picture_url'] = $this->storeBase64Image($request->input('picture'), 'clubs/'.$clubId.'/activities', 'activity_'.$activityId.'_'.time());
        } elseif ($request->hasFile('picture')) {
            if ($activity->picture_url && Storage::disk('public')->exists($activity->picture_url)) {
                Storage::disk('public')->delete($activity->picture_url);
            }
            $data['picture_url'] = $request->file('picture')->store('clubs/'.$clubId.'/activities', 'public');
        }

        $activity->update($data);

        $this->applyTranslations($activity, $request);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Activity updated successfully.',
                'activity' => $this->activityPayload($activity),
            ]);
        }

        return back()->with('success', 'Activity updated successfully.');
    }

    /**
     * Build the JSON payload used by the activities page to update a card in place.
     */
    private function activityPayload(ClubActivity $activity): array
    {
        $activity->loadMissing('facility');

        return [
            'id' => $activity->id,
            'name' => $activity->name,
            'description' => $activity->description,
            'translations' => $activity->translations,
            'notes' => $activity->notes,
            'duration_minutes' => $activity->duration_minutes,
            'picture_url' => $activity->picture_url,
            'picture_src' => $activity->picture_url ? asset('storage/'.$activity->picture_url) : null,
            'facility' => $activity->facility ? ['id' => $activity->facility->id, 'name' => $activity->facility->name] : null,
            'updated_at' => optional($activity->updated_at)->timestamp,
        ];
    }

    public function destroyActivity(\Illuminate\Http\Request $request, Tenant $club, $activityId)
    {
        $this->authorizeClub($club);
        $activity = ClubActivity::where('tenant_id', $club->id)->findOrFail($activityId);

        if ($activity->picture_url && Storage::disk('public')->exists($activity->picture_url)) {
            Storage::disk('public')->delete($activity->picture_url);
        }

        $activity->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Activity deleted successfully.']);
        }

        return back()->with('success', 'Activity deleted successfully.');
    }

    /* -----------------------------------------------------------------
     |  Equipment catalog (gear required to practice the activity)
     | ----------------------------------------------------------------- */

    public function equipment(Tenant $club, $activityId)
    {
        $this->authorizeClub($club);
        $activity = ClubActivity::where('tenant_id', $club->id)->findOrFail($activityId);

        $items = ClubActivityEquipment::where('activity_id', $activity->id)
            ->with('product')
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->map(fn ($e) => $this->equipmentPayload($e));

        // Shop products the admin can link as gear (published items).
        $products = \App\Models\ClubProduct::where('tenant_id', $club->id)
            ->where('status', 'published')
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => (float) $p->price,
                'image' => $p->image_path ? asset('storage/'.$p->image_path) : null,
            ]);

        return response()->json([
            'success' => true,
            'equipment' => $items,
            'products' => $products,
        ]);
    }

    public function storeEquipment(\Illuminate\Http\Request $request, Tenant $club, $activityId)
    {
        $this->authorizeClub($club);
        $activity = ClubActivity::where('tenant_id', $club->id)->findOrFail($activityId);

        $data = $this->validateEquipment($request, $club);

        // One activity links a given product once.
        $equipment = ClubActivityEquipment::firstOrNew([
            'activity_id' => $activity->id,
            'club_product_id' => $data['club_product_id'],
        ]);
        $equipment->fill([
            'tenant_id' => $club->id,
            'is_required' => $data['is_required'],
            'is_active' => $data['is_active'],
        ]);
        if (! $equipment->exists) {
            $equipment->sort_order = ClubActivityEquipment::where('activity_id', $activity->id)->max('sort_order') + 1;
        }
        $equipment->save();

        return response()->json([
            'success' => true,
            'message' => 'Equipment added.',
            'equipment' => $this->equipmentPayload($equipment->load('product')),
        ]);
    }

    public function updateEquipment(\Illuminate\Http\Request $request, Tenant $club, $activityId, $equipmentId)
    {
        $this->authorizeClub($club);
        $equipment = ClubActivityEquipment::where('tenant_id', $club->id)
            ->where('activity_id', $activityId)
            ->findOrFail($equipmentId);

        // Editing only toggles the registration-specific flags; the product
        // (name/price/image) is managed in the shop.
        $equipment->update([
            'is_required' => $request->boolean('is_required'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Equipment updated.',
            'equipment' => $this->equipmentPayload($equipment->load('product')),
        ]);
    }

    public function destroyEquipment(Tenant $club, $activityId, $equipmentId)
    {
        $this->authorizeClub($club);
        $equipment = ClubActivityEquipment::where('tenant_id', $club->id)
            ->where('activity_id', $activityId)
            ->findOrFail($equipmentId);

        $equipment->delete();

        return response()->json(['success' => true, 'message' => 'Equipment removed.']);
    }

    private function validateEquipment(\Illuminate\Http\Request $request, Tenant $club): array
    {
        $request->validate([
            'club_product_id' => [
                'required', 'integer',
                \Illuminate\Validation\Rule::exists('club_products', 'id')->where('tenant_id', $club->id),
            ],
            'is_required' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'club_product_id' => (int) $request->input('club_product_id'),
            'is_required' => $request->boolean('is_required'),
            'is_active' => $request->boolean('is_active', true),
        ];
    }

    private function equipmentPayload(ClubActivityEquipment $e): array
    {
        return [
            'id' => $e->id,
            'product_id' => $e->club_product_id,
            'name' => $e->product?->name,
            'price' => (float) ($e->product?->price ?? 0),
            'image' => $e->product?->image_path ? asset('storage/'.$e->product->image_path) : null,
            'is_required' => (bool) $e->is_required,
            'is_active' => (bool) $e->is_active,
        ];
    }
}
