# Public entry & the claim link — design spec

**Status:** Phases A, B and C BUILT (2026-09-01); D and E outstanding. Written 2026-09-01. Companion to
`EVENTS-ENTRY-BILLING.md` (Phases 1–2 built, Phase 3 next) — this spec adds the
**two doors that reach people who are not on TAKEONE yet**, and reuses that
document's channels, permission and fee model unchanged.

Scope: how a stranger who follows a shared link ends up entered in a
competition, and how a club owner enters athletes whose details he does not
have. One mechanism serves both.

---

## The problem, in the owner's words

Two situations, opposite directions:

1. **An organiser shares the event publicly.** People who have never heard of
   TAKEONE open the link, read the details, and decide to compete. They must be
   able to enter — which means the platform has to collect what a competition
   needs (name, birthdate, weight, belt/rank) from someone with no account.
   Registration to TAKEONE is a **side effect**; enrolment in the tournament is
   the thing they came for.

2. **The organiser restricts entry to club owners.** Only a coach may enter his
   own athletes. But a coach does **not know** his athletes' weights and
   birthdates to hand, and asking him for them produces *invented* data, not
   data. That is worse than a blank: it drives age groups, weight categories and
   the minor safeguards, and nobody ever revisits it (see CLAUDE.md, *Who Fills
   The Form Decides What It Demands*).

Both are answered by separating **who commits the entry** from **who supplies
the athlete's details**. Those have always been assumed to be the same person.
They are not.

---

## The core idea — an entry can be INCOMPLETE, and it carries its own claim link

An entry is created the moment somebody with authority commits to it. It does
not need to be complete to exist. What it needs is a way for the person who
*does* know the answers to fill them in, before the draw.

```
        commits the entry                supplies the details
        ─────────────────                ────────────────────
  A     coach picks an existing member   already on file            → complete instantly
  B     coach types a name only          the athlete, via claim link → complete before deadline
  C     stranger follows public link     themselves, in one sitting  → complete instantly
```

Three doors, one entry record, one gate (`EnrolmentDecision`), one roster.

---

## Decisions to confirm

These are the open calls from the discussion; the rest of the spec assumes the
answer in **bold**.

1. **Is public entry per-event opt-in?** → **Yes.** A new `entry_mode` on
   `club_events`: `club_only` | `members` | `public`. Default `members` (today's
   behaviour). An organiser opts a specific event into `public`; nothing about
   existing events changes. This is the feature flag, defaulted off, that RULE #1
   asks for.
2. **Does the organiser approve public entries?** → **Yes, pending by default.**
   A public link means anyone can enter. Public-door entries land as
   `pending_review` and the organiser confirms. Per-event override to
   auto-accept for an open competition that wants no gatekeeping.
3. **Real account or claimable stub?** → **Real account, email verification
   required to KEEP the place, not to take it.** The entry stands as
   `pending_review` immediately; the athlete's verification is one line on the
   organiser's checklist. An unverified entry cannot be seeded into a draw.
4. **Money.** No gateway (project policy). A public event with a fee shows the
   fee and the entry settles by the existing proof-upload path once the account
   exists. Phase 3's invoices apply unchanged — a public entrant is simply an
   `individual` channel payer.

---

## Door A — coach picks an existing member

Already built (`EntryService::roster()` / `enterMany()`). Nothing changes.
Weight, birthdate and rank come off the member's profile; a missing weight
already defers to the weigh-in desk via `EnrolmentDecision::defer()`.

---

## Door B — coach names someone he does not have

### What the coach does

In the squad-entry sheet (`partials/event-squad-entry`), beside the roster
search, an **"Enter someone not listed"** action asking for **a full name and
nothing else**. Optionally an email or phone, because if he has one the claim
link can be sent for him.

`PersonFieldRules` already encodes exactly this: a person entering someone else
is asked for a name; birthdate is never required of anyone. The coach is,
by definition, `entersPeopleOnBehalfOfOthers()`.

### What the platform creates

- A `User` row for the athlete — **unclaimed**: no password, `is_discoverable`
  false, not verified, name only. It is a real person record because the roster,
  the draw and the results all need one; it is not an account until somebody
  claims it.
- A `ClubEventRegistration` on the **club** channel, `entered_by` = the coach,
  `representing_tenant_id` = his club, `entry_state = incomplete`.
