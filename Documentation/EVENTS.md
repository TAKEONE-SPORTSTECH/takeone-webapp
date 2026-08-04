# Events — package architecture

Every event type is a **self-contained package** owning its data, rules, lifecycle, engine, outputs, financials and screens. The governing rule is in `CLAUDE.md` → *"Events Are Self-Contained Packages — STRICT"*. This document is the practical guide: the contract, how to add a type, and where the migration currently stands.

---

## Layout

**A sport owns a folder; each kind of event that sport runs is a sub-folder of it.** One sport has many event types — a Taekwondo club runs tournaments, belt tests, poomsae competitions and gradings — and they all share the sport's weight tables, belt ladder and vocabulary. So the sport is the top level, and each event type is a self-contained package inside it.

```
app/Events/
├── Contracts/EventType.php        the contract every package implements
├── AbstractEventType.php          shared defaults (core rules, date-driven lifecycle, fee financials)
├── EventTypeRegistry.php          resolves the package that owns an event
├── EventPackageServiceProvider.php  binds every sport + type folder's views and lang
├── Support/
│   ├── EnrolmentDecision.php      the answer a package gives to "may this member enter?"
│   └── SyncsDivisions.php         helper for types whose entrants split into divisions
├── Generic/GenericEvent.php       TRANSITIONAL cross-sport catch-all (shrinks with each migration)
└── Sports/
    └── Taekwondo/                 ← the sport
        ├── Taekwondo.php              weight tables, classification, bronze rule
        │                              (implements App\Sports\Combat\CombatSport)
        ├── resources/lang/{en,ar}/messages.php
        │                              strings EVERY Taekwondo event shares
        │                              → sport-taekwondo::messages.…
        ├── Tournament/            ← the reference event-type package
        │   ├── Tournament.php         the EventType implementation
        │   ├── Enrolment.php          weight gate + division routing
        │   ├── Advancement.php        bracket progression + medals
        │   ├── Roster.php             how this type reads its entrant list
        │   └── resources/
        │       ├── views/             its screens → event-taekwondo_tournament::…
        │       └── lang/{en,ar}/messages.php
        │                              its strings → event-taekwondo_tournament::messages.…
        └── BeltTest/              ← the next event type, same sport (not built yet)

config/event_types.php             the ONLY place a package is wired in
lang/{en,ar}/events.php            strings shared by the event SYSTEM across all sports
```

`EventPackageServiceProvider` discovers both levels from the registry by reflection and binds their `resources/views` and `resources/lang`. Nothing is registered by hand, so a whole sport folder is portable.

**Put a string at the level that owns it:**

| Scope | Home | Namespace |
|---|---|---|
| Every event of this sport (*"Weight category"*, *"Mat"*) | `Sports/<Sport>/resources/lang` | `sport-<sport>::messages.…` |
| One event type (*"Generate draw"*) | `Sports/<Sport>/<Type>/resources/lang` | `event-<key>::messages.…` |
| The event system, all sports (*"Result recorded"*) | `lang/{en,ar}/events.php` | `events.…` |

The only code a type cannot keep inside its folder is its **migrations** — Laravel loads those from `database/migrations`. Name them for the package (`..._create_belt_test_scores_table.php`) so the ownership stays obvious.

Engines shared across **several sports** stay outside the sport folders and are **called by** packages, never by a controller:

- `app/Sports/Combat/Engine/DrawEngine.php` — single-elimination draw building
- `app/Sports/Combat/Engine/Scheduler.php` — day/mat scheduling + bout numbering
- `app/Sports/Combat/Engine/Results.php` — podium + timeline derivation
- `app/Sports/Combat/CombatSport.php` + `SportRegistry.php` — the combat-sport plug-in contract and its registry (`config/combat.php`)

Each combat sport's *implementation* of that contract lives in its own sport folder (`app/Events/Sports/Taekwondo/Taekwondo.php`), so everything Taekwondo — the sport and all of its event types — sits in one place.

---

## The contract

`App\Events\Contracts\EventType` covers the full vertical. Extend `AbstractEventType` and override only what differs.

