# TAKEONE Project — Claude Instructions

**How to read this file.** Part 1 outranks everything else; within it, RULE #1 outranks
RULE #0 and RULE #2. Everything after Part 1 is equal-weight unless a section names its
own boundaries. A rule marked **STRICT** is not advice.

| Part | Contents |
|---|---|
| 1 | Prime directives — answers, regressions, backups |
| 2 | The project — stack, architecture, routes, ownership |
| 3 | Security |
| 4 | Architecture rules — components, event packages, sharing, device recovery |
| 5 | Design |
| 6 | Mobile & the Android app |
| 7 | Behaviour — no-reload, realtime, MCP, navigation |
| 8 | Data, uploads & people |
| 9 | Conventions, testing, ops, status |

---

# PART 1 — PRIME DIRECTIVES

## RULE #0 — Answer Questions Short and Simple — STRICT

**When the user asks a question, answer it briefly. Direct answer first, a few lines at most.**

- **Lead with the answer**, not the reasoning that produced it.
- **A few sentences, or a short list.** No multi-section write-ups, no big tables, no
  exhaustive walkthroughs unless explicitly asked.
- **Offer depth instead of delivering it** — "want the detail?" costs one line.
- **Say what matters, drop the rest.** A real risk, blocker or data-loss danger gets one
  line — never buried in an essay, never omitted either.

**This governs ANSWERS, not workmanship.** When asked to build, investigate or fix, do the
work as thoroughly as ever — then report it briefly. Short answers never mean cutting
corners, skipping verification, or staying silent about a real problem.

## RULE #1 — Never Break What Works — STRICT, OVERRIDES EVERYTHING

**This system is live, working, and represents years of accumulated work. It holds real
clubs, real members and real competition footage. No change may break an existing working
feature. Ever. There is no improvement worth a regression.**

If a change cannot be made without risking something that currently works, **stop and ask**
— do not proceed and hope.

- **Additive, never destructive.** New tables and columns, not altered or dropped ones.
  New endpoints alongside old ones. New code paths beside working ones, not rewrites.
