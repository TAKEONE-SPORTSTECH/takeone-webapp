<?php

/*
|--------------------------------------------------------------------------
| Taekwondo Tournament package — strings
|--------------------------------------------------------------------------
| Owned by this package and namespaced to it: event-taekwondo_tournament::messages.*
| Delete the package folder and these go with it.
*/

return [

    'type_label' => 'Taekwondo Championship',

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

    // Court display — the hall screen on the wall. Distinct from
    // the 'board_*' venue board above: this is signage seen from ten metres.
    'court_title' => 'Upcoming Matches',
    'court_court' => 'Court',
    'court_match' => 'Match',
    'court_get_ready' => 'Get Ready',
    'court_tbd' => 'To be decided',
    'court_idle_title' => 'Mat clear',
    'court_idle_sub' => 'No bouts queued',
    'court_reconnecting' => 'Reconnecting',
    'sb_page_title' => 'Scoreboard · :court — :event',
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
    'claim_surface' => 'What is this screen for?',
    'claim_surface_follow' => 'Follow the mat',
    'claim_surface_follow_hint' => 'The running order between bouts, the match while one is being fought. For the board over the mat.',
    'claim_surface_queue' => 'Upcoming matches, always',
    'claim_surface_queue_hint' => 'Never turns into a scoreboard. For the call room, a corridor, the entrance.',
    'claim_surface_bout' => 'Scoreboard, always',
    'claim_surface_bout_hint' => 'The introduction and the score, with its own idle card between matches.',
    'claim_surface_control' => 'Score control',
    'claim_surface_control_hint' => 'The scoring table for this mat. Only for a device that stays with an official — it can record results without signing in.',

    // "Make this screen a display" — the address a hall television is pointed at
    'screen_new_title' => 'Make this screen a display',
    'screen_new_working' => 'Setting this screen up',
    'screen_new_hint' => 'In a moment this screen will show a code to scan.',
    'screen_new_failed' => 'Could not reach the server',
    'screen_new_retry' => 'Try again',
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

    /* ── The scoring table ────────────────────────────────────────────────
       Refusals the operator has to read and act on, plus the control's own
       chrome. A WT match is a series, so two of these have no Karate
       equivalent: a round can end level, and a match is not over until the
       rounds say it is. */
    'commit_no_bout' => 'No match is loaded on this mat.',
    'commit_undecided' => 'This match is not decided yet — close the current round first.',
    'round_level' => 'This round is level. The table decides it on superiority: award it to a corner.',
    'round_not_running' => 'No round is being fought — the mat is in the break before the next one.',
    'match_over' => 'This match is already decided. Record the result, or reset it.',

    'ctl_title' => 'Scoreboard Control',
    'ctl_court' => 'Court',
    'ctl_match' => 'Match',
    'ctl_class' => 'Class',
    'ctl_category' => 'Category',
    'ctl_queue' => 'Match Queue',
    'ctl_queue_hint' => 'Load sends the match to the scoreboard and resets the score',
    'ctl_load' => 'Load to Scoreboard',
    'ctl_start_next' => 'Start Next Match',
    'ctl_end_upload' => 'End Match & Upload',
    'ctl_reset_match' => 'Reset Match',
    'ctl_reset_round' => 'Reset Round',
    // Takes the match OFF the mat and puts the queue back on the wall. NOT a
    // round reset — the two sat side by side under the same label.
    'ctl_clear_mat' => 'Clear Mat',
    'ctl_undo' => 'Undo',
    'ctl_undo_none' => 'Nothing to undo yet',
    'ctl_undo_title' => 'Undo an Action',
    'ctl_round' => 'Round',
    'ctl_rest' => 'Rest',
    'ctl_golden' => 'Golden',
    'ctl_award_round' => 'Award round',
    'ctl_start' => 'Start',
    'ctl_pause' => 'Pause',
    'ctl_gamjeom' => 'Gam-jeom',
    'ctl_gamjeom_hint' => '+1 to opponent',
    'ctl_punch' => 'Punch',
    'ctl_body' => 'Body',
    'ctl_head' => 'Head',
    'ctl_turn_body' => 'Turn Body',
    'ctl_turn_head' => 'Turn Head',
    'ctl_screens' => ':count screen(s) on this mat',
    'ctl_no_screens' => 'No screen paired to this mat yet',
    'ctl_vs' => 'vs',
    'ctl_waiting' => 'Waiting on a feeder',
    'mat_idle' => 'Mat clear',
    'mat_idle_sub' => 'Waiting for the next match',

    /* ── The mat screen ───────────────────────────────────────────────────
       The introduction's chips, and the words the scoreboard puts under the
       clock. Taekwondo calls its mat a COURT and its commands in Korean, so
       these are not Karate's strings with the namespace changed. */
    'vs_referee' => 'Referee',
    'sb_court' => 'Court',
    'sb_start' => 'Shi-jak',
    'sb_stop' => 'Kal-yeo',
    'sb_time' => 'Time',
    'sb_rest' => 'Rest',
    'sb_golden' => 'Golden Round',
    'sb_winner' => 'Winner',
    'sb_round' => 'Round',
    'sb_gamjeom' => 'Gam-jeom',
    'sb_hong' => 'Hong · Red',
    'sb_chung' => 'Chung · Blue',
    'ctl_winner_back' => 'Not yet',
    'ctl_won_rounds' => 'Wins :a – :b on rounds',
    'ctl_won_pun' => 'Wins by PUN · 5 gam-jeom',
    'ctl_won_golden' => 'Wins on the golden point',
    'ctl_next_is' => 'Next up · :aka vs :ao',
    'ctl_record_next' => 'Record result · load next match',
    'ctl_record_last' => 'Record result · last on this mat',
    'ctl_refresh_vs' => 'Refresh VS screen',
    'ctl_photo_add' => 'Add photo',
    'ctl_photo_change' => 'Change photo',
    'ctl_crop_title' => 'Frame the photo',
    'ctl_crest_title' => 'Frame the crest',
    'ctl_crest_add' => 'Add the club crest',
    'ctl_crest_change' => 'Change the club crest',
    'ctl_crest_saved' => 'Crest saved · now on every screen',
    'ctl_crop_zoom' => 'Zoom',
    'ctl_crop_hint' => 'Drag to move · scroll or ± to zoom',
    'ctl_crop_cancel' => 'Cancel',
    'ctl_crop_save' => 'Use photo',
    'ctl_photo_no_entry' => 'This corner has no entry to attach a photo to.',
    'ctl_photo_big' => 'That image is too large. Pick one under 25 MB.',
    'ctl_photo_bad' => 'That file is not a usable image. Use a JPEG, PNG or WebP.',
    'ctl_photo_saved' => 'Photo saved · now on every screen',
    'ctl_refreshed' => 'VS screen refreshed',
];
