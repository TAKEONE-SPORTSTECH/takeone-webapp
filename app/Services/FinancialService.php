<?php

namespace App\Services;

use App\Models\ClubMemberSubscription;
use App\Models\ClubTransaction;
use App\Models\Tenant;
use App\Support\ClubCache;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class FinancialService
{
    /**
     * Record a manual income or expense transaction for a club.
     */
    public function recordTransaction(Tenant $club, array $data): ClubTransaction
    {
        $transaction = ClubTransaction::create(array_merge($data, ['tenant_id' => $club->id]));
        ClubCache::flushFinancials($club->id);
        return $transaction;
    }

    /**
     * Calculate summary totals from an already-loaded transaction collection.
     * Includes cash-to-collect from unpaid subscriptions (requires one extra query).
     */
    public function getSummary(int $clubId, Collection $transactions): array
    {
        $byType        = $transactions->groupBy('type');
        $totalIncome   = (float) $byType->get('income',  collect())->sum('amount');
        $totalExpenses = (float) $byType->get('expense', collect())->sum('amount');
        $totalRefunds  = (float) $byType->get('refund',  collect())->sum('amount');

        return [
            'total_income'   => $totalIncome,
            'total_expenses' => $totalExpenses,
            'refunds'        => $totalRefunds,
            'net_profit'     => $totalIncome - $totalExpenses - $totalRefunds,
            'pending'        => (float) ClubMemberSubscription::where('tenant_id', $clubId)
                ->whereIn('payment_status', ['unpaid', 'pending_approval'])
                ->sum('amount_due'),
        ];
    }

    /**
     * Build 12-month income/expense/profit chart data from a transaction collection.
     */
    public function getMonthlyData(Collection $transactions, int $clubId): array
    {
        $cutoff  = now()->subMonths(11)->startOfMonth();
        $byMonth = $transactions
            ->filter(fn($t) => Carbon::parse($t->transaction_date)->gte($cutoff))
            ->groupBy(fn($t) => Carbon::parse($t->transaction_date)->format('Y-m'));

        // Monthly cash to collect: unpaid subscriptions grouped by start_date month (PHP-side, DB-agnostic)
        $pendingByMonth = ClubMemberSubscription::where('tenant_id', $clubId)
            ->whereIn('payment_status', ['unpaid', 'pending_approval'])
            ->where('start_date', '>=', $cutoff)
            ->get(['start_date', 'amount_due'])
            ->groupBy(fn($s) => Carbon::parse($s->start_date)->format('Y-m'))
            ->map(fn($items) => $items->sum('amount_due'));

        $monthlyData = [];
        for ($i = 11; $i >= 0; $i--) {
            $date    = now()->copy()->subMonths($i);
            $month   = $byMonth->get($date->format('Y-m'), collect());
            $income  = (float) $month->where('type', 'income')->sum('amount');
            $expense = (float) $month->where('type', 'expense')->sum('amount');
            $refund  = (float) $month->where('type', 'refund')->sum('amount');

            $monthlyData[] = [
                'month'           => $date->format('M'),
                'year_month'      => $date->format('Y-m'),
                'income'          => $income,
                'expenses'        => $expense,
                'refunds'         => $refund,
                'profit'          => $income - $expense - $refund,
                'cash_to_collect' => (float) ($pendingByMonth->get($date->format('Y-m')) ?? 0),
            ];
        }

        return $monthlyData;
    }

    /**
     * Group expense transactions by category for the breakdown chart.
     */
    public function getExpenseBreakdown(Collection $transactions): Collection
    {
        return $transactions
            ->where('type', 'expense')
            ->groupBy(fn($t) => $t->category ?? 'Other')
            ->map(fn($items, $cat) => [
                'category' => $cat,
                'items'    => $items,
                'total'    => $items->sum('amount'),
            ])
            ->values();
    }
}
