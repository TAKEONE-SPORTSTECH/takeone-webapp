<?php

/*
| Weightlifting — IWF: three snatch and three clean & jerk attempts; best total wins.
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
    'contest' => 'measured',
    'result' => 'distance',
    'formats' => ['heats'],
    'scoring' => [
        'better' => 'higher',
        'unit' => 'kg',
        'attempts' => 6,
        'counts' => 'best_total',
    ],
    'venue' => 'platform',
    'officials' => ['referee', 'technical_official', 'timekeeper'],
    'age_groups' => [
        'Youth' => [
            'max' => 17,
        ],
        'Junior' => [
            'max' => 20,
        ],
        'Senior' => [],
        'Masters 35+' => [
            'min' => 35,
        ],
    ],
];
