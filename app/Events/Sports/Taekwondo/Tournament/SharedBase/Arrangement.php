<?php

namespace App\Events\Sports\Taekwondo\Tournament\SharedBase;

use App\Events\Support\Tournament\Arrangement as SharedArrangement;

/**
 * Taekwondo on the SHARED championship base (App\Events\Support\Tournament)
 * — the parallel path of the 2026-09-27 migration. Used only while
 * `events.taekwondo_shared_base` is ON; the classes one folder up stay the live
 * path until the cut-over.
 */
class Arrangement extends SharedArrangement
{
    public function __construct(Advancement $advancement)
    {
        parent::__construct($advancement);
    }

    protected function langNamespace(): string
    {
        return 'event-taekwondo_tournament';
    }
}
