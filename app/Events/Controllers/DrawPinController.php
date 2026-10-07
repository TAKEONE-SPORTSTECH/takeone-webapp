<?php

namespace App\Events\Controllers;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventDrawPin;
use App\Events\Support\EventAccess;
use App\Events\Support\TabletDraw;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * /me/events/{uuid}/draw-pin — the organiser sets, changes or removes the
 * PIN that lets a scoring tablet edit the draw (see App\Events\Support\
 * TabletDraw). Organiser only: whoever sets it lends the tablet THEIR
 * authority, so it must be somebody who holds it.
 *
 * The PIN is never sent back — only whether one is set, and by whom.
 */
class DrawPinController extends Controller
{
    public function show(ClubEvent $event): JsonResponse
    {
        $this->guard($event);

        return response()->json(['success' => true, 'pin' => $this->state($event)]);
    }

    public function update(Request $request, ClubEvent $event): JsonResponse
    {
        $this->guard($event);

        $data = $request->validate([
            'pin' => ['required', 'string', 'regex:/^\d{4,8}$/', 'confirmed'],
        ], [
            'pin.regex' => __('events::events.tablet_pin_format'),
        ]);

        EventDrawPin::setFor($event, Auth::user(), $data['pin']);

        Log::info('Tablet draw PIN set', ['event' => $event->uuid, 'by' => Auth::id()]);

        return response()->json([
            'success' => true,
            'message' => __('events::events.tablet_pin_saved'),
            'pin' => $this->state($event),
        ]);
    }

    public function destroy(ClubEvent $event): JsonResponse
    {
        $this->guard($event);

        EventDrawPin::where('event_id', $event->id)->delete();

        Log::info('Tablet draw PIN removed', ['event' => $event->uuid, 'by' => Auth::id()]);

        return response()->json([
            'success' => true,
            'message' => __('events::events.tablet_pin_removed'),
            'pin' => $this->state($event),
        ]);
    }

    private function guard(ClubEvent $event): void
    {
        abort_unless(TabletDraw::enabled(), 404);
        abort_unless(app(EventAccess::class)->canManage($event, Auth::user()), 403);
    }

    /** @return array{set: bool, by: ?string, mine: bool} */
    private function state(ClubEvent $event): array
    {
        $pin = EventDrawPin::with('owner:id,full_name')->where('event_id', $event->id)->first();

        return [
            'set' => $pin !== null,
            'by' => $pin?->owner?->full_name,
            'mine' => $pin !== null && (int) $pin->user_id === (int) Auth::id(),
        ];
    }
}
