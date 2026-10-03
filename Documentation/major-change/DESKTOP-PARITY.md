# Desktop vs mobile — what desktop is missing (audit 2026-10-03)

Five read-only agents compared every mobile/desktop pair. Nothing was changed.
"High" = a desktop user cannot do it at all.

## HIGH — desktop users cannot do these

### Getting around
1. **No way into the personal area** (Feed, Schedule, Challenge, Events, Market) from the desktop top bar, profile menu or ⌘K — the logo and landing go to Explore. (`layouts/app.blade.php`, `app/Support/Landing.php:31`)
2. **Club admin: Messages and Notifications pages** are in the mobile menu only (`app/Clubs/Clubs.php:52-53`); **"Send notification"** has no desktop trigger.
3. **Payments** (`me.payments`: pay a subscription bill by uploading proof) — no desktop link anywhere; desktop points to the older invoices page.
4. **QR scan with a typed 6-character code** — desktop opens the scanner without the code box, so a laptop with no camera is stuck.

### Club admin
5. **Add member by scanning their QR** (`members/partials/mobile-add-member.blade.php`).
6. **Register a child on their own** (desktop always opens guardian mode).
7. **Facilities: edit the description** (desktop edit form has no description field; no Arabic description at all).

### Events
8. **"Create event" button** on the events list when you already have events.
9. **Bout video: add/edit/delete coach notes**, and **delete your own comment**.

### Member area
10. **Market: cart button** (the cart exists but nothing opens it), **search box** (does nothing), **My orders** link.
11. **Challenge history** — no desktop link.
12. **Profile: Follow / Unfollow** (desktop button does nothing), **Message**, **multi-photo gallery** (add / set as avatar / delete), **request verification / get vouched** for affiliations and work history.

### Business
13. **Revenue-per-club chart** — data is passed to desktop but never drawn.

## MEDIUM — possible on desktop but harder, or partly missing
- Club dashboard: pending-payments list per member, recent transactions.
- Financials: ledger search, month picker, "open member" from a row.
- Achievements: search + year filter. Roles: per-member custom access from the Roles page.
- Instructors: pre-fill skills/experience when picking an existing member.
- Club details: recent reviews.
- Club mode (locked): bell and QR missing on the desktop bar. Security link missing from desktop profile menu.
- Shop orders: month stepper and monthly stats. Featured-events pending count in super-admin sidebar.
- Running order: "pick and place anywhere" (desktop only moves one step at a time).
- Bout video player: speed, loop, picture-in-picture, theater, settings.
- Generic event console: officials who may score don't see "Running order".
- Profile: share, public-profile link, challenges section, schedule-based attendance, contact card.
- People profile: share, sports section, certifications/honours.

## Bugs found along the way (not parity)
- Club sidebar Financials tooltip shows fake numbers "BHD 250 / -200 / 50" (`layouts/admin-club.blade.php:325-327`).
- Club dashboard: "Renew" button and Packages "Duplicate" button do nothing; Hall of Fame / schedule bars are placeholder data.
- Bout video desktop: Like / Share / Download buttons do nothing.
- Mobile event console shows Clubs/Divisions rows to officials who then get "forbidden".
- Mobile console gives scoring officials no way into scoring (desktop has a "Scoring table" card).
- Super-admin "Delete video" control is missing on mobile.
- Open Mat and My Videos are in neither menu.

Full agent reports are in this session's history; file:line references above.

## Status — 2026-10-03

- **All 13 HIGH items: done** (commit db328fff).
- **MEDIUM: done** — club dashboard pending/recent cards; financials search + month + open member;
  achievements search + year; instructor skills prefill; club reviews; club-mode bar QR + bell
  (the bell is now ONE partial, `partials/notification-bell`, used by both bars); Security,
  My videos, Open Mat in the desktop menu; shop orders month + stats; featured/businesses badges;
  running order pick-and-place; bout player speed/loop/picture-in-picture; scorers see
  Running order; profile share/contact/challenges tab/class schedule; people profile
  share/sports/certifications.
- **Bugs: fixed** — fake Financials tooltip, Renew + Duplicate buttons, bout Like/Share/Download,
  officials seeing Clubs/Divisions, scorers with no way into scoring, menus missing Open Mat/videos.
- **Not done:** Roles page per-member access (still reachable from Members); "theater" mode
  (Hide Highlights already widens the player); super-admin Delete video on mobile;
  Hall of Fame / schedule placeholder bars on the club dashboard.
- **Note:** championship event pages are phone-layout at EVERY width by design
  (`BrandEventPage`), so the desktop event templates only serve Sparring, Open Mat and generic events.
