<?php

/*
| Gymnastics — FIG: a panel’s marks, highest and lowest dropped; highest score wins.
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
        'max' => 5,
    ],
    'criteria' => ['age', 'gender', 'level'],
    'contest' => 'judged',
    'result' => 'marks',
    'formats' => ['heats'],
    'scoring' => [
        'better' => 'higher',
        'judges' => 5,
        'drop' => 'high_low',
        'unit' => 'pts',
    ],
    'venue' => 'floor',
    'officials' => ['judge', 'technical_official'],
    'levels' => ['Level 1', 'Level 2', 'Level 3', 'Level 4', 'Level 5', 'Elite'],
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
        'Junior' => [
            'max' => 15,
        ],
        'Senior' => [],
    ],
];
