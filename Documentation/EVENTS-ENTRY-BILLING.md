# Competition entry & billing — agreed design

**Status:** design settled 2026-08-16. **Phases 1–2 are built** (entry authority
+ channel; structured fee), and **multi-pricing was added 2026-09-06** (priced
options, a late-entry penalty, and frozen per-entry fee lines — see below; it
reverses decision 3). Pick up at **Phase 3 — invoices**, which now has the fee
lines to build on.

Scope: how athletes get entered into a martial-arts competition / tournament /
championship, who is allowed to enter them, who pays, and when money comes back.
Applies to every combat event type (`Sports/Taekwondo/Tournament`,
`Sports/Karate/Tournament`, and any future sport) because it lives in
`app/Events/Support/` + shared core columns — **not** inside a package.

> **Companion spec:** `EVENTS-PUBLIC-ENTRY.md` — how people who are **not on
> TAKEONE yet** get entered: a public event link, and an incomplete entry that
> carries its own claim link so a coach never has to invent an athlete's weight
> or birthdate. It reuses this document's channels, `enter-athletes` permission
> and fee model unchanged.

---

## Where things stand today

Both entry doors already exist and both are half-finished:

| Path | Code | Gap |
|---|---|---|
| Club channel — coach enters a squad in one sitting | `EntryService::enterMany()` / `roster()` | `administeredClubIds()` accepts only role slugs `owner` and `club-admin`, so **a trainer gets an empty roster with no explanation** and cannot enter anyone |
| Individual channel — athlete self-enters with payment proof | `PersonalEventController` (~1531) | nothing records which club they compete *for*; a club can be represented without knowing |

Money is the weakest part:

- ~~`club_events.participant_fee` is a **varchar**. `AbstractEventType::feeAmount()`
  regex-scrapes the first number out of it, so `"10-15 BHD"` silently bills 10.~~
  **Fixed in Phase 2** — the amount and its currency are columns; see below.
- A coach entering 14 athletes produces 14 unpaid rows and **no bill at all**.
- `finance()` computes `paid_count × fee` as a standalone number. **No
  `ClubTransaction` is ever posted**, so event income never reaches the club
  ledger (unlike package enrollment, which does).

---

## Decisions (confirmed by the owner)

1. **An individual entry does NOT need club approval.** They claim the club they
   represent; the club may *disown* the claim, it cannot pre-approve it.
2. **A coach who enters a squad is billed for all of them — one bill.** The coach
   collects from the athletes offline; the platform does not model that.
3. ~~**One flat entry fee per athlete.** No per-division fees.~~
   **REVERSED 2026-09-06 — see "Multi-pricing" below.** An event may now sell any
   number of named priced options on top of a base fee, and charge a flat penalty
   for entering late. The reasoning behind the original call still holds for
   *divisions* — a fee is not derived from which weight class somebody lands in —
   but it was never true of what an event actually sells: a jiu-jitsu tournament
   runs Gi and No-Gi as separate entries, and a championship sells a T-shirt and a
   banquet seat beside the entry.
4. **A date change alone is not a refund event** while the competition is still
   listed. **Cancellation is.** So is a postponement that moves the start more
   than **14 days** past the date the payer agreed to, and so is moving the start
   to **TBD** (no date), which opens the refund right immediately.

---

## The model

### Entry: two channels, one gate

New on `club_event_registrations`:

- `entry_channel` — `club` | `individual` (backfill from `entered_by`: null → individual)
- `representing_tenant_id` — the club the athlete competes **for**. Nothing
  records this today and it is what federation sheets actually need.
- `club_disowned_at` — set when a club rejects a claim.

- **Club channel** — coach/owner enters. `entered_by` already stores who. The
  **club** is the payer.
- **Individual channel** — athlete self-enters, no approval, and may only claim a
  club they are an *active* member of. The club is notified and may **disown**
  before enrolment closes → the athlete continues as **unattached**. Disowning is
  moderation, never a gate: a slow or absent coach must never be able to block
  someone from competing.

