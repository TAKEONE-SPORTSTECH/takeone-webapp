# MAP — where everything lives

**Purpose: answer "where is X?" in under thirty seconds, without grepping.**

This is an index, not a specification. It says *where* things are and *what
bites*. It does not repeat the rules — those are in `CLAUDE.md`, and this file
never contradicts it. Audited 2026-09-22.

---

## 1. The 30-second answer

```
Something about a CLUB (its identity, programme, books, staff, comms)
  → app/Clubs/                    views: clubs::
Something about a PERSON (profile, family, health, membership, auth)
  → app/Members/                  views: members::
Something about a COMPETITION (entries, draw, run day, results)
  → app/Events/                   views: event-<key>:: and sport-<sport>::
Something about a MAT or a WALL SCREEN (scoring, boards, pairing)
  → app/Scoreboard/               views: scoreboard::
Something about SELLING (products, variants, stock, orders, perks)
  → app/Shop/                     views: shop::
Something about FOOTAGE (ingest, vaults, transcode, streaming)
  → app/Media/                    (no views of its own)
Member-vs-member duels           → app/Challenges/    views: challenges::
Personal training                → app/Trainers/      views: trainers::
Reading the product in another language → app/Translation/
A model writing, drawing or transcribing → app/Ai/      views: ai::
A form a club built for itself   → app/Forms/         views: forms::
The super-admin's own room, and ops → app/Platform/
```

There used to be **two classes called PlatformController meaning opposite
things**. There is now one: `App\Platform\Controllers\PlatformController`, the
super-admin's screens. The PUBLIC club browser — `/explore`,
`/{country}/clubs/{slug}`, joining, perks, timeline — is
`App\Clubs\Controllers\PublicClubController`, in the module that owns the club.

**The rule of thumb:** a vertical owns a top-level folder under `app/`, and
everything it needs lives inside it. If you are opening files from three
folders to change one thing, you are probably in the wrong one.

---

## 2. Entity index

| Entity | Code | Views | Routes | Storage |
|---|---|---|---|---|
| **Club / tenant** | `app/Clubs/` — `Models/`, `Controllers/`, `Services/`, `Commands/` | `clubs::` → `app/Clubs/resources/views/` (67) | `app/Clubs/routes.php`, `routes-club-admin.php` | `clubs/{slug}/branding\|gallery\|documents\|packages/{id}` |
| **Member / person** | `app/Members/` — plus `Support/` (`ProfilePhoto`, `MemberFilters`) | `members::` (55) | `app/Members/routes.php`, `routes-member.php` | `members/{user-uuid}/profile\|photos\|documents\|payments` |
| **Event / competition** | `app/Events/` — `Models/`, `Support/`, `Sports/<Sport>/<Type>/` | `event-<key>::`, `sport-<sport>::` | registered by the packages | `events/{event-uuid}/branding\|documents\|matches/{id}/clips` |
| **Scoreboard / screens** | `app/Scoreboard/` — `Fleet.php` is the one public door | `scoreboard::` (31) | `routes.php`, `routes-member.php` | — |
| **Shop** | `app/Shop/` | `shop::` (8) | `routes-club-admin.php`, `routes-member.php` | `clubs/{slug}/products/{id}` |
| **Challenges** | `app/Challenges/` | `challenges::` (11) | `routes-member.php` | `challenges/{id}/`, `duels/{id}/media/` |
| **Trainers** | `app/Trainers/` | `trainers::` (2) | `routes.php` | — |
| **Media** | `app/Media/` — `MediaVaults`, `Ffmpeg`, `BoutFilm`, `BoutTimeline` | none | `app/Media/Http/` | `cache/hls/{uuid}/` (derived, disposable) |
| **Translation** | `app/Translation/` | `translation::` (empty) | `routes.php`, `routes-member.php` | — |
| **AI** | `app/Ai/` — `AiManager` at the ROOT, plus `Contracts/`, `Drivers/` | `ai::` (2) | `routes.php` | — |
| **Forms** | `app/Forms/` — `FormEngine` at the root | `forms::` (1) | `routes.php` (`/f/{uuid}`) | — |
| **Platform** | `app/Platform/` — super-admin, activity catalogue, ops commands | `platform::` (28) | `routes.php` | — |

