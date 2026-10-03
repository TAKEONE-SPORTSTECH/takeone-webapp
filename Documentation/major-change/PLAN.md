# MAJOR CHANGE — Parties, Relationships, One Event Surface

Branch: `major-change` (cut 2026-10-03 from `brackets-fix`, carrying its 30 uncommitted files unchanged).
Status: **PLAN — awaiting owner approval. Nothing is built.**

---

## 0. What we are building, in one paragraph

Everyone and everything on TakeOne is a **party**: a person, a club, a business/chain,
a federation, an event, and later a clinic, trainer practice, venue or shop. Every link
between two parties is ONE dated **relationship** row (who → what role → in which party →
from → to, + share %). Ownership with shares, a federation president's term, a coach
joining and leaving, a club joining a federation, a club hosting an event — all the same
shape, all with history, nothing overwritten. Permissions are read from the
**currently active** relationships. Events get ONE page and ONE admin for every host
(person, club, federation), and the organiser chooses which entry channels an event
accepts (individual, club squad, later federation), with the payer recorded separately.

## 1. The non-negotiables this plan is built around

- **RULE #1 — nothing that works today may break.** Every phase is additive, runs in
  parallel with the old path, is verified, and only then cut over — module by module,
  behind flags defaulting OFF. Retiring old columns/tables is a separate, later approval.
- **RULE #2 — backup first.** `php artisan takeone:backup` (verified) before every
  migration, backfill or data repair in every phase.
- **Tests before and after every step** (see §9). A step is done only when the suite shows
  **no new failures against the baseline**.
- **Security first** — every new table, endpoint and MCP tool is scoped and tested for
  A-cannot-reach-B.

---

## 1a. Live-server protocol (owner, 2026-10-03: "this is a live server running a live service")

`/var/www/takeone` is the box serving the site, so every file saved here is live the moment
it is saved, and every migration hits the real database.
- **Work in the order: code that nothing calls yet → tests → flag.** A new class nobody
  references, or a migration that only ADDS, cannot change what users see.
- **One migration at a time**, each preceded by `takeone:backup` (verified) and followed by a
  page check of the affected screens.
- **No `migrate:fresh`, no `db:seed`, no bulk writes** without a fresh verified backup and
  an explicit go-ahead.
- **Artisan runs as `www-data`**, never root. `config:clear` before tests, `config:cache`
  straight after, so the site never runs uncached for longer than a test run.
- **Flags default OFF** in `.env`. Switching one ON is a separate, announced step, with the
  OFF switch as the rollback.
- **Quiet hours:** schema changes and backfills run outside event days and peak hours,
  agreed with the owner first.
- Production (`takeone-live`) only changes through a `main` deploy, which the owner triggers.

## 2. Where we stand today (audit, 2026-10-03)

### 2.1 Ownership — one person, three disagreeing places, no history
- `tenants.owner_user_id`, `businesses.owner_user_id`, `federations.owner_user_id` — single
  owner, NOT NULL, **ON DELETE CASCADE** (hard-deleting a user deletes their clubs).
- A club's owner is ALSO a `club-admin` row in `user_roles` AND a `type='owner'`
  subscription. Of 9 code paths that change an owner, most update only the column.
- Only business transfers are logged (`business_ownership_logs`, 0 rows).
- ~175 code references to `owner_user_id` (≈30 authorization, ≈30 notification-recipient,
  ≈20 display, ≈25 create, 9 transfer, 10 seed/factory, ~12 MCP), plus 37 in tests.