- An **entry claim** row (see below) with a high-entropy token.

The entry counts toward the club's invoice from the moment it is committed —
the coach commits the money, not the athlete (Phase 3, "one bill per payer").

### The claim link

New table **`event_entry_claims`**:

| column | meaning |
|---|---|
| `uuid` | public key; the URL is `/enter/{uuid}` |
| `token_hash` | hash of the secret half; the URL carries `uuid` + secret, the DB never stores the secret |
| `registration_id` | the entry it completes |
| `user_id` | the unclaimed person it will become |
| `created_by` | the coach |
| `expires_at` | the event's entry deadline, or 14 days, whichever is sooner |
| `claimed_at`, `claimed_ip` | audit |
| `revoked_at` | coach withdrew the athlete |

- One claim per entry. Regenerating invalidates the previous token.
- The coach gets a **copy link**, a **WhatsApp share**, and a **printable QR
  sheet** (`<x-qr-code>` with a poster route, exactly as club/member QRs work).
  If he supplied an email or phone, one message is sent for him.
- **A claim link is a credential.** It is not enumerable, it expires, it is
  single-use, its rate limit is strict, and it is scoped to *one* entry — it can
  never be used to reach another athlete, another event or anything else on the
  platform.

### What the athlete sees

`/enter/{uuid}?t=…` opens, unauthenticated, on a page that says plainly:
**"<Coach> has entered you in <Event>. Complete your details to confirm your
place."** — the event's identity band, the division rules, the deadline, and a
short form: birthdate, gender, weight, belt/rank, photo (optional), and the
credentials that turn the stub into an account (email + password, or "I already
have a TAKEONE account" → sign in and **merge into the existing person** rather
than creating a duplicate).

On submit: the stub becomes a real account, the entry re-runs
`EventType::enrolmentGate()` and is classified into a division, `entry_state`
becomes `complete`, and the coach is notified over MQTT.

**A guardian may complete it for a minor.** If the birthdate given makes the
athlete a minor, the form asks for the guardian's details instead and creates
the guardian relationship (`UserRelationship`) — the same shape the family flows
already use. A minor never gets a bare account of their own through this door.

### If nobody claims it

- The console shows **"17 entered · 5 incomplete"** with the names.
- An incomplete entry **cannot be seeded into a draw** — `DrawEngine` only ever
  sees classified entries, so this enforces itself and cannot corrupt a bracket.
- Reminders go to the coach at the deadline minus 3 days and minus 1 day.
- **After the deadline the coach may fill the gaps himself** — a fallback, never
  the default path. This is the one place he is asked for data he may not have,
  and by then the alternative is scratching the athlete.

---

## Door C — the public link

### The page

New public route **`GET /e/{uuid}`** (`events.public`), no auth, throttled.
Renders only when `entry_mode = public` **and** the event is not archived;
anything else 404s with the same response either way (anti-enumeration).

It shows what a poster shows: title, host club, dates, venue, sport and event
type, divisions and their rules, entry fee and deadline, entrant count,
documents the organiser marked public, and the organiser's contact line. It
shows **no competitor names, no roster, no draw** — those stay behind
`EventAccess::visible()`.

A signed-in visitor is redirected to the normal `me.events.show`; this page
exists for people who have no account.

### The action

The button is **"Enrol"** on a `public` event, and **"Request entry"** on a
`club_only` one (see below). Enrol opens the same completion form as the claim
link, with the account fields plus the event's gate fields, and on submit:

- creates the account (unverified) and sends verification;
- creates a `ClubEventRegistration` on the **individual** channel,
  `representing_tenant_id` null (unattached) unless they name a club they will
  join, `entry_state = pending_review`;
- notifies the organiser, who accepts or declines from the console.

### Abuse surface — this is a public write endpoint

It is the only place on the platform where an unauthenticated stranger creates a
`User`. Non-negotiables:

- Strict per-IP and per-event throttles on both the page and the submit.
- Email uniqueness handled without disclosing whether an address exists — an
  existing address is told to sign in, in the same words either way.
- The organiser's accept step is the real gate; an entry is worth nothing until
  it is accepted.
- Nothing the stranger posts decides money, division or status server-side —
  the fee comes from `EventFee`, the division from the package's gate.
