<?php

/*
|--------------------------------------------------------------------------
| Brazilian Jiu-Jitsu — sport-wide strings
|--------------------------------------------------------------------------
| Shared by EVERY BJJ event type (tournament, belt grading, open mat …).
| Namespaced to the sport: sport-brazilianjiujitsu::messages.*
|
| A string only one event type uses belongs in that type's own folder
| (<Type>/resources/lang), not here.
*/

return [

    'sport_label' => 'Brazilian Jiu-Jitsu',
    'division_label' => 'Weight class',
    'weigh_in' => 'Weigh-in',
    'mat' => 'Mat',

    // ── Officiating (IBJJF) ──────────────────────────────────────────────
    // One referee on the mat; the rest of the panel sits at the table.
    'official_referee'       => 'Referee',
    'official_mat_organiser' => 'Mat Organiser',
    'official_scorekeeper'   => 'Scorekeeper',
    'official_timekeeper'    => 'Timekeeper',
    'official_referee_hint' => 'Runs the match on the mat: starts and stops it, and signals every score.',
    'official_mat_organiser_hint' => 'Keeps the mat running to schedule and calls the athletes up.',
    'official_scorekeeper_hint' => 'Enters points, advantages and penalties at the table.',
    'official_timekeeper_hint' => 'Keeps the clock: match time, stoppages and the final bell.',

    // Scoring vocabulary. A value alone does not name the action in jiu-jitsu —
    // two points is a takedown, a sweep or knee-on-belly — so these are the
    // neutral words, and the specific source travels beside them in the log.
    'score_four' => '4 points',
    'score_three' => '3 points',
    'score_two' => '2 points',

    // The corners. Never red: red belongs to penalties and disqualification on
    // every screen this sport draws.
    /* ⚠️ THE KEY IS THE SIDE, NOT THE COLOUR. `corner_blue` is side A, the
       LEFT corner, and it is RED since 2026-09-20; `corner_white` is side B,
       the RIGHT corner, and it is BLUE. The old names are what the mat's own
       columns and 1,100 recorded ledger rows are keyed by, so renaming them is
       a migration — registered in Documentation/HOUSE-CLEANING.md. Translate
       these as the colours below, never as 'blue' and 'white'. */
    'corner_blue' => 'RED',
    'corner_white' => 'BLUE',

    // The belt ladder, as an organiser picks a division.
    'belt_white' => 'White',
    'belt_blue' => 'Blue',
    'belt_purple' => 'Purple',
    'belt_brown' => 'Brown',
    'belt_black' => 'Black',

    // Grades on the sport's own belt ladder (beltLadder()), 2026-09-27.
    'grade_stripes' => '{0} No stripes|{1} 1 stripe|[2,*] :n stripes',
    'grade_degree' => '{1} 1st degree|{2} 2nd degree|{3} 3rd degree|[4,*] :nth degree',
];
