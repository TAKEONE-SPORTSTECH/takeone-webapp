<?php

namespace App\Events\Support;

use App\Models\ClubEvent;

/**
 * What an entry costs — the one place that answers it.
 *
 * The fee used to be a sentence ("BHD 20", "10-15 BHD", "Qualified finalists")
 * and every caller that needed a number scraped the first digits out of it. That
 * is fine for a label and wrong for money: it billed 10 for a 10–15 range, read
 * "20 / team" as 20 a head, and carried no currency at all.
 *
 * So there are now two questions with two answers:
 *  - **What does it cost?** → `amount()`, a real number in `fee_currency`.
 *  - **What does the page say?** → `club_events.participant_fee`, the display
 *    line, which may legitimately be prose ("Free", "Qualified finalists").
 *
 * A null amount is not zero-by-another-name: it means the event never stated a
 * price. Both read as "nothing to pay" for now, but Phase 3 invoices only ever
 * bill a stated number.
 *
 * Legacy rows are covered by the same migration that added the columns; the
 * scrape below exists only for a row written between that migration and a
 * caller that has not been updated, and it is the last of its kind.
 */
class EventFee
{
    /** Roles that can carry a fee, mapped to their columns. */
    private const COLUMNS = [
        'participant' => ['participant_fee', 'participant_fee_amount'],
        'spectator' => ['spectator_fee', 'spectator_fee_amount'],
    ];

    /** The stated price for a role, in the event's currency. Null = never stated. */
    public static function amount(ClubEvent $event, string $role = 'participant'): ?float
    {
        [$display, $column] = self::COLUMNS[$role] ?? self::COLUMNS['participant'];

        if ($event->{$column} !== null) {
            return (float) $event->{$column};
        }

        // A row that predates the column, or one written by a path not yet
        // updated. Deliberately the ONLY surviving scrape.
        return self::parse($event->{$display});
    }

    /** What this event bills in. Falls back to the host club's currency. */
    public static function currency(ClubEvent $event): string
    {
        return $event->fee_currency ?: ($event->tenant?->currency ?: 'BHD');
    }

    /**
     * Is there money to collect for this role?
     *
     * Anything at or below zero is not a fee, and neither is a display line with
     * no number behind it — an event whose entry is by qualification is not an
     * event with a fee of nothing.
     */
    public static function isPaid(ClubEvent $event, string $role = 'participant'): bool
    {
        if ($role === 'spectator' && ! $event->spectator_enabled) {
            return false;
        }

        return (self::amount($event, $role) ?? 0.0) > 0;
    }

    /**
     * The display line for an amount: "BHD 20", "BHD 7.5", or the word for free.
     *
     * Trailing zeros are trimmed because a coach typed 20, not 20.000, and the
     * line they see should be the one they wrote.
     */
    public static function display(?float $amount, string $currency): string
    {
        if ($amount === null || $amount <= 0) {
            return __('events.fee_free');
        }

        $formatted = rtrim(rtrim(number_format($amount, 3, '.', ''), '0'), '.');

        return $currency.' '.$formatted;
    }

    /**
     * Read a number out of a fee line. Only for legacy strings — a write path
     * that has an amount must pass the amount.
     */
    public static function parse(?string $fee): ?float
    {
        return ($fee && preg_match('/[\d.]+/', $fee, $m)) ? (float) $m[0] : null;
    }
}