### Entry authority is delegable

New per-club permission **`events.enter_athletes`**, granted through the existing
per-member custom-permission machinery. The owner holds it implicitly and grants
it to trusted coaches. `EntryService::administeredClubIds()` honours it instead of
hard-coding the two role slugs. This matters more now that entering commits the
club to money.

### Billing: one invoice per payer per event

New table **`event_entry_invoices`** (uuid public key, per the Unpredictable
Resource Identifiers rule):

| Payer | Created by | Lines |
|---|---|---|
| Club | coach/owner squad entry | one per athlete entered |
| Individual | self-entry | one |

- Flat fee → the invoice is simply `entrants × fee`.
- **The club is the debtor, not the coach personally.** `entered_by` names the
  coach who committed it, but if that coach leaves, the debt stays with the club.
- **A settled invoice is never mutated.** Athletes added later get a *second,
  incremental* invoice, so a settled amount can never silently change.
- Settlement reuses the established pattern: proof upload → `pending_approval` →
  host organiser approves → `FinancialService::recordTransaction()` posts income
  to the **host** club's ledger. Manual proof-of-payment only — no gateway
  (project policy).
- `registrations.paid` stays as a column but becomes **derived from the invoice**,
  so the roster, `DrawEngine` and scoreboard code are untouched.

Also replace the free-text fee with a structured amount + currency, keeping
`participant_fee` as the display string.

### Refunds: anchored to the date the payer agreed to

Each invoice stores **`date_anchor`** = the event's start date at the moment it
was settled. That is the promise the payer paid against.

| What happens | Refund right |
|---|---|
| Date changes, event still listed, new date within 14 days of `date_anchor` | **No** |
| Start moves >14 days past `date_anchor` | **Yes** |
| Start set to TBD (no date) | **Yes**, immediately |
| Event cancelled or archived | **Yes** |
| Club withdraws its own athletes | **No** — their choice; organiser may still refund at discretion |

- Window default **14 days**, in config, overridable per event so a large
  championship can state its own terms up front.
- When a right opens, everyone who settled is notified over MQTT and a **Request
  refund** action appears on their invoice. It is a *request* — the host organiser
  processes it manually with proof, reusing
  `ClubMemberAdminController::refundPayment()`'s pattern (`type: 'refund'`
  transaction + `refund_proof` on the `local` disk + notification).
- The refund goes to the **payer**. A club that entered 14 athletes receives one
  refund of the whole invoice and redistributes it itself.

---

## Build order

Each phase leaves the app working and is shippable alone.

**Phase 1 — Entry authority + channel (no money)** ✅ **BUILT 2026-08-16**
- Permission slug is **`enter-athletes`** (kebab, matching every other permission
  in `permissions.slug` — the dotted `events.enter_athletes` in the design above
  would have been the only one of its shape). Migration
  `2026_08_16_100100_add_enter_athletes_permission` creates it and grants it to
  `club-admin`/`owner`; `RolePermissionSeeder` seeds it; it appears in the
  per-member access editor under the **Competitions** group.
  `EntryService::administeredClubIds()` now resolves by that permission plus
  implicit ownership, so a coach who holds the grant can enter a squad.
- `entry_channel`, `representing_tenant_id`, `club_disowned_at` on
  `club_event_registrations` (migration `2026_08_16_100000_…`), backfilled:
  channel from `entered_by`, represented club only where the athlete has exactly
  one active membership (never guessed otherwise).
- `RosterPeople` now prints the club the ENTRY names, not the first club the
  athlete happens to belong to; present-but-null means unattached and is never
  overwritten by membership.
- **The club is chosen inside the join sheet**, at the moment of entering, and
  pre-selected with `EntryService::defaultRepresentingClub()` — the club where
  the athlete last practised THIS event's sport (via `SkillAcquisition` →
  `ClubAffiliation` → tenant, matching by `tenant_id` or club name), falling back
  to their most recently joined club. A competitor with a club to represent
  therefore always goes through the sheet, even for a free entry; the "Competing
  for" card on the page is the *change it afterwards* surface and only renders
  once they hold a place.
