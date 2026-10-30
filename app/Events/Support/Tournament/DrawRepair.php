<?php

namespace App\Events\Support\Tournament;

use App\Events\Contracts\AwardsDivisions;
use App\Events\Models\ClubEvent;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Sports\Combat\Engine\GroupEngine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The draw tidies itself after any hand edit (owner, 2026-10-25: "the system
 * must be smart, I don't need to feed instructions for obvious things").
 *
 * Several doors write a running draw — free edit, free move, live move, late
 * entry, result correction, exhibition links — and each was written to do its
 * own job, not to notice what that job left behind: a semi-final emptied to
 * one fighter still "waiting for a decision", a bye still carrying a bout
 * number, a winner who never reached their next corner. This is the one place
 * that notices, so a door added tomorrow is covered without remembering to be.
 *
 * It runs once per touched division, after the request has done its work
 * (EventMatch's `saved` hook queues it), and repairs only what is OBVIOUS:
 *  · a bout holding one fighter whose empty corner nothing can ever fill (the
 *    first round, or a feeder with nobody left in it) is that fighter's bye;
 *  · a winner whose next corner is EMPTY takes it;
 *  · a bye carries no bout number;
 *  · a bye that now has somebody in BOTH corners (a fighter added into its
 *    empty one) is a real bout again: its verdict goes, it takes a number on
 *    its division's mat, and the bye-holder steps back out of the next bout —
 *    only while that bout is unfought and unscored, else it is left alone.
 * Then the mat closes its gaps (MatNumbering) and every board re-reads.
 *
 * What it never does: overwrite an occupied corner, touch a bout that has a
 * result or a score, act before the competition has started (the draw is
 * still being arranged then, and Arrangement owns it), or read a shape that is
 * not a plain halving knockout (groups, round robin, play-ins are left alone).
 * Everything it changes goes to the activity log as `events.draw_repair`.
 *
 * EVENT_DRAW_SELF_REPAIR — off, and nothing here runs.
 */
final class DrawRepair
{
    /** @var array<int, true> divisions waiting to be tidied this request */
    private static array $queued = [];

    private static bool $running = false;

    public static function enabled(): bool
    {
        return (bool) config('events.draw_self_repair', false);
    }

    /** Queue a division for one tidy-up once the request's own work is done. */
    public static function touched(EventMatch $bout): void
    {
        if (self::$running || ! $bout->category_id || ! self::enabled()) {
            return;
        }
        if (self::$queued === []) {
            \Illuminate\Support\defer(fn () => self::flush(), 'draw-repair')->always();
        }
        self::$queued[(int) $bout->category_id] = true;
    }

    /** Tidy every queued division now. */
    public static function flush(): void
    {
        $ids = array_keys(self::$queued);
        self::$queued = [];
        if (! self::enabled()) {
            return;
        }
        foreach ($ids as $id) {
            $category = EventCategory::find($id);
            if ($category) {
                rescue(fn () => app(self::class)->repair($category), null, true);
            }
        }
    }

    /**
     * Tidy one division. Returns what changed: [bout id => [before, after]].
     *
     * @return array<int, array{0: array, 1: array}>
     */
    public function repair(EventCategory $category): array
    {
        $event = ClubEvent::find($category->event_id);
        if (! $event || ! $event->started_at) {
            return [];
        }

        self::$running = true;
        try {
            [$changed, $mats, $reopened] = DB::transaction(fn () => $this->tidy($category));

            if ($changed !== [] && MatNumbering::enabled()) {
                foreach ($mats as [$court, $day]) {
                    app(MatNumbering::class)->close($event->id, $court, $day);
                }
            }
            // A bye reopened as a real bout is called again: it needs a number.
            foreach ($reopened as $id) {
                if ($bout = EventMatch::find($id)) {
                    $this->number($bout);
                    $changed[$id][1] = $this->snapshot($bout->fresh());
                }
            }
        } finally {
            self::$running = false;
        }

        if ($changed === []) {
            return [];
        }

        rescue(fn () => activity()->withProperties([
            'event' => $event->uuid, 'division' => $category->id,
            'before' => array_column($changed, 0), 'after' => array_column($changed, 1),
        ])->log('events.draw_repair'), null, false);

        $type = rescue(fn () => app(\App\Events\EventTypeRegistry::class)->for($event), null, false);
        if ($type instanceof AwardsDivisions) {
            rescue(fn () => $type->awardDivision($category->fresh()), null, false);
        }
        if ($type && method_exists($type, 'announceDrawChanged')) {
            rescue(fn () => $type->announceDrawChanged($event, $category->id), null, false);
        }
        rescue(fn () => app(\App\Scoreboard\Fleet::class)->boardsChanged($event), null, false);

        return $changed;
    }

    /** @return array{0: array<int, array{0: array, 1: array}>, 1: list<array{0: string, 1: ?int}>, 2: list<int>} */
    private function tidy(EventCategory $category): array
    {
        $rounds = $this->rounds($category);
        if ($rounds === null) {
            return [[], [], []];
        }

        $changed = [];
        $mats = [];
        $reopened = [];
        $note = function (EventMatch $m, array $before) use (&$changed) {
            $changed[$m->id] = [$changed[$m->id][0] ?? $before, $this->snapshot($m)];
        };

        // Walk the rounds in order, so a bye decided in round r seats its
        // fighter in round r+1 before that round is read.
        foreach ($rounds as $r => $bouts) {
            foreach ($bouts as $i => $m) {
                $before = $this->snapshot($m);

                // A bye with somebody in both corners is a real bout again.
                if ($m->won_by_bye && $this->has($m, 'a') && $this->has($m, 'b')) {
                    if (! $this->blank($m)) {
                        // Points on the board: a real result that was only labelled a bye.
                        $m->won_by_bye = false;
                    } elseif ($this->reopen($m, $rounds[$r + 1][intdiv($i, 2)] ?? null, $i % 2 === 0 ? 'a' : 'b', $note)) {
                        $reopened[] = $m->id;
                    }
                }

                // A bye carries no number.
                if ($m->won_by_bye && $m->match_no !== null) {
                    $mats[] = [(string) $m->court, $m->day];
                    $m->match_no = null;
                    $m->number_pinned = false;
                }

                // One fighter, and nothing can ever fill the other corner: their bye.
                $a = $this->has($m, 'a');
                $b = $this->has($m, 'b');
                if (! $m->winner && $a !== $b && $this->blank($m) && ! $this->canFill($rounds, $r, $i, $a ? 'b' : 'a')) {
                    if ($m->match_no !== null) {
                        $mats[] = [(string) $m->court, $m->day];
                    }
                    $m->winner = $a ? 'a' : 'b';
                    $m->won_by_bye = true;
                    $m->status = 'done';
                    $m->a_score = $m->b_score = null;
                    $m->match_no = null;
                    $m->number_pinned = false;
                }

                if ($m->isDirty()) {
                    $m->save();
                    $note($m, $before);
                }

                // A winner whose next corner is empty takes it.
                $next = $rounds[$r + 1][intdiv($i, 2)] ?? null;
                if ($m->winner && $next && ! $next->winner) {
                    $side = $i % 2 === 0 ? 'a' : 'b';
                    if ($this->has($m, $m->winner) && ! $this->has($next, $side)) {
                        $nb = $this->snapshot($next);
                        foreach (['name', 'competitor_id', 'country', 'provisional'] as $col) {
                            $next->{$side.'_'.$col} = $m->{$m->winner.'_'.$col};
                        }
                        $next->{$side.'_score'} = null;
                        $next->save();
                        $note($next, $nb);
                    }
                }
            }
        }

        $mats = array_values(array_unique(array_filter($mats, fn ($x) => $x[0] !== ''), SORT_REGULAR));

        return [$changed, $mats, $reopened];
    }

    /**
     * Undo a bye that gained an opponent — unless the bye-holder has already
     * fought on (or been scored) in the next bout, which a reopen would
     * contradict. Takes the bye-holder back out of that next bout when they
     * stand in it. Does not save the bout itself.
     */
    private function reopen(EventMatch $m, ?EventMatch $next, string $side, \Closure $note): bool
    {
        $holder = $m->winner;
        if ($next && ($next->winner || (float) $next->{$side.'_score'} != 0)) {
            return false;
        }

        if ($next && $holder && $this->same($next, $side, $m, $holder)) {
            $nb = $this->snapshot($next);
            foreach (['name', 'competitor_id', 'country'] as $col) {
                $next->{$side.'_'.$col} = null;
            }
            $next->{$side.'_provisional'} = false;
            $next->{$side.'_score'} = null;
            $next->save();
            $note($next, $nb);
        }

        $m->winner = null;
        $m->won_by_bye = false;
        $m->status = 'upcoming';
        $m->a_score = $m->b_score = null;
        $m->win_reason = null;
        $m->win_note = null;
        $m->number_pinned = false;

        return true;
    }

    /** The same person in $side of $a as in $from's $fromSide of $b — by entry, else by the typed name. */
    private function same(EventMatch $a, string $side, EventMatch $b, string $fromSide): bool
    {
        $id = $b->{$fromSide.'_competitor_id'};

        return $id
            ? (int) $a->{$side.'_competitor_id'} === (int) $id
            : ! $a->{$side.'_competitor_id'} && trim((string) $a->{$side.'_name'}) === trim((string) $b->{$fromSide.'_name'});
    }

    /**
     * A reopened bout's number: placed in order on its division's mat
     * (MatNumbering), or — with renumbering off — the next number on that mat.
     */
    private function number(EventMatch $bout): void
    {
        if ($bout->match_no !== null) {
            return;
        }
        if (MatNumbering::enabled()) {
            app(MatNumbering::class)->place($bout);

            return;
        }

        $mate = EventMatch::where('category_id', $bout->category_id)->whereNotNull('court')->whereNotNull('match_no')->orderBy('slot')->first(['court', 'day']);
        $court = $bout->court ?: $mate?->court;
        if (! $court) {
            return;
        }
        $day = $bout->court ? $bout->day : $mate?->day;
        $bout->court = $court;
        $bout->day = $day;
        $bout->match_no = (int) EventMatch::where('event_id', $bout->event_id)->where('court', $court)
            ->when($day !== null, fn ($q) => $q->where('day', $day))
            ->max(\Illuminate\Support\Facades\DB::raw('CAST(match_no AS INTEGER)')) + 1;
        $bout->save();
    }

    /**
     * Somebody stands in this corner: an entry, or a name typed by hand for an
     * entrant with no account behind them — a typed name is a person too.
     */
    private function has(EventMatch $m, string $side): bool
    {
        return (bool) $m->{$side.'_competitor_id'} || trim((string) $m->{$side.'_name'}) !== '';
    }

    /** Nothing scored in it: a bout with points on the board is not ours to decide. */
    private function blank(EventMatch $m): bool
    {
        return (float) $m->a_score == 0 && (float) $m->b_score == 0;
    }

    /** Could anybody still arrive in this corner? Only through a feeder that holds, or can still receive, someone. */
    private function canFill(array $rounds, int $r, int $i, string $side): bool
    {
        if ($r === 0) {
            return false;
        }
        $j = $i * 2 + ($side === 'a' ? 0 : 1);
        $feeder = $rounds[$r - 1][$j] ?? null;
        if (! $feeder) {
            return false;
        }
        if ($feeder->winner) {
            return $this->has($feeder, $feeder->winner);
        }
        if ($this->has($feeder, 'a') || $this->has($feeder, 'b')) {
            return true;
        }

        return $this->canFill($rounds, $r - 1, $j, 'a') || $this->canFill($rounds, $r - 1, $j, 'b');
    }

    /**
     * The division as a plain halving knockout, rounds in order — or null for
     * any other shape (groups, round robin, a play-in round), which is left alone.
     *
     * @return list<list<EventMatch>>|null
     */
    private function rounds(EventCategory $category): ?array
    {
        $rounds = EventMatch::where('category_id', $category->id)
            ->where('round', '!=', Advancement::ROUND_THIRD)
            ->orderBy('slot')->get()
            ->groupBy('round')
            ->sortBy(fn (Collection $bouts) => $bouts->min('slot'))
            ->map(fn (Collection $bouts) => $bouts->values()->all())
            ->values()->all();

        if ($rounds === [] || count(end($rounds)) !== 1) {
            return null;
        }
        foreach ($rounds as $r => $bouts) {
            if ($r > 0 && count($rounds[$r - 1]) !== 2 * count($bouts)) {
                return null;
            }
            foreach ($bouts as $m) {
                if ($m->round === GroupEngine::ROUND || $m->group_no) {
                    return null;
                }
            }
        }

        return $rounds;
    }

    private function snapshot(EventMatch $m): array
    {
        return $m->only(['id', 'match_no', 'round', 'a_competitor_id', 'a_name', 'b_competitor_id', 'b_name', 'winner', 'won_by_bye', 'status']);
    }
}
