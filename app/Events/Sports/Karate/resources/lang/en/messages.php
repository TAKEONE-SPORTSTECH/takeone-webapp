<?php

/*
|--------------------------------------------------------------------------
| Karate — sport-wide strings
|--------------------------------------------------------------------------
| Shared by EVERY Karate event type (tournament, belt test, kata …).
| Namespaced to the sport: sport-karate::messages.*
|
| A string only one event type uses belongs in that type's own folder
| (<Type>/resources/lang), not here.
*/

return [

    'sport_label' => 'Karate',
    'division_label' => 'Weight category',
    'weigh_in' => 'Weigh-in',
    'mat' => 'Mat',

    // ── Officiating panel (WKF kumite) ───────────────────────────────────
    // Titles carry both the English role and the Japanese term, because that is
    // how they appear on a WKF bout sheet.
    'official_referee'          => 'Referee (Shushin)',
    'official_judge'            => 'Judge :n (Fukushin)',
    'official_match_supervisor' => 'Match Supervisor (Kansa)',
    'official_tatami_manager'   => 'Tatami Manager',
    'official_scorekeeper'      => 'Scorekeeper',
    'official_timekeeper'       => 'Timekeeper',
];
