<?php

/*
| Fitness competition — workouts scored in points; highest total wins.
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
        'min' => 2,
        'max' => 4,
    ],
    'criteria' => ['age', 'gender', 'level'],
    'contest' => 'measured',
    'result' => 'marks',
    'formats' => ['heats'],
    'scoring' => [
        'better' => 'higher',
        'unit' => 'pts',
        'attempts' => 1,
    ],
    'venue' => 'floor',
    'officials' => ['judge'],
    'levels' => ['Scaled', 'Intermediate', 'RX'],
    'age_groups' => [
        'Teens' => [
            'max' => 17,
        ],
        'Open' => [],
        'Masters 35+' => [
            'min' => 35,
        ],
    ],
];
