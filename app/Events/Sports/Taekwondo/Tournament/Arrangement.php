<?php

namespace App\Events\Sports\Taekwondo\Tournament;

use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventCategory;
use App\Models\EventMatch;
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
class Arrangement
{
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
        if ($event->hasStarted() || $event->hasEnded()) {
            return ['ok' => false, 'message' => __('event-taekwondo_tournament::messages.draw_final')];
        }

        $category = $event->categories()->find($payload['category_id'] ?? null);
        if (! $category) {
            return ['ok' => false, 'message' => __('events.division_not_found')];
        }

        $first = $this->firstRound($category);
        if ($first->isEmpty()) {
            return ['ok' => false, 'message' => __('events.bracket_not_drawn')];
        }

        $from = $this->endpoint($payload['from'] ?? [], $first);
        $to = $this->endpoint($payload['to'] ?? [], $first);

        if ($from === null || $to === null) {
            return ['ok' => false, 'message' => __('events.bracket_slot_invalid')];
        }

        // Nothing can be dragged from the bench to the bench.
        if ($from['type'] === 'bench' && $to['type'] === 'bench') {
            return ['ok' => false, 'message' => __('events.bracket_slot_invalid')];
        }

        $moving = $from['type'] === 'bench'
            ? $this->benchEntrant($category, $payload['from']['competitor_id'] ?? null, $first)
            : $this->occupant($from['match'], $from['side']);

        if (! $moving) {
            return ['ok' => false, 'message' => __('events.bracket_competitor_invalid')];
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
            return ['ok' => false, 'message' => __('event-taekwondo_tournament::messages.draw_final')];
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
                'name' => $who->user?->full_name ?: $who->user?->name ?: __('events.athlete'),
                'country' => $who->meta ?: null,
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

            $bout->winner = ($hasA xor $hasB) ? ($hasA ? 'a' : 'b') : null;
            $bout->status = $bout->winner ? 'done' : 'upcoming';
            $bout->save();
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
