<?php

namespace App\Parties;

/**
 * ONE role vocabulary for every kind of party (PLAN.md §3.3). A module uses the
 * roles that make sense for it; it never invents a synonym for one here
 * ("Shared stays shared": two names for one thing drift).
 */
final class Roles
{
    // person → organisation
    public const OWNER = 'owner';           // share_bp says how much
    public const ADMIN = 'admin';           // runs it day to day
    public const STAFF = 'staff';           // meta.staff_type: instructor|secretary|operator|cleaner|other
    public const COACH = 'coach';
    public const MEMBER = 'member';
    public const PRESIDENT = 'president';   // a federation's head, for a recorded term
    public const OFFICER = 'officer';       // federation staff; meta.sports scopes it

    // party → event
    public const HOST = 'host';
    public const CO_HOST = 'co_host';
    public const SANCTIONED_BY = 'sanctioned_by';
    public const VENUE = 'venue';
    public const ORGANISER = 'organiser';
    public const OFFICIAL = 'official';     // meta.official_role: jury|weigh-in|payments|other

    // organisation → organisation
    public const MEMBER_CLUB = 'member_club';   // club → federation
    public const CHAIN_CLUB = 'chain_club';     // club → business
    public const SUB_BODY = 'sub_body';         // federation → parent federation

    /** @return list<string> */
    public static function all(): array
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }

    public static function exists(string $role): bool
    {
        return in_array($role, self::all(), true);
    }
}
