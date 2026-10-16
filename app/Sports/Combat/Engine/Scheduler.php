<?php

namespace App\Sports\Combat\Engine;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;

/**
 * Day/court scheduling + per-mat match numbering for a combat championship.
 * Sport-agnostic — driven entirely by the event timing and each division's
 * schedule (phase → day[/court]).
 */
class Scheduler
{
    /** Number of distinct days the event spans (date → end_date inclusive). */
    public function eventDayCount(ClubEvent $event): int
    {
        if ($event->end_date && $event->date) {
            return max(1, (int) $event->date->diffInDays($event->end_date) + 1);
        }

        return 1;
    }

    /**
     * The competition day the venue is on right now, 1-based.
     *
     * A mat's queue, its wall board and its next-bout are all about TODAY: a
     * board that lists tomorrow's bouts under today's is not a running order,
     * it is two of them shuffled together. Bout numbers restart each morning,
     * so without this the same mat shows two bout #1s and the caller cannot say
     * which one they mean.
     *
     * Clamped at both ends deliberately. Before the doors open there is no
     * elapsed day, and the thing worth showing is day one's order; after the
     * last day the event is history and the final day's board is what belongs
     * on the wall. Neither end should ever produce an empty queue for a reason
     * nobody standing in the hall can see.
     */
    public function currentDay(ClubEvent $event): int
    {
        if (! $event->date) {
            return 1;
        }

        // The competition day rolls over at the HALL's midnight, not UTC's.
        $elapsed = (int) $event->atLocal($event->date)->diffInDays($event->localNow()->startOfDay(), absolute: false);

        return max(1, min($this->eventDayCount($event), $elapsed + 1));
    }

    /** Auto-spread default: divisions distributed across days, all phases on the base day. */
    public function defaultSchedule(ClubEvent $event, EventCategory $cat): array
    {
        $days = $this->eventDayCount($event);
        $base = $days > 1 ? ((((int) $cat->sort_order - 1) % $days) + 1) : 1;

        return ['preliminary' => $base, 'quarterfinals' => $base, 'finals' => $base];
    }

    /** Day for a phase (schedule value may be an int day or {day,court}). */
    public function phaseDay(EventCategory $cat, string $phase): int
    {
        $s = $cat->schedule[$phase] ?? null;

        return (int) (is_array($s) ? ($s['day'] ?? 1) : ($s ?: 1));
    }

    /** Owner-pinned court for a phase, or null = auto-assign. */
    public function phaseCourt(EventCategory $cat, string $phase): ?int
    {
        $s = $cat->schedule[$phase] ?? null;

        return is_array($s) && ! empty($s['court']) ? (int) $s['court'] : null;
    }

    /** Minutes-since-midnight for a "HH:MM[:SS]" time string. */
    public function minsOfDay(string $t): int
    {
        [$h, $m] = array_map('intval', array_pad(explode(':', $t), 2, '0'));

        return $h * 60 + $m;
    }

    /** How many bouts one court can run in a competition day, from the event's timing. */
    public function dailyCapacityPerCourt(ClubEvent $event): int
    {
        $start = $event->start_time ? $this->minsOfDay($event->start_time) : 9 * 60;
        $end = $event->end_time ? $this->minsOfDay($event->end_time) : $start + 480; // default 8h day
        $break = ($event->break_start && $event->break_end)
            ? max(0, $this->minsOfDay($event->break_end) - $this->minsOfDay($event->break_start))
            : (int) ($event->break_minutes ?? 0);
        $window = max(0, $end - $start - $break);
        $per = max(1, (int) ($event->minutes_per_match ?: 8));

        return max(1, intdiv($window, $per));
    }

