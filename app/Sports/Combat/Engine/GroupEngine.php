<?php

namespace App\Sports\Combat\Engine;

use App\Models\ClubEvent;
use App\Models\EventCategory;
use App\Models\EventMatch;
use Illuminate\Support\Collection;

/**
 * A division run as a GROUP STAGE that feeds a knockout final.
 *
 * The other shape a competition can take, beside the single-elimination ladder
 * DrawEngine cuts. Everyone in the division meets everyone else; the table
 * decides who goes through; the medals are settled in a knockout of the leaders.
 * Asked for on 2026-09-07 ("group stage feeds a knockout final").
 *
 * ── Why a round robin is not just "more bouts" ───────────────────────────────
 * A knockout answers "who beat whom" and needs no memory: the bracket IS the
 * record, and a winner walks forward one slot. A group answers "who did best",
 * which exists nowhere on any single bout — it is DERIVED, every time, from all
 * of them. So there is no table stored anywhere: `standings()` replays the
 * finished bouts, exactly as the bout-video timeline is derived from the
 * officiating log rather than typed. Correct a result and the table is simply
 * right on the next read, with nothing to migrate or invalidate.
 *
 * ── The shape ───────────────────────────────────────────────────────────────
 * One division, three possible stages, chosen by how many entered:
 *
 *   n ≥ 6   group → semifinals (top 4) → final
 *   4 ≤ n ≤ 5   group → final (top 2)
 *   n ≤ 3   group only; the table decides the medals
 *
 * A group of five already has everyone meeting everyone, so semifinals between
 * four of those five would replay bouts that just happened. Below four there is
 * nothing a knockout would add at all: three people who have each fought each
 * other have answered the question.
 *
 * ── What it does NOT own ────────────────────────────────────────────────────
 * Nothing sport-specific. Points are win/draw/loss, the tie-breaks are the
 * standard federation ladder, and both are one method each so a sport that
 * grades differently overrides in one place rather than forking the engine
 * (CLAUDE.md, "Shared Stays Shared"). Scheduling, mats and bout numbers stay
 * with Scheduler; medals stay with Advancement.
 */
class GroupEngine
{
    /** The round label every group bout carries. Knockout labels are DrawEngine's. */
    public const ROUND = 'Group';

    public const POINTS_WIN = 3;

    public const POINTS_DRAW = 1;

    public function __construct(private Scheduler $scheduler) {}

    /**
     * (Re)build a division as a group stage plus its knockout.
     *
     * Mirrors DrawEngine::build's contract exactly — same arguments, same
     * side effects, same `draw_state` bookkeeping — so the caller never has to
     * know which shape a division is. DrawEngine::build() dispatches here.
     *
     * @param  bool  $paidOnly  the FINAL draw: only officially verified entries.
     */
    public function build(ClubEvent $event, EventCategory $cat, bool $paidOnly): void
    {
        $competitors = $this->competitors($cat, $paidOnly);

        $cat->matches()->delete();

        $n = $competitors->count();

        if ($n < 2) {
            $cat->update([
                'draw_state' => $paidOnly ? 'final' : 'provisional',
                'draw_count' => $n,
            ]);

            return;
        }

        $slot = 0;

        /*
         * The group, in CIRCLE order rather than the naive nested loop.
         *
         * A nested loop gives one competitor every one of their bouts back to
         * back at the top of the list, which on the day means one person
         * fighting five times while another waits. The circle method rotates a
         * fixed opponent against a turning ring, so each round is a set of
         * simultaneous pairings and nobody appears twice in the same round —
         * which is what makes the running order humane once Scheduler numbers
         * it.
         */
        foreach ($this->circleRounds($n) as $round) {
            foreach ($round as [$i, $j]) {
                $a = $competitors[$i];
                $b = $competitors[$j];

                $cat->matches()->create([
                    'event_id' => $event->id,
                    'round' => self::ROUND,
                    'phase' => 'preliminary',
                    'slot' => $slot++,
                    'a_name' => $a['name'],
                    'a_competitor_id' => $a['id'],
                    'a_country' => $a['country'],
                    'a_provisional' => $a['provisional'],
                    'b_name' => $b['name'],
                    'b_competitor_id' => $b['id'],
                    'b_country' => $b['country'],
                    'b_provisional' => $b['provisional'],
                    'status' => 'upcoming',
                ]);
            }
        }

        // The knockout, empty. Built now rather than when the group ends so the
        // day can be scheduled, mats assigned and bouts numbered before a
        // single result exists — and so the board shows where the group leads.
        foreach ($this->knockoutRounds($n) as $round => $count) {
            for ($i = 0; $i < $count; $i++) {
                $cat->matches()->create([
                    'event_id' => $event->id,
                    'round' => $round,
                    'phase' => $round === 'Final' ? 'finals' : 'finals',
                    'slot' => $slot++,
                    'status' => 'upcoming',
                ]);
            }
        }

        $cat->update([
            'draw_state' => $paidOnly ? 'final' : 'provisional',
            'draw_count' => $n,
            'schedule' => $cat->schedule ?: $this->scheduler->defaultSchedule($event, $cat),
            'status' => ($paidOnly && $cat->status === 'enrolling') ? 'live' : $cat->status,
        ]);
    }

