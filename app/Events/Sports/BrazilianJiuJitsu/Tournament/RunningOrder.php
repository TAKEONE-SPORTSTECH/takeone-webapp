<?php

namespace App\Events\Sports\BrazilianJiuJitsu\Tournament;

use App\Events\Support\Tournament\RunningOrder as SharedRunningOrder;

/**
 * The jiu-jitsu running order — mat scheduling, what is on now and what is
 * next. Entirely shared behaviour; this class exists so the package owns its
 * own type, and so anything sport-specific has somewhere to land later.
 */
class RunningOrder extends SharedRunningOrder
{
}
