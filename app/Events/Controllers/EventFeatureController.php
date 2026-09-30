<?php

namespace App\Events\Controllers;

use App\Events\Models\ClubEvent;
use App\Events\Support\EventAccess;
use App\Events\Support\EventFeaturing;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The organiser's half of paid featuring for a CLUB event: read where it
 * stands, and send the proof of payment. The rules are EventFeaturing's; this
 * only decides who may ask — the event's managers (EventAccess::canManage).
 */
class EventFeatureController extends Controller
{
    public function show(Request $request, ClubEvent $event, EventAccess $access): JsonResponse
    {
        $this->authorizeManager($request, $event, $access);

        return response()->json(['success' => true, 'feature' => EventFeaturing::state($event)]);
    }

    public function store(Request $request, ClubEvent $event, EventAccess $access): JsonResponse
    {
        $this->authorizeManager($request, $event, $access);
        $request->validate(['proof' => ['required', 'file', 'max:'.(EventFeaturing::MAX_PROOF_BYTES / 1024)]]);

        $r = EventFeaturing::submit($request->user(), $event, $request->file('proof'));

        return response()->json(['success' => $r['ok'], 'message' => $r['message'], 'feature' => EventFeaturing::state($event)], $r['ok'] ? 200 : 422);
    }

    private function authorizeManager(Request $request, ClubEvent $event, EventAccess $access): void
    {
        // One answer for every refusal: an archived event and somebody else's look the same.
        abort_if($event->is_archived || ! $request->user() || ! $access->canManage($event, $request->user()), 403);
    }
}