- Self-entry takes `representing_tenant_id`, re-checked server-side against the
  athlete's own active memberships; the club's entry-authority holders are
  notified; `POST me/events/{event:uuid}/claims/{user}/disown` rejects a claim
  (athlete keeps their place and competes unattached), `GET …/claims` lists them.
- **A missing weight no longer blocks a club entry.** `EnrolmentDecision::defer()`
  marks a refusal the weigh-in desk can settle; the sports' `no_weight` gate now
  uses it. `EntryService` admits a deferrable refusal on the **club** channel
  only — the coach commits their own squad and the athlete stands on the scale on
  the day like everyone else — while **self-entry still refuses**, so a member is
  asked to complete their own profile. Such entries go in unclassified and are
  placed by the new `EventType::classifyEntry()`, which `verifyWeighIn()` calls
  after recording the official weight (and which re-derives the draw). When the
  weight fits no division the event runs, the desk is told plainly rather than
  left with an entrant nobody can draw.
- **Entries close, and the UI closes with them.** `EntryService::entriesState()`
  is the single answer to "may anyone still take a place, and if not why" — not
  open yet / closed on a date / started / over. It drives the entry endpoints,
  the coach's roster rows, the coach's card (not rendered at all when closed),
  and treats an event **due to have started** as closed too — "nobody pressed
  start" is an admin detail, not an invitation —
  and the join button, which is **removed** rather than disabled and replaced by
  a short "Entries are closed · <reason>" panel. Nobody new enters once the
  competition has STARTED either, by either door (`code: started`).
  Someone ALREADY entered is never refused: `register()` is also how they upload
  a receipt, and paying at the venue on the day is ordinary. Spectator tickets
  are unaffected — you can still walk in to watch.
- The coach's roster is **searchable** (name / email / phone, server-side and
  scoped to the actor's own club members) and capped at 60 rows a page; the
  response carries no contact details.
