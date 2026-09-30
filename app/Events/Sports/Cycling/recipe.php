<?php

/*
| Cycling — UCI: time trial or massed start; fastest time wins.
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
        'max' => 6,
    ],
    'criteria' => ['age', 'gender'],
    'contest' => 'field',
    'result' => 'time',
    'formats' => ['heats'],
    'scoring' => [
        'better' => 'lower',
        'unit' => 's',
    ],
    'venue' => 'course',
    'officials' => ['commissaire', 'timekeeper'],
    'age_groups' => [
        'U15' => [
            'max' => 14,
        ],
        'U17' => [
            'max' => 16,
        ],
        'Junior' => [
            'max' => 18,
        ],
        'Elite' => [],
        'Masters 35+' => [
            'min' => 35,
        ],
    ],
];