    /**
     * The table, derived from the group's finished bouts.
     *
     * Every entrant appears, including one who has not fought yet — a table
     * that hides the people with no results is a table nobody can read the
     * fixtures against.
     *
     * @return array<int, array<string, mixed>>
     */
    public function standings(EventCategory $cat): array
    {
        $bouts = $cat->matches()->where('round', self::ROUND)->orderBy('slot')->get();

        if ($bouts->isEmpty()) {
            return [];
        }

        $rows = [];

        // Seed the table from the FIXTURES, so the order is the draw's order
        // and not the order results happened to arrive in.
        foreach ($bouts as $bout) {
            foreach (['a', 'b'] as $side) {
                $key = $this->key($bout, $side);

                if ($key === null || isset($rows[$key])) {
                    continue;
                }

                $rows[$key] = [
                    'key' => $key,
                    'competitor_id' => $bout->{$side.'_competitor_id'},
                    'name' => $bout->{$side.'_name'},
                    'country' => $bout->{$side.'_country'},
                    'played' => 0, 'won' => 0, 'drawn' => 0, 'lost' => 0,
                    'scored' => 0, 'conceded' => 0, 'points' => 0,
                ];
            }
        }

        foreach ($bouts as $bout) {
            if ($bout->status !== 'done') {
                continue;
            }

            $a = $this->key($bout, 'a');
            $b = $this->key($bout, 'b');

            // A bye inside a group is not a bout: nobody was met, so nothing
            // is recorded. It can only appear if an entrant was lifted out
            // after the fixtures were cut.
            if ($a === null || $b === null) {
                continue;
            }

            $as = (int) $bout->a_score;
            $bs = (int) $bout->b_score;

            $rows[$a]['played']++;
            $rows[$b]['played']++;
            $rows[$a]['scored'] += $as;
            $rows[$a]['conceded'] += $bs;
            $rows[$b]['scored'] += $bs;
            $rows[$b]['conceded'] += $as;

            if ($bout->winner === 'a') {
                $rows[$a]['won']++;
                $rows[$b]['lost']++;
                $rows[$a]['points'] += self::POINTS_WIN;
            } elseif ($bout->winner === 'b') {
                $rows[$b]['won']++;
                $rows[$a]['lost']++;
                $rows[$b]['points'] += self::POINTS_WIN;
            } else {
                $rows[$a]['drawn']++;
                $rows[$b]['drawn']++;
                $rows[$a]['points'] += self::POINTS_DRAW;
                $rows[$b]['points'] += self::POINTS_DRAW;
            }
        }

        $table = $this->order(collect($rows), $bouts);

        return $table->values()->map(function (array $row, int $i) {
            $row['position'] = $i + 1;
            $row['difference'] = $row['scored'] - $row['conceded'];

            return $row;
        })->all();
    }

    /** Has every bout in the group been decided? */
    public function groupComplete(EventCategory $cat): bool
    {
        $bouts = $cat->matches()->where('round', self::ROUND)->get(['status']);

        return $bouts->isNotEmpty() && $bouts->every(fn ($b) => $b->status === 'done');
    }

