<?php

/*
| Water polo — World Aquatics: 3/1/0 points; a penalty shoot-out decides a knockout tie.
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
        'min' => 7,
        'max' => 15,
    ],
    'criteria' => ['age', 'gender'],
    'result' => 'score',
    'formats' => ['groups_knockout', 'round_robin', 'knockout'],
    'draws' => true,
    'scoring' => [
        'penalties' => true,
    ],
    'points' => [
        'win' => 3,
        'draw' => 1,
        'loss' => 0,
    ],
    'tie_breakers' => ['points', 'difference', 'scored', 'head_to_head', 'name'],
    'medals' => 'third_place_match',
    'venue' => 'pool',
    'officials' => ['referee', 'goal_judge', 'timekeeper'],
    'age_groups' => [
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
