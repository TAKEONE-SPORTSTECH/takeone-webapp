<?php

/*
| Sparring — the club's own scoreboard for training.
|
| Strings this package alone uses. Anything a screen shows during a BOUT comes
| from the sport's own scoreboard (event-karate_tournament::, and Taekwondo's),
| because a session borrows that console whole and must not read differently
| from a competition on the same mat.
*/

return [

    'label' => 'Sparring',
    'session_title' => 'Sparring · :date',
    'mat_n' => 'Mat :n',

    // The club-admin launcher.
    'launch_title' => 'Sparring',
    'launch_eyebrow' => 'Training scoreboard',
    'launch_lead' => 'Put two people on a mat and the hall sees the score. No draw, no entries — open it now, close it when training ends.',
    'launch_sport' => 'Which sport',
    'launch_mats' => 'How many mats',
    'launch_minutes' => 'Bout length',
    'launch_minutes_n' => ':n min',
    'launch_start' => 'Start sparring',
    'launch_resume' => 'Open today’s session',
    'launch_running' => 'Running now',
    'launch_open_since' => 'Open since :time',
    'launch_no_sport' => 'This club has no sport with a mat scoreboard yet.',
    'launch_past' => 'Earlier sessions',
    'launch_past_none' => 'No sessions yet.',
    'launch_bouts_n' => ':n bouts',

    // The console.
    'console_mats' => 'Mats',
    'console_mat_count' => 'Running :n',
    'console_floor' => 'Who’s here',
    'console_floor_hint' => 'Tap two to pair them up',
    'console_add_people' => 'Add people',
    'console_queue' => 'Queue',
    'console_queue_empty' => 'Nothing queued on this mat.',
    'console_fought' => 'Fought',
    'console_fought_empty' => 'No bouts yet.',
    'console_open_table' => 'Scoring table',
    'console_table_needs_bout' => 'Queue a bout on this mat to open its scoring table.',
    'console_screens' => 'Hall screens',
    'console_pick_red' => 'Red corner',
    'console_pick_blue' => 'Blue corner',
    'console_pair_hint' => 'Pick two people from the floor',
    'console_queue_it' => 'Queue this bout',
    'console_clear_pick' => 'Clear',
    'console_bouts_today' => ':n today',
    'console_end_session' => 'End session',
    'console_end_confirm' => 'End this sparring session? The scoreboard stops and the session is archived.',
    'console_closed' => 'This session has ended.',
    'console_search_members' => 'Search members',
    'console_no_members' => 'No members found.',
    'console_add_selected' => 'Add :n to the floor',
    'console_mats_label' => 'Mats running',

    // Outcomes of an action.
    'entrants_added' => 'Added to the floor.',
    'entrant_removed' => 'Taken off the floor.',
    'entrant_queued' => 'They are in a bout that has not been fought yet.',
    'bout_queued' => 'Queued.',
    'bout_removed' => 'Removed from the queue.',
    'bout_fought' => 'That bout has already been fought.',
    'mats_saved' => 'Mats updated.',
    'session_ended' => 'Session ended.',
    'session_closed' => 'This session has ended.',
    'same_person' => 'Pick two different people.',
    'unknown_mat' => 'That is not a mat this session runs.',
    'unknown_entrant' => 'Both corners must be on the floor first.',

    // Actions, as the action list names them.
    'action_add_entrants' => 'Add people',
    'action_remove_entrant' => 'Remove person',
    'action_queue_bout' => 'Queue a bout',
    'action_unqueue_bout' => 'Remove a bout',
    'action_set_mats' => 'Set mats',
    'action_close' => 'End session',
];
