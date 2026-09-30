<?php

// The generic competition engine (App\Events\Championship), 2026-09-27.
// Official roles named in sport recipes (app/Events/Sports/*/recipe.php).

return [
    'official_referee' => 'Referee',
    'official_umpire' => 'Umpire',
    'official_line_judge' => 'Line judge',
    'official_service_judge' => 'Service judge',
    'official_marker' => 'Marker',
    'official_scorer' => 'Scorer',
    'official_assistant_referee' => 'Assistant referee',
    'official_fourth_official' => 'Fourth official',
    'official_timekeeper' => 'Timekeeper',
    'official_technical_officer' => 'Technical officer',
    'official_goal_judge' => 'Goal judge',
    'official_admin' => 'Match admin',
    'official_judge' => 'Judge',
    'official_mat_chairman' => 'Mat chairman',
    'official_arbiter' => 'Arbiter',
    'official_starter' => 'Starter',
    'official_commissaire' => 'Commissaire',
    'official_technical_official' => 'Technical official',
    'official_stroke_judge' => 'Stroke judge',

    // Result rules (ResultRules, Heats).
    'result_sets_required' => 'Enter the score of each set.',
    'result_too_many_sets' => '{1} This is played over 1 set.|[2,*] This is played best of :n sets.',
    'result_set_invalid' => 'Set :n is not a finished set by this sport’s rules.',
    'result_after_decided' => 'The match was already decided before the last set.',
    'result_not_decided' => '{1} Nobody has won the set it takes yet.|[2,*] Nobody has won the :n sets it takes yet.',
    'result_score_required' => 'Enter both scores.',
    'result_needs_decider' => 'A tie here must be decided — enter the penalties (or who won the decider).',
    'result_value_required' => 'Enter the result.',
];
