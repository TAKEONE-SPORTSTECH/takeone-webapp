<?php

namespace App\Scoreboard\Sports\Karate\Styles\Shotokan;

use App\Scoreboard\Sports\Karate\Mat\Scoring as BaseScoring;

/**
 * The Shotokan scoring engine — the style's own rules for a bout.
 *
 * Started 2026-10-07 as an exact copy of the Karate board everyone runs today
 * (the owner: "copy it to the other karate types and work on them from
 * there"), so it changes nothing yet. Rules that differ for Shotokan are
 * overridden HERE and nowhere else; the plumbing (mat state, screens, the
 * live channel, saving the result) stays in the shared base.
 *
 * Chosen by App\Scoreboard\Sports\Karate\Styles\KarateStyles for an event whose
 * style is `shotokan`.
 */
class Scoring extends BaseScoring {}
