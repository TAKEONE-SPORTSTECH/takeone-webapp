<?php

namespace App\Events\Support\Tournament;

use App\Events\Contracts\AwardsDivisions;
use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Events\Support\EventFee;
use App\Members\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Free edit — the organiser writes one bout exactly as it happened, whatever
 * state the event is in (owner, 2026-10-17: "I need the ability to change the
 * data in the brackets even if the competition is over. Please stop blocking
 * me" — a bout was played by hand because the bracket was missing a person).
 *
 * Every other door onto a draw protects results: Arrangement refuses once the
 * day starts, LateEntry refuses to spend a fought bout, ResultCorrection
 * refuses while a later bout stands on the change, OneDivisionRule refuses a
 * second bracket. Those stay exactly as they are. This is the one door that
 * does not refuse, because the person at the desk knows what happened on the
 * mat and the board does not.
 *
 * What makes it safe to have anyway:
 *  · it writes ONE bout — the corners, the winner, the scores the organiser
 *    set — and, when asked, the winner into the ONE corner of the next bout
 *    they feed. It never ripples, never voids a later result, never re-cuts,
 *    never deletes a row;
 *  · names come from REGISTRATIONS, never from the client;
 *  · the whole before/after goes to the activity log;
 *  · organiser only (EventAccess::canManage), behind EVENT_FREE_EDIT.
 */
final class FreeEdit
{
    public static function enabled(): bool
    {
        return (bool) config('events.free_edit', false);
    }

    /** The bout as the sheet shows it, plus everyone who could stand in it. */
    public function describe(ClubEvent $event, EventMatch $match): array
    {
        $people = ClubEventRegistration::where('event_id', $event->id)
            ->where('role', 'participant')
            ->with(['user:id,full_name,name', 'category:id,name'])
            ->get(['id', 'user_id', 'category_id', 'role'])
            ->map(fn (ClubEventRegistration $r) => [
                'id' => $r->id,
                'name' => $this->nameOf($r),
                'division' => $r->category?->name,
                'here' => (int) $r->category_id === (int) $match->category_id,
            ])
            ->sortBy(fn ($p) => [! $p['here'], mb_strtolower($p['name'])])
            ->values()->all();

        return [
            'match' => [
                'id' => $match->id,
                'no' => $match->match_no,
                'round' => $match->round,
                'a' => ['id' => $match->a_competitor_id, 'name' => $match->a_name, 'score' => $match->a_score],
                'b' => ['id' => $match->b_competitor_id, 'name' => $match->b_name, 'score' => $match->b_score],
                'winner' => $match->winner,
                'has_next' => $this->next($match) !== null,
            ],
            'people' => $people,
        ];
    }

    /**
     * Write the bout.
     *
     * @param  array{a: ?int, b: ?int, winner: ?string, a_score: ?string, b_score: ?string, advance: bool}  $data
     * @return array{ok: bool, message: string}
     */
    public function save(ClubEvent $event, EventMatch $match, array $data, ?User $by): array
    {
        $category = $match->category()->first();
        if (! $category || (int) $category->event_id !== (int) $event->id) {
            return ['ok' => false, 'message' => __('events::events.division_not_found')];
        }

        $entries = [];
        foreach (['a', 'b'] as $side) {
            $id = $data[$side] ?? null;
            if ($id === null) {
                $entries[$side] = null;

                continue;
            }
            $entry = ClubEventRegistration::where('event_id', $event->id)->where('id', (int) $id)
                ->where('role', 'participant')->with('user:id,full_name,name,nationality')->first();
            if (! $entry) {
                return ['ok' => false, 'message' => __('events::events.late_entry_not_an_entrant')];
            }
            $entries[$side] = $entry;
        }

        if ($entries['a'] && $entries['b'] && $entries['a']->id === $entries['b']->id) {
            return ['ok' => false, 'message' => __('events::events.free_edit_same_person')];
        }

        $winner = in_array($data['winner'] ?? null, ['a', 'b'], true) ? $data['winner'] : null;
        if ($winner && ! $entries[$winner]) {
            return ['ok' => false, 'message' => __('events::events.free_edit_winner_empty')];
        }

        $next = ! empty($data['advance']) && $winner ? $this->next($match) : null;
        $before = array_values(array_filter([$this->snapshot($match), $next ? $this->snapshot($next['bout']) : null]));

        $leftMat = null;
        DB::transaction(function () use ($event, $category, $match, $entries, $winner, $data, $next, $by, &$leftMat) {
            foreach (['a', 'b'] as $side) {
                $entry = $entries[$side] ? $this->inDivision($event, $entries[$side], $category, $by) : null;
                $this->corner($match, $side, $entry);
                $entries[$side] = $entry;
            }

            $both = $entries['a'] && $entries['b'];
            $match->a_score = $this->score($data['a_score'] ?? null);
            $match->b_score = $this->score($data['b_score'] ?? null);
            $match->winner = $winner;
            $match->won_by_bye = $winner !== null && ! $both;
            $match->status = $winner ? 'done' : 'upcoming';
            // A bye is not a bout: no number, as the draw writes one (LiveMove::decideBye).
            // Before this, a bye made here kept its number and the mat counted it.
            if ($match->won_by_bye && $match->match_no !== null) {
                $leftMat = [$match->court, $match->day];
                $match->match_no = null;
                $match->number_pinned = false;
            }
            if (! $winner) {
                $match->win_reason = null;
                $match->win_note = null;
            } elseif (! $match->win_reason) {
                $match->win_reason = 'other';
            }
            $match->save();

            // …and the mat it left closes the gap (MatNumbering, when switched on).
            if ($leftMat && $leftMat[0] && MatNumbering::enabled()) {
                app(MatNumbering::class)->close($event->id, $leftMat[0], $leftMat[1]);
            }

            if ($next) {
                $this->corner($next['bout'], $next['side'], $entries[$winner]);
                $next['bout']->save();
            }

            $category->update(['draw_state' => 'manual']);
        });

        $after = array_values(array_filter([$this->snapshot($match->fresh()), $next ? $this->snapshot($next['bout']->fresh()) : null]));

        rescue(fn () => activity()->causedBy($by)->withProperties([
            'event' => $event->uuid, 'division' => $category->id, 'before' => $before, 'after' => $after,
        ])->log('events.free_edit'), null, false);

        $this->settle($event, $category->fresh());
        // The mat's numbers moved: the scoring consoles re-read (as LiveMove does).
        if ($leftMat && MatNumbering::enabled()) {
            rescue(fn () => app(\App\Scoreboard\Fleet::class)->boardsChanged($event), null, false);
        }

        return ['ok' => true, 'message' => __('events::events.free_edit_saved')];
    }

    /**
     * The board's Move, once the event has started (owner, 2026-10-17: "when I
     * tap on the person with Move it must allow me to drag and drop the person
     * to the basket or even remove it from the brackets").
     *
     * Arrangement refuses after the start because it re-flows the whole first
     * round. This writes only the corners the drag touched — any round —
     * swapping a corner with a corner, filling one from the basket, or emptying
     * one into it. A bout whose winner was the person who left loses that
     * winner (and nothing downstream is touched).
     *
     * @param  array  $payload  {category_id, from:{type, match_id?, side?, competitor_id?}, to:{type, match_id?, side?}}
     * @return array{ok: bool, message: string, category?: EventCategory}
     */
    public function move(ClubEvent $event, array $payload, ?User $by): array
    {
        $category = $event->categories()->find($payload['category_id'] ?? null);
        if (! $category || $category->isHeading()) {
            return ['ok' => false, 'message' => __('events::events.division_not_found')];
        }

        $end = function (array $e) use ($category): ?array {
            if (($e['type'] ?? null) === 'bench') {
                return ['type' => 'bench'];
            }
            $bout = in_array($e['side'] ?? null, ['a', 'b'], true)
                ? EventMatch::where('category_id', $category->id)->find((int) ($e['match_id'] ?? 0)) : null;

            return $bout ? ['type' => 'slot', 'bout' => $bout, 'side' => $e['side']] : null;
        };

        $from = $end($payload['from'] ?? []);
        $to = $end($payload['to'] ?? []);
        if (! $from || ! $to || ($from['type'] === 'bench' && $to['type'] === 'bench')) {
            return ['ok' => false, 'message' => __('events::events.bracket_slot_invalid')];
        }

        if ($from['type'] === 'bench') {
            $entry = ClubEventRegistration::where('event_id', $event->id)->where('category_id', $category->id)
                ->where('role', 'participant')->where('id', (int) ($payload['from']['competitor_id'] ?? 0))
                ->with('user:id,full_name,name,nationality')->first();
            if (! $entry) {
                return ['ok' => false, 'message' => __('events::events.bracket_competitor_invalid')];
            }
            $moving = $this->cornerFrom($entry);
        } else {
            $moving = $this->cornerOf($from['bout'], $from['side']);
            if (! $moving['name'] && ! $moving['competitor_id']) {
                return ['ok' => false, 'message' => __('events::events.bracket_competitor_invalid')];
            }
        }

        $touched = collect([$from, $to])->where('type', 'slot')->pluck('bout')->unique('id');
        $before = $touched->map(fn (EventMatch $m) => $this->snapshot($m))->values()->all();

        DB::transaction(function () use ($from, $to, $moving, $category) {
            $displaced = $to['type'] === 'slot' ? $this->cornerOf($to['bout'], $to['side']) : null;

            // Same bout object when both ends are in one bout: write through one model.
            if ($to['type'] === 'slot' && $from['type'] === 'slot' && $from['bout']->id === $to['bout']->id) {
                $to['bout'] = $from['bout'];
            }
            if ($from['type'] === 'slot') {
                $this->writeCorner($from['bout'], $from['side'], $displaced);
            }
            if ($to['type'] === 'slot') {
                $this->writeCorner($to['bout'], $to['side'], $moving);
            }
            foreach ([$from, $to] as $e) {
                if ($e['type'] === 'slot') {
                    $e['bout']->save();
                }
            }

            $category->update(['draw_state' => 'manual']);
        });

        $after = $touched->map(fn (EventMatch $m) => $this->snapshot($m->fresh() ?? $m))->values()->all();

        rescue(fn () => activity()->causedBy($by)->withProperties([
            'event' => $event->uuid, 'division' => $category->id, 'before' => $before, 'after' => $after,
        ])->log('events.free_move'), null, false);

        $this->settle($event, $category->fresh());

        return ['ok' => true, 'message' => __('events::events.free_edit_saved'), 'category' => $category->fresh()];
    }

    /**
     * Put a person into another division while the event runs (owner,
     * 2026-10-17: "I removed the person from its own bracket and went to the
     * other bracket to add them, and it still says the person is in the old
     * bracket").
     *
     * MOVE: the person leaves their old division completely — every corner
     * they stand in there is emptied (a winner who leaves is no longer the
     * winner) — and their entry joins the target. COPY: a recorded free second
     * entry joins the target and the old bracket is left alone. Then, given a
     * corner, they stand in it (whoever was there goes to the basket);
     * otherwise they wait in the target's basket.
     *
     * @param  array{type: string, match_id?: ?int, side?: ?string}|null  $to
     * @return array{ok: bool, message: string, entry?: ClubEventRegistration, from?: ?EventCategory, to?: EventCategory}
     */
    public function place(ClubEvent $event, int $entryId, int $toCategoryId, ?array $to, bool $copy, ?User $by): array
    {
        $category = $event->categories()->find($toCategoryId);
        $entry = ClubEventRegistration::where('event_id', $event->id)->where('id', $entryId)
            ->where('role', 'participant')->with('user:id,full_name,name,nationality')->first();

        if (! $category || $category->isHeading() || ! $entry) {
            return ['ok' => false, 'message' => __('events::events.division_not_found')];
        }

        $slot = null;
        if (($to['type'] ?? null) === 'slot') {
            $bout = in_array($to['side'] ?? null, ['a', 'b'], true)
                ? EventMatch::where('category_id', $category->id)->find((int) ($to['match_id'] ?? 0)) : null;
            if (! $bout) {
                return ['ok' => false, 'message' => __('events::events.bracket_slot_invalid')];
            }
            $slot = ['bout' => $bout, 'side' => $to['side']];
        }

        $from = $entry->category_id ? $event->categories()->find($entry->category_id) : null;
        $touched = collect();

        $joined = DB::transaction(function () use ($event, $entry, $category, $slot, $copy, $by, $from, &$touched) {
            $mine = $entry->user_id && (int) $entry->category_id !== (int) $category->id
                ? ClubEventRegistration::where('event_id', $event->id)->where('role', 'participant')
                    ->where('user_id', $entry->user_id)->where('category_id', $category->id)->with('user')->first()
                : null;

            if ((int) $entry->category_id === (int) $category->id) {
                $joined = $entry;
            } elseif ($mine) {
                $joined = $mine;
            } elseif ($copy) {
                $joined = $this->freeCopy($event, $entry, $category, $by);
            } else {
                $joined = $entry;
            }

            // A move takes them out of the old bracket entirely.
            if (! $copy && $from && (int) $from->id !== (int) $category->id) {
                foreach (EventMatch::where('category_id', $from->id)
                    ->where(fn ($q) => $q->where('a_competitor_id', $entry->id)->orWhere('b_competitor_id', $entry->id))->get() as $bout) {
                    $touched->push($this->snapshot($bout));
                    foreach (['a', 'b'] as $side) {
                        if ((int) $bout->{$side.'_competitor_id'} === (int) $entry->id) {
                            $this->writeCorner($bout, $side, null);
                        }
                    }
                    $bout->save();
                }
                if ($joined->id === $entry->id) {
                    $entry->category_id = $category->id;
                    $entry->category_source = ClubEventRegistration::PLACED_MANUAL;
                    $entry->save();
                }
            }

            if ($slot) {
                $touched->push($this->snapshot($slot['bout']));
                $this->writeCorner($slot['bout'], $slot['side'], $this->cornerFrom($joined));
                $slot['bout']->save();
            }

            $category->update(['draw_state' => 'manual']);

            return $joined;
        });

        rescue(fn () => activity()->causedBy($by)->withProperties([
            'event' => $event->uuid, 'entry' => $entry->id, 'joined' => $joined->id,
            'from' => $from?->id, 'to' => $category->id, 'copy' => $copy, 'before' => $touched->all(),
        ])->log('events.free_place'), null, false);

        if ($from && $from->id !== $category->id) {
            $this->settle($event, $from->fresh());
        }
        $this->settle($event, $category->fresh());

        return ['ok' => true, 'message' => __('events::events.free_edit_placed', ['name' => $this->nameOf($joined), 'division' => $category->name]),
            'entry' => $joined->fresh(['user']), 'from' => $from?->fresh(), 'to' => $category->fresh()];
    }

    /* ---------------- Helpers ---------------- */

    /** A recorded FREE second entry in $category (the way Transfer copies). */
    private function freeCopy(ClubEvent $event, ClubEventRegistration $entry, EventCategory $category, ?User $by): ClubEventRegistration
    {
        $copy = $entry->replicate();
        $copy->category_id = $category->id;
        $copy->category_source = ClubEventRegistration::PLACED_MANUAL;
        $copy->payment_proof = null;
        $copy->paid = true;
        $copy->paid_at = now();
        $copy->paid_by = $by?->id;
        $copy->save();
        EventFee::commit($copy, ['total' => 0.0, 'currency' => EventFee::currency($event), 'lines' => []]);

        return $copy->fresh(['user']);
    }

    /** Every column a corner carries, as LiveMove moves them. */
    private const CORNER = ['competitor_id', 'name', 'country', 'seed', 'provisional', 'corner', 'side_id'];

    private function cornerOf(EventMatch $bout, string $side): array
    {
        $out = [];
        foreach (self::CORNER as $c) {
            $out[$c] = $bout->{$side.'_'.$c};
        }

        return $out;
    }

    private function cornerFrom(ClubEventRegistration $entry): array
    {
        return ['competitor_id' => $entry->id, 'name' => $this->nameOf($entry), 'country' => $entry->countryCode(),
            'seed' => null, 'provisional' => ! $entry->paid || $entry->weight === null, 'corner' => null, 'side_id' => null];
    }

    /**
     * Put a corner (or nobody) in place. The corner's score goes with the
     * person who left it, and a winner who is no longer standing there is no
     * longer the winner. Does not save.
     */
    private function writeCorner(EventMatch $bout, string $side, ?array $who): void
    {
        $changed = (int) $bout->{$side.'_competitor_id'} !== (int) ($who['competitor_id'] ?? 0)
            || (string) $bout->{$side.'_name'} !== (string) ($who['name'] ?? '');

        foreach (self::CORNER as $c) {
            $bout->{$side.'_'.$c} = $c === 'provisional' ? (bool) ($who[$c] ?? false) : ($who[$c] ?? null);
        }

        if ($changed) {
            $bout->{$side.'_score'} = null;
            if ($bout->winner === $side) {
                $bout->winner = null;
                $bout->won_by_bye = false;
                $bout->status = 'upcoming';
                $bout->win_reason = null;
                $bout->win_note = null;
            }
        }
    }

    /**
     * The entry to stand in this division: the person's own entry here if they
     * hold one, else the picked entry moved here when it stands in no other
     * bracket, else a recorded FREE copy (the way Transfer copies) so the
     * bracket they already stand in is left alone.
     */
    private function inDivision(ClubEvent $event, ClubEventRegistration $entry, EventCategory $category, ?User $by): ClubEventRegistration
    {
        if ((int) $entry->category_id === (int) $category->id) {
            return $entry;
        }

        if ($entry->user_id && ($mine = ClubEventRegistration::where('event_id', $event->id)->where('role', 'participant')
            ->where('user_id', $entry->user_id)->where('category_id', $category->id)->with('user')->first())) {
            return $mine;
        }

        $standing = $entry->category_id && EventMatch::where('category_id', $entry->category_id)
            ->where(fn ($q) => $q->where('a_competitor_id', $entry->id)->orWhere('b_competitor_id', $entry->id))->exists();

        if (! $standing) {
            $entry->category_id = $category->id;
            $entry->category_source = ClubEventRegistration::PLACED_MANUAL;
            $entry->save();

            return $entry;
        }

        $copy = $entry->replicate();
        $copy->category_id = $category->id;
        $copy->category_source = ClubEventRegistration::PLACED_MANUAL;
        $copy->payment_proof = null;
        $copy->paid = true;
        $copy->paid_at = now();
        $copy->paid_by = $by?->id;
        $copy->save();
        EventFee::commit($copy, ['total' => 0.0, 'currency' => EventFee::currency($event), 'lines' => []]);

        return $copy->fresh(['user']);
    }

    /** Stand an entry (or nobody) in a corner. Does not save. */
    private function corner(EventMatch $bout, string $side, ?ClubEventRegistration $entry): void
    {
        $bout->{$side.'_competitor_id'} = $entry?->id;
        $bout->{$side.'_name'} = $entry ? $this->nameOf($entry) : null;
        $bout->{$side.'_country'} = $entry?->countryCode();
        $bout->{$side.'_seed'} = null;
        $bout->{$side.'_provisional'} = $entry ? (! $entry->paid || $entry->weight === null) : false;
    }

    /**
     * The corner this bout's winner walks into: bout i of a round feeds bout
     * ⌊i/2⌋ of the next, corner a when i is even — the rule every Advancement
     * walks. Null for a final, a bronze bout, or a pool.
     *
     * @return array{bout: EventMatch, side: string}|null
     */
    private function next(EventMatch $match): ?array
    {
        $rounds = EventMatch::where('category_id', $match->category_id)->orderBy('slot')->get()
            ->reject(fn (EventMatch $m) => $m->round === Advancement::ROUND_THIRD)
            ->groupBy('round')
            ->sortBy(fn (Collection $bouts) => $bouts->min('slot'))
            ->map(fn (Collection $bouts) => $bouts->sortBy('slot')->values())
            ->values();

        foreach ($rounds as $r => $bouts) {
            $i = $bouts->search(fn (EventMatch $m) => $m->id === $match->id);
            if ($i === false) {
                continue;
            }
            $bout = $rounds->get($r + 1)?->get(intdiv($i, 2));

            return $bout ? ['bout' => $bout, 'side' => $i % 2 === 0 ? 'a' : 'b'] : null;
        }

        return null;
    }

    /** Re-derive the podium (as ResultCorrection does) and tell every board. */
    private function settle(ClubEvent $event, EventCategory $category): void
    {
        $type = app(\App\Events\EventTypeRegistry::class)->for($event);

        if ($type instanceof AwardsDivisions) {
            rescue(function () use ($type, $category) {
                $category->update(['podium' => []]);
                $podium = $type->awardDivision($category->fresh());
                if ($podium === [] && $category->fresh()->status === 'completed') {
                    $category->update(['status' => 'live']);
                }
            }, null, true);
        }

        if (method_exists($type, 'announceDrawChanged')) {
            rescue(fn () => $type->announceDrawChanged($event, $category->id), null, false);
        }
        rescue(fn () => app(\App\Scoreboard\Fleet::class)->boardsChanged($event), null, false);
    }

    private function score(mixed $v): ?string
    {
        $v = is_scalar($v) ? trim((string) $v) : '';

        return $v === '' ? null : mb_substr($v, 0, 10);
    }

    private function snapshot(EventMatch $m): array
    {
        return $m->only(['id', 'match_no', 'round', 'a_competitor_id', 'a_name', 'b_competitor_id', 'b_name', 'a_score', 'b_score', 'winner', 'won_by_bye', 'status']);
    }

    private function nameOf(ClubEventRegistration $entry): string
    {
        return $entry->user?->full_name ?: $entry->user?->name ?: __('events::events.athlete');
    }
}