    /**
     * Put the leaders into the knockout, once the group has finished.
     *
     * Idempotent, and re-seeds on every call: correcting a group result can
     * change who goes through, and a knockout still holding the person the
     * table no longer sends is the exact failure this has to avoid. A knockout
     * bout that has already been FOUGHT is left alone — a result is a record of
     * something that happened, and an organiser correcting a group score after
     * a semifinal was contested has a problem no engine should silently solve.
     *
     * @return array<int, EventMatch>  the bouts that changed
     */
    public function seed(EventCategory $cat): array
    {
        $knockout = $cat->matches()
            ->where('round', '!=', self::ROUND)
            ->orderBy('slot')->get();

        if ($knockout->isEmpty() || ! $this->groupComplete($cat)) {
            return [];
        }

        $table = $this->standings($cat);
        $first = $knockout->where('round', $knockout->first()->round);

        // Top 4 into two semifinals (1v4, 2v3), or top 2 straight into a final.
        $pairs = $first->count() === 2
            ? [[0, 3], [1, 2]]
            : [[0, 1]];

        $changed = [];

        foreach ($first->values() as $i => $bout) {
            [$x, $y] = $pairs[$i] ?? [null, null];

            if ($x === null || $this->contested($bout)) {
                continue;
            }

            $before = [$bout->a_competitor_id, $bout->a_name, $bout->b_competitor_id, $bout->b_name];

            $this->place($bout, 'a', $table[$x] ?? null);
            $this->place($bout, 'b', $table[$y] ?? null);

            if ($before !== [$bout->a_competitor_id, $bout->a_name, $bout->b_competitor_id, $bout->b_name]) {
                $bout->save();
                $changed[] = $bout;
            }
        }

        return $changed;
    }

    /**
     * The medals a group decides on its own — the shape with no knockout at
     * all (three entrants or fewer). Returns [] for a division that has one, so
     * the caller can fall through to the bracket's own podium.
     *
     * @return array<int, array{place: int, name: string, competitor_id: ?int}>
     */
    public function podium(EventCategory $cat): array
    {
        $hasKnockout = $cat->matches()->where('round', '!=', self::ROUND)->exists();

        if ($hasKnockout || ! $this->groupComplete($cat)) {
            return [];
        }

        return collect($this->standings($cat))->take(3)
            ->map(fn (array $row, int $i) => [
                'place' => $i + 1,
                'name' => $row['name'],
                'competitor_id' => $row['competitor_id'],
            ])->all();
    }

    /* ==================== Internals ==================== */

    /**
     * Rank the table.
     *
     * Points, then the tie-breaks in the order every federation writes them:
     *
     *   1. points
     *   2. the result BETWEEN the tied competitors (head to head) — a mini
     *      table of just their own bouts, so a three-way tie is broken by how
     *      those three did against each other and nobody else
     *   3. score difference
     *   4. score for
     *   5. the draw's own order, so the table is stable and never reshuffles
     *      itself between two reads
     *
     * @param  Collection<string, array<string, mixed>>  $rows
     * @param  Collection<int, EventMatch>  $bouts
     * @return Collection<int, array<string, mixed>>
     */
    private function order(Collection $rows, Collection $bouts): Collection
    {
        $seed = $rows->keys()->flip();   // key => original position

        return $rows->values()->sort(function (array $x, array $y) use ($rows, $bouts, $seed) {
            if ($x['points'] !== $y['points']) {
                return $y['points'] <=> $x['points'];
            }

            $head = $this->headToHead($rows, $bouts, $x['points']);

            if (isset($head[$x['key']], $head[$y['key']]) && $head[$x['key']] !== $head[$y['key']]) {
                return $head[$y['key']] <=> $head[$x['key']];
            }

            $dx = $x['scored'] - $x['conceded'];
            $dy = $y['scored'] - $y['conceded'];

            if ($dx !== $dy) {
                return $dy <=> $dx;
            }

            if ($x['scored'] !== $y['scored']) {
                return $y['scored'] <=> $x['scored'];
            }

            return $seed[$x['key']] <=> $seed[$y['key']];
        });
    }

