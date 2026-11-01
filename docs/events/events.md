# Events — package architecture

Every event type is a **self-contained package** owning its data, rules, lifecycle, engine, outputs, financials and screens. The governing rule is in `CLAUDE.md` → *"Events Are Self-Contained Packages — STRICT"*. This document is the practical guide: the contract, how to add a type, and where the migration currently stands. *Rechecked against the code 31 Oct 2026.*

---

## Layout

**A sport owns a folder; each kind of event that sport runs is a sub-folder of it.** One sport has many event types — a Taekwondo club runs tournaments, belt tests, poomsae competitions and gradings — and they all share the sport's weight tables, belt ladder and vocabulary. So the sport is the top level, and each event type is a self-contained package inside it. Event types that are not tied to one sport (Championship, League, Sparring, Open Mat, the generic fallback) live directly under `app/Events/`.

`app/Events/` is also a **module** (CLAUDE.md → *"The Platform Is a Set of Modules"*): its routes, config, migrations, views and lang all live inside it and are discovered by `App\Support\Modules\ModuleServiceProvider`.

```
app/Events/
├── Events.php                       the module (App\Support\Modules\Module)
├── routes.php                       every event route (/me/events/…, /enter, /camera, /screen, /openmat …)
├── config/
│   ├── event_types.php              the ONLY place a package is wired in
│   ├── combat.php                   combat-sport plug-ins (sport key → CombatSport class), bronze rules
│   ├── events.php                   feature flags + tunables (division_merge, shared-base flags, …)
│   ├── event_schema.php             type catalogue + scopes
│   ├── event_notifications.php      notification tunables
│   └── {taekwondo,karate,bjj,bjj_nogi}_divisions.php
├── database/migrations/             the module's migrations (package-owned tables included)
├── resources/
│   ├── views/                       shared event screens → events::…
│   └── lang/{en,ar,…}/events.php    strings shared by the event SYSTEM → events::events.…
├── Contracts/
│   ├── EventType.php                the contract every package implements
│   ├── AwardsDivisions.php          named entry point to (re-)award a division
│   ├── HasNoDraw.php                a type that never has a draw (open mat, sparring)
│   └── ProvidesWorkspace.php        a type that brings its own organiser workspace
├── AbstractEventType.php            shared defaults (core rules, date-driven lifecycle, fee financials)
├── EventTypeRegistry.php            resolves the package that owns an event
├── EventPackageServiceProvider.php  binds every sport + type folder's views and lang
├── Controllers/  Models/  Jobs/  Commands/  Middleware/  …
├── Support/                         COMMON code every package calls (Shared Stays Shared)
│   ├── EnrolmentDecision.php        the answer a package gives to "may this member enter?"
│   ├── SyncsDivisions.php           helper for types whose entrants split into divisions
│   ├── EntryService.php · EventFee.php · EventAccess.php · AudienceResolver.php · EventNotifier.php …
│   ├── Tournament/                  the SHARED combat-championship base (see below)
│   ├── Competition/                 the generic competition engine (recipes, formats, standings)
│   ├── Cameras/                     mat cameras (docs/events/matside-camera.md)
│   └── ClubEntry/ · Budget/ · …
├── Generic/GenericEvent.php         TRANSITIONAL cross-sport catch-all (shrinks with each migration)
├── Championship/                    a championship in ANY sport with a recipe (flag-gated)
├── League/                          a season and a table, for any sport played by sides
├── Sparring/                        the club's training scoreboard
├── OpenMat/                         a scoreboard for a fight nobody planned (docs/events/open-mat.md)
└── Sports/
    ├── <Sport>/recipe.php           ~30 sports with only a recipe (padel, judo, football …), run by Championship
    ├── Karate/  BrazilianJiuJitsu/  combat sports with their own Tournament package, same shape as Taekwondo
    └── Taekwondo/                   ← the sport
        ├── Taekwondo.php                weight tables, classification, bronze rule
        │                                (extends App\Sports\Combat\AbstractCombatSport)
        ├── Classifier.php · divisions.php
        ├── resources/lang/{en,ar}/messages.php
        │                                strings EVERY Taekwondo event shares
        │                                → sport-taekwondo::messages.…
        ├── Tournament/                ← the reference event-type package
        │   ├── Tournament.php           the EventType implementation
        │   ├── Enrolment.php            weight gate + division routing
        │   ├── Advancement.php          bracket progression + medals
        │   ├── Arrangement.php · CallNotifier.php · RunningOrder.php · Roster.php · CompetitorPhoto.php
        │   ├── SharedBase/              the same collaborators on the shared base (flag-gated, see below)
        │   └── resources/
        │       ├── views/               its screens → event-taekwondo_tournament::… (README only today)
        │       └── lang/{en,ar,…}/messages.php
        │                                its strings → event-taekwondo_tournament::messages.…
        └── BeltTest/                  ← the next event type, same sport (not built yet)
```

