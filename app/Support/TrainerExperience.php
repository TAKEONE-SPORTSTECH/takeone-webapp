<?php

namespace App\Support;

use App\Members\Models\User;

/**
 * Live, accumulating trainer experience: the total time a person has spent working
 * as a trainer — their prior coaching employment (from work history) PLUS their
 * tenure as an instructor on the platform (from when they were added → now). It is
 * computed fresh on every render, so a trainer who started today reads "New" and
 * "1 day" tomorrow, growing on its own. Overlapping periods are merged so concurrent
 * club roles never double-count.
 */
class TrainerExperience
{
    private const COACH_KEYWORDS = ['coach', 'trainer', 'instructor', 'sensei', 'master', 'sabum', 'sabeom', 'professor', 'mentor'];

    /** Does a work-history role read like a coaching/instructor job? */
    public static function isCoachingRole(?string $title, ?string $type = null): bool
    {
        $haystack = mb_strtolower(trim(($title ?? '').' '.($type ?? '')));

        return collect(self::COACH_KEYWORDS)->contains(fn ($k) => str_contains($haystack, $k));
    }

    /** Prior (pre-platform) coaching experience from work history, in whole months. */
    public static function priorCoachingMonths(User $user): int
    {
        $months = 0;
        foreach ($user->workHistory as $role) {
            if (! self::isCoachingRole($role->title, $role->employment_type) || ! $role->start_date) {
                continue;
            }
            $end = $role->end_date ?? now();
            if ($end->lte($role->start_date)) {
                continue;
            }
            $months += (int) floor($role->start_date->floatDiffInMonths($end));
        }

        return $months;
    }

    /** Prior coaching experience in whole years (snapshot for lists/cards). */
    public static function priorCoachingYears(User $user): int
    {
        return intdiv(self::priorCoachingMonths($user), 12);
    }

    /** Total accumulated trainer days across all merged coaching periods, live to now. */
    public static function totalDays(User $user): int
    {
        $ivals = [];

        // Prior coaching employment (work history).
        foreach ($user->workHistory as $role) {
            if (! self::isCoachingRole($role->title, $role->employment_type) || ! $role->start_date) {
                continue;
            }
            $end = $role->end_date ?? now();
            if ($end->gt($role->start_date)) {
                $ivals[] = [$role->start_date->copy(), $end->copy()];
            }
        }

        // Platform instructor tenure — from when they were added as staff to now
        // (or to when they were deactivated). This is what makes the number live.
        foreach ($user->clubInstructors as $ci) {
            $start = $ci->created_at;
            if (! $start) {
                continue;
            }
            $end = $ci->is_active ? now() : ($ci->updated_at ?? now());
            if ($end->gt($start)) {
                $ivals[] = [$start->copy(), $end->copy()];
            }
        }

        // Merge overlapping periods so concurrent roles don't double-count.
        usort($ivals, fn ($a, $b) => $a[0]->timestamp <=> $b[0]->timestamp);
        $merged = [];
        foreach ($ivals as [$s, $e]) {
            if ($merged && $s->lte($merged[count($merged) - 1][1])) {
                if ($e->gt($merged[count($merged) - 1][1])) {
                    $merged[count($merged) - 1][1] = $e;
                }
            } else {
                $merged[] = [$s, $e];
            }
        }

        return (int) collect($merged)->sum(fn ($iv) => (int) floor($iv[0]->floatDiffInDays($iv[1])));
    }

    /**
     * Human, live experience label — e.g. "3 years 2 months", "5 months", "1 day".
     * Returns null when there is no tenure yet (started today), so callers can show
     * their own "New" state.
     */
    public static function label(User $user): ?string
    {
        $days = self::totalDays($user);
        if ($days < 1) {
            return null;
        }
        if ($days < 30) {
            return $days.' '.($days === 1 ? __('day') : __('days'));
        }

        $years = intdiv($days, 365);
        $months = intdiv($days % 365, 30);
        $parts = [];
        if ($years) {
            $parts[] = $years.' '.($years > 1 ? __('years') : __('year'));
        }
        if ($months) {
            $parts[] = $months.' '.($months > 1 ? __('months') : __('month'));
        }
        // 30–364 days with 0 whole months rounds to at least "1 month".
        if (! $parts) {
            $parts[] = '1 '.__('month');
        }

        return implode(' ', $parts);
    }
}
