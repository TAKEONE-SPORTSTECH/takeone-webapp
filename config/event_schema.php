<?php

/*
|--------------------------------------------------------------------------
| Event schema — sport & type aware
|--------------------------------------------------------------------------
| Single source of truth for the dynamic event form. Each TYPE declares which
| field "sections" it shows; each SPORT adapts the terminology/structure
| (what a "division" is called, whether it's team-based, whether it has belts).
|
| Adding a new sport = one entry under 'sports'. No code changes needed.
|
| Section keys the form/controller understand:
|   schedule · requirements · belt_levels · divisions · phases · prize · league
| (basic info + pricing/tickets always show. Winners/results is a post-event
|  action available to the manager on every competitive type.)
*/

return [

    'types' => [
        'class' => [
            'label' => 'Class', 'icon' => 'bi-heart-pulse-fill', 'color' => '#10b981',
            'sections' => ['schedule', 'requirements'],
        ],
        'race' => [
            'label' => 'Race', 'icon' => 'bi-lightning-charge-fill', 'color' => '#7c3aed',
            'sections' => ['divisions', 'schedule', 'prize'],
            'competitive' => true,
        ],
        'belt_test' => [
            'label' => 'Belt Test', 'icon' => 'bi-patch-check-fill', 'color' => '#f59e0b',
            'sections' => ['belt_levels', 'requirements', 'schedule'],
        ],
        'tournament' => [
            'label' => 'Tournament', 'icon' => 'bi-trophy', 'color' => '#0ea5e9',
            'sections' => ['divisions', 'phases', 'schedule', 'requirements', 'prize'],
            'competitive' => true,
        ],
        'championship' => [
            'label' => 'Championship', 'icon' => 'bi-trophy-fill', 'color' => '#ef4444',
            'sections' => ['divisions', 'phases', 'schedule', 'requirements', 'prize'],
            'competitive' => true,
        ],
        'league' => [
            'label' => 'League', 'icon' => 'bi-table', 'color' => '#6d28d9',
            'sections' => ['league', 'phases', 'schedule', 'prize'],
            'competitive' => true,
        ],
    ],

    /*
    | Sports catalog, grouped by family. division_label = what the "divisions"
    | section is called for that sport. team = competitors are teams. belts =
    | has a belt/grade progression (drives the Belt Test belt pickers).
    */
    'sports' => [

        // ---- Combat / martial arts ----
        'taekwondo'   => ['label' => 'Taekwondo',   'family' => 'Combat', 'icon' => 'bi-person-arms-up', 'division_label' => 'Weight category', 'unit' => 'kg', 'team' => false, 'belts' => true,  'sample' => ['Fin −54kg', 'Fly −58kg', 'Bantam −63kg', 'Feather −68kg', 'Light −74kg', 'Welter −80kg', 'Heavy +87kg']],
        'karate'      => ['label' => 'Karate',      'family' => 'Combat', 'icon' => 'bi-person-arms-up', 'division_label' => 'Weight category', 'unit' => 'kg', 'team' => false, 'belts' => true,  'sample' => ['Kumite −60kg', 'Kumite −67kg', 'Kumite −75kg', 'Kumite +84kg', 'Kata']],
        'judo'        => ['label' => 'Judo',         'family' => 'Combat', 'icon' => 'bi-person-arms-up', 'division_label' => 'Weight category', 'unit' => 'kg', 'team' => false, 'belts' => true,  'sample' => ['−60kg', '−66kg', '−73kg', '−81kg', '−90kg', '+100kg']],
        'bjj'         => ['label' => 'Brazilian Jiu-Jitsu', 'family' => 'Combat', 'icon' => 'bi-person-arms-up', 'division_label' => 'Belt & weight', 'unit' => 'kg', 'team' => false, 'belts' => true, 'sample' => ['White −70kg', 'Blue −76kg', 'Purple −82kg', 'Brown/Black Open']],
        'boxing'      => ['label' => 'Boxing',       'family' => 'Combat', 'icon' => 'bi-trophy', 'division_label' => 'Weight class', 'unit' => 'kg', 'team' => false, 'belts' => false, 'sample' => ['Lightweight −60kg', 'Welterweight −69kg', 'Middleweight −75kg', 'Heavyweight +81kg']],
        'kickboxing'  => ['label' => 'Kickboxing',   'family' => 'Combat', 'icon' => 'bi-trophy', 'division_label' => 'Weight class', 'unit' => 'kg', 'team' => false, 'belts' => false, 'sample' => ['−60kg', '−67kg', '−75kg', '+81kg']],
        'mma'         => ['label' => 'MMA',          'family' => 'Combat', 'icon' => 'bi-trophy', 'division_label' => 'Weight class', 'unit' => 'kg', 'team' => false, 'belts' => false, 'sample' => ['Flyweight', 'Bantamweight', 'Featherweight', 'Lightweight', 'Welterweight']],
        'wrestling'   => ['label' => 'Wrestling',    'family' => 'Combat', 'icon' => 'bi-person-arms-up', 'division_label' => 'Weight category', 'unit' => 'kg', 'team' => false, 'belts' => false, 'sample' => ['−57kg', '−65kg', '−74kg', '−86kg', '−97kg']],
        'fencing'     => ['label' => 'Fencing',      'family' => 'Combat', 'icon' => 'bi-trophy', 'division_label' => 'Weapon & age', 'unit' => '', 'team' => false, 'belts' => false, 'sample' => ['Foil', 'Épée', 'Sabre']],

        // ---- Team sports ----
        'football'    => ['label' => 'Football',     'family' => 'Team', 'icon' => 'bi-dribbble', 'division_label' => 'Group / Division', 'unit' => '', 'team' => true, 'belts' => false, 'sample' => ['Group A', 'Group B', 'Group C', 'Group D']],
        'futsal'      => ['label' => 'Futsal',       'family' => 'Team', 'icon' => 'bi-dribbble', 'division_label' => 'Group', 'unit' => '', 'team' => true, 'belts' => false, 'sample' => ['Group A', 'Group B']],
        'basketball'  => ['label' => 'Basketball',   'family' => 'Team', 'icon' => 'bi-dribbble', 'division_label' => 'Conference / Group', 'unit' => '', 'team' => true, 'belts' => false, 'sample' => ['East', 'West']],
        'volleyball'  => ['label' => 'Volleyball',   'family' => 'Team', 'icon' => 'bi-dribbble', 'division_label' => 'Pool', 'unit' => '', 'team' => true, 'belts' => false, 'sample' => ['Pool A', 'Pool B']],
        'handball'    => ['label' => 'Handball',     'family' => 'Team', 'icon' => 'bi-dribbble', 'division_label' => 'Group', 'unit' => '', 'team' => true, 'belts' => false, 'sample' => ['Group A', 'Group B']],
        'rugby'       => ['label' => 'Rugby',        'family' => 'Team', 'icon' => 'bi-dribbble', 'division_label' => 'Pool', 'unit' => '', 'team' => true, 'belts' => false, 'sample' => ['Pool A', 'Pool B']],
        'hockey'      => ['label' => 'Hockey',       'family' => 'Team', 'icon' => 'bi-dribbble', 'division_label' => 'Group', 'unit' => '', 'team' => true, 'belts' => false, 'sample' => ['Group A', 'Group B']],
        'cricket'     => ['label' => 'Cricket',      'family' => 'Team', 'icon' => 'bi-dribbble', 'division_label' => 'Group', 'unit' => '', 'team' => true, 'belts' => false, 'sample' => ['Group A', 'Group B']],

        // ---- Racquet ----
        'padel'       => ['label' => 'Padel',        'family' => 'Racquet', 'icon' => 'bi-trophy', 'division_label' => 'Draw', 'unit' => '', 'team' => false, 'belts' => false, 'sample' => ['Men’s Doubles', 'Women’s Doubles', 'Mixed Doubles']],
        'tennis'      => ['label' => 'Tennis',       'family' => 'Racquet', 'icon' => 'bi-trophy', 'division_label' => 'Draw', 'unit' => '', 'team' => false, 'belts' => false, 'sample' => ['Men’s Singles', 'Women’s Singles', 'Doubles']],
        'badminton'   => ['label' => 'Badminton',    'family' => 'Racquet', 'icon' => 'bi-trophy', 'division_label' => 'Draw', 'unit' => '', 'team' => false, 'belts' => false, 'sample' => ['Singles', 'Doubles', 'Mixed']],
        'squash'      => ['label' => 'Squash',       'family' => 'Racquet', 'icon' => 'bi-trophy', 'division_label' => 'Draw / Grade', 'unit' => '', 'team' => false, 'belts' => false, 'sample' => ['A Grade', 'B Grade', 'C Grade']],
        'table_tennis'=> ['label' => 'Table Tennis', 'family' => 'Racquet', 'icon' => 'bi-trophy', 'division_label' => 'Draw', 'unit' => '', 'team' => false, 'belts' => false, 'sample' => ['Singles', 'Doubles']],

        // ---- Athletics / endurance ----
        'running'     => ['label' => 'Running',      'family' => 'Athletics', 'icon' => 'bi-lightning-charge-fill', 'division_label' => 'Distance / Age category', 'unit' => '', 'team' => false, 'belts' => false, 'sample' => ['100m', '400m', '1500m', '5K', '10K']],
        'athletics'   => ['label' => 'Athletics',    'family' => 'Athletics', 'icon' => 'bi-lightning-charge-fill', 'division_label' => 'Event / Age category', 'unit' => '', 'team' => false, 'belts' => false, 'sample' => ['100m', 'Long jump', 'Shot put', 'Relay']],
        'cycling'     => ['label' => 'Cycling',      'family' => 'Athletics', 'icon' => 'bi-bicycle', 'division_label' => 'Category', 'unit' => '', 'team' => false, 'belts' => false, 'sample' => ['Road', 'Time trial', 'Criterium']],
        'triathlon'   => ['label' => 'Triathlon',    'family' => 'Athletics', 'icon' => 'bi-lightning-charge-fill', 'division_label' => 'Distance / Age group', 'unit' => '', 'team' => false, 'belts' => false, 'sample' => ['Sprint', 'Olympic', 'Relay']],

        // ---- Aquatic ----
        'swimming'    => ['label' => 'Swimming',     'family' => 'Aquatic', 'icon' => 'bi-water', 'division_label' => 'Event / Age group', 'unit' => '', 'team' => false, 'belts' => false, 'sample' => ['50m Free', '100m Free', '100m Back', '100m Breast', '4×100 Relay']],
        'waterpolo'   => ['label' => 'Water Polo',   'family' => 'Aquatic', 'icon' => 'bi-water', 'division_label' => 'Group', 'unit' => '', 'team' => true, 'belts' => false, 'sample' => ['Group A', 'Group B']],
        'diving'      => ['label' => 'Diving',        'family' => 'Aquatic', 'icon' => 'bi-water', 'division_label' => 'Event', 'unit' => '', 'team' => false, 'belts' => false, 'sample' => ['3m Springboard', '10m Platform']],

        // ---- Strength / fitness ----
        'crossfit'    => ['label' => 'CrossFit',     'family' => 'Fitness', 'icon' => 'bi-fire', 'division_label' => 'Division', 'unit' => '', 'team' => false, 'belts' => false, 'sample' => ['RX Men', 'RX Women', 'Scaled', 'Masters']],
        'weightlifting'=> ['label' => 'Weightlifting','family' => 'Fitness', 'icon' => 'bi-trophy', 'division_label' => 'Weight category', 'unit' => 'kg', 'team' => false, 'belts' => false, 'sample' => ['−61kg', '−73kg', '−89kg', '+102kg']],
        'powerlifting'=> ['label' => 'Powerlifting', 'family' => 'Fitness', 'icon' => 'bi-trophy', 'division_label' => 'Weight class', 'unit' => 'kg', 'team' => false, 'belts' => false, 'sample' => ['−66kg', '−74kg', '−83kg', '−93kg', '+120kg']],
        'gymnastics'  => ['label' => 'Gymnastics',   'family' => 'Fitness', 'icon' => 'bi-stars', 'division_label' => 'Level / Apparatus', 'unit' => '', 'team' => false, 'belts' => false, 'sample' => ['Level 1', 'Level 2', 'Beam', 'Floor', 'Vault']],

        // ---- Other ----
        'esports'     => ['label' => 'Esports',      'family' => 'Other', 'icon' => 'bi-controller', 'division_label' => 'Bracket', 'unit' => '', 'team' => true, 'belts' => false, 'sample' => ['Group A', 'Group B']],
        'chess'       => ['label' => 'Chess',        'family' => 'Other', 'icon' => 'bi-grid-3x3', 'division_label' => 'Section', 'unit' => '', 'team' => false, 'belts' => false, 'sample' => ['Open', 'U1600', 'U1200']],
        'other'       => ['label' => 'Other',        'family' => 'Other', 'icon' => 'bi-dribbble', 'division_label' => 'Category', 'unit' => '', 'team' => false, 'belts' => false, 'sample' => []],
    ],

    'belts' => ['White', 'Yellow', 'Orange', 'Green', 'Blue', 'Purple', 'Brown', 'Red', 'Black'],

    /*
    | Event reach. Who — beyond the host club's own members — may see and
    | self-register. Ordered narrow → wide. `geo` flags the country-gated tiers.
    */
    'scopes' => [
        'internal'   => ['label' => 'This club only', 'desc' => 'Only members of the host club can join.',           'icon' => 'bi-house-door', 'geo' => false],
        'inter_club' => ['label' => 'Open to clubs',  'desc' => 'Members of any club on the platform can join.',     'icon' => 'bi-people',     'geo' => false],
        'nationwide' => ['label' => 'Nationwide',     'desc' => 'Members of clubs in the same country can join.',     'icon' => 'bi-flag',       'geo' => true],
        'regional'   => ['label' => 'Regional',       'desc' => 'Members of clubs in the same region can join.',      'icon' => 'bi-globe-americas', 'geo' => true],
        'worldwide'  => ['label' => 'Worldwide',      'desc' => 'Any member on the platform can join.',               'icon' => 'bi-globe',      'geo' => false],
    ],
];
