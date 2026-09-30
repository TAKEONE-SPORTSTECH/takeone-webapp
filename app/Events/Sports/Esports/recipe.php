<?php

/*
| Esports — head-to-head series (best of N maps/games), individual or team.
|
| A recipe for the generic competition engine (App\Events\Championship): the
| building blocks this sport uses and its official numbers, read by
| App\Events\Support\Competition\SportRecipes. Official conventions as of
| 2026-09-27 — the owner updates them here when a federation or a club runs
| differently. See SportRecipe for every key.
*/

return [
    'entrants' => ['team', 'individual'],
    'team_size' => [
        'min' => 1,
        'max' => 7,
    ],
    'criteria' => ['age', 'level'],
    'result' => 'sets',
    'formats' => ['groups_knockout', 'knockout', 'swiss', 'round_robin'],
    'scoring' => [
        'best_of' => 3,
        'points_to' => 1,
        'win_by' => 1,
    ],
    'tie_breakers' => ['points', 'sets', 'head_to_head', 'name'],
    'medals' => 'both_sf_losers',
    'venue' => 'station',
    'officials' => ['referee', 'admin'],
    'age_groups' => [
        'Open' => [],
        'U18' => [
            'max' => 17,
        ],
    ],
];