    /**
     * Schedule + number every bout. Per day: courts = owner override or the
     * time-based suggestion; each division is placed on a mat (auto round-robin
     * unless pinned); within a mat, bouts run phase-by-phase (prelim→QF→finals)
     * interleaved across divisions, numbered PER MAT (Mat 1 · #1, #2 …).
     * Byes and walkovers get no number — every bout already decided, whether
     * one corner was empty or both.
     *
     * ⚠️ UNLESS THE ORGANISER HAS WRITTEN THE DAY DOWN. A running order
     * (`event_run_slots`, arranged on the day board and published on the event
     * page) says "10:00 Group B, 10:30 Group E, …" — and a mat that then
     * interleaves every division across the whole day contradicts the sheet the
     * hall is standing there reading. So when a sheet exists, a mat runs
     * DIVISION BY DIVISION in the order the organiser arranged: all of Group B,
     * prelims through its final, until it has a first, second and third — then
     * the next division. Phase order still decides the sequence WITHIN a
     * division; it simply stops reaching across them.
     *
     * The sheet already assumed this: `App\Events\Support\RunSheet` sizes each
     * slot from that division's own bout count, which is only a truthful clock
     * if the division runs as one block.
     *
     * Additive: an event with no running order is numbered exactly as before,
     * down to the bout number. Divisions the sheet does not mention keep their
     * old interleaved behaviour and run after the ones it does.
     *
     * @return array<int,array{matches:int,capacity:int,suggested:int,courts:int}> per-day plan
     */
    /**
     * A mat's bouts, as groups to be run one after another.
     *
     * WITHOUT a running order (`$sheetPos` empty) a group is a PHASE: every
     * division's preliminaries interleaved, then every quarter-final, then
     * every final — the behaviour this engine has always had, returned
     * unchanged so an event nobody has written a day for is numbered exactly
     * as it was before.
     *
     * WITH one, a group is a DIVISION, in the order the organiser arranged it:
     * that division's bouts, phase by phase, until it has produced a podium —
     * then the next division. Divisions the sheet does not mention have no
     * place in it, so they keep the phase-interleaved shape and run after
     * everything that does (PHP's sort is stable, so their relative order is
     * the one they already had).
     *
     * @param  array<int, array<int, array<int, EventMatch>>>  $ranks  phase rank => division id => bouts
     * @param  array<int, int>  $sheetPos  division id => its place in the running order
     * @return array<int, array<int, array<int, EventMatch>>>  groups of division queues
     */
    /**
     * The mat's order when the running order places divisions PART by part.
     *
     * Each slot becomes one queue — its division's bouts in the parts it names
     * (all of them for a whole division), in round order — drained before the
     * next slot starts, so nothing interleaves. Bouts no slot claims run
     * after, phase by phase, as matOrder() leaves divisions off the sheet.
     *
     * @param  list<array{0: int, 1: ?array}>  $slots  [category id, parts or null] in running order
     */
    /**
     * The mat's order when the running order has GROUPS, each with a method.
     *
     * Walks the running order group by group. Each slot claims its
     * division's bouts (the parts it names, or all of them) exactly as
     * matOrderByParts() does; what changes is how a session's claims are
     * sequenced:
     *
     *   class  — slot after slot, each drained before the next (as before);
     *   round  — every slot's earliest round first, then the next round, the
     *            bronze bouts before the finals and the finals last, slots in
     *            running order within a round (World Taekwondo);
     *   manual — the order the bouts already hold (their current number),
     *            anything without one after, in class order.
     *
     * One queue per group, already in order, so the caller's round-robin
     * never interleaves it. A division of another mat claims nothing here.
     * Bouts no slot claims run after, phase by phase, as before.
     *
     * @param  list<array{cat: int, parts: ?array, mode: string, session: string}>  $plan
     */
    private function matOrderBySessions(array $ranks, array $plan): array
    {
        $perDivision = [];
        foreach ($ranks as $divGroups) {
            foreach ($divGroups as $catId => $bouts) {
                foreach ($bouts as $bout) {
                    $perDivision[$catId][] = $bout;
                }
            }
        }

        // Consecutive plan entries of one session run together.
        $runs = [];
        foreach ($plan as $entry) {
            $last = array_key_last($runs);
            if ($last !== null && $runs[$last]['session'] === $entry['session']) {
                $runs[$last]['entries'][] = $entry;
            } else {
                $runs[] = ['session' => $entry['session'], 'mode' => $entry['mode'], 'entries' => [$entry]];
            }
        }

        $claimed = [];
        $groups = [];
        foreach ($runs as $run) {
            $queues = [];
            foreach ($run['entries'] as $entry) {
                $queue = [];
                foreach ($perDivision[$entry['cat']] ?? [] as $bout) {
                    if (! isset($claimed[$bout->id])
                        && ($entry['parts'] === null || in_array(\App\Events\Support\BoutPart::of($bout->round), $entry['parts'], true))) {
                        $queue[] = $bout;
                        $claimed[$bout->id] = true;
                    }
                }
                if ($queue) {
                    $queues[] = $queue;
                }
            }

            if (! $queues) {
                continue;
            }

            if ($run['mode'] === \App\Events\Support\RunSessions::CLASS_AT_A_TIME) {
                foreach ($queues as $queue) {
                    $groups[] = [$queue];
                }

                continue;
            }

            // Flatten, remembering each bout's slot and its place in that slot.
            $all = [];
            foreach ($queues as $k => $queue) {
                foreach ($queue as $i => $bout) {
                    $all[] = ['b' => $bout, 'k' => $k, 'i' => $i, 'depth' => $this->roundDepth($bout->round)];
                }
            }

            if ($run['mode'] === \App\Events\Support\RunSessions::ROUND_BY_ROUND) {
                usort($all, fn ($x, $y) => [$y['depth'], $x['k'], $x['i']] <=> [$x['depth'], $y['k'], $y['i']]);
            } else {
                // MANUAL: the order the bouts already hold; unnumbered last, in class order.
                usort($all, fn ($x, $y) => [$x['b']->match_no === null ? 1 : 0, (int) $x['b']->match_no, $x['k'], $x['i']]
                    <=> [$y['b']->match_no === null ? 1 : 0, (int) $y['b']->match_no, $y['k'], $y['i']]);
            }

            $groups[] = [array_column($all, 'b')];
        }

        foreach ($ranks as $divGroups) {
            $tail = [];
            foreach ($divGroups as $catId => $bouts) {
                $left = array_values(array_filter($bouts, fn ($b) => ! isset($claimed[$b->id])));
                if ($left) {
                    $tail[$catId] = $left;
                }
            }
            if ($tail) {
                $groups[] = $tail;
            }
        }

        return $groups;
    }

