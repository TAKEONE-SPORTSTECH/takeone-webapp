<?php

namespace App\Events\Support\Tournament;

use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Sports\Combat\Engine\DrawEngine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Admitting an athlete to a division that is ALREADY BEING FOUGHT.
 *
 * ── Why this exists ───────────────────────────────────────────────────────
 *
 * A competitor walks up to the desk mid-morning and says he is meant to be in
 * this division. He is entered, he is eligible, and three of its bouts have
 * already been fought. Every existing door refuses him:
 *
 *   · `generate_draw` and `grow_draw` re-cut the division from scratch, so they
 *     are withdrawn the moment anything has been fought — correctly, because a
 *     re-cut DELETES results.
 *   · `arrange` can drop him into an empty corner, and a full bracket has none.
 *
 * So the honest answer the system gave was "no", and the honest answer a
 * tournament needs is "yes, here, and nothing that happened is disturbed".
 * That is this class. It never re-cuts anything: it finds the one place the
 * newcomer can stand without spending a result, and if there is no such place
 * it MAKES one by growing the ladder a round at the front — which costs the
 * athletes already drawn a walkover each and costs the record nothing.
 *
 * ── The rule everything here obeys ────────────────────────────────────────
 *
 * A BOUT THAT WAS FOUGHT IS A FACT. This class reads results; it never writes
 * over one, and it refuses rather than touch one. "Fought" is `EventMatch`'s
 * own `contested` test — two corners filled and an outcome — plus anything
 * standing on a mat this second, because a bout being scored is a result that
 * has not been filed yet.
 *
 * A bye is NOT a result. It is scaffolding the draw wrote, and turning one back
 * into a real bout is exactly what admitting a late entrant means.
 */
class LateEntry
{
    public function __construct(private PropagatesResults $advancement) {}

    /**
     * Put a late entrant onto a division's board.
     *
     * @return array{ok: bool, message?: string, category?: EventCategory, how?: string, opponent?: ?string}
     */
    public function admit(ClubEvent $event, EventCategory $category, ClubEventRegistration $entry): array
    {
        /*
         * A POOL is not grafted onto, it is re-cut. Everybody meets everybody,
         * so one more entrant means one more fixture against each of them —
         * which the group engine already does from the entrant list. Sending a
         * pool through here would produce a ladder inside a round robin.
         */
        if (($category->format ?: EventCategory::FORMAT_KNOCKOUT) === EventCategory::FORMAT_ROUND_ROBIN) {
            return ['ok' => false, 'message' => __('events.late_entry_pool')];
        }

        if ($entry->category_id !== $category->id || $entry->role !== 'participant') {
            return ['ok' => false, 'message' => __('events.late_entry_not_an_entrant')];
        }

        $rounds = $this->rounds($category);

        if ($rounds->isEmpty()) {
            return ['ok' => false, 'message' => __('events.late_entry_no_draw')];
        }

        // Already standing somewhere: nobody fights themselves.
        $placed = $category->matches()->get()
            ->flatMap(fn (EventMatch $m) => $m->competitorIds())->contains($entry->id);

        if ($placed) {
            return ['ok' => false, 'message' => __('events.late_entry_already_drawn')];
        }

        $onMats = \App\Scoreboard\Fleet::matchesOnMats($event);
        $third = $category->matches()->where('round', Advancement::ROUND_THIRD)->first();

        /*
         * A division whose final has been fought is OVER, and that is a
         * different answer from "there is no room". Saying "nowhere to add
         * anybody" to somebody standing in front of a finished podium reads as
         * a fault in the system; saying the division has finished reads as the
         * truth, and sends the desk to the right conversation.
         */
        $final = $category->matches()->where('round', 'Final')->first();

        if ($final && $this->spent($final, $onMats)) {
            return ['ok' => false, 'message' => __('events.late_entry_division_over')];
        }

        // 1. A corner nobody is standing in, whose consequences are all still
        //    to be fought. The cheapest admission there is: no bout moves.
        if ($found = $this->freeCorner($rounds, $onMats, $third)) {
            [$bout, $side] = $found;

            $opponent = $bout->{($side === 'a' ? 'b' : 'a').'_name'};

            $this->fill($category, $bout, $side, $entry);

            return [
                'ok' => true,
                'category' => $category->fresh(),
                'how' => 'slot',
                'opponent' => $opponent,
            ];
        }

        // 2. No corner anywhere: grow the ladder by one round at the front.
        return $this->grow($event, $category, $entry, $rounds, $onMats, $third);
    }

    /* ---------------- Finding a place ---------------- */