- Uploaded photos go through `StoresBase64Images` like every other upload.

---

## `club_only` — the restricted event

When `entry_mode = club_only`:

- `EntryService` refuses the individual channel entirely. Only holders of
  `enter-athletes` at a club may commit an entry — which is exactly the
  restriction the organiser asked for.
- The public page still exists if the organiser shared the link, but its button
  is **"Request entry"**: a stranger submits name + contact, and the request is
  **routed to the club they name** (to its `enter-athletes` holders), or to the
  organiser when they name no club.
- Accepting a request is Door B: it creates the entry and issues the claim link
  back to the person who asked — so they complete their own details anyway, and
  the coach never types a weight.

Requests live in a small **`event_entry_requests`** table (uuid, event, name,
contact, named club, state, decided_by, decided_at) and never touch
`club_event_registrations` until accepted.

---

## Scenarios — how it actually plays out

Nine walkthroughs. Together they cover every door, both restriction modes, and
the five ways it goes wrong.

### 1. The coach with a squad of eleven — eight known, three not

Bahrain Taekwondo Academy is entering the Gulf Open. Coach Ali opens the event,
taps **Enter squad**, searches his roster and picks eight members — their
birthdates, weights and belts are on file, so all eight land **complete** and
classified into divisions instantly.

Three of his athletes joined last month and were never fully profiled. He types
their names — *Yusuf Hasan*, *Maryam Ali*, *Omar Saleh* — one field each, and
nothing more. Three **incomplete** entries appear, each with a claim link. He
copies all three into the squad's WhatsApp group.

The club's invoice is for **eleven** athletes from this moment. Ali committed
the money; whether the three complete their details doesn't change what the club
owes.

By evening two have filled in their own weight, belt and birthdate. Ali's console
reads **"11 entered · 1 incomplete"**. He sends Omar one more nudge.

### 2. The stranger who saw the poster

The organiser shares `takeone.bh/e/9f3c…` on Instagram. Khalid, who has never
heard of TAKEONE, opens it on his phone. He sees the competition: date, venue,
host club, the weight divisions, the entry fee, the deadline, how many are
entered. No competitor names, no draw.

He taps **Enrol**. One form: name, email, password, birthdate, gender, weight,
belt. He submits. His account is created, a verification email goes out, and his
entry appears on the organiser's console as **pending review**, marked
*unattached* — he competes for no club.

The organiser accepts it the next morning. Khalid clicks the verification link,
and from that point he is an ordinary TAKEONE member who happens to have arrived
through a competition. He now has a profile, a bout history, and a reason to come
back — which is the commercial point of the whole feature.

### 3. The federation event: club owners only

The Bahrain Karate Federation runs the National Championship and will accept
entries **only from affiliated clubs**. The organiser sets `entry_mode =
club_only`.

Sara, who trains at an affiliated club, finds the public link anyway. Her button
does not say Enrol — it says **Request entry**. She submits her name, her phone,
and names her club.

The request lands with her club's `enter-athletes` holders. Her coach opens it,
recognises her, and accepts. That accept **is Door B**: it creates a club-channel
entry representing his club, and issues Sara a claim link. She fills in her own
weight and belt.

Her coach never typed a number he didn't know, the federation's restriction held,
and Sara got in.

### 4. The eleven-year-old

Coach Ali's fourth name is *Layla Ahmed*, aged eleven. He types her name; the
claim link goes to her mother.

Her mother opens it. She enters Layla's birthdate — and because that makes Layla
a minor, the form changes: it now asks for **the guardian's** details and
credentials, not Layla's. The account created belongs to the mother, with Layla
linked as her dependent (`UserRelationship`), exactly as the family flows already
work. Layla never gets a bare login of her own through this door.

Layla is classified into the under-12 division from a birthdate her mother gave —
not one her coach guessed.

### 5. The athlete who already has an account

Coach Ali types *Fatima Noor*. She already trains at another club and has been on
TAKEONE for two years.

She opens the claim link and taps **"I already have a TAKEONE account."** She
signs in, and the claim **merges into her existing person** — her real profile,
her real bout history, her real belt rank — instead of creating a duplicate. The
entry now points at her actual account and is complete in one tap, because
everything the gate wanted was already on file.

