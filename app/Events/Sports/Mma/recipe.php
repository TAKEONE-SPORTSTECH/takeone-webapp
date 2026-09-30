<?php

/*
| MMA — decided on points, stoppage or submission.
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
    'venue' => 'cage',
    'officials' => ['referee', 'judge', 'timekeeper'],
    'age_groups' => [
        'Junior' => [
            'max' => 17,
        ],
        'Senior' => [],
    ],
];
