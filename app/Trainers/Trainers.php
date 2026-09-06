<?php

namespace App\Trainers;

use App\Support\Modules\AbstractModule;

/**
 * Personal training: a trainer's public profile, their rates and availability,
 * the reviews members leave, and personal sessions booked outside a club's
 * timetable.
 *
 * Distinct from a club's instructors. An instructor is a role a person holds
 * INSIDE a club (App\Clubs); a personal trainer is a person offering their own
 * service on the platform, reachable whether or not any club employs them.
 * `users.is_personal_trainer` is the opt-in.
 *
 * Its surfaces are /t/{user} (public, the QR page), /trainer/{user} and the
 * instructor reviews at /instructor/{id}/reviews. The three do NOT share a
 * middleware stack — see routes.php, where each keeps the one it has always had.
 *
 * Reviews are the seam with the club: `InstructorReview` lives here because a
 * review is written by a member about a person, but it is read by the club's
 * instructor admin, and `App\Support\TrainerExperience` stays platform-level
 * for the same reason — both verticals need it, so neither owns it
 * (CLAUDE.md, "Shared Stays Shared, Private Stays Private").
 */
class Trainers extends AbstractModule
{
    public function key(): string
    {
        return 'trainers';
    }
}
