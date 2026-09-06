<?php

/*
|--------------------------------------------------------------------------
| Karate Tournament package — strings
|--------------------------------------------------------------------------
| Owned by this package and namespaced to it: event-karate_tournament::messages.*
| Delete the package folder and these go with it.
*/

return [

    'type_label' => 'Karate Championship',

    // Enrolment gate
    'gate_no_profile' => 'Add your gender and date of birth to your profile so we can place you in a weight category.',
    'gate_no_weight' => 'Add your current weight to your health profile so we can place you in a weight category.',
    'gate_no_division' => 'Your weight category isn’t one of this championship’s divisions, so you’re not eligible to compete in this event.',
    'gate_no_division_spectator' => 'Your weight category isn’t one of this championship’s divisions, so you can’t compete — but you’re welcome to attend as a spectator.',

    // Roster
    'roster_registered' => 'Registered',
    'roster_unclassified' => 'Unclassified',

    // Draw + run day
    'action_generate_draw' => 'Generate draw',
    'draw_generated' => 'Provisional draw generated',
    'draw_final' => 'The championship has started — the draw is final and can’t be regenerated.',
    'action_arrange_draw' => 'Arrange draw',
    'action_clear_draw' => 'Empty draw',
    'draw_arranged' => 'Draw updated 🥋',
    'draw_cleared' => 'Draw emptied — every entrant is back on the list',
    'day_mats' => 'Day :day: :count mats',
    'ended_locked' => 'This championship has ended — its results are final.',

    // Notifications
    'notify_weigh_in_title' => 'Weigh-in tomorrow — :title',
    'notify_weigh_in_body' => 'Official weigh-in at :time, :place. You must make your division’s limit to compete.',
    'notify_draw_title' => 'The draw is out — :title',
    'notify_draw_body' => 'Your opponent, mat and bout number are now published.',

    // Run day — calls to the mat
    'call_mat_tbc' => 'your mat',
    'call_opponent_tbc' => 'TBC',
    'call_warmup_title' => 'Warm up — you\'re on :mat soon',
    'call_warmup_body' => ':ahead bouts ahead of you · roughly :minutes min',
    'call_room_title' => 'Report to the call room — :mat',
    'call_room_body' => ':mat · bout :bout · vs :opponent',
    'result_win_title' => 'You won 🎉',
    'result_loss_title' => 'Bout finished',
    'result_body_next' => 'Next: :round on :mat, roughly :minutes min',
    'result_body_done' => 'That was your last bout of the day.',

    // Next-up screen
    'next_up_title' => 'My next bout',
    'next_up_none' => 'Nothing scheduled for you right now.',
    'next_up_done' => 'You\'ve finished for the day.',
    'next_up_ahead' => 'bouts ahead',
    'next_up_you_are_next' => 'You\'re next — go to the mat',
    'next_up_estimate' => 'estimate, moves with the mat',
    'next_up_squad' => 'My squad',
    'next_up_opponent_tbc' => 'Awaiting opponent',
    'next_up_finished' => 'Finished',

    // Venue board
    'board_title' => 'Mat board — :event',
    'board_heading' => 'Running order',
    'board_on_deck' => 'On deck',
    'board_idle' => 'No bouts in play',
    'board_live' => 'Live',
    'board_stale' => 'Reconnecting',
    // Rehearsal only — never offered in production (Tournament::availableActions)
    'action_end_next_bout' => 'End the next bout (test)',
    'end_next_bout_none' => 'No bout is ready to be ended — every queued bout is still waiting on a feeder.',
    'end_next_bout_done' => ':winner wins on :court. The screens on that mat have been told.',
];
