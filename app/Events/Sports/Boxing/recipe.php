<?php

/*
| Boxing — decided on points, stoppage or disqualification; two bronzes.
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
    'formats' => ['knockout'],
    'medals' => 'both_sf_losers',
    'venue' => 'ring',
    'officials' => ['referee', 'judge', 'timekeeper'],
    'age_groups' => [
        'Schoolboy' => [
            'max' => 14,
        ],
        'Junior' => [
            'min' => 15,
            'max' => 16,
        ],
        'Youth' => [
            'min' => 17,
            'max' => 18,
        ],
        'Elite' => [
            'min' => 19,
        ],
    ],
];
