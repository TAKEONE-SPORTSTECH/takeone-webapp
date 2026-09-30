<?php

/*
| Diving — World Aquatics: a panel’s marks, highest and lowest dropped, times the dive’s difficulty.
|
| A recipe for the generic competition engine (App\Events\Championship): the
| building blocks this sport uses and its official numbers, read by
| App\Events\Support\Competition\SportRecipes. Official conventions as of
| 2026-09-27 — the owner updates them here when a federation or a club runs
| differently. See SportRecipe for every key.
*/

return [
    'entrants' => ['individual', 'pair'],
    'criteria' => ['age', 'gender'],
    'contest' => 'judged',
    'result' => 'marks',
    'formats' => ['heats'],
    'scoring' => [
        'better' => 'higher',
        'judges' => 7,
        'drop' => 'high_low',
        'unit' => 'pts',
        'difficulty' => true,
    ],
    'venue' => 'pool',
    'officials' => ['referee', 'judge'],
    'age_groups' => [
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
        'Senior' => [],
    ],
];
