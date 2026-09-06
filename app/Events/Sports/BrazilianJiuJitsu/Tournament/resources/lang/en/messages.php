<?php

/*
|--------------------------------------------------------------------------
| Brazilian Jiu-Jitsu Tournament — strings for THIS event type
|--------------------------------------------------------------------------
| Namespaced to the package: event-bjj_tournament::messages.*
|
| A string every BJJ event shares (the sport's name, its belts, its officiating
| titles) belongs one level up, in the sport folder's own lang files.
*/

return [

    'type_label' => 'Jiu-Jitsu Championship',

    // ── Enrolment gate ───────────────────────────────────────────────────
    'gate_no_profile' => 'Add your gender and date of birth to your profile so we can place you in a weight class.',
    'gate_no_weight' => 'Add your current weight to your health profile so we can place you in a weight class.',
    'gate_no_division' => 'Your weight class isn’t one of this championship’s divisions, so you’re not eligible to compete in this event.',
    'gate_no_division_spectator' => 'Your weight class isn’t one of this championship’s divisions, so you can’t compete — but you’re welcome to attend as a spectator.',

    // ── Roster ───────────────────────────────────────────────────────────
    'roster_registered' => 'Registered',
    'roster_unclassified' => 'Unclassified',

    // ── The draw ─────────────────────────────────────────────────────────
    'action_generate_draw' => 'Generate draw',
    'action_arrange_draw' => 'Arrange draw',
    'action_clear_draw' => 'Clear draw',
    'action_end_next_bout' => 'End next match',
    'draw_generated' => 'Provisional draw generated',
    'draw_final' => 'The championship has started — the draw is final and can’t be regenerated.',
    'draw_arranged' => 'Draw updated 🥋',
    'draw_cleared' => 'Draw emptied — every entrant is back on the list',
    'day_mats' => 'Day :day: :count mats',
    'ended_locked' => 'This championship has ended — its results are final.',
    'end_next_bout_none' => 'No match is ready to be ended — every queued match is still waiting on a feeder.',
    'end_next_bout_done' => ':winner wins on :court. The screens on that mat have been told.',

    // ── Notifications ────────────────────────────────────────────────────
    'notify_weigh_in_title' => 'Weigh-in tomorrow — :title',
    'notify_weigh_in_body' => 'Weigh-in opens at :time at :place. Bring your ID.',
    'notify_draw_title' => 'The draw is out — :title',
    'notify_draw_body' => 'Your division has been drawn. Open the event to see your opponent, mat and match number.',
    'call_mat_tbc' => 'your mat',
    'call_opponent_tbc' => 'TBC',
    'call_warmup_title' => 'Warm up — you’re on :mat soon',
    'call_warmup_body' => ':ahead matches ahead of you · roughly :minutes min',
    'call_room_title' => 'Report to the call room — :mat',
    'call_room_body' => ':mat · match :bout · vs :opponent',
    'result_win_title' => 'You won 🎉',
    'result_loss_title' => 'Match finished',
    'result_body_next' => 'Next: :round on :mat, roughly :minutes min',
    'result_body_done' => 'That was your last match of the day.',
];