- **Never touch working code you were not asked to touch.** No opportunistic refactors,
  cleanup, renames, or "while I was in there". (Reinforces Design Rule #3.)
- **The database holds real data.** No `migrate:fresh`, no destructive migration, no bulk
  delete without an explicit request and a fresh backup.
- **Migrate in parallel, then cut over.** Build the new path, run both, verify, switch, and
  only then retire the old one — as a separate, deliberate step.
- **Feature-flag anything new and risky**, defaulted OFF.
- **Security fixes are not exempt.** Add the guarded path, verify the callers, then close
  the old one.
- **Verify before and after.** State plainly what you verified and what you did not.
- **Small reversible steps** over one large change.
- **When in doubt, ask.** A blocked task is recoverable; a broken production system on an
  event day is not.

> The sibling video platform (`video.takeone.bh`) was **disconnected on 2026-08-27** — the
> integration code, its API credentials and the SSH access were all removed at the user's
> request. It is somebody else's box now. Do not re-add access or write anything that talks
> to it. (See *Match video is ours*, Part 2.)

## RULE #2 — Back Up Before Anything That Could Lose Data — STRICT

**Before any migration, schema change, bulk update/delete, data repair, seeder, reset, or
any operation that could erase or corrupt data — take a database backup FIRST, verify it,
and only then proceed.** No backup → no destructive operation. "It's only additive" is
exactly how data gets lost.

**Triggers:** any `php artisan migrate` (yes, including additive ones); any schema, index or
column-type change; bulk `update()`/`delete()`/truncate/`forceDelete`; seeders; resets
(`takeone:reset-baseline`); demo purge; data-repair scripts; anything touching the storage
or upload folders destructively.

**How:** `php artisan takeone:backup` — SQLite `VACUUM INTO` after a WAL checkpoint
(consistent under load), plus the upload folders, verified by reading each artifact back,
non-zero exit on failure. Scheduled nightly 03:30. Use `VACUUM INTO`, never `cp` — copying
a live SQLite file mid-write yields a corrupt snapshot.

**Media is not covered by default.** Competition footage cannot be regenerated and is too
large to archive nightly. `takeone:backup --media` mirrors it with rsync, but only once
`BACKUP_MEDIA_DEST` points at a NAS mount or remote path. **Until that is set, the video
library has no backup at all.**

**Rules about the backups themselves**
- **Verify every backup** by reading it back before proceeding.
- **Timestamp** them; never overwrite the previous one.
- **Say so** — state that the backup was taken, where it is, and that it verified, before
  reporting the change done.
- Keep at least one copy **off the box** (open item — see the Pre-Launch Runbook).

---

# PART 2 — THE PROJECT

## Overview

Laravel 12 SaaS platform for sports clubs (TAKEONE-SPORTSTECH). Multi-tenant: each club is
a tenant with its own admin panel.

- **Stack:** PHP 8.2+, Laravel 12, Tailwind CSS 4, Vite 7, jQuery 3.7, Chart.js 4,
  Select2 4, Alpine.js 3
- **Custom package:** `takeone/cropper` (GitHub: TAKEONE-SPORTSTECH/laravel-image-cropper)
- **Auth:** Laravel Sanctum + email verification + optional 2FA
- **Roles:** super-admin (platform), club owners/admins, regular members

## Key directories

- `app/Http/Controllers/Admin/` — ClubAdminController, PlatformController, ClubApiController,
  ClubMemberAdminController
- `app/Http/Controllers/` — MemberController, FamilyController, InvoiceController,
  TrainerController, InstructorReviewController
- `app/Models/` — Tenant, User, ClubInstructor, ClubPackage, ClubGalleryImage, ClubFacility,
  ClubActivity, ClubTransaction, ClubMemberSubscription, ClubReview, ClubSocialLink,
  ClubMessage, ClubAffiliation, Role, Permission, Membership, Invoice, Attendance, Goal,
  HealthRecord, TournamentEvent, PerformanceResult, SkillAcquisition, AffiliationMedia,
  NotesMedia, UserRelationship
- `app/Services/` — FamilyService
- `resources/views/` — admin/club/*, admin/platform/*, platform/, auth/, family/, trainer/

**Trainer/Instructor data model:** `bio`, `skills`, `experience_years`,
`is_personal_trainer` live on `User`. `ClubInstructor` holds only club-specific data:
`tenant_id`, `user_id`, `role`, `rating`. Routes `/trainer/{user}` and `/t/{user}` use User
model binding (User ID, not ClubInstructor ID).

## Route structure

| Prefix | Description |
|--------|-------------|
| `/` | Redirect (explore if authed, login if not) |
| `/login`, `/register`, `/forgot-password`, etc. | Auth routes |
| `/mobile/{country}/{slug}` | Public club page (QR code) |
| `/explore`, `/{country}/clubs/{slug}`, `/trainer/{user}` | Authenticated platform browsing |
| `/admin/*` | Super-admin platform management |
| `/admin/club/{club}/*` | Club admin panel |
| `/family/*`, `/member/*` | Member management |
| `/bills/*` | Invoice/billing |
| `/instructor/{instructorId}/reviews` | Instructor reviews |

> New/refactored routes must follow **Unpredictable Resource Identifiers** (Part 3) — public
> keys are non-predictable (uuid/public_id), never the auto-increment `id` or a name slug.

## Match video is ours — the sibling platform is disconnected

Competition video is recorded, stored, transcoded, authorised and played **entirely by this
platform**. A mat camera uploads to `CameraController`, `IngestClipMedia` files it through
`App\Media\MediaVaults`, `TranscodeMedia` builds the HLS ladder, and
`App\Media\Http\MediaStreamController` serves it with authorisation re-checked on every
segment. A bout is watched at `me.events.bout.video`, whose highlights bar is derived by
`App\Media\BoutTimeline` from the officiating log — nobody types a timestamp.

**`video.takeone.bh` was disconnected on 2026-08-27**: `app/Play/*`, the push/upload jobs,
the inbound `/api/integration/play` surface, its console commands, `config/play.php`, the
API token and the SSH key are all gone, and its Sanctum token was revoked. **Do not re-add
any of it**, and do not write code that calls that host.

Two dormant remnants, left deliberately:
- `event_recordings.play_*` columns still hold URLs for a handful of bouts published there
  before the split. Nothing writes them; they render as an outbound link. When they are
  gone, the columns can go too.
- `play_timeline_rounds` / `play_timeline_points` are orphaned mirror tables. Their models
  are deleted and nothing reads them. Dropping them is a migration nobody has needed yet.

---

# PART 3 — SECURITY

## Security First — STRICT

**Security is prioritized in every build, change, action, experiment, prototype, refactor,
trial feature, admin tool, automation and integration — even if the work is temporary,
internal-only, test-only, or "just for now".** There is no "unsafe because it is only a
trial" exception; temporary code becomes permanent code.

**Non-negotiable principles:** secure by default · least privilege · deny by default ·
validate all inputs · authorize every sensitive action · escape output · protect uploads
and file handling · no secret leakage · never trust client-side claims · no "we will secure
it later".

**Applies to all work:** UI features, backend logic, AJAX endpoints, admin tools, MCP
tools, mobile/WebView features, imports/exports, uploads, notifications, realtime events,
internal scripts, demo/trial utilities, and test helpers that touch real environments or
real data.

**These are not acceptable reasons to weaken security:** "it is only a prototype" · "just
for testing" · "only admins will use it" · "this is internal" · "we will harden it later" ·
"it is only temporary".

**If a feature improves speed, convenience, or visual polish but weakens security, security
wins.** Security is a release requirement, a trial requirement, and a development
requirement — never a final polishing step.

## Threat-Driven Security — STRICT

Do not build as if the system will only be used by honest users. Assume attackers will try
to gain unauthorized access, extract data, escalate privileges, abuse APIs, automate
attacks, inject malicious input, upload dangerous files, exploit weak defaults, flood the
system with expensive requests, abuse mobile/browser capabilities, exploit realtime
channels or third-party packages, and chain small weaknesses into a larger compromise.

Account for both classic and modern patterns: OWASP Top 10 and OWASP API risks, credential
stuffing, bot-driven abuse, scraping and mass extraction, supply-chain compromise,
application-layer DDoS, short-burst high-intensity attacks, unrestricted resource
consumption, and insecure business-flow exposure. Attackers automate and scale.

## Attack classes every change is designed against — STRICT

1. **Access control / privilege escalation** — broken access control, IDOR, broken
   object- and function-level authorization, tenant-boundary bypass, role bypass, forced
   browsing. → Enforce authorization server-side on every sensitive action; never trust
   hidden inputs, client-side role flags, or UI visibility; scope every query by
   tenant/club/user; deny by default.
2. **Injection** — SQL, command, template, log, header. → Parameterized queries / Eloquent;
   never concatenate untrusted input into SQL, shell commands, headers or HTML; validate and
   normalize server-side; review every raw expression, shell call and dynamic filter.
3. **XSS / content injection** — stored, reflected, DOM, via rich fields, uploads, URLs or
   profile data. → Escape by default; never render untrusted HTML unsanitized; treat file
   names, captions, notes, bios, links and metadata as untrusted; validate URL schemes;
   reject dangerous upload types.
4. **Auth, sessions, account abuse** — weak flows, session fixation, insecure remember-me,
   brute force, credential stuffing, takeover, reset abuse, enumeration. → Use Laravel's
   auth features properly; throttle auth-sensitive routes; support strong verification and
   optional MFA; avoid leaking account existence through messages or timing; secure session
   and token handling.
5. **CSRF / unsafe state changes** — keep CSRF protection intact on state-changing web
   requests; never move writes onto GET; AJAX write flows keep Laravel's protections.
6. **File upload & media** — malicious files, fake MIME/extension, SVG payloads, path
   traversal, oversized files, storage poisoning, orphaned or publicly-exposed private
   uploads. → Validate real file bytes; reject dangerous formats; generate server-side names
   and paths; never trust user paths or extensions; delete/replace safely; correct disk and
   visibility. See Part 8: *Image Uploads Must Validate Real Bytes*, *Upload Storage
   Structure*, *Delete Files Before Records*.
7. **API abuse & data extraction** — broken API authz, mass assignment, overexposed JSON,
   excessive disclosure, object enumeration, unrestricted resource consumption,
   business-flow abuse, forgotten endpoints. → Return only required fields; scope every
   query tightly; rate-limit and paginate expensive endpoints; treat internal/admin APIs as
   attack surface too.
8. **Business logic abuse** — out-of-order workflows, replay, duplicate submission, bypassed
   approvals, price/amount/status tampering, abuse of invitations, registrations,
   subscriptions, notifications or scheduling. → Validate state transitions server-side;
   never trust client-calculated amounts, statuses or permissions; enforce invariants in
   services/controllers/models; add idempotency or duplicate-submission handling where it
   matters.
9. **Misconfiguration** — unsafe debug settings, permissive storage exposure, weak
   CORS/cookie/session config, verbose errors, stale permissions, unprotected admin
   surfaces, insecure defaults in trial code. → Secure defaults; fail safely when config is
   missing; never expose stack traces, secrets, internal paths or config in responses.
10. **Crypto & secrets** — plaintext secrets, weak credential hashing, token exposure,
    insecure reset/verification flows, keys leaking into logs, JS, responses or the repo. →
    Framework-approved primitives only; never invent crypto; never hardcode secrets;
    minimize secret exposure in logs.
11. **Realtime / websocket / event abuse** — publishing to the wrong users, insecure
    fanout, leaking private payloads. → Scope every push payload to its intended audience;
    send the minimum necessary data; never assume a client should see data just because it
    can receive events; prefer a refresh signal over oversharing when views differ per user.
12. **Mobile / WebView / device capability** — unsafe camera/mic/geolocation use, permission
    misuse, insecure file selection, WebView-specific surface. → Request only necessary
    permissions; feature-detect first; provide safe fallbacks; avoid WebView behaviours that
    expose unsafe download or popup paths.
13. **Bots, scraping, automated attacks** — credential stuffing, fake accounts, abusive form
    submissions, endpoint hammering, availability abuse, API probing. → Throttle sensitive
    routes; rate-limit expensive reads and writes; make enumeration and bulk extraction
    harder; design pagination with abuse resistance in mind.
14. **DDoS & resource exhaustion** — volumetric and application-layer floods, expensive
    query abuse, oversized uploads, queue flooding, notification storming. → Keep endpoints
    efficient; cap and rate-limit expensive operations; paginate; debounce live search;
    avoid N+1 and unbounded queries; reject abusive payload sizes; degrade gracefully.
    **Never create an endpoint that is cheap to call but expensive to execute without
    protection.**
15. **Supply chain** — vulnerable, unreviewed or stale packages, unsafe third-party scripts,
    compromised update paths. → Avoid unnecessary dependencies; never add a library for a
    trivial feature; review security implications before adding any package or CDN.
16. **Logging & incident visibility** — silent failures, undetected abuse, missing audit
    trails, logs leaking secrets or personal data. → Log security-relevant actions; never
    log secrets, tokens, raw sensitive payloads or private files; keep audit trails for
    sensitive admin/member actions.

## Security checklist for every change — STRICT

1. **Input** — validated server-side? enums, IDs and uploads constrained? untrusted values
   normalized?
2. **Authorization** — allowed to perform this action? tenant/club scope enforced? sensitive
   data limited to authorized viewers?
3. **Output** — escaped correctly? any HTML injection possible? JSON minimal?
4. **Uploads** — validated by real bytes/MIME, not extension? dangerous types rejected? old
   files deleted safely on replace/remove?
5. **Secrets** — out of source, responses, logs and client-side code?
6. **Client trust** — server avoids trusting hidden inputs, JS flags, user-supplied
   roles/prices/statuses?
7. **Dependencies** — is a new one truly necessary?
8. **Safe defaults** — does it fail safely when config is missing or ambiguous?
9. **Realtime / background** — MQTT events scoped correctly? nothing leaking to the wrong
   users?
10. **Trial / internal tools** — even if temporary, could this expose data, permissions,
    files or attack surface later?

## Unpredictable Resource Identifiers & Object Access — STRICT

**Never use predictable, enumerable or easily-derived public links** for profiles, records,
files or private objects. Not `/me/ghassanyusuf`, `/member/26`, `/invoice/1001` — nor any
route where changing a name, username, sequence number or nearby value could reveal another
resource.

**Why:** predictable identifiers enable IDOR, unauthorized profile access, private data
extraction, tenant probing, account discovery, scraping, and leak object count, creation
order and naming patterns.

**Required**
- Public keys are high-entropy: `uuid`, `public_id`, `external_id`, or a strong random
  token. Never the auto-increment `id`, a username, a name-derived slug, or an email-like
  identifier for a sensitive resource.
- Route model binding for protected resources uses that non-predictable key. Internal DB
  identity may stay numeric.
- Server-side queries are still scoped by the viewer's permissions, ownership, club, tenant,
  family relationship or admin scope.
- When the user should only reach their own resource, resolve it from the authenticated
  session rather than trusting a URL parameter at all.
- Do not leak whether another valid object exists through differing error messages, timing
  or response details.

> **Unpredictable identifiers are defence in depth, not the defence.** A random identifier
> never replaces authorization. `/people/{uuid}` is the reference implementation.

## Anti-Enumeration and Anti-Extraction — STRICT

- Avoid sequential public identifiers for sensitive records.
- Throttle repeated lookups; log suspicious repeated failures or scanning patterns.
- Return safe, generic not-found / unauthorized behaviour; never reveal whether nearby
  identifiers exist.
- Paginate and scope list endpoints tightly.
- Never let a user switch a parameter and browse another user's data.

**Test requirement:** whenever a route references an object, test that User A cannot access
User B's object by changing the identifier — denied even when the identifier is valid, and
denied whether it is predictable or random.

---

# PART 4 — ARCHITECTURE RULES

## Component-First, and Components Are Standalone — STRICT

**Before building any UI block, script, interaction pattern or layout fragment, check
whether an existing component, partial, helper, widget or view pattern already covers it.**
Start at the **Blade Component Library** (Part 5).

**Decision order:** reuse as-is → reuse with props/slots → extend without breaking current
usage → compose several existing components → create a new reusable component only if none
of those solves it cleanly.

- Never duplicate markup, JS behaviours or visual patterns that already exist.
- Prefer shared Blade components, Alpine widgets, partials and reusable JS helpers over
  page-local one-offs.
- New components are generic enough for reuse unless the requirement is truly one-off, and
  are added to the component library table immediately.
- Keep business logic outside presentational components.

### Standalone means it works anywhere

A component owns its markup, local behaviour, internal state, event binding, guards and
render/update logic, and must work when dropped into another compatible view with no
rewrites. It must **never** depend on hidden page-specific glue: another page's init
script, a random global, a sibling element elsewhere on the page, an inline script in
another file doing the real work, an assumed DOM structure, or undocumented manual setup.
If setup is needed, it is part of the component or an explicit, documented input.

**Allowed shared foundations** (standalone ≠ duplicating the stack): Laravel/Blade, the
Tailwind design tokens, Alpine, jQuery, and the approved shared helpers and shell
behaviours already established in the project.

**Implementation style:** keep a component's JS inside the component file or a clearly named
companion module; use clear, documented event names; require or generate stable IDs; own the
AJAX request flow and DOM patching (or expose a defined API for it); keep animation inside
the component.

**Reuse test:** can it be used on another page without rewriting internals? does it depend on
hidden external script or page-only structure? are its inputs and outputs clear? can it
initialize itself safely? will it behave consistently everywhere? If any answer is no, it is
not standalone yet.

## The Platform Is a Set of Modules — STRICT

**Every vertical owns a top-level folder under `app/` and everything it needs lives
inside it.** Not one folder of models, one of controllers and one of services with
the shop, the roster and the books sitting side by side as neighbours that have
nothing to do with each other.

```
app/
├── Clubs/        the tenant: identity, presence, programme, books, comms
├── Members/      the person: profile, family, health, membership, roles
├── Events/       competition (itself made of sport/event-type PACKAGES)
├── Shop/         commerce: products, variants, stock, orders, perks
├── Challenges/   member-vs-member: challenges, duels, witnesses
├── Trainers/     personal training: profiles, rates, reviews, sessions
├── Media/        footage: ingest, vaults, transcode, streaming
└── Support/Modules/   the module system itself
```

**Why:** at ~106k lines, organising by TECHNICAL KIND means reading or changing one
vertical requires opening files belonging to five others, and nothing tells you where
a vertical stops. A module boundary answers that: everything about the shop is
`app/Shop/`, and a change there cannot reach the club's books without crossing a line
you can see.

### A module is a boundary, not a plugin

This is the one thing not to confuse with the events rule below. An event **type** is a
*substitutable variant behind one contract* — nothing depends on Taekwondo, everything
depends on `EventType`. A module is **not** substitutable: there is no second kind of
club. It exists to be **bounded** — read, reviewed, changed or deleted as a unit.

Consequence: modules nest. `app/Events/` is a module; the sport and event-type packages
inside it are a level below, bound by `EventPackageServiceProvider`. Do not flatten one
into the other.

### The structure

```
app/Shop/
├── Shop.php                    implements App\Support\Modules\Module
├── Models/  Controllers/  Services/  Commands/
├── routes.php                  plain `web`
├── routes-member.php           → /me prefix, `me.` names, auth stack
├── routes-club-admin.php       → admin/club/{club}, `admin.club.` names
└── resources/views|lang/       → shop::<view>, shop::messages.…
```

All of it discovered from `config/modules.php` and bound by
`App\Support\Modules\ModuleServiceProvider`. **Never wire a module path by hand.**
Route files are registered inside `web` with the exact middleware stack the shared
block applies — a module must never quietly opt out of session, CSRF or auth.

### Contributing to a shell

A module puts entries in a shell's navigation by implementing that shell's **surface
interface** (`App\Support\Modules\Surfaces\`), not by being edited into a layout.
`ContributesClubAdminNav` is the first; the club admin sidebar is composed from it on
both desktop and mobile.

- **A module with nothing to say to a shell does not implement the interface.** That is
  the honest version of "this vertical has no club settings".
- **`group` is separate from ownership.** Instructors are club programme code that reads
  under "People"; the shop's perks read under "Content" on mobile. A module says where
  its entry belongs **to the reader** and keeps its code where it belongs to the
  **codebase**. Groups themselves are shell property, declared in `config/modules.php`.
- **Desktop and mobile are separate navigations**, not one at two widths.

### Public vs private — enforced by test

- **`Models/` is PUBLIC.** Verticals legitimately relate to each other's records.
- **`Controllers/`, `Commands/`, `Services/` are PRIVATE.** Nothing outside the module
  may reference them. `tests/Feature/Modules/ModuleBoundaryTest.php` fails the build if
  anything does. **The fix is never to add an exception** — move the caller in, or give
  the module a named public entry point and call that.

### ⚠️ Moving a class between modules

Two hazards, both of which have already bitten during this migration:

1. **The database stores class names.** A polymorphic `*_type` column held literal
   `App\Models\Tenant`, welding the audit trail to the namespace — move the class and
   every historical row resolves to nothing, silently. **`App\Support\MorphMap` is the
   fix and must be updated BEFORE a model with a morph column moves.** Never hand-write
   `X::class` into a morph column; use `$model->getMorphClass()`. Guarded by
   `tests/Feature/Modules/MorphMapTest.php`.
2. **Implicit same-namespace references.** `Tenant.php` says `hasMany(ClubProduct::class)`
   with no import, because they were siblings. Move `ClubProduct` and that resolves to a
   namespace it no longer lives in — no error until the relation is used. Every moved
   class must be re-checked for bare references and given explicit imports.
   Related: a leading backslash on `use \App\Traits\X;` is load-bearing **inside a
   class body** (it is a trait use, not an import) — stripping it kills the class at
   autoload.

**After any move:** `composer dump-autoload`, `config:clear`, `view:clear`, then verify
the route table count is unchanged and the moved pages still render.

### Adding a module

Create `app/<Name>/`, implement `Module`, add one line to `config/modules.php`, ship its
routes, views, migrations, MCP tools and tests. Removing one is the same three things in
reverse.

> Test: *could this vertical be deleted by removing its directory, its tables and its one
> registry line — with the rest of the platform still working?*

---

## Events Are Self-Contained Packages — STRICT

**Every event type is its own code package** — a self-contained vertical owning its data,
rules, lifecycle, inputs, outputs and screens. An event type is NEVER a set of `if`/`match`
branches inside a shared controller, form or view. A Taekwondo Tournament, a Belt Test and
a Football League are three products that happen to share a calendar entry.

### What a package owns (all of it, or it isn't done)

1. **Schema** — its type-specific fields and validation rules.
2. **Creation input** — the fields its own create/edit form asks for.
3. **Enrolment rules** — who may register, in what role, what gates apply (weight class,
   belt rank, age, squad size), and what a registration means for this type.
4. **Lifecycle / state machine** — legal states and transitions
   (`draft → enrolling → weigh-in → draw locked → running → completed`), validated
   server-side inside the package.
5. **Processing / engine** — the domain logic: building a draw per weight class, advancing a
   winner, computing bronze from both semi-finals, scoring test items, computing a league
   table.
6. **Outputs** — results, podium/medals, grades, standings, and how they are written back to
   the member's profile.
7. **Financials** — how revenue is derived for this type, so the event P&L is correct.
8. **Display** — its own create form, detail page and run-day screens, **mobile and
   desktop** (per Mobile / Desktop Separation).

### Required structure — sport folder, event types inside it

A sport owns a folder; each kind of event that sport runs is a sub-folder. One sport has
many event types, and they share the sport's weight tables, belt ladder and vocabulary — so
the sport, not the event type, is the top level.

```
app/Events/Sports/<Sport>/
├── <Sport>.php                            the sport itself: weight/belt tables,
│                                          classification, sport-level conventions
├── resources/lang/{en,ar}/messages.php    strings EVERY event of this sport shares
│                                          → sport-<sport>::messages.…
├── <Type>/                                one event type
│   ├── <Type>.php                         the EventType implementation
│   ├── …                                  its collaborators (gate, engine, roster)
│   └── resources/
│       ├── views/{mobile,desktop}/        its screens → event-<key>::<view>
│       └── lang/{en,ar}/messages.php      its strings → event-<key>::messages.…
└── <OtherType>/                           another event type, same sport
```

- Both levels are bound automatically by `App\Events\EventPackageServiceProvider`, which
  discovers folders from the registry. **Never wire a package path by hand.** Use
  `view('event-<key>::mobile.show')`, `__('event-<key>::messages.…')` and
  `__('sport-<sport>::messages.…')`.
- **Put a string at the level that owns it:** shared by every event of the sport → the sport
  folder; used by one type → that type's folder; shared by the event SYSTEM across sports →
  `lang/{en,ar}/events.php`.
- Cross-sport types not tied to a sport (the generic fallback) live at `app/Events/<Name>/`.
- Only **migrations** may live outside the folder — name them for the package.
- **Adding, moving or deleting a type is adding, moving or deleting one directory** plus its
  one line in `config/event_types.php`; the same for a whole sport.
- Every package implements the shared **`EventType`** contract and is registered in the
  event-type registry — `app/Sports/Combat/SportRegistry.php` (`CombatSport` /
  `AbstractCombatSport` / `Taekwondo`) is the precedent. Generalise it; do not invent a
  second mechanism.
- Shared engines that genuinely serve several types stay in a common namespace and are
  **called by** packages, never called directly from a controller.

### Storage model

**Core columns stay shared** on `club_events` (title, dates, times, location, host club,
scope, status, capacity, fees) so listing, search, calendar, permissions and financial
roll-ups stay uniform. **Type-specific data lives in package-owned tables** (e.g.
`event_matches` / `event_categories`, a belt-test scores table, a league fixtures table). Do
not keep bolting type-specific columns onto `club_events`, and do not dump structured type
data into a generic JSON blob when it needs to be queried, joined or validated.

### Controllers are thin dispatchers

Controllers resolve the event's type from the registry and delegate. **Forbidden:**
`if ($event->sport === 'taekwondo')` (or any sport/event_type string comparison) in a
controller, service or view outside that type's package; a single create form with `x-show`
branches per type; a shared detail/run-day view with per-type sections toggled inline;
domain logic (standings, bracket maths, scoring) inline in a controller method.

### Adding a new event type

Only: create the directory, implement the contract, register it, add views, add migrations
for package-owned tables, add MCP tool coverage, add tests. **No edits to a shared
controller, form or view.** If adding a type forces you to edit shared code, the abstraction
is wrong — fix the abstraction, don't add the branch.

### Applies to existing types too

Not future-only. `PersonalEventController` still hardcodes Taekwondo by name in places,
computes football league standings inline, and has no belt-test implementation despite
`belt_test` being a declared type. **Taekwondo is the reference migration.** Any change to
an existing type moves logic toward its package, never deepens the branching.

### Boundaries this rule does NOT override

Security (a package is a full attack surface and satisfies every Part 3 rule
independently) · MCP sync (a new type ships with its MCP coverage in the same change) ·
Design-First, the Mobile Pattern Language and the mobile/desktop split · Component-First
(packages reuse the shared component library — "self-contained" means owning its *domain*,
not duplicating the design system).

> Test: *could this event type be deleted by removing its one directory, its tables and its
> registry line — with nothing else breaking?* If not, it isn't self-contained yet.

## Shared Stays Shared, Private Stays Private — STRICT

**Anything COMMON across sports, event types or systems exists in exactly ONE place and is
used identically everywhere. Anything genuinely SPECIFIC to one sport stays inside that
package.** Copy-pasting a shared mechanism into a second package is a defect — even when
the copy is made "so the package stays self-contained".

This is the counterweight to the packages rule: a package owns its *domain*, not its
*plumbing*. It is self-contained because it can be deleted cleanly, not because it carries
its own copy of everything.

### The test — two questions, in this order

1. **Would this behave the same for a different sport?** → COMMON: it belongs in
   `app/Events/Support/` (or `app/Sports/Combat/Engine/`, or the shared component library)
   and every package calls the one copy.
2. **Does a rule book, a governing body, or a mat design decide it?** → PRIVATE: it stays in
   the package.

If a class differs between two packages only by its namespace and its lang prefix, it was
never private. Two packages differing by a handful of constants means ONE shared class
taking those constants from the `EventType`.

**COMMON (call it, never re-implement it):** bout camera / footage
(`app/Events/Support/Cameras/*`) · brackets and the draw (`App\Events\Support\BracketView`
feeding `<x-tournament-bracket>`; `app/Sports/Combat/Engine/{DrawEngine,Scheduler,Results}`)
· participation and entry (`EntryService`, `EnrolmentDecision`, `EventFee`, `EventAccess`,
`RosterPeople`) · management surfaces (the console, milestones, documents, `EventNotifier`,
`MatchEventLog`, `AudienceResolver`) · hall screens and pairing (`HallScreenRouter`,
`PendingScreen`, `ScreenPairingController`, `ScreenMedia`) · run-day flow (running order,
arrangement of the draw, advancement, calling competitors, reading the entrant list).

**PRIVATE (stays in the package):** `Scoring` (what counts as a point, how a bout is won) ·
divisions (weight bands, belt/rank ladders, age groups) · medal convention (one bronze or
two, third-place match or not) · the board's design, vocabulary and language files.

### Non-negotiables

- **Never fork a shared class to change it for one sport.** Add the seam — a parameter, an
  abstract method, a value from the `EventType` — and let every package pass its own value.
- **Never name the same thing differently per package.** `CourtDisplay` in one sport and
  `HallScreen` in another guarantees drift and guarantees the next person copies.
- **Divergence is the real cost.** A fix lands in one copy and the other becomes a bug
  nobody knows about. This has already happened here.
- **Cross-sport is the default assumption** for anything built for a bracketed,
  medal-awarding competition, unless a rule book says otherwise.
- Applies beyond sports: any two systems needing the same behaviour get one implementation,
  parameterised.

### Known debt (audited 2026-08-30 — do not add to it)

`Advancement`, `Arrangement`, `CallNotifier`, `Enrolment`, `Roster` and `RunningOrder`
(~1,160 lines) are triplicated across Taekwondo, Karate and Brazilian Jiu-Jitsu,
byte-identical apart from namespace and lang prefix. The hall-screen layer is duplicated
under two names (Karate/Taekwondo `CourtDisplay/`, BJJ `HallScreen/`) and has **already
diverged** (490 vs 721 lines). The fix is a shared `app/Events/Support/Tournament/` base
taking the package's lang namespace and `Scoring` from its `EventType`, plus one shared
hall-screen controller/device/channel with the board view supplied per sport. **Agreed
approach: extract the base and point BJJ at it first** (not in the field yet, so Karate and
Taekwondo carry no risk), then port those two as separate deliberate steps. Not started — it
needs an explicit go-ahead under RULE #1.

## Unattended Devices Must Always Recover — STRICT

**A device with no keyboard — a wall screen, a scoring tablet, a camera on a tripod — must
never reach a state it cannot leave by itself.** Every page such a device can land on either
draws something useful or sends it back to the pairing room. **A page-rendering route may
not `abort(404)` on a token, a pairing, or an event.** Ever.

**Why this keeps happening:** the identity a device holds is a row on the server, and that
row goes away for ordinary reasons — unpaired, revoked, event deleted, database re-seeded,
enrolled against a different host. The device reopens the one address it remembers after a
power cut at 8am on an event day, and whatever answers is the whole of its world. A 404
there is not an error message, it is a **brick**: no back button on a television, no address
bar in a kiosk WebView. This has cost real time twice.

**The three shapes, and the answer each takes**

1. **A page a device renders** (board, overlay, console, waiting room) →
   `return redirect()->route('screen.new')`. Never `abort`. The waiting room issues a fresh
   code, which is a state somebody in the hall can act on.
2. **A JSON endpoint a device polls** (`status`, `config`, `payload`, `link`) → `404` is
   correct and required: it is the ONLY way the client can tell "this identity is gone" from
   "the wifi dropped". The client owes a matching response — **404 → discard the stored
   token and re-enrol**; a timeout, a 500 or a captive portal leaves the identity alone. See
   `CameraApi::configResult()` / `_CameraStationState._sync()` and the `<x-screen-pairing>`
   poll.
3. **A hand-off between two device pages** — the sender must ask whether the destination
   will actually open before redirecting. `tokenControl()` sends a console it cannot open
   back to the board, so the board must NOT send it straight back: guard with
   `canServeControl($device)`. Two pages each redirecting to the other is an infinite loop
   on a screen nobody can stop. This shipped once in the BJJ package because the guard was
   dropped when the file was copied from Karate.

**Non-negotiables**
- **One answer for every failure.** A bad token, a revoked device and an unpaired one get
  the SAME response — differing replies tell a stranger which tokens are real.
- **Recovery goes to `/screen`**, the sport-neutral room — never a package's own pairing
  code, which only that sport's events can claim.
- **Every redirect chain must terminate.** Prove it: follow it to a page that renders.
- **A stale code gets an honest message** — "No camera is waiting with that code", never
  "that code belongs to a screen" when the code belongs to nothing.
- **The client half is part of the fix.** A server answering 404 correctly and an app that
  ignores it is still a bricked device.

**Verify with** `/tmp/takeone-smoke.php` (source in the session scratchpad): boots the app
and asserts all of the above for every sport — dead-token recovery on every page route, 404
on every JSON door, and a full camera pair. Extend it when a package adds a device surface;
run it as `www-data`. It opens a transaction it always rolls back, so it writes nothing.

> This rule and *Shared Stays Shared* are the same lesson from two directions: the recovery
> logic is COMMON, and every time it was copy-pasted into a new sport, a guard went missing.

## Frontend Technology Decision Rules — STRICT

The project uses Blade, Tailwind, Alpine, jQuery, AJAX/fetch, Chart.js, Select2 — and React
only when explicitly justified. **Prefer the simplest technology that fits, while preserving
performance, maintainability and harmony with the existing codebase.**

- **Blade** — server-driven page structure, reusable layout/view components, initial page
  state, mostly static or form-based features.
- **Alpine** — dropdowns, modals, tabs, sheets, accordions, inline UI state local to one
  rendered region.
- **jQuery + AJAX** — updating existing Laravel views in place, submitting forms without
  reload, patching DOM regions after API responses, and anything inside admin/member views
  that already uses this pattern.
- **React only when** the feature is truly state-heavy or component-driven, would be brittle
  in Blade + Alpine + jQuery, benefits from isolated client-side state, and can be
  introduced without fragmenting the product. **Do not** reach for React for simple
  CRUD/forms/modals, and never create a parallel frontend architecture unless explicitly
  requested. Any React usage still follows the same design system, routes, API contracts and
  no-reload rules.

**Universal:** no unnecessary page refreshes · in-place updates after writes · reusable
components first · the same product feel across every view.

## Optimization, Speed, and Harmony — STRICT

Optimize from the start, not as cleanup. **Priorities: speed → harmony → reuse →
maintainability → clarity → scalability.**

**Speed:** in-place updates over reloads · minimal DOM work · reuse components instead of
duplicating heavy markup · partial rendering for complex sections · debounce live search ·
throttle expensive listeners · lightweight animations · small reusable modules over
page-specific monoliths.

**Harmony:** the platform must feel like one product — same visual language, spacing rhythm,
interaction logic, component behaviours, naming conventions and UX expectations everywhere.

**Avoid:** duplicate components solving the same problem · duplicate JS behaviours with
slightly different names · inconsistent UI patterns for the same action · unnecessary
framework mixing · animations that reduce responsiveness · refactors that reduce consistency
with established patterns.

---

# PART 5 — DESIGN

## Design-First: Creative, Innovative, Artistic, Modern — TOP PRIORITY, STRICT

**Every piece of UI built in this project must be creative, innovative, artistic and modern.
Never ship plain, default-looking or "good enough" UI.**

- **Design is a requirement, not a polish step.** Any new page, component, modal, sheet,
  card, list, empty state or loading state must look intentionally designed — considered
  composition, rhythm, hierarchy, motion and detail.
- **No generic AI/template aesthetics.** No stock boxy card grids, no unstyled form stacks,
  no bare tables. Bring art direction: layered depth, purposeful whitespace, expressive
  typography scale, meaningful iconography, tasteful micro-interactions.
- **Use the `frontend-design` skill for any new UI work** and follow the Design System
  tokens below exactly.
- **Modern means:** fluid responsive layouts, smooth Alpine/CSS transitions, thoughtful
  states (hover / focus / active / empty / loading / error), and a mobile experience that is
  animated and creative — never a stripped-down desktop.

**Boundaries this does NOT override:** Design Rule #1 (never redesign existing UI unless
asked — creativity applies to what you are *building*) · the Design System palette,
typography and tokens (be creative *within* the system) · Security, Component-First reuse,
and the No-Reload / Realtime rules. If a visual flourish weakens security or performance, it
loses.

> Ask on every UI task: *would a great product designer be proud to ship this screen?*

## Design Rules — STRICT

### 1. Never modify existing UI design
Do not change existing layout, styling, colors or visual structure unless **explicitly
instructed**. When adding features, reuse the existing HTML structure and class patterns
exactly. No new visual elements, color changes or layout shifts as a side effect of logic
changes.

### 2. Reuse existing patterns
Match the exact patterns already used in the same view — same button styles, same card
structure, same spacing classes. No speculative improvements.

### 3. No unsolicited refactors
Do not clean up surrounding code, add docstrings, rename variables or reorganize logic
unless asked.

### 4. No native OS-rendered form popups (`<select>`, `<input type="date|time">`)
Never use a native `<select>`/`<option>`, or a native `<input type="date">` / `type="time"` /
`type="datetime-local"`, when the control is part of the styled UI — the OS renders those
popups with its own sharp-cornered chrome and they cannot be themed. Build a custom
**Alpine** control instead: a `rounded-xl` bordered trigger button (chevron that rotates on
open, purple focus ring) plus an absolutely-positioned `rounded-xl` panel
(`bg-white border border-gray-100 shadow-lg overflow-hidden`) with a fade/slide
`x-transition`, `hover:bg-muted/60` rows and a `text-primary` highlight. Close on
`@click.outside` and `@keydown.escape`. Keep the panel as smooth/rounded as the trigger.
Reuse an existing `<x-*-dropdown>` / `<x-select-menu>` / `<x-date-picker>` when one fits;
otherwise match the reference implementations in
`resources/views/personal/challenge-create.blade.php` (Win condition / Stake dropdowns, and
the Deadline calendar popover — stores an ISO `YYYY-MM-DD`, disables past days, has Clear /
Today actions).

### 5. Logos render on transparent backgrounds — never on a white tile
Club, brand and business/chain logos are transparent PNGs and MUST be shown as the bare
image on a transparent background. Never wrap a logo in a white/filled rounded card, tile or
chip (`bg-white`, `p-1`, `shadow`, `ring`, `rounded-2xl overflow-hidden`) — that white square
looks broken against non-white/hero/photo backdrops. Use only a sizing container plus the
image: `<span class="w-16 h-16 flex-shrink-0"><img class="w-full h-full object-contain" …></span>`.
No background fill, no padding box, no ring/shadow. Applies to every logo placement.

### 6. Page headers are full-bleed hero bands — never a small rounded card

**THE STYLE IS FIXED. The CONTENT is yours.** Change the title, the chips, the
owner line, which actions sit on the right, the subject's colour — never the
classes, the structure, the spacing or the order. Every page on the platform,
mobile and desktop, opens with this same band, so a reader arriving from
anywhere already knows where the back button is and what the page is about.
Every page that introduces a subject (an event, a club, a member, a console) opens with the same **hero band**. It runs edge to edge, the content below rides up over its tail, and the page's identity — chips, title, owner — sits *under* the control row, not squeezed beside a back arrow.

**The pattern** (reference: `personal/{mobile,desktop}/event-show.blade.php` cover, and both `event-manage` consoles):

```blade
<header class="m-hero -mx-4 -mt-4 px-5 pt-5 pb-16 text-white relative overflow-hidden"
        style="background: linear-gradient(150deg, {{ $color }}, {{ $color }}b0);">
    <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
    <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

    {{-- Control row: back on the left, actions on the right. z-50 so a dropdown
         paints above the title block. --}}
    <div class="flex items-center justify-between relative z-50"> … </div>

    {{-- Identity: chips, then the big title, then who it belongs to. --}}
    <div class="relative z-10 mt-6">
        <div class="flex items-center gap-1.5 flex-wrap"> …chips… </div>
        <h1 class="text-2xl font-black mt-3 leading-tight">{{ $title }}</h1>
        <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5"><i class="bi bi-building"></i>{{ $owner }}</p>
    </div>
</header>

{{-- Content rides up over the band's tail --}}
<div class="-mt-10 relative z-10 space-y-4"> … </div>
```

**Non-negotiables**
- **Full-bleed.** Cancel the page wrapper's padding on the band (`-mx-4 -mt-4`, or `-mx-4 sm:-mx-6 lg:-mx-8 -mt-6` on desktop) and restore it on the content beneath. A header that stops short of the screen edge is wrong.
- **Gradient is `colour → colour+b0`**, not `colour → #1f2937`. The subject's own colour, lightened — not faded to charcoal.
- **Two soft circles** (`bg-white/10`) for depth. They are part of the pattern, not decoration to drop.
- **Bottom padding sized to what overlaps it** — `pb-16`/`pb-20` when a card rides up over the tail, less (≈`pb-10`) when the thing straddling the edge is a compact control like a filter tray. The band should end just under what overlaps it, never leave a strip of empty colour between the title and the first element.
- **Title is `text-2xl font-black mt-3 leading-tight`** on its own line, with chips above and the owner line below. (Both breakpoints — the desktop band does NOT go bigger.)
- **Round 40px controls**: `w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center`.
- **Back is a ROUND 40px CONTROL holding a TAIL-LESS ARROW, and nothing else** — `w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur inline-flex items-center justify-center text-white`, holding `<i class="bi bi-chevron-left rtl:rotate-180"></i>`. **`bi-chevron-left`, never `bi-arrow-left`** — the tailed arrow is out everywhere on the platform (decided 2026-09-04). No label: the destination travels in `aria-label` + `title` so a pointer and a screen reader still name it. This REPLACED the labelled pill this rule used to require; the pill's own classes (`gap-2 h-10 ps-3 pe-4`) must not come back. Back therefore looks like the action controls opposite it, and is told apart by its position: back is always the leading edge, actions the trailing one.
  - The two places a back arrow may still carry words: a **wizard step** button paired with a Next ("Previous", "Back" inside a multi-step modal), and a **Cancel** that happens to wear an arrow. Those are form controls, not page navigation.
- **Actions cluster on the right**, in this order where they exist: console (`bi-sliders`, only when the viewer may manage) → `<x-qr-code>` (`button-class` set to the round-control classes) → share. Nothing else lives in that row.
- **The subject's chips carry its identity**, not the control row: a screen's own label ("Draw", "Officials") is a chip in the identity block, not a badge floating opposite the back button.

> **This band is the standard header for EVERY page that introduces a subject** — event, draw, roster, officiating sheet, club, member, console. Reference implementations: `personal/mobile/event-show.blade.php` and `personal/mobile/event-bracket.blade.php` (and `personal/desktop/event-show.blade.php` for the desktop measurements). A new screen copies that band; it does not invent a header of its own.

**The DESKTOP band, verbatim.** Copy this; do not re-derive it. Reference
implementation: `personal/desktop/event-show.blade.php`.

```blade
<div class="-mx-4 sm:-mx-6 lg:-mx-8 -mt-6 overflow-hidden shadow-sm mb-6 text-white relative"
     style="background: linear-gradient(150deg, {{ $color }}, {{ $color }}b0);">
    <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
    <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

    {{-- Inner padding mirrors the page wrapper's, so the hero text stays on the
         same vertical axis as the content below it at every breakpoint. --}}
    <div class="relative px-4 sm:px-6 lg:px-8 py-6 sm:py-8">

        {{-- Control row: labelled back pill left, round actions right. --}}
        <div class="flex items-center justify-between gap-2 mb-4">
            <a href="{{ $backUrl }}"
               class="inline-flex items-center gap-2 h-10 ps-3 pe-4 rounded-full bg-white/15 border border-white/25 backdrop-blur text-white text-sm font-semibold hover:bg-white/25 transition-colors">
                <i class="bi bi-arrow-left rtl:rotate-180"></i>{{ $backLabel }}
            </a>

            <div class="flex items-center gap-2">
                {{-- console → QR → share, each: --}}
                <a href="…" title="…"
                   class="w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center hover:bg-white/25 transition-colors">
                    <i class="bi bi-sliders text-base"></i>
                </a>
            </div>
        </div>

        {{-- Optional state banner, above the identity block. --}}
        <div x-show="cancelled" x-cloak
             class="mb-4 rounded-xl bg-white/20 backdrop-blur px-3 py-2 text-xs font-bold flex items-center gap-2">
            <i class="bi bi-exclamation-triangle-fill"></i> {{ __('…') }}
        </div>

        {{-- Identity: chips, the title, then who it belongs to. --}}
        <div class="flex items-center gap-1.5 flex-wrap">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                <i class="bi {{ $icon }}"></i> {{ $label }}
            </span>
        </div>
        <h1 class="text-2xl font-black mt-3 leading-tight">{{ $title }}</h1>
        <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5">
            <i class="bi bi-building"></i>{{ $owner }}
        </p>
    </div>
</div>
```

**Desktop specifics that differ from the mobile band**, and are not negotiable
either: the wrapper carries `mb-6` and `shadow-sm` instead of a deep `pb-*` tail
(nothing rides up over the desktop band); the inner padding is
`px-4 sm:px-6 lg:px-8 py-6 sm:py-8` so the title sits on the same vertical axis
as the content beneath; controls use `hover:bg-white/25 transition-colors` where
mobile uses `m-press`.

**A page with no subject colour** — a platform hub like `/me/videos` rather than
one club's event — uses the shared `m-hero` mesh instead of an inline gradient,
and has no back pill, because it is a top-level destination rather than a
drill-down. Everything else about the band is identical.

**Never** open a page with a small `rounded-2xl p-4` gradient card holding a back arrow and a squeezed title, and never with a gradient stat card standing in for a header. Those are *cards* — fine inside the page, never as its header. Never open one with a bare `<h1>` over the page background either.

**The one standing exception**, because the user asked for it explicitly: the bout
review screens (`personal/{mobile,desktop}/bout-video.blade.php`) are the
standalone designs in `drafts/`, used verbatim with their own dark shell and their
own header. They are outside the app shell entirely. Do not "fix" them to this
band, and do not treat them as licence to invent a header anywhere else.

### 7. The bracket icon is always rotated 90° clockwise
`bi-diagram-3` (and `bi-diagram-3-fill`) is drawn as a **top-down org chart**, but a
knockout bracket runs **left to right**. So wherever that glyph stands for a **draw or
a bracket**, it is turned a quarter turn clockwise — every time, on every screen,
mobile and desktop.

**How:** add the shared class `bracket-icon` next to it. Never hand-roll the rotation.

```blade
<i class="bi bi-diagram-3 bracket-icon"></i>
<i class="bi bi-diagram-3-fill bracket-icon text-2xl"></i>
```

`.bracket-icon` lives in `resources/css/app.css` and is `display:inline-block` +
`transform: rotate(90deg)`. **The `inline-block` is load-bearing** — a bare `<i>` is an
inline box and CSS transforms do not apply to those, so `rotate-90` alone silently does
nothing.

**When the icon name arrives as DATA** — an event package's action list, a milestone, a
console tile, `<x-event-section-band icon="…">` — do not concatenate the class by hand:
call **`App\Support\Icon::bi($name, $extraClasses)`**, which whitelists the `bi-*` name
and appends `bracket-icon` itself when the name is a `bi-diagram-3*` one.

```blade
<i class="{{ \App\Support\Icon::bi($card['icon'], 'text-xl') }}"></i>
```

**Only for brackets.** The same glyph is used for the family tree, the business/chain
hierarchy, club affiliations, roles and hall screens — those stay **upright**. Rotating
them would be a regression, not consistency.

---

### 8. Every bottom sheet opens with a gradient header band

**A sheet is not a white box with a title in it.** Every bottom sheet, modal sheet and
drawer opens with the same **gradient header band** — a coloured, full-bleed top on the
sheet itself, carrying the drag handle, an icon tile, the title, its sub-line, and the ✕.
Reference implementation: `resources/views/components-templates/member/mobile/partials/tournament-detail-sheet.blade.php`.

```blade
{{-- Header: the subject is the headline, the classification beneath it --}}
<div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
     style="background: linear-gradient(150deg, #b45309, #d97706b0);">
    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>

    <div class="relative flex items-start gap-3">
        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
            <i class="bi bi-trophy-fill text-xl"></i>
        </span>
        <div class="min-w-0 flex-1">
            <h3 class="text-lg font-black leading-tight">{{ $title }}</h3>
            <p class="text-[12px] text-white/85 mt-0.5">{{ $subtitle }}</p>
        </div>
        <button type="button" @click="open = false" aria-label="{{ __('shared.close') }}"
                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    {{-- Optional chip row: the few facts that belong beside the title --}}
    <div class="relative mt-3 flex flex-wrap gap-1.5">
        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/20 text-[11px] font-bold">…</span>
    </div>
</div>
```

**Non-negotiables**
- **⚠️ The gradient is `#hex → #hex + b0`.** The `b0` alpha suffix is **hex-only** — writing
  `hsl(38 92% 50%)b0` produces an INVALID gradient, the whole declaration is dropped, and
  you get white text on the sheet's grey. This is not theoretical; it shipped once.
- **The band is inside the sheet's own rounded top** (`rounded-t-3xl` on the band, matching
  the sheet) and `flex-shrink-0`, so the body below it scrolls and the band never does.
- **One soft circle** (`bg-white/10`, `-right-8 -top-10 w-36 h-36`) for depth, and the
  content rows carry `relative` so they sit above it.
- **Drag handle** (`mx-auto w-10 h-1 rounded-full bg-white/40 mb-3`) on every sheet that
  rises from the bottom edge — it is what says "this is a sheet".
- **Title is `text-lg font-black leading-tight`**, its sub-line `text-[12px] text-white/85`.
  Icon tile is `w-12 h-12 rounded-2xl bg-white/20`; the ✕ is the round 36px control.
- **Colour comes from the subject**: a club/event colour where one exists, otherwise the
  section's accent (amber for trophies, `hsl(250 65% 65%)`/`#7c6bf5` for platform actions).
  Stay on palette — never a one-off colour.
- **Body and footer keep the mobile-form rules**: `flex-1 overflow-y-auto` body, sticky
  footer padded with `calc(0.75rem + env(safe-area-inset-bottom))`, sheet teleported to
  `<body>` (Mobile Forms Must Be Mobile-Friendly).
- **The band's ✕ is the close control — never repeat it in a footer.** A read-only sheet
  therefore has NO footer at all: its body is the last element and carries the safe-area
  padding itself. A footer belongs to a sheet that has something to SUBMIT (Save / Confirm
  / Pay); a second "Close" button under a scroll is wasted reach, not reassurance.

> This applies to **every** sheet — detail sheets, form sheets, pickers, confirmations —
> mobile and desktop-modal-as-sheet alike. A new sheet copies this band; it does not invent
> a header of its own. Editing an existing sheet's header is the one time Design Rule #1's
> "don't redesign existing UI" yields: bringing a sheet onto this band is a correction, not
> a redesign.

---

## Design System — follow these patterns exactly

**Color palette (defined in `resources/css/app.css` `@theme`):**
- Primary: `hsl(250 65% 65%)` — purple. Use `bg-primary`, `text-primary`, `border-primary`
- Background: `hsl(220 15% 97%)` — near-white gray. Use `bg-background`
- Card/surfaces: `bg-white` or `bg-card` with `rounded-xl shadow-sm`
- Muted: `bg-muted` (`hsl(220 15% 94%)`), `text-muted-foreground`
- Border: `border-border` (`hsl(210 14% 80%)`)
- Success: `text-green-600` / `bg-success`
- Destructive/danger: `bg-destructive text-white`
- Accent: `bg-accent` (`hsl(250 60% 92%)`) for subtle highlights

**Typography:**
- Font: `Inter` (loaded from Google Fonts)
- Page headings: `text-xl font-bold` or `text-3xl font-bold text-gray-900`
- Sub-headings: `text-sm font-medium text-muted-foreground`
- Body: `text-sm text-foreground`
- Labels/metadata: `text-xs text-muted-foreground`

**Layout patterns:**
- Page wrapper: `px-4 sm:px-6 lg:px-8 py-4` (member pages) or `space-y-6` inside `admin-club` layout — **full width, no `max-w-* mx-auto` cap** (matches `/admin`'s edge-to-edge sidebar content; removed from every desktop page in July 2026)
- Page header: `flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4` with a title block on the left and action buttons on the right
- Cards: `bg-white rounded-xl shadow-sm border border-gray-100 p-4` or `p-6`
- Grids: `grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6`
- Stat rows: `grid grid-cols-2 sm:grid-cols-4 gap-4`

**Buttons:**
- Primary action: `bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 transition-colors font-medium` or use `.btn.btn-primary`
- Outline/secondary: `border border-primary text-primary bg-transparent px-4 py-2 rounded-md text-sm font-medium hover:bg-primary hover:text-white transition-colors`
- Destructive: `border border-red-300 text-red-600 hover:bg-red-50` or `.btn.btn-danger`
- Icon-only (sidebar actions): `w-9 h-9 rounded-lg flex items-center justify-center bg-card text-foreground hover:bg-accent hover:shadow-sm transition-all border border-border`
- Pill/badge style: `px-3 py-1.5 rounded-full text-xs font-medium`

**Tabs:**
- Use `border-b border-gray-200` container with `nav -mb-px flex gap-8`
- Active tab: `border-b-2 border-purple-500 text-purple-600 font-medium text-sm`
- Inactive tab: `border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium text-sm`
- Count badges inside tabs: `ml-2 py-0.5 px-2.5 rounded-full text-xs font-medium bg-purple-100 text-purple-600`

**Forms & inputs:**
- Input: `w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent`
- Search input with icon: wrap in `relative`, use `pl-10` on input, SVG icon `absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400`
- Labels: `block text-sm font-medium text-gray-700 mb-1`
- Select: same border/radius pattern as input

**Icons:**
- Use Bootstrap Icons exclusively (`bi bi-*` classes). No Heroicons, Lucide, or FontAwesome.
- Inline with text: `<i class="bi bi-{name} mr-2"></i>`
- In icon-only buttons or stat cards: `<i class="bi bi-{name} text-xl"></i>` or `text-2xl`

**Interactivity:**
- Dropdowns, modals, toggles: Alpine.js (`x-data`, `x-show`, `x-cloak`, `@click.outside`)
- Modals use the custom Bootstrap bridge in `app.blade.php` — trigger with `data-bs-toggle="modal"` or `bsModal.show(el)` / `bsModal.hide(el)`
- Toasts: call `window.showToast('success'|'error'|'info'|'warning', 'message')`
- Transitions on dropdowns: `x-transition:enter="transition ease-out duration-100"` / `x-transition:enter-start="opacity-0 scale-95"` / `x-transition:enter-end="opacity-100 scale-100"`

**Mobile responsiveness (required on every new view):**
- All headers: `flex-col sm:flex-row`
- All tables/tab groups: wrap in `overflow-x-auto`
- Modals: `max-w-[calc(100vw-2rem)]` or `w-full max-w-lg`
- Dropdowns: `max-w-[calc(100vw-2rem)]`

**What to avoid:**
- Do NOT use Bootstrap CSS classes (`.btn`, `.card`, `.modal` etc.) unless they are already present in the file being edited
- Do NOT use arbitrary Tailwind colors like `bg-blue-500` for primary actions — always use `bg-primary`
- Do NOT use inline `style=` for colors that have a Tailwind token equivalent
- Do NOT add gradients, glassmorphism, or decorative effects unless the specific context already uses them (e.g. the public club hero banner)

---

## Blade Component Library — REUSE FIRST

**Rule:** Before building any new UI element, check this catalog. If a component covers the need, use it — do not re-implement it inline. When a new reusable component is created, add it here immediately. New components must satisfy the **Standalone Self-Contained Components** contract.

All components live in `resources/views/components/` and are called as `<x-{name}>`.

### Dropdowns / Form Selects

| Tag | Purpose | Key props |
|-----|---------|-----------|
| `<x-birthdate-dropdown>` | Day/Month/Year cascading picker with live age badge | `model` (Alpine state path — binds a parent property instead of posting a hidden input; its behaviour is then written INLINE, so it survives inside a `<template x-if>` where a `<script>` would be inert), `name`, `id`, `value`, `label`, `minAge`, `maxAge`, `minYear`, `maxYear`, `required`, `error` |
| `<x-blood-type-dropdown>` | Blood type selector (A+, A-, B+, etc.) | `name`, `id`, `value`, `label`, `required`, `error` |
| `<x-call-code-dropdown>` | Country dial-code `<select>` | `name`, `id`, `value`, `label`, `required`, `error` |
| `<x-country-code-dropdown>` | Searchable calling-code picker (Alpine) | `name`, `id`, `value`, `required`, `error` |
| `<x-country-dropdown>` | Searchable country name picker (Alpine) | `name`, `id`, `value`, `label`, `required`, `error` |
| `<x-currency-dropdown>` | Searchable currency picker (Alpine) | `name`, `id`, `value`, `label`, `required`, `error` |
| `<x-gender-dropdown>` | Gender selector | `model` (Alpine state path — see `<x-birthdate-dropdown>`), `name`, `id`, `value`, `label`, `required`, `error` |
| `<x-gender-toggle>` | Two-button Male/Female selector (Alpine-bound) — Male shades blue, Female shades pink when selected | `model` (Alpine state path, e.g. `self.gender`), `maleLabel`/`femaleLabel` (Alpine label expressions), `maleValue`/`femaleValue` |
| `<x-marital-status-dropdown>` | Marital status selector | `name`, `id`, `value`, `label`, `required`, `error` |
| `<x-relationship-dropdown>` | Family relationship type selector | `name`, `id`, `value`, `label`, `required`, `error` |
| `<x-timezone-dropdown>` | Searchable timezone picker (Alpine) | `name`, `id`, `value`, `label`, `required`, `error` |

### Modals

| Tag | Purpose | Key props |
|-----|---------|-----------|
| `<x-activity-modal>` | Create or edit a club activity with image cropper | `club`, `mode` (`create`\|`edit`) |
| `<x-club-modal>` | Create/edit club — multi-tab (basic-info, contact, location, branding, finance) | `mode` (`create`\|`edit`), `club` |
| `<x-confirm-dialog>` | Async JS confirmation dialog — include once per layout; invoke via `window.confirmAction({title, message, type, confirmText})` → returns `Promise<bool>` | _(no props)_ |
| `<x-expense-modal>` | Record an expense/transaction | `club` |
| `<x-image-upload-modal>` | Standalone crop-and-upload image modal (Alpine) | `aspectRatio`, `maxSize`, `title`, `uploadUrl` |
| `<x-income-modal>` | Record manual income | `club`, `currency` |
| `<x-member-create-modal>` | Create new member with optional guardian/family (Alpine) | _(no required props)_ |
| `<x-profile-modal>` | Full user profile edit/create modal — tabs: photo, personal, social, additional | `user`, `formAction`, `formMethod`, `mode` (`edit`\|`create`), `cancelUrl`, `showRelationshipFields`, `relationship`, `title`, `subtitle`, `submitText`, `submitIcon`, `eventName`, `showPasswordFields`, `showEmailField` |
| `<x-registration-walkin>` | Multi-step walk-in member registration | `club`, `packages`, `eventName` |
| `<x-user-picker-modal>` | Search and select a platform user (Alpine) | _(no required props)_ |

### Cards

| Tag | Purpose | Key props |
|-----|---------|-----------|
| `<x-member-card>` | Member summary card — age group badge, guardian, member-since | `member`, `href`, `footerLabel`, `footerStyle`, `guardian`, `memberSince`, `cardClass` |
| `<x-package-card>` | Club package card — cover image, pricing, schedule, activities, capacity | `package`, `club`, `instructorsMap`; slots: `actions`, `footer` |
| `<x-gender-avatar>` | Portrait fallback avatar — gendered head-and-shoulders silhouette on a colored tile, for users with no profile picture. Pass sizing/rounding/border via `class`. | `gender` (`m`/`f`/…), `bg` (default `hsl(250 55% 60%)`) |

### UI / Display

| Tag | Purpose | Key props |
|-----|---------|-----------|
| `<x-toast-notification>` | Global toast container — **include once per layout**; call `window.showToast(type, message)` from JS | `position` (default `top-right`) |
| `<x-qr-code>` | Offline QR (bacon, server-rendered SVG) — trigger button opens a modal with the code + Download PNG/SVG + Copy link + optional printable poster. Build target URLs via `App\Http\Controllers\QrController::clubPageUrl/clubRegisterUrl/memberUrl/eventUrl()`; poster routes are `qr.club.page`, `qr.club.register`, `qr.member`, `qr.event`. QR rendering helper: `App\Support\Qr::svg()`. | `url` (required), `title`, `caption`, `filename`, `label`, `icon`, `size`, `posterUrl`, `buttonClass` |
| `<x-stat-card>` | KPI stat card with sparkline, trend indicator, and live-update API (`StatCard.update(cardId, detail)`). Supports click navigation via `href`, `modal`, or `on-click`. | `label`, `value`, `sub-label`, `icon` (bi-*), `icon-bg`, `icon-color`, `size` (`sm`\|`md`\|`lg`), `spark-data`, `spark-labels`, `spark-color`, `trend`, `trend-up`, `refresh-event`, `card-id`, `href`, `modal`, `on-click` |
| `<x-admin-hero>` | Platform/club-admin page hero band (purple gradient, control-center style) — title + optional eyebrow/subtitle, optional right-side `count` chip, and an `actions` slot for buttons/badges. Used on the platform dashboard + admin list pages. | `title` (required), `eyebrow`, `subtitle`, `icon` (bi-*), `count`, `countLabel`; slot: `actions` |
| `<x-financial-chart>` | Monthly income/expense Chart.js bar chart with drill-down modal | `monthlyData`, `transactions`, `currency`, `canvasId`, `maintainAspectRatio`, `canvasHeightAttr`, `containerClass` |
| `<x-location-map>` | Leaflet map with address search and lat/lng hidden inputs | `id`, `latName`, `lngName`, `addressName`, `lat`, `lng`, `address`, `defaultLat`, `defaultLng`, `height`, `required` |
| `<x-tournament-bracket>` | **Zoomable knockout bracket — the one bracket renderer for every sport.** Pan/zoom with the same gestures and mechanics as the family tree (`family/partials/tree-runtime.blade.php`): a `touch-action:none` viewport, a canvas carrying ONE transform, SVG connectors measured from the laid-out DOM, native-touch + pointer + wheel paths kept apart. Fed by the shared payload `App\Events\Support\BracketView` (`EventType::bracketView()`), so it never knows which sport it draws. With `can-arrange`, organisers get an **Arrange mode**: drag a competitor between first-round slots or to/from the entrants bench, **or** tap-to-pick → tap-to-place (also the keyboard path). Each drop saves atomically to `me.events.bracket.arrange`; later rounds are never arrangeable (they're derived) and the draw locks the moment the event starts. Owns its own realtime refresh (`realtime:events`), styling and RTL mirroring. Runtime lives in `components/bracket/runtime.blade.php`. Dispatches `bracket:loaded` / `bracket:state` / `bracket:match` on the viewport. | `data-url` (required), `arrange-url`, `clear-url`, `event-uuid`, `can-arrange`, `my-competitor-ids`, `id`, `height` |
| `<x-event-documents>` | Download list for an event's attached files, plus an uploader when the viewer may manage the event. Owns its own Alpine state, upload/delete requests and in-place list patching (No-Reload); dispatches `event-documents-changed` (`{action:'created'\|'deleted', document\|uuid}`). Files are served only via `me.events.documents.download`, which re-checks `EventAccess::visible` per request — the list never holds a storage path, and every server value is rendered with `x-text`/`:href`, never `innerHTML`. Byte-level validation + server-assigned filenames live in `App\Support\DocumentUpload`. | `event` (uuid), `documents`, `canManage`, `color` |
| `<x-event-section-band>` | Full-bleed dark gradient band that announces a section of the event detail card (About / How the event runs / Divisions / Requirements / Location) and doubles as the divider between them. Two modes: **heading** (icon + title) or **value** (icon + eyebrow + a big value line — pass `value`; this is what the prize band is). Render it as a direct child of the card, **outside** the padded content wrapper, so it meets both edges. `color`/`icon` are whitelisted inside the component (hex + `bi-*`) since they are organiser-supplied and land in a `style` attribute / class name. Used by both `personal/{mobile,desktop}/event-show`. | `color`, `icon` (bi-*), `title`, `value` |
| `<x-court-screens>` | **Hall screens panel for the event console** — the wall displays showing an event's mats. Lists each paired screen (mat plate, live/last-seen, unpair) and owns the whole pairing flow in a teleported bottom sheet: **scan the QR** (opens the shared `partials/qr-scanner` in hand-back mode via `qr-scan:open` with `{emit:'court-screens:scanned'}` — it returns the value instead of navigating) **or type the 6-character code** printed under it, then pick a mat from selection cards (the mats come from the draw; "Another mat" is the escape hatch). Writes patch in place and other organisers are nudged over `realtime:events` `{action:'screens'}` — a refresh signal, so each console re-fetches what it may see. Rendered only when `EventType::hallScreens()` returns non-null, so a type with no wall boards has no section. **Unpair ≠ revoke:** it unclaims the device so the screen returns to a fresh pairing code (revoking would kill the token and strand a screen the agent can never re-enrol). | `event` (uuid), `mats`, `screens`, `color` |
| `<x-media-lightbox>` | **Full-screen single-file viewer — black tint, zoom + pan.** Include once per page; open it from any element by adding `data-media-lightbox data-src="…" data-label="…"` (delegated off `document`, so innerHTML-rebuilt rows work), or dispatch `open-media-lightbox` with `{src, label, kind}`. Images get pinch / wheel / double-tap zoom and drag-to-pan and are fitted to the stage on load; a PDF is handed to the browser's own viewer inside the same dark surface. `src` is refused unless it resolves to an http(s) URL. Teleported to `<body>`, safe-area padded, Escape/+/-/0 keys. Used by the member profile's identity documents (mobile + desktop). | `eventName` |
| `<x-event-draw-visibility>` | **When an event's draw becomes readable** — the organiser's switch, as one row of the event console opening a sheet. Three selection cards: *Visible now* (`always`), *On the day* (`start_day` — opens on the event's own date, or the moment it is started), *Hidden* (`hidden` — no clock; the organiser puts it up). Writes to `me.events.draw-reveal` (organiser only), patches itself in place and dispatches `event-draw-reveal-changed`. Disclosure only: it never changes who may ARRANGE a draw, and the organiser and their appointed officials read the bracket at every setting. The rule itself is `App\Events\Support\EventAccess::drawVisible()` — one place, obeyed by the board's JSON door, the bout page, the public event page and the MCP tool. | `event` (uuid), `reveal`, `date`, `color`, `title` |
| `<x-draw-veil>` | The card shown in place of a withheld draw — padlock, the sentence saying WHEN it opens, and the event's own colour. Says *when* rather than nothing on purpose: a reader who cannot tell "not published yet" from "nobody entered" phones the organiser. | `message`, `color` |
| `<x-prose-text>` | **Textarea prose → real markup, with the small slice of Markdown people type by reflex.** The default way to render any free-text field an author typed in a `<textarea>` (an event's About, notes, a description). Blank line = `<p>`, single newline = `<br>`; `#`–`######` headings, `- `/`* `/`• ` bullets, `1. ` numbered lists, `> ` quotes, `---` rules, `**bold**`, `*italic*`, `` `code` ``, `[label](url)`. Never use `whitespace-pre-line` for this — it honours newlines but leaves one undifferentiated wall, and shows `##`/`**` as literal punctuation. **Safe on untrusted input by construction:** `App\Support\PlainProse::toHtml()` escapes every character BEFORE a tag is added and emits only tags it writes itself; link schemes are whitelisted to http(s)/mailto and Markdown images are downgraded to links (no author-chosen fetch on a public page). Deliberately a SUBSET, not a Markdown library — no raw-HTML passthrough, images or tables. Last block never carries the gap class (`last:mb-0` is not in the prebuilt bundle), and `list-decimal` is set inline for the same reason. | `text`, `textClass`, `gapClass` (+ any attributes, applied to the wrapper) |
| `<x-client-paginator>` | Client-side pagination for any list filtered via JS. Renders the container div and injects the `ClientPaginator` JS class (once per page). Instantiate in JS: `new ClientPaginator({ itemsSelector, containerId, perPage, countBadgeId, scrollTargetId, labelSingular, labelPlural, filterFn })` then call `.refresh()` when filters change. Registered in `window._pagers[id]` for inline `onclick` access. | `id` (required), `perPage` (default `20`) |

> **`<x-stat-card>` sparkline alignment rule:** Always pass `:spark-data` from the same domain as the card's value (e.g. revenue card → monthly revenue array, not monthly member counts). When no real time-series exists yet, pass `array_fill(0, 12, 0)` as a flat baseline — never reuse another card's unrelated data array. The component is `flex flex-col` with `mt-auto` on the sparkline, so it always pins to the bottom of the card in equal-height grid rows. Do not remove these classes from the component.
>
> **`<x-stat-card>` constrained eager-loading:** When loading models for stat card data via constrained eager loads (e.g. `user:id,name,...`), always include `updated_at` in the column list. The `member-card` component uses `$member->updated_at->timestamp` for image cache-busting — omitting it causes a null-dereference that silently breaks the AJAX response.
>
> **`@json()` in Blade with nested arrays:** Do NOT write `@json($arr['key'] ?? [0,0,...,0])` — Blade's bracket-matcher chokes on `['key']` followed by `?? [literal array]` inside a single `@json()` call. Pre-assign to a `@php` variable first, then use `@json($variable)`.

### Form Utilities

| Tag | Purpose | Key props |
|-----|---------|-----------|
| `<x-select-menu>` | **Generic styled dropdown — the default replacement for any native `<select>`** (Design Rule #4). Rounded trigger (rotating chevron, purple focus ring) + `rounded-xl` fade/slide panel with `hover:bg-muted/60` rows and a `text-primary` check on the selected item; closes on click-outside/escape. Two modes: pass **`model`** (an Alpine state path, e.g. `cat`) to bind a property in the PARENT Alpine scope (use instead of `x-model`); OR omit `model` and pass **`value`** for a standalone server-form control (seeds state, posts via the hidden `name` input). Prefer a specialized `<x-*-dropdown>` when the vocabulary matches (gender/country/etc.). Do NOT use for JS-coupled selects (options read by `id`/`data-*`, built in JS strings, `form.reset()`-cleared, or dynamic `x-for` options) — those stay native. | `model`, `value`, `options` (`[['value'=>,'label'=>], …]`), `name`, `placeholder`, `error`, `change` (Alpine expr run after pick), `panelClass` |
| `<x-date-picker>` | **Calendar field — the default replacement for `<input type="date">`** (Design Rule #4). Rounded trigger (calendar icon, formatted date, rotating chevron) + a month grid that expands **inside the normal flow** — never `position: absolute` — so a scrolling bottom-sheet body can't clip it. Today is ringed, the selection is filled `bg-primary`, out-of-range days are disabled, and Today / Clear sit in the footer. Value is always an ISO `YYYY-MM-DD` string. Pass **`model`** (Alpine path in the parent scope) to bind parent state, or omit it and pass **`value`** for a standalone server-form control posted via `name`. Use `min-expr` / `max-expr` / `name-expr` when the bound range or field name is itself reactive (e.g. a form that toggles between a one-off date and a recurring day). All behaviour is inline `x-data` — no registration script, so it survives mobile-shell content swaps. | `model`, `value`, `name`, `min`, `max`, `minExpr`, `maxExpr`, `nameExpr`, `placeholder`, `error`, `change`, `dayOnly` |
| `<x-rich-text-editor>` | WYSIWYG rich-text editor (Alpine + `contenteditable`). Toolbar: bold/italic/underline/strikethrough, text color, H1/H2/H3/paragraph/quote, bullet+numbered lists, indent, align, link (inline URL bar, no native prompt)/unlink, horizontal rule, undo/redo, clear. Submits HTML via a hidden `<textarea name="{name}">`. Supports `dir="rtl"`. ⚠️ Its output is untrusted HTML — sanitize server-side before storing and never echo it unescaped without sanitization. | `name`, `id`, `value`, `dir`, `placeholder`, `minHeight` |
| `<x-image-upload>` | Inline image upload with crop preview (uses takeone-cropper) | `id`, `name`, `width`, `height`, `shape`, `folder`, `filename`, `uploadUrl`, `currentImage`, `placeholder`, `placeholderIcon`, `buttonText`, `rounded`, `showPreview` |
| `<x-takeone-cropper>` | Raw cropper widget (used inside `image-upload`). Pass `:inline="true"` for the **mobile-first bottom-sheet** crop editor (**required on mobile** — see *One Cropper Everywhere*); default is desktop modal mode. `:uploadAsIs="true"` shows a second full-res upload button (label via `uploadAsIsText`). `:showControls="false"` swaps the zoom/rotation sliders for pinch/twist touch gestures. `sheetMaxWidth`/`sheetClass` shape the inline sheet; `saveText` relabels the crop button; `:showCancel="false"` hides the footer Cancel (header ✕ closes). Viewport auto-fits `canvasHeight`; handlers are teleport-safe (delegated). | `id`, `width`, `height`, `shape`, `folder`, `filename`, `uploadUrl`, `currentImage`, `buttonText`, `buttonClass`, `mode` (ajax\|form), `inputName`, `canvasHeight`, `uploadAsIs`, `uploadAsIsText`, `inline`, `showControls`, `showCancel`, `saveText`, `sheetMaxWidth`, `sheetClass` |
| `<x-schedule-time-picker>` | Days-of-week multi-select + start/end time inputs | `id`, `daysName`, `startTimeName`, `endTimeName`, `selectedDays`, `startTime`, `endTime`, `required`, `showLabels` |
| `<x-social-links-editor>` | Editable list of social media links (add/remove/reorder) | `links`, `containerId` |
| `<x-social-link-row>` | Single social link row — used internally by `social-links-editor` | `index`, `link` |

---

# PART 6 — MOBILE & THE ANDROID APP

## Mobile / Desktop Separation — STRICT

**Any view that has both a mobile and a desktop experience MUST be two separate Blade
files**, never one file branching with responsive classes for fundamentally different
layouts. Keep the markup distinct so editing one can never break the other.

- Convention: `resources/views/<feature>/desktop/<view>.blade.php` and
  `resources/views/<feature>/mobile/<view>.blade.php`, and where needed separate layouts
  `layouts/<name>-desktop.blade.php` / `layouts/<name>-mobile.blade.php`.
- The controller (or layout) picks the file with the `$isMobile` flag from the
  `DetectDevice` middleware: `view($isMobile ? '<feature>.mobile.<view>' : '<feature>.desktop.<view>')`.
- **Parity:** a change to a desktop view lands in its mobile counterpart too.
- Applies to **new** views; existing CSS-responsive pages need not be split unless asked.
- Ordinary responsive tweaks (a header stacking via `flex-col sm:flex-row`) do NOT require a
  separate file — only views whose layouts genuinely diverge.

Baseline responsiveness patterns already in place everywhere: `overflow-x-auto` for tables
and tab containers, `flex-col sm:flex-row` for headers, `z-40` on an open sidebar.

## Android App (APK) Mirrors the Mobile Web — STRICT

**The mobile web experience IS the Android app.** The member's app (`bh.takeone.app`) loads
the live site (`https://takeone.bh`) in a WebView — not a separate codebase. Mobile web work
and the APK are **one deliverable**.

**It is a Flutter build.** The Capacitor shell in `mobile/` was converted on 2026-08-29 and
that folder is gone. One Flutter project — `flutter/TV/` — produces four APKs from one
codebase, differing only in application id, manifest and a single `--dart-define`:

| Variant | Application id | What it is |
|---|---|---|
| `tv` | `bh.takeone.tv` | the wall screen (leanback) |
| `tab` | `bh.takeone.tab` | the scoring table |
| `cam` | `bh.takeone.cam` | a camera beside the mat |
| `app` | `bh.takeone.app` | **the member's phone app** |

Build with `./flutter/TV/build.sh {tv|tab|cam|app} [base-url]`. `flutter/TV/lab-app/` is the
separate boutcam project.

### Rules

1. **Mobile web changes flow to the app automatically — no rebuild needed.** Any change to a
   mobile Blade view, controller, route, JS, CSS or backend logic appears in the installed
   APK the moment it is deployed. Do **not** tell the user to rebuild the APK for
   content/UI/logic changes.
2. **Every mobile feature must actually work inside a WebView.** Verify it there, not just in
   a desktop browser.
   - Camera / QR scanning / photo capture use `getUserMedia`; the `CAMERA` permission is
     declared in `flutter/TV/android_app/AndroidManifest.xml`. A new device capability (mic,
     geolocation, notifications) means **adding the matching Android permission there** in
     the same task. That file is the member app's manifest; the hall variants
     (`android_tv`, `android_tab`, `android_cam`) have their own and must not be edited for a
     member-app need.
   - **Uploads and permissions are granted by the shell, not the page.** A WebView opens no
     file picker and grants no camera unless the host app hands it one —
     `flutter/TV/lib/src/app/shell.dart` does both (`setOnShowFileSelector`,
     `setOnPlatformPermissionRequest`). If an upload silently does nothing in the app but
     works in a browser, that is where to look.
   - Avoid browser-only APIs that Android WebView blocks or handles differently (certain
     downloads, `window.open` popups, native file pickers) without a WebView-safe fallback.
3. **Only rebuild/re-sign the APK when the NATIVE shell changes** — icon, name, splash,
   colors, permissions, or the host it points at. Then do the whole loop as one unit: edit
   the variant's manifest or `lib/src/`, rebuild with
   `./flutter/TV/build.sh app https://takeone.bh`, and re-verify the signed artifact.
4. **The published app has a version series, and Play enforces it.** `build.sh` sets
   `VERSION_CODE`/`VERSION_NAME` for the `app` variant only (currently 11 / 1.10, continuing
   the Capacitor build's 10 / 1.9). **Raise the code on every store release** — Flutter's
   default of `1` is rejected as a downgrade.
5. **⚠️ The web still calls a Capacitor-shaped bridge, and the Flutter shell answers to
   it.** `partials/app-update.blade.php`, `partials/push-register.blade.php` and
   `auth/mobile/login.blade.php` call `window.Capacitor.Plugins.{App,MqttPush}`.
   `shell.dart` injects a shim mapping those onto a Kotlin `MethodChannel` (`appInfo`,
   `mqttStart`, `mqttStop`, `batteryExemption`, `downloadAndInstall`). **Keep both ends in
   step:** a new native call means adding it to the shim, the channel handler in
   `MainActivity.kt`, and the web that calls it.
6. **Notifications are native and outlive the WebView.** `MqttNotificationService.java`
   (carried over unchanged) holds one MQTT connection in a foreground service and posts what
   arrives, authenticating with the session cookie the WebView already holds. It runs with no
   Flutter engine, so it reads its host from manifest meta-data (`bh.takeone.baseUrl`), which
   `build.sh` stamps in from the same `BASE_URL` the Dart side is built with — do not let
   those drift.
7. **Never break a mobile view in a way that only shows up in the app.** The APK has no
   address bar and no easy refresh, so a mobile view that soft-locks or clips off-screen is
   worse there than on the web.

## Mobile Device Capabilities and Animation — STRICT

Build mobile-web features that use supported browser/WebView capabilities when required —
camera, microphone, geolocation, file picking / image capture, share actions, vibration —
and always: **detect capability support first, request permission correctly, provide a
fallback, and ensure it still works inside the Android WebView shell.**

Polished animation is expected too, via Tailwind transitions, Alpine transitions, CSS
transitions/keyframes, or lightweight JS. Animations must improve clarity and feel, never
reduce speed or usability. Respect `prefers-reduced-motion`.

## Mobile Forms Must Be Mobile-Friendly — STRICT

**Rule:** Any form, modal, or sheet shown in a mobile view MUST be fully usable on a phone — the entire form (including every field AND the submit/cancel actions) must be reachable and visible. Never ship a mobile form where content is clipped off-screen or the submit button can't be reached. This is a default requirement whenever the user is talking about a mobile view — they should not have to ask for it.

**Required patterns:**
- **Use a bottom-sheet (or full-screen) layout** for non-trivial mobile forms: `fixed inset-x-0 bottom-0 max-h-[92vh] flex flex-col`, with a `flex-shrink-0` header, a `flex-1 overflow-y-auto` scrollable body, and a `flex-shrink-0` sticky footer holding the actions. The body scrolls; the actions stay reachable.
- **Respect the safe area** on the sticky footer: `padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));`. ⚠️ This only works because the base layouts (`layouts/app.blade.php`, `layouts/tailwind.blade.php`) set `<meta name="viewport" content="… viewport-fit=cover">` — without `viewport-fit=cover` every `env(safe-area-inset-*)` resolves to `0` and the padding silently does nothing. Never drop `viewport-fit=cover` from a base layout's viewport meta, and never ship a mobile viewport with `maximum-scale=1`/`user-scalable=no` (breaks pinch-zoom / WCAG 1.4.4).
- **⚠️ Teleport fixed overlays to `<body>`.** The mobile shell's `#shell-content` carries `.mobile-stagger`, whose `m-rise` animation leaves a `transform` on every direct child. A CSS `transform` makes that element the containing block for any `position: fixed` descendant — so a bottom-sheet's `bottom-0` / `max-h-[92vh]` resolve against the tiny wrapper instead of the viewport and the form gets clipped. Wrap any fixed sheet/FAB/overlay in `<template x-teleport="body">…</template>` (inside an Alpine `x-data` scope) so it escapes the transformed ancestor. Use `z-[60]+` for teleported sheets so they sit above the bottom tab bar (`z-40`).
- Inputs full-width (`w-full`), comfortable tap targets, and the design-system input/button tokens. Keep [[Mobile = Creative + Animated]] in mind — animated, on-palette, not a stripped-down desktop form.

> Reference implementation: `resources/views/components/schedule-session-modal.blade.php` (teleported bottom-sheet with scrollable body + sticky footer).

---

## One Cropper Everywhere — Mobile Uses `<x-takeone-cropper :inline>` — STRICT

**Rule:** Every image crop/upload in a **mobile** view MUST use the shared `<x-takeone-cropper>` widget in **inline mode** (`:inline="true"`) — the mobile-first bottom-sheet crop editor. Never render the cropper's default **modal mode** on mobile, and never build a one-off/alternate cropper. There is exactly one cropper in this project; on phones it always appears as the same bottom sheet.

### Why
The default modal mode opens through the Bootstrap bridge with the LOCKED desktop dialog size (`max-width:75%; width:1000px`), which collapses to a cramped, clipped box on a phone. Inline mode is the purpose-built mobile bottom sheet: teleported to `<body>` (escapes the mobile-shell transform trap), scrollable body, safe-area sticky footer, delegated handlers, and an auto-fit crop viewport. It is already used by the profile-picture and photo-edit croppers.

### How (canonical mobile invocation)
```blade
<x-takeone-cropper
    id="…Mobile" mode="ajax" :inline="true"
    :width="1600" :height="900" shape="rectangle" :canvasHeight="300"
    folder="…" filename="…" :uploadUrl="route('…')"
    sheetMaxWidth="100%"                                   {{-- match the host form's full-width sheet --}}
    sheetClass="rounded-t-3xl shadow-2xl bg-background"    {{-- match the host sheet's shape/size --}}
    :showControls="false"                                  {{-- drop sliders; pinch/twist gestures drive it --}}
    :showCancel="false"                                    {{-- the header ✕ closes; no redundant Cancel --}}
    saveText="Crop" :uploadAsIs="true" uploadAsIsText="Upload" />
```
- **Match the host sheet.** Set `sheetMaxWidth` / `sheetClass` so the crop sheet looks the same (shape + size) as the form it opens from. Defaults keep the old centered-card look for non-mobile callers, so overriding is opt-in and never touches the profile/photo croppers.
- **Sliders vs gestures.** `:showControls="false"` hides the zoom/rotation sliders; the widget then enables **pinch-to-zoom + two-finger twist-to-rotate** (Cropme has no native pinch — the widget adds it). Single-finger pan is Cropme's. Leave `showControls` default (`true`) only where sliders are actually wanted.
- **Labels / actions.** Customize with `saveText`, `uploadAsIsText`; hide the footer Cancel with `:showCancel="false"` (there is always a header ✕).

### Non-negotiable gotchas (already handled inside the widget — do not regress)
- **All handlers are delegated off `document`** (namespaced `.tk{id}`, guarded by `off()`), because in mobile the cropper renders inside a `<template x-teleport="body">` and `$(ready)` fires before Alpine teleports — direct `$('#id').on()` / `getElementById(...).addEventListener` silently bind to nothing and the picked image never loads. Never convert these back to direct binds.
- **The crop element is resolved lazily inside `initCropper`** (not cached at `$(ready)`), for the same teleport reason.
- **The viewport auto-fits the canvas.** Cropme throws *"Viewport height cannot be greater that container height"* if `width`/`height` exceed the `canvasHeight` box — the widget scales the viewport down (preserving aspect) only when it overflows. So a wide `:width`/`:height` (e.g. 1600×900 hero) is fine.
- **⚠️ Crop at full resolution — never upscale a viewport-sized crop.** Because the on-screen viewport is auto-fit small, the crop MUST be taken from the SOURCE image via Cropme's `crop({ width: <output px> })` option (it re-renders from the original pixels), then normalized to exactly `width`×`height` on a canvas and JPEG-encoded (~0.92). The old handler cropped at the viewport size and *stretched that tiny image up* to `width`×`height` → severe blur. Do not reintroduce that upscale. The **Upload** (`uploadAsIs`) path already sends the original full-resolution bytes untouched. (Regression-tested with a high-frequency source: full-res crop preserved ~331 stripe edges vs **0** for the upscale path.)

### Boundaries
- Does **not** override the LOCKED profile-picture cropper config — the profile cropper keeps its desktop modal + fixed dims.
- Desktop callers may keep modal mode (fine on a large screen); this rule is about the **mobile** experience.

> Reference implementation: `resources/views/admin/platform/mobile/activities.blade.php` (activity hero uploader). The widget lives at `resources/views/vendor/takeone/components/widget.blade.php` + `widget-crop-body.blade.php`.

---

## Mobile Pattern Language — the BASE for every mobile view — STRICT

**Rule:** Every mobile view — new or edited — is built from the named patterns below. This is the default vocabulary; do not invent a new structure when one of these fits, and never port a desktop layout to mobile unchanged. When a desktop screen is dense (many tabs, many fields, long tables), the mobile answer is **restructure**, not shrink.

> Canonical reference implementations: `resources/views/admin/club/details/mobile.blade.php` (hub + drill-down, completion ring, live preview), `resources/views/admin/club/roles/mobile.blade.php` (hub + drill-down, coverage meter, search + filter chips, live upsert into a panel list) and `resources/views/admin/club/roles/mobile-access-form.blade.php` (bottom sheet, selection cards, progressive disclosure).

### The core principle
**One screen, one job.** The reason the pattern works is not the styling — it is that each screen asks the user for one thing. The same tokens applied to 40 fields on a single scroll still feel bad. If a mobile screen is doing more than one job, split it before styling it.

### 1. Structure — Hub-and-spoke drill-down
*(a.k.a. list-detail / master-detail; iOS "hierarchical navigation")*

The replacement for desktop tabs. A **hub** lists the sections; tapping one opens a focused **detail panel** with a back affordance.

- Hub rows are a **grouped inset list**: rounded card, leading icon tile (colored per section), title, one-line summary, trailing `bi-chevron-right` (add `rtl:rotate-180`).
- Panels are **in-page `x-show` sections, not `position: fixed`** — a sticky panel header (`sticky top-0`, back · title · Save) avoids the transform/teleport trap entirely.
- Keep the submit reachable twice: in the sticky header AND as a full-width button at the end of the panel.
- Support deep links (`#section`) and, on validation failure, reopen the offending panel.
- **One form wrapping all panels** when the backend expects one submit — hidden `x-show` fields still post. Never split into per-panel partial saves unless the endpoint genuinely supports it (check for "absent key wipes data" behavior first).

### 2. Progressive disclosure
Long lists collapse into groups that are **closed by default**, each showing a live count badge so the state is readable without expanding. Add per-group and global **Select all / Clear** actions when the items are checkboxes. Use `x-show` + `x-transition` — **`x-collapse` is NOT available** (the Alpine collapse plugin is not registered in this project).

### 3. Controls
- **Selection cards** replace any dropdown with a short, known option set (≈2–7 items): full-width tappable rows acting as a radio group — icon tile, label, radio-style check circle, `border-primary bg-primary/5` when selected. Never a pop-over inside a scroll container: an `absolute` panel is clipped by any `overflow-y-auto` ancestor.
- Keep a styled Alpine dropdown (`<x-select-menu>` / `<x-*-dropdown>`) only for long or searchable vocabularies (country, currency, timezone).
- Native `<select>` / native date-time pickers remain banned (Design Rule #4).
- Tap targets ≥44px; checkboxes ≥18px.

### 4. Feedback & motivation
- **Completion meter** — a progress ring / "profile strength" scored from real signals, plus a **status dot** per hub row (green = done, amber = missing). Makes "is my setup finished?" answerable without opening anything.
- **Live preview / direct manipulation** — edit the thing, not a form describing it. Bind the hero (name, logo, cover) or a phone mockup to the inputs so changes are visible as they are typed or picked.
- **Bottom sheet** for focused sub-tasks, with a drag handle; teleported to `<body>` per the rule above.

### 5. Motion & surface
Reuse the shared motion system in `app.css` — `m-hero` (mesh-gradient band + sheen), `m-card`, `m-press`, `m-float`, `m-bar-fill`, `mobile-stagger`, `m-in*`, and **`m-panel-in`** (the drill-down panel entrance). Do not invent parallel animation classes. Respect `prefers-reduced-motion` (the shared classes already do).

### Anti-patterns — do not ship
- Desktop tabs rendered as a horizontal scroll strip on mobile.
- A read-only mobile page for a screen that is editable on desktop, plus a "edit this from desktop" note. Mobile must reach feature parity (see **Mobile / Desktop Separation** + the APK rule — mobile web *is* the Android app).
- An absolutely-positioned dropdown inside a scrolling sheet body.
- One endless scroll of every field, or a checkbox wall with no grouping.
- Native browser dialogs, native selects, off-palette one-off styling.

### Naming
When describing this work, the phrase is: **"a mobile-first settings hub with drill-down detail panels and progressive disclosure."**

---

# PART 7 — BEHAVIOUR: NO-RELOAD, REALTIME, MCP, NAVIGATION
## No Page Reload Rule — STRICT

**All write operations (create, update, delete) in this project must update the UI in place. Never require the user to manually refresh the page to see the result of their action.**

### How to implement:

1. **Every AJAX write endpoint** must return the updated data in its JSON response alongside `success` and `message`:
   ```json
   { "success": true, "message": "...", "<entity>": { ...updated fields... } }
   ```

2. **Every modal / form that submits via AJAX** must, on success, dispatch a `CustomEvent` carrying the returned data so any listening component on the page can update itself:
   ```js
   window.dispatchEvent(new CustomEvent('entity-updated', { detail: data.entity }));
   ```

3. **The page/view** that displays the data must listen for the event and patch the relevant DOM elements **in place** — no `window.location.reload()`, no `location.href =` redirect unless the action itself requires navigation (e.g. creation that navigates to a new record).

4. **Add stable target IDs** (`id="..."`) to every element whose content can change after a write so the JS listener can find and update it reliably.

5. For **complex re-rendered sections** (lists, cards with conditional content), regenerate the innerHTML from the returned JSON rather than patching individual text nodes.

> Returned payloads are subject to the security rules above — return only fields the viewer is authorized to see, never internal ids/paths/flags they shouldn't have.

### Established pattern (member profile page):
- Server returns `member` object in update response
- `submitForm()` in profile modal dispatches `member-profile-updated` with the member data
- `show.blade.php` has a `window.addEventListener('member-profile-updated', ...)` listener that patches: name, motto, age, blood type, marital status, social links, emergency contacts, documents, health conditions — all without reload

Apply this same pattern to every other feature: health records, goals, affiliations, tournaments, club details, packages, instructors, etc.

---

## Realtime / MQTT — Always Instant — STRICT

**Every action must reflect instantly for *all* affected users — never make anyone refresh.** The actor's own device updates in place (see No Page Reload Rule); everyone *else* affected must be updated live over MQTT. Notifications must arrive the same way.

### Rules
1. **On every write that affects other users, push over MQTT in the same request.** Use `Realtime()->publishToUser($id, $channel, $payload)` (one user) or `Realtime()->publishMany([...])` (many). It's best-effort — the DB stays the source of truth — but it must always be attempted. Realtime is enabled (`REALTIME_ENABLED=true`).
2. **Notifications go through `UserNotification::notifyUser()`**, which already writes the row *and* pushes MQTT (`notifications` channel) with an `action_url` deep-link. Never create notification rows without it.
3. **Client patches in place.** `realtime.js` re-emits each inbound message as a DOM event `realtime:<channel>` (e.g. `realtime:notification`, `realtime:message`, `realtime:schedule`). Feature views add a listener that updates the DOM — no reload.
4. **Use an `{action: '...'}` discriminator per channel.** Two reliable shapes:
   - **Targeted patch** — send the changed entity (`{action:'created'|'updated'|'deleted', <entity>:{...}}`); the listener upserts/removes that one item. Best when the payload is identical for every recipient.
   - **Refresh signal** — send `{action:'refresh'}` and have the view silently re-fetch its data from a JSON endpoint and re-render. **Use this when the same change renders differently per user** (different ids/content/permissions), e.g. a club class where each user sees their own card variant. Don't try to hand-craft per-user card payloads.
5. **Fan out to the whole audience**, not just the obvious user. A class change touches enrolled members + the coach + substitute(s) + the actor — push to all of them.
6. **Dedup client listeners** that live inside shell-swapped content: the mobile shell re-runs inline scripts on every AJAX nav, so store the handler on `window.__xxx` and `removeEventListener` the previous one before re-adding, or listeners stack up.
7. **Scope every payload to its audience** (see Security Coverage §11). Send the minimum necessary data; a recipient must never receive fields they aren't authorized to see. When the same change renders differently per user, use the refresh signal rather than oversharing.

### Reference implementation
`/me/schedule`: `scheduleData()` returns the schedule as JSON; `pushScheduleRefresh($userIds)` publishes `{action:'refresh'}` on the `schedule` channel to every affected user; the list view's `realtime:schedule` handler calls `reloadData()` on `refresh` (and patches individual cards on `created/updated/deleted`). Personal-session edits use the targeted-patch shape; club-class/substitute changes use the refresh shape.

---

## Keep the MCP Server in Sync — STRICT

**Rule:** The general-purpose MCP server (`app/Mcp/*`, exposed at `POST /mcp` and via `php artisan mcp:start takeone`) is a first-class interface to the platform, just like the web UI. **It must never fall behind the data model or feature set.** Whenever a change alters what the app can read or do, the MCP must be updated in the **same** change — treat a stale MCP as a broken build. Full subsystem docs: `Documentation/MCP.md`. See [[project_mcp_server]].

### When a change REQUIRES an MCP update
Update or add a tool (in `app/Mcp/Tools/`, registered in `app/Mcp/Servers/TakeOneServer.php`) whenever you:
- **Add a new entity / feature** that a user or integration would reasonably want to read or act on (new model, new admin action, new member self-service flow) → add a read tool, and a write tool if the app can mutate it.
- **Add or rename a field** that an existing tool returns (e.g. a new member/club attribute) → surface it in that tool's JSON so consumers see it.
- **Change a relationship or query** a tool relies on (e.g. the club↔member relation, a status enum, a slug/uuid binding) → fix the tool's query so it still returns correct, scoped data. (The existing tools already hit real gotchas: `memberClubs()` not `memberships`, `birthdate` not `date_of_birth`, `ClubTransaction.payment_method` is a NOT-NULL enum — keep these correct.)
- **Change an authorization rule** (who can see/do what) → mirror it in `app/Mcp/Concerns/AuthorizesClubAccess.php` (`canAdminClub`, `canViewMember`, `accessibleClubIds`) so the MCP enforces the *same* scope. The MCP must never expose more than the acting user could do in the UI.
- **Add a validation rule / enum vocabulary** to a write path → apply the identical rule in the corresponding write tool (e.g. gender `Male`/`Female`, payment methods).

### How to do it (non-negotiable steps)
1. Add/adjust the tool; extend `App\Mcp\Tools\BaseTool` (gives `guard()`, the write kill-switch, and scoping helpers). Guard first: `$user = $this->guard($request); if ($user instanceof \Laravel\Mcp\Response) return $user;`
2. Set `protected bool $isWrite = true;` on any tool that mutates data.
3. Register the class in `TakeOneServer::$tools`.
4. Enforce tenant scope with the `AuthorizesClubAccess` helpers — never query models unscoped.
5. Add/extend a case in `tests/Feature/McpServerTest.php` covering both the happy path **and** an authorization denial.
6. Update the tool table in `Documentation/MCP.md`.

### What does NOT need an MCP change
Pure UI/styling/copy edits, internal refactors, and changes with no new readable/actionable surface. When unsure, err toward adding the tool — an integration that can't see a feature is worse than one extra tool.

> Rationale: external systems (n8n, Claude, other services) consume the platform through this one server. If a feature ships to the web but not the MCP, every integration silently drifts out of date. The MCP is part of the deliverable, not an afterthought. MCP tools are a full attack surface — the Security sections above apply to them exactly as they do to web routes.

---

## Admin Sidebar SPA Navigation — STRICT

**Rule:** Clicking any link in a desktop admin sidebar (club admin `/admin/club/{club}/*` **and** super-admin platform `/admin/*`) must load the page **in place — no full browser reload**, like a React SPA. The sidebar, top bar, and scroll shell stay mounted; only the main content area swaps.

### How it works
- **Navigator:** `resources/views/partials/admin-shell-nav.blade.php` — included by both desktop admin layouts (`layouts/admin-club.blade.php`, `layouts/admin.blade.php`). It intercepts clicks on `a[data-shell-link]`, `fetch()`es the destination (sends `X-Requested-With`), parses the response, and swaps it in. Shows a slim top progress bar, updates `document.title`, the active nav state, and `history.pushState`/`popstate`. Modeled on the proven mobile shell navigator (`partials/mobile-shell-nav.blade.php`).
- **Swap target:** the layout's `<main>` carries `data-shell-main="club"` (or `"platform"`) plus `data-route`. The navigator replaces its `innerHTML` from the destination's matching `<main>`. **If the destination has a different `data-shell-main` value (or none), it hard-loads** — so club↔platform and admin→non-admin links fall back to a real navigation (their sidebars differ).
- **Page scripts & modals:** pages put JS/modals in `@push('scripts')`/`@push('modals')` (rendered *outside* `<main>`), so `app.blade.php` wraps those stacks in `<div id="shell-scripts">` / `<div id="shell-modals">`. On nav the navigator swaps those regions and runs each **unique** `<script>` **once per session** (deduped by content/`src`, seeded with the first-paint scripts). This is deliberate: desktop admin pages declare top-level `const`/`let`/`function` at global scope (for inline `onclick=`), so re-running the same script would throw "already declared". Globals therefore run once; their functions persist.
- **Re-init bridge:** after each swap the navigator **dispatches a synthetic `DOMContentLoaded`** and a `shell:navigated` window event. Existing pages gate their init on `DOMContentLoaded`, so this re-runs that init against the freshly-swapped DOM — on first visit AND revisit (the listener registered on first visit persists and re-fires). Alpine re-inits swapped `x-data` via its own mutation observer (the navigator does **not** call `Alpine.initTree`, which would double-initialize).
- **Speed:** links are **prefetched on hover / touchstart** (15s cache) so the click is usually instant; a top progress bar covers any remaining latency.
- **Page styles:** `@push('styles')` blocks are injected from the destination's `<head>` on first visit (content-deduped, so globals aren't re-added).
- **Marking links:** add `data-shell-link data-route="{{ $routeName }}"` to a sidebar `<a>` **only when it stays within the same shell**. Never mark cross-shell links (platform↔club, Back to Explore, external/`target="_blank"`) — leave them as plain links so they hard-load.
- **In-content links navigate in place automatically.** Beyond the sidebar, the navigator also intercepts any same-origin `<a>` whose path is under the shell's base (`data-shell-base` on `<main>` — `/admin/club/{slug}` or `/admin`), e.g. status-filter and pagination links. Links to *other* sections (member profiles `/member/*`, etc.) fall through to a normal full load. A non-HTML response (file download, redirect, JSON) auto-falls-back to full navigation via a Content-Type + redirect check.
- **Opt a content link OUT** of in-place nav with `data-no-shell` (e.g. file-download links like the member import template), or `download` / `target="_blank"` / an `onclick` / `data-bs-toggle` (those are skipped automatically).

### What page authors should know
1. **`DOMContentLoaded` works** — the navigator re-fires it on every swap, so existing `document.addEventListener('DOMContentLoaded', …)` init runs on each visit. New init code can use it, or listen for the `shell:navigated` window event. Put DOM-binding init *inside* that handler (not bare top-level) so it re-runs per visit and binds to the new DOM.
2. **Top-level declarations run once** — `const`/`let`/`function`/`class` at script top level execute only on the first visit (dedupe). Don't rely on them re-executing; keep per-visit work inside the `DOMContentLoaded`/`shell:navigated` handler.
3. **Dedup persistent listeners.** A `DOMContentLoaded` handler re-fires every nav, and re-bound `document`/`window` listeners can stack. Prefer delegated listeners, or store the handler on `window.__xxx` and `removeEventListener` the previous one before re-adding (see the mobile-shell dedup note). The navigator itself is guarded with `window.__adminShellNavInit`.

### Mobile shells: cross-shell guard
All mobile shells reuse `id="shell-content"`, so the mobile navigator can't tell them apart by id alone. Each shell's `<main id="shell-content">` carries a **`data-shell-id`** (`personal` / `admin-club` / `business`); the navigator hard-loads when the destination's `data-shell-id` differs (mirrors the desktop `data-shell-main` guard). **When you add a new mobile shell layout, give its `#shell-content` a unique `data-shell-id`.** Cross-shell links must still be plain `<a>` (no `data-shell-link`).

---

## Navigation Integrity — no dead ends — STRICT

**Rule:** Every clickable control must go to a real, reachable destination. A link/button that 404s, opens a modal that isn't on the page, or is a bare `href="#"` with no handler is a defect — treat it like a broken build.

- **`route('name', …)` must name a real route** with the **correct binding key** — `member.show` binds `{uuid}`, but `member.update`/`edit`/`destroy`/`upload-picture` bind `{id}`; club-admin routes bind `{club}` (id-or-slug via `Route::bind('club')`); many public routes need a `{country}` prefix (`/{country}/clubs/{slug}`). Passing an id to a `{uuid}` route (or omitting `{country}`) is a runtime 404, not a compile error — audit these when linking.
- **A modal trigger requires the modal on the page.** `data-bs-toggle="modal" data-bs-target="#x"` needs an element `id="x"` in the rendered output; if the real editor is an Alpine component (e.g. `<x-profile-modal>` opening on the `open-profile-modal` window event), dispatch that event instead — `onclick="window.dispatchEvent(new CustomEvent('open-profile-modal'))"`. Don't point at a `#modalId` that doesn't exist.
- **`data-bs-*` goes through the bridge** (`app.blade.php`), which fires on `data-bs-toggle`. `data-bs-target` **without** `data-bs-toggle` does nothing. `window.bootstrap.Modal` / `bsModal.show()` are shimmed and work.
- **No placeholder `href="#"`** on a control that implies navigation. Wire it to the real route/tab, or don't render it. Prefer omitting an unbuilt action over shipping a dead button.
- **JS-built URLs** (`fetch`/`location.href`/template literals) must match a real route pattern — verify against `php artisan route:list` before committing. Dead/orphaned handler clusters that point at removed routes should be deleted, not left dormant.
- **To verify:** dump the route table (`php artisan route:list --json`) and cross-reference `route('…')` names, then click-test the primary actions on any page you touch.

---

# PART 8 — DATA, UPLOADS & PEOPLE
## Upload Storage Structure and File Naming — STRICT

**Rule:** Every uploaded file must be stored in a clear, organized, entity-based folder structure, and every stored filename must be generated by the application. Never store uploads using their original client filename.

### Folder structure — build every path with `App\Support\StoragePath`

**Never hand-write a storage path again.** `App\Support\StoragePath` is the single
source of truth for the layout, and the media layer already goes through it. Every
path it produces has the same shape:

```
{owner}/{owner-public-id}/{purpose}/[{child}/{child-id}/]{generated-name}
```

```
members/{user-uuid}/profile|documents|payments/{subscription}|posts/{post}
clubs/{club-slug}/branding|gallery|documents|packages/{id}|products/{id}|posts/{id}
businesses/{business-slug}/…
events/{event-uuid}/branding|documents
events/{event-uuid}/matches/{match-id}/clips/{media-uuid}.mp4     ← a bout's video
events/{event-uuid}/unassigned/clips/…                            ← filmed with no bout loaded
challenges/{id}/…      duels/{id}/media/…      platform/{purpose}/…
cache/hls/{media-uuid}/…        cache/fetched/{media-uuid}/…      ← DERIVED, disposable
```

Four rules the shape encodes — keep them:

1. **Owner first.** Everything for one member, club or event is one subtree: one
   place to browse on a NAS, total up, move to another vault, or delete when they
   leave.
2. **Public ids, never auto-increment ids**, wherever the entity has one (member =
   uuid, club = slug, event = uuid). A numeric id is used only where there is no
   public id yet (a bout, a package) and is safe there because **these paths are
   never URLs** — media is served by the media file's own uuid through a
   controller that authorises first.
3. **Generated names.** A uuid plus a server-decided extension. The uploaded
   filename is untrusted metadata: keep it in the DB if it is wanted, never on disk.
4. **Derived files live under `cache/`.** HLS ladders, fetched copies, generated
   posters. One root, so "delete this to reclaim disk" is always safe and the vault
   sync knows what never needs to leave this server.

A folder that a human will browse gets a small `meta.json` beside the files
(`MediaVaults::putMeta()`) — labels only, never a copy of the record. An event
folder named by a uuid tells somebody standing at a file browser nothing.

Do not dump unrelated uploads into shared flat folders.

> **Legacy folders still exist and are NOT to be moved casually.** `avatars/`,
> `documents/`, `payment-screenshots/`, `order-proofs/{id}/`, `perks/{slug}/`,
> `timeline/{slug}/`, `club-products/{id}/`, `packages/`, `achievements/`,
> `goal-proofs/`, `business-logos/`, `user-posts/`, `images/`, `temp/` predate this
> and are still read by the code that wrote them. `StoragePath` is the shape
> everything NEW takes; migrating an old folder is a separate, deliberate change
> with a verified backup behind it (RULE #1, RULE #2).

### File naming
- Never store the original uploaded filename.
- Never store a filename derived from the original name by adding a suffix, prefix, timestamp, or slug.
- Always generate the stored filename on the server.
- Use a random, UUID, ULID, hash-like, or timestamp-plus-random generated filename.
- The extension must be assigned safely by the server based on validated file type rules.
- The original filename may be discarded entirely or stored only as untrusted metadata when explicitly needed, but never used as the storage filename.

### Display title
- The human-readable file title must come from a separate form input and be stored separately in the database.
- The display title is for UI only.
- The physical stored filename is for storage only.
- Never treat the original uploaded filename as the display title automatically unless explicitly required and safely sanitized.

### Security requirements
- Folder paths must be application-generated, never user-controlled.
- Do not allow path traversal or nested user-provided paths.
- Files must be validated by authorization, type, size, and real content rules before storage.
- Sensitive uploads should be stored in private/non-public storage and served through controlled access.
- Publicly accessible files must still follow generated naming and proper access design.

### Required mindset
Storage structure must be readable to developers, but filenames must be non-meaningful to attackers.
Organized folders, random filenames, and separate display titles are mandatory.

---

## Delete Files Before Records — STRICT

**Rule:** Whenever a record owns uploaded files (proof images, photos, documents, attachments, media), the **files MUST be deleted FIRST, then the record**. Never delete a row and leave its uploaded files orphaned in storage. This applies to every delete path — admin actions, cleanup scripts, manual DB maintenance, and bulk wipes.

### How it's enforced in code

- Trait **`App\Traits\DeletesUploadedFiles`** hooks the model's `deleting` event and purges the declared files *before* the row is removed (while the paths are still available). Best-effort: a missing file never blocks the deletion.
- Each file-bearing model declares its uploads + disk:
  ```php
  use App\Traits\DeletesUploadedFiles;

  protected array $fileUploads = [
      'proof_of_payment' => 'local',   // attribute => disk
      'refund_proof'     => 'local',
      'cover_image',                    // bare entry → 'public' disk
  ];
  ```
- On `SoftDeletes` models the trait only purges on a real **force-delete** (a soft-deleted row may be restored).
- Already applied to: `ClubMemberSubscription` (`proof_of_payment`, `refund_proof` on `local`), `Order` (`payment_proof_path` on `public`). **Apply this trait to any new model that stores file paths.**

### Caveats

- **Eloquent events do NOT fire on mass/bulk deletes** (`Model::query()->delete()` / `->forceDelete()`). For bulk cleanups, either iterate (`->cursor()->each(fn ($m) => $m->forceDelete())`) so the trait runs, or delete the files explicitly alongside the rows.
- When clearing financial records by hand, also wipe the proof folders: `storage/app/private/payment-proofs/`, `storage/app/public/order-proofs/`, `storage/app/public/payment-screenshots/` (keep the dirs + `.gitignore`).

---

## Image Uploads Must Validate Real Bytes — STRICT

**Rule:** NEVER derive a stored file's extension from the client-supplied data-URI header, and never write request bytes to disk with `file_put_contents`/raw paths. Every base64 image upload MUST go through the **`App\Traits\StoresBase64Images`** trait, which sniffs the real MIME from the decoded bytes, assigns the extension server-side from a whitelist (jpg/png/gif/webp — **SVG rejected**, it can carry script), and returns the stored path (or `null` to reject).

### Why
The old pattern `$ext = explode('image/', $parts[0])[1]` let a logged-in user upload `shell.php`/`.svg` by lying in the data-URI header → stored XSS, and RCE under the common nginx/php-fpm docroot config. Fixed across all upload endpoints on the `harden/launch-readiness` branch.

### How to add an upload endpoint
```php
use App\Traits\StoresBase64Images;   // add to the controller class

$path = $this->storeBase64Image($request->image, $folder, $filenameBase);
if ($path === null) {
    return response()->json(['success' => false, 'message' => 'Invalid or unsupported image.'], 422);
}
// delete the OLD file only after a successful store, and only if the path changed
```
- Validate the request with **`UploadImageRequest`** (or the same rules): `image` = `starts_with:data:image/`; `folder` = `regex:/^[A-Za-z0-9_\-\/]+$/`; `filename` = `regex:/^[A-Za-z0-9_\-]+$/` (blocks traversal + stray extensions).
- The extension is assigned by the trait — do **not** append `.$ext` yourself, and don't trust `$request->filename` to already carry one.
- The `$folder` must be built by the app from the owning entity's public id per **Upload Storage Structure and File Naming** — never passed through from raw client input.
- Endpoint-level regression coverage lives in `tests/Feature/UploadSecurityTest.php`; the trait's unit coverage is `tests/Feature/StoresBase64ImagesTest.php`. Add a case when you add an endpoint.

---

## Canonical Enum Vocabularies — STRICT

**Gender is stored and validated as `Male` / `Female`** everywhere — never `m`/`f`. This was normalized by migration `2026_06_05_000001_normalize_gender_enum_on_users_table` and is the value the `<x-gender-dropdown>` submits. Every FormRequest/controller uses `in:Male,Female`. When writing tests or new forms, send `Male`/`Female`; sending `m`/`f` will fail validation silently (redirect-back with errors, no row created).

---

## Who Fills The Form Decides What It Demands — STRICT

**A person form's required fields depend on WHO is filling it in, never on whether
it is a create or an edit.** Two cases, and they pull in opposite directions:

- **Staff entering someone else** — super-admin, club owner, club admin, event
  organiser, appointed event official — must be asked for **a name and nothing
  else**. They routinely do not have the rest: a referee arrives on a federation
  list with only a name, an athlete is entered at a weigh-in off a paper sheet, a
  walk-in is registered at the door mid-session. Forcing a value there does not
  produce data, it produces an **invented** birthdate — which is worse than a
  blank, because it looks authoritative and nobody revisits it.
- **The member on their own profile** — the only person who actually knows these
  answers. Their form stays strict, and their first visit to the edit screen is
  the moment worth asking. Anyone else editing a person they know (a guardian and
  their dependent) stays strict too.

### How it is enforced
- `User::entersPeopleOnBehalfOfOthers()` is the predicate: super-admin, owns a
  tenant, holds `club-admin`, or has any `event_officials` row. It decides
  **strictness only** — never what anyone may DO. Every authorisation check stays
  exactly where it is; widening this grants no access.
- `App\Http\Requests\Concerns\PersonFieldRules` supplies the presence rule.
  Use `$this->personRule('date')` rather than writing `required|date`, and pass
  the subject's user id on an edit so a member's own profile resolves as strict:
  `$this->personRule('in:Male,Female', (int) $this->route('id'))`.
- Applied to `StoreMemberRequest`, `StoreFamilyMemberRequest`,
  `Admin\StorePlatformMemberRequest`, `UpdateMemberRequest`,
  `UpdateFamilyMemberRequest`, `UpdateProfileRequest`. **A new person form joins
  them** — do not hand-write `required` on these fields again.

### Non-negotiables
- **`full_name` is always required.** A member without a name breaks every
  listing, card and search result on the platform.
- **`birthdate` is NEVER required — of anyone, on any form, including a member
  editing their own profile.** It is the field people least often have to hand,
  and an invented one is the worst kind of bad data: it drives age groups, weight
  categories and the minor safeguards, so a guess there is not a cosmetic
  blemish. The format is still enforced when a value is given (`date`, and
  `before:today` where it already applied). ⚠️ Consequence to keep in mind: code
  that asks "is this a minor?" reads a NULL birthdate as an ADULT
  (`$user->birthdate ? age < 18 : false`), so a missing birthdate silently
  removes the minor protections — see `PersonalEventController::boutSide()`. If
  that default is ever wrong for a surface, fix it at that surface; do not fix it
  by demanding a birthdate.
- **Format rules are never relaxed.** A gender is still `Male`/`Female`
  (Canonical Enum Vocabularies), a birthdate is still a date, an email is still
  an email. Only the demand that a value be *present* is lifted.
- **The client must agree with the server.** `<x-profile-modal>` computes
  `$demandPersonFields` from the same predicate and gates its own checks on it. A
  browser that refuses what the endpoint would accept is a worse bug than no
  validation, because the user cannot get past it.
- **Absent is not the same as blank.** On an update, a field the request never
  sent must be left ALONE; a field sent empty is a deliberate clear. Reading
  `$validated['gender']` unconditionally lets a partial request wipe a value that
  was already on file — see the `$optional` block in `MemberController::update()`
  and `FamilyController::update()` for the shape to copy.

> Rationale: the platform's job is to record what is known, not to extract what
> is not. A blank field an organiser can fill in later beats a fabricated one
> nobody knows is fabricated.

---

## Profile Pictures Are Portrait 3:4 — STRICT

**Every profile picture on this platform is portrait: 3 wide by 4 tall.** Stored at
**600×800**, cropped through the locked `<x-takeone-cropper>` viewport at `300×400`.
Verified against the real library — 53 of 54 stored pictures are exactly 600×800, and the
one exception (1194×1579) is the same ratio uploaded at full resolution.

**Never assume square.** Any container that shows a person's face must be 3:4, so the
crop the member chose is what the reader sees. A `w-10 h-10` avatar silently centre-crops
a portrait into a square and cuts the top of the head — it does not fail, it just looks
wrong, which is why this needs stating once rather than being caught per screen.

### How to size one
Pick the height, then take three-quarters of it for the width:

| Height | Width | Tailwind |
|---|---|---|
| 32px | 24px | `w-6 h-8` |
| 48px | 36px | `w-9 h-12` |
| 56px | 42px | `w-[42px] h-14` |
| 64px | 48px | `w-12 h-16` |
| 128px | 96px | `w-24 h-32` |

Always pair with `object-cover` on the `<img>` and `overflow-hidden` on the container.

### Two rules that travel with it
- **Fall back to `<x-gender-avatar>`**, not an icon or initials, wherever a real person's
  face is expected and missing — it is drawn for this ratio.
- **Honour `users.profile_picture_is_public`** on any surface wider than the member's own
  profile (bout pages, brackets, court displays, public profiles). It is the member's own
  choice about their face; `BracketView::photo()` is the reference implementation. No
  picture is not a bug, and the absence must be silent — never a "hidden" label.

> Applies to club logos too, in the opposite direction: logos are transparent PNGs of
> arbitrary shape and use `object-contain` on a bare sizing box (Design Rule #5), never a
> 3:4 crop.

---

## LOCKED — Do Not Modify: Profile Picture Cropper Config

These values are final and must never be changed:

**`resources/views/components/profile-modal.blade.php`** (`<x-takeone-cropper>` call):
- `width="300"` — viewport crop width
- `height="400"` — viewport crop height
- `shape="rectangle"`
- `:canvasHeight="500"` — canvas work area height (must stay 500, NOT 400)

**`resources/views/vendor/takeone/components/widget.blade.php`** (modal-dialog div):
- Must use: `class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: {{ $modalMaxWidth }}; width: {{ $modalWidth }}px;"`
- Do NOT replace with `.modal-lg` or any class-only approach
- Default params: `$modalMaxWidth = '75%'`, `$modalWidth = 1000`

---

## Payment Gateway

Intentionally **deferred indefinitely** — additional cost not wanted at this stage.

- Do not suggest payment gateway integration (Stripe, Tap, Benefit Pay, etc.)
- The intended workflow is manual: member uploads proof-of-payment → club admin approves
- Do not design features that depend on a payment gateway

---

# PART 9 — CONVENTIONS, TESTING, OPS & STATUS
## Coding Conventions

- PHP 8.2 — use named arguments, match expressions, null-safe operator where appropriate
- Blade components live in `resources/views/components/`
- Use `@stack('scripts')` / `@stack('modals')` to inject page-specific JS and modals
- AJAX responses return `response()->json(['success' => true/false, 'message' => '...'])`
- Authorization checks follow the pattern: super-admin → own profile → family relationship → club admin of member
- Throttle middleware is applied on all write routes (`throttle:member-write`, `throttle:admin-write`, `throttle:uploads`, etc.) — and on expensive reads/lookups per the anti-enumeration rules
- **Guard `@php` helper-function declarations.** The member profile (`components-templates/member/show.blade.php`) and the super-admin member view (`family/show.blade.php`) are near-identical twins that each declare top-level PHP helpers in `@php` blocks (`calculateTimeDifference`, `getChangeIcon`, and `calculateAgeAtDate` in their `affiliations-enhanced` partials). Any PHP function declared in a Blade `@php` block MUST be wrapped in `if (! function_exists('name')) { … }` — otherwise rendering both views (or the same view twice) in one request/process is a "Cannot redeclare" fatal. JS functions inside `<script>` are exempt.

### Member profile — self-service create flows (built on `member/show` + mirrored to `family/show`)
The member/family profile "Actions" menu creates records via shared endpoints on `MemberController` (auth: super-admin → own profile → guardian). Each opens an Alpine modal (`@open-*-add-modal.window`), POSTs JSON, and patches the DOM in place (No-Reload rule):
- **Goals** — `member.store-goal` / `family.store-goal` (`StoreGoalRequest`, `Goal` model). Goals had update-only before; this added create.
- **Attendance** — `member.store-attendance` / `family.store-attendance` (`StoreAttendanceRequest`, `Attendance` on `members_attendance`).
- **Event participation** — `member.store-event` / `family.store-event` (`StoreMemberEventRequest`, new `MemberEvent` model / `member_events` table): a free-form personal event log, distinct from club-event registrations (`ClubEventRegistration`) and tournaments (`TournamentEvent`).
- **Achievements stay club-awarded** (read-only `member_award` via `ClubAchievement`) — there is intentionally no member self-service "add achievement".
- Tests: `GoalTest`, `AttendanceRecordTest`, `MemberEventTest`, `ProfilePageRendersTest`.

### People discovery (member-to-member)
`PeopleController` provides a platform-wide **"Find People"** search (`me.people` page + `me.people.search` AJAX) and a **SAFE public profile** at `people.show` (`/people/{uuid}`). Views are device-split: `people/{mobile,desktop}/{index,show}.blade.php` (+ `people/partials/club-row`).
- **`users.is_discoverable`** (bool, default true) = opt-OUT of discovery. It gates BOTH search visibility AND cold DMs — a discoverable member has opted into being found *and* contacted, so `User::canMessage()` allows messaging any discoverable member. Toggle in `/me/settings` → `me.discoverable.update`.
- **Public profile shows only safe data** (name, photo, clubs active+history, skills, medals, challenge win-rate). It must NEVER expose health/billing/documents/contacts/family — those live only on the family/admin-gated `member.show`. `User::canViewPublicProfile()` = any signed-in viewer not blocked either way; viewing your own → redirect to `member.show`.
- **The old `/u/{slug}` wall + follow notifications now redirect to `people.show`** (was `member.show`, which 404'd for non-family). New "view another member" links should target `people.show`, not `member.show`.
- Follow routes are `wall.follow` (POST) / `wall.unfollow` (DELETE) bound by `{user:slug}` — use the slug, and DELETE to unfollow. Tests: `PeopleDiscoveryTest`.
- The `/people/{uuid}` binding is the reference for the **Unpredictable Resource Identifiers** rule: public key = uuid, and authorization still runs on every request.

---
## Never Run Tests With a Cached Config — STRICT

**Rule:** `php artisan config:clear` **before** running the test suite, every time. Only re-run `config:cache` afterwards.

`RefreshDatabase` runs `migrate:fresh`, which **drops every table**. `phpunit.xml` points the suite at `DB_DATABASE=:memory:`, but `config:cache` freezes `env()` into `bootstrap/cache/config.php`, and a cached config silently overrides those phpunit env vars — so the suite runs `migrate:fresh` against the **real database**.

This destroyed the stage database once (2026-08-02). `tests/TestCase.php` now refuses to run while a config cache exists, and refuses any sqlite connection that is not `:memory:`, aborting before a single table is touched. **Do not weaken or bypass that guard.** If it fires, the fix is `php artisan config:clear` — never deleting the check.

Correct order: `config:clear` → `vendor/bin/phpunit` → `config:cache`.

---

## 403s Redirect on Web, Return JSON on API — expected behavior

The global handler in `bootstrap/app.php` intentionally converts a 403:
- **Browser navigation** (non-JSON) for a logged-in user → `redirect('/')` with an error flash (so a stale higher-privilege page never dead-ends on a raw 403). Access is still denied.
- **JSON/AJAX** (`expectsJson`) → real `403`.

So authorization tests must assert `assertRedirect('/')` for a `get()` and `assertForbidden()` for a `getJson()` — a browser GET to a forbidden page is a `302`, not a `403`. Don't "fix" the controller to emit 403 on web; that reroute is deliberate.

### Tenant resolution in `role:`/`permission:` middleware
`CheckRole`/`CheckPermission` resolve the tenant via `resolveTenantId()` (bound `{club}` model → numeric id → `slug` lookup). Do not reintroduce the old inline `$a ?? $b ? c : d` expression — `??` binds tighter than `?:`, so it always resolved by slug and returned `null` under a `{club}` binding, silently defeating any club-scoped role/permission check.

---

## Technical Notes

- **Storage permissions:** `umask(0002)` set in artisan for group-writable storage
- **Primary color:** 65% lightness (HSL)
- **Bootstrap replacement:** The project uses a custom JS bridge (`app.blade.php`) that handles `data-bs-*` attributes without Bootstrap CSS/JS. Do not add Bootstrap JS or assume Bootstrap modal/tab APIs work natively — they go through this bridge.
- **No npm test framework** — verify UI features manually or via dev server
- **No native browser dialogs** — never use `alert()`, `confirm()`, `prompt()`, or any native browser dialog. Always use `window.showToast(...)` for notifications and custom modals for confirmations.

---

## Skills

### `frontend-design`
Use the `frontend-design` skill whenever the user asks to build or design new UI — pages, components, modals, cards, sections, or any visual interface work. It generates production-grade, polished frontend code that avoids generic AI aesthetics.

**Trigger on:** "build a page", "create a component", "design a section", "add a new view", "make a modal/card/form", or any request that involves writing new Blade/HTML/CSS.

**Do not trigger on:** pure backend changes, bug fixes to existing UI, or minor copy/label edits.

The skill must still respect every rule in Part 5 — it enhances quality but does not override Design Rule #1 (no unrequested redesigns), the Design System tokens, the Component-First / Standalone rules, or any Security rule.

---

## Financials Feature Status

- Step 1 ✅ `PlatformController@joinClub` auto-creates a `ClubTransaction` on package registration
- Step 2 ✅ `getMonthlyFinancials()` uses real DB query — dashboard chart works
- Step 3 ✅ "Cash to Collect" sums `amount_due` from unpaid subscriptions
- Step 4 🔜 Mark subscription as paid from members admin page — **not started**

---

## Demo Data — Super-Admin Showcase (REMOVABLE before go-live)

A large, cohesive demo dataset wired to the super admin (`superadmin@takeone.bh`) so every surface looks full for demos. **It is fully removable** — built specifically so it can be wiped before going live.

- **Seed:** `php artisan demo:seed` (options: `--clubs=6 --members=40 --admin=<email> --fresh`). Creates a business/chain, N mixed-sport clubs (Taekwondo/Boxing/Fitness/Swimming/Padel/Yoga), trainers, packages+activities with weekly class schedules, members + active subscriptions, club feeds, challenges, events, and shop products. Wires the super admin as owner of every club + business, enrolled in 2 (synced `/me/schedule` classes), coaching 1 (teaching classes), plus personal sessions, feed posts/stories/follows, challenge participations, event registrations and a duel.
- **Purge:** `php artisan demo:purge` (`--force` to skip prompt). Removes **exactly** what was seeded.
- **How removal stays exact & safe:** `demo:seed` records every created row id (and any uploaded file) to a **manifest** at `storage/app/private/demo/manifest.json` (`App\Support\DemoManifest`). `demo:purge` reads it, deletes files first (defensively scanning file-bearing columns in case images were uploaded to demo records via the UI), then deletes rows in reverse-FK order inside a transaction. It can never touch real imported members or the super-admin account. Second safety net: demo clubs use slug `demo-*` and demo users `@demo.takeone.bh`.
- Commands: `app/Console/Commands/DemoSeed.php`, `app/Console/Commands/DemoPurge.php`. Only one manifest at a time (re-seed requires purge or `--fresh`).
- Demo/trial utilities are **not** exempt from the Security First rules — they run against the real environment and real storage.

---

## Pre-Launch Runbook — operational, NOT code

These are launch gates that live in the **environment/ops**, not the repo — a green test suite does not clear them. Verify before any production go-live. (Surfaced by the launch-readiness audit; code-level findings from that audit are already fixed and codified in the STRICT sections above.)

- **Production env flags (BLOCKER).** The live `.env` must be `APP_ENV=production` + `APP_DEBUG=false` (it was `local`/`true` on `https://takeone.bh`, which leaks a full stack trace + secrets on every error). Also set `LOG_LEVEL=warning` and `LOG_STACK=single,sentry` (so `Log::error()` reaches Sentry). **Re-run `php artisan config:cache` after ANY `.env` change** — caching freezes `env()`.
- **Warm prod caches on deploy:** `config:cache` + `route:cache` + `event:cache` + `view:cache` — all four now succeed. `view:cache` used to die with `DirectoryNotFoundException` for `vendor/takeone/cropper/src/resources/views`: takeone/cropper's provider registers `__DIR__.'/resources/views'` from `src/`, but ships its views one level up at `resources/views`. `AppServiceProvider::pruneMissingViewPaths()` now drops non-existent paths from every view namespace on `booted()`, so the bad hint is gone before `view:cache` walks it. The published copy at `resources/views/vendor/takeone/` is what resolves at runtime, and still does. **This is a workaround for an upstream package bug** — fix the path in `laravel-image-cropper` and the guard becomes a no-op (keep it; it protects against any package doing the same).
- **Backups / DR (PARTLY DONE — off-server still a BLOCKER).** `php artisan takeone:backup` snapshots the DB (SQLite `VACUUM INTO` after a WAL checkpoint, so it is consistent under load) plus the upload folders, **verifies each artifact by reading it back**, prunes on a retention window (`BACKUP_RETAIN_DAYS`, never below `BACKUP_KEEP_MINIMUM`) and exits non-zero on any failure. Scheduled nightly at 03:30 in `routes/console.php`; config in `config/backup.php`. ⚠️ **Still required: set `BACKUP_DISK`** to a remote filesystem — until then every copy sits on the same disk as the data and does not survive losing the box (the command warns on every run). Also **the Android release keystore is a blocker of its own**: it is not in this repository, and the Flutter build reads it from `flutter/TV/android/keystore.properties` (git-ignored, absent here — the build falls back to the debug key and cannot produce a store-acceptable artefact). Put the real keystore and that properties file on the release machine, and copy the keystore to secure external storage — losing it means the Play Store app can never be updated. (`/database/*.sqlite*` is now in `.gitignore` — the DB, its `-wal`/`-shm` sidecars, and `.bak-*` snapshots can never be committed. Nothing sqlite was ever tracked, so no history scrub was needed.)
- **Mail (MAJOR).** Queued mail had failing jobs (`SendQueuedMailable`) — verification emails silently not sending. Diagnose the Gmail SMTP creds, send a real end-to-end verification, then clear `queue:failed`. Mail is real Gmail SMTP (`smtp.gmail.com:465 ssl`), queued — see the no-Mailpit rule.
- **Datastore at scale (MAJOR).** Production is SQLite (WAL). Fine for a soft launch, but SQLite is single-writer — under concurrent multi-club writes it throws "database is locked". A ready `mysql` connection already sits in `config/database.php`; migrate before real concurrency. Keep WAL checkpoints healthy (the `-wal` file should not dwarf the DB).
- **Workers as least-privilege.** Supervisor `queue:work` should run as `www-data`, not `root`.

---

