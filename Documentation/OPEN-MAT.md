# Open Mat — the scoreboard for a fight nobody planned

Two people decide to go. One of them opens `/openmat` on a phone, puts a name in each corner,
and taps **Start**. There is no entry list, no draw, no queue, no division, no fee and nothing
to sign up for — and the bout is nevertheless scored on the same table, shown on the same wall
screens and introduced with the same VS card as a national championship, because underneath it
is the same machinery.

Then the next two step on, and the operator sets them **from the scoreboard they are already
looking at**. That last part is not a convenience; it is the difference between a feature that
gets used at a real open mat and one that does not.

Package: **`app/Events/OpenMat/`**. Event type key: **`open_mat`**.
Sports: **Karate** and **Taekwondo** — the two with a mat scoreboard, which is the whole feature.

---

## The whole product in four taps

```
/openmat  →  tap red  →  tap blue  →  Start  →  … and never leave the scoreboard again
```

After that first bout the operator stays on the scoring table: the **NEXT PAIR** panel (below)
sets every subsequent pair from the mat's own floor without a single page change.

`/openmat` is **not a launcher**. It resolves everything it can by itself and redirects straight
onto the mat's two corner cards, because every question asked before those cards appear is a
question asked at the worst possible moment — somebody is standing on the mat waiting.

What it resolves without asking:

| | |
|---|---|
| **Club** | the opener's own (first alphabetically). Never asked, never shown as a choice. |
| **Sport** | whatever their last open mat used, else the first available. Changed on the mat in one tap. |
| **Mats** | one. A second is added from the console. |
| **Bout length** | three minutes. The sport's scoring table owns it from there. |

Hitting `/openmat` twice **resumes** the same mat rather than opening a second one — a second mat
would strand every wall screen already paired to the first.

---

## Two screens, and which one you live on

| | |
|---|---|
| **The mat console** — `/me/events/{uuid}/manage` | Where `/openmat` lands you. Set the first pair, add mats, switch sport, pair hall screens, read what has been fought, close the mat. **You visit it, you do not live on it.** |
| **The scoring table** — the sport's own console | Where you go once and stay. It scores the bout, and its **NEXT PAIR** panel sets every pair after the first. |

The whole shape of the feature is that second row. The first cut had only the mat console, so
every new pair meant a round trip; the panel is what removed it.

---

## Filling a corner — the three ways in

The same three ways exist in both places: the mat console's corner sheet, and the NEXT PAIR panel
on the scoring table. This is the feature; everything else is scaffolding around it.

### 1. Find a member
One box, matching **name, phone or email**, partial, across the whole platform — **including
yourself**. The person holding the console is usually one of the two fighters, so there is also a
one-tap **"That's me"** button above the search box and the searcher sorts first in their own
results.

Phone matching tries the digits as typed *and* the last 9/8/7 of them, so a number saved as
`+973 33165444` finds one stored as `33165444`.

> ⚠️ **`UserBlock::idsBlockedEitherWayWith()` pushes the CALLER onto its own list.** That is right
> for the people directory it was written for and fatal here — it made the searcher invisible to
> themselves by name, phone and email alike. `OpenMatSession::findablePool()` rejects the caller
> back out rather than changing a helper `PeopleController` depends on. Do not "simplify" it back.

### 2. Type a name
A guest: somebody who is not on TAKEONE at all. Nothing is created for them — no user, no
invitation, no record. The name is stored as text on the bout and printed on the wall.

**This is not a shortcut, it is the point.** Requiring an account to be scored is what keeps a
scoreboard inside one club; not requiring one is what lets it travel.

### 3. Let them join
The mat prints six characters and a QR. The opponent opens it on their own phone
(`/openmat/join/{CODE}`) and takes a corner themselves.

This is the consent path, and the only route that reaches a member from another club, another
country, or an account nobody can search. They must be signed in: taking a corner puts a name and
a face on a wall screen and files a bout on a record, so an anonymous join would be a way of
standing in as somebody else.

---

## Start — and the handoff to the sport

Nothing in this package **scores anything**. Start does two things and then gets out of the way:

