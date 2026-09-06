<?php

namespace App\EventLab;

use App\Support\Modules\AbstractModule;

/**
 * The event sandbox.
 *
 * A place to build the next version of how a competition takes entries, while
 * the competition that is actually running keeps using the code it runs on
 * today. Nothing in `app/Events/` is edited to make this work, and nothing here
 * is reachable from there — so a mistake in this folder cannot reach a real
 * event, a real registration or a real member.
 *
 * Three things are being tried, all of them answers to what actually goes wrong
 * on the day:
 *
 *  - **An entrant is not an account.** People register twice, mistype their own
 *    names, and enter events they never turn up to. Making each of those a
 *    platform member on the spot means the mess is permanent. Here an entrant is
 *    a row on a start list; only after the competition, and only for the people
 *    who really competed, does anybody become a member.
 *  - **A club need not be on the platform yet.** A club shows up with a team and
 *    no account. It is recorded as a name, and it can become a real club later —
 *    or never, without breaking anything.
 *  - **One event can hold several competitions.** Gi and No-Gi are two things to
 *    enter within one event, each with its own fee, plus a penalty for entering
 *    late. Not two events with two posters and two rosters.
 *
 * It contributes to no shell: this is a workbench, not a product surface, and it
 * deliberately puts nothing in anybody's sidebar. It is reached only at
 * `/testcode`, and only by a super-admin.
 */
class EventLab extends AbstractModule
{
    public function key(): string
    {
        return 'eventlab';
    }
}
