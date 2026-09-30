<?php

/*
| Triathlon — World Triathlon: one start, total time decides.
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
    'criteria' => ['age', 'gender'],
    'contest' => 'field',
    'result' => 'time',
    'formats' => ['heats'],
    'scoring' => [
        'better' => 'lower',
        'unit' => 's',
    ],
    'venue' => 'course',
    'officials' => ['technical_official', 'timekeeper'],
    'age_groups' => [
        'Youth' => [
            'max' => 17,
        ],
        'Junior' => [
            'max' => 19,
        ],
        'Elite' => [],
        'Age group 40+' => [
            'min' => 40,
        ],
    ],
];
