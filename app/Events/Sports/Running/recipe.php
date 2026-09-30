<?php

/*
| Running — World Athletics: heats then a final; fastest time wins.
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
        'min' => 4,
        'max' => 6,
    ],
    'criteria' => ['age', 'gender'],
    'contest' => 'field',
    'result' => 'time',
    'formats' => ['heats'],
    'scoring' => [
        'better' => 'lower',
        'unit' => 's',
        'lanes' => 8,
    ],
    'venue' => 'track',
    'officials' => ['starter', 'timekeeper', 'judge'],
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
