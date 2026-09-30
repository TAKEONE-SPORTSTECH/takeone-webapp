<?php

/*
| Rugby — World Rugby: 4/2/0 points (bonus points are the organiser’s to add).
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
        'min' => 15,
        'max' => 23,
    ],
    'criteria' => ['age', 'gender'],
    'result' => 'score',
    'formats' => ['groups_knockout', 'round_robin', 'knockout'],
    'draws' => true,
    'scoring' => [
        'extra_time' => true,
    ],
    'points' => [
        'win' => 4,
        'draw' => 2,
        'loss' => 0,
    ],
    'tie_breakers' => ['points', 'difference', 'scored', 'head_to_head', 'name'],
    'medals' => 'third_place_match',
    'venue' => 'pitch',
    'officials' => ['referee', 'assistant_referee'],
    'age_groups' => [
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
