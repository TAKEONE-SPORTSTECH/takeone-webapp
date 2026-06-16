<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BusinessApprovalController extends Controller
{
    /**
     * List businesses for super-admin review (pending first).
     */
    public function index(Request $request): View
    {
        $businesses = Business::with(['owner:id,full_name,email', 'clubs:id,business_id,club_name'])
            ->withCount('clubs')
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 WHEN 'rejected' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at')
            ->get();

        $counts = [
            'all'      => $businesses->count(),
            'pending'  => $businesses->where('status', Business::STATUS_PENDING)->count(),
            'approved' => $businesses->where('status', Business::STATUS_APPROVED)->count(),
            'rejected' => $businesses->where('status', Business::STATUS_REJECTED)->count(),
        ];

        $pendingCount = $counts['pending'];

        return view('admin.platform.businesses.index', compact('businesses', 'pendingCount', 'counts'));
    }

    /**
     * Approve a business — activates the switcher and auto-links the owner's clubs.
     */
    public function approve(Request $request, Business $business): JsonResponse|RedirectResponse
    {
        $business->approve(Auth::id());

        return $this->respond($request, $business, "“{$business->name}” has been approved.");
    }

    /**
     * Reject a business with an optional reason.
     */
    public function reject(Request $request, Business $business): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $business->reject($validated['rejection_reason'] ?? null, Auth::id());

        return $this->respond($request, $business, "“{$business->name}” has been rejected.");
    }

    /**
     * Update a business's details (name, description, logo) and status.
     */
    public function update(Request $request, Business $business): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'name'             => ['required', 'string', 'max:120'],
            'description'      => ['nullable', 'string', 'max:1000'],
            'status'           => ['required', Rule::in([
                Business::STATUS_PENDING,
                Business::STATUS_APPROVED,
                Business::STATUS_REJECTED,
            ])],
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
            'logo'             => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
        ]);

        if ($request->hasFile('logo')) {
            if ($business->logo && Storage::disk('public')->exists($business->logo)) {
                Storage::disk('public')->delete($business->logo);
            }
            $business->logo = $request->file('logo')->store('business-logos', 'public');
        }

        $business->name        = $validated['name'];
        $business->description = $validated['description'] ?? null;
        $business->save();

        // Apply any status transition through the model's helpers so club-linking
        // and approval bookkeeping stay consistent.
        if ($validated['status'] === Business::STATUS_APPROVED && $business->status !== Business::STATUS_APPROVED) {
            $business->approve(Auth::id());
        } elseif ($validated['status'] === Business::STATUS_REJECTED) {
            $business->reject($validated['rejection_reason'] ?? null, Auth::id());
        } elseif ($validated['status'] === Business::STATUS_PENDING && $business->status !== Business::STATUS_PENDING) {
            $business->update([
                'status'           => Business::STATUS_PENDING,
                'rejection_reason' => null,
                'approved_at'      => null,
                'approved_by'      => null,
            ]);
        }

        return $this->respond($request, $business, "“{$business->name}” has been updated.");
    }

    /**
     * Transfer the chain to a new owner and record it in the audit trail.
     */
    public function transferOwner(Request $request, Business $business): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'to_user_id'     => ['required', 'integer', 'exists:users,id'],
            'reassign_clubs' => ['nullable', 'boolean'],
            'note'           => ['nullable', 'string', 'max:1000'],
        ]);

        $newOwnerId = (int) $validated['to_user_id'];

        if ($newOwnerId === (int) $business->owner_user_id) {
            return $this->fail($request, 'That user already owns this business.');
        }

        // One business per user — block taking over someone who already owns another chain.
        $conflict = Business::where('owner_user_id', $newOwnerId)
            ->where('id', '!=', $business->id)
            ->exists();

        if ($conflict) {
            return $this->fail($request, 'That user already owns another business. A user can own only one chain.');
        }

        $business->transferOwnerTo(
            $newOwnerId,
            Auth::id(),
            (bool) ($validated['reassign_clubs'] ?? false),
            $validated['note'] ?? null,
        );

        $newOwner = User::find($newOwnerId);

        if ($request->wantsJson()) {
            return response()->json([
                'success'  => true,
                'message'  => "Ownership transferred to {$newOwner->full_name}.",
                'business' => $this->payload($business->fresh()),
                'history'  => $this->historyPayload($business),
            ]);
        }

        return back()->with('success', "Ownership transferred to {$newOwner->full_name}.");
    }

    /**
     * Return the ownership-transfer history for a business (most recent first).
     */
    public function history(Business $business): JsonResponse
    {
        return response()->json([
            'success' => true,
            'history' => $this->historyPayload($business),
        ]);
    }

    /**
     * Return the clubs currently in the chain plus the clubs that can be added.
     */
    public function clubs(Business $business): JsonResponse
    {
        $available = Tenant::where(function ($q) use ($business) {
                $q->whereNull('business_id')->orWhere('business_id', '!=', $business->id);
            })
            ->with(['owner:id,full_name', 'business:id,name'])
            ->orderBy('club_name')
            ->get(['id', 'club_name', 'logo', 'owner_user_id', 'business_id'])
            ->map(fn ($c) => [
                'id'       => $c->id,
                'name'     => $c->club_name,
                'logo_url' => $c->logo ? asset('storage/' . $c->logo) : null,
                'owner'    => $c->owner?->full_name,
                'business' => $c->business?->name,
            ])
            ->all();

        return response()->json([
            'success'   => true,
            'clubs'     => $this->clubsPayload($business),
            'available' => $available,
        ]);
    }

    /**
     * Add an existing club to this chain (reassigns it if it was in another chain).
     */
    public function attachClub(Request $request, Business $business): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'club_id' => ['required', 'integer', 'exists:tenants,id'],
        ]);

        $club = Tenant::findOrFail($validated['club_id']);

        // business_id is guarded against mass assignment, so set it directly.
        $club->business_id = $business->id;
        $club->save();

        return $this->clubsResponse($request, $business, "“{$club->club_name}” was added to the chain.");
    }

    /**
     * Remove a club from this chain (leaves the club itself untouched).
     */
    public function detachClub(Request $request, Business $business): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'club_id' => ['required', 'integer'],
        ]);

        $club = $business->clubs()->find($validated['club_id']);

        if (!$club) {
            return $this->fail($request, 'That club is not part of this chain.');
        }

        $club->business_id = null;
        $club->save();

        return $this->clubsResponse($request, $business, "“{$club->club_name}” was removed from the chain.");
    }

    /**
     * Build the list of clubs that belong to a chain.
     */
    private function clubsPayload(Business $business): array
    {
        return $business->clubs()
            ->with('owner:id,full_name')
            ->orderBy('club_name')
            ->get(['id', 'club_name', 'logo', 'owner_user_id', 'business_id'])
            ->map(fn ($c) => [
                'id'       => $c->id,
                'name'     => $c->club_name,
                'logo_url' => $c->logo ? asset('storage/' . $c->logo) : null,
                'owner'    => $c->owner?->full_name,
            ])
            ->all();
    }

    /**
     * Standard response after attaching/detaching a club.
     */
    private function clubsResponse(Request $request, Business $business, string $message): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json([
                'success'  => true,
                'message'  => $message,
                'business' => $this->payload($business->fresh()),
                'clubs'    => $this->clubsPayload($business),
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Delete a business — unlinks its clubs first, then soft-deletes the chain.
     */
    public function destroy(Request $request, Business $business): JsonResponse|RedirectResponse
    {
        $name = $business->name;

        Tenant::where('business_id', $business->id)->update(['business_id' => null]);

        if ($business->logo && Storage::disk('public')->exists($business->logo)) {
            Storage::disk('public')->delete($business->logo);
        }

        $business->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "“{$name}” has been deleted.",
                'id'      => $business->id,
            ]);
        }

        return back()->with('success', "“{$name}” has been deleted.");
    }

    /**
     * Build the JSON payload for a business card so the page can patch in place.
     */
    private function payload(Business $business): array
    {
        $business->loadMissing('owner:id,full_name,email')->loadCount('clubs');

        return [
            'id'               => $business->id,
            'name'             => $business->name,
            'description'      => $business->description,
            'status'           => $business->status,
            'rejection_reason' => $business->rejection_reason,
            'logo'             => $business->logo,
            'logo_url'         => $business->logo ? asset('storage/' . $business->logo) : null,
            'owner_id'         => $business->owner_user_id,
            'owner_name'       => $business->owner?->full_name,
            'owner_email'      => $business->owner?->email,
            'clubs_count'      => $business->clubs_count,
        ];
    }

    /**
     * Build the ownership-history payload (most recent transfer first).
     */
    private function historyPayload(Business $business): array
    {
        return $business->ownershipLogs()
            ->with(['fromUser:id,full_name', 'toUser:id,full_name', 'changedBy:id,full_name'])
            ->get()
            ->map(fn ($log) => [
                'from'             => $log->fromUser?->full_name,
                'to'               => $log->toUser?->full_name,
                'changed_by'       => $log->changedBy?->full_name,
                'clubs_reassigned' => $log->clubs_reassigned,
                'clubs_count'      => $log->clubs_reassigned_count,
                'note'             => $log->note,
                'at'               => $log->created_at?->format('M j, Y g:i A'),
            ])
            ->all();
    }

    /**
     * Return a JSON error for AJAX requests, otherwise redirect back with the message.
     */
    private function fail(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['success' => false, 'message' => $message], 422);
        }

        return back()->with('error', $message);
    }

    /**
     * Return JSON for AJAX requests, otherwise redirect back with a flash message.
     */
    private function respond(Request $request, Business $business, string $message): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json([
                'success'  => true,
                'message'  => $message,
                'business' => $this->payload($business->fresh()),
            ]);
        }

        return back()->with('success', $message);
    }
}
