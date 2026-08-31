<?php

namespace App\Events\Sports\BrazilianJiuJitsu\Tournament;

use App\Events\Support\Tournament\CallNotifier as SharedCallNotifier;

/**
 * Calling jiu-jitsu competitors to the mat — shared behaviour, this package's
 * wording.
 */
class CallNotifier extends SharedCallNotifier
{
    public function __construct(RunningOrder $order)
    {
        parent::__construct($order);
    }

    protected function langNamespace(): string
    {
        return 'event-bjj_tournament';
    }
}
