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

    // The coefficient of an event nobody has set one for. Each event's own is
    // set on the federation event's Analytics tab (App\Rankings\Support\EventFactor).
    'default_factor' => (int) env('RANKINGS_DEFAULT_FACTOR', 1),
];