**Module wiring is automatic.** Modules are listed in `config/modules.php` and
bound by `App\Support\Modules\ModuleServiceProvider`. Event packages are
discovered from `app/Events/config/event_types.php` by
`App\Events\EventPackageServiceProvider`. **Never wire a module path by hand.**

---

## 3. Views — read this before you go looking

There are **601 blades / 603 resolvable names**, in two places, and the split
is by history rather than by logic:

- `resources/views/` (347) — the shared tree
- `app/*/resources/views/` (190) + event packages — the module trees

### Where a screen actually is

| Looking for | It is in |
|---|---|
| Club admin section (members, packages, financials, roles…) | `app/Clubs/resources/views/<section>/{index,mobile}.blade.php` |
| A member's own screens (home, schedule, settings, videos) | `app/Members/resources/views/personal/` |
| Event screens (show, bracket, manage, people, divisions) | `app/Events/resources/views/personal/{mobile,desktop}/event-*.blade.php` → `events::personal.*` |
| The marketplace | `resources/views/personal/*market*` |
| Public event page / entry | `app/Events/resources/views/entry/` → `events::entry.*` (own layout + shell) |
| Platform super-admin | `app/Platform/resources/views/` → `platform::` |
| The global activity directory `/activity/{uuid}` | `app/Platform/resources/views/activity/{mobile,desktop}/` |
| Public explore / club pages | `app/Clubs/resources/views/public/` → `clubs::public.*` |
| Mat panels, boards, screens | `app/Scoreboard/resources/views/{bjj,karate,taekwondo}/` |
| Shared components | `resources/views/components/` (126, mostly flat) |
| Layouts (8) | `resources/views/layouts/` |

> Every event file lives in `app/Events/` since 2026-09-23 — controllers, routes, views
> (`events::`), lang (`events::events.…`), config (`app/Events/config/`), migrations.
> Camera and screen pages are `events::camera.*` / `events::screen.*`.

### Naming conventions that are load-bearing

Two filename conventions are read by CODE, not just by people. Break them and
a screen changes without an error:

1. **`<section>/index.blade.php` + `<section>/mobile.blade.php`** — the shape
   `App\Clubs\Support\ClubView::pick()` builds. 20 call sites.
2. **`<feature>/{mobile,desktop}/<view>.blade.php`** — the shape the
   `$isMobile` ternaries build, in ~25 controllers.

---

## 4. Storage — one helper, never a hand-written path

**`App\Support\StoragePath` is the single source of truth.** Every path it
builds has the same shape:

```
{owner}/{owner-public-id}/{purpose}/[{child}/{child-id}/]{generated-name}
```

- Owner first, so one member/club/event is one subtree.
- Public ids (member = uuid, club = slug, event = uuid), never auto-increment.
- Generated filenames. The uploaded name is untrusted metadata.
- Derived files under `cache/` — safe to delete to reclaim disk.

Files are served by `app/Http/Controllers/FileController.php` — the single
door — at `/file/{path}`. **There is no `public/storage` symlink**, so a
`/storage/…` URL in JS is a dead link.

A person's photograph has exactly one home: `members/{uuid}/photos/…`, written
only through `App\Members\Support\ProfilePhoto`. Everything else stores a
reference.

---

## 5. Traps — the things that fail silently

These cost real time. Each one is a bug that does **not** throw.

| Trap | What happens | Where |
|---|---|---|
| **Silent view fallback** | `ClubView::pick()` / `packageView()` ask `View::exists()` and serve the DESKTOP view when the mobile file is missing. No error, wrong screen. | `app/Support/ClubView.php`. Guarded by `tests/Feature/Architecture/ViewResolutionTest.php` |
| **Morph columns store class names** | Move a model with a polymorphic column and every historical row resolves to nothing. | `App\Support\MorphMap` — update it BEFORE the move. Use `$model->getMorphClass()`, never `X::class` |
| **Tailwind bundle is prebuilt** | A class nobody used before (`text-[44px]`, `max-w-[1180px]`) has no CSS and renders as nothing. | verify against `public/build`, or use inline `style=` |
| **Alpine `x-data` in a teleported template** | An inline `<script>` is inert inside `<template x-teleport>`. | register with `@once @push` + `Alpine.data()` |
| **`<x-…>` inside a `<script>`** | Blade parses component tags even in JS comments; breaks the whole view with a misleading "expecting endif". | use `{{-- --}}` comments |
| **Bare `use \App\Traits\X;` inside a class body** | The leading backslash is a trait use, not an import. Stripping it kills the class at autoload. | any moved class |
| **Implicit same-namespace references** | `hasMany(ClubProduct::class)` with no import resolves to the old namespace after a move. No error until the relation is used. | re-check every moved class |
| **NULL birthdate reads as adult** | `$user->birthdate ? age < 18 : false` — a missing birthdate silently removes minor protections. | `PersonalEventController::boutSide()` |

