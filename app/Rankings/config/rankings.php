<?php

return [
    // National rankings. OFF by default: no points are written and no page is
    // reachable until this is switched on.
    'enabled' => (bool) env('RANKINGS_ENABLED', false),

    // Sport → the points scheme that ranks it. A sport not listed earns no points.
    'schemes' => [
        'karate' => \App\Rankings\Schemes\Wkf::class,
    ],

    // The rule books whose events are ranked (an event's category_system). A
    // ranking only means something when every event in it divides athletes the
    // same way, so this starts with Shotokan alone; add one to rank it too.
    'category_systems' => array_values(array_filter(array_map('trim', explode(',', (string) env('RANKINGS_CATEGORY_SYSTEMS', 'shotokan'))))),

    // The event factor every federation event carries until events can be given
    // tiers of their own. 6 = what WKF gives a Premier League / continental
    // championship, the closest thing to a national championship.
    'default_factor' => (int) env('RANKINGS_DEFAULT_FACTOR', 6),
];
