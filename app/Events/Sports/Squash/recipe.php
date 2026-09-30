<?php

/*
| Squash — WSF: games to 11 point-a-rally (win by 2), best of five.
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
        'min' => 3,
        'max' => 5,
    ],
    'criteria' => ['age', 'gender', 'level'],
    'result' => 'sets',
    'formats' => ['knockout', 'groups_knockout', 'round_robin'],
    'scoring' => [
        'best_of' => 5,
        'points_to' => 11,
        'win_by' => 2,
    ],
    'tie_breakers' => ['points', 'head_to_head', 'sets', 'games', 'name'],
    'medals' => 'both_sf_losers',
    'venue' => 'court',
    'officials' => ['referee', 'marker'],
    'levels' => ['A', 'B', 'C', 'D'],
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
        'U17' => [
            'max' => 16,
        ],
        'U19' => [
            'max' => 18,
        ],
        'Open' => [],
        'Veterans 40+' => [
            'min' => 40,
        ],
    ],
];
