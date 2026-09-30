<?php

namespace App\Events\Sports\Taekwondo\Tournament\SharedBase;

use App\Events\Support\Tournament\Advancement as SharedAdvancement;

/**
 * Taekwondo on the SHARED championship base (App\Events\Support\Tournament)
 * — the parallel path of the 2026-09-27 migration. Used only while
 * `events.taekwondo_shared_base` is ON; the classes one folder up stay the live
 * path until the cut-over.
 */
class Advancement extends SharedAdvancement
{
    /**
     * None. The taekwondo package never recorded a reason beyond the score
     * (its console sends none), so the shared base is told there are no
     * others — exactly what the old copy did by not reading the field.
     */
    protected function winReasons(): array
    {
        return [];
    }
}