1. **`start_bout`** creates the `EventMatch` on the spot, from the two corners. This is the whole
   difference from every other event in the product: nothing was entered in advance and there is
   no queue to draw from — the bout comes into existence at the moment somebody taps the button.
2. The bout is handed to the **sport's own** scoring engine as a `load` command.

Step 2 is deliberate, and it is what keeps this package out of the sport's code. It goes through
the sport's real command path rather than reaching into its `Scoring` class, so it gets the same
validation, the same MQTT screen pushes and the same authority as an official at a championship
pressing the same button.

The two Starts differ only in where they are standing:

| | |
|---|---|
| **Mat console** | POSTs `load` to `{sport}-scoreboard.command`, then **navigates** to the control page. This is the one trip you make. |
| **NEXT PAIR panel** | calls the console's own `window.MatConsole.send('load', …)`. **No navigation** — you are already there. |

When the bout is committed, `Scoring::commit` hands the result back through the registry to
`OpenMat::recordOutcome()`. Corners stay standing afterwards, ready for a rematch, and both
fighters' `bouts` counters have already moved.

---

## NEXT PAIR — the panel on the scoreboard

**This is the part that makes it usable.** Every other event type knows its bouts in advance, so
its operator taps them off a running order and never leaves the scoring table. An open mat has no
running order by definition — and the first cut made the operator navigate back to a console,
re-find two people and come back, **for every pair, all evening**.

So the mat brings its **floor** to the table. A `NEXT PAIR` button sits in the console's control
row and opens one more of its modals:

```
┌─ NEXT PAIR ──────────────────────────────────────┐
│  [ Marco        ]   VS   [ Yuki         ]        │  ← the pair being built
│            [ START THIS PAIR ]                    │
├─ ON THE MAT ────────────────  join code: 47J3AK ─┤
│  Ghassan   0 so far   5–2     [RED] [BLUE] [rm]  │
│  Marco     1 so far           [RED] [BLUE] [rm]  │  ← sorted by who
│  Yuki      2 so far           [RED] [BLUE] [rm]  │     has fought LEAST
├──────────────────────────────────────────┤
│  [ search name / phone / email ]  [ Add myself ] │  ← someone new walks up
│  [ or type a name             ]  [ Add guest  ] │
└──────────────────────────────────────────┘
```

Two taps set the corners, one starts the bout, and **the page never changes**. Start posts
`start_bout` and then hands the new bout to the console's own `send('load', …)`, so the mat is
introduced and scored without a navigation.

`START THIS PAIR` refuses while a bout is already on the mat — the operator finishes or clears it
first, exactly as they would between two draw bouts.

### The hook it rides on

`AbstractEventType::matPanel($event, $court, $viewer): ?array` — **null by default**, which is
what every championship returns, and null renders nothing at all.

```php
['view' => 'event-<key>::mat-panel', 'label' => 'NEXT PAIR', 'data' => [...]]
```

Three guarded additions per console — **Karate and Taekwondo both have them** — and nothing else:

1. `@isset($matPanel)` around one button in the control row
2. `@isset($matPanel) @include($matPanel['view'], $matPanel['data']) @endisset` beside the other dialogs
3. `window.MatConsole = { send, alert, mat, state }` at the init tail — the one seam a contributed
   panel talks through, so it never reimplements how to load a bout or raise a message. Two ways
   of talking to one mat is how two consoles come to disagree.

Taekwondo has a fourth, one line: its console auto-opens the **queue** on arrival at a clear mat,
which for an open mat would open an empty dialog over the one control that can fill the mat. It
now opens the contributed panel instead *when there is one* — `(btnPanel ? el('omPanel') :
el('queueModal')).hidden = false` — so a championship's behaviour is unchanged.

### The panel styles itself

