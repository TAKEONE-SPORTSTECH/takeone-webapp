<?php

namespace App\Events\Controllers;

use App\Events\Models\ClubEvent;
use App\Events\Support\EventAccess;
use App\Events\Support\EventDuplicates;
use App\Events\Support\KnownPerson;
use App\Http\Controllers\Controller;
use App\Members\Support\AccountMerger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The organiser's pre-draw duplicate check (App\Events\Support\EventDuplicates):
 * list the likely pairs among this event's entrants, and settle one — the same
 * person, or two different people. Organiser only, like removing an entry.
 */
class EventDuplicatesController extends Controller
{
    public function index(Request $request, ClubEvent $event, EventDuplicates $dups, EventAccess $access): JsonResponse
    {
        abort_unless(KnownPerson::enabled() && $access->canManage($event, $request->user()), 404);

        return response()->json(['success' => true, 'pairs' => $dups->pairs($event)], 200, ['Cache-Control' => 'no-store, private']);
    }

    public function resolve(Request $request, ClubEvent $event, EventDuplicates $dups, EventAccess $access): JsonResponse
    {
        abort_unless(KnownPerson::enabled() && $access->canManage($event, $request->user()), 404);

        $data = $request->validate([
            'a' => ['required', 'uuid'],
            'b' => ['required', 'uuid', 'different:a'],
            'verdict' => ['required', 'in:same,different'],
            'keep' => ['required_if:verdict,same', 'nullable', 'uuid'],
        ]);

        $pair = $dups->resolvePair($event, $data['a'], $data['b']);
        if (! $pair) {
            return response()->json(['success' => false, 'message' => __('events::events.dup_gone')], 422);
        }
        [$a, $b] = $pair;

        if ($data['verdict'] === 'different') {
            app(AccountMerger::class)->dismiss($a, $b, $request->user());

            return response()->json(['success' => true, 'message' => __('events::events.dup_kept_apart')]);
        }

        $keep = $data['keep'] === $a->uuid ? $a : ($data['keep'] === $b->uuid ? $b : null);
        if (! $keep) {
            return response()->json(['success' => false, 'message' => __('events::events.dup_gone')], 422);
        }

        $result = $dups->combine($event, $request->user(), $keep, $keep->is($a) ? $b : $a);

        // Every other console and board of this event re-reads (refresh signal
        // only — what each viewer may see differs), the same audience as
        // PersonalEventController::pushEventRefresh.
        rescue(function () use ($event) {
            if (! \Realtime()->enabled()) {
                return;
            }
            $ids = $event->registrations()->pluck('user_id')->merge($event->officials()->pluck('user_id'))
                ->push($event->created_by)->filter()->unique()->values()->all();
            \Realtime()->publishMany(array_map(fn (int $uid) => [
                'topic' => \Realtime()->userTopic($uid, 'events'),
                'payload' => ['action' => 'refresh', 'event' => $event->uuid],
            ], $ids));
        }, null, false);

        return response()->json(['success' => $result['ok']] + $result + [
            'going' => $event->participantRegistrations()->count(),
        ], $result['ok'] ? 200 : 422);
    }
}
