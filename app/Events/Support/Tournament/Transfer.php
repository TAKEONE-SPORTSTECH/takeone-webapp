<?php

namespace App\Events\Support\Tournament;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Events\Support\EventFee;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Taking a competitor from one division's draw into ANOTHER division's.
 *
 * Two acts, and they are different facts about a person:
 *
 *   · MOVE — they were in the wrong class. Their entry leaves the old division
 *     (its corner empties, the old draw re-flows) and stands in the new one.
 *     Still ONE entry; nothing about money changes.
 *   · COPY — they compete twice. A SECOND entry is made in the new division,
 *     and the organiser says, there and then, whether it is charged: charged
 *     writes the event's entry price as an unpaid fee line; not charged
 *     records it as a free entry (a zero line — see EventFee::commit), paid.
 *
 * This class owns no bracket rules of its own. Every change to a draw goes
 * through the PACKAGE's own Arrangement — the same `move()` the board's drag
 * already uses, with its on-the-mat guard and, for a package still on its own
 * copy, its refusal once the day has started — and every entrant-list change
 * through its `syncEntrants()`. So a transfer can never do anything an
 * organiser could not already do in two steps by hand; it does them as one,
 * inside one transaction, so a refusal half-way leaves nothing changed.
 *
 * What it refuses on its own account:
 *   · a competitor who has already FOUGHT in the division they would leave
 *     (moving them would take a result with them — copy is still fine);
 *   · a person who already holds an entry in the target division;
 *   · anything outside this event, any non-participant, a heading.
 */
class Transfer
{
    public const MOVE = 'move';

    public const COPY = 'copy';

    /**
     * @param  object  $arrangement  the package's Arrangement (move + syncEntrants)
     */
    public function __construct(private object $arrangement) {}

    /**
     * @param  array  $payload  {from_category_id, competitor_id, to_category_id,
     *                          to:{type:'bench'}|{type:'slot',match_id,side},
     *                          mode:'move'|'copy', charge:bool}
     * @return array{ok: bool, message?: string, from?: EventCategory, to?: EventCategory, entry?: ClubEventRegistration}
     */
    public function run(ClubEvent $event, array $payload, ?int $actorId = null): array
    {
        $mode = ($payload['mode'] ?? self::MOVE) === self::COPY ? self::COPY : self::MOVE;

        $from = $event->categories()->find($payload['from_category_id'] ?? null);
        $to = $event->categories()->find($payload['to_category_id'] ?? null);

        if (! $from || ! $to || $from->isHeading() || $to->isHeading()) {
            return ['ok' => false, 'message' => __('events::events.division_not_found')];
        }

        if ($from->id === $to->id) {
            return ['ok' => false, 'message' => __('events::events.transfer_same_division')];
        }

        $entry = ClubEventRegistration::where('event_id', $event->id)
            ->where('id', (int) ($payload['competitor_id'] ?? 0))
            ->where('category_id', $from->id)
            ->where('role', 'participant')
            ->with('user:id,full_name,name')
            ->first();

        if (! $entry) {
            return ['ok' => false, 'message' => __('events::events.bracket_competitor_invalid')];
        }

        // One entry per person per division — the unique key says so, and a
        // person cannot stand on one board twice.
        $held = ClubEventRegistration::where('event_id', $event->id)
            ->where('user_id', $entry->user_id)
            ->where('category_id', $to->id)
            ->exists();

        if ($entry->user_id !== null && $held) {
            return ['ok' => false, 'message' => __('events::events.transfer_already_there')];
        }

        $slot = $this->slotOf($from, $entry->id);

        if ($mode === self::MOVE && $this->hasFought($from, $entry->id, $slot)) {
            return ['ok' => false, 'message' => __('events::events.transfer_already_fought')];
        }

        $to_ = $payload['to'] ?? ['type' => 'bench'];
        $toSlot = ($to_['type'] ?? null) === 'slot';

        try {
            $moved = DB::transaction(function () use ($event, $from, $to, $entry, $slot, $mode, $to_, $toSlot, $payload, $actorId) {
                if ($mode === self::MOVE) {
                    // 1. Out of the old draw through the package's own door.
                    if ($slot !== null) {
                        $this->must($this->arrangement->move($event, [
                            'category_id' => $from->id,
                            'from' => ['type' => 'slot', 'match_id' => $slot['match_id'], 'side' => $slot['side']],
                            'to' => ['type' => 'bench'],
                        ]));
                    }

                    // 2. The entry itself changes division — placed by a person.
                    $entry->update([
                        'category_id' => $to->id,
                        'category_source' => ClubEventRegistration::PLACED_MANUAL,
                    ]);

                    $this->arrangement->syncEntrants($from->fresh());
                    $moving = $entry->fresh(['user']);
                } else {
                    $moving = $this->copy($event, $entry, $to, (bool) ($payload['charge'] ?? false), $actorId);
                }

                // 3. Into the new draw. A pool is simply re-cut around its new
                //    entrant list; a ladder takes them into the corner chosen,
                //    or onto its basket.
                if ($this->isPool($to)) {
                    $this->arrangement->syncEntrants($to->fresh());
                } elseif ($toSlot) {
                    $this->must($this->arrangement->move($event, [
                        'category_id' => $to->id,
                        'from' => ['type' => 'bench', 'competitor_id' => $moving->id],
                        'to' => ['type' => 'slot', 'match_id' => (int) ($to_['match_id'] ?? 0), 'side' => $to_['side'] ?? null],
                    ]));
                }

                return $moving;
            });
        } catch (TransferRefused $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return ['ok' => false, 'message' => __('events::events.transfer_already_there')];
        }

        return ['ok' => true, 'from' => $from->fresh(), 'to' => $to->fresh(), 'entry' => $moved];
    }