Merging is the reason the claim is bound to one entry and one athlete: it is the
only way to let a stranger's link touch an existing account safely.

### 6. Nobody claims it

Omar never opens his link. The deadline minus three days, and again minus one,
the platform reminds Ali — not Omar, because Ali is the one who can act.

On the last day Ali gives up chasing and fills Omar's weight and birthdate
himself. This is the **fallback**, and the only moment the coach is asked for
data he may not have — by then the alternative is scratching the athlete, so a
best guess he can correct at the weigh-in beats an empty slot.

If he does nothing, Omar's entry stays incomplete, never enters the draw, and the
bracket is built from ten. Nothing is corrupted; the competition just runs
without him.

### 7. The link that leaks

Someone forwards Yusuf's claim link into a public group. A stranger opens it.

They see one thing: *"Coach Ali has entered you in the Gulf Open"* and a form.
They cannot see Ali's roster, the other entrants, the draw, the club's members,
or any other event. The token is bound to **one entry**, expires at the deadline,
dies on first use, and is rate-limited.

The worst case is that a stranger completes Yusuf's entry with wrong numbers.
Yusuf stands on the scale at the weigh-in, the desk records his real weight, and
`classifyEntry()` moves him. Recoverable — and the reason the weigh-in stays the
authority on weight regardless of what any form said.

### 8. The flood

A bot finds the public link and fires two hundred entries in a minute.

Per-IP and per-event throttles cut it off. Everything it did create sits in
**pending review** and is worth nothing until the organiser accepts — so the
worst outcome is a list the organiser clears in one gesture, not a corrupted
competition. No account it created is verified, none can be drawn, and none
appears in search or discovery (`is_unclaimed` / unverified are excluded).

The organiser's accept step is not paperwork. It is the actual gate.

### 9. The walk-up on the day

A competitor arrives at the venue having never entered anything. The desk has the
event's QR on a printed sheet.

He scans it, lands on the same public page on his own phone, enrols, and the
organiser — standing right there with the console open — accepts it. He is in the
system before he reaches the scale, and nobody at the desk typed his details for
him.

If entries are already closed, `entriesState()` says so plainly on the public
page and there is no Enrol button to press — the same rule that already governs
the member-facing join button.

---

## Data changes, all additive

`club_events`
- `entry_mode` varchar(16) default `members` — `club_only` | `members` | `public`
- `public_entry_auto_accept` bool default false
- `entry_deadline` date, nullable (today's close rule is derived from the start
  date via `entriesState()`; this lets an organiser state a real deadline, and
  `entriesState()` gains one branch)

`club_event_registrations`
- `entry_state` varchar(16) default `complete` — `complete` | `incomplete` |
  `pending_review` | `declined`. Backfill every existing row to `complete`.

`users`
- `is_unclaimed` bool default false — a person record created on someone's
  behalf that nobody has signed into yet. Excluded from search, discovery,
  messaging and every listing that means "accounts".

New: `event_entry_claims`, `event_entry_requests`.

**Nothing existing changes shape.** `entriesState()`, `RosterPeople`,
`DrawEngine`, the scoreboard and the invoices of Phase 3 all read the same
columns they read today; the new states are additional filters in front of them.

---

## Where the code goes

All of it is cross-sport and lives in `app/Events/Support/` — none of it belongs
to a package (*Shared Stays Shared*):

- `Support/EntryClaim.php` — issue, verify, consume, revoke a claim token.
- `Support/EntryRequest.php` — the `club_only` request queue.
- `Support/PublicEvent.php` — the exact, minimal payload the public page may
  show. One place, so a field can never leak by being added to a view.
- `Http/PublicEntryController.php` — the three unauthenticated routes.
- `EntryService` gains `enterUnnamed()` (Door B) and `enterPublic()` (Door C);
  the gate, classification, fee and notification paths it already owns are
  reused untouched.

The **completion form is one component** used by both doors and by the
guardian variant — mobile and desktop, hero band, bottom sheet, the usual rules.
A package contributes only its own gate fields (weight, rank), via the existing
`EventType` contract.

---

## Build order

Each phase ships alone and leaves the app working.

**Phase A — incomplete entries + the claim link** *(the coach's problem, and the
harder half)*
`entry_state`, `is_unclaimed`, `event_entry_claims`; "Enter someone not listed";
the claim page and completion form; the console's completeness checklist; draw
seeding excludes incomplete. **No public surface at all** — everything still
behind auth, so nothing new is exposed.

