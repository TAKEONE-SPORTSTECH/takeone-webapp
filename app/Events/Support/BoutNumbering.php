<?php

namespace App\Events\Support;

use App\Events\EventTypeRegistry;
use App\Events\Models\ClubEvent;
use App\Events\Models\EventMatch;
use Illuminate\Support\Facades\Log;

/**
 * "Renumber bouts" — the organiser's button (owner, 2026-10-14): number every
 * bout from the day's running order NOW, and make every screen showing a
 * number show the new one.
 *
 * The numbering itself is the Scheduler's (`scheduleAndNumber`, which already
 * reads the running order, its groups and its parts) and is NOT re-implemented
 * here. Arranging the day renumbers on its own (RunOrder::renumber); this is
 * the same pass on demand, plus the part that was missing: telling the open
 * brackets, the mats board and the hall screens that their numbers changed.
 *
 * Numbers set by hand (`number_pinned`) are kept — the Scheduler numbers
 * around them — and the answer says how many.
 *
 * ⚠️ Never once a bout has been FOUGHT: a number the hall has called is not
 * moved under it. That is the same line RunSessions::renumberIfUnfought draws.
 */
class BoutNumbering
{
    /** @return array{ok: bool, message: string, numbered?: int, kept?: int} */
    public function renumber(ClubEvent $event): array
    {
        if ($event->hasEnded()) {
            return ['ok' => false, 'message' => __('events::events.renumber_ended')];
        }

        $sport = (string) $event->sport;
        if ($sport === '' || ! app(\App\Sports\Combat\SportRegistry::class)->get($sport)
            || ! EventMatch::where('event_id', $event->id)->exists()) {
            return ['ok' => false, 'message' => __('events::events.renumber_nothing')];
        }

        $fought = EventMatch::where('event_id', $event->id)
            ->whereNotNull('winner')->where('won_by_bye', false)
            ->whereNotNull('a_competitor_id')->whereNotNull('b_competitor_id')
            ->exists();
        if ($fought) {
            return ['ok' => false, 'message' => __('events::events.renumber_fought')];
        }

        app(\App\Sports\Combat\Engine\Scheduler::class)->scheduleAndNumber($event->fresh());

        $numbered = EventMatch::where('event_id', $event->id)->whereNotNull('match_no')->count();
        $kept = EventMatch::where('event_id', $event->id)->whereNotNull('match_no')->where('number_pinned', true)->count();

        $this->announce($event);

        Log::info('Bouts renumbered', ['event' => $event->uuid, 'by' => auth()->id(), 'numbered' => $numbered, 'kept' => $kept]);

        return [
            'ok' => true,
            'numbered' => $numbered,
            'kept' => $kept,
            'message' => $kept
                ? __('events::events.renumber_done_kept', ['count' => $numbered, 'kept' => $kept])
                : __('events::events.renumber_done', ['count' => $numbered]),
        ];
    }

    /** Every screen that prints a bout number re-reads: brackets, the wall, the mats. Best-effort. */
    private function announce(ClubEvent $event): void
    {
        rescue(function () use ($event) {
            $type = app(EventTypeRegistry::class)->for($event);
            if (method_exists($type, 'announceDrawChanged')) {
                $type->announceDrawChanged($event);
            }
        }, null, false);
        rescue(fn () => HallWalls::refresh($event), null, false);
        rescue(fn () => app(\App\Scoreboard\Fleet::class)->boardsChanged($event), null, false);
    }
}
