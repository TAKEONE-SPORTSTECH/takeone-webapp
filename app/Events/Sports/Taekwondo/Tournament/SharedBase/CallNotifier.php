<?php

namespace App\Events\Sports\Taekwondo\Tournament\SharedBase;

use App\Events\Sports\Taekwondo\Tournament\RunningOrder;
use App\Events\Support\Tournament\CallNotifier as SharedCallNotifier;

/**
 * Taekwondo on the SHARED championship base (App\Events\Support\Tournament)
 * — the parallel path of the 2026-09-27 migration. Used only while
 * `events.taekwondo_shared_base` is ON; the classes one folder up stay the live
 * path until the cut-over.
 */
class CallNotifier extends SharedCallNotifier
{
    public function __construct(RunningOrder $order)
    {
        parent::__construct($order);
    }

    protected function langNamespace(): string
    {
        return 'event-taekwondo_tournament';
    }
}
