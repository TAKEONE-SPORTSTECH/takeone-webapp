<?php

namespace App\Services;

use App\Models\ClubRecurringExpense;
use App\Models\ClubTransaction;
use App\Support\ClubCache;
use Carbon\Carbon;

/**
 * Posts recurring expense rules (staff wages, rent, …) to the ledger.
 *
 * A monthly wage belongs to the month it covers, so a rule posts as soon as that
 * month starts — not only once its `day_of_month` arrives. That is what keeps net
 * profit honest: the salary weighs against the month's income from day one instead
 * of appearing on payday and flipping a "profitable" club into a loss-making one.
 *
 * Partial months are pro-rated, so a rule that starts (or stops) mid-month costs
 * only the days it actually covers. `last_run_at` guarantees one posting per rule
 * per month, whichever path posts it — the daily cron, creating the rule, editing
 * it, or re-activating it.
 */
class RecurringExpenseService
{
    /**
     * Post this month's expense for a rule, unless it is inactive or already posted.
     */
    public function postForCurrentMonth(ClubRecurringExpense $rule): ?ClubTransaction
    {
        if (! $rule->is_active) {
            return null;
        }

        if ($rule->hasRunThisMonth()) {
            // Editing a wage mid-month must correct the figure already sitting in this
            // month's net, not silently take effect only from next month.
            $this->syncPostedAmount($rule);

            return null;
        }

        $today = Carbon::today();
        $amount = $this->proratedAmount($rule, $this->coverageStart($rule), $today->copy()->endOfMonth());

        if ($amount <= 0) {
            return null;
        }

        $date = $this->dueDateThisMonth($rule);

        $transaction = ClubTransaction::create([
            'tenant_id' => $rule->tenant_id,
            'type' => 'expense',
            'description' => $rule->description,
            'amount' => $amount,
            'category' => $rule->category,
            'payment_method' => $rule->payment_method,
            'reference_number' => $rule->notes,
            'transaction_date' => $date->toDateString(),
            'instructor_id' => $rule->instructor_id,
            'recurring_expense_id' => $rule->id,
            'user_id' => $rule->instructor?->user_id,
        ]);

        $rule->forceFill(['last_run_at' => $date->toDateString()])->save();

        ClubCache::flushFinancials($rule->tenant_id);

        return $transaction;
    }

    /**
     * Bring this month's already-posted row back in line with the rule after it was
     * edited. Matched by `recurring_expense_id` and scoped to the rule's own club +
     * month, so it can never touch a manually recorded expense.
     */
    public function syncPostedAmount(ClubRecurringExpense $rule): void
    {
        $posted = $this->postedThisMonth($rule);

        if (! $posted) {
            return;
        }

        $posted->update([
            'amount' => $this->proratedAmount($rule, $this->coverageStart($rule), Carbon::today()->endOfMonth()),
            'description' => $rule->description,
            'category' => $rule->category,
        ]);

        ClubCache::flushFinancials($rule->tenant_id);
    }

    /**
     * A rule that stops mid-month (staff leaves, expense paused) must not leave a whole
     * month's cost in the ledger: cut this month's posted row down to the days actually
     * covered, and drop it entirely if it covered nothing.
     */
    public function stopForCurrentMonth(ClubRecurringExpense $rule): void
    {
        $posted = $this->postedThisMonth($rule);

        if (! $posted) {
            return;
        }

        $today = Carbon::today();
        $prorated = $this->proratedAmount($rule, $this->coverageStart($rule), $today);

        $prorated > 0
            ? $posted->update(['amount' => $prorated, 'transaction_date' => $today->toDateString()])
            : $posted->delete();

        ClubCache::flushFinancials($rule->tenant_id);
    }

    /**
     * This month's posted transaction for the rule, if any.
     */
    public function postedThisMonth(ClubRecurringExpense $rule): ?ClubTransaction
    {
        return ClubTransaction::where('tenant_id', $rule->tenant_id)
            ->where('recurring_expense_id', $rule->id)
            ->whereBetween('transaction_date', [
                Carbon::today()->startOfMonth()->toDateString(),
                Carbon::today()->endOfMonth()->toDateString(),
            ])
            ->latest('id')
            ->first();
    }

    /**
     * The rule's due date inside the current month, clamped to the month's length
     * (a rule due on the 31st posts on the 28th/29th/30th in shorter months).
     */
    public function dueDateThisMonth(ClubRecurringExpense $rule): Carbon
    {
        $start = Carbon::today()->startOfMonth();

        return $start->copy()->day(min((int) $rule->day_of_month ?: 1, $start->daysInMonth));
    }

    /**
     * First day of this month the rule actually covers — the 1st, or the day the wage
     * started if the staff member was hired (or switched to paid) partway through.
     */
    private function coverageStart(ClubRecurringExpense $rule): Carbon
    {
        $monthStart = Carbon::today()->startOfMonth();
        $ruleStart = $rule->instructor?->paid_since ?? $rule->created_at ?? $monthStart;
        $ruleStart = Carbon::parse($ruleStart)->startOfDay();

        return $ruleStart->greaterThan($monthStart) ? $ruleStart : $monthStart;
    }

    /**
     * The rule's monthly amount scaled to the days it covers between two dates,
     * as a share of the month's length. A full month returns the amount unchanged.
     */
    private function proratedAmount(ClubRecurringExpense $rule, Carbon $from, Carbon $to): float
    {
        if ($from->greaterThan($to)) {
            return 0.0;
        }

        // Whole days only — Carbon 3 returns a float diff, and an end-of-month
        // timestamp would otherwise inflate the span by a fraction of a day.
        $daysInMonth = $from->daysInMonth;
        $daysCovered = min((int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1, $daysInMonth);

        return round((float) $rule->amount * $daysCovered / $daysInMonth, 2);
    }
}
