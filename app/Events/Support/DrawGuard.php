<?php

namespace App\Events\Support;

use App\Events\Models\ClubEvent;

/**
 * What stands between an event and "Generate draw".
 *
 * One hard stop, and one report:
 *  - REFUSED while "one division per person" is on and somebody already stands
 *    in two divisions (OneDivisionRule::conflicts). Drawing then would put the
 *    same athlete into two brackets — the thing the switch exists to prevent.
 *  - REPORTED, never refused: divisions with fewer entrants than the merge
 *    threshold (events.division_merge.below). Merging, combining or running an
 *    exhibition is the organiser's decision (DivisionRange: a guide, never a
 *    gate); the draw says which ones are short so the decision is a conscious one.
 *
 * Behind events.draw_guards (OFF by default) — with it off, nothing changes.
 * Sport-neutral: asked by the shared action door, never by a package.
 */
final class DrawGuard
{
    public static function enabled(): bool
    {
        return (bool) config('events.draw_guards', false);
    }

    /** The sentence that refuses the draw, or null when it may go ahead. */
    public static function refusal(ClubEvent $event): ?string
    {
        if (! self::enabled() || ! OneDivisionRule::on($event)) {
            return null;
        }

        $conflicts = OneDivisionRule::conflicts($event);

        return $conflicts === [] ? null
            : trans_choice('events::events.draw_refused_double_entries', count($conflicts), ['count' => count($conflicts)]);
    }

    /**
     * Divisions too small to be a fair competition: id, name, entrants.
     *
     * @return list<array{id:int, name:string, entrants:int}>
     */
    public static function small(ClubEvent $event): array
    {
        if (! self::enabled()) {
            return [];
        }
        $below = max(2, (int) config('events.division_merge.below', 3));

        return $event->categories()
            ->withCount(['registrations as entrants' => fn ($q) => $q->where('role', 'participant')])
            ->get(['id', 'name'])
            ->filter(fn ($c) => $c->entrants > 0 && $c->entrants < $below)
            ->map(fn ($c) => ['id' => (int) $c->id, 'name' => (string) $c->name, 'entrants' => (int) $c->entrants])
            ->values()->all();
    }
}
