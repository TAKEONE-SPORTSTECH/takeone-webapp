<?php

namespace App\Events\Support\Competition\Formats;

use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Events\Support\Competition\CompetitionProfile;
use App\Events\Support\Competition\Entrants;
use App\Events\Support\Competition\Standings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The Swiss system — chess and esports. A fixed number of rounds; in each,
 * entrants on the same score meet, nobody meets the same opponent twice, and
 * an odd field gives the lowest-placed entrant without one a bye (a win on
 * the table). Round 1 pairs the top half of the seeding against the bottom.
 *
 * One round is paired at a time, from the standings as they stand — a round
 * can only be paired once the one before it is decided.
 *
 * Contest rows: `phase = 'sw:<round>'`, `round` "Round <n>".
 */
final class Swiss
{
    public const PHASE = 'sw';

    public function __construct(private Standings $standings) {}

    public function roundsPlayed(EventCategory $division): int
    {
        return (int) EventMatch::where('category_id', $division->id)->where('phase', 'like', self::PHASE.':%')
            ->get()->map(fn ($m) => (int) explode(':', $m->phase)[1])->max();
    }

    /** Pair the next round. Returns its number, or null when it may not be paired yet. */
    public function pairNext(EventCategory $division, Collection $entrants, CompetitionProfile $profile, int $totalRounds): ?int
    {
        $played = $this->roundsPlayed($division);
        $contests = EventMatch::where('category_id', $division->id)->where('phase', 'like', self::PHASE.':%')->get();

        if ($played >= $totalRounds || $contests->contains(fn ($m) => $m->status !== 'done')) {
            return null;
        }

        $round = $played + 1;
        $met = [];
        $hadBye = [];

        foreach ($contests as $m) {
            $a = Entrants::keyOf($m, 'a');
            $b = Entrants::keyOf($m, 'b');
            if ($a && $b) { $met[$a][$b] = $met[$b][$a] = true; }
            if ($m->won_by_bye) { $hadBye[Entrants::keyOf($m, $m->winner)] = true; }
        }

        if ($round === 1) {
            $list = $entrants->values()->all();
            $half = intdiv(count($list) + 1, 2);
            $order = [];
            for ($i = 0; $i < $half; $i++) {
                $order[] = $list[$i];
                if (isset($list[$i + $half])) { $order[] = $list[$i + $half]; }
            }
            $ranked = $order;
        } else {
            $table = collect($this->standings->build($entrants, $contests, $profile))->keyBy('key');
            $ranked = $entrants->sortBy(fn ($e) => $table[$e['key']]['position'] ?? PHP_INT_MAX)->values()->all();
        }

        $bye = null;
        if (count($ranked) % 2 === 1) {
            for ($i = count($ranked) - 1; $i >= 0; $i--) {
                if (empty($hadBye[$ranked[$i]['key']])) { $bye = $ranked[$i]; array_splice($ranked, $i, 1); break; }
            }
            $bye ??= array_pop($ranked);
        }

        $pairs = $round === 1 ? array_chunk($ranked, 2) : ($this->pair($ranked, $met) ?? array_chunk($ranked, 2));

        DB::transaction(function () use ($division, $pairs, $bye, $round) {
            $no = (int) EventMatch::where('event_id', $division->event_id)->max('match_no');
            $slot = (int) EventMatch::where('category_id', $division->id)->max('slot');

            foreach ($pairs as [$a, $b]) {
                $division->matches()->create([
                    'event_id' => $division->event_id, 'category_id' => $division->id,
                    'round' => 'Round '.$round, 'phase' => self::PHASE.':'.$round,
                    'slot' => ++$slot, 'match_no' => ++$no, 'status' => 'upcoming',
                ] + Entrants::columns($a, 'a') + Entrants::columns($b, 'b'));
            }

            if ($bye) {
                $division->matches()->create([
                    'event_id' => $division->event_id, 'category_id' => $division->id,
                    'round' => 'Round '.$round, 'phase' => self::PHASE.':'.$round,
                    'slot' => ++$slot, 'match_no' => ++$no, 'status' => 'done', 'winner' => 'a', 'won_by_bye' => true,
                ] + Entrants::columns($bye, 'a'));
            }
        });

        return $round;
    }

    /** Pair down the ranking, never a rematch — backtracking, small fields. */
    private function pair(array $ranked, array $met): ?array
    {
        if ($ranked === []) {
            return [];
        }

        $first = array_shift($ranked);

        foreach ($ranked as $i => $candidate) {
            if (! empty($met[$first['key']][$candidate['key']])) {
                continue;
            }

            $rest = $ranked;
            array_splice($rest, $i, 1);

            if (($tail = $this->pair($rest, $met)) !== null) {
                return array_merge([[$first, $candidate]], $tail);
            }
        }

        return null;
    }
}
