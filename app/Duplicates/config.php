<?php

/*
|--------------------------------------------------------------------------
| Duplicate guard and watch
|--------------------------------------------------------------------------
| Every switch is OFF by default (RULE #1). Turn one on in .env, then
| `php artisan config:cache` as www-data.
*/

return [

    /*
     | The watch: `duplicates:watch` runs hourly and records every likely
     | duplicate person, club and entry as a finding. Read-only apart from its
     | own table. DUPLICATE_WATCH=true
     */
    'watch' => (bool) env('DUPLICATE_WATCH', false),

    /*
     | Let the watch FIX what is certain — and only that:
     |   · a typed event club whose own federation application became a
     |     platform club → linked to it (fills empty fields only);
     |   · an unclaimed record (no password, no email) that is certainly an
     |     existing person — same phone AND the same name, or the same
     |     national ID — and whose birthdate and gender do not disagree →
     |     merged into them through AccountMerger, which keeps an undo ledger;
     |   · a spare entry row, when events.spare_entries is also on.
     | Two accounts that can both sign in are NEVER merged automatically.
     | DUPLICATE_AUTOFIX=true
     */
    'autofix' => (bool) env('DUPLICATE_AUTOFIX', false),

    /*
     | Who a fix is recorded as. A super-admin's email; blank = the oldest
     | super-admin account.
     */
    'actor_email' => env('DUPLICATE_ACTOR_EMAIL'),

    /* Most automatic merges in one run — a brake if a rule ever misfires. */
    'max_fixes_per_run' => (int) env('DUPLICATE_MAX_FIXES', 25),

    /*
     | Guards at the doors. Each one makes its door link to an existing record
     | on a CERTAIN key instead of creating a second one.
     */
    'guard' => [
        // Approving a federation club application links the copies of that
        // club already written down on the federation's platform events; and
        // "approve as a NEW club" is refused while a platform club has the same
        // email or phone, unless the manager confirms it is a second club.
        'federation_links' => (bool) env('DUPLICATE_GUARD_FEDERATION', false),
        // "Register the clubs that competed" links a typed club to the one
        // platform club holding the same name instead of creating another.
        'promotion' => (bool) env('DUPLICATE_GUARD_PROMOTION', false),
        // The club portal's "register a new club": also matches the club's
        // phone, and a same email/phone can no longer be waved past with
        // "we are a different club".
        'club_portal' => (bool) env('DUPLICATE_GUARD_CLUB_PORTAL', false),
        // Federation staff "Enroll athlete" / Excel import: the same name and
        // birthdate already in the federation is reused at the same club, and
        // refused (transfer instead) at another.
        'staff_enrol' => (bool) env('DUPLICATE_GUARD_STAFF_ENROL', false),
        // Officials: a new official with a phone matching an existing person
        // of the same name is that person; a club's named officials are reused
        // across that club's events.
        'officials' => (bool) env('DUPLICATE_GUARD_OFFICIALS', false),
    ],
];
