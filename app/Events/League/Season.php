<?php

namespace App\Events\League;

use App\Events\League\Models\LeagueFixture;
use App\Events\League\Models\LeagueTeam;
use App\Events\Models\ClubEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The fixture list.
 *
 * Everybody plays everybody, once or twice, and no side plays twice on the same
 * matchday. That is the circle method: fix one team, rotate the rest, and each
 * rotation is a round in which every team has exactly one opponent. An odd
 * number of teams gets a phantom opponent, and whoever draws it sits that week
 * out — a bye, in a league's own vocabulary.
 *
 * Two things it is careful about, because a season is read by people who play
 * in it:
 *
 *  · HOME AND AWAY alternate. Naively pairing rotations gives one team every
 *    home match in the first half; flipping the fixed team's side on alternate
 *    rounds spreads them.
 *  · A SECOND round is the first one mirrored, in the same order, with the
 *    grounds swapped — which is how every league anybody has played in reads.
 *
 * It never touches a fixture that has been played. Regenerating a season after
 * three matchdays keeps those three and re-cuts the rest, because a result is a
 * fact and a fixture list is a plan.
 */
class Season
{
    /**
     * (Re)cut the fixture list.
     *
     * @return array{created: int, kept: int, matchdays: int}
     */
    public function generate(ClubEvent $event, int $rounds = 1): array
    {
        $teams = $this->teams($event);

        if ($teams->count() < 2) {
            return ['created' => 0, 'kept' => 0, 'matchdays' => 0];
        }

        $rounds = max(1, min(4, $rounds));

        return DB::transaction(function () use ($event, $teams, $rounds) {
            // Results are facts. Everything still unplayed is a plan, and plans
            // are what this replaces.
            $kept = LeagueFixture::where('event_id', $event->id)
                ->where('status', LeagueFixture::PLAYED)->count();

            LeagueFixture::where('event_id', $event->id)
                ->where('status', '!=', LeagueFixture::PLAYED)->delete();

            $played = LeagueFixture::where('event_id', $event->id)->get()
                ->map(fn (LeagueFixture $f) => $this->pairKey($f->home_team_id, $f->away_team_id, $f->matchday))
                ->flip();

            $day = 0;
            $created = 0;
            $kickoff = $event->date ? Carbon::parse($event->date) : null;

            foreach ($this->rounds($teams->pluck('id')->all(), $rounds) as $round) {
                $day++;

                foreach ($round as [$homeId, $awayId]) {
                    if ($played->has($this->pairKey($homeId, $awayId, $day))) {
                        continue;   // already played on this matchday
                    }

                    LeagueFixture::create([
                        'event_id' => $event->id,
                        'matchday' => $day,
                        'home_team_id' => $homeId,
                        'away_team_id' => $awayId,
                        // A matchday a week apart from the event's own date —
                        // the ordinary shape of a season, and the organiser can
                        // move any single fixture afterwards.
                        'kickoff_at' => $kickoff?->copy()->addWeeks($day - 1),
                        'status' => LeagueFixture::SCHEDULED,
                    ]);

                    $created++;
                }
            }

            return ['created' => $created, 'kept' => $kept, 'matchdays' => $day];
        });
    }

    /** Teams still in the competition, in the order the organiser listed them. */
    public function teams(ClubEvent $event): Collection
    {
        return LeagueTeam::where('event_id', $event->id)
            ->whereNull('withdrawn_at')
            ->orderBy('sort_order')->orderBy('id')
            ->get();
    }

    /**
     * The whole season as rounds of [home, away] pairs.
     *
     * @param  array<int, int>  $ids
     * @return array<int, array<int, array{0: int, 1: int}>>
     */
    public function rounds(array $ids, int $rounds): array
    {
        // The phantom that gives an odd field its weekly bye.
        $bye = count($ids) % 2 === 1;
        if ($bye) {
            $ids[] = 0;
        }

        $n = count($ids);
        $half = intdiv($n, 2);
        $fixed = array_shift($ids);      // the pivot the others rotate around
        $season = [];

        for ($leg = 0; $leg < $rounds; $leg++) {
            $rotating = $ids;

            for ($r = 0; $r < $n - 1; $r++) {
                $pairs = [];
                $line = array_merge([$fixed], $rotating);

                for ($i = 0; $i < $half; $i++) {
                    $a = $line[$i];
                    $b = $line[$n - 1 - $i];

                    if ($a === 0 || $b === 0) {
                        continue;        // whoever drew the phantom is off this week
                    }

                    /*
                     * Which side has the ground.
                     *
                     * The pivot is the only team whose opponent list is not
                     * rotated, so it is the one that would otherwise play at
                     * home every week: its match alternates by round. Every
                     * other pair takes the natural order, which the rotation
                     * already varies. For four teams that gives 2/2/1/1 in a
                     * single leg — the closest to even a three-match leg allows.
                     *
                     * The second leg is the first one mirrored, which is what
                     * "home and away" means, and evens it to 3/3/3/3.
                     *
                     * ⚠️ Written with `!==` rather than `xor`: in PHP `xor`
                     * binds LOOSER than `=`, so `$flip = A xor B` assigns A and
                     * throws B away. It did exactly that here — the second leg
                     * came out as an identical copy of the first, same grounds
                     * and all, and one team never played at home.
                     */
                    $flip = ($i === 0 && $r % 2 === 1);

                    if ($leg % 2 === 1) {
                        $flip = ! $flip;
                    }

                    $pairs[] = $flip ? [$b, $a] : [$a, $b];
                }

                $season[] = $pairs;

                // Rotate everyone but the pivot.
                $rotating[] = array_shift($rotating);
            }
        }

        return $season;
    }

    private function pairKey(int $home, int $away, int $matchday): string
    {
        return $matchday.':'.$home.'v'.$away;
    }
}
