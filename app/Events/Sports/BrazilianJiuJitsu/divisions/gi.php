<?php

/*
|--------------------------------------------------------------------------
| IBJJF weight divisions — single source of truth for Brazilian Jiu-Jitsu
|--------------------------------------------------------------------------
| Mirrors config/karate_divisions.php and config/taekwondo_divisions.php in
| shape and purpose: read by BOTH the sport plug-in's classify() (to place a
| member by gender/age/weight) and the event form (to generate the divisions an
| organiser can pick from). One place, so the form's generated names match what
| registration auto-creates.
|
| Age groups follow IBJJF competition bands:
|
|   Kids       4–15   (federation-defined; IBJJF runs many narrow kids bands —
|                      these are the common national-level ones)
|   Juvenile   16–17
|   Adult      18–29
|   Master     30+    (IBJJF splits Master 1–7 by age; they share weights,
|                      which is why they share one entry here)
|
| Weights are the IBJJF GI limits WITH the gi, in kilograms. The no-gi table is
| ~1kg lighter per class; a no-gi competition should be run off its own table
| rather than by nudging this one.
|
| Adult, Juvenile and Master GI limits were checked against the published IBJJF
| tables on 2026-08-29 and match. The KIDS bands below are NOT IBJJF's — IBJJF
| runs a much finer kids ladder — they are common national-level bands and are
| the part most likely to differ from whatever federation you are running under.
|
| ⚠️ RE-VERIFY BEFORE A REAL COMPETITION. Weight tables are exactly the kind of
| thing a federation revises, and national bodies routinely run their own kids
| and master bands. Check against your federation's current rules and edit HERE
| — nothing else needs to change, because everything reads this file.
|
| Each class: label (the limit, e.g. "-76"), name (the IBJJF division name, e.g.
| "Light"), min and max in kg. "-X" = up to X (min 0). "+X" = over X (max 200).
*/

// IBJJF adult gi limits. Juvenile uses its own lighter table below; Master
// shares the adult one, which is why the two reference the same array.
$adultMale = [
    ['label' => '-57.5',  'name' => 'Rooster',        'min' => 0,     'max' => 57.5],
    ['label' => '-64',    'name' => 'Light Feather',  'min' => 0,     'max' => 64],
    ['label' => '-70',    'name' => 'Feather',        'min' => 0,     'max' => 70],
    ['label' => '-76',    'name' => 'Light',          'min' => 0,     'max' => 76],
    ['label' => '-82.3',  'name' => 'Middle',         'min' => 0,     'max' => 82.3],
    ['label' => '-88.3',  'name' => 'Medium Heavy',   'min' => 0,     'max' => 88.3],
    ['label' => '-94.3',  'name' => 'Heavy',          'min' => 0,     'max' => 94.3],
    ['label' => '-100.5', 'name' => 'Super Heavy',    'min' => 0,     'max' => 100.5],
    ['label' => '+100.5', 'name' => 'Ultra Heavy',    'min' => 100.5, 'max' => 200],
];

$adultFemale = [
    ['label' => '-48.5', 'name' => 'Rooster',       'min' => 0,    'max' => 48.5],
    ['label' => '-53.5', 'name' => 'Light Feather', 'min' => 0,    'max' => 53.5],
    ['label' => '-58.5', 'name' => 'Feather',       'min' => 0,    'max' => 58.5],
    ['label' => '-64',   'name' => 'Light',         'min' => 0,    'max' => 64],
    ['label' => '-69',   'name' => 'Middle',        'min' => 0,    'max' => 69],
    ['label' => '-74',   'name' => 'Medium Heavy',  'min' => 0,    'max' => 74],
    ['label' => '-79.3', 'name' => 'Heavy',         'min' => 0,    'max' => 79.3],
    ['label' => '+79.3', 'name' => 'Super Heavy',   'min' => 79.3, 'max' => 200],
];

return [

    // Common national-level kids bands. IBJJF's own kids ladder is far finer
    // (one band per year of age); replace with your federation's if they differ.
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
            ['label' => '-28', 'min' => 0,  'max' => 28],
            ['label' => '-32', 'min' => 0,  'max' => 32],
            ['label' => '-36', 'min' => 0,  'max' => 36],
            ['label' => '-40', 'min' => 0,  'max' => 40],
            ['label' => '-45', 'min' => 0,  'max' => 45],
            ['label' => '+45', 'min' => 45, 'max' => 200],
        ],
    ],

    'Juvenile' => [
        'male' => [
            ['label' => '-53.5', 'name' => 'Rooster',       'min' => 0,    'max' => 53.5],
            ['label' => '-58.5', 'name' => 'Light Feather', 'min' => 0,    'max' => 58.5],
            ['label' => '-64',   'name' => 'Feather',       'min' => 0,    'max' => 64],
            ['label' => '-69',   'name' => 'Light',         'min' => 0,    'max' => 69],
            ['label' => '-74',   'name' => 'Middle',        'min' => 0,    'max' => 74],
            ['label' => '-79.3', 'name' => 'Medium Heavy',  'min' => 0,    'max' => 79.3],
            ['label' => '-84.3', 'name' => 'Heavy',         'min' => 0,    'max' => 84.3],
            ['label' => '-89.3', 'name' => 'Super Heavy',   'min' => 0,    'max' => 89.3],
            ['label' => '+89.3', 'name' => 'Ultra Heavy',   'min' => 89.3, 'max' => 200],
        ],
        'female' => [
            ['label' => '-44.3', 'name' => 'Rooster',       'min' => 0,    'max' => 44.3],
            ['label' => '-48.3', 'name' => 'Light Feather', 'min' => 0,    'max' => 48.3],
            ['label' => '-52.5', 'name' => 'Feather',       'min' => 0,    'max' => 52.5],
            ['label' => '-56.5', 'name' => 'Light',         'min' => 0,    'max' => 56.5],
            ['label' => '-60.5', 'name' => 'Middle',        'min' => 0,    'max' => 60.5],
            ['label' => '-65',   'name' => 'Medium Heavy',  'min' => 0,    'max' => 65],
            ['label' => '-69',   'name' => 'Heavy',         'min' => 0,    'max' => 69],
            ['label' => '+69',   'name' => 'Super Heavy',   'min' => 69,   'max' => 200],
        ],
    ],

    'Adult' => [
        'male' => $adultMale,
        'female' => $adultFemale,
    ],

    // Master 1–7 share the adult limits; they are separated by AGE, not weight.
    'Master' => [
        'male' => $adultMale,
        'female' => $adultFemale,
    ],
];