`EventPackageServiceProvider` discovers both levels from the registry by reflection and binds their `resources/views` and `resources/lang`. Nothing is registered by hand, so a whole sport folder is portable.

**Put a string at the level that owns it:**

| Scope | Home | Namespace |
|---|---|---|
| Every event of this sport (*"Weight category"*, *"Mat"*) | `Sports/<Sport>/resources/lang` | `sport-<sport>::messages.…` |
| One event type (*"Generate draw"*) | `Sports/<Sport>/<Type>/resources/lang` | `event-<key>::messages.…` |
| The event system, all sports (*"Result recorded"*) | `app/Events/resources/lang/{en,ar,…}/events.php` | `events::events.…` |

The only code a type cannot keep inside its folder is its **migrations** — they live in the module's `app/Events/database/migrations/`. Name them for the package (`..._create_belt_test_scores_table.php`, `..._create_open_mat_tables.php`) so the ownership stays obvious.

Engines shared across **several sports** stay outside the sport folders and are **called by** packages, never by a controller:

- `app/Sports/Combat/Engine/DrawEngine.php` — single-elimination draw building
- `app/Sports/Combat/Engine/GroupEngine.php` — pools / groups
- `app/Sports/Combat/Engine/Scheduler.php` — day/mat scheduling + bout numbering
- Known numbering gaps and the fixes still to build: `docs/plans/future-plans.md`
- `app/Sports/Combat/Engine/Results.php` — podium + timeline derivation
- `app/Sports/Combat/CombatSport.php` + `AbstractCombatSport.php` + `SportRegistry.php` — the combat-sport plug-in contract, its base and its registry (`app/Events/config/combat.php`); belts in `BeltLadder` / `BeltRank` / `BeltSystem`, rule books in `app/Sports/Package/`

The engine itself still lives under `app/Sports/` (namespace `App\Sports\Combat`); each combat sport's *implementation* of that contract lives in its own sport folder (`app/Events/Sports/Taekwondo/Taekwondo.php`), so everything Taekwondo — the sport and all of its event types — sits in one place.

### The shared tournament base

`app/Events/Support/Tournament/` is the one copy of the championship collaborators (`Advancement`, `Arrangement`, `CallNotifier`, `Enrolment`, `Roster`, `RunningOrder`, plus `DrawRepair`, `FairDraw`, `FreeEdit`, `LateEntry`, `LiveMove`, `MatNumbering`, `OrderBoard`, `ResultCorrection`, `Transfer` …); a package hands it its lang namespace and its private `Scoring` whitelist. **Brazilian Jiu-Jitsu runs on it.** Karate and Taekwondo each carry a `Tournament/SharedBase/` folder porting them onto it, used only while `events.karate_shared_base` / `events.taekwondo_shared_base` (`EVENT_KARATE_SHARED_BASE` / `EVENT_TAEKWONDO_SHARED_BASE`, default **off**; on in the staging `.env`) is on — the package's own copies one folder up stay the live path until the cut-over (migration started 27 Sep 2026). Retiring those copies is a separate, deliberate step (CLAUDE.md RULE #1, *Shared Stays Shared → Known debt*).

---

## The contract

`App\Events\Contracts\EventType` covers the full vertical. Extend `AbstractEventType` and override only what differs.

| Group | Methods | Purpose |
|---|---|---|
| Identity | `key()` `label()` `profile()` `owns()` | which events this package claims — answer **narrowly** (own type *and* own sport) |
| Schema & input | `validationRules()` `formSections()` `formCatalog()` `columnsFromInput()` `saveRelatedData()` | what it stores and asks for; package columns override core columns, so a type may clear fields it doesn't use |
| Enrolment | `enrolmentGate()` `classifyEntry()` `onEntrantsChanged()` | who may compete and in which division; place an entry that went in unclassified (e.g. after the weigh-in); re-derive state when the entrant set changes |
| Lifecycle | `stage()` `stages()` `canTransitionTo()` | the type's own state machine, enforced server-side |
| Engine | `recordOutcome()` `performAction()` `availableActions()` | record a bout/fixture/test and propagate it; expose the type's own manager actions |
| Notifications | `audienceFor()` `notificationSchedule()` | who hears about it, and when (see below) |
| Outputs | `results()` `allowsManualResults()` `timeline()` `nextUp()` `rosterRows()` | final standings, run-of-show, what is on next, entrant list |
| Financials | `finance()` | how this type earns, including any per-division breakdown |
| Display | `views()` `brandedSurface()` `viewData()` `runData()` `publicView()` `bracketView()` `reloadHallScreens()` | its own screens and the data they need |

