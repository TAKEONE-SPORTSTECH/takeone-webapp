<?php

namespace App\Events\Support\Competition\Formats;

use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Events\Support\Competition\Entrants;
use App\Sports\Combat\Engine\DrawEngine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Single elimination for any head-to-head sport — people, pairs or teams.
 *
 * The field is padded to the next power of two with byes, seeded the standard
 * way (1 meets the lowest, 1 and 2 only in the final — DrawEngine::seedOrder),
 * and a bye walks its entrant straight into the next round. A contest row is
 * an EventMatch with `phase = 'ko:<round>'` (or 'ko:third'), slots numbered
 * across the whole bracket so BracketView and <x-tournament-bracket> draw it
 * as they draw a combat bracket. Round names are the bracket's own
 * (Round of 16 … Semifinal, Final).
 *
 * `$thirdPlace` adds the bronze play-off (the recipe's medals rule).
 */
final class Knockout
{
    public const PHASE = 'ko';

    public function __construct(private DrawEngine $draws) {}

    /**
     * Cut the bracket. `$entrants` are in seed order (Entrants::of, or a
     * group stage's qualifiers). Replaces this stage's contests; never touches
     * a group stage's.
     */
    public function build(EventCategory $division, Collection $entrants, bool $thirdPlace, int $firstSlot = 1000): void
    {
        DB::transaction(function () use ($division, $entrants, $thirdPlace, $firstSlot) {
            EventMatch::where('category_id', $division->id)->where('phase', 'like', self::PHASE.':%')->delete();

            $n = $entrants->count();

            if ($n < 2) {
                return;
            }

            $size = 2 ** (int) ceil(log($n, 2));
            $order = $this->draws->seedOrder($size);            // slot => seed (1-based)
            $names = $this->draws->roundNames($size);
            $byKey = $entrants->values();
            $slot = $firstSlot;
            $no = (int) EventMatch::where('event_id', $division->event_id)->max('match_no');

            foreach ($names as $r => $name) {
                $count = intdiv($size, 2 ** ($r + 1));

                for ($i = 0; $i < $count; $i++) {
                    $row = [
                        'event_id' => $division->event_id,
                        'category_id' => $division->id,
                        'round' => $name,
                        'phase' => self::PHASE.':'.$r,
                        'slot' => $slot++,
                        'match_no' => ++$no,
                        'status' => 'upcoming',
                    ];

                    if ($r === 0) {
                        $a = $byKey[$order[$i * 2] - 1] ?? null;
                        $b = $byKey[$order[$i * 2 + 1] - 1] ?? null;
                        $row += Entrants::columns($a, 'a') + Entrants::columns($b, 'b');
                    }

                    $division->matches()->create($row);
                }
            }

            if ($thirdPlace && $size >= 4) {
                $division->matches()->create([
                    'event_id' => $division->event_id,
                    'category_id' => $division->id,
                    'round' => 'Third place',
                    'phase' => self::PHASE.':third',
                    'slot' => $slot++,
                    'match_no' => ++$no,
                    'status' => 'upcoming',
                ]);
            }

            // Byes walk through.
            foreach ($this->round($division, 0) as $m) {
                if ($m->a_name xor $m->b_name) {
                    $m->forceFill(['winner' => $m->a_name ? 'a' : 'b', 'status' => 'done', 'won_by_bye' => true])->save();
                    $this->advance($m);
                }
            }
        });
    }

    /** Carry a decided contest's winner (and a semi-final's loser) onward. */
    public function advance(EventMatch $m): void
    {
        [$stage, $r] = array_pad(explode(':', (string) $m->phase, 2), 2, null);

        if ($stage !== self::PHASE || $r === 'third' || ! in_array($m->winner, ['a', 'b'], true)) {
            return;
        }

        $r = (int) $r;
        $round = $this->round($m->category, $r);
        $i = $round->search(fn (EventMatch $x) => $x->id === $m->id);
        $next = $this->round($m->category, $r + 1);

        $winner = $this->corner($m, $m->winner);
        $loser = $this->corner($m, $m->winner === 'a' ? 'b' : 'a');

        if ($next->isNotEmpty() && ($target = $next->get(intdiv($i, 2)))) {
            $target->fill(Entrants::columns($winner, $i % 2 === 0 ? 'a' : 'b'))->save();
            $this->walkoverIfAlone($target);
        }

        // Semi-final losers meet for bronze, where the sport plays it off.
        if ($next->count() === 1 && ($third = $this->third($m->category))) {
            $third->fill(Entrants::columns($loser['name'] ? $loser : null, $i % 2 === 0 ? 'a' : 'b'))->save();
        }
    }

    /**
     * Undo what a contest carried forward, before its result changes — so a
     * corrected semi-final never leaves its old winner in the final.
     */
    public function retract(EventMatch $m): void
    {
        [$stage, $r] = array_pad(explode(':', (string) $m->phase, 2), 2, null);

        if ($stage !== self::PHASE || $r === 'third') {
            return;
        }

        $r = (int) $r;
        $i = $this->round($m->category, $r)->search(fn (EventMatch $x) => $x->id === $m->id);
        $next = $this->round($m->category, $r + 1);

        if ($target = $next->get(intdiv($i, 2))) {
            if ($target->winner) {
                $this->retract($target);
            }
            $target->fill(Entrants::columns(null, $i % 2 === 0 ? 'a' : 'b') + ['winner' => null, 'status' => 'upcoming', 'a_score' => null, 'b_score' => null, 'detail' => null, 'won_by_bye' => false])->save();
        }

        if ($next->count() === 1 && ($third = $this->third($m->category))) {
            $third->fill(Entrants::columns(null, $i % 2 === 0 ? 'a' : 'b') + ['winner' => null, 'status' => 'upcoming', 'a_score' => null, 'b_score' => null, 'detail' => null])->save();
        }
    }

    /** First, second and third — once the bracket has answered them. */
    public function podium(EventCategory $division, string $medals): array
    {
        $rounds = EventMatch::where('category_id', $division->id)->where('phase', 'like', self::PHASE.':%')->get();
        $final = $rounds->where('round', 'Final')->first();

        if (! $final || ! in_array($final->winner, ['a', 'b'], true)) {
            return [];
        }

        $place = fn (EventMatch $m, string $c, int $p) => ['place' => $p, 'name' => $m->{$c.'_name'}, 'country' => $m->{$c.'_country'}, 'key' => Entrants::keyOf($m, $c)];
        $out = [$place($final, $final->winner, 1), $place($final, $final->winner === 'a' ? 'b' : 'a', 2)];

        $third = $this->third($division);

        if ($third && in_array($third->winner, ['a', 'b'], true)) {
            $out[] = $place($third, $third->winner, 3);
        } elseif (! $third && $medals !== 'none') {
            foreach ($rounds->where('round', 'Semifinal') as $semi) {
                if (in_array($semi->winner, ['a', 'b'], true) && ! $semi->won_by_bye) {
                    $out[] = $place($semi, $semi->winner === 'a' ? 'b' : 'a', 3);
                }
            }
        }

        return $out;
    }

    /** @return Collection<int, EventMatch> */
    private function round(EventCategory $division, int $r): Collection
    {
        return EventMatch::where('category_id', $division->id)->where('phase', self::PHASE.':'.$r)->orderBy('slot')->get();
    }

    private function third(EventCategory $division): ?EventMatch
    {
        return EventMatch::where('category_id', $division->id)->where('phase', self::PHASE.':third')->first();
    }

    private function corner(EventMatch $m, string $c): array
    {
        return [
            'name' => $m->{$c.'_name'},
            'country' => $m->{$c.'_country'},
            'registration_id' => $m->{$c.'_competitor_id'},
            'side_id' => $m->{$c.'_side_id'},
            'seed' => $m->{$c.'_seed'},
        ];
    }

    /**
     * A later-round contest whose other corner can never be filled (its feeder
     * was a bye against a bye) walks its entrant through too.
     */
    private function walkoverIfAlone(EventMatch $m): void
    {
        [, $r] = explode(':', (string) $m->phase, 2);
        $prev = $this->round($m->category, (int) $r - 1);
        $i = $this->round($m->category, (int) $r)->search(fn (EventMatch $x) => $x->id === $m->id);
        $feeders = $prev->slice($i * 2, 2)->values();

        if ($feeders->count() === 2 && $feeders->every(fn (EventMatch $f) => $f->status === 'done') && ($m->a_name xor $m->b_name)) {
            $m->forceFill(['winner' => $m->a_name ? 'a' : 'b', 'status' => 'done', 'won_by_bye' => true])->save();
            $this->advance($m);
        }
    }
}
