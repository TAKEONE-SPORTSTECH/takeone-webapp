<?php

namespace App\Console\Commands;

use App\Models\ClubRecurringExpense;
use App\Models\ClubTransaction;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ProcessRecurringExpenses extends Command
{
    protected $signature = 'expenses:process-recurring';

    protected $description = 'Create expense transactions for active recurring expenses due today';

    public function handle(): int
    {
        $today       = Carbon::today();
        $dayOfMonth  = (int) $today->format('j'); // day without leading zero

        $recurring = ClubRecurringExpense::with('tenant')
            ->where('is_active', true)
            ->where('day_of_month', $dayOfMonth)
            ->get();

        if ($recurring->isEmpty()) {
            $this->info('No recurring expenses due today.');
            return 0;
        }

        $count = 0;

        foreach ($recurring as $expense) {
            // Skip if already processed this month
            if ($expense->hasRunThisMonth()) {
                continue;
            }

            ClubTransaction::create([
                'tenant_id'        => $expense->tenant_id,
                'type'             => 'expense',
                'description'      => $expense->description,
                'amount'           => $expense->amount,
                'category'         => $expense->category,
                'payment_method'   => $expense->payment_method,
                'reference_number' => $expense->notes,
                'transaction_date' => $today->toDateString(),
            ]);

            $expense->update(['last_run_at' => $today->toDateString()]);

            $count++;
        }

        $this->info("Processed {$count} recurring expense(s).");

        return 0;
    }
}