| Group | Methods | Purpose |
|---|---|---|
| Identity | `key()` `label()` `owns()` | which events this package claims — answer **narrowly** (own type *and* own sport) |
| Schema & input | `validationRules()` `formSections()` `columnsFromInput()` `saveRelatedData()` | what it stores and asks for; package columns override core columns, so a type may clear fields it doesn't use |
| Enrolment | `enrolmentGate()` `onEntrantsChanged()` | who may compete and in which division; re-derive state when the entrant set changes |
| Lifecycle | `stage()` `stages()` `canTransitionTo()` | the type's own state machine, enforced server-side |
| Engine | `recordOutcome()` `performAction()` `availableActions()` | record a bout/fixture/test and propagate it; expose the type's own manager actions |
| Outputs | `results()` `allowsManualResults()` `timeline()` `rosterRows()` | final standings, run-of-show, entrant list |
| Financials | `finance()` | how this type earns, including any per-division breakdown |
| Display | `views()` `viewData()` `bracketView()` | its own screens and the data they need |

**`bracketView()`** returns the type's divisions as brackets, in the one shared shape (`App\Events\Support\BracketView`), for the zoomable bracket screen (`<x-tournament-bracket>`). A knockout draw is not one sport's idea — a taekwondo weight class, a karate pool and a padel cup are the same picture in different words — so the *shape* is shared while each package decides what fills it. `AbstractEventType` implements it for any type whose divisions hold bouts; a type with no bouts (a belt test, a league) returns `[]` and the screen offers no bracket. Hand-arranging the draw is exposed as ordinary package actions (`arrange_draw`, `clear_draw`), so the "may this run right now?" question stays in `availableActions()` and a locked draw simply withdraws them.

**`allowsManualResults()`** is the guard that stops a hand-typed podium contradicting recorded play. Types that derive results from an engine return `false`, and the write path refuses hand-entry with a 422.

**`views()`** may be empty or partial. A declared view is used only when it exists, so a package can take over one screen — or one device — at a time and can never point the app at a screen it hasn't shipped.

---

## Routing

The controller is a thin dispatcher. Two generic routes serve every type, so **adding a type never adds a route**:

| Route | Delegates to |
|---|---|
| `POST /me/events/{event:uuid}/actions/{action}` → `me.events.action` | `performAction()`, allowlisted against `availableActions()` (deny by default) |
| `POST /me/events/{event:uuid}/outcomes/{unit}` → `me.events.outcome` | `recordOutcome()` |

---

## Adding a new event type

1. `app/Events/Sports/<Sport>/<Type>/` — implement `EventType` (extend `AbstractEventType`). Everything below except the migrations goes **inside this folder**. If the sport doesn't exist yet, create `app/Events/Sports/<Sport>/` first, with its `<Sport>.php` (and, for a combat sport, a line in `config/combat.php`).
2. Add its migration(s) for any package-owned tables. Type-specific data lives in **its own tables**, not in new columns on `club_events` and not in an unqueryable JSON blob. Core columns (title, dates, location, fees, scope, status) stay shared.
3. `<Type>/resources/views/{mobile,desktop}/` — its screens, following the Design System and the Mobile Pattern Language. Declare them from `views()`; they resolve as `event-<key>::mobile.show`.
4. `<Type>/resources/lang/{en,ar}/messages.php` — its strings, used as `__('event-<key>::messages.…')`. Anything the whole sport shares goes one level up instead (`sport-<sport>::messages.…`).
5. One line in `config/event_types.php`, **above** the fallback.
6. Its MCP tool coverage (`app/Mcp/Tools/`, registered in `TakeOneServer`) — a type integrations can't see is an incomplete type.
7. `tests/Feature/Events/<Name>PackageTest.php` — ownership, the enrolment gate, the engine, and an authorization denial.

**No edits to a shared controller, form, or view.** If adding a type forces one, the abstraction is wrong — fix the abstraction rather than adding a branch.

---

## Notifications — who gets told, and when

Two independent axes: **(trigger × audience) → notification**.

### Audience widens with `scope` — shared, not package code

The predicate *practises this activity* is constant; only the geography widens. Resolved by `App\Events\Support\AudienceResolver`.

