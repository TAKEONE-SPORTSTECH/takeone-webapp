<?php

namespace App\Events\Support;

use App\Events\Models\ClubEvent;

/**
 * Is this event over, as an events LIST tells it? (added 2026-09-29)
 *
 * Stricter than ClubEvent::hasEnded(), which calls a same-day event finished
 * the minute its PLANNED end time passes. A competition day overruns, so a
 * list reads an event as over only when:
 *
 *   · its last day (end_date, else date) is behind us, or
 *   · it is that day and the results are OUT — the organiser completed the
 *     event, or every division that has entrants has its podium settled
 *     (the engines set a division's `status = completed` when they do).
 *
 * Used by the Explore → Events tab and the /me/events list, and nothing else:
 * draws, consoles and the entry door still read hasEnded(). One rule, one
 * place, so the two lists can never disagree about the same event.
 */
class EventOver
{
    public static function isOver(ClubEvent $event): bool
    {
        $lastDay = ($event->end_date ?? $event->date)->toDateString();
        // "Today" on the HALL's calendar — the app runs in UTC, so in Manama the
        // day would otherwise roll over at 03:00 (ClubEvent::zone()).
        $today = $event->localNow()->toDateString();

        if ($lastDay < $today) {
            return true;
        }
        if ($lastDay > $today) {
            return false;
        }
        if ($event->status === 'completed') {
            return true;
        }

        $divisions = $event->categories()->competing()
            ->whereHas('registrations', fn ($q) => $q->where('role', 'participant'))
            ->pluck('status');

        return $divisions->isNotEmpty() && $divisions->every(fn ($s) => $s === 'completed');
    }
}
