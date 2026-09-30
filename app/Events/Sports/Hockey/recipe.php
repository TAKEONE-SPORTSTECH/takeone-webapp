<?php

/*
| Hockey — FIH: 3/1/0 points; a shoot-out decides a knockout tie.
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
        'min' => 11,
        'max' => 18,
    ],
    'criteria' => ['age', 'gender'],
    'result' => 'score',
    'formats' => ['groups_knockout', 'round_robin', 'knockout'],
    'draws' => true,
    'scoring' => [
        'penalties' => true,
    ],
    'points' => [
        'win' => 3,
        'draw' => 1,
        'loss' => 0,
    ],
    'tie_breakers' => ['points', 'difference', 'scored', 'head_to_head', 'name'],
    'medals' => 'third_place_match',
    'venue' => 'pitch',
    'officials' => ['umpire', 'technical_officer'],
    'age_groups' => [
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
