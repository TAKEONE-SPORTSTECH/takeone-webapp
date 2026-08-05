<?php

namespace App\Sports\Combat\Engine;

use App\Models\ClubEvent;

/** Derives medalists and the public lifecycle timeline from the bracket data. */
class Results
{
    public function __construct(private Scheduler $scheduler) {}

    /**
     * Medalists per weight class, derived purely from the bracket results:
     * Final winner = gold, Final loser = silver, both Semifinal losers = bronze.
     * Only divisions whose Final is decided are returned.
     */
    public function podium(ClubEvent $event): array
    {
        $out = [];
        foreach ($event->categories()->with('matches')->orderBy('sort_order')->get() as $cat) {
            $final = $cat->matches->firstWhere('round', 'Final');
            if (! $final || ! $final->winner) {
                continue; // no champion yet
            }
            // Medals carry the ENTRY that won them, so a result can reach the
            // athlete's profile, a club medal tally and ranking points — not
            // just a name printed on a board.
            $medal = fn (int $place, $bout, string $side) => [
                'place' => $place,
                'name' => $bout->{$side.'_name'},
                'competitor_id' => $bout->{$side.'_competitor_id'},
            ];

            $win = $final->winner;
            $lose = $win === 'a' ? 'b' : 'a';

            $medals = [$medal(1, $final, $win), $medal(2, $final, $lose)];

            foreach ($cat->matches->where('round', 'Semifinal') as $sf) {
                if (! $sf->winner) {
                    continue;
                }
                $loserSide = $sf->winner === 'a' ? 'b' : 'a';
                if ($sf->{$loserSide.'_name'}) {
                    $medals[] = $medal(3, $sf, $loserSide);
                }
            }

            $out[] = ['division' => $cat->name, 'class' => $cat->weight_class, 'medals' => $medals];
        }

        return $out;
    }

    /**
     * Public lifecycle timeline derived from the real schedule:
     * Registration closes → Weigh-in & draw → Day-by-day (which sections + how
     * many classes + break). Each entry carries a date so the view derives status.
     */
    public function timeline(ClubEvent $event): array
    {
        $start = $event->date;
        if (! $start) {
            return [];
        }

        $cats = $event->categories()->get();
        $phaseLabel = ['preliminary' => 'Preliminaries', 'quarterfinals' => 'Quarter-finals', 'finals' => 'Finals'];

        // A competition day is not one block of time — it is play, a break, then
        // play again. Emitted as segments so each gets its own line with its own
        // clock, rather than one range plus a footnote nobody reads.
        $segments = $this->daySegments($event);
        // Only when there is no usable clock at all does the length stand alone.
        $breakNote = ($segments === [] && $event->break_minutes)
            ? ' · '.$event->break_minutes.' min break'
            : '';

        $entries = [];

        // When entries OPEN. Without it the timeline began at the deadline,
        // which tells someone what they have missed, not what they can do.
        if ($event->enrollment_starts_at) {
            $entries[] = [
                'label' => 'Enrolment opens',
                'date' => $event->enrollment_starts_at->toDateString(),
                'note' => 'Entries accepted from this day',
                'icon' => 'bi-calendar-plus',
            ];
        }

        if ($event->enrollment_ends_at) {
            $entries[] = [
                'label' => 'Registration closes',
                'date' => $event->enrollment_ends_at->toDateString(),
                'note' => 'Last day to enrol',
                'icon' => 'bi-pencil-square',
            ];
        }

        // Weigh-in: the one appointment a competitor must physically make, so it
        // carries its time and its place, not just a date.
        $wi = $event->weigh_in_at ? \Carbon\Carbon::parse($event->weigh_in_at) : $start->copy();
        $entries[] = [
            'label' => 'Weigh-in & draw',
            'date' => $wi->toDateString(),
            'time' => $event->weigh_in_at ? $wi->format('g:i A') : null,
            'note' => 'Official weights recorded, brackets drawn'
                .($event->location ? ' · '.$event->location : ''),
            'icon' => 'bi-clipboard-data',
        ];

        $lastCompetitionDay = null;

        for ($d = 1, $days = $this->scheduler->eventDayCount($event); $d <= $days; $d++) {
            $phasesOnDay = [];
            $classCount = 0;
            foreach ($cats as $c) {
                $hit = false;
                foreach (['preliminary', 'quarterfinals', 'finals'] as $ph) {
                    if ($this->scheduler->phaseDay($c, $ph) === $d) {
                        $phasesOnDay[$ph] = true;
                        $hit = true;
                    }
                }
                $classCount += $hit ? 1 : 0;
            }
            if (! $phasesOnDay) {
                continue;
            }
            $names = array_map(fn ($p) => $phaseLabel[$p], array_keys($phasesOnDay));
            $lastCompetitionDay = $start->copy()->addDays($d - 1);

            $entries[] = [
                'label' => 'Tournament Day '.$d,
                'date' => $lastCompetitionDay->toDateString(),
                // Play / break / play, each its own line.
                'segments' => $segments,
                'note' => implode(' · ', $names).' — '.$classCount.' weight '.\Illuminate\Support\Str::plural('class', $classCount).$breakNote,
                'icon' => 'bi-flag',
            ];
        }

        // The finish. A timeline that stops at the last bout leaves out the part
        // everyone came for, and a competitor's family needs to know whether to
        // stay for it.
        if ($lastCompetitionDay) {
            $entries[] = [
                'label' => 'Awards & finish',
                'date' => $lastCompetitionDay->toDateString(),
                'time' => $event->end_time ? \Carbon\Carbon::parse($event->end_time)->format('g:i A') : null,
                'note' => 'Medals presented'.($event->prize ? ' · '.$event->prize : '').' — right after the finals',
                'icon' => 'bi-award-fill',
            ];
        }

        return $entries;
    }

