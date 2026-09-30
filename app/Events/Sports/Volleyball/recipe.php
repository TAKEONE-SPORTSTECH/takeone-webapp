<?php

/*
| Volleyball — FIVB: sets to 25 (fifth to 15), win by 2, best of five.
|
| A recipe for the generic competition engine (App\Events\Championship): the
| building blocks this sport uses and its official numbers, read by
| App\Events\Support\Competition\SportRecipes. Official conventions as of
| 2026-09-27 — the owner updates them here when a federation or a club runs
| differently. See SportRecipe for every key.
*/

return [
    'entrants' => ['team'],
    'team_size' => [
        'min' => 6,
        'max' => 14,
    ],
    'criteria' => ['age', 'gender'],
    'result' => 'sets',
    'formats' => ['groups_knockout', 'round_robin', 'knockout'],
    'scoring' => [
        'best_of' => 5,
        'points_to' => 25,
        'win_by' => 2,
        'final_set_to' => 15,
    ],
    'points' => [
        'win' => 3,
        'draw' => 0,
        'loss' => 0,
    ],
    'tie_breakers' => ['points', 'sets', 'games', 'head_to_head', 'name'],
    'medals' => 'third_place_match',
    'venue' => 'court',
    'officials' => ['referee', 'scorer', 'line_judge'],
    'age_groups' => [
        'U16' => [
            'max' => 15,
        ],
        'U18' => [
            'max' => 17,
        ],
        'U20' => [
            'max' => 19,
        ],
        'Senior' => [],
    ],
];
