<?php

/*
|--------------------------------------------------------------------------
| Event-type packages
|--------------------------------------------------------------------------
| Every event type is a self-contained package owning its data, rules,
| lifecycle, engine, outputs, financials and screens (CLAUDE.md → "Events Are
| Self-Contained Packages"). This file is the ONLY place a package is wired in.
|
| Order matters: the registry asks each package `owns($event)` top-down and the
| first to claim it wins, so specific packages sit above general ones. Anything
| unclaimed falls through to `fallback`.
|
| Adding a type = create app/Events/Sports/<Sport>/<Type>/, implement the
| EventType contract, add one line here, ship its views + migrations + MCP tool
| + tests. No shared controller/form/view edit is permitted.
*/

return [

    'types' => [

        // Combat championships — bracketed, weight-classed, medal-awarding.
        \App\Events\Sports\Taekwondo\Tournament\Tournament::class,
        \App\Events\Sports\Karate\Tournament\Tournament::class,

        // The club's own scoreboard for training: sparring, a friendly, an
        // exhibition. No draw, no medal, opened in one tap on a Tuesday — but
        // the same mat screens, the same scoring table, the same VS
        // introduction as a championship. Cross-sport by design: it adds no
        // rules of its own, it borrows the sport's scoring table whole.
        \App\Events\Sparring\Sparring::class,

        // Open Mat — a scoreboard for a fight nobody planned. No entries, no
        // draw, no queue: two corners, and either may be a member found by
        // search, a member who scanned the mat's code, or a stranger whose name
        // was typed in. Opened by ANY member from /me, for any club they belong
        // to. Sits below Sparring because both claim by event_type and neither
        // overlaps; order between them is immaterial.
        \App\Events\OpenMat\OpenMat::class,

        // TODO — port the remaining types out of the generic bucket. Each
        // lives under its SPORT's folder (see Documentation/EVENTS.md):
        // \App\Events\Sports\Taekwondo\BeltTest\BeltTest::class,
        // \App\Events\Sports\Taekwondo\Poomsae\Poomsae::class,
        // \App\Events\Sports\Football\League\League::class,
    ],

    /*
    | Transitional catch-all. Holds the pre-package behaviour for every type
    | that has not been extracted yet. It shrinks with each migration and is
    | deleted once the last type owns its package.
    */
    'fallback' => \App\Events\Generic\GenericEvent::class,
];
