<?php

namespace App\Challenges;

use App\Support\Modules\AbstractModule;

/**
 * Member-to-member competition outside any event: challenges, their stakes and
 * win conditions, duels, witnesses and the media that settles them.
 *
 * Contributes nothing to the club admin workspace — a challenge is between
 * members, not something a club runs. It therefore implements no surface
 * interface, which is the module system's way of saying "this vertical has
 * nothing to show that shell" out loud.
 */
class Challenges extends AbstractModule
{
    public function key(): string
    {
        return 'challenges';
    }
}
