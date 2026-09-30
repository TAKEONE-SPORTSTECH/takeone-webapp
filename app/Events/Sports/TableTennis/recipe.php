<?php

/*
| Table tennis — ITTF: games to 11 (win by 2), best of five; ITTF age categories.
|
| A recipe for the generic competition engine (App\Events\Championship): the
| building blocks this sport uses and its official numbers, read by
| App\Events\Support\Competition\SportRecipes. Official conventions as of
| 2026-09-27 — the owner updates them here when a federation or a club runs
| differently. See SportRecipe for every key.
*/

return [
    'entrants' => ['individual', 'pair', 'team'],
    'team_size' => [
        'min' => 3,
        'max' => 5,
    ],
    'criteria' => ['age', 'gender', 'rating'],
    'result' => 'sets',
    'formats' => ['groups_knockout', 'knockout', 'round_robin'],
    'scoring' => [
        'best_of' => 5,
        'points_to' => 11,
        'win_by' => 2,
    ],
    'tie_breakers' => ['points', 'head_to_head', 'sets', 'games', 'name'],
    'medals' => 'both_sf_losers',
    'venue' => 'table',
    'officials' => ['umpire', 'referee'],
    'age_groups' => [
        'U11' => [
            'max' => 10,
        ],
        'U13' => [
            'max' => 12,
        ],
        'U15' => [
            'max' => 14,
        ],
        'U19' => [
            'max' => 18,
        ],
        'U21' => [
            'max' => 20,
        ],
        'Senior' => [],
        'Veterans 40+' => [
            'min' => 40,
        ],
    ],
];
