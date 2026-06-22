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
            $gold   = $final->winner === 'a' ? $final->a_name : $final->b_name;
            $silver = $final->winner === 'a' ? $final->b_name : $final->a_name;

            $bronze = [];
            foreach ($cat->matches->where('round', 'Semifinal') as $sf) {
                if (! $sf->winner) {
                    continue;
                }
                $loser = $sf->winner === 'a' ? $sf->b_name : $sf->a_name;
                if ($loser) {
                    $bronze[] = $loser;
                }
            }

            $medals = [
                ['place' => 1, 'name' => $gold],
                ['place' => 2, 'name' => $silver],
            ];
            foreach ($bronze as $b) {
                $medals[] = ['place' => 3, 'name' => $b];
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
        $breakNote = ($event->break_start && $event->break_end)
            ? ' · Break ' . \Carbon\Carbon::parse($event->break_start)->format('g:i A') . '–' . \Carbon\Carbon::parse($event->break_end)->format('g:i A')
            : '';

        $entries = [];
        if ($event->enrollment_ends_at) {
            $entries[] = [
                'label' => 'Registration closes',
                'date'  => $event->enrollment_ends_at->toDateString(),
                'note'  => 'Last day to enrol',
                'icon'  => 'bi-pencil-square',
            ];
        }
        $wi = $event->weigh_in_at ? \Carbon\Carbon::parse($event->weigh_in_at) : $start->copy();
        $entries[] = [
            'label' => 'Weigh-in & draw',
            'date'  => $wi->toDateString(),
            'note'  => 'Official weights recorded, brackets drawn',
            'icon'  => 'bi-clipboard-data',
        ];

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
            $entries[] = [
                'label' => 'Day ' . $d,
                'date'  => $start->copy()->addDays($d - 1)->toDateString(),
                'note'  => implode(' · ', $names) . ' — ' . $classCount . ' weight ' . \Illuminate\Support\Str::plural('class', $classCount) . $breakNote,
                'icon'  => 'bi-flag',
            ];
        }

        return $entries;
    }
}
