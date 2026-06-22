<?php

/*
|--------------------------------------------------------------------------
| World Taekwondo weight divisions — single source of truth
|--------------------------------------------------------------------------
| Used by BOTH classifyTaekwondo() (to place a member by gender/age/weight)
| and the event form (to auto-generate divisions the owner can pick from).
| Keeping them in one place guarantees the form's generated division names
| match what registration auto-creates — no duplicate/orphan divisions.
|
| Age groups (by competition age): Kids 6–11, Cadet 12–14, Junior 15–17,
| Senior 18–30, Masters 31+. Each class: label (e.g. "-58"), min, max (kg).
| "-X" = up to X (min 0). "+X" = over X (max 200).
*/

return [
    'Kids' => [
        'male' => [
            ['label' => '-26', 'min' => 0,  'max' => 26],
            ['label' => '-30', 'min' => 0,  'max' => 30],
            ['label' => '-33', 'min' => 0,  'max' => 33],
            ['label' => '-36', 'min' => 0,  'max' => 36],
            ['label' => '-40', 'min' => 0,  'max' => 40],
            ['label' => '-45', 'min' => 0,  'max' => 45],
            ['label' => '-50', 'min' => 0,  'max' => 50],
            ['label' => '+50', 'min' => 50, 'max' => 200],
        ],
        'female' => [
            ['label' => '-26', 'min' => 0,  'max' => 26],
            ['label' => '-30', 'min' => 0,  'max' => 30],
            ['label' => '-33', 'min' => 0,  'max' => 33],
            ['label' => '-36', 'min' => 0,  'max' => 36],
            ['label' => '-40', 'min' => 0,  'max' => 40],
            ['label' => '-45', 'min' => 0,  'max' => 45],
            ['label' => '-50', 'min' => 0,  'max' => 50],
            ['label' => '+50', 'min' => 50, 'max' => 200],
        ],
    ],
    'Cadet' => [
        'male' => [
            ['label' => '-33', 'min' => 0,  'max' => 33],
            ['label' => '-37', 'min' => 0,  'max' => 37],
            ['label' => '-41', 'min' => 0,  'max' => 41],
            ['label' => '-45', 'min' => 0,  'max' => 45],
            ['label' => '-49', 'min' => 0,  'max' => 49],
            ['label' => '-53', 'min' => 0,  'max' => 53],
            ['label' => '-57', 'min' => 0,  'max' => 57],
            ['label' => '-61', 'min' => 0,  'max' => 61],
            ['label' => '-65', 'min' => 0,  'max' => 65],
            ['label' => '+65', 'min' => 65, 'max' => 200],
        ],
        'female' => [
            ['label' => '-29', 'min' => 0,  'max' => 29],
            ['label' => '-33', 'min' => 0,  'max' => 33],
            ['label' => '-37', 'min' => 0,  'max' => 37],
            ['label' => '-41', 'min' => 0,  'max' => 41],
            ['label' => '-44', 'min' => 0,  'max' => 44],
            ['label' => '-47', 'min' => 0,  'max' => 47],
            ['label' => '-51', 'min' => 0,  'max' => 51],
            ['label' => '-55', 'min' => 0,  'max' => 55],
            ['label' => '-59', 'min' => 0,  'max' => 59],
            ['label' => '+59', 'min' => 59, 'max' => 200],
        ],
    ],
    'Junior' => [
        'male' => [
            ['label' => '-45', 'min' => 0,  'max' => 45],
            ['label' => '-48', 'min' => 0,  'max' => 48],
            ['label' => '-51', 'min' => 0,  'max' => 51],
            ['label' => '-55', 'min' => 0,  'max' => 55],
            ['label' => '-59', 'min' => 0,  'max' => 59],
            ['label' => '-63', 'min' => 0,  'max' => 63],
            ['label' => '-68', 'min' => 0,  'max' => 68],
            ['label' => '-73', 'min' => 0,  'max' => 73],
            ['label' => '-78', 'min' => 0,  'max' => 78],
            ['label' => '+78', 'min' => 78, 'max' => 200],
        ],
        'female' => [
            ['label' => '-42', 'min' => 0,  'max' => 42],
            ['label' => '-44', 'min' => 0,  'max' => 44],
            ['label' => '-46', 'min' => 0,  'max' => 46],
            ['label' => '-49', 'min' => 0,  'max' => 49],
            ['label' => '-52', 'min' => 0,  'max' => 52],
            ['label' => '-55', 'min' => 0,  'max' => 55],
            ['label' => '-59', 'min' => 0,  'max' => 59],
            ['label' => '-63', 'min' => 0,  'max' => 63],
            ['label' => '-68', 'min' => 0,  'max' => 68],
            ['label' => '+68', 'min' => 68, 'max' => 200],
        ],
    ],
    'Senior' => [
        'male' => [
            ['label' => '-54', 'min' => 0,  'max' => 54],
            ['label' => '-58', 'min' => 0,  'max' => 58],
            ['label' => '-63', 'min' => 0,  'max' => 63],
            ['label' => '-68', 'min' => 0,  'max' => 68],
            ['label' => '-74', 'min' => 0,  'max' => 74],
            ['label' => '-80', 'min' => 0,  'max' => 80],
            ['label' => '-87', 'min' => 0,  'max' => 87],
            ['label' => '+87', 'min' => 87, 'max' => 200],
        ],
        'female' => [
            ['label' => '-46', 'min' => 0,  'max' => 46],
            ['label' => '-49', 'min' => 0,  'max' => 49],
            ['label' => '-53', 'min' => 0,  'max' => 53],
            ['label' => '-57', 'min' => 0,  'max' => 57],
            ['label' => '-62', 'min' => 0,  'max' => 62],
            ['label' => '-67', 'min' => 0,  'max' => 67],
            ['label' => '-73', 'min' => 0,  'max' => 73],
            ['label' => '+73', 'min' => 73, 'max' => 200],
        ],
    ],
    'Masters' => [
        'male' => [
            ['label' => '-60', 'min' => 0,  'max' => 60],
            ['label' => '-70', 'min' => 0,  'max' => 70],
            ['label' => '-80', 'min' => 0,  'max' => 80],
            ['label' => '+80', 'min' => 80, 'max' => 200],
        ],
        'female' => [
            ['label' => '-55', 'min' => 0,  'max' => 55],
            ['label' => '-63', 'min' => 0,  'max' => 63],
            ['label' => '-72', 'min' => 0,  'max' => 72],
            ['label' => '+72', 'min' => 72, 'max' => 200],
        ],
    ],
];
