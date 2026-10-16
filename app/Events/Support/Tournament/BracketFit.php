<?php

namespace App\Events\Support\Tournament;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Sports\Combat\Engine\DrawEngine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A bracket that SHRINKS when people leave it (owner, 2026-10-16: "make the
 * brackets expand and shrink based on the number of participants").
 *
 * Growing is LateEntry's job (a full board takes one more by adding a round at
 * the front). This is the other half: when the people standing in a board fit
 * a ladder half its size, it is laid again at that size — so a bracket of 8
 * left with 4 becomes a bracket of 4, not 4 people and 4 empty corners.
 *
 * The people keep their places as far as a smaller board allows: whoever was
 * paired stays paired, and the order down the sheet is kept. Only the people
 * already ON the board are laid; anybody in the basket stays there.
 *
 * ⚠️ Never once anything has happened. A started event, a division with a
 * fought bout, a hand-typed name in a corner (no entry to re-lay it from) or a
 * pool is left exactly as it is — `shrink()` answers false and changes nothing.
 * Called only by the draw desk's own moves (the `fit` flag), never by the
 * ordinary board.
 */
final class BracketFit
{
    public function __construct(private DrawEngine $draws) {}

    /** @return bool whether the board was laid again, smaller */
    public function shrink(ClubEvent $event, EventCategory $category): bool
    {
        if ($event->hasStarted() || $event->hasEnded()
            || ($category->format ?: EventCategory::FORMAT_KNOCKOUT) === EventCategory::FORMAT_ROUND_ROBIN
            || $category->isHeading()
            || $category->matches()->contested()->exists()) {
            return false;
        }

        $first = $this->firstRound($category);
        $size = $first->count() * 2;

        // Who stands where, read down the sheet: a pair stays a unit.
        $units = [];
        foreach ($first as $bout) {
            $unit = [];
            foreach (['a', 'b'] as $side) {
                if ($bout->{$side.'_name'} && ! $bout->{$side.'_competitor_id'}) {
                    return false;   // a hand-typed name: nothing to re-lay it from
                }
                if ($id = $bout->{$side.'_competitor_id'}) {
                    $unit[] = (int) $id;
                }
            }
            if ($unit !== []) {
                $units[] = $unit;
            }
        }

        $people = $units === [] ? [] : array_merge(...$units);
        $n = count($people);

        if ($n === 0) {
            return false;
        }

        $target = 2;
        while ($target < $n) {
            $target *= 2;
        }

        if ($target >= $size) {
            return false;
        }

        // Exactly target/2 bouts: keep the first (n - target/2) pairs, split the rest.
        $bouts = intdiv($target, 2);
        $pairsWanted = $n - $bouts;
        $laid = [];
        foreach ($units as $unit) {
            if (count($unit) === 2 && $pairsWanted > 0) {
                $laid[] = $unit;
                $pairsWanted--;
            } else {
                foreach ($unit as $id) {
                    $laid[] = [$id];
                }
            }
        }

        // Too few pairs survived: pair singles down the sheet until it fits.
        while (count($laid) > $bouts) {
            $i = $this->single($laid, 0);
            $j = $this->single($laid, $i + 1);
            $laid[$i][] = $laid[$j][0];
            array_splice($laid, $j, 1);
        }

        $order = [];
        foreach ($laid as $unit) {
            $order[] = $unit[0];
            $order[] = $unit[1] ?? null;
        }

        DB::transaction(function () use ($event, $category, $order, $people) {
            $this->draws->build($event, $category, paidOnly: false, order: $order, only: $people);
            $category->update(['draw_state' => 'manual', 'podium' => null]);
        });

        return true;
    }

    /** The first bout from $from holding one person. */
    private function single(array $laid, int $from): int
    {
        for ($i = $from; $i < count($laid); $i++) {
            if (count($laid[$i]) === 1) {
                return $i;
            }
        }

        return $from;
    }

    /** @return Collection<int, EventMatch> */
    private function firstRound(EventCategory $category): Collection
    {
        $matches = $category->matches()->orderBy('slot')->get()
            ->reject(fn (EventMatch $m) => $m->round === DrawEngine::ROUND_THIRD);

        if ($matches->isEmpty()) {
            return collect();
        }

        return $matches->groupBy('round')
            ->sortBy(fn (Collection $bouts) => $bouts->min('slot'))
            ->first()->sortBy('slot')->values();
    }
}