`AbstractEventType` also offers hooks outside the contract, e.g. `matPanel()` — one control a type contributes to a sport's scoring table (null by default; Open Mat's NEXT PAIR panel uses it).

**`bracketView()`** returns the type's divisions as brackets, in the one shared shape (`App\Events\Support\BracketView`), for the zoomable bracket screen (`<x-tournament-bracket>`). A knockout draw is not one sport's idea — a taekwondo weight class, a karate pool and a padel cup are the same picture in different words — so the *shape* is shared while each package decides what fills it. `AbstractEventType` implements it for any type whose divisions hold bouts; a type with no bouts (a belt test, a league) returns `[]` and the screen offers no bracket. Hand-arranging the draw is exposed as ordinary package actions (`arrange_draw`, `clear_draw`), so the "may this run right now?" question stays in `availableActions()` and a locked draw simply withdraws them.

**`allowsManualResults()`** is the guard that stops a hand-typed podium contradicting recorded play. Types that derive results from an engine return `false`, and the write path refuses hand-entry with a 422.

**`views()`** may be empty or partial. A declared view is used only when it exists (`PersonalEventController::packageView()`), so a package can take over one screen — or one device — at a time and can never point the app at a screen it hasn't shipped. The three combat Tournament packages still return `[]` — their screens are the shared ones in `app/Events/resources/views/personal/`; Championship, League, Sparring and Open Mat declare their own.

---

## Routing

The controller is a thin dispatcher. Two generic routes (in `app/Events/routes.php`) serve every type's writes, so **adding a type never adds a write route**:

| Route | Delegates to |
|---|---|
| `POST /me/events/{event:uuid}/actions/{action}` → `me.events.action` | `performAction()`, allowlisted against `availableActions()` (deny by default) |
| `POST /me/events/{event:uuid}/outcomes/{unit}` → `me.events.outcome` | `recordOutcome()` |

Every event page is `/me/events/{uuid}` (`me.events.show`) — the one event address since 27 Sep 2026; the old public `/e/{uuid}` address is gone and answers *not found*. See `docs/events/public-entry.md`.

---

## Adding a new event type

1. `app/Events/Sports/<Sport>/<Type>/` — implement `EventType` (extend `AbstractEventType`). Everything below except the migrations goes **inside this folder**. If the sport doesn't exist yet, create `app/Events/Sports/<Sport>/` first, with its `<Sport>.php` (and, for a combat sport, a line in `app/Events/config/combat.php`). A non-combat sport may need nothing more than a `recipe.php` for `App\Events\Championship`.
2. Add its migration(s) in `app/Events/database/migrations/` for any package-owned tables. Type-specific data lives in **its own tables**, not in new columns on `club_events` and not in an unqueryable JSON blob. Core columns (title, dates, location, fees, scope, status) stay shared.
3. `<Type>/resources/views/{mobile,desktop}/` — its screens, following the Design System and the Mobile Pattern Language. Declare them from `views()`; they resolve as `event-<key>::mobile.show`.
4. `<Type>/resources/lang/{en,ar}/messages.php` — its strings, used as `__('event-<key>::messages.…')`. Anything the whole sport shares goes one level up instead (`sport-<sport>::messages.…`).
5. One line in `app/Events/config/event_types.php`, **above** the fallback (order matters: the first package whose `owns()` claims the event wins).
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
- **Time triggers** fire from `php artisan events:send-notifications` (`App\Events\Commands\SendDueEventNotifications`, scheduled hourly in `routes/console.php`). `--dry` lists what is due without sending.

### Safety rails

