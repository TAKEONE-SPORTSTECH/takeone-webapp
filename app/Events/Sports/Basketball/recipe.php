<?php

/*
| Basketball — FIBA: no draws (overtime); 2 points a win, 1 a loss.
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
        'min' => 5,
        'max' => 12,
    ],
    'criteria' => ['age', 'gender'],
    'result' => 'score',
    'formats' => ['groups_knockout', 'round_robin', 'knockout'],
    'points' => [
        'win' => 2,
        'draw' => 0,
        'loss' => 1,
    ],
    'tie_breakers' => ['points', 'head_to_head', 'difference', 'scored', 'name'],
    'medals' => 'third_place_match',
    'venue' => 'court',
    'officials' => ['referee', 'umpire', 'scorer', 'timekeeper'],
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
        'Senior' => [],
    ],
];
