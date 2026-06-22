<?php

/*
|--------------------------------------------------------------------------
| Combat Championship module
|--------------------------------------------------------------------------
| Sport-agnostic bracket/scheduling engine. Each combat sport is a plug-in
| (App\Combat\Sports\CombatSport). Add a sport = add a class here + its
| weight-table config. Zero engine changes.
*/

return [

    // sport key (matches ClubEvent->sport) => plug-in class (app/Sports/<Name>/)
    'sports' => [
        'taekwondo' => \App\Sports\Taekwondo\Taekwondo::class,
        // 'karate'  => \App\Sports\Karate\Karate::class,
        // 'judo'    => \App\Sports\Judo\Judo::class,
    ],

    // engine defaults (overridable per event)
    'defaults' => [
        'minutes_per_match' => 8,   // bout + changeover
        'break_minutes'     => 60,  // fallback when no break window is set
        'division_capacity' => null,// null = no cap
    ],

    // bronze-medal conventions a sport may declare
    'bronze_rules' => ['repechage', 'both_sf_losers', 'third_place_match'],
];
