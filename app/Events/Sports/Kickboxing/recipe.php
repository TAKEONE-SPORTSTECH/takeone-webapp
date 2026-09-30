<?php

/*
| Kickboxing — WAKO: knockout by weight; two bronzes.
|
| A recipe for the generic competition engine (App\Events\Championship): the
| building blocks this sport uses and its official numbers, read by
| App\Events\Support\Competition\SportRecipes. Official conventions as of
| 2026-09-27 — the owner updates them here when a federation or a club runs
| differently. See SportRecipe for every key.
*/

return [
    'entrants' => ['individual'],
    'criteria' => ['age', 'gender', 'weight', 'rank'],
    'result' => 'score',
    'formats' => ['knockout', 'round_robin'],
    'medals' => 'both_sf_losers',
    'venue' => 'ring',
    'officials' => ['referee', 'judge', 'timekeeper'],
    'age_groups' => [
        'Kids' => [
            'max' => 12,
        ],
        'Cadet' => [
            'min' => 13,
            'max' => 15,
        ],
        'Junior' => [
            'min' => 16,
            'max' => 18,
        ],
        'Senior' => [],
        'Masters 41+' => [
            'min' => 41,
        ],
    ],
];
