<?php

namespace App\Events\Support;

use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventFeeOption;
use App\Models\EventRegistrationFeeLine;

/**
 * What the verification desk signs off, and what it needs to draw itself.
 *
 * ── Why this is not in a controller any more ───────────────────────────────
 *
 * It was private to PersonalEventController, which was right while exactly one
 * screen opened the desk. TWO do now: the organiser's entry list at
 * /me/events/{uuid}/people, and the participant card on the public event page
 * at /e/{uuid}/participants, which opens the same sheet over the card that was
 * tapped rather than sending the organiser to another list (2026-09-16, at the
 * user's request).
 *
 * A second copy of this is not an option. It decides which official may hold a
 * body weight and which may see a bank receipt, and a rule about disclosure
 * that exists twice is a rule that will eventually disagree with itself
 * (CLAUDE.md → Shared Stays Shared). So the controller keeps its method name
 * and delegates here, and the public page calls the same object.
 *
 * Nothing about the behaviour changed in the move: the code below is what the
 * controller had, and the tests that covered it there cover it here.
 */
class EntryGates
{
    /**
     * Merge the officiating payload onto roster rows — officials only.
     *
     * Roster rows are keyed by USER id (that is what moderation acts on), but
     * the weigh-in and payment endpoints act on a REGISTRATION. This is where
     * the two are joined, and it is the only place `reg_id`, the recorded
     * weight, or a proof-of-payment URL ever enters a roster payload.
     *
     * Each gate's DETAIL goes only to the role that guards it, because the two
     * jobs are held by different people:
     *   - the body weight — these are Kids divisions — only to weigh-in
     *   - whether a receipt exists, and the link to it, only to payments
     *
     * What both roles do get is the pair of signed/not-signed booleans, since
     * "is this entry cleared for the draw?" is the question the whole screen
     * answers and neither flag discloses a weight or a bank transfer.
     *
     * Callers must have already established that the viewer may officiate.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function attach(ClubEvent $event, array $rows, bool $canWeigh, bool $canPay): array
    {
        /*
         * Keyed by REGISTRATION. It used to be keyBy('user_id'), which silently
         * dropped one of the two entries an athlete entered in two divisions:
         * the desk could sign for a weight and approve a fee on one of them and
         * had no door at all to the other. The row is the PERSON now and lists
         * every entry they hold (RosterPeople::byPerson), so each entry gets its
         * own payload and the sheet switches between them.
         */
        $regs = $event->participantRegistrations()
            ->get(['id', 'user_id', 'weight', 'payment_proof', 'paid', 'paid_by', 'weighed_in_at', 'weighed_in_by'])
            ->keyBy('id');

        // id => uuid for the event's fee types, so a stored line can be matched
        // back to the box that is ticked in the sheet. One query, not one per row.
        $optionKeys = $canPay
            ? \App\Models\EventFeeOption::where('event_id', $event->id)->pluck('uuid', 'id')->all()
            : [];

        /* Every fee line for the event in one query rather than one per entry:
           a two-hundred-name roster where a third of the field holds two
           entries is not the place for a query in a loop. */
        $lines = $canPay
            ? \App\Models\EventRegistrationFeeLine::whereIn(
                'registration_id',
                $regs->keys()->all(),
            )->get(['registration_id', 'fee_option_id', 'amount'])->groupBy('registration_id')
            : collect();

        /** The payload for ONE entry — what the desk signs, per entry. */
        $payload = function (ClubEventRegistration $reg, ?string $division) use ($canWeigh, $canPay, $event, $optionKeys, $lines) {
            $entry = [
                'reg_id' => $reg->id,
                'division' => $division,
                'weigh_verified' => $reg->weighed_in_at !== null && $reg->weighed_in_by !== null,
                'pay_verified' => (bool) $reg->paid && $reg->paid_by !== null,
            ];

            if ($canWeigh) {
                $entry['weight'] = $reg->weight !== null ? (float) $reg->weight : null;
            }

            if ($canPay) {
                $entry['has_proof'] = (bool) $reg->payment_proof;
                $entry['proof_url'] = $reg->payment_proof
                    ? route('me.events.verify.proof', [$event->uuid, $reg->id])
                    : null;

                /*
                 * WHAT they are paying for, so the desk can correct it.
                 *
                 * Only the uuids they ticked and what the entry was charged —
                 * the same two facts the sheet needs to draw the tick boxes and
                 * a total. Empty for an entry taken at a desk before anything
                 * was quoted, which is exactly the case this exists to fix: an
                 * official chooses the types and the amount stops being a guess
                 * (see verifyFees).
                 */
                $own = $lines->get($reg->id) ?? collect();

                $entry['fee_options'] = $own->pluck('fee_option_id')->filter()
                    ->map(fn ($id) => $optionKeys[$id] ?? null)->filter()->values()->all();
                $entry['fee_recorded'] = $own->isNotEmpty();
                $entry['fee_charged'] = $own->isNotEmpty() ? round((float) $own->sum('amount'), 3) : null;
            }

            return $entry;
        };

        return array_map(function (array $row) use ($regs, $payload) {
            $ids = collect($row['entries'] ?? [])->pluck('registration')->filter();

            if ($ids->isEmpty() && ($row['registration'] ?? null)) {
                $ids = collect([$row['registration']]);
            }

            $divisions = collect($row['entries'] ?? [])
                ->mapWithKeys(fn (array $e) => [$e['registration'] => $e['division'] ?? null]);

            $entries = $ids
                ->map(fn ($id) => $regs->get($id))
                ->filter()
                ->map(fn (ClubEventRegistration $reg) => $payload($reg, $divisions->get($reg->id)))
                ->values()
                ->all();

            if ($entries === []) {
                return $row;
            }

            /* The person's entries, each with its own gate, plus the FIRST one
               flattened onto the row: that is the entry a tap on the card opens,
               and it keeps the shape every existing reader of this payload
               already expects. */
            $row['entry_gates'] = $entries;

            return $entries[0] + $row;
        }, $rows);
    }

    public function paymentInstructions(ClubEvent $event): array
    {
        $bank = $event->tenant?->bankAccounts()
            ->orderByDesc('is_primary')->orderBy('id')->first();

        return [
            'club' => $event->tenant?->club_name,
            'bank' => $bank ? array_filter([
                'bank_name' => $bank->bank_name,
                'account_name' => $bank->account_name,
                'account_number' => $bank->account_number,
                'iban' => $bank->iban,
                'benefitpay' => $bank->benefitpay_account,
            ]) : null,
        ];
    }

    public function feeTypes(ClubEvent $event): array
    {
        $currency = EventFee::currency($event);

        return EventFee::options($event, 'participant')
            ->map(fn ($o) => [
                'key' => (string) $o->uuid,
                'label' => (string) $o->label,
                'amount' => (float) $o->amount,
                'label_amount' => EventFee::display((float) $o->amount, $currency),
            ])->values()->all();
    }
}