    /**
     * An empty corner in the earliest round that can take somebody without
     * spending a result.
     *
     * Preference, and it is a fairness decision rather than a mechanical one:
     * a corner OPPOSITE somebody comes first, so the late entrant fights for
     * his place like everyone else, and a wholly empty bout — which would hand
     * him a walkover the others had to earn — is taken only when there is no
     * opponent to give him.
     *
     * @param  Collection<int, Collection<int, EventMatch>>  $rounds
     * @param  array<int, int>  $onMats
     * @return array{0: EventMatch, 1: string}|null
     */
    private function freeCorner(Collection $rounds, array $onMats, ?EventMatch $third): ?array
    {
        $first = $rounds->first();
        $fallback = null;

        foreach ($first as $i => $bout) {
            if ($this->spent($bout, $onMats)) {
                continue;
            }

            foreach (['a', 'b'] as $side) {
                if ($bout->{$side.'_name'}) {
                    continue;
                }

                if (! $this->chainClean($rounds, 0, $i, $onMats, $third)) {
                    continue;
                }

                // Somebody to fight: take it now.
                if ($bout->{($side === 'a' ? 'b' : 'a').'_name'}) {
                    return [$bout, $side];
                }

                $fallback ??= [$bout, $side];
            }
        }

        return $fallback;
    }

    /**
     * Is everything DOWNSTREAM of this bout still unfought?
     *
     * Placing somebody in a first-round corner un-advances whoever was walking
     * through it, and that ripples forward. The ripple is only allowed to reach
     * bouts nobody has fought — so this walks the same geometry `Advancement`
     * walks (bout i feeds bout ⌊i/2⌋ of the next round) and refuses as soon as
     * it meets a result.
     *
     * The play-off for bronze is checked alongside the semi-finals rather than
     * in the walk, because it sits outside the winner-walk by design: it is fed
     * by the semi-finals' LOSERS, so a change to a semi-final reaches it even
     * though no round follows it.
     *
     * @param  Collection<int, Collection<int, EventMatch>>  $rounds
     * @param  array<int, int>  $onMats
     */
    private function chainClean(Collection $rounds, int $r, int $i, array $onMats, ?EventMatch $third): bool
    {
        while (true) {
            $next = $rounds->get($r + 1);

            if (! $next) {
                return true;        // that was the final
            }

            $r++;
            $i = intdiv($i, 2);
            $bout = $next->get($i);

            if (! $bout) {
                return true;
            }

            if ($this->spent($bout, $onMats)) {
                return false;
            }

            if ($bout->round === 'Semifinal' && $third && $this->spent($third, $onMats)) {
                return false;       // bronze has already been settled off this semi-final
            }
        }
    }

    /**
     * Has this bout been fought, or is it being fought right now?
     *
     * Deliberately the same test `EventMatch::contested()` makes, written out
     * because this runs against models already in memory. A bye fails it — one
     * corner — which is the entire point: a bye is scaffolding and may be taken
     * back, a contest may not.
     *
     * @param  array<int, int>  $onMats
     */
    private function spent(EventMatch $bout, array $onMats): bool
    {
        if (in_array($bout->id, $onMats, true)) {
            return true;            // on a mat: a result that is not filed yet
        }

        if (! $bout->a_name || ! $bout->b_name) {
            return false;
        }

        return $bout->status === 'done'
            || $bout->winner !== null
            || (int) $bout->a_score > 0
            || (int) $bout->b_score > 0;
    }

    /* ---------------- Writing ---------------- */

    /** Stand a competitor in a corner, and take back the walkover it was. */
    private function fill(EventCategory $category, EventMatch $bout, string $side, ClubEventRegistration $entry): void
    {
        DB::transaction(function () use ($category, $bout, $side, $entry) {
            $bout->{$side.'_competitor_id'} = $entry->id;
            $bout->{$side.'_name'} = $this->nameOf($entry);
            $bout->{$side.'_country'} = $entry->countryCode();
            $bout->{$side.'_seed'} = null;
            $bout->{$side.'_provisional'} = ! $entry->paid || $entry->weight === null;
            $bout->{$side.'_score'} = null;

            /*
             * The bout had a winner because nobody was standing here. Somebody
             * is now, so it has to be fought — and whoever was walking through
             * stops walking until they have won it. `propagate` below carries
             * that forward; `chainClean` has already proved there is nothing
             * downstream worth protecting.
             */
            if ($bout->winner && $bout->won_by_bye) {
                $bout->winner = null;
                $bout->won_by_bye = false;
                $bout->status = 'upcoming';
            }

            $bout->save();

            $this->advancement->propagate($category, $bout);

            // A human placed this. `manual` is what stops any housekeeping pass
            // re-cutting the division out from under them.
            $category->update(['draw_state' => 'manual']);
        });
    }

