<?php

namespace App\Events\Controllers;

use App\Http\Controllers\Controller;
use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventSide;
use App\Events\Models\EventSideMember;
use App\Events\Support\Competition\EventSides;
use App\Events\Support\EventAccess;
use App\Members\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Pairs and teams in an event — the organiser's door (step 3 of the
 * generic-sports plan, 2026-09-27).
 *
 * Every action asks EventAccess::canManage first and resolves the side by its
 * uuid INSIDE this event, so a side of another event is simply not found. The
 * rules about what a side may be live in App\Events\Support\Competition\
 * EventSides. JSON only; each write returns the side as it now stands
 * (No-Reload rule).
 */
class EventSidesController extends Controller
{
    public function __construct(private EventSides $sides) {}

    public function index(ClubEvent $event): JsonResponse
    {
        $this->guard($event);

        return response()->json([
            'success' => true,
            'kind' => $this->sides->kindFor($event),
            'sides' => $this->sides->list($event),
        ]);
    }

    public function store(Request $request, ClubEvent $event): JsonResponse
    {
        $me = $this->guard($event);

        $data = $request->validate([
            'category_id' => ['nullable', 'integer'],
            'name' => ['nullable', 'string', 'max:80'],
            'short_name' => ['nullable', 'string', 'max:12'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'seed' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        $side = $this->sides->create($event, $me, $data);

        return response()->json(['success' => true, 'message' => __('events::events.side_created'), 'side' => $this->sides->present($side->fresh())]);
    }

    public function update(Request $request, ClubEvent $event, string $side): JsonResponse
    {
        $this->guard($event);
        $row = $this->side($event, $side);

        $data = $request->validate([
            'category_id' => ['sometimes', 'nullable', 'integer'],
            'name' => ['sometimes', 'nullable', 'string', 'max:80'],
            'short_name' => ['sometimes', 'nullable', 'string', 'max:12'],
            'seed' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:999'],
            'withdrawn' => ['sometimes', 'boolean'],
        ]);

        $row = $this->sides->update($row, $data);

        return response()->json(['success' => true, 'message' => __('events::events.side_saved'), 'side' => $this->sides->present($row)]);
    }

    public function destroy(ClubEvent $event, string $side): JsonResponse
    {
        $this->guard($event);
        $row = $this->side($event, $side);
        $uuid = $row->uuid;

        $this->sides->delete($row);

        return response()->json(['success' => true, 'message' => __('events::events.side_deleted'), 'uuid' => $uuid]);
    }

    public function addMember(Request $request, ClubEvent $event, string $side): JsonResponse
    {
        $this->guard($event);
        $row = $this->side($event, $side);

        $data = $request->validate([
            'registration_id' => ['required', 'integer'],
            'role' => ['nullable', 'string', Rule::in(EventSideMember::ROLES)],
            'number' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        // Scoped to THIS event: an id from another event is not found, and
        // says nothing about whether it exists.
        $registration = ClubEventRegistration::where('event_id', $event->id)->findOrFail((int) $data['registration_id']);

        $this->sides->addMember($row, $registration, $data['role'] ?? 'player', $data['number'] ?? null);

        return response()->json(['success' => true, 'message' => __('events::events.side_member_added'), 'side' => $this->sides->present($row->fresh())]);
    }

    public function removeMember(ClubEvent $event, string $side, int $member): JsonResponse
    {
        $this->guard($event);
        $row = $this->side($event, $side);

        $this->sides->removeMember($row, EventSideMember::where('side_id', $row->id)->findOrFail($member));

        return response()->json(['success' => true, 'message' => __('events::events.side_member_removed'), 'side' => $this->sides->present($row->fresh())]);
    }

    private function guard(ClubEvent $event): User
    {
        $me = Auth::user();

        abort_unless($me instanceof User && app(EventAccess::class)->canManage($event, $me), 403);

        return $me;
    }

    private function side(ClubEvent $event, string $uuid): EventSide
    {
        return EventSide::where('event_id', $event->id)->where('uuid', $uuid)->firstOrFail();
    }
}
