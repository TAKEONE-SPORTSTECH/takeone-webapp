<?php

namespace App\Services;

use App\Models\Business;
use App\Models\ClubMemberSubscription;
use App\Models\ClubTransaction;
use App\Models\Membership;
use Illuminate\Support\Collection;

/**
 * Aggregates performance metrics across every club in a business (chain).
 * Uses a fixed number of grouped queries regardless of how many clubs exist.
 */
class ChainDashboardService
{
    /** Subscription payment statuses that count as money still owed. */
    private const UNPAID_STATUSES = ['unpaid', 'pending_approval'];

    /** Subscription statuses considered active. */
    private const ACTIVE_SUB_STATUSES = ['active', 'pending'];

    /**
     * Build the chain dashboard payload: per-club rows plus combined totals.
     *
     * @return array{clubs: Collection, totals: array}
     */
    public function build(Business $business): array
    {
        $clubs = $business->clubs()
            ->select('id', 'club_name', 'slug', 'logo', 'currency')
            ->orderBy('club_name')
            ->get();

        $clubIds = $clubs->pluck('id')->all();

        if (empty($clubIds)) {
            return [
                'clubs'  => collect(),
                'totals' => $this->emptyTotals(),
            ];
        }

        $members = Membership::whereIn('tenant_id', $clubIds)
            ->where('status', 'active')
            ->selectRaw('tenant_id, COUNT(*) as c')
            ->groupBy('tenant_id')
            ->pluck('c', 'tenant_id');

        $activeSubs = ClubMemberSubscription::whereIn('tenant_id', $clubIds)
            ->whereIn('status', self::ACTIVE_SUB_STATUSES)
            ->selectRaw('tenant_id, COUNT(*) as c')
            ->groupBy('tenant_id')
            ->pluck('c', 'tenant_id');

        $income = ClubTransaction::whereIn('tenant_id', $clubIds)
            ->where('type', 'income')
            ->selectRaw('tenant_id, COALESCE(SUM(amount),0) as s')
            ->groupBy('tenant_id')
            ->pluck('s', 'tenant_id');

        $expenses = ClubTransaction::whereIn('tenant_id', $clubIds)
            ->where('type', 'expense')
            ->selectRaw('tenant_id, COALESCE(SUM(amount),0) as s')
            ->groupBy('tenant_id')
            ->pluck('s', 'tenant_id');

        $cashToCollect = ClubMemberSubscription::whereIn('tenant_id', $clubIds)
            ->whereIn('payment_status', self::UNPAID_STATUSES)
            ->selectRaw('tenant_id, COALESCE(SUM(amount_due),0) as s')
            ->groupBy('tenant_id')
            ->pluck('s', 'tenant_id');

        $rows = $clubs->map(function ($club) use ($members, $activeSubs, $income, $expenses, $cashToCollect) {
            $rev = (float) ($income[$club->id] ?? 0);
            $exp = (float) ($expenses[$club->id] ?? 0);

            return [
                'id'              => $club->id,
                'name'            => $club->club_name,
                'slug'            => $club->slug,
                'logo'            => $club->logo,
                'currency'        => $club->currency ?: '',
                'members'         => (int) ($members[$club->id] ?? 0),
                'active_subs'     => (int) ($activeSubs[$club->id] ?? 0),
                'revenue'         => $rev,
                'expenses'        => $exp,
                'net'             => $rev - $exp,
                'cash_to_collect' => (float) ($cashToCollect[$club->id] ?? 0),
            ];
        });

        $totals = [
            'clubs'           => $rows->count(),
            'members'         => (int) $rows->sum('members'),
            'active_subs'     => (int) $rows->sum('active_subs'),
            'revenue'         => (float) $rows->sum('revenue'),
            'expenses'        => (float) $rows->sum('expenses'),
            'net'             => (float) $rows->sum('net'),
            'cash_to_collect' => (float) $rows->sum('cash_to_collect'),
            // Most clubs share one currency; surface the first for display.
            'currency'        => $rows->first()['currency'] ?? '',
        ];

        return ['clubs' => $rows, 'totals' => $totals];
    }

    private function emptyTotals(): array
    {
        return [
            'clubs' => 0, 'members' => 0, 'active_subs' => 0,
            'revenue' => 0.0, 'expenses' => 0.0, 'net' => 0.0,
            'cash_to_collect' => 0.0, 'currency' => '',
        ];
    }
}
