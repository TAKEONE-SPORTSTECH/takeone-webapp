<?php

namespace App\Media;

use App\Events\Models\EventMatch;
use App\Sports\Combat\SportRegistry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One bout's statistics, read back from what its mat logged.
 *
 * The stat sheet of a single bout — who scored first and when, how the lead
 * changed hands, each corner's points by value, penalties, time in the lead,
 * longest run, the bout in thirds, how long the mat stood still — for the bout
 * page's PDF and for the card that closes a sent video. Nobody types a figure
 * in: every number is derived from `event_match_events` and the bout row.
 * Read-only.
 *
 * How the log is read (the same rules the competition report's analytics use):
 *  · load / reset — a fresh attempt: only the last attempt before `finish`
 *    counts (a bout that was never finished is read as it stands)
 *  · point — `points` (or payload `n`) for the logged side; undo_point takes the
 *    latest matching one back
 *  · penalty — payload `level` is the corner's penalty STATE after the call; a
 *    foul is a step up, a lower level is a correction
 *  · encho — the clock starts again for the extension; times keep counting on
 *  · clock_remaining against clock_duration is WHEN in the bout; occurred_at
 *    is the wall clock (for stoppages and the time it took to give a point)
 *
 * Sides in the log are the draw's slots (a / b); the corner each slot fought in
 * comes from the bout row (`a_corner`), as BoutTimeline reads it, so a sheet is
 * always laid out red against blue.
 */
class BoutStats
{
    public function __construct(private SportRegistry $sports) {}

    /**
     * @param  Collection<int, object>|null  $rows  the log when it is not the bout's own (an exhibition's mat window)
     * @return array<string, mixed>|null  null when nothing was scored or penalised — there is no story to tell
     */
    public function for(EventMatch $match, ?Collection $rows = null): ?array
    {
        $rows ??= app(\App\Events\Support\ExhibitionLink::class)->rowsForBout($match)
            ?? DB::table('event_match_events')
                ->where('match_id', $match->id)
                ->orderBy('sequence')
                ->orderBy('id')
                ->get();

        $bout = $this->replay($rows->all());

        if ($bout === null || $bout['events'] === []) {
            return null;
        }

        $sport = $this->sports->get($match->event?->sport);
        $colour = [
            'a' => $match->a_corner === 'blue' ? 'blue' : 'red',
            'b' => $match->b_corner === 'red' ? 'red' : 'blue',
        ];
        $slot = array_flip($colour);

        $length = max(1.0, (float) ($bout['length'] ?? 0));
        $events = $bout['events'];

        // The running score, red and blue, after every scoring moment.
        $steps = [['at' => 0.0, 'red' => 0, 'blue' => 0]];
        $run = ['red' => 0, 'blue' => 0];
        foreach ($events as &$e) {
            $e['corner'] = $colour[$e['side']];
            unset($e['side']);
            if ($e['kind'] === 'point') {
                $run[$e['corner']] += $e['n'];
                $steps[] = ['at' => $e['at'], 'red' => $run['red'], 'blue' => $run['blue']];
                $e['label'] = $sport?->scoreLabel($e['n']);
            }
            $e['red'] = $run['red'];
            $e['blue'] = $run['blue'];
        }
        unset($e);

        // Who led, for how long; lead changes; biggest lead.
        $lead = ['red' => 0.0, 'blue' => 0.0, 'level' => 0.0];
        $biggest = ['red' => 0, 'blue' => 0];
        $changes = 0;
        $leader = null;
        $trailed = ['red' => false, 'blue' => false];
        $decisive = null;
        foreach ($steps as $i => $s) {
            $end = $steps[$i + 1]['at'] ?? $length;
            $d = $s['red'] - $s['blue'];
            $now = $d > 0 ? 'red' : ($d < 0 ? 'blue' : null);
            $lead[$now ?? 'level'] += max(0.0, $end - $s['at']);
            $biggest['red'] = max($biggest['red'], $d);
            $biggest['blue'] = max($biggest['blue'], -$d);
            if ($now !== null && $leader !== null && $now !== $leader) {
                $changes++;
            }
            if ($now !== null && $now !== $leader) {
                $decisive = $s['at'];
            }
            if ($now !== null) {
                $leader = $now;
                $trailed[$now === 'red' ? 'blue' : 'red'] = true;
            }
        }

        // Longest unanswered run, per corner.
        $streak = ['red' => 0, 'blue' => 0];
        $cur = null;
        $acc = 0;
        foreach ($events as $e) {
            if ($e['kind'] !== 'point') {
                continue;
            }
            $acc = $e['corner'] === $cur ? $acc + $e['n'] : $e['n'];
            $cur = $e['corner'];
            $streak[$cur] = max($streak[$cur], $acc);
        }

        $winner = in_array($match->winner, ['a', 'b'], true) ? $colour[$match->winner] : null;
        $points = fn (string $c) => array_values(array_filter($events, fn ($e) => $e['kind'] === 'point' && $e['corner'] === $c));
        $third = $length / 3;
        $inThird = fn (array $list, int $k) => array_sum(array_map(fn ($p) => $p['n'],
            array_filter($list, fn ($p) => $p['at'] >= $k * $third && ($k === 2 || $p['at'] < ($k + 1) * $third))));

        $corner = function (string $c) use ($points, $events, $lead, $biggest, $streak, $length, $match, $slot, $run) {
            $mine = $points($c);
            $byValue = [];
            foreach ($mine as $p) {
                $byValue[$p['n']] = ($byValue[$p['n']] ?? 0) + 1;
            }
            ksort($byValue);
            $side = $slot[$c];

            return [
                'name' => (string) $match->{$side.'_name'},
                'points' => $run[$c],
                'scores' => count($mine),
                'by_value' => $byValue,
                'penalties' => count(array_filter($events, fn ($e) => $e['kind'] === 'penalty' && $e['corner'] === $c)),
                'per_minute' => round($run[$c] / ($length / 60), 1),
                'lead_seconds' => (int) round($lead[$c]),
                'biggest_lead' => $biggest[$c],
                'longest_run' => $streak[$c],
                'last_third' => array_sum(array_map(fn ($p) => $p['n'], array_filter($mine, fn ($p) => $p['at'] >= $length * 2 / 3))),
            ];
        };

        $firstPoint = collect($events)->firstWhere('kind', 'point');
        $scored = $run['red'] + $run['blue'];
        $late = array_sum(array_map(fn ($e) => $e['kind'] === 'point' && $e['at'] >= $length * 2 / 3 ? $e['n'] : 0, $events));
        // Only a winner AHEAD at the end took the lead for good — a level bout
        // was settled some other way (senshu, the judges' decision).
        $held = $winner !== null && $run[$winner] > $run[$winner === 'red' ? 'blue' : 'red'];

        return [
            'length' => (int) round($length),
            'fought' => $bout['fought'] !== null ? (int) round($bout['fought']) : null,
            'wall' => $bout['wall'],
            'encho' => $bout['encho'],
            'winner' => $winner,
            'score' => $run,
            'level_at_end' => $run['red'] === $run['blue'],
            'senshu' => $bout['senshu'] !== null ? $colour[$bout['senshu']] : null,
            'first' => $firstPoint ? ['corner' => $firstPoint['corner'], 'at' => $firstPoint['at'], 'n' => $firstPoint['n'], 'won' => $winner === $firstPoint['corner']] : null,
            'lead_changes' => $changes,
            'level_seconds' => (int) round($lead['level']),
            'comeback' => $winner !== null && $trailed[$winner],
            // When the winner took the lead for good, and how much clock was left.
            'decided_at' => $held ? $decisive : null,
            'decided_left' => $held && $decisive !== null ? max(0, round($length - $decisive, 1)) : null,
            'late_share' => $scored ? (int) round($late / $scored * 100) : 0,
            'thirds' => array_map(fn ($k) => [
                'from' => (int) round($k * $third),
                'to' => (int) round(($k + 1) * $third),
                'red' => $inThird($points('red'), $k),
                'blue' => $inThird($points('blue'), $k),
            ], [0, 1, 2]),
            'stoppages' => $bout['stoppages'],
            'award_seconds' => $bout['award'] !== [] ? (int) round(array_sum($bout['award']) / count($bout['award'])) : null,
            'red' => $corner('red'),
            'blue' => $corner('blue'),
            'steps' => $steps,
            'events' => array_map(fn ($e) => array_intersect_key($e, array_flip(['at', 'kind', 'corner', 'n', 'level', 'label', 'red', 'blue'])), $events),
        ];
    }

    /**
     * The last attempt before the bout was finished, as a list of moments.
     *
     * @param  list<object>  $rows
     * @return array<string, mixed>|null
     */
    private function replay(array $rows): ?array
    {
        $fresh = fn () => ['events' => [], 'level' => ['a' => 0, 'b' => 0], 'senshu' => null, 'duration' => null, 'offset' => 0.0,
            'encho' => false, 'start' => null, 'end' => null, 'left' => null, 'stoppages' => 0, 'paused' => null, 'award' => []];
        $s = $fresh();
        $done = null;

        foreach ($rows as $r) {
            $p = json_decode((string) $r->payload, true) ?: [];
            $raw = $p['side'] ?? null;
            $side = in_array($r->side, ['a', 'b'], true) ? $r->side : ($raw === 'aka' ? 'a' : ($raw === 'ao' ? 'b' : null));
            $dur = $r->clock_duration !== null ? (float) $r->clock_duration : null;
            $left = $r->clock_remaining !== null ? (float) $r->clock_remaining : null;
            $wall = $r->occurred_at ? Carbon::parse($r->occurred_at, 'UTC') : null;
            $d = $dur ?: $s['duration'];
            $at = $d !== null && $left !== null ? round($s['offset'] + max(0.0, $d - $left), 1) : null;

            switch ($r->command) {
                case 'load':
                case 'reset':
                    $s = $fresh();
                    $s['duration'] = $dur;
                    break;
                case 'start':
                case 'resume':
                    $s['start'] ??= $wall;
                    $s['duration'] ??= $dur;
                    $s['paused'] = null;
                    break;
                case 'pause':
                    $s['stoppages']++;
                    $s['paused'] = $wall;
                    break;
                case 'encho':
                    $s['offset'] += (float) ($s['duration'] ?? 0);
                    $s['encho'] = true;
                    $s['duration'] = null;
                    break;
                case 'point':
                case 'score':
                    $n = (int) ($r->points ?? $p['n'] ?? 0);
                    if ($side && $n > 0) {
                        $s['events'][] = ['at' => $at ?? 0.0, 'kind' => 'point', 'side' => $side, 'n' => $n];
                        if ($s['paused'] && $wall) {
                            $gap = $wall->getTimestamp() - $s['paused']->getTimestamp();
                            if ($gap >= 0 && $gap < 120) {
                                $s['award'][] = $gap;
                            }
                        }
                    }
                    break;
                case 'undo_point':
                    $n = (int) ($p['n'] ?? $r->points ?? 0);
                    for ($i = count($s['events']) - 1; $i >= 0; $i--) {
                        $e = $s['events'][$i];
                        if ($e['kind'] === 'point' && $e['side'] === $side && ($n === 0 || $e['n'] === $n)) {
                            array_splice($s['events'], $i, 1);
                            break;
                        }
                    }
                    break;
                case 'penalty':
                    $lvl = (int) ($p['level'] ?? 0);
                    if ($side) {
                        if ($lvl > $s['level'][$side]) {
                            $s['events'][] = ['at' => $at ?? 0.0, 'kind' => 'penalty', 'side' => $side, 'level' => $lvl];
                        }
                        $s['level'][$side] = $lvl;
                    }
                    break;
                case 'senshu':
                    $s['senshu'] = $side;
                    break;
                case 'finish':
                    $s['end'] = $wall;
                    $s['left'] = $left;
                    $s['duration'] ??= $dur;
                    $done = $s;
                    break;
            }
        }

        $b = $done ?? ($s['events'] !== [] ? $s : null);

        if ($b === null) {
            return null;
        }

        $length = $b['offset'] + (float) ($b['duration'] ?? 0);
        $fought = $b['left'] !== null && $b['duration'] ? $b['offset'] + $b['duration'] - $b['left'] : null;

        usort($b['events'], fn ($x, $y) => $x['at'] <=> $y['at']);

        return [
            'events' => $b['events'],
            'length' => $length ?: ($b['events'] !== [] ? end($b['events'])['at'] : 0),
            'fought' => $fought,
            'wall' => $b['start'] && $b['end'] ? max(0, $b['end']->getTimestamp() - $b['start']->getTimestamp()) : null,
            'encho' => $b['encho'],
            'senshu' => $b['senshu'],
            // The pause that ENDS the bout (the final bell) is not a stoppage.
            'stoppages' => max(0, $b['stoppages'] - ($done ? 1 : 0)),
            'award' => $b['award'],
        ];
    }
}
