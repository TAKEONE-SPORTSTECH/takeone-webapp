<?php

namespace App\Events\Support\Tournament;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Sports\Combat\Engine\Scheduler;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The running order, as something an organiser can pick up and move.
 *
 * ── What a running order IS ────────────────────────────────────────────────
 *
 * Three numbers on a bout: `day`, `court`, `match_no`. Everything that reads a
 * queue — the wall board, the scoring table, "three bouts away", the caller —
 * reads those and nothing else (RunningOrder). So the whole of scheduling is
 * deciding what those numbers should be, and there are exactly two ways an
 * organiser wants to decide them:
 *
 *   · have the machine lay the whole day out (Scheduler::scheduleAndNumber,
 *     which already exists and is what a generated draw runs), and
 *   · then move the parts of it they disagree with, by hand.
 *
 * The second is what was missing. This is it — and deliberately nothing more:
 * it WRITES the same three columns the generator writes, so nothing downstream
 * learns that a human was involved. A bout that was dragged and a bout that was
 * generated are the same row.
 *
 * ── The one rule it will not let you break ─────────────────────────────────
 *
 * A bout cannot run before the bouts that feed it. That is not a preference an
 * organiser may override, it is the bracket: a semi-final whose quarter-final
 * has not happened has an empty corner, cannot be called, and a mat that
 * reaches it stalls with nobody able to explain why. So a move that would place
 * a bout ahead of a feeder is REFUSED, with the offending bout named.
 *
 * Everything else is allowed, because everything else is somebody's hall:
 * mixing divisions on one mat, running the finals first, emptying a mat, giving
 * one division two mats. The system's job is to keep the bracket honest, not to
 * have opinions about the timetable.
 */
class OrderBoard
{
    public function __construct(private Scheduler $scheduler) {}

    /* ==================== Reading ==================== */

    /**
     * The board: one column per mat, in running order, for one day.
     *
     * Decided bouts are included but marked — an organiser rearranging the
     * afternoon needs to see what has already happened above it, and a list
     * that silently drops them reads as though the mat is emptier than it is.
     *
     * @return array{day: int, days: int, mats: array<int, array{court: string, bouts: array<int, array<string, mixed>>}>}
     */
    public function plan(ClubEvent $event, ?int $day = null): array
    {
        $days = $this->scheduler->eventDayCount($event);
        $day = max(1, min($days, (int) ($day ?: $this->scheduler->currentDay($event))));

        $divisions = $event->categories()->get()->keyBy('id');

        $bouts = EventMatch::where('event_id', $event->id)
            ->where(fn ($q) => $q->where('day', $day)->orWhereNull('day'))
            ->orderByRaw('CASE WHEN match_no IS NULL THEN 1 ELSE 0 END')
            ->orderBy('match_no')
            ->orderBy('slot')
            ->get();

        $mats = [];

        foreach ($this->courts($event, $bouts) as $court) {
            $mats[] = [
                'court' => $court,
                'bouts' => $bouts->where('court', $court)->values()
                    ->map(fn (EventMatch $m) => $this->card($m, $divisions))->all(),
            ];
        }

        /*
         * The bouts belonging to no mat — a division the generator has never
         * placed, or one an organiser emptied a mat of. They are the SOURCE a
         * board is filled from, so they are a column of their own rather than
         * invisible: a bout that exists and is nowhere is the one thing a
         * scheduling screen must never hide.
         */
        $unplaced = $bouts
            ->filter(fn (EventMatch $m) => ! $m->court)
            /*
             * ...but NOT the byes. A bout with no mat and a winner already in
             * it is a bye: `Scheduler` strips its number, mat and day on
             * purpose, because nobody will ever hear it called. Listing those
             * here told an organiser that thirty-one bouts were unscheduled on
             * a day that was entirely scheduled — which is a false alarm on the
             * one screen whose job is telling them what still needs placing.
             */
            ->reject(fn (EventMatch $m) => $m->winner !== null)
            ->values();

        return [
            'day' => $day,
            'days' => $days,
            'mats' => $mats,
            'unplaced' => $unplaced->map(fn (EventMatch $m) => $this->card($m, $divisions))->all(),
        ];
    }

    /**
     * The mats this day has: the ones bouts are on, plus the ones the event is
     * configured for, so an organiser can drag onto an empty mat that does not
     * exist yet in the data.
     *
     * @return array<int, string>
     */
    private function courts(ClubEvent $event, Collection $bouts): array
    {
        $used = $bouts->pluck('court')->filter()->unique()->values()->all();
        $configured = (int) ($event->courts ?: 0);

        for ($i = 1; $i <= max(1, $configured); $i++) {
            $used[] = 'Mat '.$i;
        }

        $used = array_values(array_unique($used));

        // "Mat 10" after "Mat 9", not between "Mat 1" and "Mat 2".
        usort($used, fn ($a, $b) => strnatcasecmp($a, $b));

        return $used;
    }