### 2.2 Roles & staff — seven mechanisms, none dated
| Source | Meaning | Dates? |
|---|---|---|
| `user_roles` (+ `roles`, `permissions`) | super-admin / club-admin / instructor / member per club | no — revoke = delete |
| `club_instructors` | club staff (coach, secretary…), wage, `is_active` | no — unique key blocks a 2nd stint |
| `federation_staff` | federation officers, per sport | no — hard delete |
| `memberships` | person ↔ club | no |
| `club_member_subscriptions` | paid membership | **yes** (start/end) |
| `federation_clubs` | club ↔ federation | decision date only |
| `tenants.business_id`, `federations.parent_id` | club ↔ chain, federation ↔ parent | no |
| `event_officials` | per-event roles (organiser, jury…) | event-scoped |
| `federation_athlete_clubs` | athlete ↔ club in a federation | **yes** — the model to copy |

Only ONE permission slug (`enter-athletes`) is actually enforced; `permission:` middleware is
unused; `role:` is only ever `role:super-admin`; there are no Laravel policies — every club
controller calls `authorizeClub()` by hand.

### 2.3 Events
- `club_events` has `tenant_id` (club) AND newer `host_type/host_id`; `EventHost` resolves
  club / federation / none. **Self-organised events are impossible** (`tenant_id` required at
  store; currency, bank details, logo, notifications, bans all read `$event->tenant`).
- **Three create/edit forms**: `/me` event-create (the rich one), the club-admin
  `ClubEventController` form (simpler, different fields), and the federation dashboard modal
  writing `federation_events`, synced one-way into `club_events` by
  `FederationPlatformEvent` (flag `FEDERATION_PLATFORM_EVENTS`). Run-day is already shared.
- Fields that exist only on `federation_events`: status, logo/art, own documents table,
  test mode, team types/fees, spectator fees, draw date, rich "about", map URL.

### 2.4 Entries & payer
- `entry_channel` = `individual` | `club`; `representing_tenant_id`; `entered_by`.
- **There is no payer column.** `paid_by` is the person who *verified* a payment.
- Federation club entries land on the platform as `individual` with no `entered_by` — the
  fact that a club entered and pays is lost.
- No organiser setting for allowed channels (`entry_mode` members/public is a different switch).

### 2.5 Pre-existing security problems found by the audit (NOT caused by this plan)
1. **Any club-admin can transfer club ownership** (`ClubAdminController::createOwner` /
   `transferOwnership` — gated only by `authorizeClub`, request `authorize()` returns true).
2. **Hard-deleting a user cascades to the clubs/businesses/federations they own.**
3. `entersPeopleOnBehalfOfOthers()` — documented as "never decides what someone may do" —
   is used as a security exemption in `DeskSignInCode` and `PublicEntry`.
4. Giving a member custom per-member access silently strips their `club-admin` role.
5. MCP `canAdminClub` does not recognise the chain owner (web does) — MCP/web disagree.
6. The first user to register becomes super-admin if none exists.
7. Old owner keeps `club-admin` forever after a transfer.

→ **Decision needed (§10):** fix these as separate small guarded steps before Phase 1,
or fold them into the phases where they naturally fall.

### 2.6 Test baseline (2026-10-03, `major-change` @ 834354a8 + uncommitted)
- The full suite **cannot run in one process** — it exhausts 2 GB at ~test 950/1639.
  Baseline was taken **one file at a time**: 148 files, **132 pass, 16 fail, 48 failing
  tests**, all pre-existing (mostly event pages/translation/participant card, from the
  uncommitted `brackets-fix` work and the `/e/` removal).
- Exact list: `Documentation/major-change/baseline-failing-tests.txt`. **The rule for every
  step: no test outside that list may fail.**

---

## 2.7 Pre-work DONE (2026-10-03, owner's go-ahead: "fix the security issues and failing tests first")

**Suite: 152 files, 1,610 tests, 0 failures** (run with `scripts/test-per-file.sh`).
`baseline-failing-tests.txt` is now EMPTY — from here on, any failing test is new breakage.

