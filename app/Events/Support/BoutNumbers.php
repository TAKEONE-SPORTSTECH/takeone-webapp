<?php

namespace App\Events\Support;

use App\Events\Controllers\PersonalEventController;
use App\Events\Models\ClubEvent;
use App\Events\Models\EventMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A bout's NUMBER on the draw board — where it stands on its mat, moving it,
 * and handing it back to automatic numbering. One implementation for both
 * doors that offer it: the scoring tablet (TabletDrawController, as the PIN's
 * owner) and the organiser's own draw desk (DrawDeskController, as the viewer).
 *
 * Callers authorise FIRST (organiser only, both doors); this decides nothing
 * about who may ask. A bout's number IS its position on its mat, so a
 * renumber is the running-order board's own move.
 */
class BoutNumbers
{
    /** Where one bout stands: its mat, number, and how many bouts each mat of that day holds. */
    public function place(ClubEvent $event, int $match): JsonResponse
    {
        $bout = EventMatch::where('event_id', $event->id)->find($match);
        abort_unless($bout !== null, 404);

        $plan = app(Tournament\OrderBoard::class)->plan($event, $bout->day ?: null);

        return response()->json([
            'success' => true,
            'match' => [
                'id' => $bout->id, 'no' => $bout->match_no, 'court' => $bout->court,
                'day' => $plan['day'], 'decided' => $bout->winner !== null,
                'pinned' => (bool) $bout->number_pinned,
            ],
            'mats' => array_map(fn ($m) => ['court' => $m['court'], 'count' => count($m['bouts'])], $plan['mats']),
        ]);
    }

    /** Give a bout a new number (and, if asked, a new mat). */
    public function renumber(Request $request, ClubEvent $event): Response
    {
        $data = $request->validate([
            'match' => ['required', 'integer'],
            'court' => ['required', 'string', 'max:40'],
            'no' => ['required', 'integer', 'min:1', 'max:999'],
            'day' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        abort_unless(EventMatch::where('event_id', $event->id)
            ->whereKey((int) $data['match'])->exists(), 404);

        // Only a mat the day actually has — never a label typed into the request.
        $mats = array_column(app(Tournament\OrderBoard::class)
            ->plan($event, $data['day'] ?? null)['mats'], 'court');
        abort_unless(in_array($data['court'], $mats, true), 422);

        $request->replace([
            'match' => (int) $data['match'],
            'court' => $data['court'],
            'position' => (int) $data['no'] - 1,
            'day' => $data['day'] ?? null,
        ]);

        return app()->call([app(PersonalEventController::class), 'moveInRunningOrder'],
            ['request' => $request, 'event' => $event]);
    }

    /** Hand a bout's number back to automatic numbering. */
    public function release(ClubEvent $event, int $match): JsonResponse
    {
        $result = app(Tournament\OrderBoard::class)->release($event, $match);

        if (! ($result['ok'] ?? false)) {
            return response()->json(['success' => false, 'message' => $result['message'] ?? __('events::events.action_failed')], 422);
        }

        rescue(fn () => app(\App\Events\EventTypeRegistry::class)->for($event)
            ->onEntrantsChanged($event, null), null, false);
        HallWalls::refresh($event);

        return response()->json(['success' => true, 'message' => $result['message']]);
    }
}
