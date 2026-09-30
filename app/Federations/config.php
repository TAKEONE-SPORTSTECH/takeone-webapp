<?php

return [

    /*
    | The federation dashboard (app/Federations). New and still being shaped,
    | so it is OFF unless switched on: with it off every federation route
    | answers 404, exactly as if the module were not there.
    */
    'enabled' => (bool) env('FEDERATIONS_ENABLED', false),

    /*
    | Clubs page management — edit, unlink, and deciding pending applications
    | (approve as new / link to an existing club / refuse). OFF by default.
    */
    'club_management' => (bool) env('FEDERATION_CLUB_MANAGEMENT', false),

    /*
    | The Draw tab drawn by the shared <x-competition-brackets> board instead
    | of the prototype's own grid (which stays as the fallback). OFF by default.
    */
    'draw_board' => (bool) env('FEDERATION_DRAW_BOARD', false),

    /*
    | Registrations tab → "Enroll athlete" (staff enter one person by hand). OFF by default.
    */
    'manual_enrolment' => (bool) env('FEDERATION_MANUAL_ENROLMENT', false),

    /*
     * The club invitation in the EVENT PAGE's design (2026-09-28): the same
     * cover with its language carousel, band, quick facts and section cards as
     * /me/events/{uuid}, with the invitation's own flow inside it. Same
     * address, same endpoints, same server logic — only the page is new.
     * OFF by default: the prototype page keeps serving until this is switched
     * on, and switching it off again brings the prototype straight back.
     */
    'invite_v2' => (bool) env('FEDERATION_INVITE_V2', false),

    /*
     * A federation's competition RUNS as a platform event (2026-09-28): the
     * same console, draw, hall screens, scoreboard, next-up and cameras as a
     * club's event, hosted by the federation itself (never a club). Saving a
     * federation event keeps its platform event in step
     * (FederationPlatformEvent); clubs' entries flow into it from the
     * invitation. OFF by default — the dashboard keeps working either way.
     */
    'platform_events' => (bool) env('FEDERATION_PLATFORM_EVENTS', false),

    /*
    | TakeOne's own account — where a federation pays its subscription by
    | bank transfer (there is no card gateway). Shown on the dashboard's pay
    | screen; while the IBAN is unset the screen says so instead of showing a
    | placeholder number.
    */
    'takeone_bank' => [
        'beneficiary' => env('TAKEONE_BANK_BENEFICIARY', 'TakeOne Sports Technologies W.L.L.'),
        'bank' => env('TAKEONE_BANK_NAME'),
        'iban' => env('TAKEONE_BANK_IBAN'),
        'swift' => env('TAKEONE_BANK_SWIFT'),
    ],

];
