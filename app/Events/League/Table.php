<?php

namespace App\Events\League;

use App\Events\League\Models\LeagueFixture;
use App\Events\League\Models\LeagueTeam;
use App\Models\ClubEvent;
use Illuminate\Support\Collection;

/**
 * The league table — derived, every time, from the results.
 *
 * Never stored. A table is a reading of the fixtures, and a stored one is a
 * second answer waiting to disagree with the first: correct a score entered
 * wrongly three weeks ago and the standings must simply be right again.
 *
 * ── The order ───────────────────────────────────────────────────────────────
 *
 * Points, then goal difference, then goals scored, then the mini-league between
 * the tied sides, then the name. The points themselves are the organiser's —
 * three for a win is football's convention, not the platform's, and a club that
 * plays two-for-a-win says so on its own event.
 *
 * Head-to-head is applied AFTER goal difference rather than before because that
 * is the commoner convention and the one a reader assumes; a league that wants
 * it first is a setting nobody has asked for yet.
 */
class Table
{
    public const DEFAULT_POINTS = ['win' => 3, 'draw' => 1, 'loss' => 0];

    /**
     * @param  array{win: int, draw: int, loss: int}|null  $points
     * @return array<int, array<string, mixed>>
     */
    public function build(ClubEvent $event, ?array $points = null): array
    {
        $points = $points ?: self::DEFAULT_POINTS;

        $teams = LeagueTeam::where('event_id', $event->id)
            ->orderBy('sort_order')->orderBy('id')->get();

        if ($teams->isEmpty()) {
            return [];
        }

        $fixtures = LeagueFixture::where('event_id', $event->id)
            ->where('status', LeagueFixture::PLAYED)
            ->whereNotNull('home_score')->whereNotNull('away_score')
            ->get();

        $rows = $teams->mapWithKeys(fn (LeagueTeam $team) => [$team->id => [
            'team_id' => $team->id,
            'uuid' => $team->uuid,
            'name' => $team->name,
            'short_name' => $team->short_name,
            'logo' => $team->logo ? file_url($team->logo) : null,
            'country' => $team->country,
            'withdrawn' => $team->withdrawn_at !== null,
            'played' => 0, 'won' => 0, 'drawn' => 0, 'lost' => 0,
            'scored' => 0, 'conceded' => 0, 'difference' => 0, 'points' => 0,
            // The last five results, newest last — the strip every table has.
            'form' => [],
        ]])->all();

        foreach ($fixtures->sortBy('matchday') as $fixture) {
            foreach ([['home', 'away'], ['away', 'home']] as [$side, $other]) {
                $id = $fixture->{$side.'_team_id'};

                if (! isset($rows[$id])) {
                    continue;   // a team deleted out from under a played fixture
                }

                $for = (int) $fixture->{$side.'_score'};
                $against = (int) $fixture->{$other.'_score'};

                $rows[$id]['played']++;
                $rows[$id]['scored'] += $for;
                $rows[$id]['conceded'] += $against;

                $result = $for <=> $against;

                if ($result > 0) {
                    $rows[$id]['won']++;
                    $rows[$id]['points'] += $points['win'];
                    $rows[$id]['form'][] = 'W';
                } elseif ($result === 0) {
                    $rows[$id]['drawn']++;
                    $rows[$id]['points'] += $points['draw'];
                    $rows[$id]['form'][] = 'D';
                } else {
                    $rows[$id]['lost']++;
                    $rows[$id]['points'] += $points['loss'];
                    $rows[$id]['form'][] = 'L';
                }
            }
        }

        $table = collect($rows)->map(function (array $row) {
            $row['difference'] = $row['scored'] - $row['conceded'];
            $row['form'] = array_slice($row['form'], -5);

            return $row;
        });

        return $this->order($table, $fixtures)
            ->values()
            ->map(function (array $row, int $i) {
                $row['position'] = $i + 1;

                return $row;
            })
            ->all();
    }

    /**
     * Points → goal difference → goals scored → head-to-head → name.
     *
     * The mini-league is computed only between the sides that are actually
     * level, and only from the matches they played against each other — which
     * is what "head-to-head" means and why it cannot be a column on the row.
     */
    private function order(Collection $table, Collection $fixtures): Collection
    {
        return $table->sort(function (array $a, array $b) use ($table, $fixtures) {
            $byNumbers = [$b['points'], $b['difference'], $b['scored']]
                <=> [$a['points'], $a['difference'], $a['scored']];

            if ($byNumbers !== 0) {
                return $byNumbers;
            }

            $tied = $table->filter(fn (array $r) => $r['points'] === $a['points']
                && $r['difference'] === $a['difference']
                && $r['scored'] === $a['scored'])
                ->pluck('team_id')->all();

            if (count($tied) > 1) {
                $mini = $this->headToHead($tied, $fixtures);

                $byMini = [$mini[$b['team_id']] ?? 0] <=> [$mini[$a['team_id']] ?? 0];

                if ($byMini !== 0) {
                    return $byMini;
                }
            }

            return strcasecmp($a['name'], $b['name']);
        })->values();
    }

    /**
     * Points won in the matches the tied teams played against EACH OTHER.
     *
     * @param  array<int, int>  $tied
     * @return array<int, int>
     */
    private function headToHead(array $tied, Collection $fixtures): array
    {
        $points = array_fill_keys($tied, 0);

        foreach ($fixtures as $fixture) {
            if (! in_array($fixture->home_team_id, $tied, true) || ! in_array($fixture->away_team_id, $tied, true)) {
                continue;
            }

            $winner = $fixture->winnerId();

            if ($winner === null) {
                $points[$fixture->home_team_id]++;
                $points[$fixture->away_team_id]++;

                continue;
            }

            $points[$winner] += 3;
        }

        return $points;
    }
}
