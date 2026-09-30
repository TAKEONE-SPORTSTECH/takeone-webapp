<?php

namespace App\Events\Sports\Taekwondo\Tournament;

use App\Events\Support\Tournament\RunningOrder as SharedRunningOrder;

/**
 * The taekwondo running order — the shared one, unchanged.
 *
 * This file used to carry its own copy of App\Events\Support\Tournament\
 * RunningOrder, identical to it line for line apart from the namespace
 * (verified 2026-09-27 before replacing it). Kept as a class of its own because
 * the taekwondo scoreboard, hall screen and console type-hint THIS name.
 */
class RunningOrder extends SharedRunningOrder
{
}