    /**
     * A competition day split into play / break / play.
     *
     * `approx` flags a break the organiser sized but never placed on the clock —
     * we centre it in the bout time. The view currently prints it like any other
     * time; the flag is kept so a caller that wants to qualify it still can.
     *
     * The durable fix is upstream: make break_start/break_end the input on the
     * event form and derive break_minutes from them, so the time shown is always
     * one the organiser actually set.
     *
     * @return array<int, array{label:string, time:string, kind:string, approx:bool}>
     */
    private function daySegments(ClubEvent $event): array
    {
        if (! $event->start_time || ! $event->end_time) {
            return [];
        }

        $s = $this->minsOfDay($event->start_time);
        $t = $this->minsOfDay($event->end_time);
        if ($t <= $s) {
            return [];
        }

        $exact = $event->break_start && $event->break_end;
        $len = $exact
            ? max(0, $this->minsOfDay($event->break_end) - $this->minsOfDay($event->break_start))
            : (int) ($event->break_minutes ?? 0);

        // No break, or one that would swallow the day: a single block of play.
        if ($len <= 0 || $len >= ($t - $s)) {
            return [[
                'label' => 'Play', 'kind' => 'play', 'approx' => false,
                'time' => $this->clock($s).' – '.$this->clock($t),
            ]];
        }

        $bStart = $exact
            ? $this->minsOfDay($event->break_start)
            : $s + intdiv($t - $s - $len, 2);
        $bEnd = min($t, $bStart + $len);

        return [
            ['label' => 'Play', 'kind' => 'play', 'approx' => false,
                'time' => $this->clock($s).' – '.$this->clock($bStart)],
            ['label' => 'Break', 'kind' => 'break', 'approx' => ! $exact,
                'time' => $this->clock($bStart).' – '.$this->clock($bEnd)],
            ['label' => 'Play', 'kind' => 'play', 'approx' => false,
                'time' => $this->clock($bEnd).' – '.$this->clock($t)],
        ];
    }

    /** "09:30" / "09:30:00" -> minutes since midnight. */
    private function minsOfDay(string $time): int
    {
        [$h, $m] = array_pad(array_map('intval', explode(':', $time)), 2, 0);

        return $h * 60 + $m;
    }

    /** Minutes since midnight -> "9:30 AM", wrapping safely past midnight. */
    private function clock(int $mins): string
    {
        $mins = ((int) $mins % 1440 + 1440) % 1440;

        return \Carbon\Carbon::createFromTime(intdiv($mins, 60), $mins % 60)->format('g:i A');
    }
}
