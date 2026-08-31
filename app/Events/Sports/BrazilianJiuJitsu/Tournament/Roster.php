<?php

namespace App\Events\Sports\BrazilianJiuJitsu\Tournament;

use App\Events\Support\Tournament\Roster as SharedRoster;

/**
 * How a jiu-jitsu championship reads its entrant list — shared behaviour,
 * this package's wording.
 */
class Roster extends SharedRoster
{
    protected function langNamespace(): string
    {
        return 'event-bjj_tournament';
    }
}
