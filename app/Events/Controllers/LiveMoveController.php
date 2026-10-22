<?php

namespace App\Events\Controllers;

use App\Events\Models\ClubEvent;
use App\Events\Support\EventAccess;
use App\Events\Support\Tournament\LiveMove;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Move to the correct division" while the event runs
 * (App\Events\Support\Tournament\LiveMove). Organiser only, behind
 * EVENT_LIVE_MOVE — off, or not the organiser, and the door is not there.
 */
class LiveMoveController extends Controller
{
    public function move(Request $request, ClubEvent $event, LiveMove $move, EventAccess $access): JsonResponse
    {
        abort_unless(LiveMove::enabled() && $access->canManage($event, $request->user()), 404);

        $data = $request->validate([
            'entry' => ['required', 'integer', 'min:1'],
            'to' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $out = $move->run($event, (int) $data['entry'], (int) $data['to'], $request->user());
        } catch (\Throwable $e) {
            // Never a 500 on the organiser's screen mid-event: the move was one
            // transaction, so a failure here changed nothing.
            report($e);
            $out = ['ok' => false, 'message' => __('events::events.live_move_unsafe')];
        }

        return response()->json(['success' => $out['ok']] + collect($out)->except('ok')->all(), $out['ok'] ? 200 : 422);
    }

    /** Who this entry could swap places with (LiveMove::candidates). */
    public function candidates(Request $request, ClubEvent $event, LiveMove $move, EventAccess $access): JsonResponse
    {
        abort_unless(LiveMove::enabled() && $access->canManage($event, $request->user()), 404);

        $data = $request->validate(['entry' => ['required', 'integer', 'min:1']]);

        return response()->json(['success' => true, 'people' => $move->candidates($event, (int) $data['entry'])],
            200, ['Cache-Control' => 'no-store, private']);
    }

    /** Swap two people's places in the draw (LiveMove::swap). */
    public function swap(Request $request, ClubEvent $event, LiveMove $move, EventAccess $access): JsonResponse
    {
        abort_unless(LiveMove::enabled() && $access->canManage($event, $request->user()), 404);

        $data = $request->validate([
            'entry' => ['required', 'integer', 'min:1'],
            'with' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $out = $move->swap($event, (int) $data['entry'], (int) $data['with'], $request->user());
        } catch (\Throwable $e) {
            report($e);
            $out = ['ok' => false, 'message' => __('events::events.live_move_unsafe')];
        }

        return response()->json(['success' => $out['ok'], 'message' => $out['message']], $out['ok'] ? 200 : 422);
    }
}