    /** One bout, as the board draws it. */
    private function card(EventMatch $m, Collection $divisions): array
    {
        return [
            'id' => $m->id,
            'no' => $m->match_no,
            'court' => $m->court,
            'round' => $m->round,
            'phase' => $m->phase,
            'division' => $divisions->get($m->category_id)?->name,
            'division_id' => $m->category_id,
            'a' => $m->a_name,
            'b' => $m->b_name,
            // A bout with an empty corner cannot be called yet — the board
            // greys it, and the organiser can still place it.
            'runnable' => (bool) ($m->a_competitor_id && $m->b_competitor_id),
            'decided' => $m->winner !== null,
        ];
    }

    /* ==================== Moving ==================== */

    /**
     * Put one bout on one mat at one position, and renumber what that changes.
     *
     * `$position` is the index within the destination mat AFTER the move, zero
     * based, clamped. Everything on the source mat and the destination mat is
     * renumbered from 1, because `match_no` is a position rather than a name:
     * gaps in it are what make a caller announce bout 7 after bout 5.
     *
     * @return array{ok: bool, message?: string, blocked_by?: array<string, mixed>}
     */
    public function move(ClubEvent $event, int $matchId, string $court, int $position, ?int $day = null): array
    {
        $bout = EventMatch::where('event_id', $event->id)->find($matchId);

        if (! $bout) {
            return ['ok' => false, 'message' => __('events::events.order_bout_gone')];
        }

        /*
         * A bout that has been FOUGHT is history, not a plan. Moving it would
         * renumber a result the officiating log, the bracket and anybody's
         * memory of the hall all already refer to.
         */
        if ($bout->winner !== null) {
            return ['ok' => false, 'message' => __('events::events.order_bout_decided')];
        }

        $day = $day ?: ($bout->day ?: $this->scheduler->currentDay($event));

        return DB::transaction(function () use ($event, $bout, $court, $position, $day) {
            $from = $bout->court;

            // The destination mat as it will be, with the bout inserted.
            $target = $this->matBouts($event, $court, $day)
                ->reject(fn (EventMatch $m) => $m->id === $bout->id)
                ->values();

            $position = max(0, min($target->count(), $position));

            $bout->court = $court;
            $bout->day = $day;

            $target->splice($position, 0, [$bout]);

            // The bracket's own rule, checked against the order this move would
            // actually produce rather than against the one it started from.
            if ($clash = $this->feederClash($event, $bout, $target, $day)) {
                return ['ok' => false, 'message' => __('events::events.order_after_feeder', ['bout' => $clash]),
                    'blocked_by' => ['bout' => $clash]];
            }

            $this->renumber($target);

            // The mat it came off closes its gap.
            if ($from && $from !== $court) {
                $this->renumber($this->matBouts($event, $from, $day));
            }

            return ['ok' => true, 'message' => __('events::events.order_saved')];
        });
    }

    /**
     * Lay the whole day out again, from scratch.
     *
     * This is the generator that already exists and already runs when a draw is
     * built — offered here as a button so an organiser who has edited
     * themselves into a corner can start over. It is the one destructive act on
     * this screen: every hand placement goes.
     *
     * @return array<int, array<string, mixed>>  the per-day plan Scheduler returns
     */
    public function auto(ClubEvent $event): array
    {
        return $this->scheduler->scheduleAndNumber($event);
    }

    /* ==================== The rule ==================== */

