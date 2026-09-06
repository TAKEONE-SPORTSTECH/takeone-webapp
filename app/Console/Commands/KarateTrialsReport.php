<?php

namespace App\Console\Commands;

use App\Models\ClubEvent;
use App\Models\EventCategory;
use App\Models\EventMatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * What happened, group by group and second by second.
 *
 * A round robin has no final, so nothing in the draw says who won a group — the
 * table does, and the table is a reading of the results rather than a structure
 * the engine keeps. This is that reading, plus the bout-by-bout timeline the
 * scoreboard recorded while the competition ran.
 *
 * Read-only, deliberately: it never writes a standing back onto the event. A
 * report that quietly becomes the source of truth is a report nobody can
 * re-derive.
 *
 *   php artisan karate:trials-report <event-uuid>            standings
 *   php artisan karate:trials-report <event-uuid> --timeline  every action
 *   php artisan karate:trials-report <event-uuid> --csv=out/  files for a report
 */
class KarateTrialsReport extends Command
{
    protected $signature = 'karate:trials-report
        {event : The event uuid}
        {--timeline : Print every recorded action, bout by bout}
        {--csv= : Write standings.csv, bouts.csv and timeline.csv into this directory}';

    protected $description = 'Round-robin standings and the full recorded timeline for a karate event';

    public function handle(): int
    {
        $event = ClubEvent::where('uuid', $this->argument('event'))->first();

        if (! $event) {
            $this->error('No event with that uuid.');

            return self::FAILURE;
        }

        $this->info($event->title);
        $this->line($event->date->toDateString().' · '.$event->start_time.' · '.($event->tenant?->club_name ?? '—'));

        $groups = EventCategory::where('event_id', $event->id)->orderBy('sort_order')->get();
        $standings = [];
        $boutRows = [];

        foreach ($groups as $group) {
            $bouts = EventMatch::where('category_id', $group->id)
                ->orderByRaw('CAST(match_no AS INTEGER)')->get();

            $table = $this->standings($bouts);
            $standings[$group->name] = $table;

            $this->newLine();
            $this->line("<options=bold>{$group->name}</>");
            $this->table(
                ['#', 'Athlete', 'P', 'W', 'L', 'Pts for', 'Pts against', 'Diff'],
                collect($table)->values()->map(fn ($r, $i) => [
                    $i + 1, $r['name'], $r['played'], $r['won'], $r['lost'],
                    $r['for'], $r['against'], sprintf('%+d', $r['for'] - $r['against']),
                ])->all(),
            );

            foreach ($bouts as $b) {
                $boutRows[] = [
                    'bout' => $b->match_no,
                    'group' => $group->name,
                    'aka' => $b->a_name,
                    'ao' => $b->b_name,
                    'score' => ($b->a_score ?? 0).'-'.($b->b_score ?? 0),
                    'winner' => $b->winner === 'a' ? $b->a_name : ($b->winner === 'b' ? $b->b_name : ''),
                    'by' => $b->win_reason ?: ($b->winner ? 'points' : ''),
                    'note' => $b->win_note,
                    'status' => $b->status,
                ];
            }
        }

        $this->newLine();
        $this->line('<options=bold>Bouts</>');
        $this->table(
            ['Bout', 'Group', 'AKA', 'AO', 'Score', 'Winner', 'By', 'Status'],
            collect($boutRows)->map(fn ($r) => [
                $r['bout'], $r['group'], $r['aka'], $r['ao'], $r['score'], $r['winner'], $r['by'], $r['status'],
            ])->all(),
        );

        $timeline = $this->timeline($event);

        if ($this->option('timeline')) {
            $this->newLine();
            $this->line('<options=bold>Timeline</>');
            $this->table(
                ['Bout', 'Seq', 'Action', 'Side', 'Pts', 'Score', 'Bout clock', 'Wall clock'],
                collect($timeline)->map(fn ($r) => [
                    $r['bout'], $r['sequence'], $r['action'], $r['side'], $r['points'],
                    $r['score'], $r['clock'], $r['wall'],
                ])->all(),
            );
        } else {
            $this->newLine();
            $this->line('Recorded actions: '.count($timeline).'  (--timeline to print them)');
        }

        if ($dir = $this->option('csv')) {
            $this->writeCsvs($dir, $standings, $boutRows, $timeline);
        }

        return self::SUCCESS;
    }

