<?php

/*
|--------------------------------------------------------------------------
| IBJJF NO-GI weight divisions — Brazilian Jiu-Jitsu
|--------------------------------------------------------------------------
| The sibling of config/bjj_divisions.php (which is the GI table) and the same
| shape exactly, so everything that reads one can read the other: the sport
| plug-in's classify(), and the organiser's weight-class preset.
|
| It is a SEPARATE TABLE and not the gi one with a few kilos taken off, which
| is what the gi file's own header asks for: "a no-gi competition should be run
| off its own table rather than by nudging this one". An athlete weighs in
| without a gi here, so the limits are lower — about 2kg for an adult — and the
| ladder steps differently.
|
| ⚠️ PROVENANCE, AND WHAT TO CHECK BEFORE A REAL COMPETITION
|
|   Adult / Master  — IBJJF's published no-gi limits. VERIFY: weight tables are
|                     exactly the kind of thing a federation revises, and these
|                     were written from the published table rather than read off
|                     a scale.
|   Juvenile        — DERIVED, not published here: the gi juvenile limits less
|                     the 2kg the adult tables differ by. Computed below rather
|                     than typed, so it is obvious that it is a derivation and
|                     not a table somebody transcribed.
|   Kids            — THE SAME BANDS as the gi file, deliberately. Those are
|                     round national-level bands a federation sets (30, 35, 40…),
|                     not gi limits, so there is nothing for a gi to add to them.
|
| Edit HERE and nothing else changes: the preset, classify() and every screen
| read this file.
*/

// Adult, weighed without the gi. Master 1–7 share these; they are separated by
// AGE, not by weight.
$adultMale = [
    ['label' => '-55.5', 'name' => 'Rooster',       'min' => 0,    'max' => 55.5],
    ['label' => '-61.5', 'name' => 'Light Feather', 'min' => 0,    'max' => 61.5],
    ['label' => '-67.5', 'name' => 'Feather',       'min' => 0,    'max' => 67.5],
    ['label' => '-73.5', 'name' => 'Light',         'min' => 0,    'max' => 73.5],
    ['label' => '-79.5', 'name' => 'Middle',        'min' => 0,    'max' => 79.5],
    ['label' => '-85.5', 'name' => 'Medium Heavy',  'min' => 0,    'max' => 85.5],
    ['label' => '-91.5', 'name' => 'Heavy',         'min' => 0,    'max' => 91.5],
    ['label' => '-97.5', 'name' => 'Super Heavy',   'min' => 0,    'max' => 97.5],
    ['label' => '+97.5', 'name' => 'Ultra Heavy',   'min' => 97.5, 'max' => 200],
];

$adultFemale = [
    ['label' => '-46.5', 'name' => 'Rooster',       'min' => 0,    'max' => 46.5],
    ['label' => '-51.5', 'name' => 'Light Feather', 'min' => 0,    'max' => 51.5],
    ['label' => '-56.5', 'name' => 'Feather',       'min' => 0,    'max' => 56.5],
    ['label' => '-61.5', 'name' => 'Light',         'min' => 0,    'max' => 61.5],
    ['label' => '-66.5', 'name' => 'Middle',        'min' => 0,    'max' => 66.5],
    ['label' => '-71.5', 'name' => 'Medium Heavy',  'min' => 0,    'max' => 71.5],
    ['label' => '-76.5', 'name' => 'Heavy',         'min' => 0,    'max' => 76.5],
    ['label' => '+76.5', 'name' => 'Super Heavy',   'min' => 76.5, 'max' => 200],
];

/*
 * Juvenile, DERIVED from the gi table by the 2kg the adult tables differ by.
 *
 * Computed rather than transcribed on purpose: a reader can see at a glance
 * that these are not numbers somebody read off a federation page, and fixing
 * them means replacing this block with a real table rather than hunting for
 * which of nine numbers was a guess.
 */
$lighter = static function (array $classes, float $by): array {
    return array_map(static function (array $c) use ($by) {
        $max = $c['max'] >= 200 ? 200 : round($c['max'] - $by, 1);
        $min = $c['min'] > 0 ? round($c['min'] - $by, 1) : 0;

        return [
            'label' => ($c['min'] > 0 ? '+' : '-').($c['min'] > 0 ? $min : $max),
            'name' => $c['name'] ?? null,
            'min' => $min,
            'max' => $max,
        ];
    }, $classes);
};

/*
 * ⚠️ Loaded inside a closure, NOT with a bare `require`.
 *
 * A bare require runs the other file in THIS scope, and the gi table builds
 * itself from variables named `$adultMale` / `$adultFemale` — exactly the names
 * used above. It silently overwrote them, and this file returned the gi limits
 * under a no-gi name. The closure gives that file a scope of its own.
 */
$gi = (static fn () => require __DIR__.'/gi.php')();

return [
    // Round national bands, weighed without a gi either way — unchanged.
    'Kids' => $gi['Kids'],

    'Juvenile' => [
        'male' => $lighter($gi['Juvenile']['male'], 2),
        'female' => $lighter($gi['Juvenile']['female'], 2),
    ],

    'Adult' => [
        'male' => $adultMale,
        'female' => $adultFemale,
    ],

    'Master' => [
        'male' => $adultMale,
        'female' => $adultFemale,
    ],
];
