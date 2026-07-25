<?php

namespace App\Support;

use App\Models\User;

/**
 * Accumulated skill experience for a member: how long they've practised each skill
 * across ALL their affiliations. Mirrors the affiliation sheet + overview badges —
 * enrolled (gap-excluded) time for system clubs, each skill's own recorded span for
 * manual/pre-system records.
 */
class SkillExperience
{
    /** [lower(skill name) => accumulated whole months] across the user's affiliations. */
    public static function monthsPerSkill(User $user): array
    {
        $affiliations = $user->clubAffiliations()
            ->with(['subscriptions', 'skillAcquisitions'])
            ->get();

        $out = [];
        foreach ($affiliations as $aff) {
            $hasSubs = $aff->subscriptions->isNotEmpty();
            $affMonths = $hasSubs ? self::enrolledMonths($aff) : null;
            foreach ($aff->skillAcquisitions as $skill) {
                $key = mb_strtolower(trim((string) $skill->skill_name));
                if ($key === '') {
                    continue;
                }
                $months = $hasSubs ? $affMonths : (int) ($skill->duration_months ?? 0);
                $out[$key] = ($out[$key] ?? 0) + max(0, (int) $months);
            }
        }

        return $out;
    }

    /** Accumulated ENROLLED months in a club — merged subscription periods, gaps excluded. */
    private static function enrolledMonths($aff): int
    {
        $ivals = $aff->subscriptions
            ->filter(fn ($s) => $s->start_date)
            ->map(fn ($s) => [$s->start_date->copy(), ($s->end_date ?? now())->copy()])
            ->sortBy(fn ($i) => $i[0]->timestamp)->values()->all();

        $merged = [];
        foreach ($ivals as [$s, $e]) {
            if ($e->lte($s)) {
                continue;
            }
            if ($merged && $s->lte($merged[count($merged) - 1][1])) {
                if ($e->gt($merged[count($merged) - 1][1])) {
                    $merged[count($merged) - 1][1] = $e;
                }
            } else {
                $merged[] = [$s, $e];
            }
        }

        return (int) collect($merged)->sum(fn ($iv) => (int) floor($iv[0]->floatDiffInMonths($iv[1])));
    }

    /** "X years Y months" from a whole-month count, or null when under a month. */
    public static function format(int $months): ?string
    {
        $months = max(0, $months);
        if ($months < 1) {
            return null;
        }
        $y = intdiv($months, 12);
        $r = $months % 12;
        $parts = [];
        if ($y) {
            $parts[] = $y.' '.($y > 1 ? __('years') : __('year'));
        }
        if ($r) {
            $parts[] = $r.' '.($r > 1 ? __('months') : __('month'));
        }

        return implode(' ', $parts);
    }
}
