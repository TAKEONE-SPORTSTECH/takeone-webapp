<?php

namespace App\Clubs\Support;

use App\Clubs\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The numbers on a club's Analytics page, read from the club's own records.
 *
 * Everything is scoped to ONE tenant id and bounded in time (this calendar
 * year at most), so the page stays cheap however long the club has existed.
 * Money follows the club's test mode the same way the Financials page does:
 * a club in test mode sees its test transactions, a live club never does.
 * Rows are grouped in PHP rather than with SQL date functions so the same
 * code runs on SQLite today and MySQL later.
 */
final class ClubAnalytics
{
    /** Peak-hours buckets: 6am, 8am … 10pm (two hours each). */
    private const HOUR_BUCKETS = 9;

    public function build(Tenant $club): array
    {
        $now = CarbonImmutable::now();
        $monthStart = $now->startOfMonth();
        $lastMonthStart = $monthStart->subMonth();
        $yearStart = $now->startOfYear();
        $isTest = (bool) $club->is_test_mode;

        // ── Members ────────────────────────────────────────────────────
        $memberships = DB::table('memberships')
            ->where('tenant_id', $club->id)
            ->get(['status', 'created_at']);

        $joinedThisMonth = $memberships->filter(fn ($m) => $m->created_at >= $monthStart->toDateTimeString())->count();
        $joinedLastMonth = $memberships->filter(fn ($m) => $m->created_at >= $lastMonthStart->toDateTimeString()
            && $m->created_at < $monthStart->toDateTimeString())->count();

        $monthlyMembers = array_fill(0, 12, 0);
        foreach ($memberships as $m) {
            if ($m->created_at >= $yearStart->toDateTimeString()) {
                $monthlyMembers[(int) substr($m->created_at, 5, 2) - 1]++;
            }
        }

        $active = $memberships->where('status', 'active')->count();
        $retention = $memberships->count() > 0 ? round($active / $memberships->count() * 100) : 0;

        // ── Money ──────────────────────────────────────────────────────
        $income = DB::table('club_transactions')
            ->where('tenant_id', $club->id)
            ->where('is_test', $isTest)
            ->where('type', 'income')
            ->where('transaction_date', '>=', $yearStart->toDateString())
            ->get(['amount', 'transaction_date']);

        $monthlyRevenue = array_fill(0, 12, 0.0);
        foreach ($income as $t) {
            $monthlyRevenue[(int) substr((string) $t->transaction_date, 5, 2) - 1] += (float) $t->amount;
        }
        $revenueThisMonth = $monthlyRevenue[$now->month - 1];

        // ── Check-ins: club class attendance + door check-ins ─────────
        $since = $now->subDays(90)->startOfDay();
        $classCheckins = DB::table('class_attendances as ca')
            ->join('club_package_activities as pa', 'pa.id', '=', 'ca.package_activity_id')
            ->join('club_packages as p', 'p.id', '=', 'pa.package_id')
            ->where('p.tenant_id', $club->id)
            ->where('ca.date', '>=', min($since, $lastMonthStart)->toDateString())
            ->get(['ca.date', 'ca.slot_start'])
            ->map(fn ($r) => [substr((string) $r->date, 0, 10), (string) $r->slot_start]);

        $doorCheckins = DB::table('attendance_records')
            ->where('tenant_id', $club->id)
            ->where('check_in_time', '>=', min($since, $lastMonthStart)->toDateTimeString())
            ->pluck('check_in_time')
            ->map(fn ($t) => [substr((string) $t, 0, 10), substr((string) $t, 11, 5)]);

        $checkins = $classCheckins->concat($doorCheckins);
        $thisMonth = $checkins->filter(fn ($c) => $c[0] >= $monthStart->toDateString())->count();
        $lastMonth = $checkins->filter(fn ($c) => $c[0] >= $lastMonthStart->toDateString() && $c[0] < $monthStart->toDateString())->count();

        $hourly = array_fill(0, self::HOUR_BUCKETS, 0);
        foreach ($checkins as [$date, $time]) {
            if ($date < $since->toDateString() || ! preg_match('/^(\d{1,2}):/', $time, $h)) {
                continue;
            }
            $hourly[max(0, min(self::HOUR_BUCKETS - 1, intdiv((int) $h[1] - 6, 2)))]++;
        }

        // ── Activity breakdown: who is signed up to what ──────────────
        $activities = DB::table('club_member_subscriptions as s')
            ->join('club_package_activities as pa', 'pa.package_id', '=', 's.package_id')
            ->join('club_activities as a', 'a.id', '=', 'pa.activity_id')
            ->where('s.tenant_id', $club->id)
            ->where('s.is_test', $isTest)
            ->whereIn('s.status', ['active', 'pending'])
            ->groupBy('a.id', 'a.name')
            ->orderByDesc(DB::raw('count(distinct s.user_id)'))
            ->limit(5)
            ->get(['a.name', DB::raw('count(distinct s.user_id) as members')]);

        return [
            'new_members' => $joinedThisMonth,
            'new_members_change' => $this->change($joinedThisMonth, $joinedLastMonth),
            'retention_rate' => $retention,
            'retention_change' => 0,
            'avg_revenue' => $active > 0 ? round($revenueThisMonth / $active, 2) : 0,
            'monthly_revenue' => array_map(fn ($v) => round($v, 2), $monthlyRevenue),
            'total_checkins' => $thisMonth,
            'checkins_change' => $this->change($thisMonth, $lastMonth),
            'monthly_members' => $monthlyMembers,
            'activity_labels' => $activities->pluck('name')->map(fn ($n) => (string) $n)->all(),
            'activity_data' => $activities->pluck('members')->map(fn ($n) => (int) $n)->all(),
            'hourly_checkins' => $hourly,
        ];
    }

    /** Percentage change against the previous period; 0 when there is nothing to compare. */
    private function change(int $now, int $before): int
    {
        return $before > 0 ? (int) round(($now - $before) / $before * 100) : 0;
    }
}
