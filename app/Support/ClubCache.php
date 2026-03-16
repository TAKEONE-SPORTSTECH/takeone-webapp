<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Centralised cache key registry and flush helper for per-club data.
 *
 * TTLs are intentionally conservative — stale stats by one hour are
 * acceptable, but stale financial data is flushed eagerly on write.
 *
 * Changing CACHE_STORE from 'database' to 'redis' in .env is all
 * that is needed to upgrade the backend when Redis is available.
 */
class ClubCache
{
    // TTLs in seconds
    const TTL_STATS      = 3600;  // 1 hour  — member counts, nationality, etc.
    const TTL_FINANCIALS = 1800;  // 30 min  — monthly chart data
    const TTL_ANALYTICS  = 3600;  // 1 hour  — popular packages

    // ── Key builders ────────────────────────────────────────────────────────

    public static function dashboardStats(int $clubId): string
    {
        return "club.{$clubId}.dashboard.stats";
    }

    public static function dashboardFinancials(int $clubId): string
    {
        return "club.{$clubId}.dashboard.financials";
    }

    public static function showStats(int $clubId): string
    {
        return "club.{$clubId}.show.stats";
    }

    public static function showMonthlyTrend(int $clubId): string
    {
        return "club.{$clubId}.show.monthly_trend";
    }

    public static function analyticsPopularPackages(int $clubId): string
    {
        return "club.{$clubId}.analytics.popular_packages";
    }

    // ── Flush helpers ────────────────────────────────────────────────────────

    /**
     * Bust all stat caches for a club (membership changes).
     */
    public static function flushStats(int $clubId): void
    {
        Cache::forget(self::dashboardStats($clubId));
        Cache::forget(self::showStats($clubId));
        Cache::forget(self::showMonthlyTrend($clubId));
        Cache::forget(self::analyticsPopularPackages($clubId));
    }

    /**
     * Bust financial caches for a club (transaction written or payment approved).
     */
    public static function flushFinancials(int $clubId): void
    {
        Cache::forget(self::dashboardFinancials($clubId));
    }

    /**
     * Bust everything for a club (e.g. on club update or bulk import).
     */
    public static function flushAll(int $clubId): void
    {
        self::flushStats($clubId);
        self::flushFinancials($clubId);
    }
}