    /**
     * How far a round is from its division's final: 0 the final, 0.5 the
     * bronze bout (fought before the finals), 1 the semi-finals, 2 the
     * quarter-finals, 3 a round of 16… Anything else (a pool's rounds) comes
     * first, before the ladder.
     */
    private function roundDepth(?string $round): float
    {
        return match (true) {
            $round === 'Final' => 0.0,
            $round === 'Third place' => 0.5,
            $round === 'Semifinal' => 1.0,
            $round === 'Quarterfinal' => 2.0,
            (bool) preg_match('/^Round of (\d+)$/', (string) $round, $m) => max(3.0, log((int) $m[1], 2) - 1),
            default => 99.0,
        };
    }

    private function matOrderByParts(array $ranks, array $slots): array
    {
        $perDivision = [];
        foreach ($ranks as $divGroups) {
            foreach ($divGroups as $catId => $bouts) {
                foreach ($bouts as $bout) {
                    $perDivision[$catId][] = $bout;
                }
            }
        }

        $claimed = [];
        $groups = [];
        foreach ($slots as [$catId, $parts]) {
            $queue = [];
            foreach ($perDivision[$catId] ?? [] as $bout) {
                if (! isset($claimed[$bout->id])
                    && ($parts === null || in_array(\App\Events\Support\BoutPart::of($bout->round), $parts, true))) {
                    $queue[] = $bout;
                    $claimed[$bout->id] = true;
                }
            }
            if ($queue) {
                $groups[] = [$queue];
            }
        }

        foreach ($ranks as $divGroups) {
            $tail = [];
            foreach ($divGroups as $catId => $bouts) {
                $left = array_values(array_filter($bouts, fn ($b) => ! isset($claimed[$b->id])));
                if ($left) {
                    $tail[$catId] = $left;
                }
            }
            if ($tail) {
                $groups[] = $tail;
            }
        }

        return $groups;
    }