**Phase A — BUILT 2026-09-01.** Incomplete entries and the claim link, end to
end, both sides.

- Migrations, all additive: `club_event_registrations.entry_state`
  (`complete` default — every existing row is exactly what it was),
  `users.is_unclaimed`, and `event_entry_claims`.
- **`App\Events\Support\EntryClaim`** owns every rule: `issue()` (a name
  commits a real entry and mints the link), `pending()`, `revoke()`,
  `regenerate()`, `resolve()` and `complete()`. Cross-sport — it asks the
  event's own package for the gate and the classification.
- **The coach:** a third tab, *By name*, in the squad sheet
  (`partials/event-squad-entry` + `partials/event-show-script`). One field. The
  minted link is shown ONCE with copy / WhatsApp / share, and below it the
  "waiting on them" checklist with relink and withdraw.
  Endpoints: `me.events.entries.unnamed`, `me.events.entry-links`, and that
  link's `.revoke` / `.relink`.
- **The athlete:** `GET|POST /enter/{claim}` (`entry.claim`,
  `entry.claim.store`) — OPEN, the one unauthenticated write this feature adds.
  `entry/mobile/claim.blade.php` is the real page now (the mockup is gone);
  `entry/mobile/claim-dead.blade.php` is the single answer to every kind of
  dead link.
- **A minor's account belongs to their guardian** — the credentials create the
  guardian, `UserRelationship` links them, and the athlete keeps no login of
  their own. The guardian's name is then required, checked on both sides.
- Birthdate and weight are still never demanded; a blank sends them to the
  weigh-in desk, and the entry simply carries no division until it is settled.
- **Deferred to the sync pass:** MCP tool coverage and feature tests. The
  architecture tripwires were updated (six routes, on purpose).

**Phase B — BUILT 2026-09-01.** The event page anybody may open, read-only.

- `club_events.entry_mode` (`members` default — every existing event is exactly
  what it was). Opt-in per event: the organiser publishes ONE competition, from
  the console's **Public page** card. `<x-event-public-link>` owns the switch,
  the link, WhatsApp/share, a QR poster and a plain statement of what a stranger
  will and will not see; it is in both consoles, mobile and desktop.
  `me.events.public.toggle` is the only writer, organiser-only.
- **`App\Events\Support\PublicEvent`** is the one place that decides what may
  be said: a poster's worth of facts, an entrant COUNT and never a name, no
  financials, no contacts, no internal ids. A view cannot reach past it.
- `GET /e/{uuid}` (`events.public`) — the entire new surface is a GET. Device
  split, `entry/public/{mobile,desktop}.blade.php` over the shared standalone
  shell `entry/layout.blade.php`, which also carries the Open Graph tags,
  because the link is going into WhatsApp and Instagram and the card it unfurls
  into is part of the design. A signed-in viewer who can already see the event
  is redirected to the real page.
- **`FileAccess` now serves a public event's poster to an anonymous visitor** —
  and only that: the exact file named in a public event's `images`, on an event
  in public mode. Verified all four ways (public poster 200, an unrelated club
  event image refused, the same poster refused the moment the page is switched
  off, and no entrant name on the page).
- An event not in public mode 404s exactly as an unknown uuid does, so the
  address cannot be used to discover which events exist.
- The entry action is deliberately **not** a button yet — the page states how
  entry works and Phase C turns it into Enrol. A button that goes nowhere is
  worse than none (Navigation Integrity).
- **Deferred to the sync pass:** MCP coverage and tests, as for Phase A.

**Phase C — BUILT 2026-09-01.** Public enrolment, end to end.

- **The request is not an entry.** The spec above sketched a `pending_review`
  row in `club_event_registrations`; it is a table of its own instead —
  `event_public_entries` — and the change is deliberate. That registrations
  table IS the entry list: thirty-nine places across the platform read it as
  "who is competing" (roster, draw, entrant count, mats, invoices) and none of
  them filter on a state that did not exist before today. Putting an unreviewed
  stranger in it means every one of those is one forgotten `where` away from
  seeding a bot into a bracket. So a request waits elsewhere and BECOMES a
  registration on accept. Consequence, and it is the right one: a pending
  request counts toward nothing — not the entrant total, not capacity, not the
  money.
