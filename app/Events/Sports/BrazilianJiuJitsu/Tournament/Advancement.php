<?php

namespace App\Events\Sports\BrazilianJiuJitsu\Tournament;

use App\Scoreboard\Sports\BrazilianJiuJitsu\Mat\Scoring;
use App\Events\Support\Tournament\Advancement as SharedAdvancement;

/**
 * Bracket progression for a jiu-jitsu weight division.
 *
 * The mechanics — carrying a winner forward, re-cutting everything downstream
 * of a corrected result, awarding the podium by the sport's bronze rule — are
 * the same for every bracketed, medal-awarding sport, so they live once in
 * App\Events\Support\Tournament\Advancement. All this package adds is the one
 * thing a rule book decides: which reasons other than points may end a bout.
 */
class Advancement extends SharedAdvancement
{
    /** @return array<int, string> */
    protected function winReasons(): array
    {
        return Scoring::WIN_REASONS;
    }
}
