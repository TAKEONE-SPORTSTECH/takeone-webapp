<?php

/*
| Swimming — World Aquatics: heats then a final, eight lanes; fastest time wins.
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
        'max' => 4,
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
    'venue' => 'pool',
    'officials' => ['referee', 'starter', 'timekeeper', 'stroke_judge'],
    'age_groups' => [
        'U10' => [
            'max' => 9,
        ],
        'U12' => [
            'max' => 11,
        ],
        'U14' => [
            'max' => 13,
        ],
        'U16' => [
            'max' => 15,
        ],
        'U18' => [
            'max' => 17,
        ],
        'Open' => [],
        'Masters 25+' => [
            'min' => 25,
        ],
    ],
];