- **`App\Events\Support\PublicEntry`** owns every rule: `state()` (is the door
  open, asked by the button and the endpoint alike so they cannot disagree),
  `enrol()`, `pending()`, `accept()`, `decline()`. Cross-sport — it asks the
  event's own package to classify and never knows which sport it is serving.
- **The stranger:** `GET|POST /e/{uuid}/enter` (`events.public.enter`,
  `.store`) — the one place on the platform where a wholly unauthenticated
  visitor creates a `User`. Throttled 30/min on the page and **5/min** on the
  submit, per IP. `entry/public/enrol.blade.php` is a four-step flow over the
  shared standalone shell: name → weight and belt → the account, LAST, after
  they have already decided to compete. An event not in public mode 404s here
  exactly as it does on the poster.
- **Nothing typed decides anything.** The fee comes from `EventFee`, the
  division from the package's `classifyEntry()`, the state from the service. A
  birthdate and a weight are still never demanded of anyone; a blank weight
  sends them to the weigh-in desk, which outranks the form regardless.
- **The organiser's accept IS the gate**, not paperwork after one.
  `<x-event-entry-review>` is the queue — in both consoles, mobile and desktop,
  rendering nothing when nobody is waiting — with accept / decline per row and
  the one signal that separates a person from a script beside each: whether the
  address is verified. It re-fetches on `realtime:events` rather than trusting a
  pushed payload, because what each viewer may see differs.
  Endpoints: `me.events.public-entries` and that queue's `.accept` / `.decline`,
  organiser-only, re-checked in the service on every call.
- **Auto-accept is a second switch, not the same one.**
  `club_events.public_entry_auto_accept`, default false, set from
  `me.events.public.auto-accept`. Publishing a page and opening an unreviewed
  door are two decisions, and the second must never happen as a side effect of
  the first. The auto path still runs the same accept code, so an auto-accepted
  entry is indistinguishable from a reviewed one afterwards.
- **A declined request keeps its account.** They made it; deleting somebody's
  account because one organiser said no is not ours to do. They are simply not
  in this competition, and they are told so.
- Verification is sent on enrolment and is what KEEPS the place, not what takes
  it. A duplicate address is answered with one sentence — sign in — the same
  sentence either way, and no account is touched.
- **Deferred to the sync pass:** MCP coverage and feature tests, as for Phases A
  and B. Verified instead by a scratch smoke run (23 assertions): the closed
  doors 404 indistinguishably, the open ones render, a pending request is not an
  entrant, an outsider can neither see nor decide the queue, a decision cannot
  be taken twice, auto-accept enters immediately, and no entrant name or address
  reaches the public page. Both consoles render with the queue mounted.
  Architecture tripwires updated: 663 routes, 651 named.

### The public page IS the member page, minus the app — 2026-09-01

The first cut of the public page was its own design — a dark poster. Wrong: a
competition should look the same whoever opens it, and the organiser had to
maintain two ideas of what their event looks like. It is now the SAME page as
`/me/events/{uuid}`, with the app taken away.

- **The detail card is literally shared.** `partials/event-detail-card-mobile`
  and `-desktop` were extracted verbatim from the two member views and are now
  `@include`d by both the member page and the public one. About, run-of-show,
  divisions, requirements, documents and venue are all poster-grade, so the
  public page gets the real thing rather than a lookalike that drifts. Verified
  by rendering both member views before and after the extraction: identical HTML
  apart from the CSRF token and the QR component's random id.
- **`PublicEvent::payload()` now speaks the member payload's key vocabulary**
  (`key`, `wday`/`day`/`mon`, `participant_fee`, `capped`/`going`/`cap`,
  `phases`, `date_iso`, …) — necessarily, since both render from one set of
  partials. It is still the gate: the keys it refuses to produce are the ones
  that would name a competitor (`participants`, `results`, `bans_list`,
  `joined`, `fee_due`), and `id` is a slice of the uuid rather than the
  auto-increment id, so no internal id reaches the DOM.
- **`entry/layout.blade.php` is the app's page environment with the app removed**
  — the same `app.css`, the same tokens, the same `mobile-stagger px-4 py-4`
  wrapper as the shell's `<main>` — and no top bar, drawer, bottom tabs or
  footer bar.
