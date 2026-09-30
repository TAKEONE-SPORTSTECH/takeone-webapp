<?php

namespace App\Events\Support\Competition\Formats;

use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Events\Support\Competition\Entrants;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Everyone meets everyone — one group, or several (a group stage).
 *
 * Pairings are the circle method, so each entrant plays once per round and a
 * group of odd size gives one entrant a rest each round (no contest row is
 * written for it). Groups are filled by SNAKE seeding (1 A, 2 B, 3 C, 4 C,
 * 5 B, 6 A…) so the strongest are spread across them.
 *
 * Contest rows: `phase = 'rr:<group>:<round>'`, `group_no` the group (1 = A),
 * `round` the group's name ("Group A").
 */
final class RoundRobin
{
    public const PHASE = 'rr';

    /**
     * @param  int|null  $groupSize  null = one group of everybody
     * @return int how many groups were made
     */
    public function build(EventCategory $division, Collection $entrants, ?int $groupSize = null): int
    {
        return DB::transaction(function () use ($division, $entrants, $groupSize) {
            EventMatch::where('category_id', $division->id)->where('phase', 'like', self::PHASE.':%')->delete();

            $groups = $this->groups($entrants->values(), $groupSize);
            $slot = 1;
            $no = (int) EventMatch::where('event_id', $division->event_id)->max('match_no');

            foreach ($groups as $g => $members) {
                $label = count($groups) > 1 ? 'Group '.chr(65 + $g) : 'Round robin';

                foreach ($this->rounds($members->all()) as $r => $pairs) {
                    foreach ($pairs as [$a, $b]) {
                        $division->matches()->create([
                            'event_id' => $division->event_id,
                            'category_id' => $division->id,
                            'round' => $label,
                            'phase' => self::PHASE.':'.($g + 1).':'.($r + 1),
                            'group_no' => $g + 1,
                            'slot' => $slot++,
                            'match_no' => ++$no,
                            'status' => 'upcoming',
                        ] + Entrants::columns($a, 'a') + Entrants::columns($b, 'b'));
                    }
                }
            }

            return count($groups);
        });
    }

    /** The entrants of each group, as drawn (snake by seed). */
    public function groups(Collection $entrants, ?int $groupSize): array
    {
        $n = $entrants->count();

        if (! $groupSize || $groupSize >= $n) {
            return [$entrants->values()];
        }

        $count = (int) ceil($n / max(2, $groupSize));
        $groups = array_fill(0, $count, []);

        foreach ($entrants->values() as $i => $e) {
            $lap = intdiv($i, $count);
            $g = $lap % 2 === 0 ? $i % $count : $count - 1 - ($i % $count);
            $groups[$g][] = $e;
        }

        return array_map(fn ($g) => collect($g), $groups);
    }

    /** Group membership back from the contests already written. */
    public function membersOf(EventCategory $division, Collection $entrants): array
    {
        $byGroup = [];

        foreach (EventMatch::where('category_id', $division->id)->where('phase', 'like', self::PHASE.':%')->get() as $m) {
            foreach (['a', 'b'] as $c) {
                if ($k = Entrants::keyOf($m, $c)) {
                    $byGroup[(int) $m->group_no][$k] = true;
                }
            }
        }

        ksort($byGroup);

        return array_map(
            fn (array $keys) => $entrants->filter(fn ($e) => isset($keys[$e['key']]))->values(),
            $byGroup,
        );
    }

    /** Circle method: rounds of pairs, a rest (null) for an odd group. */
    private function rounds(array $members): array
    {
        if (count($members) % 2 === 1) {
            $members[] = null;
        }

        $n = count($members);
        $rounds = [];

        for ($r = 0; $r < $n - 1; $r++) {
            $pairs = [];

            for ($i = 0; $i < intdiv($n, 2); $i++) {
                $a = $members[$i];
                $b = $members[$n - 1 - $i];

                if ($a !== null && $b !== null) {
                    $pairs[] = $r % 2 === 0 ? [$a, $b] : [$b, $a];   // alternate who is listed first
                }
            }

            $rounds[] = $pairs;
            // Keep the first fixed, rotate the rest.
            $members = array_merge([$members[0]], [$members[$n - 1]], array_slice($members, 1, $n - 2));
        }

        return $rounds;
    }
}
