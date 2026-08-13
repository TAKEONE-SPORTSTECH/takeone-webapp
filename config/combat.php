<?php

/*
|--------------------------------------------------------------------------
| Combat Championship module
|--------------------------------------------------------------------------
| Sport-agnostic bracket/scheduling engine. Each combat sport is a plug-in
| (App\Sports\Combat\CombatSport) living in its own sport folder under
| app/Events/Sports/<Sport>/. Add a sport = add a class here + its weight-table
| config. Zero engine changes.
*/

return [

    // sport key (matches ClubEvent->sport) => plug-in class (app/Events/Sports/<Sport>/)
    'sports' => [
        'taekwondo' => \App\Events\Sports\Taekwondo\Taekwondo::class,
        'karate' => \App\Events\Sports\Karate\Karate::class,
        // 'judo'    => \App\Events\Sports\Judo\Judo::class,
    ],

    // engine defaults (overridable per event)
    'defaults' => [
        'minutes_per_match' => 8,   // bout + changeover
        'break_minutes' => 60,  // fallback when no break window is set
        'division_capacity' => null, // null = no cap
    ],

    // bronze-medal conventions a sport may declare
    'bronze_rules' => ['repechage', 'both_sf_losers', 'third_place_match'],
];