    /**
     * Grow the ladder by one round at the front, so a full bracket has room.
     *
     * ── What this does, in a sentence ─────────────────────────────────────
     *
     * Every athlete already drawn gets a walkover into the bout they are
     * already in, and the newcomer gets a real bout against one of them. The
     * existing bouts are not touched: same rows, same ids, same numbers, same
     * results, same recordings filed against them.
     *
     * ── Why the incumbent stays in the round behind ───────────────────────
     *
     * The play-in's winner belongs in the bout the incumbent currently stands
     * in — and until it is fought, the incumbent stays there rather than the
     * slot reading "(tbd)". Two reasons: a board that empties a named corner
     * the moment a late entry is admitted alarms a whole hall, and if the
     * play-in never happens (he does not show, the desk changes its mind) the
     * division is still runnable. When the play-in IS fought, `propagate` puts
     * the winner in — and if that is the incumbent, it compares equal and
     * writes nothing at all.
     *
     * @param  Collection<int, Collection<int, EventMatch>>  $rounds
     * @param  array<int, int>  $onMats
     */
    private function grow(
        ClubEvent $event,
        EventCategory $category,
        ClubEventRegistration $entry,
        Collection $rounds,
        array $onMats,
        ?EventMatch $third
    ): array {
        $first = $rounds->first();

        // Who the newcomer plays in against: somebody whose own bout is still
        // to be fought, and whose road ahead is clear. Pairing him with an
        // athlete who has already fought would put a bout BEFORE one that has
        // been decided, which is the one thing this class exists not to do.
        $partner = null;

        foreach ($first as $i => $bout) {
            if ($this->spent($bout, $onMats) || ! $this->chainClean($rounds, 0, $i, $onMats, $third)) {
                continue;
            }

            foreach (['a', 'b'] as $side) {
                if ($bout->{$side.'_name'}) {
                    $partner = ['index' => $i, 'side' => $side, 'name' => $bout->{$side.'_name'}];
                    break 2;
                }
            }
        }

        if (! $partner) {
            return ['ok' => false, 'message' => __('events.late_entry_all_fought')];
        }

        $draw = app(DrawEngine::class);
        $n = $first->count();                 // bouts in the current earliest round
        $newCount = $n * 2;                   // bouts in the round we are adding
        $roundName = $draw->roundNames($newCount * 2)[0];
        $phase = $draw->phaseForRound($roundName);

        // The corner of the NEW bout the newcomer takes: the one his partner is
        // not in. Each new bout j carries the competitor from old bout ⌊j/2⌋,
        // side a when j is even — the same geometry Advancement walks.
        $playIn = $partner['index'] * 2 + ($partner['side'] === 'a' ? 0 : 1);

        DB::transaction(function () use ($category, $first, $entry, $newCount, $roundName, $phase, $playIn) {
            // Make room at the front. Shifting every slot by a constant leaves
            // the order — and therefore the bracket geometry — untouched.
            foreach ($category->matches()->orderByDesc('slot')->get() as $m) {
                $m->slot = $m->slot + $newCount;
                $m->save();
            }

            for ($j = 0; $j < $newCount; $j++) {
                $src = $first->get(intdiv($j, 2));
                $side = $j % 2 === 0 ? 'a' : 'b';

                $name = $src?->{$side.'_name'};

                $row = [
                    'event_id' => $category->event_id,
                    'round' => $roundName,
                    'phase' => $phase,
                    'slot' => $j,
                    'a_name' => $name,
                    'a_competitor_id' => $src?->{$side.'_competitor_id'},
                    'a_country' => $src?->{$side.'_country'},
                    'a_provisional' => (bool) $src?->{$side.'_provisional'},
                ];

                if ($j === $playIn) {
                    // The one real bout in this round.
                    $row += [
                        'b_name' => $this->nameOf($entry),
                        'b_competitor_id' => $entry->id,
                        'b_country' => $entry->countryCode(),
                        'b_provisional' => ! $entry->paid || $entry->weight === null,
                        'winner' => null,
                        'won_by_bye' => false,
                        'status' => 'upcoming',
                    ];
                } else {
                    // Everybody else walks through, exactly as a drawn bye does.
                    $row += [
                        'winner' => $name ? 'a' : null,
                        'won_by_bye' => (bool) $name,
                        'status' => $name ? 'done' : 'upcoming',
                    ];
                }

                $category->matches()->create($row);
            }

            $category->update(['draw_state' => 'manual']);
        });

        return [
            'ok' => true,
            'category' => $category->fresh(),
            'how' => 'grown',
            'opponent' => $partner['name'],
        ];
    }

    private function nameOf(ClubEventRegistration $entry): string
    {
        return $entry->user?->full_name ?: $entry->user?->name ?: __('events.athlete');
    }

    /**
     * Bouts grouped into rounds, earliest first, each round in slot order.
     *
     * The play-off for bronze is left out for the same reason `Advancement`
     * leaves it out: it sits last in slot order and would otherwise read as a
     * round after the final.
     *
     * @return Collection<int, Collection<int, EventMatch>>
     */
    private function rounds(EventCategory $category): Collection
    {
        return $category->matches()->orderBy('slot')->get()
            ->reject(fn (EventMatch $m) => $m->round === Advancement::ROUND_THIRD)
            ->groupBy('round')
            ->sortBy(fn (Collection $bouts) => $bouts->min('slot'))
            ->values()
            ->map(fn (Collection $bouts) => $bouts->sortBy('slot')->values());
    }
}
