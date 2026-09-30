<?php

/*
| Wrestling — UWW weight categories; two bronzes through repechage.
|
| A recipe for the generic competition engine (App\Events\Championship): the
| building blocks this sport uses and its official numbers, read by
| App\Events\Support\Competition\SportRecipes. Official conventions as of
| 2026-09-27 — the owner updates them here when a federation or a club runs
| differently. See SportRecipe for every key.
*/

return [
    'entrants' => ['individual'],
    'criteria' => ['age', 'gender', 'weight'],
    'result' => 'score',
    'formats' => ['knockout', 'round_robin'],
    'medals' => 'both_sf_losers',
    'venue' => 'mat',
    'officials' => ['referee', 'judge', 'mat_chairman'],
    'age_groups' => [
        'U15' => [
            'max' => 14,
        ],
        'U17' => [
            'max' => 16,
        ],
        'U20' => [
            'max' => 19,
        ],
        'Senior' => [],
        'Veterans 35+' => [
            'min' => 35,
        ],
    ],
];
