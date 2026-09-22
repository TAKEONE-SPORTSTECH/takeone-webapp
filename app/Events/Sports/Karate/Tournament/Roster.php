<?php

namespace App\Events\Sports\Karate\Tournament;

use App\Events\Support\Tournament\Roster as SharedRoster;

/**
 * How a Karate championship reads its entrant list — shared behaviour, this
 * package's wording.
 *
 * This was a full copy of the shared roster until 2026-09-15, and the copy had
 * gone stale in the way the *Shared Stays Shared* rule warns about: the shared
 * one gained the ENTRY's own id and photo, and the competitor's age, and these
 * two did not. Nothing announced that. It showed up as the verification desk
 * rendering no gates at all for the two sports actually in the field — an
 * official at a Karate event could open the entry list and sign for nothing,
 * because a gate is keyed on a `registration` the roster never sent.
 *
 * The two copies were byte-identical to the base apart from this namespace and
 * the lang prefix below, which is the definition of a class that was never
 * private. BJJ was pointed at the base first (it was not yet in the field);
 * these two followed on the owner's go-ahead.
 */
class Roster extends SharedRoster
{
    protected function langNamespace(): string
    {
        return 'event-karate_tournament';
    }
}