It borrows **nothing** from the console around it. That is not tidiness: the two hosts are
different documents with different vocabularies — Karate says `modal` / `mhead` / `mtitle` /
`mclose` / `cbtn`, Taekwondo says `sheet` / `sheet-head` / `sheet-title` / `sheet-close` / `btn`,
and their CSS custom properties differ too (Taekwondo's are `oklch`). A panel written against
either is broken in the other. So it ships its own `.om-*` styles and its own open/close/Escape
handling, and the only thing each console supplies is **the button that opens it**, which lives in
that console's control row and rightly wears that console's own class.

**Rendered only for a signed-in operator.** A scoring table paired by *device token* has no user
behind it and the panel's writes are member-authorised endpoints, so rather than open a
token-authorised way to put arbitrary names on a mat, that door gets no panel
(`ScoreboardController::packagePanel()` returns null with no `Auth::user()`).

It is plain vanilla JS in the dark broadcast idiom. **There is no Alpine, no jQuery, no Tailwind
and no design system on those documents** — design-system classes resolve to nothing there, so do
not bring them in.

---

## The floor

`open_mat_people` — everybody who is here. Somebody put in a corner **joins the floor and stays on
it**, so the next pair is two taps from a list that is already there.

- Sorted by **who has fought least**, because that is the decision the operator is actually making.
- `bouts` is bumped when a bout **starts**, not when it is filed: somebody who stepped on has had
  their turn whether or not the result was ever committed.
- A member is on the floor once (`unique(event_id, user_id)`). Guests are not constrained — two
  people called Marco is a real thing at an open mat.
- Somebody standing in a corner cannot be removed; clear the corner first.
- `open_mat_corners.person_id` points here. A corner is a **position**; the person moves between
  corners and mats all evening.

---

## Why it is a `ClubEvent`

Everything that makes a mat work — `MatState`, the screen pairing tokens, the MQTT channels, the
VS introduction, the scoring table — is keyed by **an event and a court**. Making that
polymorphic for a session that lasts an afternoon would mean rewriting all of it, and the only
thing gained would be a tidier diagram.

So an open mat **is** a `ClubEvent`: `event_type = 'open_mat'`, `scope = internal`, started the
moment it is opened, archived when it is closed. The hall then works with no new plumbing.

**It hangs on a club** because `club_events.tenant_id` is `NOT NULL` and every event in the
product has a host. That is the schema's business rather than the user's, so it is never asked
about — but a member who belongs to no club has nothing to hang a mat on, and gets the single
`no-club` screen instead of a redirect. Making mats truly club-less means altering that column on
a live core table; it has not been done.

---

## Files

```
app/Events/OpenMat/
├── OpenMat.php               the EventType: actions, results, screens, view data
├── OpenMatSession.php        the domain: open/resume/close, corners, join codes, start, file
├── OpenMatController.php     the front door + the reads that are not actions
├── OpenMatPerson.php         one person on the floor — the list the panel pairs from
├── OpenMatCorner.php         who is standing in one corner of one mat
├── OpenMatResult.php         the casual head-to-head record (read model)
└── resources/
    ├── lang/{en,ar}/messages.php     → event-open_mat::messages.…
    └── views/                        → event-open_mat::…
        ├── mat-panel.blade.php       NEXT PAIR — injected into the SPORT'S scoring console.
        │                             Vanilla JS, self-styled (.om-*), borrows nothing from
        │                             the host document. Drops into either console unchanged.
        ├── no-club.blade.php         the one screen /openmat can render instead of redirecting
        ├── join.blade.php            "take a corner", for the person who scanned
        └── manage/
            ├── mobile.blade.php      the console — phone
            ├── desktop.blade.php     the console — laptop at the mat side
            ├── fill-sheet.blade.php  the three ways to fill a corner (shared)
            └── runtime.blade.php     the Alpine component (shared, inline, guarded)

database/migrations/2026_08_25_120000_create_open_mat_tables.php
database/migrations/2026_08_25_160000_create_open_mat_people_table.php
```

Registered by one line in `config/event_types.php`. The console is reached through the shared
`me.events.manage` route because `views()` declares `manage`, exactly as Sparring does.

---

## Data

Three additive tables. **Nothing existing was altered** — no column on any shared table was
changed, dropped or repurposed.

**`open_mat_people`** — the floor (see *The floor* above). **`open_mat_corners`** — who is in
each corner right now, per event + court, pointing at a person on the floor.
`user_id` is nullable throughout: three kinds of person fill the same row (a searched member, a
member who scanned the code, a typed guest) and downstream nothing cares which — the board prints
`name`, and `user_id` decides only whether the bout lands on anybody's record. `source` is
`picked` / `joined` / `guest`. Corners **outlive a bout** on purpose.

**`open_mat_results`** — what was fought, denormalised for the one question a casual record asks:
*how have I done, and against whom*. The bout itself stays in `event_matches` like every other
bout in the product; this is the read model beside it, so a member's record is one indexed query
rather than a join across events, registrations and matches. A guest is a `NULL` user id and a
name, so the other side's record still reads "beat Marco" rather than "beat someone".

A member corner also gets a `club_event_registrations` row when a bout starts — that is what the
scoring table reads to find a face, a club, a belt and a record for the VS introduction. Free,
marked settled, so a mat can never surface as an unpaid entry.

**Join codes live in the cache**, not a table (4-hour TTL, both directions). A code is worth
nothing once the mat is shut. The alphabet excludes `O`/`0`/`I`/`1` — it gets read off a screen
and typed on a phone.

---

## Routes

| | |
|---|---|
| `GET /openmat` | resolve-or-open a mat, redirect to its console |
| `GET /openmat/join/{code}` | take a corner (signed in) |
| `POST /openmat/join/{code}` | …and the write behind it |
| `GET /me/events/{uuid}/manage` | the console (shared event route, package view) |
| `POST /me/events/{uuid}/actions/{action}` | every write (shared event route, package actions) |
| `GET /me/events/{uuid}/openmat` | mat state as JSON, for a realtime re-read |
| `GET /me/events/{uuid}/openmat/search` | find an opponent |
| `GET /me/events/{uuid}/openmat/qr` | the mat's join QR as SVG |

`/openmat` is top-level rather than under `/me` because it is the one address in the product
somebody is told out loud — *"go to takeone.bh/openmat"* — and it has to be short enough to say
across a dojo and type one-handed.

---

## Actions

Every write goes through the shared, throttled, authorised `me.events.action` endpoint, which
**refuses any action the package does not currently offer** (deny by default, so a stale page
cannot post something the mat has moved past). A closed mat offers none.

| action | notes |
|---|---|
| `add_member` | onto the floor; re-runs the search pool as its authorisation check |
| `add_guest` | onto the floor; name cleaned: control chars out, whitespace collapsed, 60 chars |
| `remove_person` | off the floor; refused while they are standing in a corner |
| `assign_corner` | stand a floor person in a corner — the panel's two taps |
| `place_member` | add + assign in one move (the phone console's sheet) |
| `place_guest` | add + assign in one move |
| `clear_corner`, `swap_corners` | |
| `start_bout` | idempotent while a bout is live on that mat |
| `set_mats` | up to 4 |
| `set_sport` | **only while the mat has fought nothing** |
| `new_code` | burns a code that has been photographed or shouted across a hall |
| `close_mat` | archives; the bouts stay readable |

Both surfaces post to the same endpoint: the NEXT PAIR panel uses `add_*` / `assign_corner` /
`start_bout`, and the mat console's sheet uses `place_*`, which is `add` + `assign` in one call.
There is no second write path and no token-authorised one.

`set_sport` is locked once anything has been fought because the sport decides the scoring table,
the corner names and which screen fleet a paired display belongs to — changing it under bouts
fought by other rules would make those results mean something they did not mean.

---

## Security

- **Operating a mat** is `EventAccess::canManage || canOfficiate` — the same authority every other
  event type answers to. This package invents no rule of its own, so a mat can never be operable
  by somebody an event of any other type would refuse. Verified: a non-operator gets `403` on the
  state, search and QR endpoints.
- **The search is a search, not a directory.** `users.is_discoverable` is honoured (except for
  finding yourself), blocks are mutual, there is a minimum term length and a 12-row cap, the
  endpoint is throttled, and each row carries only a name, a club and a photo the member
  *published* — the same three facts `people.show` already shows any signed-in member.
  > This is a **wider surface** than a club-mates-only rule. It is a deliberate, requested
  > trade: the feature is unusable without it (a visitor from another club could not be found,
  > and on a small club the search returned nothing ever).
- **The console is a convenience; the endpoint is the door.** Every guard lives in
  `OpenMatSession` — unknown mat, unknown side, blank name, same person in both corners,
  un-findable user id, writes after close — not in the Blade.
- **The QR endpoint takes a MAT, never a URL**, so it can never be turned into a QR generator for
  arbitrary content pointing anywhere.
- **Photos obey `profile_picture_is_public`** wherever a face reaches a corner or a wall — a hall
  screen is a publication.
- **The scoring-table panel is signed-in only.** A table paired by *device token* has no user
  behind it, and every write the panel makes is a member-authorised endpoint. Rather than open a
  token-authorised way to put arbitrary names on a mat, that door gets no panel at all
  (`ScoreboardController::packagePanel()` returns null with no `Auth::user()`).
- **Join is signed-in only.** The code is public by design and short-lived; the mat behind it is
  re-checked on every request, and a wrong or expired code says only that the code is wrong —
  never "that mat has closed", which would tell a stranger the code was once real.

---

## Realtime

Writes push `{action: 'open_mat'}` on the `events` channel — a **refresh signal, not a payload**,
because what each holder of the console may see differs, so they re-fetch from
`me.events.openmat`. The mat's wall screens get the sport's own `ScreenChannel::notifyCourt`.
Both are best-effort: a screen that missed one re-reads when it next polls.

The listener is stored on `window.__openMatRealtime` and removed before re-adding, because the
mobile shell re-runs inline scripts on every AJAX navigation.

---

## Testing it by hand

**The one trip:**

1. Sign in, go to **`/openmat`** — you land on a mat.
2. Tap the **red card** → *That's me*, or search a name / phone / email, or type a guest, or show
   the QR and scan it on a second phone.
3. Fill blue the same way → **Start the bout** → the sport's scoring table opens with both names
   already on the mat.

**Then stay there, all evening:**

4. Score it: hajime, points, finish, commit.
5. Tap **NEXT PAIR**. Add whoever has turned up — search, *Add myself*, or a typed name. Tap one
   person for **RED** and another for **BLUE**, then **START THIS PAIR**. The page does not change.
6. Repeat. The floor re-sorts each round so whoever has fought least is at the top, and each
   member shows their `won–lost` beside their bout count.

**Worth checking as well:** pair a hall screen from the mat console's *Hall screens* section and
watch the wall board follow; open the join link on a second phone and take a corner from there —
the panel picks it up over MQTT without a reload. **End** on the mat console's hero closes the mat.

Both sports: `/karate/control/{uuid}` and `/taekwondo/control/{uuid}` behave identically.

---

## Deleting it

Remove `app/Events/OpenMat/`, its one line in `config/event_types.php`, its routes, and drop the
three `open_mat_*` tables. The mats become ordinary archived club events and nothing else in the
product notices. That is the test of whether it is really a package.

The hooks left behind in the two sport consoles go inert by themselves — `$matPanel` is null for
every other type, so the button and the include render nothing and the seam is never called. They
can be removed with it, or left as the extension point for the next package that needs one.
`AbstractEventType::matPanel()` is general, not Open Mat's: it is the answer to *"this type needs
one control on the scoring table"*, whoever asks next.

---

## Not built yet

- **MCP tool coverage** — a new readable/actionable surface normally ships with it.
- **Tests** — none written; the flow was verified by hand end to end through the real Karate
  engine (load → hajime → points → finish → commit → record filed).
- **The casual record on a member's profile.** The data is written and `OpenMatResult::recordFor()`
  serves it; the only place it currently shows is the corner card. Deliberately kept apart from
  medals and tournament placings — a training win is a real thing that happened and is not a
  competitive result, and a profile showing the two together would make both harder to read.
- **A public spectator link** for a running mat.
- **Club-less mats** — see *Why it is a `ClubEvent`*.
