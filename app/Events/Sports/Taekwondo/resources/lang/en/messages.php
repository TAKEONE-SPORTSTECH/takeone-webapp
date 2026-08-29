<?php

/*
|--------------------------------------------------------------------------
| Taekwondo — sport-wide strings
|--------------------------------------------------------------------------
| Shared by EVERY Taekwondo event type (tournament, belt test, poomsae …).
| Namespaced to the sport: sport-taekwondo::messages.*
|
| A string only one event type uses belongs in that type's own folder
| (<Type>/resources/lang), not here.
*/

return [

    'sport_label' => 'Taekwondo',
    'division_label' => 'Weight category',
    'weigh_in' => 'Weigh-in',
    'mat' => 'Mat',

    // ── Officiating panel (WT kyorugi) ───────────────────────────────────
    'official_center_referee'   => 'Center Referee',
    'official_corner_judge'     => 'Corner Judge :n',
    'official_review_jury'      => 'Review Jury',
    'official_court_supervisor' => 'Court Supervisor',
    'official_table_recorder'   => 'Table Recorder',
    'official_timekeeper'       => 'Timekeeper',
    'official_center_referee_hint' => 'Runs the bout in the middle of the court.',
    'official_corner_judge_hint' => 'Scores from a corner of the court.',
    'official_review_jury_hint' => 'Rules on video review requests.',
    'official_court_supervisor_hint' => 'Oversees the court and its officials.',
    'official_table_recorder_hint' => 'Keeps the official record at the table.',
    'official_timekeeper_hint' => 'Keeps the clock: round time, stoppages and the bell.',

    // Kyorugi scoring vocabulary — the technique each value stands for.
    'score_turning_head' => 'Turning head kick',
    'score_turning_body' => 'Turning body kick',
    'score_head_kick' => 'Head kick',
    'score_body_kick' => 'Body kick',
    'score_punch' => 'Punch',
    'corner_hong' => 'HONG',
    'corner_chung' => 'CHUNG',
];