    /**
     * A second entry for the same person, in the target division.
     *
     * Made the way the division-members screen already makes one (a replica of
     * the entry, placed by hand) — so weight, belt, club, photo and payer come
     * with it — and then its money is settled explicitly rather than inherited
     * silently: a replica with no fee lines would otherwise be valued at the
     * event's list price AND carry the original's `paid`, which is a sale
     * nobody decided on.
     */
    private function copy(ClubEvent $event, ClubEventRegistration $entry, EventCategory $to, bool $charge, ?int $actorId): ClubEventRegistration
    {
        $copy = $entry->replicate();
        $copy->category_id = $to->id;
        $copy->category_source = ClubEventRegistration::PLACED_MANUAL;
        $copy->payment_proof = null;

        $currency = EventFee::currency($event);

        if ($charge) {
            $copy->paid = false;
            $copy->paid_at = null;
            $copy->paid_by = null;
            $copy->save();

            $price = EventFee::listPrice($event);

            EventFee::commit($copy, [
                'total' => $price,
                'currency' => $currency,
                'lines' => $price > 0 ? [[
                    'kind' => \App\Events\Models\EventRegistrationFeeLine::KIND_BASE,
                    'label' => __('events::events.fee_line_entry'),
                    'amount' => $price,
                    'fee_option_id' => null,
                ]] : [],
            ]);
        } else {
            $copy->paid = true;
            $copy->paid_at = now();
            $copy->paid_by = $actorId;
            $copy->save();

            // A recorded FREE entry: one zero line, so it is never re-valued.
            EventFee::commit($copy, ['total' => 0.0, 'currency' => $currency, 'lines' => []]);
        }

        return $copy->fresh(['user']);
    }

    /** Where this entrant stands in a division's FIRST round, if anywhere. */
    private function slotOf(EventCategory $category, int $entryId): ?array
    {
        foreach ($this->firstRound($category) as $bout) {
            foreach (['a', 'b'] as $side) {
                if ((int) $bout->{$side.'_competitor_id'} === $entryId) {
                    return ['match_id' => $bout->id, 'side' => $side];
                }
            }
        }

        return null;
    }

    /**
     * Has this entrant fought here — a contested bout with them in it, or a
     * place beyond the first round (they got there by winning, or by a bye
     * that turns back into nothing only if the first round is theirs)?
     */
    private function hasFought(EventCategory $category, int $entryId, ?array $slot): bool
    {
        $mine = fn ($q) => $q->where('a_competitor_id', $entryId)->orWhere('b_competitor_id', $entryId);

        if ($category->matches()->contested()->where($mine)->exists()) {
            return true;
        }

        // Standing in a later round without having contested a bout is a bye
        // advancing them — that is scaffolding, and leaving the first round
        // takes it with them. Standing in a later round with NO first-round
        // corner is a result of some other kind, and is not ours to undo.
        return $slot === null && $category->matches()->where($mine)->exists();
    }

    private function isPool(EventCategory $category): bool
    {
        return ($category->format ?: EventCategory::FORMAT_KNOCKOUT) === EventCategory::FORMAT_ROUND_ROBIN;
    }

    /** @return Collection<int, EventMatch> */
    private function firstRound(EventCategory $category): Collection
    {
        $matches = $category->matches()->orderBy('slot')->get();

        if ($matches->isEmpty()) {
            return collect();
        }

        return $matches->groupBy('round')
            ->sortBy(fn (Collection $bouts) => $bouts->min('slot'))
            ->first()->sortBy('slot')->values();
    }

    /** A refused arrangement step aborts the whole transfer. */
    private function must(array $result): void
    {
        if (! ($result['ok'] ?? false)) {
            throw new TransferRefused($result['message'] ?? __('events::events.bracket_move_failed'));
        }
    }
}