Tests: `/e/` tests repointed to `/me/events/…`; `phpunit.xml` pins `EVENT_UNIFIED_PAGE=false`
and stops reading `bootstrap/cache/routes-v7.php` / `events.php` (tests used to depend on
the server's `.env` and its last `route:cache`); route tripwire 769→937 reconciled;
`times_in_zone` translated into the 62 missing languages; bracket test follows the owner's
"arranging never decides a bout" rule; module-boundary breach in the register wizard fixed.

Security (§2.5):
- ✅ #1 transfer/create-owner AND club deletion: owner / approved chain owner / super-admin
  only (`canActAsClubOwner`); controls hidden on desktop + mobile for everyone else.
- ✅ #2 an owner can never be permanently deleted (`User::forceDeleting`); the delete
  screens now refuse owners of chains and federations too (`ownedOrganisationNames()`).
  The FK cascade itself stays until Phase 8 (needs a table rebuild).
- ✅ #3 desk codes / quick sign-in now refuse chain owners, federation owners and staff
  (`holdsPrivilegedAccess()`); the form-strictness helper is unchanged.
- ✅ #6 public registration never grants super-admin.
- ⏸ #4 custom per-member access stripping club-admin, #5 MCP vs web chain-owner mismatch,
  #7 old owner keeping club-admin — behaviour questions, left for the owner / Phase 4.
- 🐞 Found, NOT fixed (outside scope): create-owner 500s when no blood type is given
  (`users.blood_type` is NOT NULL, the controller writes NULL).

## 2.8 Progress (2026-10-03)

| Phase | State | Notes |
|---|---|---|
| 0 Safety net | ✅ | `scripts/test-per-file.sh`; empty failing baseline; Parties + federation shadow tests |
| 1 party_relations + Relations | ✅ live | migrated on stage after a verified backup |
| 2 Backfill + verify | ✅ live | 109 rows; `parties:verify` = no drift |
| 3 Dual write | ✅ ON on stage | `PARTIES_ENABLED=true`; can never break the mirrored write |
| 4 Shadow authority | ✅ ON on stage | `PARTIES_SHADOW=true`; 1,360 live checks, 0 disagreements. Cut-over (Authority deciding) NOT done — owner's call |
| 5 Co-ownership + votes | ✅ live (on) | owners panel on club details (desktop + mobile), MCP tools |
| 6 One event surface | ⏳ | needs owner input — see §10a |
| 7 Entry channels + payer | ✅ | organiser switch in both consoles; payer on every entry (backfilled). 7.3 deviation: federation entries get the CLUB as payer but keep their channel — their lifecycle belongs to the federation dashboard, and the club channel would hand coaches platform-side squad controls that bypass it. 7.4 invoices still to do (EVENTS-ENTRY-BILLING Phase 3) |
| 8 Retire the old | ⏸ | separate owner approval |

## 3. Target model

### 3.1 Parties
No new "party" super-table to start with. A party is any model registered in the morph map
that implements a small `Party` contract (`partyKind()`, `partyName()`, `partyLogo()`,
`partyCountry()`, `partyCurrency()`, `partyTimezone()`):
`User` (person), `Tenant` (club), `Business` (chain), `Federation`, `ClubEvent` (event).
Later: `Clinic`, `TrainerPractice`, `Venue`, `Shop` — each a module under `app/`.
Morph aliases go through `App\Support\MorphMap` (never class names in a column).

### 3.2 `party_relations` — the one relationship table (new, additive)
| column | notes |
|---|---|
| `id`, `uuid` | uuid is the public key |
| `from_type`, `from_id` | who holds the role (usually a person; can be a club, e.g. club→federation) |
| `to_type`, `to_id` | the party it is held in |
| `role` | from one vocabulary (§3.3) |
| `share_bp` | ownership share in basis points (10000 = 100%), owners only |
| `title` | display label ("Head Coach", "President") |
| `starts_at`, `ends_at` | `ends_at` NULL = active. **Ending ≠ deleting.** |
| `end_reason` | retired / transferred / removed / term-ended… |
| `granted_by`, `ended_by` | user ids, for the audit trail |
| `source` | where a backfilled row came from (`tenants.owner_user_id`, `club_instructors#12`…) |
| `meta` | JSON for role-specific extras (sports scope, wage stays in `club_instructors`) |
| timestamps | |

Indexes: (to_type,to_id,role,ends_at), (from_type,from_id,ends_at).
Invariants enforced in ONE service (`App\Support\Parties\Relations`), never by callers:
- owner shares of one party sum to exactly 10000 at every moment;
- a party always has ≥1 active owner (except events, which have a host);
- a role is ended, never deleted; a transfer = end old + start new in one transaction.

### 3.3 Role vocabulary (one list, modules declare which they use)
`owner`, `admin`, `staff` (+ `meta.staff_type`), `coach`, `member`, `president`,
`officer` (+ `meta.sports`), `host`, `co_host`, `sanctioned_by`, `venue`, `member_club`
(club→federation), `chain_club` (club→business), `sub_body` (federation→parent),
`organiser`, `official` (+ `meta.official_role`).

### 3.4 Authority
ONE service, `App\Support\Parties\Authority`: `can(User, ability, Party)` reading active
relations + super-admin. Abilities are declared per party type by its module
(e.g. club: `manage`, `transfer-ownership`, `enter-athletes`…). It replaces, one caller at a
time, `authorizeClub`, `EventAccess::canManage`'s owner branch, `FederationDirectory::access`,
`AuthorizesClubAccess` (so web and MCP finally agree).

---

## 4. Phases

Every phase = **backup → build behind a flag (OFF) → tests (new + full baseline) → owner
review → turn on in stage → verify → next**. Each phase is its own commit(s).

### Phase 0 — Safety net (no behaviour change)
0.1 `scripts/test-per-file.sh`: runs the suite file-by-file (works around the OOM), compares
    against `baseline-failing-tests.txt`, exits non-zero on any NEW failure.
0.2 Characterization tests for everything this plan will touch that has no coverage today:
    club ownership transfer (all 9 paths), `authorizeClub` matrix (owner / admin / chain owner /
    stranger), `FederationDirectory::access` (owner / staff / sub-body / suspended),
    `ClubEventController` store/update, `EventHost` per kind, MCP `canAdminClub`,
    federation entry → platform row.
0.3 Snapshot of route table count and `/tmp/takeone-smoke.php` result.

### Phase 1 — `party_relations` table + service (additive, flag `PARTIES_ENABLED`, OFF)
Migration, model, `Relations` service with invariants, MorphMap entries, unit tests for
every invariant (shares sum, end-not-delete, transfer atomic, ≥1 owner). Nothing reads it.

### Phase 2 — Backfill (dry-run by default)
`php artisan parties:backfill` (dry run) → `--apply` after verified backup. Sources:
owners (3 tables → `owner` 100%), `user_roles` club-admin → `admin`, `club_instructors` →
`staff`/`coach` (active or ended), `federation_staff` → `officer`, `memberships` → `member`,
`federation_clubs` (approved) → `member_club`, `tenants.business_id` → `chain_club`,
`federations.parent_id` → `sub_body`, `club_events` host → `host`. Idempotent (`source` key).
`php artisan parties:verify` reports any disagreement between old sources and relations.

### Phase 3 — Dual write
Every writer of an old source also writes relations, via model observers where possible
(so the ~25 write sites are not each edited) and explicit calls where Eloquent events don't
fire (e.g. `Business::transferOwnerTo` bulk update). `parties:verify` scheduled nightly;
must report zero drift for an agreed period before Phase 4.

### Phase 4 — Shadow authority, then cut-over per module
4.1 `Authority` answers alongside the old checks; disagreements are LOGGED, old answer wins.
4.2 When a module shows zero disagreements: flip that module's flag so `Authority` decides.
    Order (lowest risk first): MCP → federations → events → clubs admin.
4.3 Security fixes land here naturally (transfer = owners only; old owner's role ends).

### Phase 5 — New capabilities (the reason we are doing this)
Co-ownership with shares + transfer/sell-share flow (with consent rules, §10),
dated terms (federation president), staff history with re-hire, "history" panel on each
party, the person's own "my roles" view. Mobile + desktop, MCP tools.

### Phase 6 — Events: one surface for every host
6.1 Host via relations: `host` (person / club / federation), plus `co_host`,
    `sanctioned_by`, `venue`. Self-organised events become possible (person as host;
    payment details, currency, logo read from the host party, not `$event->tenant`).
6.2 ONE create/edit form = today's `/me` event-create, with a **host picker** limited to
    parties where the user holds a role allowing it. The club-admin form and the federation
    modal point to it. Federation-only fields (§2.3) move into the shared form/console as
    options shown when the host may use them.
6.3 Federation dashboard "Events" opens the shared console. `federation_events` keeps
    working (read-only mirror) in parallel, then is retired in Phase 8.

### Phase 7 — Entry channels & payer
7.1 `club_events.entry_channels` (JSON list: `individual`, `club`, later `federation`),
    default = exactly today's behaviour per event. Organiser switch in the console.
    Turning a channel off blocks NEW entries only.
7.2 `club_event_registrations.payer_type/payer_id` (a person or a club) — backfilled from
    `entry_channel` + `representing_tenant_id`. `paid_by` keeps meaning "verified by".
7.3 Federation entries land as `entry_channel='club'` with `entered_by` and the club as payer.
7.4 Invoices (EVENTS-ENTRY-BILLING Phase 3): one invoice per payer per event.

### Phase 8 — Retire the old (separate owner approval, listed in HOUSE-CLEANING.md)
Readers of `owner_user_id` switched → column kept but unused → dropped later; cascade FKs
removed; `federation_events` + sync retired; club-admin event form removed; dead
`FederationDashboardController`; unused permission plumbing.

---

## 5–8. Cross-cutting
- **MCP:** every phase ships its tools (relations read/write, transfer, host, channels) with
  allow + deny tests, and `Documentation/MCP.md` updated.
- **Realtime:** relation changes push `{action:'refresh'}` to affected people.
- **Mobile/desktop parity** for every screen; design rules (hero band, sheets) apply.
- **Morph map** updated BEFORE any morph column is written.

## 9. How "nothing breaks" is proven, every step
1. Backup + verify (RULE #2).
2. `config:clear` → `scripts/test-per-file.sh` → compare with baseline → `config:cache`.
3. New tests for the step: happy path + A-cannot-reach-B + the old behaviour still holds.
4. Route count unchanged (or changed exactly as intended); smoke script green.
5. Shadow-mode logs show zero disagreements before any cut-over.
6. Each flag can be switched OFF to return to the old behaviour instantly.

## 9a. Owner decisions (2026-10-03)
1. **Shares decide.** Ownership decisions go by majority of shares.
2. **Who may transfer / add a co-owner:** the owner holding a majority, or else a VOTE of
   the owners, weighted by share. Rule (confirmed 2026-10-03): passes when owners holding
   MORE than 50% approve; a 50/50 split does not pass; every vote recorded and dated.
3. Security fixes and failing tests first — DONE (§2.7).
4. **Self-organised event:** the person enters their own payment details.
5. **Federation president:** the term is only recorded (start/end); nothing expires on its own.
6. Failing tests first — DONE.

## 10. Decisions needed from the owner before building
1. **Shares:** do shares only RECORD ownership, or do they decide things (e.g. majority
   owner can transfer; all owners must agree to add a co-owner)?
2. **Who may transfer / add co-owners** of a club / business / federation?
3. **Pre-existing security issues (§2.5):** fix first as small separate steps (recommended),
   or inside the phases?
4. **Self-organised events:** where does a person's payment info come from (their profile)?
5. **Federation leadership:** is a president just recorded with dates, or do terms also
   expire automatically?
6. **The 48 failing baseline tests:** fix them first on this branch (recommended — a red
   baseline hides new breakage), or accept them as the baseline?