**After moving any class:** `composer dump-autoload`, `config:clear`,
`view:clear`, then confirm the route count is unchanged (**767** as of
2026-09-22) and the moved pages still render.

---

## 6. Still in the legacy flat folders

Audited 2026-09-23, after Clubs, Members and Events were gathered into their own
folders (classes, routes, views, lang files and migrations). **Nothing that
belongs only to Clubs, Members or Events is left outside `app/Clubs/`,
`app/Members/` or `app/Events/`.** What is left in the flat folders belongs to other modules or
to nobody:

| Folder | Files | What they are |
|---|---|---|
| `app/Models/` | 0 | `TenantScope` moved to `app/Clubs/Models/Scopes/` |
| `app/Http/Controllers/` | 7 | Copilot/Languages/MediaVault admin, Impersonation, QR, and the shared `Controller` + `FileController` — the event controllers moved to `app/Events/Controllers/` |
| `app/Services/` | 5 | Copilot (3) + the two documented shared-kernel ones |
| `app/Console/Commands/` | 4 | Media (3), MCP token |
| `app/Traits/` | 5 | Translation (3) + shared upload/file traits |

Clubs/Members things that stay shared ON PURPOSE: the `<x-…>` component
library (`resources/views/components/`), the base layouts and shell partials,
`lang/*/personal.php` (read by the event and scoreboard screens too), the MCP
tools, the tests, and the few migrations that change a clubs table AND a
members table together.

**Where things live inside a module:** `Controllers/` `Commands/` `Services/`
are private (ModuleBoundaryTest); anything another module or a test calls
lives in `Support/`, `Concerns/` (traits), `Requests/`, `Middleware/`,
`Mail/`, `Jobs/`, `Models/`. Sign-in lives in `app/Members/Auth/` — public,
because the public event page signs people in through it.

**Why the controllers are still here, and it is not neglect:** moving a
controller drags its ROUTES with it (ModuleBoundaryTest treats `Controllers/`
as private, and routing is the one legitimate caller). For `Auth/` those are
the login routes; for the event surfaces they sit around
`PersonalEventController`, which imports from seven modules and is not being
moved. Each is its own deliberate step.

**Genuinely shared — these stay put:** `Controller.php` (base),
`FileController.php` (the single door to stored files),
`Models/Scopes/TenantScope.php`, and `Services/{ActivityVideoResearcher,
WhatsAppManager}.php`, both documented in their own headers as shared kernel.

## 7. Known duplication — do not add to it

| What | Where |
|---|---|
| **Two rival running-order boards** | `personal/{mobile,desktop}/event-order` vs `event-run-order`. Unreconciled. |
| Per-sport tournament classes triplicated (~1,160 lines) | `Advancement`, `Arrangement`, `CallNotifier`, `Enrolment`, `Roster`, `RunningOrder` in Karate/Taekwondo/BJJ |
| Per-sport scoreboard trees near-identical | `app/Scoreboard/resources/views/{bjj,karate,taekwondo}/` |
| Six dead controller methods | `PersonalMobileController` — unrouted, naming views that no longer exist |

All of these are registered in `Documentation/HOUSE-CLEANING.md`. Nothing is
deleted in passing.

---

## 8. Conventions worth knowing before you build

- **Check the Blade component library first** (`CLAUDE.md` Part 5) — 126
  components exist; most UI needs one, not a new file.
- **Profile pictures are portrait 3:4**, stored 600×800. Never size a face
  container square.
- **Logos render on transparent backgrounds** — never on a white tile.
- **Every page that introduces a subject opens with the full-bleed hero band.**
- **Every bottom sheet opens with a gradient header band.**
- **No native `<select>` or date/time input.**
- **Flags** come from `<x-flag>` / `fi fi-xx` — never an emoji, never a CDN.
- **No page reload after a write** — patch in place, push over MQTT to others.
- **The MCP server is part of the deliverable**, not an afterthought.

