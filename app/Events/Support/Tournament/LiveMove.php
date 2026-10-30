<?php

namespace App\Events\Support\Tournament;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Members\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Moving an athlete into the division they belong in WHILE THE EVENT RUNS
 * (owner, 2026-10-17: "players in the wrong brackets … we discover the mistake
 * too late, on the day of the competition").
 *
 * Transfer cannot do this: it goes through the package's Arrangement, which
 * refuses once the day has started and, before then, re-flows the WHOLE first
 * round — fine with nothing fought, wrong mid-event. This class edits single
 * corners instead, by the same bracket rule every Advancement uses (bout i of
 * a round feeds bout ⌊i/2⌋ of the next, corner a when i is even).
 *
 * PLAN FIRST, WRITE ONCE. Both halves are worked out before anything is
 * written; if either cannot be done safely, nothing changes and the organiser
 * is told why. Then one transaction.
 *
 * What it will never do: touch a bout that has been fought or started (a
 * winner that is not a bye, a score, anything in its officiating log), touch a
 * bout loaded on a mat, renumber a mat, re-cut a draw, or delete a row. The
 * whole before/after goes to the activity log.
 *
 * Leaving (the division they are in):
 *   · not in its bracket          → they simply leave;
 *   · in an unfought first-round bout with an opponent
 *                                  → the opponent advances on a bye;
 *   · holding a bye, with somebody already waiting in the next bout
 *                                  → that somebody advances on a bye;
 *   · fought                       → refused: remove those results first
 *                                    (ResultCorrection), then move them.
 * Joining (the division they belong in):
 *   · no bracket yet               → they go on its list;
 *   · a bye in the first round     → they take the empty corner: the bye
 *                                    becomes a real bout, numbered at the end
 *                                    of that mat, and the bye-holder comes back
 *                                    to fight it;
 *   · an empty first-round bout    → they take it as a bye;
 *   · no free place                → refused (a play-in is not built yet).
 */
final class LiveMove
{
    /** Every column a corner carries — moved or emptied together. */
    private const CORNER = ['name', 'country', 'seed', 'score', 'provisional', 'competitor_id', 'corner', 'side_id'];

    public static function enabled(): bool
    {
        return (bool) config('events.live_move', false);
    }

    /**
     * @return array{ok: bool, message: string, from?: int, to?: int}
     */
    public function run(ClubEvent $event, int $entryId, int $toCategoryId, ?User $by): array
    {
        $entry = ClubEventRegistration::where('event_id', $event->id)->where('id', $entryId)
            ->where('role', 'participant')->with('user:id,full_name,name,nationality')->first();
        $to = $event->categories()->find($toCategoryId);

        if (! $entry || ! $entry->category_id || ! $to || $to->isHeading()) {
            return $this->no(__('events::events.division_not_found'));
        }
        $from = $event->categories()->find($entry->category_id);
        if (! $from) {
            return $this->no(__('events::events.division_not_found'));
        }
        if ($from->id === $to->id) {
            return $this->no(__('events::events.transfer_same_division'));
        }
        if ($entry->user_id && ClubEventRegistration::where('event_id', $event->id)
            ->where('user_id', $entry->user_id)->where('category_id', $to->id)->exists()) {
            return $this->no(__('events::events.transfer_already_there'));
        }

        $name = (string) ($entry->user?->full_name ?: $entry->user?->name ?: __('events::events.athlete'));
        $onMats = $this->onMats($event);

        // ── Plan both halves before writing either ───────────────────────────
        $leave = $this->planLeave($from, $entry, $onMats, $name);
        if (isset($leave['refuse'])) {
            return $this->no($leave['refuse']);
        }
        $join = $this->planJoin($event, $to, $onMats);
        if (isset($join['refuse'])) {
            return $this->no($join['refuse']);
        }

        $touched = collect([...$leave['bouts'], ...$join['bouts']])->unique('id');
        $before = $touched->map(fn (EventMatch $m) => $this->snapshot($m))->values()->all();

        try {
            $summary = DB::transaction(function () use ($event, $entry, $from, $to, $leave, $join, $name) {
                // Re-read inside the transaction: if anything moved under us
                // since planning, refuse rather than write on a stale picture.
                foreach ([...$leave['bouts'], ...$join['bouts']] as $m) {
                    $now = $m->fresh();
                    if (! $now || (string) $now->updated_at !== (string) $m->updated_at) {
                        throw new TransferRefused(__('events::events.live_move_changed'));
                    }
                }

                $lines = [];
                $lines[] = $this->applyLeave($leave, $from);

                $entry->update(['category_id' => $to->id, 'category_source' => ClubEventRegistration::PLACED_MANUAL]);

                $lines[] = $this->applyJoin($event, $join, $entry->fresh(['user']), $to, $name);

                // A bout that became a bye left its number behind: no gaps.
                if (MatNumbering::enabled()) {
                    collect([...$leave['bouts'], ...$join['bouts']])
                        ->map(fn (EventMatch $m) => [$m->court, $m->day])->unique()
                        ->each(fn ($mat) => app(MatNumbering::class)->close($event->id, $mat[0], $mat[1]));
                }

                return array_values(array_filter($lines));
            });
        } catch (TransferRefused $e) {
            return $this->no($e->getMessage());
        }

        $after = $touched->map(fn (EventMatch $m) => $this->snapshot($m->fresh() ?? $m))->values()->all();

        rescue(fn () => activity()->causedBy($by)->withProperties([
            'event' => $event->uuid, 'entry' => $entry->id, 'from' => $from->id, 'to' => $to->id,
            'before' => $before, 'after' => $after,
        ])->log('events.live_move'), null, false);

        $this->announce($event, [$from->id, $to->id]);

        return [
            'ok' => true,
            'message' => trim(__('events::events.live_move_done', ['name' => $name, 'division' => $to->name]).' '.implode(' ', $summary)),
            'from' => $from->id,
            'to' => $to->id,
        ];
    }

    /* ── Swapping two people's places (owner, 2026-10-17: "he was set in
          another person's place") ─────────────────────────────────────────
       Who stands in the corners changes; the bracket does not. Every corner
       holding X takes Y and every corner holding Y takes X — byes, numbers,
       mats and the shape of the draw stay exactly as they were — and the two
       entries trade divisions when they were in different ones. Refused when
       either person has fought or started a bout, or stands in a bout loaded
       on a mat. */

    /**
     * @return array{ok: bool, message: string}
     */
    public function swap(ClubEvent $event, int $xId, int $yId, ?User $by): array
    {
        $load = fn (int $id) => ClubEventRegistration::where('event_id', $event->id)->where('id', $id)
            ->where('role', 'participant')->with('user:id,full_name,name,nationality')->first();
        $x = $load($xId);
        $y = $load($yId);

        if (! $x || ! $y || $x->id === $y->id || ($x->user_id && $x->user_id === $y->user_id)) {
            return $this->no(__('events::events.bracket_competitor_invalid'));
        }

        $nameOf = fn (ClubEventRegistration $e) => (string) ($e->user?->full_name ?: $e->user?->name ?: __('events::events.athlete'));

        // Each takes the other's division: nobody may end up twice in one, and
        // "one division per person" still holds.
        foreach ([[$x, $y->category_id], [$y, $x->category_id]] as [$who, $cat]) {
            if (! $cat || (int) $cat === (int) $who->category_id) {
                continue;
            }
            if ($who->user_id && ClubEventRegistration::where('event_id', $event->id)->where('user_id', $who->user_id)
                ->where('category_id', $cat)->where('id', '!=', $who->id)->exists()) {
                return $this->no(__('events::events.transfer_already_there'));
            }
            if ($refused = \App\Events\Support\OneDivisionRule::refusal($event, $who, (int) $cat, moving: true)) {
                return $this->no($refused);
            }
        }

        $onMats = $this->onMats($event);
        $ids = [$x->id, $y->id];
        $bouts = EventMatch::where('event_id', $event->id)
            ->where(fn ($q) => $q->whereIn('a_competitor_id', $ids)->orWhereIn('b_competitor_id', $ids))->get();

        foreach ($bouts as $m) {
            if (in_array($m->id, $onMats, true)) {
                return $this->no(__('events::events.live_move_on_mat'));
            }
            if (($m->winner && ! $m->won_by_bye) || $this->started($m)) {
                $who = in_array((int) $m->a_competitor_id, [$x->id], true) || in_array((int) $m->b_competitor_id, [$x->id], true) ? $x : $y;

                return $this->no(__('events::events.live_move_fought', [
                    'name' => $nameOf($who), 'division' => (string) $m->category?->name,
                ]));
            }
        }

        $before = $bouts->map(fn (EventMatch $m) => $this->snapshot($m))->values()->all();

        try {
            DB::transaction(function () use ($bouts, &$x, &$y) {
                foreach ($bouts as $m) {
                    $now = $m->fresh();
                    if (! $now || (string) $now->updated_at !== (string) $m->updated_at) {
                        throw new TransferRefused(__('events::events.live_move_changed'));
                    }
                }

                // Trade divisions first, so each corner's flag is read for the
                // division the person now stands in.
                if ((int) $x->category_id !== (int) $y->category_id) {
                    [$cx, $cy] = [$x->category_id, $y->category_id];
                    $x->update(['category_id' => $cy, 'category_source' => ClubEventRegistration::PLACED_MANUAL]);
                    $y->update(['category_id' => $cx, 'category_source' => ClubEventRegistration::PLACED_MANUAL]);
                }
                $x = $x->fresh(['user']);
                $y = $y->fresh(['user']);

                foreach ($bouts as $m) {
                    foreach (['a', 'b'] as $s) {
                        $id = (int) $m->{$s.'_competitor_id'};
                        if ($id === $x->id) {
                            $this->standIn($m, $s, $y);
                        } elseif ($id === $y->id) {
                            $this->standIn($m, $s, $x);
                        }
                    }
                    $m->save();
                }
            });
        } catch (TransferRefused $e) {
            return $this->no($e->getMessage());
        }

        $after = $bouts->map(fn (EventMatch $m) => $this->snapshot($m->fresh() ?? $m))->values()->all();

        rescue(fn () => activity()->causedBy($by)->withProperties([
            'event' => $event->uuid, 'x' => $x->id, 'y' => $y->id,
            'x_category' => $x->category_id, 'y_category' => $y->category_id,
            'before' => $before, 'after' => $after,
        ])->log('events.live_swap'), null, false);

        $this->announce($event, array_values(array_filter(array_unique([(int) $x->category_id, (int) $y->category_id]))));

        return ['ok' => true, 'message' => __('events::events.live_swap_done', ['x' => $nameOf($x), 'y' => $nameOf($y)])];
    }

    /**
     * Who this entry could swap with: every other participant of the event,
     * their own division first, each marked drawn / not drawn / fought.
     *
     * @return list<array{entry:int, name:string, division:?string, same:bool, drawn:bool, fought:bool}>
     */
    public function candidates(ClubEvent $event, int $entryId): array
    {
        $entry = ClubEventRegistration::where('event_id', $event->id)->where('id', $entryId)->first();
        if (! $entry) {
            return [];
        }

        $bouts = EventMatch::where('event_id', $event->id)->get(['a_competitor_id', 'b_competitor_id', 'winner', 'won_by_bye', 'a_score', 'b_score']);
        $drawn = [];
        $fought = [];
        foreach ($bouts as $m) {
            $real = ($m->winner && ! $m->won_by_bye) || (float) $m->a_score > 0 || (float) $m->b_score > 0;
            foreach ([$m->a_competitor_id, $m->b_competitor_id] as $id) {
                if ($id) {
                    $drawn[$id] = true;
                    if ($real) {
                        $fought[$id] = true;
                    }
                }
            }
        }

        $names = $event->categories()->pluck('name', 'id');

        return ClubEventRegistration::where('event_id', $event->id)->where('role', 'participant')
            ->where('id', '!=', $entry->id)
            ->when($entry->user_id, fn ($q) => $q->where(fn ($w) => $w->whereNull('user_id')->orWhere('user_id', '!=', $entry->user_id)))
            ->with('user:id,full_name,name')->get()
            ->map(fn (ClubEventRegistration $r) => [
                'entry' => $r->id,
                'name' => (string) ($r->user?->full_name ?: $r->user?->name ?: ''),
                'division' => $r->category_id ? ($names[$r->category_id] ?? null) : null,
                'same' => (int) $r->category_id === (int) $entry->category_id && $entry->category_id !== null,
                'drawn' => isset($drawn[$r->id]),
                'fought' => isset($fought[$r->id]),
            ])
            ->sortBy(fn ($c) => [$c['same'] ? 0 : 1, $c['fought'] ? 1 : 0, $c['name']])
            ->values()->all();
    }

    /** Put $entry into this corner as who stands there — the slot's seed, side and score stay the slot's. */
    private function standIn(EventMatch $m, string $side, ClubEventRegistration $entry): void
    {
        $m->{$side.'_competitor_id'} = $entry->id;
        $m->{$side.'_name'} = (string) ($entry->user?->full_name ?: $entry->user?->name ?: '');
        $m->{$side.'_country'} = $entry->countryCode();
    }

    /* ── Leaving ─────────────────────────────────────────────────────────── */

    private function planLeave(EventCategory $from, ClubEventRegistration $entry, array $onMats, string $name): array
    {
        $rounds = $this->rounds($from);
        $mine = $this->boutsOf($rounds, $entry->id);

        if ($mine->isEmpty()) {
            return ['kind' => 'bench', 'bouts' => []];
        }

        foreach ($mine as $m) {
            if (in_array($m->id, $onMats, true)) {
                return ['refuse' => __('events::events.live_move_on_mat')];
            }
        }

        // Fought: any real result, any score, anything in the officiating log —
        // in ANY of their bouts.
        foreach ($mine as $m) {
            if (($m->winner && ! $m->won_by_bye) || $this->started($m)) {
                return ['refuse' => __('events::events.live_move_fought', ['name' => $name, 'division' => $from->name])];
            }
        }

        $first = $rounds->first();
        $b = $mine->first();
        $side = (int) $b->a_competitor_id === (int) $entry->id ? 'a' : 'b';
        $other = $side === 'a' ? 'b' : 'a';

        // An unfought first-round bout with an opponent: the opponent takes it as a bye.
        if ($mine->count() === 1 && $first->contains('id', $b->id) && ! $b->winner && $this->filled($b, $other)) {
            $next = $this->next($rounds, $b);
            if ($next) {
                [$n, $nSide] = $next;
                if (in_array($n->id, $onMats, true) || $n->winner || $this->started($n) || $this->filled($n, $nSide)) {
                    return ['refuse' => __('events::events.live_move_unsafe')];
                }

                return ['kind' => 'bye_opponent', 'bout' => $b, 'side' => $side, 'next' => $n, 'next_side' => $nSide, 'bouts' => [$b, $n]];
            }

            // A final of two: the corner simply empties — never an automatic gold.
            return ['kind' => 'empty_corner', 'bout' => $b, 'side' => $side, 'bouts' => [$b]];
        }

        // Holding a bye: they won bout B by bye and stand in the next bout N.
        if ($mine->count() === 2 && $b->won_by_bye && $b->winner === $side && $first->contains('id', $b->id)) {
            $n = $mine->last();
            $nSide = (int) $n->a_competitor_id === (int) $entry->id ? 'a' : 'b';
            $nOther = $nSide === 'a' ? 'b' : 'a';
            $next = $this->next($rounds, $b);

            if (! $next || $next[0]->id !== $n->id || $n->winner || $this->started($n) || in_array($n->id, $onMats, true)) {
                return ['refuse' => __('events::events.live_move_unsafe')];
            }

            // Somebody already waiting in N advances on a bye in their turn.
            if ($this->filled($n, $nOther)) {
                $after = $this->next($rounds, $n);
                if ($after) {
                    [$m2, $m2Side] = $after;
                    if (in_array($m2->id, $onMats, true) || $m2->winner || $this->started($m2) || $this->filled($m2, $m2Side)) {
                        return ['refuse' => __('events::events.live_move_unsafe')];
                    }

                    return ['kind' => 'bye_holder_advance', 'bout' => $b, 'side' => $side, 'next' => $n, 'next_side' => $nSide,
                        'after' => $m2, 'after_side' => $m2Side, 'bouts' => [$b, $n, $m2]];
                }

                // N is the final: its other finalist stands alone, undecided.
                return ['kind' => 'bye_holder_final', 'bout' => $b, 'side' => $side, 'next' => $n, 'next_side' => $nSide, 'bouts' => [$b, $n]];
            }

            // Nobody waiting yet: that would leave a bout no one can finish.
            return ['refuse' => __('events::events.live_move_unsafe')];
        }

        return ['refuse' => __('events::events.live_move_unsafe')];
    }

    private function applyLeave(array $plan, EventCategory $from): ?string
    {
        switch ($plan['kind']) {
            case 'bench':
                return null;

            case 'empty_corner':
                $b = $plan['bout'];
                $this->clearCorner($b, $plan['side']);
                $b->save();

                return null;

            case 'bye_opponent':
                $b = $plan['bout'];
                $other = $plan['side'] === 'a' ? 'b' : 'a';
                $this->clearCorner($b, $plan['side']);
                $this->decideBye($b, $other);
                $b->save();
                $this->copyCorner($b, $other, $plan['next'], $plan['next_side']);
                $plan['next']->save();

                return __('events::events.live_move_bye', ['name' => (string) $b->{$other.'_name'}, 'division' => $from->name]);

            case 'bye_holder_advance':
            case 'bye_holder_final':
                $b = $plan['bout'];
                $this->clearCorner($b, $plan['side']);
                $b->winner = null;
                $b->won_by_bye = false;
                $b->save();

                $n = $plan['next'];
                $nOther = $plan['next_side'] === 'a' ? 'b' : 'a';
                $this->clearCorner($n, $plan['next_side']);
                if ($plan['kind'] === 'bye_holder_advance') {
                    $this->decideBye($n, $nOther);
                    $n->save();
                    $this->copyCorner($n, $nOther, $plan['after'], $plan['after_side']);
                    $plan['after']->save();

                    return __('events::events.live_move_bye', ['name' => (string) $n->{$nOther.'_name'}, 'division' => $from->name]);
                }
                $n->save();

                return null;
        }

        return null;
    }

    /* ── Joining ─────────────────────────────────────────────────────────── */

    private function planJoin(ClubEvent $event, EventCategory $to, array $onMats): array
    {
        $rounds = $this->rounds($to);
        if ($rounds->isEmpty()) {
            return ['kind' => 'bench', 'bouts' => []];
        }

        $first = $rounds->first();

        // A bye: one corner filled, decided as a bye, its holder not yet fought onward.
        foreach ($first as $b) {
            if (! $b->won_by_bye || ! $b->winner || in_array($b->id, $onMats, true)) {
                continue;
            }
            $hold = $b->winner;
            $empty = $hold === 'a' ? 'b' : 'a';
            if (! $this->filled($b, $hold) || $this->filled($b, $empty)) {
                continue;
            }
            $next = $this->next($rounds, $b);
            if ($next) {
                [$n, $nSide] = $next;
                $holder = $b->{$hold.'_competitor_id'};
                if (in_array($n->id, $onMats, true) || $n->winner || $this->started($n)
                    || ($holder && $n->{$nSide.'_competitor_id'} !== $holder)) {
                    continue;
                }

                return ['kind' => 'bye_slot', 'bout' => $b, 'side' => $empty, 'next' => $n, 'next_side' => $nSide, 'bouts' => [$b, $n]];
            }
        }

        // An empty first-round bout: they take it as a bye, into an empty corner
        // of a next bout where somebody is, or will be, waiting.
        foreach ($first as $b) {
            if ($b->winner || $this->filled($b, 'a') || $this->filled($b, 'b') || in_array($b->id, $onMats, true)) {
                continue;
            }
            $next = $this->next($rounds, $b);
            if (! $next) {
                continue;
            }
            [$n, $nSide] = $next;
            $nOther = $nSide === 'a' ? 'b' : 'a';
            if (in_array($n->id, $onMats, true) || $n->winner || $this->started($n) || $this->filled($n, $nSide)) {
                continue;
            }
            $sibling = $this->feeder($rounds, $n, $nOther);
            $someoneComing = $this->filled($n, $nOther) || ($sibling && ($this->filled($sibling, 'a') || $this->filled($sibling, 'b')));
            if (! $someoneComing) {
                continue;
            }

            return ['kind' => 'empty_slot', 'bout' => $b, 'next' => $n, 'next_side' => $nSide, 'bouts' => [$b, $n]];
        }

        return ['refuse' => __('events::events.live_move_no_place', ['division' => $to->name])];
    }

    private function applyJoin(ClubEvent $event, array $plan, ClubEventRegistration $entry, EventCategory $to, string $name): ?string
    {
        if ($plan['kind'] === 'bench') {
            return __('events::events.live_move_bench', ['division' => $to->name, 'name' => $name]);
        }

        $b = $plan['bout'];
        $n = $plan['next'];

        if ($plan['kind'] === 'bye_slot') {
            $this->fillCorner($b, $plan['side'], $entry);

            // The bye becomes a bout: undecided, fresh, numbered at the end of
            // the mat its division runs on. Nothing else on that mat moves.
            $b->winner = null;
            $b->won_by_bye = false;
            $b->status = 'upcoming';
            $b->a_score = $b->b_score = null;
            $b->win_reason = $b->win_note = null;
            [$court, $day] = $this->placeOf($event, $to, $n);
            $b->court = $court;
            $b->day = $day;
            $b->match_no = $court ? $this->nextNumber($event, $court, $day) : null;
            $b->number_pinned = false;
            $b->save();

            // Not at the end of the mat: where it belongs, the mat renumbered.
            if ($court && MatNumbering::enabled()) {
                app(MatNumbering::class)->place($b);
            }

            // The bye-holder comes back to fight it: their place in the next bout empties.
            $this->clearCorner($n, $plan['next_side']);
            $n->save();

            return __('events::events.live_move_new_bout', [
                'no' => $b->match_no ? '#'.$b->match_no : '', 'mat' => (string) $court,
                'a' => (string) $b->a_name, 'b' => (string) $b->b_name,
            ]);
        }

        // empty_slot: a bye of their own, carried into the next bout.
        $this->fillCorner($b, 'a', $entry);
        $this->decideBye($b, 'a');
        $b->save();
        $this->copyCorner($b, 'a', $n, $plan['next_side']);
        $n->save();

        return __('events::events.live_move_bye', ['name' => $name, 'division' => $to->name]);
    }

    /* ── Bracket reading, the way every Advancement reads it ─────────────── */

    /** @return Collection<int, Collection<int, EventMatch>> rounds in bracket order */
    private function rounds(EventCategory $category): Collection
    {
        return EventMatch::where('category_id', $category->id)
            ->where('round', '!=', 'Third place')
            ->orderBy('slot')->get()
            ->groupBy('round')
            ->sortBy(fn (Collection $bouts) => $bouts->min('slot'))
            ->map(fn (Collection $bouts) => $bouts->values())
            ->values();
    }

    /** @return array{0: EventMatch, 1: string}|null */
    private function next(Collection $rounds, EventMatch $from): ?array
    {
        foreach ($rounds as $r => $bouts) {
            $index = $bouts->search(fn (EventMatch $m) => $m->id === $from->id);
            if ($index === false) {
                continue;
            }
            $bout = $rounds->get($r + 1)?->get(intdiv($index, 2));

            return $bout ? [$bout, $index % 2 === 0 ? 'a' : 'b'] : null;
        }

        return null;
    }

    /** The bout that feeds $side of $bout, if any. */
    private function feeder(Collection $rounds, EventMatch $bout, string $side): ?EventMatch
    {
        foreach ($rounds as $r => $bouts) {
            $index = $bouts->search(fn (EventMatch $m) => $m->id === $bout->id);
            if ($index === false || $r === 0) {
                continue;
            }

            return $rounds->get($r - 1)?->get($index * 2 + ($side === 'a' ? 0 : 1));
        }

        return null;
    }

    /** @return Collection<int, EventMatch> this entry's bouts, earliest round first */
    private function boutsOf(Collection $rounds, int $entryId): Collection
    {
        return $rounds->flatten(1)
            ->filter(fn (EventMatch $m) => (int) $m->a_competitor_id === $entryId || (int) $m->b_competitor_id === $entryId)
            ->values();
    }

    private function filled(EventMatch $m, string $side): bool
    {
        return (bool) ($m->{$side.'_competitor_id'} || ($m->{$side.'_name'} !== null && $m->{$side.'_name'} !== ''));
    }

    /**
     * A score, or the mat has FOUGHT it — not merely put it up: `load` (and
     * the screen commands) only show a bout, and a bout loaded then put away
     * has not started.
     */
    private function started(EventMatch $m): bool
    {
        return (float) $m->a_score > 0 || (float) $m->b_score > 0
            || DB::table('event_match_events')->where('match_id', $m->id)
                ->whereIn('command', self::FIGHTING)->exists();
    }

    /** Officiating-log commands that mean the bout was actually under way. */
    private const FIGHTING = ['start', 'pause', 'point', 'undo_point', 'penalty', 'senshu', 'finish', 'commit', 'time', 'reset'];

    /* ── Writing corners ─────────────────────────────────────────────────── */

    private function clearCorner(EventMatch $m, string $side): void
    {
        foreach (self::CORNER as $f) {
            $m->{$side.'_'.$f} = $f === 'provisional' ? false : null;
        }
    }

    private function copyCorner(EventMatch $from, string $fromSide, EventMatch $to, string $toSide): void
    {
        foreach (self::CORNER as $f) {
            $to->{$toSide.'_'.$f} = $f === 'score' ? null : $from->{$fromSide.'_'.$f};
        }
    }

    private function fillCorner(EventMatch $m, string $side, ClubEventRegistration $entry): void
    {
        $this->clearCorner($m, $side);
        $m->{$side.'_competitor_id'} = $entry->id;
        $m->{$side.'_name'} = (string) ($entry->user?->full_name ?: $entry->user?->name ?: '');
        $m->{$side.'_country'} = $entry->countryCode();
    }

    /** Decided as a bye for $side: no number, off the mat queue — as the draw writes one. */
    private function decideBye(EventMatch $m, string $side): void
    {
        $m->winner = $side;
        $m->won_by_bye = true;
        $m->status = 'done';
        $m->a_score = $m->b_score = null;
        $m->match_no = null;
        $m->number_pinned = false;
    }

    /** The mat and day this division runs on. @return array{0: ?string, 1: ?int} */
    private function placeOf(ClubEvent $event, EventCategory $to, EventMatch $next): array
    {
        if ($next->court) {
            return [$next->court, $next->day];
        }
        $any = EventMatch::where('category_id', $to->id)->whereNotNull('court')->orderBy('slot')->first(['court', 'day']);
        if ($any) {
            return [$any->court, $any->day];
        }
        $busiest = EventMatch::where('event_id', $event->id)->whereNotNull('court')
            ->select('court', 'day', DB::raw('count(*) as n'))->groupBy('court', 'day')->orderByDesc('n')->first();

        return [$busiest?->court, $busiest?->day];
    }

    private function nextNumber(ClubEvent $event, string $court, ?int $day): int
    {
        return (int) EventMatch::where('event_id', $event->id)->where('court', $court)
            ->when($day !== null, fn ($q) => $q->where('day', $day))
            ->max(DB::raw('CAST(match_no AS INTEGER)')) + 1;
    }

    /* ── Mats, logging, telling the screens ──────────────────────────────── */

    /** Bouts loaded on a mat right now, for the sports that keep a mat. @return list<int> */
    private function onMats(ClubEvent $event): array
    {
        $courts = EventMatch::where('event_id', $event->id)->whereNotNull('court')->distinct()->pluck('court')->all();
        $state = match ((string) $event->sport) {
            'karate' => \App\Scoreboard\Sports\Karate\Mat\MatState::class,
            'taekwondo' => \App\Scoreboard\Sports\Taekwondo\Mat\MatState::class,
            default => null,
        };

        $ids = [];
        if ($state) {
            foreach ($courts as $court) {
                $id = rescue(fn () => $state::load($event, (string) $court)->matchId, null, false);
                if (is_numeric($id)) {
                    $ids[] = (int) $id;
                }
            }
        }

        return array_values(array_unique([...$ids, ...array_map('intval', \App\Scoreboard\Fleet::matchesOnMats($event))]));
    }

    private function snapshot(EventMatch $m): array
    {
        return $m->only(['id', 'category_id', 'round', 'slot', 'match_no', 'court', 'day', 'winner', 'won_by_bye', 'status',
            'a_competitor_id', 'a_name', 'a_score', 'b_competitor_id', 'b_name', 'b_score']);
    }

    private function announce(ClubEvent $event, array $categoryIds): void
    {
        $type = rescue(fn () => app(\App\Events\EventTypeRegistry::class)->for($event), null, false);
        foreach ($categoryIds as $id) {
            rescue(fn () => $type?->announceDrawChanged($event, $id), null, false);
        }
        rescue(fn () => \App\Events\Support\HallWalls::refresh($event), null, false);
        // The mat's numbers may have moved (MatNumbering): the scoring consoles re-read.
        if (MatNumbering::enabled()) {
            rescue(fn () => app(\App\Scoreboard\Fleet::class)->boardsChanged($event), null, false);
        }
    }

    private function no(string $message): array
    {
        return ['ok' => false, 'message' => $message];
    }
}