    /**
     * A group's table.
     *
     * Ordered by wins, then by points difference, then by points scored — the
     * ordinary karate pool convention. Where those are all equal the athletes
     * are genuinely level and the order here says nothing: a tie that decides a
     * selection is a decision for the officials, not for a sort function, so it
     * is left visible rather than broken silently.
     */
    private function standings($bouts): array
    {
        $rows = [];

        $touch = function (array &$rows, ?string $name) {
            if ($name && ! isset($rows[$name])) {
                $rows[$name] = ['name' => $name, 'played' => 0, 'won' => 0, 'lost' => 0, 'for' => 0, 'against' => 0];
            }
        };

        foreach ($bouts as $b) {
            $touch($rows, $b->a_name);
            $touch($rows, $b->b_name);

            // Only decided bouts count. An unplayed fixture in the table would
            // read as a nil-nil draw, which karate does not have.
            if (! $b->winner) {
                continue;
            }

            $a = (int) $b->a_score;
            $bs = (int) $b->b_score;

            $rows[$b->a_name]['played']++;
            $rows[$b->b_name]['played']++;
            $rows[$b->a_name]['for'] += $a;
            $rows[$b->a_name]['against'] += $bs;
            $rows[$b->b_name]['for'] += $bs;
            $rows[$b->b_name]['against'] += $a;

            $winner = $b->winner === 'a' ? $b->a_name : $b->b_name;
            $loser = $b->winner === 'a' ? $b->b_name : $b->a_name;
            $rows[$winner]['won']++;
            $rows[$loser]['lost']++;
        }

        uasort($rows, function ($x, $y) {
            return [$y['won'], $y['for'] - $y['against'], $y['for']]
               <=> [$x['won'], $x['for'] - $x['against'], $x['for']];
        });

        return $rows;
    }

    /** Every action the scoreboard recorded, in the order it happened. */
    private function timeline(ClubEvent $event): array
    {
        $names = EventMatch::where('event_id', $event->id)->pluck('match_no', 'id');

        return DB::table('event_match_events')
            ->where('event_id', $event->id)
            ->orderBy('sequence')
            ->get()
            ->map(fn ($r) => [
                'bout' => $names[$r->match_id] ?? '—',
                'sequence' => $r->sequence,
                'action' => $r->command,
                'side' => $r->side === 'a' ? 'AKA' : ($r->side === 'b' ? 'AO' : ''),
                'points' => $r->points ?? '',
                'score' => $r->score_a.'-'.$r->score_b,
                // How much of the bout was left, which is how a result is cited.
                'clock' => $r->clock_remaining !== null
                    ? sprintf('%d:%02d', intdiv((int) $r->clock_remaining, 60), (int) $r->clock_remaining % 60)
                    : '',
                'wall' => $r->occurred_at,
            ])->all();
    }

    private function writeCsvs(string $dir, array $standings, array $bouts, array $timeline): void
    {
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true)) {
            $this->error("Cannot write to {$dir}");

            return;
        }

        $put = function (string $file, array $head, array $rows) use ($dir) {
            $fh = fopen(rtrim($dir, '/').'/'.$file, 'w');
            fputcsv($fh, $head);
            foreach ($rows as $row) {
                fputcsv($fh, $row);
            }
            fclose($fh);
            $this->line('  wrote '.rtrim($dir, '/').'/'.$file);
        };

        $standingRows = [];
        foreach ($standings as $group => $table) {
            $place = 1;
            foreach ($table as $r) {
                $standingRows[] = [$group, $place++, $r['name'], $r['played'], $r['won'], $r['lost'], $r['for'], $r['against'], $r['for'] - $r['against']];
            }
        }

        $this->newLine();
        $put('standings.csv', ['Group', 'Place', 'Athlete', 'Played', 'Won', 'Lost', 'Points for', 'Points against', 'Difference'], $standingRows);
        $put('bouts.csv', ['Bout', 'Group', 'AKA', 'AO', 'Score', 'Winner', 'Won by', 'Note', 'Status'], array_map('array_values', $bouts));
        $put('timeline.csv', ['Bout', 'Sequence', 'Action', 'Side', 'Points', 'Score after', 'Bout clock', 'Wall clock'], array_map('array_values', $timeline));
    }
}