    private function matOrder(array $ranks, array $sheetPos): array
    {
        if (! $sheetPos) {
            return array_values($ranks);
        }

        // Division-major: division id => phase rank => bouts.
        $byDivision = [];
        foreach ($ranks as $rank => $divGroups) {
            foreach ($divGroups as $catId => $bouts) {
                $byDivision[$catId][$rank] = $bouts;
            }
        }

        $onSheet = [];
        $rest = [];
        foreach ($byDivision as $catId => $byRank) {
            isset($sheetPos[$catId]) ? $onSheet[$catId] = $byRank : $rest[$catId] = $byRank;
        }

        uksort($onSheet, fn ($a, $b) => $sheetPos[$a] <=> $sheetPos[$b]);

        $groups = [];
        foreach ($onSheet as $catId => $byRank) {
            ksort($byRank);
            // One division = one group holding one queue, so the caller's
            // round-robin drains it in phase order and nothing interleaves.
            foreach ($byRank as $bouts) {
                $groups[] = [$bouts];
            }
        }

        /*
         * Anything the sheet never mentions runs after it, in the phase-by-
         * phase shape it had — a division left off the running order is not a
         * division the organiser has re-ordered, and silently promoting it to
         * the front of the mat would be this engine inventing a decision.
         */
        foreach ($ranks as $rank => $divGroups) {
            $tail = array_intersect_key($divGroups, $rest);
            if ($tail) {
                $groups[] = $tail;
            }
        }

        return $groups;
    }

