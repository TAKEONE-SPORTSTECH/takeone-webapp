<?php

namespace App\Events\Support\Tournament;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Hand-arranging a division's draw before it starts.
 *
 * The auto-cut draw is a starting point, not a verdict: an organiser knows
 * things the seeding cannot — two athletes from the same club drawn together in
 * round one, a late withdrawal, a competitor who must fight early to catch a
 * flight. Until the first bout is due, they may move competitors between
 * first-round slots or take them out of the draw entirely and put them back
 * where they choose.
 *
 * What it will NOT do:
 *  - touch a draw once the championship has started (the draw is final from the
 *    first bout — nobody's opponent changes under them mid-event),
 *  - place anyone who is not an entrant in THIS division,
 *  - let a competitor stand in two slots at once,
 *  - accept a name from the client. Every move names a REGISTRATION; the name,
 *    the country and the provisional flag are read from it server-side.
 *
 * Later rounds are never arrangeable — they are derived from what happens in
 * the earlier ones, and are re-derived after every move.
 */
abstract class Arrangement
{
    use SpeaksPackageLanguage;

    public function __construct(private Advancement $advancement) {}

    /**
     * Apply one move and return the division's fresh state.
     *
     * @param  array  $payload  {category_id, from:{…}, to:{…}} where each end is
     *                          {type:'bench'} or {type:'slot', match_id, side}
     * @return array{ok: bool, message?: string, category?: EventCategory}
     */
    public function move(ClubEvent $event, array $payload): array
    {
        /*
         * ⚠️ ONE MOVE SURVIVES THE START: FILLING AN EMPTY SLOT FROM THE BENCH
         * (2026-09-18, at the user's instruction).
         *
         * The draw locking at the start is right — an arranged bracket is a
         * published promise and shuffling it mid-competition changes who meets
         * whom after people have seen it. But the lock was total, and that left
         * no way to fix its own worst side effect: an athlete who is enrolled,
         * in the right weight class, and simply not ON the board. Nine of them
         * appeared at 08:01 on 2026-09-18 when the day started and each division
         * was re-cut to its verified entries. The organiser could see them on
         * the bench and could not put them anywhere.
         *
         * So exactly one shape is still allowed after the start: bench → an
         * EMPTY first-round corner. It adds somebody who was missing and can do
         * nothing else — it cannot move a competitor who is already drawn, it
         * cannot swap two, and `endpoint()` has always refused a later round.
         * Everything the lock was protecting stays protected, because a corner
         * with nobody in it is not part of anybody's promise.
         *
         * A bye's empty corner is a legitimate destination, and filling one
         * turns the walkover back into a bout — `reflow()` re-cuts the rounds
         * above it, so the athlete who was advancing unopposed stops advancing
         * until they have won. That is the correct outcome and the reason this
         * goes through the normal path rather than writing the corner by hand.
         */
        $afterStart = $event->hasStarted() || $event->hasEnded();

        /*
         * ⚠️ THE ONE THING A RUNNING EVENT STILL REFUSES: THE BOUT ON THE MAT.
         *
         * The lock was total, then it allowed bench → empty corner, and it is
         * now open, because a draw laid wrong is not a draw worth protecting.
         * An organiser who finds their brackets mis-built on the morning has to
         * be able to put them right; refusing that does not preserve a
         * competition, it stops one.
         *
         * What stays refused is the bout being FOUGHT. A mat holds its own
         * snapshot of the two corners, taken when the bout was loaded, and it
         * files its result against that row when the officials declare. Change
         * the row underneath and the table is scoring one bout while the
         * bracket describes another — which is exactly the state Group D was
         * left in at 11:40 on 2026-09-18, with Mat 1 live on a semi-final whose
         * corners had just been emptied.
         *
         * Everything else is the organiser's to change, including a bout that
         * has already been decided: correcting one re-cuts what follows it,
         * which is the documented behaviour of Advancement and is what "fixing
         * the bracket" actually means.
         */
        if ($afterStart) {
            $onMat = $this->boutOnAMat($event, $payload);

            if ($onMat !== null) {
                return ['ok' => false, 'message' => __('events::events.bracket_bout_on_mat', ['bout' => $onMat])];
            }
        }

        $category = $event->categories()->find($payload['category_id'] ?? null);
        if (! $category) {
            return ['ok' => false, 'message' => __('events::events.division_not_found')];
        }

        /*
         * ⚠️ A POOL IS NOT ARRANGED.
         *
         * Arranging is a bracket idea: a draw has SLOTS, and who stands in
         * which one decides who meets whom, so an organiser moves people
         * between them. A round robin has no such choice to make — everybody
         * meets everybody, and the fixtures ARE the entrant list. Dragging a
         * competitor out of one group bout leaves that fixture with an empty
         * corner while every other fixture still names them, which is not a
         * rearranged pool, it is a broken one. (The server used to answer
         * "Draw updated" and do exactly that.)
         *
         * The action that belongs here is adding or removing somebody from the
         * class — `me.events.divisions.members` — after which the pool is
         * re-cut around whoever is left.
         */
        if (($category->format ?: EventCategory::FORMAT_KNOCKOUT) === EventCategory::FORMAT_ROUND_ROBIN) {
            return ['ok' => false, 'message' => $this->t('pool_not_arranged')];
        }

        $first = $this->firstRound($category);
        if ($first->isEmpty()) {
            return ['ok' => false, 'message' => __('events::events.bracket_not_drawn')];
        }

        $from = $this->endpoint($payload['from'] ?? [], $first);
        $to = $this->endpoint($payload['to'] ?? [], $first);

        if ($from === null || $to === null) {
            return ['ok' => false, 'message' => __('events::events.bracket_slot_invalid')];
        }

        // Nothing can be dragged from the bench to the bench.
        if ($from['type'] === 'bench' && $to['type'] === 'bench') {
            return ['ok' => false, 'message' => __('events::events.bracket_slot_invalid')];
        }

        $moving = $from['type'] === 'bench'
            ? $this->benchEntrant($category, $payload['from']['competitor_id'] ?? null, $first)
            : $this->occupant($from['match'], $from['side']);

        if (! $moving) {
            return ['ok' => false, 'message' => __('events::events.bracket_competitor_invalid')];
        }

        DB::transaction(function () use ($from, $to, $moving, $category) {
            // Whoever is standing where this competitor is going: they take the
            // vacated slot (a swap), or go back to the bench if the mover came
            // from there.
            $displaced = $to['type'] === 'slot' ? $this->occupant($to['match'], $to['side']) : null;

            if ($from['type'] === 'slot') {
                $this->place($from['match'], $from['side'], $displaced);
            }
            if ($to['type'] === 'slot') {
                $this->place($to['match'], $to['side'], $moving);
            }

            $this->reflow($category);
        });

        return ['ok' => true, 'category' => $category->fresh()];
    }

    /**
     * Empty every first-round slot into the bench — the blank canvas an
     * organiser starts from when they want to build the whole draw by hand.
     */
    public function clear(ClubEvent $event, EventCategory $category): array
    {
        if ($event->hasStarted() || $event->hasEnded()) {
            return ['ok' => false, 'message' => $this->t('draw_final')];
        }

        // Emptying a pool onto a bench is the same category mistake as moving
        // one competitor within it — see move().
        if (($category->format ?: EventCategory::FORMAT_KNOCKOUT) === EventCategory::FORMAT_ROUND_ROBIN) {
            return ['ok' => false, 'message' => $this->t('pool_not_arranged')];
        }

        DB::transaction(function () use ($category) {
            foreach ($this->firstRound($category) as $bout) {
                $this->place($bout, 'a', null);
                $this->place($bout, 'b', null);
            }

            $this->reflow($category);
        });

        return ['ok' => true, 'category' => $category->fresh()];
    }

    /**
     * Keep a hand-arranged draw honest when the entrant set moves under it: a
     * competitor who is no longer an entrant in this division is lifted out of
     * their slot. New entrants are NOT auto-placed — they appear on the bench
     * for the organiser to position, which is the whole point of arranging by
     * hand.
     */
    public function syncEntrants(EventCategory $category): void
    {
        /*
         * A POOL is never hand-arranged, so it is never kept in step by hand
         * either: its fixtures are simply what its entrant list implies, and
         * the honest answer to "the entrants moved" is to cut them again.
         *
         * This also repairs a division marked `manual` before arranging a pool
         * was refused. That flag told every caller "leave this alone, a human
         * placed it" — so such a pool was frozen: it could not be arranged (now
         * refused) and could not be re-cut (this branch). It is cut here and
         * rejoins the ordinary path.
         */
        if (($category->format ?: EventCategory::FORMAT_KNOCKOUT) === EventCategory::FORMAT_ROUND_ROBIN) {
            if ($event = $category->event) {
                app(\App\Sports\Combat\Engine\GroupEngine::class)
                    ->build($event, $category, paidOnly: $event->hasStarted());
            }

            return;
        }

        $entrantIds = $category->registrations()
            ->where('role', 'participant')->pluck('id')->all();

        $first = $this->firstRound($category);
        $changed = false;

        foreach ($first as $bout) {
            foreach (['a', 'b'] as $side) {
                $id = $bout->{$side.'_competitor_id'};

                // A hand-typed entrant (no registration behind them) stays put —
                // there is no entrant list they could have fallen off.
                if ($id === null || in_array($id, $entrantIds, true)) {
                    continue;
                }

                $this->place($bout, $side, null);
                $changed = true;
            }
        }

        if ($changed) {
            $this->reflow($category);
        }
    }

    /* ---------------- Internals ---------------- */

    /**
     * Resolve one end of a move, rejecting anything outside this division's
     * first round. Returns null when the client named a slot that is not
     * arrangeable — a later round, another division, another event.
     *
     * @param  Collection<int, EventMatch>  $first
     * @return array{type: string, match?: EventMatch, side?: string}|null
     */
    private function endpoint(array $end, Collection $first): ?array
    {
        $type = $end['type'] ?? null;

        if ($type === 'bench') {
            return ['type' => 'bench'];
        }

        if ($type !== 'slot') {
            return null;
        }

        $side = $end['side'] ?? null;
        if (! in_array($side, ['a', 'b'], true)) {
            return null;
        }

        $match = $first->firstWhere('id', (int) ($end['match_id'] ?? 0));

        return $match ? ['type' => 'slot', 'match' => $match, 'side' => $side] : null;
    }

    /**
     * Does this move touch a bout that is currently loaded on a mat?
     *
     * Returns the bout's number (or id, when it has no number yet) so the
     * refusal can NAME it — "the draw is locked" sends an organiser looking for
     * a setting, whereas "bout 23 is on a mat" sends them to the mat, which is
     * where the answer is. Either end of the move counts: a swap changes both.
     *
     * @return int|string|null null when nothing in this move is being fought
     */
    private function boutOnAMat(ClubEvent $event, array $payload): int|string|null
    {
        $onMats = \App\Scoreboard\Fleet::matchesOnMats($event);

        if ($onMats === []) {
            return null;
        }

        foreach (['from', 'to'] as $end) {
            if (($payload[$end]['type'] ?? null) !== 'slot') {
                continue;
            }

            $id = (int) ($payload[$end]['match_id'] ?? 0);

            if (in_array($id, $onMats, true)) {
                return EventMatch::find($id)?->match_no ?: $id;
            }
        }

        return null;
    }

    /**
     * An entrant sitting out of the draw. Must be a participant in THIS
     * division and must not already stand somewhere in it — otherwise one
     * person could be dropped into two slots and fight themselves.
     *
     * @param  Collection<int, EventMatch>  $first
     */
    private function benchEntrant(EventCategory $category, mixed $competitorId, Collection $first): ?ClubEventRegistration
    {
        $entry = ClubEventRegistration::where('id', (int) $competitorId)
            ->where('category_id', $category->id)
            ->where('role', 'participant')
            ->with('user:id,full_name,name')
            ->first();

        if (! $entry) {
            return null;
        }

        $placed = $category->matches()->get()
            ->flatMap(fn (EventMatch $m) => $m->competitorIds())->contains($entry->id);

        return $placed ? null : $entry;
    }

    /** Who currently stands in a slot, as the values that describe them. */
    private function occupant(EventMatch $match, string $side): ?array
    {
        if (! $match->{$side.'_name'} && ! $match->{$side.'_competitor_id'}) {
            return null;
        }

        return [
            'competitor_id' => $match->{$side.'_competitor_id'},
            'name' => $match->{$side.'_name'},
            'country' => $match->{$side.'_country'},
            'seed' => $match->{$side.'_seed'},
            'provisional' => (bool) $match->{$side.'_provisional'},
        ];
    }

    /** Write a competitor (or emptiness) into a slot. */
    private function place(EventMatch $match, string $side, ClubEventRegistration|array|null $who): void
    {
        $values = $who instanceof ClubEventRegistration
            ? [
                'competitor_id' => $who->id,
                'name' => $who->user?->full_name ?: $who->user?->name ?: __('events::events.athlete'),
                'country' => $who->countryCode(),
                'seed' => null,
                'provisional' => ! $who->paid || $who->weight === null,
            ]
            : $who;

        $match->fill([
            $side.'_competitor_id' => $values['competitor_id'] ?? null,
            $side.'_name' => $values['name'] ?? null,
            $side.'_country' => $values['country'] ?? null,
            $side.'_seed' => $values['seed'] ?? null,
            $side.'_provisional' => $values['provisional'] ?? false,
            $side.'_score' => null,
        ])->save();
    }

    /**
     * Re-derive the bracket after a move: a slot left alone in its bout is a
     * bye and its occupant advances, an empty or contested bout has no winner,
     * and every consequence of that is carried forward through the rounds.
     *
     * Safe to run wholesale because arranging is only possible before the first
     * bout — there are no real results to overwrite, only byes.
     */
    private function reflow(EventCategory $category): void
    {
        $first = $this->firstRound($category);

        foreach ($first as $bout) {
            $hasA = (bool) $bout->a_name;
            $hasB = (bool) $bout->b_name;

            /*
             * ⚠️ A DECIDED CONTEST IS A FACT, NOT SOMETHING TO RE-DERIVE.
             *
             * The line below reads a winner off the SHAPE of a bout: one corner
             * occupied means a walkover, so that corner won. That is right
             * while a draw is being arranged and nothing has been fought — and
             * catastrophic afterwards, because a bout with BOTH corners filled
             * fails the `xor` and was therefore set to `winner = null`. Every
             * real result in the round was wiped by any arrangement anywhere
             * else in it.
             *
             * It happened on 2026-09-18: one competitor was dropped into an
             * open corner of Group D and the three fought quarter-finals beside
             * it lost their winners, the semi-finals emptied, and the bout
             * standing on Mat 1 at that moment had its row blanked underneath
             * the table scoring it.
             *
             * So a contested bout that has been decided is left exactly alone.
             * Byes and empty slots still re-derive, which is the whole job this
             * loop was written for.
             */
            if ($hasA && $hasB && $bout->winner && ! $bout->won_by_bye) {
                continue;
            }

            /*
             * ⚠️ ARRANGING NEVER DECIDES A BOUT (2026-09-18, at the user's
             * instruction: "the system auto makes the fighters lose, which is
             * wrong" — "don't undo the winning results unless I go and do it
             * manually").
             *
             * The line that used to stand here read a winner off the SHAPE of
             * the bout: one corner occupied meant a walkover, so that corner
             * won. Cutting a draw may say that — a bye is genuinely part of the
             * layout, and DrawEngine writes it. An organiser moving people
             * around may not. Pulling somebody out of a bout to put them
             * somewhere else instantly awarded the bout to whoever was left,
             * walked them up the bracket, and recorded the person who had been
             * moved as beaten — a defeat nobody fought, written by a drag.
             *
             * So this loop now only ever TAKES a verdict away, and only one it
             * put there itself:
             *
             *   · a fought result is untouchable, and is left alone above;
             *   · a walkover whose empty corner has just been FILLED is stale —
             *     the bout is real now and has to be fought, so the verdict
             *     goes and the winner stops advancing (this is the bug that
             *     shipped this morning: the guard above could not tell a
             *     walkover from a result, kept it, and left a bout reading
             *     "done" with two names in it);
             *   · a walkover whose own winner has been moved OUT holds a
             *     verdict for somebody who is no longer there, so that goes
             *     too.
             *
             * A bout left with one name and no verdict simply waits. Nobody is
             * advanced by the machine and nobody is beaten by it; the organiser
             * declares the walkover themselves by tapping the card, exactly as
             * they declare every other result.
             */
            $decided = $hasA && $hasB;                 // a real contest to be fought
            $stale = $bout->winner && ($decided || ! $bout->{$bout->winner.'_name'});

            if ($stale) {
                $bout->winner = null;
                $bout->won_by_bye = false;
                $bout->status = 'upcoming';
                $bout->save();
            }
        }

        // Carry each first-round outcome (or the lack of one) forward. Propagate
        // walks the whole chain, so the later rounds re-derive themselves.
        foreach ($first as $bout) {
            $this->advancement->propagate($category, $bout);
        }

        $category->update(['draw_state' => 'manual', 'podium' => null]);
    }

    /**
     * The arrangeable round: the earliest one in the division.
     *
     * @return Collection<int, EventMatch>
     */
    private function firstRound(EventCategory $category): Collection
    {
        $matches = $category->matches()->orderBy('slot')->get();

        if ($matches->isEmpty()) {
            return collect();
        }

        $earliest = $matches->groupBy('round')
            ->sortBy(fn (Collection $bouts) => $bouts->min('slot'))
            ->first();

        return $earliest->sortBy('slot')->values();
    }
}
