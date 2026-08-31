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
 */
class Trainers extends AbstractModule
{
    public function key(): string
    {
        return 'trainers';
    }
}
