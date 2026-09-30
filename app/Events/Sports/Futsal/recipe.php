<?php

/*
| Futsal — FIFA: 3/1/0 points; extra time and penalties decide a knockout tie.
|
| A recipe for the generic competition engine (App\Events\Championship): the
| building blocks this sport uses and its official numbers, read by
| App\Events\Support\Competition\SportRecipes. Official conventions as of
| 2026-09-27 — the owner updates them here when a federation or a club runs
| differently. See SportRecipe for every key.
*/

return [
    'entrants' => ['team'],
    'team_size' => [
        'min' => 5,
        'max' => 14,
    ],
    'criteria' => ['age', 'gender'],
    'result' => 'score',
    'formats' => ['groups_knockout', 'round_robin', 'knockout'],
    'draws' => true,
    'scoring' => [
        'extra_time' => true,
        'penalties' => true,
    ],
    'points' => [
        'win' => 3,
        'draw' => 1,
        'loss' => 0,
    ],
    'tie_breakers' => ['points', 'difference', 'scored', 'head_to_head', 'name'],
    'medals' => 'third_place_match',
    'venue' => 'court',
    'officials' => ['referee', 'timekeeper'],
    'age_groups' => [
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
        'Senior' => [],
    ],
];
