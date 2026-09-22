<?php

namespace App\Events\Support\Tournament;

use App\Models\ClubEvent;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Sports\Combat\Engine\GroupEngine;

/**
 * A championship's POOLS, as a stranger reads them.
 *
 * A weight class run as a pool has no bracket to look at — its answer is a
 * table — so an event with pools needs a public door of its own beside the
 * draw. An event with both gets both: the draw for the classes that are
 * bracketed, the tables for the classes that are not.
 *
 * Shared by every bracketed sport, because a pool table is the same object in
 * all of them: rows, points, and the fixtures they came from. What differs
 * between karate, taekwondo and jiu-jitsu is how a BOUT is won, and a table
 * does not care (CLAUDE.md → Shared Stays Shared).
 *
 * Names and numbers only. No entry ids, no photographs, no weights — the same
 * restraint the public entry list is built with.
 */
class PublicPools
{
    public function __construct(private GroupEngine $groups) {}

    /**
     * @return array{key?: string, icon?: string, label?: string, sub?: string, data?: array<string, mixed>}
     */
    public function view(ClubEvent $event): array
    {
        $pools = $event->categories()
            ->where('format', EventCategory::FORMAT_ROUND_ROBIN)
            ->orderBy('sort_order')->get()
            ->reject(fn (EventCategory $c) => $c->isHeading());

        if ($pools->isEmpty()) {
            return [];
        }

        $tables = $pools->map(function (EventCategory $pool) {
            $standings = $this->groups->standings($pool);

            return [
                'division' => $pool->name,
                'points' => null,          // a pool is scored by wins, not by a points table
                'rows' => collect($standings)->values()->map(fn (array $row, int $i) => [
                    'position' => $row['position'] ?? $i + 1,
                    'name' => $row['name'] ?? '',
                    'played' => $row['played'] ?? 0,
                    'won' => $row['won'] ?? 0,
                    'drawn' => $row['drawn'] ?? 0,
                    'lost' => $row['lost'] ?? 0,
                    'difference' => $row['difference'] ?? 0,
                    'points' => $row['points'] ?? 0,
                    'form' => [],
                ])->all(),
                /*
                 * Every bout of the class, not only the group ones.
                 *
                 * A pool of four ends with a FINAL between its top two, and the
                 * table's leader is not always the one who wins it — so a page
                 * that showed the table and the group bouts alone flatly
                 * contradicted the winners page beside it, with nothing on it
                 * to explain why the athlete at the top of the table was not
                 * the champion. The deciding bout belongs on the same page as
                 * the table it decided.
                 */
                'fixtures' => $pool->matches()
                    ->orderByRaw("case when round = ? then 0 else 1 end", [GroupEngine::ROUND])
                    ->orderBy('match_no')->orderBy('slot')->get()
                    ->filter(fn (EventMatch $m) => filled($m->a_name) || filled($m->b_name))
                    ->map(fn (EventMatch $m) => [
                        'matchday' => null,
                        'home' => $m->a_name,
                        'away' => $m->b_name,
                        'home_score' => $m->winner ? $this->score($m, 'a') : null,
                        'away_score' => $m->winner ? $this->score($m, 'b') : null,
                        'played' => $m->status === 'done' && $m->winner !== null,
                        // The group's bouts are numbered; the bout that decided
                        // the class says what it WAS.
                        'kickoff' => $m->round === GroupEngine::ROUND
                            ? ($m->match_no ? '#'.$m->match_no : null)
                            : $m->round,
                    ])->values()->all(),
            ];
        })->values()->all();

        return [
            'key' => 'table',
            'icon' => 'bi-table',
            'label' => __('events.public_pools_title'),
            'sub' => trans_choice('events.public_pools_count', count($tables), ['count' => count($tables)]),
            'data' => ['tables' => $tables],
        ];
    }

    /**
     * A pool bout's score for one side.
     *
     * A combat bout carries its score as free text the mat wrote ("4-2"), not
     * as two columns, so a missing or unreadable one becomes a win/loss of
     * 1-0 rather than a blank — the table is built on wins either way, and a
     * reader should see who beat whom.
     */
    private function score(EventMatch $match, string $side): int
    {
        $raw = trim((string) $match->{$side.'_score'});

        if ($raw !== '' && is_numeric($raw)) {
            return (int) $raw;
        }

        return $match->winner === $side ? 1 : 0;
    }
}