| `scope` | Reaches |
|---|---|
| `internal` | the host club |
| `inter_club` | clubs within `radius_km` (default 50) of the host — bounding box + haversine |
| `nationwide` | clubs in the host's country |
| `regional` | host country + `notify_countries`, else the configured neighbours |
| `worldwide` | `notify_countries`, or everywhere when none are chosen |

**Deny by default:** if the event's activity can't be matched to any club activity or member skill, a broadcast resolves to **nobody**. Failing to identify the sport must never mean notifying a whole country.

⚠️ *"Practises this activity"* is defined in exactly one place — `AudienceResolver::membersPractising()`. Today it means *holds a skill record for it* **or** *belongs to a club that runs it*. Narrow or widen it there, nowhere else.

### Triggers are declared by the package

`EventType::notificationSchedule()` returns `Milestone` objects. `AbstractEventType` gives every type: `created`, `enrolment_opens`, `enrolment_closing` (lead days before), `enrolment_closed`, `event_day` (early morning, **in the host club's timezone**). The Taekwondo package adds `weigh_in` and `draw_published`. A type with no weigh-in simply doesn't declare one.

- **State triggers** (`at: null`) fire inside the write that causes them — creation announces immediately.
- **Time triggers** fire from `php artisan events:send-notifications`, scheduled hourly. `--dry` lists what is due without sending.

### Safety rails

| Rail | Where |
|---|---|
| **Exactly-once** — unique `(event_id, milestone)` claimed *before* delivery, so cron re-runs, queue retries and racing workers can't double-send | `event_notifications_sent` + `EventNotifier::claim()` |
| **Queued + chunked** — a nationwide announcement never runs inline in the request | `DeliverEventNotification` |
| **Recipient cap** — hard limit per milestone; anything dropped is recorded in `skipped` **and logged**, never silent | `event_notifications.max_recipients` |
| **Opt-out** — separate switches for announcements vs reminders | `users.notify_event_announcements` / `notify_event_reminders` |
| **Broadcast is a privilege** — only a club owner/admin (or super-admin) may set a scope beyond the host club | `PersonalEventController::assertMayBroadcast()` |
| **Cancelled/archived events go quiet** | checked at fire time *and* again in the job |

Everything is tunable in `config/event_notifications.php`. Tests: `tests/Feature/Events/EventNotificationTest.php`.

---

## Migration status

| Type | Package | State |
|---|---|---|
| Taekwondo tournament / championship | `Sports/Taekwondo/Tournament` | ✅ backend extracted, tested (`tests/Feature/Events/`) — screens still shared (Phase 2) |
| Taekwondo belt test | — (goes in `Sports/Taekwondo/BeltTest/`) | ⛔ **not implemented at all**, despite `belt_test` being a declared type in `config/event_schema.php` |
| Football / team league | — (goes in `Sports/Football/League/`) | on `GenericEvent` (standings maths lives in `GenericEvent::leagueView()`) |
| Race | — | on `GenericEvent` |
| Class | — | on `GenericEvent` |
| Other combat sports (karate, judo, BJJ…) | — | on `GenericEvent`; each needs its own `Sports/<Sport>/` folder with a `CombatSport` plug-in (`config/combat.php`) plus a `Tournament/` package, which can largely reuse the Taekwondo package's collaborators |

Suggested order: **Belt Test** (smallest, and currently missing outright) → **League** (lifts the standings engine out of the generic bucket) → **Race** → **Class**. `GenericEvent` is deleted when the last type owns its package.

---

## Known gaps in the surrounding events code

These are **not** package concerns but they sit on the same feature; see the audit for detail:

- `PlatformController@joinEvent` is a second registration path that bypasses the package gate entirely (and is not scoped to the club in its own URL).
- `Admin\ClubEventController` is a separate legacy editor that cannot set `event_type`, `sport`, `scope` or `created_by`, so events created there fall to `GenericEvent` and have no manager on the member side.
- Base64 event images in `Admin\ClubEventController::saveEventBase64Images()` do not go through `StoresBase64Images`.
- No event coverage in the MCP server.