- **Hero band per Design Rule #6**, with no back pill: this is a top-level
  destination reached from a link, and a back control that leads nowhere is
  worse than none. The quick-facts chips still jump to their sections; the page
  defines the two helpers (`jump`, `share`) the shared markup expects, because
  the shell's helpers are not loaded here.
- **The enrolment flow was restyled to match** — app cards, app tokens, the same
  hero band, a white action bar — since landing on a differently-styled page one
  tap after the poster is exactly the seam this surface exists to remove.
- Verified: both public views and the enrol flow render 200 with the hero band,
  the shared section bands, the app stylesheet, no shell markup and no TAKEONE
  anywhere; the shared card's output is byte-identical to the member page's up
  to the point where each page's own next section begins. Suites green (451).

### The link is the event's own app, not a page on ours — 2026-09-01

Added after Phase C, and it applies to **every** page a stranger sees (the
poster, the enrolment flow, the claim link and its dead-link page).

A public link is opened from an Instagram story or a WhatsApp message by
somebody who does not know what TAKEONE is and has no reason to care. What they
were sent is THE GULF OPEN. A platform logo in the footer tells them they landed
on somebody else's website, which is the one thing a shared link must never do.
So the standalone shell is **white-labelled**: no TAKEONE mark anywhere on it.

- **`App\Events\Support\PublicBrand`** is the single place deciding what the
  shell is branded AS — the event, backed by its host club. The manifest, the
  icons, the head tags and the footer all ask it, so they cannot drift.
- **Installable as the event.** `GET /e/{uuid}/app.webmanifest` gives the
  competition's own name, its own start URL, `display: standalone` and the
  event's colour; `GET /e/{uuid}/icon-{180|192|512}.png` draws the CLUB's logo,
  contained never cropped, on a tile of the event's colour (GD, cached per
  event/size, version-busted by the logo). A transparent logo of arbitrary shape
  is not installable, which is why the icon is generated rather than linked.
  Both doors 404 for an unpublished event, exactly like the page.
- **The tab, the home screen and the share card** carry the event and the club:
  `apple-touch-icon`, `apple-mobile-web-app-title`, `application-name`, and
  `og:site_name` = the host club, never the platform.
- **The footer names the ORGANISER** — the club's logo, "Organised by", the club
  — where the TAKEONE logo used to be.
- **The claim pages too:** the club's mark in the top row and on the tab. The
  dead-link page carries no mark at all, deliberately: it is shown for a link
  that resolved to nothing, so naming the club would confirm which links are
  real.
- Strings a stranger reads no longer name the platform ("I already have an
  account"). The organiser's own console still says TAKEONE, because an
  organiser IS a TAKEONE user and vagueness there would be worse.
- Verified: 17-assertion smoke run — manifest and all three icon sizes are real
  PNGs at the declared dimensions, an unlisted size 404s, both brand doors close
  with the page, and the word TAKEONE appears nowhere in the rendered poster,
  enrolment flow, claim page or dead-link page. Routes 665 / 653 named.

**Phase C — as originally specified** *(kept for the record)*
The Enrol action, account creation, `pending_review`, the organiser's
accept/decline, throttles, verification handling.

**Phase D — `club_only` + entry requests**
The restriction, the Request-entry button, `event_entry_requests`, routing to
the named club, accept → Door B.

**Phase E — sync pass** (per build-fast-sync-later)
MCP tools (`list_entry_claims`, `accept_entry_request`), tests — including the
Part 3 requirement that one claim token can never reach another entry — and
`Documentation/EVENTS.md`.

Phases A and B are independent of Phase 3 (invoices) and can land before or
after it. Phase C touches money only through the existing `EventFee`.

---

## What this deliberately does not do

- **No payment gateway.** A public entrant pays by the same proof-upload path.
- **No open registration to TAKEONE from an event.** The account exists because
  a competition needed a person; it is not a marketing funnel with a competition
  attached.
- **No public roster or draw.** Competitor names stay behind `EventAccess`.
  Publishing a draw publicly is its own decision, not a side effect of this.
- **No club-side auto-membership.** Completing a claim link does not enrol the
  athlete in the coach's club — it records who they compete FOR
  (`representing_tenant_id`), which is already the right column.