- UI: `partials/event-representing` (the "Competing for" selection cards) and
  `partials/event-squad-entry` (the coach's roster + claims sheet), both shared
  by the mobile and desktop event-show pages.

**Phase 2 — Structured fee** ✅ **BUILT 2026-08-16**
- `club_events.participant_fee_amount`, `spectator_fee_amount` (decimal 10,3) and
  `fee_currency` (migration `2026_08_16_110000_…`), backfilled by scraping the
  existing strings once — the last time a price is read out of prose. A **null**
  amount means the event never stated one (free, by qualification, unstated); the
  `*_fee` varchars stay as the DISPLAY line.
- **`App\Events\Support\EventFee`** is the one place that answers what an entry
  costs: `amount()`, `currency()`, `isPaid()`, `display()`. `AbstractEventType::finance()`
  and every `paid`/`free` check in `EntryService`, `register()`, `ticket()` and
  `cancel()` now read it. `AbstractEventType::feeAmount()` is deprecated and
  called by nothing.
- Write paths send the **amount**; the display line is composed server-side from
  it (`PersonalEventController::feeColumns()`, `Admin\ClubEventController`). Both
  admin forms and the personal create form post `participant_fee_amount`, and the
  edit forms seed their amount box from the column rather than re-parsing the
  string. `ClubEvent`'s `saving` hook derives the number for any caller that
  still sets only the string, so no write path can leave the two out of step.
- Known leftover: entry-by-qualification is still detected by the word
  "qualified" in the display line (`PersonalEventController::register()`). That is
  a vocabulary problem, not a money one — worth a real flag when the create form
  next changes.

**Multi-pricing** ✅ **BUILT 2026-09-06**

Reverses decision 3 above, at the owner's request. The model:

- **`event_fee_options`** — `event_id`, `role` (participant|spectator), `label`,
  `amount`, `is_active`, `sort`, `uuid`. A flat list per role; an entrant ticks
  any number. Deliberately NOT groups with "pick exactly one" semantics — that
  was offered and declined as too much machinery for the shape organisers
  actually need.
- **The base stays the base.** `participant_fee_amount` / `spectator_fee_amount`
  are unchanged and remain what entering costs at all; options are ADD-ONS.
  `total = base + options ticked + late penalty`. This is what makes the change
  safe for events already in the database — one with no options answers every
  existing question exactly as before — and it is also what stops a flat
  tick-list producing a free entry when somebody ticks nothing.
- **`club_events.late_fee_amount` + `late_fee_from`** — a flat penalty added once
  to an entry taken at or after that moment. Participants only: somebody buying a
  ticket on the day is the normal way anyone watches sport. Both columns are
  required together to mean anything.
- **`event_registration_fee_lines`** — the important part. One row per thing
  charged, written when the entry is taken, `label` and `amount` COPIED rather
  than looked up. `finance()` now SUMS these instead of `paid_count × today's
  fee`, which was wrong before multi-pricing existed (an organiser correcting a
  price silently restated every entry ever taken) and is inexpressible with it.
  Entries predating the table have no lines and still fall back to the base fee —
  reporting them as free would be a worse answer than the one the platform gave
  at the time.
- **Options are deactivated, never deleted** (`is_active`), because a frozen line
  points back at one for provenance. Same rule `club_product_variants` follows.
- **`event_public_entries.fee_options`** — a JSON list of UUIDs held on a pending
  public request, so an entrant an organiser reviews next week is billed for
  exactly what an auto-accepted one is. UUIDs, never amounts: acceptance
  re-prices from the event's own rows, so an option retired in the meantime
  simply drops out.
- **Security:** every door takes option UUIDs and nothing else. `EventFee::quote()`
  is the single place that resolves them, against that event's own active rows —
  a forged, retired, duplicated or wrong-role key is dropped silently rather than
  refused, so a stale form left open overnight never becomes an error page
  between somebody and entering.
- Covered doors: self-entry (`register`), spectator `ticket`, coach squad entry
  (`EntryService::enterMany`, options keyed per athlete), claim issue
  (`EntryClaim::issue`), the public link (`PublicEntry::enrol`/`settle`/`accept`),
  the personal create/edit form, and the club-admin event form (participants
  only — that form still has no spectator pricing at all, unchanged).

**Phase 3 — Invoices**
- `event_entry_invoices` table + model; squad entry creates/extends, later
  additions issue an increment.
- Self-entry gets a one-line personal invoice, replacing the current
  per-registration proof flow.
- Approve → `ClubTransaction` posted to the host club's ledger.
- `registrations.paid` derived from the invoice.

**Phase 4 — Refunds**
- `date_anchor` at settlement; 14-day window in config + per-event override; TBD detection.
- Date change / cancel / archive re-evaluates eligibility, fans out over MQTT.
- Request-refund action; organiser processes manually with proof.

**Phase 5 — sync pass** (per the build-fast-sync-later preference)
- MCP tool coverage, tests, update `Documentation/EVENTS.md`.

---

## Adjacent findings (not part of this work, worth knowing)

- **`Sports/Karate/Tournament` is a byte-for-byte clone of
  `Sports/Taekwondo/Tournament`.** Diffing all seven classes with the sport names
  normalised away gives **zero** difference lines (~2,020 duplicated lines);
  Karate is already missing `CompetitorPhoto.php`. Adding a third sport this way
  triples it. The fix is a shared combat-tournament base where a sport contributes
  only what genuinely differs. **This entry/billing work is unaffected** — it all
  lives in `Support/` and core columns.
- **`bronzeRule()` accepts `'repechage'` but no repechage is implemented.**
  `Advancement.php:139` treats it identically to `both_sf_losers`. Real repechage
  generates extra bouts that must be drawn and scheduled — the config currently
  promises a convention the engine cannot run.
- Only one competition format exists: individual, knockout, weight-classed
  sparring. No kata/poomsae panel scoring, no pools/round-robin, no team events,
  no belt-graded divisions (despite `BeltRank` existing), no belt test.
