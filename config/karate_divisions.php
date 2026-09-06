<?php

/*
|--------------------------------------------------------------------------
| World Karate Federation kumite weight divisions — single source of truth
|--------------------------------------------------------------------------
| Mirrors config/taekwondo_divisions.php in shape and purpose: used by BOTH
| classifyKarate() (to place a member by gender/age/weight) and the event form
| (to auto-generate divisions the owner can pick from). One place, so the form's
| generated names match what registration auto-creates.
|
| Age groups follow WKF competition age bands, which are NOT the same as
| Taekwondo's — this is the main reason Karate needs its own table rather than
| borrowing next door:
|
|   Kids     under 12   (federation-defined; WKF has no world-level kids kumite)
|   Cadet    12–14
|   Junior   16–17      — see the note on 15-year-olds below
|   U21      18–20
|   Senior   18+        (U21 athletes may also enter Senior)
|
| Each class: label (e.g. "-67"), min, max (kg). "-X" = up to X (min 0).
| "+X" = over X (max 200). WKF kumite classes are unnamed — there is no
| "Featherweight" in karate the way there is in taekwondo — so no 'name' key.
| classifyKarate() already treats it as optional.
|
| ⚠️ VERIFY BEFORE A REAL COMPETITION. These are the standard WKF kumite
| categories, but weight tables are the kind of thing a federation revises, and
| national bodies routinely run their own kids/veteran bands. Check against your
| federation's current competition rules and edit here — nothing else needs to
| change, because everything reads this file.
|
| Kata is not weight-classed at all. It divides by age and gender only, so a
| kata division does not come from this table.
*/

// WKF Senior kumite (18+). U21 uses the same weights, which is why the two
// share an array rather than repeating it.
$wkfSeniorMale = [
    ['label' => '-60', 'min' => 0,  'max' => 60],
    ['label' => '-67', 'min' => 0,  'max' => 67],
    ['label' => '-75', 'min' => 0,  'max' => 75],
    ['label' => '-84', 'min' => 0,  'max' => 84],
    ['label' => '+84', 'min' => 84, 'max' => 200],
];
$wkfSeniorFemale = [
    ['label' => '-50', 'min' => 0,  'max' => 50],
    ['label' => '-55', 'min' => 0,  'max' => 55],
    ['label' => '-61', 'min' => 0,  'max' => 61],
    ['label' => '-68', 'min' => 0,  'max' => 68],
    ['label' => '+68', 'min' => 68, 'max' => 200],
];

return [
    // WKF runs no world-level kumite below cadet, so these are the common
    // national-level bands. Replace with your federation's if they differ.
    'Kids' => [
        'male' => [
            ['label' => '-30', 'min' => 0,  'max' => 30],
            ['label' => '-35', 'min' => 0,  'max' => 35],
            ['label' => '-40', 'min' => 0,  'max' => 40],
            ['label' => '-45', 'min' => 0,  'max' => 45],
            ['label' => '-50', 'min' => 0,  'max' => 50],
            ['label' => '+50', 'min' => 50, 'max' => 200],
        ],
        'female' => [
            ['label' => '-30', 'min' => 0,  'max' => 30],
            ['label' => '-35', 'min' => 0,  'max' => 35],
            ['label' => '-40', 'min' => 0,  'max' => 40],
            ['label' => '-45', 'min' => 0,  'max' => 45],
            ['label' => '+45', 'min' => 45, 'max' => 200],
        ],
    ],
    'Cadet' => [
        'male' => [
            ['label' => '-52', 'min' => 0,  'max' => 52],
            ['label' => '-57', 'min' => 0,  'max' => 57],
            ['label' => '-63', 'min' => 0,  'max' => 63],
            ['label' => '-70', 'min' => 0,  'max' => 70],
            ['label' => '+70', 'min' => 70, 'max' => 200],
        ],
        'female' => [
            ['label' => '-47', 'min' => 0,  'max' => 47],
            ['label' => '-54', 'min' => 0,  'max' => 54],
            ['label' => '+54', 'min' => 54, 'max' => 200],
        ],
    ],
    'Junior' => [
        'male' => [
            ['label' => '-55', 'min' => 0,  'max' => 55],
            ['label' => '-61', 'min' => 0,  'max' => 61],
            ['label' => '-68', 'min' => 0,  'max' => 68],
            ['label' => '-76', 'min' => 0,  'max' => 76],
            ['label' => '+76', 'min' => 76, 'max' => 200],
        ],
        'female' => [
            ['label' => '-48', 'min' => 0,  'max' => 48],
            ['label' => '-53', 'min' => 0,  'max' => 53],
            ['label' => '-59', 'min' => 0,  'max' => 59],
            ['label' => '+59', 'min' => 59, 'max' => 200],
        ],
    ],
    'U21' => [
        'male'   => $wkfSeniorMale,
        'female' => $wkfSeniorFemale,
    ],
    'Senior' => [
        'male'   => $wkfSeniorMale,
        'female' => $wkfSeniorFemale,
    ],
];
