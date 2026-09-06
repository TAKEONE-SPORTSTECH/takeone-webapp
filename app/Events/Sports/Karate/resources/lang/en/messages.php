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
    'official_referee_hint' => 'Runs the bout on the mat: starts and stops it, and calls the score.',
    'official_judge_hint' => 'Sits at a corner and flags the points they see.',
    'official_match_supervisor_hint' => 'Watches the officials themselves and settles protests.',
    'official_tatami_manager_hint' => 'Keeps the mat running to schedule and calls the athletes up.',
    'official_scorekeeper_hint' => 'Records the score and the penalties on the sheet.',
    'official_timekeeper_hint' => 'Keeps the clock: bout time, stoppages and the final bell.',

    // Kumite scoring vocabulary — the words on the bout sheet.
    'score_ippon' => 'Ippon',
    'score_wazari' => 'Waza-ari',
    'score_yuko' => 'Yuko',
    'corner_aka' => 'AKA',
    'corner_ao' => 'AO',
];
