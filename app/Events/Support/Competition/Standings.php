<?php

namespace App\Events\Support\Competition;

use App\Events\Models\EventMatch;
use Illuminate\Support\Collection;

/**
 * A table from decided head-to-head contests — a group, a round robin, a
 * Swiss event — ranked by the sport's own points and tie-breakers (its
 * CompetitionProfile). Never stored: correct a score and the table is simply
 * right again.
 *
 * Tie-breaker keys, applied in the profile's order:
 *   points · difference (scored − conceded) · scored · sets (set difference)
 *   · games (game difference) · head_to_head (the mini-table of the tied)
 *   · buchholz (sum of the opponents' points — Swiss) · name
 *
 * The row shape is the one the public event page's `table` section already
 * draws (App\Events\League\League::publicView), so a group table appears there
 * with no new public screen.
 */
final class Standings
{
    /**
     * @param  Collection<int, array<string, mixed>>  $entrants  Entrants::of shape
     * @param  Collection<int, EventMatch>  $contests
     * @return list<array<string, mixed>>
     */
    public function build(Collection $entrants, Collection $contests, CompetitionProfile $profile): array
    {
        $points = $profile->points ?? ['win' => 3, 'draw' => 1, 'loss' => 0];
        $rows = [];

        foreach ($entrants as $e) {
            $rows[$e['key']] = [
                'key' => $e['key'], 'name' => $e['name'], 'country' => $e['country'] ?? null,
                'played' => 0, 'won' => 0, 'drawn' => 0, 'lost' => 0,
                'scored' => 0, 'conceded' => 0, 'difference' => 0,
                'sets_won' => 0, 'sets_lost' => 0, 'games_won' => 0, 'games_lost' => 0,
                'points' => 0.0, 'buchholz' => 0.0, 'form' => [], 'opponents' => [],
            ];
        }

        $decided = $contests->filter(fn (EventMatch $m) => $m->status === 'done' && ! $m->won_by_bye)->sortBy('slot');

        foreach ($decided as $m) {
            $a = Entrants::keyOf($m, 'a');
            $b = Entrants::keyOf($m, 'b');

            if (! isset($rows[$a], $rows[$b])) {
                continue;
            }

            foreach ([[$a, $b, 'a', 'b'], [$b, $a, 'b', 'a']] as [$me, $them, $mine, $theirs]) {
                $r = &$rows[$me];
                $r['played']++;
                $r['opponents'][] = $them;

                $for = is_numeric($m->{$mine.'_score'}) ? (float) $m->{$mine.'_score'} : 0;
                $against = is_numeric($m->{$theirs.'_score'}) ? (float) $m->{$theirs.'_score'} : 0;
                $r['scored'] += $for;
                $r['conceded'] += $against;

                $detail = (array) ($m->detail ?? []);
                if (isset($detail['sets'])) {
                    $r['sets_won'] += (int) $for;
                    $r['sets_lost'] += (int) $against;
                    $g = $detail['games'] ?? [0, 0];
                    $r['games_won'] += (int) ($mine === 'a' ? $g[0] : $g[1]);
                    $r['games_lost'] += (int) ($mine === 'a' ? $g[1] : $g[0]);
                }

                if ($m->winner === null) {
                    $r['drawn']++; $r['points'] += $points['draw']; $r['form'][] = 'D';
                } elseif ($m->winner === $mine) {
                    $r['won']++; $r['points'] += $points['win']; $r['form'][] = 'W';
                } else {
                    $r['lost']++; $r['points'] += $points['loss']; $r['form'][] = 'L';
                }

                $r['difference'] = $r['scored'] - $r['conceded'];
                unset($r);
            }
        }

        // A bye in a Swiss event is a win on the table.
        foreach ($contests->filter(fn (EventMatch $m) => $m->won_by_bye && $m->winner) as $m) {
            $k = Entrants::keyOf($m, $m->winner);
            if (isset($rows[$k])) {
                $rows[$k]['points'] += $points['win'];
                $rows[$k]['form'][] = 'W';
            }
        }

        foreach ($rows as $k => $r) {
            $rows[$k]['buchholz'] = array_sum(array_map(fn ($o) => $rows[$o]['points'] ?? 0, $r['opponents']));
        }

        $order = $profile->tieBreakers ?: ['points', 'difference', 'scored', 'name'];

        uasort($rows, fn ($x, $y) => $this->compare($x, $y, $order, $rows, $decided, $points));

        $position = 0;
        return array_values(array_map(function (array $r) use (&$position) {
            $r['position'] = ++$position;
            $r['form'] = array_slice($r['form'], -5);
            $r['points'] = $r['points'] == (int) $r['points'] ? (int) $r['points'] : $r['points'];
            unset($r['opponents']);

            return $r;
        }, $rows));
    }

    private function compare(array $x, array $y, array $order, array $rows, Collection $decided, array $points): int
    {
        foreach ($order as $key) {
            $c = match ($key) {
                'points' => $y['points'] <=> $x['points'],
                'difference' => $y['difference'] <=> $x['difference'],
                'scored' => $y['scored'] <=> $x['scored'],
                'sets' => ($y['sets_won'] - $y['sets_lost']) <=> ($x['sets_won'] - $x['sets_lost']),
                'games' => ($y['games_won'] - $y['games_lost']) <=> ($x['games_won'] - $x['games_lost']),
                'buchholz' => $y['buchholz'] <=> $x['buchholz'],
                'head_to_head' => $this->headToHead($x['key'], $y['key'], $decided, $points),
                'name' => strcmp((string) $x['name'], (string) $y['name']),
                default => 0,
            };

            if ($c !== 0) {
                return $c;
            }
        }

        return 0;
    }

    /** Between two tied entrants only: who took more points off the other. */
    private function headToHead(string $x, string $y, Collection $decided, array $points): int
    {
        $px = 0; $py = 0;

        foreach ($decided as $m) {
            $a = Entrants::keyOf($m, 'a');
            $b = Entrants::keyOf($m, 'b');

            if (! (($a === $x && $b === $y) || ($a === $y && $b === $x))) {
                continue;
            }

            if ($m->winner === null) {
                $px += $points['draw']; $py += $points['draw'];
            } else {
                $winner = $m->winner === 'a' ? $a : $b;
                $winner === $x ? $px += $points['win'] : $py += $points['win'];
            }
        }

        return $py <=> $px;
    }
}
