<?php

namespace App\Events\Support\Tournament;

use App\Events\Models\EventMatch;
use Illuminate\Support\Collection;

/**
 * A mat's bout numbers, kept in an order that makes sense after the draw is
 * re-arranged (owner, 2026-10-24: "in case of re arranging the bouts in the
 * draw, always renumber the remaining bouts to make sense" — and, asked, the
 * WHOLE mat, fought bouts included).
 *
 * Before this, a bout that came into being mid-event (a bye turned into a
 * real bout by LiveMove, an unnumbered bout an exhibition was linked to) took
 * the next free number, so a semifinal could read #78 behind its own final
 * #19. Here it is placed where it belongs instead — before the next bout of
 * its own division on that mat (within a division a later round always has a
 * higher `slot`), otherwise after that division's last bout — and the mat is
 * numbered from 1 in that order. A bout leaving the mat (decided as a bye)
 * leaves no gap behind.
 *
 * Numbers set by hand (`number_pinned`) are kept and the rest fill around
 * them: the run-order board's own rule (OrderBoard::numbersFor), not a copy.
 *
 * ⚠️ Fought bouts ARE renumbered — the owner's choice, knowing a bout page
 * (`/bout/{matchNo}`) addresses a bout by its number. Writes only; the
 * caller announces the change once its own transaction has committed.
 *
 * EVENT_RENUMBER_MATS — off, and nothing here runs.
 */
final class MatNumbering
{
    public function __construct(private OrderBoard $board) {}

    public static function enabled(): bool
    {
        return (bool) config('events.renumber_mats', false);
    }

    /**
     * Put this bout in its place on its mat and number the mat. A bout with
     * no mat is first seated on `$court` (or its division's mat) when one is
     * known. Returns the bout's number, null when it has no mat to be on.
     */
    public function place(EventMatch $bout, ?string $court = null): ?int
    {
        if (! $bout->court && ! $this->seat($bout, $court)) {
            return $bout->match_no;
        }

        $mat = $this->mat($bout->event_id, (string) $bout->court, $bout->day)
            ->reject(fn (EventMatch $m) => $m->id === $bout->id)
            ->values();

        $own = fn (EventMatch $m) => (int) $m->category_id === (int) $bout->category_id;
        $at = $mat->search(fn (EventMatch $m) => $own($m) && (int) $m->slot > (int) $bout->slot);

        if ($at === false) {
            $last = $mat->keys()->filter(fn ($i) => $own($mat[$i]))->last();
            $at = $last === null ? $mat->count() : $last + 1;
        }

        $order = $mat->all();
        array_splice($order, $at, 0, [$bout]);
        $this->number(collect($order));

        return $bout->match_no;
    }

    /** Number a mat from 1 in its present order — after a bout has left it. */
    public function close(int $eventId, ?string $court, ?int $day): void
    {
        if ($court) {
            $this->number($this->mat($eventId, $court, $day));
        }
    }

    /** @return Collection<int, EventMatch> the numbered bouts of one mat and day, in order */
    private function mat(int $eventId, string $court, ?int $day): Collection
    {
        return EventMatch::where('event_id', $eventId)
            ->where('court', $court)
            ->when($day === null, fn ($q) => $q->whereNull('day'), fn ($q) => $q->where('day', $day))
            ->whereNotNull('match_no')
            ->orderByRaw('CAST(match_no AS INTEGER)')
            ->orderBy('slot')
            ->get();
    }

    /** Seat an unseated bout on a mat its event actually runs. */
    private function seat(EventMatch $bout, ?string $court): bool
    {
        $mats = EventMatch::where('event_id', $bout->event_id)->whereNotNull('court')
            ->whereNotNull('match_no')->get(['court', 'day', 'category_id']);

        $division = $mats->firstWhere('category_id', $bout->category_id);
        $chosen = ($court !== null && $court !== '' && $mats->contains('court', $court)) ? $court : $division?->court;

        if (! $chosen) {
            return false;
        }

        $bout->court = $chosen;
        $bout->day = ($division && $division->court === $chosen ? $division : $mats->firstWhere('court', $chosen))?->day;

        return true;
    }

    /** @param  Collection<int, EventMatch>  $bouts */
    private function number(Collection $bouts): void
    {
        $numbers = $this->board->numbersFor($bouts);

        foreach ($bouts as $m) {
            if ((int) $m->match_no === $numbers[$m->id] && ! $m->isDirty()) {
                continue;
            }
            $m->match_no = $numbers[$m->id];
            $m->save();
        }
    }
}
