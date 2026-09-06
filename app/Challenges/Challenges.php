<?php

namespace App\Challenges;

use App\Support\Modules\AbstractModule;

/**
 * Member-to-member competition outside any event: challenges, their stakes and
 * win conditions, duels, witnesses and the media that settles them.
 *
 * Its surface is /me/challenge — the hub, the create form, a challenge, a duel
 * and the history — declared in routes-member.php under the same /me prefix the
 * member module uses, because the prefix is a shell, not an owner.
 *
 * Contributes nothing to the club admin workspace — a challenge is between
 * members, not something a club runs. It therefore implements no surface
 * interface, which is the module system's way of saying "this vertical has
 * nothing to show that shell" out loud.
 *
 * Two things sit outside the folder, deliberately:
 *   • The `duels:expire-pending` schedule entry stays in routes/console.php. It
 *     names the command by SIGNATURE, not by class, so the boundary holds and
 *     the command itself ships here beside the domain it prunes.
 *   • PersonalMobileController still carries five unrouted DUMMY design-preview
 *     methods (`challenge`, `challengeShow`, `challengeCreate`,
 *     `challengeHistory`, `duelShow`) that predate the real controller and
 *     already pointed at views that do not exist. They were left exactly as
 *     they were, retargeted to this namespace, rather than deleted as part of a
 *     move.
 */
class Challenges extends AbstractModule
{
    public function key(): string
    {
        return 'challenges';
    }
}
