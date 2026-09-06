<?php

namespace App\Events\Support;

use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventFeeOption;
use App\Models\EventRegistrationFeeLine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;

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
 *
 * MULTI-PRICING (2026-09-06)
 * --------------------------
 * An event may now also sell named OPTIONS — "Gi", "No-Gi", "T-shirt" — and
 * charge a flat LATE penalty to anyone entering after a stated moment. The rule
 * is one sentence:
 *
 *     total = base fee + every option ticked + late penalty if past the date
 *
 * The two amount columns are that BASE, unchanged, which is why an event with no
 * options answers every existing question exactly as it did before. `amount()`
 * still means "the base price" and every caller that only wanted a headline
 * number is still right.
 *
 * `quote()` is the new question — what does THIS entry cost — and it is the only
 * thing that may be trusted with money. It takes option UUIDs from the browser
 * and prices them from the event's own rows: a posted price is never read, on
 * any door. `commit()` then freezes the quote onto the registration as fee
 * lines, because what somebody agreed to pay is a fact about that moment and
 * must survive the organiser editing the price afterwards.
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
     *
     * The CURRENCY is translated (the `currency` lang file), because a Latin
     * three-letter code inside an Arabic page is the one word on the line that
     * still reads as English — reported 2026-09-04 on the public poster, where
     * the fee chip said "BHD 10" in an otherwise Arabic column. English is
     * unchanged: every code in that file maps to itself. A currency nobody
     * listed falls back to its own code rather than a missing-key string.
     *
     * The NUMBER keeps Western digits in both languages, deliberately — that is
     * what the rest of the platform's Arabic shows (Carbon's `ar` locale does
     * the same), and a page mixing the two digit sets reads worse than either.
     */
    public static function display(?float $amount, string $currency): string
    {
        if ($amount === null || $amount <= 0) {
            return __('events.fee_free');
        }

        $formatted = rtrim(rtrim(number_format($amount, 3, '.', ''), '0'), '.');

        return self::currencyLabel($currency).' '.$formatted;
    }

    /** The currency as this locale writes it, or the bare code when it is not listed. */
    public static function currencyLabel(string $currency): string
    {
        $code = strtoupper(trim($currency));
        $key = 'currency.'.$code;

        return Lang::has($key) ? __($key) : $currency;
    }

    /* ===================== Options ===================== */

    /**
     * The options an event sells for a role, in the organiser's order.
     *
     * Only ACTIVE ones: a withdrawn option must stop being offered without
     * taking the charge records that reference it with it.
     *
     * @return \Illuminate\Support\Collection<int, EventFeeOption>
     */
    public static function options(ClubEvent $event, string $role = 'participant')
    {
        return EventFeeOption::query()
            ->where('event_id', $event->id)
            ->forRole($role)
            ->active()
            ->ordered()
            ->get();
    }

    /** Does this event sell anything beyond its base fee? */
    public static function hasOptions(ClubEvent $event, string $role = 'participant'): bool
    {
        return self::options($event, $role)->isNotEmpty();
    }

    /**
     * Can this event charge ANYBODY anything for this role?
     *
     * `isPaid()` answers a narrower question — is there a base price — and that
     * was the wrong question the moment an event could be priced entirely out of
     * options. A jiu-jitsu tournament sold as "Gi 15 / No-Gi 15" with no base
     * has `isPaid() === false`, so every door marked its entrants
     * `paid = true` on the way in: the roster showed a hundred per cent
     * settled, `finance()` booked the revenue, and nobody was ever asked for
     * money. The same held for an event whose only charge was the late penalty.
     *
     * So: a base, or any active priced option, or an armed late fee.
     *
     * This is the question a SCREEN should ask ("does money feature here"). A
     * DOOR deciding whether one particular entry is settled should use that
     * entry's own quote total instead, because what somebody ticked is what
     * they owe.
     */
    public static function chargesAnything(ClubEvent $event, string $role = 'participant'): bool
    {
        if (self::isPaid($event, $role)) {
            return true;
        }

        if (self::options($event, $role)->contains(fn ($o) => (float) $o->amount > 0)) {
            return true;
        }

        return $role === 'participant'
            && (float) ($event->late_fee_amount ?? 0) > 0
            && $event->late_fee_from !== null;
    }

    /* ===================== The late penalty ===================== */

    /**
     * Is an entry taken RIGHT NOW a late one?
     *
     * Both columns are required to mean anything: an amount with no date has no
     * moment to start from, and a date with no amount charges nothing. Asking
     * here rather than at four call sites is the point of the method.
     *
     * Participants only — a spectator buying a ticket on the day is the normal
     * way anybody watches sport.
     */
    public static function lateFeeApplies(ClubEvent $event, string $role = 'participant', ?Carbon $at = null): bool
    {
        if ($role !== 'participant') {
            return false;
        }

        if (! $event->late_fee_from || (float) ($event->late_fee_amount ?? 0) <= 0) {
            return false;
        }

        return ($at ?? Carbon::now())->greaterThanOrEqualTo($event->late_fee_from);
    }

    /* ===================== What an entry costs ===================== */

    /**
     * Price one entry: base + the options chosen + the late penalty.
     *
     * ⚠️ `$optionKeys` are UUIDs FROM THE BROWSER and are treated as nothing
     * more than that. They are looked up against this event's own active rows,
     * and anything that does not resolve is dropped silently rather than
     * refused — a stale option on a form somebody left open overnight must not
     * turn into an error page between them and entering. Prices are never read
     * from the request on any door, which matters most on the public entry link
     * where the payer is a stranger.
     *
     * Returns the LINES as well as the total, because the lines are what gets
     * frozen and what the entrant is shown; a bare number could not be explained
     * back to them.
     *
     * @param  array<int, string> $optionKeys  option UUIDs the entrant ticked
     * @return array{total: float, currency: string, lines: array<int, array{kind: string, label: string, amount: float, fee_option_id: ?int}>}
     */
    public static function quote(
        ClubEvent $event,
        string $role = 'participant',
        array $optionKeys = [],
        ?Carbon $at = null,
    ): array {
        $currency = self::currency($event);
        $lines = [];

        // 1. The base. A null amount means the event never stated a price, which
        //    is not the same as zero — it produces no line at all, so a free
        //    event's entry has nothing to explain.
        $base = self::amount($event, $role);

        if ($base !== null && $base > 0) {
            $lines[] = [
                'kind' => EventRegistrationFeeLine::KIND_BASE,
                'label' => $role === 'spectator' ? __('events.fee_line_ticket') : __('events.fee_line_entry'),
                'amount' => (float) $base,
                'fee_option_id' => null,
            ];
        }

        // 2. What they ticked, priced from the event's rows. Keyed by uuid so a
        //    repeated key cannot be charged twice.
        if ($optionKeys !== []) {
            $keys = array_values(array_unique(array_filter(array_map(
                fn ($k) => is_string($k) ? trim($k) : null,
                $optionKeys,
            ))));

            if ($keys !== []) {
                $chosen = self::options($event, $role)->whereIn('uuid', $keys);

                foreach ($chosen as $option) {
                    $lines[] = [
                        'kind' => EventRegistrationFeeLine::KIND_OPTION,
                        'label' => $option->label,
                        'amount' => (float) $option->amount,
                        'fee_option_id' => $option->id,
                    ];
                }
            }
        }

        // 3. The penalty, once, on top of everything.
        if (self::lateFeeApplies($event, $role, $at)) {
            $lines[] = [
                'kind' => EventRegistrationFeeLine::KIND_LATE,
                'label' => __('events.fee_line_late'),
                'amount' => (float) $event->late_fee_amount,
                'fee_option_id' => null,
            ];
        }

        return [
            'total' => round(array_sum(array_column($lines, 'amount')), 3),
            'currency' => $currency,
            'lines' => $lines,
        ];
    }

    /**
     * Freeze a quote onto an entry.
     *
     * Replaces whatever was there, so re-running it for an entry an organiser
     * has just edited leaves one correct set rather than two overlapping ones.
     * Wrapped in a transaction for that reason: a half-replaced charge is worse
     * than either the old one or the new one.
     *
     * Returns the total written, which is what a caller wants to tell the payer.
     */
    public static function commit(ClubEventRegistration $registration, array $quote): float
    {
        DB::transaction(function () use ($registration, $quote) {
            EventRegistrationFeeLine::where('registration_id', $registration->id)->delete();

            /*
             * An entry that costs nothing still gets a line — a zero one.
             *
             * Otherwise "this entry was recorded as free" and "this entry is
             * older than the record" look identical: both have no lines, and
             * `charged()` falls back to the event's CURRENT base fee for the
             * second. A free event whose organiser later put a price on the row
             * would have had its whole history re-valued at that price, which is
             * precisely the bug the lines were introduced to end.
             *
             * One row per free entry is a cheap price for the distinction, and
             * it trips the `exists()` branch in charged().
             */
            $lines = $quote['lines'] ?: [[
                'kind' => EventRegistrationFeeLine::KIND_BASE,
                'label' => __('events.fee_free'),
                'amount' => 0.0,
                'fee_option_id' => null,
            ]];

            foreach ($lines as $line) {
                EventRegistrationFeeLine::create([
                    'registration_id' => $registration->id,
                    'fee_option_id' => $line['fee_option_id'],
                    'kind' => $line['kind'],
                    'label' => $line['label'],
                    'amount' => $line['amount'],
                    'currency' => $quote['currency'],
                ]);
            }
        });

        return (float) $quote['total'];
    }

    /**
     * What an entry was charged, from its own frozen lines.
     *
     * Falls back to the event's base fee for the entries taken BEFORE lines
     * existed. Those rows are not wrong, they are simply older than the record —
     * and reporting them as free would be a worse answer than the one the
     * platform gave at the time.
     */
    public static function charged(ClubEventRegistration $registration, ?ClubEvent $event = null): float
    {
        $lines = EventRegistrationFeeLine::where('registration_id', $registration->id)->sum('amount');

        if ($lines > 0) {
            return (float) $lines;
        }

        if (EventRegistrationFeeLine::where('registration_id', $registration->id)->exists()) {
            return 0.0; // A real, recorded, free entry.
        }

        $event ??= $registration->event;

        if (! $event) {
            return 0.0;
        }

        $role = $registration->role === 'spectator' ? 'spectator' : 'participant';

        return (float) (self::amount($event, $role) ?? 0.0);
    }

    /**
     * What ONE entry costs when nobody wrote down what it cost.
     *
     * The base fee where there is one — and where there is not, the CHEAPEST
     * thing on the event's own price list. That second half is the whole point:
     * once an event prices itself through options the base column is 0, and
     * valuing an unrecorded paid entry at the base meant valuing it at nothing.
     * An event with eighteen paid entrants reported ten dinars of revenue
     * (2026-09-06) because seventeen of them had no fee line and were therefore
     * counted as free.
     *
     * Deliberately the cheapest and not an average: it is an estimate standing
     * in for a missing record, and an estimate that can only be too LOW is one
     * an organiser can reason about.
     */
    public static function listPrice(ClubEvent $event, string $role = 'participant'): float
    {
        $base = (float) (self::amount($event, $role) ?? 0.0);

        if ($base > 0) {
            return $base;
        }

        $options = self::options($event, $role);

        return $options->isEmpty() ? 0.0 : (float) $options->min('amount');
    }

    /**
     * What each of these entries was charged — one query, whatever the size of
     * the list.
     *
     * Returns [registration id => amount] for every id given, plus which of
     * them are ESTIMATES rather than records, so a caller can say so instead of
     * presenting a guess as a total.
     *
     * Three states, and they are not the same thing:
     *   · lines that add up          → that is what they were charged;
     *   · a line row summing to zero → a recorded FREE entry, and zero is right;
     *   · no line row at all         → older than the record, so the list price.
     *
     * @param  iterable<int>  $registrationIds
     * @return array{amounts: array<int, float>, estimated: array<int, float>}
     */
    public static function chargedForMany(ClubEvent $event, iterable $registrationIds, string $role = 'participant'): array
    {
        $ids = collect($registrationIds)->map('intval')->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return ['amounts' => [], 'estimated' => []];
        }

        $lines = EventRegistrationFeeLine::whereIn('registration_id', $ids)
            ->selectRaw('registration_id, SUM(amount) AS total')
            ->groupBy('registration_id')
            ->pluck('total', 'registration_id');

        $list = self::listPrice($event, $role);

        $amounts = [];
        $estimated = [];

        foreach ($ids as $id) {
            if ($lines->has($id)) {
                $amounts[$id] = (float) $lines->get($id);

                continue;
            }

            $amounts[$id] = $list;

            if ($list > 0) {
                $estimated[$id] = $list;
            }
        }

        return ['amounts' => $amounts, 'estimated' => $estimated];
    }

    /**
     * The headline for a page: one price, or the range an entry can land in.
     *
     * "BHD 10" when that is the only answer; "From BHD 10" once options can take
     * it higher. Deliberately not a max — the top of the range is whatever
     * somebody ticks, and quoting the total of every option at once would
     * advertise a price nobody pays.
     */
    public static function headline(ClubEvent $event, string $role = 'participant'): string
    {
        $currency = self::currency($event);
        $base = self::amount($event, $role) ?? 0.0;
        $options = self::options($event, $role);

        if ($options->isEmpty()) {
            return self::display($base > 0 ? $base : null, $currency);
        }

        // The cheapest an entry can be: the base plus nothing, unless the base
        // itself is nothing, in which case the cheapest option is the floor.
        $floor = $base > 0 ? $base : (float) $options->min('amount');

        if ($floor <= 0) {
            return __('events.fee_free');
        }

        return __('events.fee_from', ['amount' => self::display($floor, $currency)]);
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
