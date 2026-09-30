<?php

/*
| Chess — FIDE Swiss system: 1 point a win, ½ a draw.
|
| A recipe for the generic competition engine (App\Events\Championship): the
| building blocks this sport uses and its official numbers, read by
| App\Events\Support\Competition\SportRecipes. Official conventions as of
| 2026-09-27 — the owner updates them here when a federation or a club runs
| differently. See SportRecipe for every key.
*/

return [
    'entrants' => ['individual', 'team'],
    'team_size' => [
        'min' => 4,
        'max' => 5,
    ],
    'criteria' => ['age', 'rating'],
    'result' => 'score',
    'formats' => ['swiss', 'round_robin', 'knockout'],
    'draws' => true,
    'points' => [
        'win' => 1,
        'draw' => 0.5,
        'loss' => 0,
    ],
    'tie_breakers' => ['points', 'buchholz', 'head_to_head', 'name'],
    'venue' => 'board',
    'officials' => ['arbiter'],
    'age_groups' => [
        'U8' => [
            'max' => 7,
        ],
        'U10' => [
            'max' => 9,
        ],
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
        'Seniors 50+' => [
            'min' => 50,
        ],
    ],
];
