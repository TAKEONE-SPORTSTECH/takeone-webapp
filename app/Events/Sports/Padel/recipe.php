<?php

/*
| Padel — FIP conventions. Pairs; levels 1 (beginner) to 7 (pro), the common club scale.
|
| A recipe for the generic competition engine (App\Events\Championship): the
| building blocks this sport uses and its official numbers, read by
| App\Events\Support\Competition\SportRecipes. Official conventions as of
| 2026-09-27 — the owner updates them here when a federation or a club runs
| differently. See SportRecipe for every key.
*/

return [
    'entrants' => ['pair'],
    'criteria' => ['gender', 'level', 'age'],
    'result' => 'sets',
    'formats' => ['groups_knockout', 'knockout', 'round_robin'],
    'scoring' => [
        'best_of' => 3,
        'games_to' => 6,
        'win_by' => 2,
        'tiebreak_at' => 6,
        'tiebreak_to' => 7,
        'golden_point' => true,
        'final_set' => 'super_tiebreak',
        'super_tiebreak_to' => 10,
    ],
    'tie_breakers' => ['points', 'sets', 'games', 'head_to_head', 'name'],
    'medals' => 'both_sf_losers',
    'venue' => 'court',
    'officials' => ['referee'],
    'levels' => ['1', '2', '3', '4', '5', '6', '7'],
    'age_groups' => [
        'Open' => [],
        'U14' => [
            'max' => 13,
        ],
        'U18' => [
            'max' => 17,
        ],
        'Veterans 45+' => [
            'min' => 45,
        ],
    ],
];
