<?php

/*
| Judo — IJF weight categories; two bronzes through repechage.
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
        'min' => 5,
        'max' => 7,
    ],
    'criteria' => ['age', 'gender', 'weight', 'rank'],
    'result' => 'score',
    'formats' => ['knockout', 'round_robin'],
    'scoring' => [],
    'medals' => 'both_sf_losers',
    'venue' => 'mat',
    'officials' => ['referee', 'judge'],
    'age_groups' => [
        'U13' => [
            'max' => 12,
        ],
        'U15' => [
            'max' => 14,
        ],
        'Cadet' => [
            'min' => 15,
            'max' => 17,
        ],
        'Junior' => [
            'min' => 18,
            'max' => 20,
        ],
        'Senior' => [],
        'Veterans 30+' => [
            'min' => 30,
        ],
    ],
];
