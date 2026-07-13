<?php

namespace App\Sports\Combat\Engine;

use App\Models\ClubEvent;
use App\Models\EventCategory;
use App\Models\EventMatch;

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
     * Byes/walkovers get no number.
     *
     * @return array<int,array{matches:int,capacity:int,suggested:int,courts:int}> per-day plan
     */
    public function scheduleAndNumber(ClubEvent $event): array
    {
        $phaseRank = ['preliminary' => 0, 'quarterfinals' => 1, 'finals' => 2];
        $cats = $event->categories()->get()->keyBy('id');
        $capacity = $this->dailyCapacityPerCourt($event);

        $matches = EventMatch::where('event_id', $event->id)->orderBy('slot')->get();
        $isBye = fn ($m) => $m->winner && ((($m->a_name === null) xor ($m->b_name === null)));

        // Bucket contested bouts by day.
        $byDay = [];
        $byes = [];
        foreach ($matches as $m) {
            if ($isBye($m)) {
                $byes[] = $m->id;

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
                foreach ($ranks as $divGroups) {
                    $queues = array_values($divGroups);
                    do {
                        $any = false;
                        foreach ($queues as &$q) {
                            if ($m = array_shift($q)) {
                                $m->court = 'Mat '.$court;
                                $m->match_no = ++$no;
                                $m->save();
                                $any = true;
                            }
                        }
                        unset($q);
                    } while ($any);
                }
            }
        }

        if ($byes) {
            EventMatch::whereIn('id', $byes)->update(['match_no' => null, 'court' => null]);
        }

        return $plan;
    }
}
