<?php

/*
| Tennis — ITF conventions. Singles or doubles; best of three tie-break sets.
|
| A recipe for the generic competition engine (App\Events\Championship): the
| building blocks this sport uses and its official numbers, read by
| App\Events\Support\Competition\SportRecipes. Official conventions as of
| 2026-09-27 — the owner updates them here when a federation or a club runs
| differently. See SportRecipe for every key.
*/

return [
    'entrants' => ['individual', 'pair'],
    'criteria' => ['age', 'gender', 'level'],
    'result' => 'sets',
    'formats' => ['knockout', 'groups_knockout', 'round_robin'],
    'scoring' => [
        'best_of' => 3,
        'games_to' => 6,
        'win_by' => 2,
        'tiebreak_at' => 6,
        'tiebreak_to' => 7,
        'final_set' => 'tiebreak',
    ],
    'tie_breakers' => ['points', 'sets', 'games', 'head_to_head', 'name'],
    'medals' => 'both_sf_losers',
    'venue' => 'court',
    'officials' => ['umpire', 'line_judge', 'referee'],
    'age_groups' => [
        'U12' => [
            'max' => 11,
        ],
        'U14' => [
            'max' => 13,
        ],
        'U16' => [
            'max' => 15,
        ],
        'U18' => [
            'max' => 17,
        ],
        'Open' => [],
        'Seniors 35+' => [
            'min' => 35,
        ],
    ],
];