| Rail | Where |
|---|---|
| **Exactly-once** — unique `(event_id, milestone)` claimed *before* delivery, so cron re-runs, queue retries and racing workers can't double-send | `event_notifications_sent` + `EventNotifier::claim()` |
| **Queued + chunked** — a nationwide announcement never runs inline in the request | `App\Events\Jobs\DeliverEventNotification` |
| **Recipient cap** — hard limit per milestone; anything dropped is recorded in `skipped` **and logged**, never silent | `event_notifications.max_recipients` |
| **Opt-out** — separate switches for announcements vs reminders | `users.notify_event_announcements` / `notify_event_reminders` |
| **Broadcast is a privilege** — only a club owner/admin (or super-admin) may set a scope beyond the host club | `PersonalEventController::assertMayBroadcast()` |
| **Cancelled/archived events go quiet** | checked at fire time *and* again in the job |

Everything is tunable in `app/Events/config/event_notifications.php`. Tests: `tests/Feature/Events/EventNotificationTest.php`.

---

## Migration status

| Type | Package | State |
|---|---|---|
| Taekwondo tournament / championship (and `league` as pools) | `Sports/Taekwondo/Tournament` | ✅ backend extracted, tested (`tests/Feature/Events/TaekwondoTournamentPackageTest.php` and others) — screens still the shared ones (`views()` returns `[]`); `SharedBase/` port behind `events.taekwondo_shared_base` |
| Karate tournament / championship | `Sports/Karate/Tournament` | ✅ package; same shape as Taekwondo (several collaborators still byte-identical copies); `SharedBase/` port behind `events.karate_shared_base` |
| Brazilian Jiu-Jitsu tournament | `Sports/BrazilianJiuJitsu/Tournament` | ✅ package on the shared `Support/Tournament` base; Gi and No-Gi divisions (`divisions/{gi,nogi}.php`) |
| Any other sport's tournament / championship | `Championship/` + `Sports/<Sport>/recipe.php` | ✅ generic competition engine (`Support/Competition`), behind `events.sport_championships` (`EVENT_SPORT_CHAMPIONSHIPS`, default off); claims only events it created |
| League (any non-combat sport played by sides) | `League/` | ✅ own package (season, fixtures, table); imports an event's old `club_events.league` JSON. A `league` on a COMBAT sport goes to that sport's Tournament package instead, with its weight classes cut as round-robin pools |
| Sparring (training scoreboard) | `Sparring/` | ✅ |
| Open Mat | `OpenMat/` | ✅ — `docs/events/open-mat.md` |
| Taekwondo belt test | — (goes in `Sports/Taekwondo/BeltTest/`) | ⛔ **not implemented at all**, despite `belt_test` being a declared type in `app/Events/config/event_schema.php` |
| Race | — | on `GenericEvent` |
| Class | — | on `GenericEvent` (also its schema default) |
| Legacy league data | — | `GenericEvent::leagueView()` still holds the old inline standings maths, reached only by an event left on the fallback |
| Judo, wrestling, boxing, MMA … as combat packages | — | today only as Championship recipes; a full combat package needs a `CombatSport` plug-in (`app/Events/config/combat.php` has `judo` commented out) and should build on `Support/Tournament`, not copy a package |

Suggested order for what is left: **Belt Test** (smallest, and currently missing outright) → **Race** → **Class**. `GenericEvent` is deleted when the last type owns its package.

---

## Known gaps in the surrounding events code

These are **not** package concerns but they sit on the same feature (rechecked 31 Oct 2026):

- `App\Clubs\Controllers\PublicClubController::joinEvent` (`clubs.events.join`) is a second registration path that bypasses the package gate, `EntryService` and fees entirely — it only checks capacity.
- `App\Clubs\Controllers\ClubEventController` is a separate legacy editor that cannot set `event_type`, `sport`, `scope` or `created_by`, so events created there fall to `GenericEvent` and have no manager on the member side.
- ~~Base64 event images bypassed `StoresBase64Images`~~ — fixed 31 Oct 2026 (`ClubEventController::saveEventBase64Images()` now checks the real bytes).
- ~~No event coverage in the MCP server.~~ No longer true: `app/Mcp/Tools/` carries ~45 event tools (`ListEventsTool`, `GetEventBracketTool`, `EnterEventAthletesTool`, `ArrangeEventBracketTool`, budget, certificates, translation, video …).

---

## Related documents

- `docs/events/entry-billing.md` — who may enter athletes, who pays, fees and refunds
- `docs/events/public-entry.md` — the public event page, enrolment from it, and the claim link
- `docs/events/division-merging.md` — merging small divisions, delete-all / auto-place
- `docs/events/open-mat.md` — the Open Mat package
- `docs/events/matside-camera.md` — mat cameras: what is built, replay spec
- `docs/events/sports-event-types-spec.md` — the original June 2026 design (historical; weight-table research)
