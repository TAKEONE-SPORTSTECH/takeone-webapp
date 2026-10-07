<?php

namespace App\Events\Support;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventMatch;
use App\Members\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The event's numbers at a glance — how many girls, how many boys, how many
 * divisions, how many bouts will actually be fought.
 *
 * COUNTS, NEVER PEOPLE. Everything here is an aggregate, which is why it may
 * sit on the public page beside "how full is it": nobody can be named from it.
 *
 * ── What is counted ────────────────────────────────────────────────────────
 *  · People, not entries. One athlete in two divisions is one girl or one boy,
 *    so it is DISTINCT user ids across participant entries. A withdrawn or
 *    removed entry is not somebody who is coming.
 *  · Divisions: what the organiser draws, so never a heading.
 *  · Bouts: every bout in the draw EXCEPT a bye. A bye is scaffolding the
 *    engine writes so a lone competitor advances (`won_by_bye`); nobody steps
 *    on the mat for it. A later-round bout still waiting for its two winners IS
 *    counted — it will be fought. In a knockout this is entrants − 1 per
 *    division, which is a useful cross-check.
 *
 * ── Who sees the bout count ─────────────────────────────────────────────────
 * It is a reading of the DRAW, so it obeys the draw's own disclosure rule
 * (EventAccess::drawVisible): withheld draw → no bout count, rather than a
 * number that leaks the draw's shape before the organiser opens it. Before any
 * draw exists it is null too, and the tile is simply not drawn.
 */
class EventStats
{
    /** @return array{female: int, male: int, divisions: int, bouts: ?int} */
    public static function for(ClubEvent $event, ?User $viewer = null): array
    {
        $genders = DB::table('club_event_registrations as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.event_id', $event->id)
            ->where('r.role', 'participant')
            ->where(fn ($q) => $q->whereNull('r.entry_state')->orWhereNotIn('r.entry_state', ['withdrawn', 'removed']))
            ->selectRaw('u.gender as gender, count(distinct r.user_id) as n')
            ->groupBy('u.gender')
            ->pluck('n', 'gender');

        $divisionIds = $event->categories()->where('is_heading', false)->pluck('id');

        $bouts = null;

        if ($divisionIds->isNotEmpty() && app(EventAccess::class)->drawVisible($event, $viewer)) {
            $drawn = EventMatch::whereIn('category_id', $divisionIds)->count();

            $bouts = $drawn > 0
                ? EventMatch::whereIn('category_id', $divisionIds)->where('won_by_bye', false)->count()
                : null;
        }

        return [
            'female' => (int) ($genders['Female'] ?? 0),
            'male' => (int) ($genders['Male'] ?? 0),
            'divisions' => $divisionIds->count(),
            'bouts' => $bouts,
        ];
    }
}