    /**
     * Points won in the bouts the tied competitors played against EACH OTHER.
     *
     * @param  Collection<string, array<string, mixed>>  $rows
     * @param  Collection<int, EventMatch>  $bouts
     * @return array<string, int>
     */
    private function headToHead(Collection $rows, Collection $bouts, int $points): array
    {
        $tied = $rows->filter(fn (array $r) => $r['points'] === $points)->keys()->all();

        if (count($tied) < 2) {
            return [];
        }

        $mini = array_fill_keys($tied, 0);

        foreach ($bouts as $bout) {
            if ($bout->status !== 'done') {
                continue;
            }

            $a = $this->key($bout, 'a');
            $b = $this->key($bout, 'b');

            if ($a === null || $b === null || ! in_array($a, $tied, true) || ! in_array($b, $tied, true)) {
                continue;
            }

            if ($bout->winner === 'a') {
                $mini[$a] += self::POINTS_WIN;
            } elseif ($bout->winner === 'b') {
                $mini[$b] += self::POINTS_WIN;
            } else {
                $mini[$a] += self::POINTS_DRAW;
                $mini[$b] += self::POINTS_DRAW;
            }
        }

        return $mini;
    }

    /**
     * The pairings, round by round, by the circle method.
     *
     * @return array<int, array<int, array{0: int, 1: int}>>
     */
    private function circleRounds(int $n): array
    {
        // An odd field gets a phantom opponent; a pairing against it is simply
        // not scheduled, which is how a competitor sits a round out.
        $ghost = $n % 2 === 1 ? $n : null;
        $size = $ghost === null ? $n : $n + 1;

        $order = range(0, $size - 1);
        $rounds = [];

        for ($r = 0; $r < $size - 1; $r++) {
            $pairs = [];

            for ($i = 0; $i < $size / 2; $i++) {
                $x = $order[$i];
                $y = $order[$size - 1 - $i];

                if ($x === $ghost || $y === $ghost) {
                    continue;
                }

                // Alternate who is corner A, so nobody spends the whole group
                // on one side of the mat.
                $pairs[] = $r % 2 === 0 ? [$x, $y] : [$y, $x];
            }

            $rounds[] = $pairs;

            // Rotate everything but the first position.
            $fixed = array_shift($order);
            $last = array_pop($order);
            array_unshift($order, $last);
            array_unshift($order, $fixed);
        }

        return $rounds;
    }

    /**
     * Which knockout bouts a field of this size gets, largest round first.
     *
     * @return array<string, int>
     */
    private function knockoutRounds(int $n): array
    {
        if ($n >= 6) {
            return ['Semifinal' => 2, 'Final' => 1];
        }

        if ($n >= 4) {
            return ['Final' => 1];
        }

        return [];
    }

    /**
     * The entrants, in the same shape and the same stable order DrawEngine
     * uses — so the two engines seed a division identically and switching a
     * division's format never reshuffles who is in it.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function competitors(EventCategory $cat, bool $paidOnly): Collection
    {
        return $cat->registrations()
            ->where('role', 'participant')
            ->when($paidOnly, fn ($q) => $q
                ->where('paid', true)->whereNotNull('paid_by')
                ->whereNotNull('weight')->whereNotNull('weighed_in_by'))
            ->with('user:id,full_name,name')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->user?->full_name ?? $r->user?->name ?? 'Athlete',
                'country' => $r->countryCode(),
                'provisional' => ! $paidOnly && (
                    ! $r->paid || ! $r->paid_by || $r->weight === null || ! $r->weighed_in_by
                ),
                'key' => md5($cat->id.':'.$r->user_id),
            ])
            ->sortBy('key')
            ->values();
    }

    /** A stable identity for one side of a bout: the entry, or the typed name. */
    private function key(EventMatch $bout, string $side): ?string
    {
        $id = $bout->{$side.'_competitor_id'};

        if ($id) {
            return 'r'.$id;
        }

        $name = trim((string) $bout->{$side.'_name'});

        return $name === '' ? null : 'n'.mb_strtolower($name);
    }

    /** Has this knockout bout been contested? Then it is not re-seeded. */
    private function contested(EventMatch $bout): bool
    {
        return $bout->status === 'done'
            || $bout->winner !== null
            || (int) $bout->a_score > 0
            || (int) $bout->b_score > 0;
    }

    /** @param  array<string, mixed>|null  $row */
    private function place(EventMatch $bout, string $side, ?array $row): void
    {
        $bout->{$side.'_name'} = $row['name'] ?? null;
        $bout->{$side.'_competitor_id'} = $row['competitor_id'] ?? null;
        $bout->{$side.'_country'} = $row['country'] ?? null;
        $bout->{$side.'_score'} = null;
    }
}
