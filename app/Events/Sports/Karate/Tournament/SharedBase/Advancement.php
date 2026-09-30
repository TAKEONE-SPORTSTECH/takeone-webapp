<?php

namespace App\Events\Sports\Karate\Tournament\SharedBase;

use App\Events\Support\Tournament\Advancement as SharedAdvancement;
use App\Scoreboard\Sports\Karate\Mat\Scoring;

/**
 * Karate on the SHARED championship base (App\Events\Support\Tournament) —
 * the parallel path of the 2026-09-27 migration (CLAUDE.md → "Known debt":
 * extract the base, point BJJ at it, then port Karate and Taekwondo as
 * separate deliberate steps). Used only while `events.karate_shared_base` is
 * ON; the classes one folder up stay the live path until the cut-over, and
 * are deleted only after it has been through an event day.
 */
class Advancement extends SharedAdvancement
{
    /** The reasons other than points a karate bout may end on — the rule book's. */
    protected function winReasons(): array
    {
        return Scoring::WIN_REASONS;
    }
}