    public function scheduleAndNumber(ClubEvent $event): array
    {
        $phaseRank = ['preliminary' => 0, 'quarterfinals' => 1, 'finals' => 2];
        $cats = $event->categories()->get()->keyBy('id');

        /*
         * The organiser's own order, division => its place in the day.
         * Read across every day and keyed by division, because a mat's order
         * is decided per (day, mat) further down and a division sits on one
         * day only. Empty for an event nobody has written a running order for,
         * which is the switch that keeps the old behaviour exactly as it was.
         */
        $sheetPos = \App\Events\Models\EventRunSlot::where('event_id', $event->id)
            ->whereNotNull('category_id')
            ->orderBy('day')->orderBy('position')->orderBy('id')
            ->pluck('category_id')
            ->values()
            ->flip()          // category_id => rank
            ->all();

        /*
         * …and the running order as SLOTS, when any slot runs only part of a
         * division (App\Events\Support\BoutPart — the owner, 2026-10-07:
         * preliminaries first, finals at the end of the day). Empty for every
         * running order without parts, which keeps the division-by-division
         * numbering above exactly as it was.
         */
        $sheetSlots = \App\Events\Models\EventRunSlot::where('event_id', $event->id)
            ->whereNotNull('category_id')
            ->orderBy('day')->orderBy('position')->orderBy('id')
            ->get(['category_id', 'parts']);
        $sheetSlots = $sheetSlots->contains(fn ($slot) => $slot->parts !== null)
            ? $sheetSlots->map(fn ($slot) => [(int) $slot->category_id, $slot->parts])->all()
            : [];
        /*
         * …and its GROUPS, each with its own method (App\Events\Support\
         * RunSessions): one class at a time, round by round, or manual, every
         * group finished before the next. Empty unless the running order has a
         * group, which keeps the two paths above exactly as they were.
         */
        $sessionPlan = \App\Events\Support\RunSessions::inUse($event)
            ? \App\Events\Support\RunSessions::plan($event)
            : [];
        $capacity = $this->dailyCapacityPerCourt($event);

        $matches = EventMatch::where('event_id', $event->id)->orderBy('slot')->get();

        /*
         * A bout that is already DECIDED gets no number, because the running
         * order is the list of what is still to be fought and a number in it
         * that nobody will ever hear called is a hole an operator has to
         * explain.
         *
         * ⚠️ This used to read `(a_name === null) XOR (b_name === null)` — one
         * corner empty, exactly one. A bout with a winner and BOTH corners
         * empty failed that test (false xor false is false), so it was numbered
         * like a real bout and then hidden by RunningOrder::upcomingBouts(),
         * which excludes anything decided. The mat's running order therefore
         * started at #2, and #4 was missing too. Seen on the Victory BJJ
         * Championship's Mat 1 (2026-09-10): eighteen ordinary byes were
         * unnumbered correctly and two zero-corner walkovers were not.
         *
         * Zero-corner bouts are ordinary in a thin division: a bracket is drawn
         * to a power of two and the draw resolves the slots nobody entered.
         *
         * A corner counts as EMPTY only when it has neither an entry nor a
         * name — deliberately stricter than the name alone, so a bout that was
         * genuinely fought can never lose its number on a re-schedule because
         * a denormalised name happened to be missing.
         */
        $emptyCorner = fn ($m, string $side) => ! $m->{$side.'_competitor_id'} && $m->{$side.'_name'} === null;
        $isBye = fn ($m) => $m->winner && ($emptyCorner($m, 'a') || $emptyCorner($m, 'b'));

        // Bucket contested bouts by day.
        $byDay = [];
        $byes = [];
        /*
         * Numbers an organiser set by hand (`number_pinned` — the tablet's
         * "Bout no.", a drag on the day-order board). The bout keeps its mat,
         * its day and its number; everything else on that mat is numbered
         * AROUND it. "day|mat" => [number => true].
         */
        $taken = [];
        foreach ($matches as $m) {
            if ($isBye($m)) {
                $byes[] = $m->id;

                continue;
            }
            if ($m->number_pinned && $m->match_no && $m->court && $m->day) {
                $taken[$m->day.'|'.$m->court][(int) $m->match_no] = true;

                continue;
            }
            $cat = $cats->get($m->category_id);
            if (! $cat) {
                continue;
            }
            $phase = $m->phase ?: 'preliminary';
            $byDay[$this->phaseDay($cat, $phase)][] = ['m' => $m, 'cat' => $cat, 'phase' => $phase, 'rank' => $phaseRank[$phase] ?? 0];
        }

        $plan = [];
        ksort($byDay);
        foreach ($byDay as $day => $items) {
            $count = count($items);
            $suggested = max(1, (int) ceil($count / $capacity));
            $courts = (int) ($event->day_courts[$day] ?? 0) ?: ((int) $event->courts ?: $suggested);
            $plan[$day] = ['matches' => $count, 'capacity' => $capacity, 'suggested' => $suggested, 'courts' => $courts];

            // Auto court per division (round-robin) unless the phase pins one.
            $dayCats = collect($items)->pluck('cat')->unique('id')->values();
            $autoCourt = [];
            foreach ($dayCats as $i => $c) {
                $autoCourt[$c->id] = ($i % $courts) + 1;
            }

            // Group by court → phase rank → division.
            $byCourt = [];
            foreach ($items as $it) {
                $court = $this->phaseCourt($it['cat'], $it['phase']) ?: ($autoCourt[$it['cat']->id] ?? 1);
                $court = min(max(1, $court), $courts);
                $byCourt[$court][$it['rank']][$it['cat']->id][] = $it['m'];
            }

            foreach ($byCourt as $court => $ranks) {
                ksort($ranks);
                $no = 0;

                // Numbering restarts per mat per day, so the day is part of a
                // bout's identity in the running order — persisted here because
                // it is resolved here, and everything downstream (the wall
                // board, the scoring queue, "bouts ahead") has to be able to
                // order by it. See the migration.
                $pinned = $taken[$day.'|Mat '.$court] ?? [];
                $number = function (EventMatch $m) use (&$no, $court, $day, $pinned) {
                    $m->court = 'Mat '.$court;
                    $m->day = (int) $day;
                    do {
                        ++$no;
                    } while (isset($pinned[$no]));
                    $m->match_no = $no;
                    $m->save();
                };

                $groups = $sessionPlan
                    ? $this->matOrderBySessions($ranks, $sessionPlan)
                    : ($sheetSlots ? $this->matOrderByParts($ranks, $sheetSlots) : $this->matOrder($ranks, $sheetPos));
                foreach ($groups as $group) {
                    $queues = array_values($group);
                    do {
                        $any = false;
                        foreach ($queues as &$q) {
                            if ($m = array_shift($q)) {
                                $number($m);
                                $any = true;
                            }
                        }
                        unset($q);
                    } while ($any);
                }
            }
        }

        if ($byes) {
            EventMatch::whereIn('id', $byes)->update(['match_no' => null, 'number_pinned' => false, 'court' => null, 'day' => null]);
        }

        return $plan;
    }
}
