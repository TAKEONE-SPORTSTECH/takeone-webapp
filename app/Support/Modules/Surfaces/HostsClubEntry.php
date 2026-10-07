<?php

namespace App\Support\Modules\Surfaces;

use App\Events\Models\ClubEvent;
use App\Events\Support\ClubEntry\ClubEntryDesk;

/**
 * A module that takes CLUB entries for some of the platform's events — and so
 * answers the shared "Register your club" screens for them (owner, 2026-10-04:
 * club-hosted and federation-hosted events must look and flow identically).
 *
 * The screens live once, in the Events module (events::entry.club.page). What
 * stands behind them — how a club proves who it is, where a submission is
 * kept, what it costs — is the host's own back office, handed over as a
 * ClubEntryDesk. A module with no club entries does not implement this.
 */
interface HostsClubEntry
{
    /** The desk for this event, or null when this module does not take its club entries. */
    public function clubEntryDesk(ClubEvent $event): ?ClubEntryDesk;
}