    /**
     * Would this order put a bout ahead of something that feeds it?
     *
     * The bracket is derivable without storing it: within a division, rounds
     * sort by their lowest slot, and the bout at index i of a round is fed by
     * indices 2i and 2i+1 of the round before it — the same relation
     * Advancement walks forwards when it advances a winner. Here it is walked
     * backwards.
     *
     * Compared across the whole DAY, not just this mat: a semi-final on Mat 1
     * is just as impossible if its quarter-final is later on Mat 2.
     *
     * @param  Collection<int, EventMatch>  $target  the destination mat, in its new order
     * @return string|null  the feeder's label, or null when the order is sound
     */
    private function feederClash(ClubEvent $event, EventMatch $bout, Collection $target, int $day): ?string
    {
        $feeders = $this->feedersOf($event, $bout);

        if ($feeders->isEmpty()) {
            return null;
        }

        // Where everything sits once this move lands: the destination mat in its
        // new order, every other mat as it stands.
        $order = [];
        $seq = 0;

        foreach ($this->dayBouts($event, $day) as $m) {
            if ($m->court === $bout->court && $m->id !== $bout->id) {
                continue;   // replaced by the target list below
            }
            if ($m->id === $bout->id) {
                continue;
            }
            $order[$m->id] = [$m->court, (int) ($m->match_no ?? PHP_INT_MAX), ++$seq];
        }

        foreach ($target as $i => $m) {
            $order[$m->id] = [$bout->court, $i, ++$seq];
        }

        $placeOf = function (EventMatch $m) use ($order) {
            // A bout on another mat is compared by its own number; one on this
            // mat by its new index. Both answer "is it before or after".
            return $order[$m->id][1] ?? PHP_INT_MAX;
        };

        $mine = $placeOf($bout);

        foreach ($feeders as $feeder) {
            if ($feeder->winner !== null) {
                continue;   // already fought: it cannot be "after" anything
            }

            if ($placeOf($feeder) >= $mine) {
                return trim(($feeder->round ? $feeder->round.' ' : '').'#'.($feeder->match_no ?? '?'));
            }
        }

        return null;
    }

    /**
     * The one or two bouts whose winners fill this bout's corners.
     *
     * @return Collection<int, EventMatch>
     */
    private function feedersOf(ClubEvent $event, EventMatch $bout): Collection
    {
        if (! $bout->category_id) {
            return collect();
        }

        $all = EventMatch::where('event_id', $event->id)
            ->where('category_id', $bout->category_id)
            ->orderBy('slot')
            ->get();

        /*
         * ⚠️ THE THIRD-PLACE BOUT IS NOT PART OF THE LADDER.
         *
         * A naive round list built from slot order makes it look like a round of
         * the ladder — which is backwards twice over: it is fed by the two
         * SEMI-FINAL losers, and it is the one bout in a bracket whose
         * competitors both lost. Advancement drops it from its own round list
         * for exactly this reason (`reject(... === ROUND_THIRD)`), and this is
         * the same rejection walked the other way.
         *
         * Its slot has moved — it is cut BEFORE the final since 2026-09-18, so
         * it runs while the finalists rest — and neither this nor Advancement
         * cares, because both find it by ROUND NAME. That is the property that
         * makes the slot free to express running order.
         */
        $rounds = $all
            ->reject(fn (EventMatch $m) => $m->round === Advancement::ROUND_THIRD)
            ->groupBy('round')
            ->sortBy(fn (Collection $bouts) => $bouts->min('slot'))
            ->values();

        // Third place: both semi-finals have to have happened, and that is the
        // whole of its rule.
        if ($bout->round === Advancement::ROUND_THIRD) {
            $semis = $rounds->get($rounds->count() - 2);

            return $semis ? $semis->values() : collect();
        }

        foreach ($rounds as $r => $bouts) {
            $index = $bouts->search(fn (EventMatch $m) => $m->id === $bout->id);

            if ($index === false) {
                continue;
            }

            $previous = $r > 0 ? $rounds->get($r - 1) : null;

            if (! $previous) {
                return collect();   // a first-round bout is fed by nobody
            }

            return collect([$previous->get($index * 2), $previous->get($index * 2 + 1)])->filter();
        }

        return collect();
    }

    /* ==================== Plumbing ==================== */

    /** @return Collection<int, EventMatch> */
    private function matBouts(ClubEvent $event, string $court, int $day): Collection
    {
        return EventMatch::where('event_id', $event->id)
            ->where('court', $court)
            ->where('day', $day)
            ->orderByRaw('CASE WHEN match_no IS NULL THEN 1 ELSE 0 END')
            ->orderBy('match_no')
            ->orderBy('slot')
            ->get();
    }

    /** @return Collection<int, EventMatch> */
    private function dayBouts(ClubEvent $event, int $day): Collection
    {
        return EventMatch::where('event_id', $event->id)
            ->where('day', $day)
            ->orderBy('match_no')
            ->get();
    }

    /**
     * Number a mat from 1, in the order given.
     *
     * A DECIDED bout keeps its place in the list but is numbered too: it was
     * called, it was announced, and renumbering the day around it would rewrite
     * what the hall was told. Byes have no number and never gain one here —
     * `Scheduler` is the only thing that decides what is a bye.
     *
     * @param  Collection<int, EventMatch>  $bouts
     */
    private function renumber(Collection $bouts): void
    {
        $no = 0;

        foreach ($bouts as $m) {
            $m->match_no = ++$no;
            $m->save();
        }
    }
}
