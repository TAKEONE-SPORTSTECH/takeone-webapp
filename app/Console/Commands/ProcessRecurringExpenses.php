<?php

namespace App\Console\Commands;

use App\Models\ClubRecurringExpense;
use App\Services\RecurringExpenseService;
use Illuminate\Console\Command;

class ProcessRecurringExpenses extends Command
{
    protected $signature = 'expenses:process-recurring';

    protected $description = "Post this month's expense for every active recurring expense not yet posted";

    public function handle(RecurringExpenseService $recurringExpenses): int
    {
        // Every active rule that has not posted this month — not only the ones whose
        // day_of_month happens to be today. A monthly wage is an expense of the month
        // it covers, so it must weigh on that month's net from the start of the month;
        // waiting for the due day made a club look profitable until payday.
        $rules = ClubRecurringExpense::with('tenant', 'instructor')
            ->where('is_active', true)
            ->get();

        $count = 0;

        foreach ($rules as $rule) {
            if ($recurringExpenses->postForCurrentMonth($rule)) {
                $count++;
            }
        }

        $this->info("Processed {$count} recurring expense(s).");

        return 0;
    }
}
