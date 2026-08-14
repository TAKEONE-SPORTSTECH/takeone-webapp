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

    // Court display — the hall screen driven by the Raspberry Pi. Distinct from
    // the 'board_*' venue board above: this is signage seen from ten metres.
    'court_title' => 'Upcoming Matches',
    'court_court' => 'Court',
    'court_match' => 'Match',
    'court_get_ready' => 'Get Ready',
    'court_tbd' => 'To be decided',
    'court_idle_title' => 'Mat clear',
    'court_idle_sub' => 'No bouts queued',
    'court_reconnecting' => 'Reconnecting',
    'court_page_title' => 'Court :court — :event',
    'round_final' => 'Final',
    'round_semifinal' => 'Semifinal',
    'round_quarterfinal' => 'Quarterfinal',
    'round_bronze' => 'Bronze',
    'round_of' => 'Round of :n',

    // Court display — pairing a new screen
    'pair_title' => 'Pair this screen',
    'pair_eyebrow' => 'Court display',
    'pair_code_label' => 'Pairing code',
    'pair_hint' => 'Scan this code with the TAKEONE app, then choose which mat this screen shows.',
    'pair_waiting' => 'Waiting to be paired',
    'pair_done' => 'This screen now shows :court — :event.',
    'claim_title' => 'Which mat is this screen on?',
    'claim_sub' => 'Screen :code is waiting to be told what to show.',
    'claim_no_events' => 'You do not manage any events running right now.',
    'claim_no_courts' => 'This event has no mats yet — build the draw first.',
    'claim_submit' => 'Show this mat on the screen',
    'claimed_title' => ':court is on the screen',
    'claimed_sub' => 'Showing the running order for :event.',
    'claimed_court' => 'Mat',
    'claimed_screen' => 'Screen',
    'claimed_note' => 'The screen updates itself as results are entered. You can reassign or revoke it at any time.',

    // Pairing from inside the event console
    'pair_unknown' => 'That code does not match a screen waiting to be paired. Check the code on the screen — a code is spent once it has been used.',
    // Rehearsal only — never offered in production (Tournament::availableActions)
    'action_end_next_bout' => 'End the next bout (test)',
    'end_next_bout_none' => 'No bout is ready to be ended — every queued bout is still waiting on a feeder.',
    'end_next_bout_done' => ':winner wins on :court. The screens on that mat have been told.',
    'pair_revoked' => 'The screen has been unpaired. It is back to showing its pairing code.',

    // ── The scoring table and the mat screen ─────────────────────────────────
    'sb_tatami' => 'Tatami',
    'sb_hajime' => 'Hajime',
    'sb_yame' => 'Yame',
    'sb_time' => 'Time',
    'sb_winner' => 'Winner',
    'ctl_title' => 'Scoring table',
    'ctl_mat' => 'Mat',
    'ctl_queue' => 'Up next on this mat',
    'ctl_load' => 'Load',
    'ctl_no_queue' => 'Nothing left to run on this mat.',
    'ctl_start' => 'Hajime',
    'ctl_pause' => 'Yame',
    'ctl_finish' => 'End bout',
    'ctl_reset' => 'Reset',
    'ctl_clear' => 'Clear screen',
    'ctl_senshu' => 'Senshu',
    'ctl_penalty' => 'Penalty',
    'ctl_undo' => 'Undo',
    'ctl_screens' => ':count screen(s) on this mat',
    'ctl_no_screens' => 'No screen paired to this mat yet',
    'ctl_waiting' => 'No bout loaded',
    'ctl_keys' => 'AKA Q/W/E · A penalty · Z senshu   |   AO U/I/O · K penalty · M senshu   |   Space hajime/yame · R reset',
    'ctl_failed' => 'That did not reach the mat. Try again.',
    'vs_referee' => 'Referee',
    'commit_no_bout' => 'There is no bout on this mat to record.',
    'commit_level' => 'The bout is level and neither athlete has senshu. Award senshu or a point before recording the result.',
    'ctl_commit' => 'Record result · next bout',
    'ctl_commit_hint' => 'Writes the result, advances the bracket, and calls the next bout on this mat.',
    'ctl_waiting_feeder' => 'Waiting on an earlier bout — both competitors are not known yet.',
    /* ── Pairing: what the screen is FOR ─────────────────────────────── */
    'claim_surface' => 'What is this screen for?',
    'claim_surface_follow' => 'Follow the mat',
    'claim_surface_follow_hint' => 'The running order between bouts, the bout while one is being fought. For the board over the mat.',
    'claim_surface_queue' => 'Upcoming boutes, always',
    'claim_surface_queue_hint' => 'Never turns into a scoreboard. For the call room, a corridor, the entrance.',
    'claim_surface_bout' => 'Scoreboard, always',
    'claim_surface_bout_hint' => 'The introduction and the score, with its own idle card between boutes.',
    'claim_surface_control' => 'Score control',
    'claim_surface_control_hint' => 'The scoring table for this mat. Only for a device that stays with an official — it can record results without signing in.',
];
