<?php

/*
| Fencing — FIE: pools then a direct-elimination tableau; two bronzes.
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
        'max' => 4,
    ],
    'criteria' => ['age', 'gender', 'rating'],
    'result' => 'score',
    'formats' => ['groups_knockout', 'knockout'],
    'medals' => 'both_sf_losers',
    'venue' => 'piste',
    'officials' => ['referee'],
    'age_groups' => [
        'U13' => [
            'max' => 12,
        ],
        'U15' => [
            'max' => 14,
        ],
        'Cadet' => [
            'max' => 16,
        ],
        'Junior' => [
            'max' => 19,
        ],
        'Senior' => [],
        'Veterans 40+' => [
            'min' => 40,
        ],
    ],
];
