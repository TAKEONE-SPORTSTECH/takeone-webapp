<?php

/*
| Badminton — BWF: games to 21 (win by 2, capped at 30), best of three.
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
    'formats' => ['groups_knockout', 'knockout', 'round_robin'],
    'scoring' => [
        'best_of' => 3,
        'points_to' => 21,
        'win_by' => 2,
        'cap' => 30,
    ],
    'tie_breakers' => ['points', 'head_to_head', 'sets', 'games', 'name'],
    'medals' => 'both_sf_losers',
    'venue' => 'court',
    'officials' => ['umpire', 'service_judge', 'line_judge', 'referee'],
    'age_groups' => [
        'U13' => [
            'max' => 12,
        ],
        'U15' => [
            'max' => 14,
        ],
        'U17' => [
            'max' => 16,
        ],
        'U19' => [
            'max' => 18,
        ],
        'Open' => [],
        'Masters 35+' => [
            'min' => 35,
        ],
    ],
];
