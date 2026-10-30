# TakeOne Federation — Implementation Brief for Claude Code

**Owner:** Ghassan Yusuf · **Prepared:** 22 Oct 2026 · **Target:** the TakeOne codebase behind https://stage.takeone.bh (federation manager area: `/federation/...`, organiser area: `/me/events/...`)

This file contains everything from the UX audit (text audit + hands-on test with 156 screenshots) and the agreed redesign. It is written so you (Claude Code) can implement it phase by phase.

---

## 0. How to work through this file

1. **Read the codebase first.** Find: the federation SPA (sidebar, event workspace tabs, participants, draw, schedule, results, financials), the organiser draw desk (`/me/events/{id}/draw-desk`), the i18n layer (`i18n-dom.js`), the event/division/registration models, and the metrics used on Statistics/Events/Athletes/Clubs.
2. **Work in phases, in order.** One branch per phase (`fix/phase-1-integrity`, …). Phase 1 is server-side and does not depend on the redesign.
3. **Every behaviour change gets a test** (unit or feature test on the server; component test where the UI has logic).
4. **Never run destructive actions against real events.** Use a seeded test event. Staging data from the QA run is listed in §1.
5. **Follow the house rules already in the codebase** (the QA run referenced "Design Rules" #4, #8, #9, `<x-fields.date>`, `<x-gender-avatar>`, Bootstrap Icons, Poppins, names never truncated). Where this brief conflicts with an existing house rule, follow the house rule and note it.
6. After each phase: summarise what changed, list files touched, and list anything you could not do.

### Domain glossary

| Term | Meaning |
|---|---|
| Federation | e.g. Bahrain Karate Federation, child of Bahrain Combat Sports Council |
| Event | A competition, e.g. "Bahrain Energy Zone Combat 4" (16 Oct 2026, 57 athletes, 19 divisions, 43 bouts) |
| Division | An age × weight × gender bracket, currently named like `Group C : 8-9y, 27.1-31.5kg` |
| Entry | One athlete in one division. Athletes ≠ entries (57 athletes produced 63 entries because of double entries) |
| Merge | Moving a lone athlete into a nearby division so they can compete. **Intentional and normal practice** — must be supported, recorded and checked for safety, not blocked |
| Weigh-in | Recording the athlete's weight on the day |
| Draw | Building brackets for each division |

---

## 1. Phase 0 — Staging cleanup and urgent security

### 1.1 Remove QA test data (staging only)
The hands-on test left data on staging. Test mode is still ON and turning it off does **not** undo weigh-ins, division edits, outcomes or schedule changes.
- Event "[QA TEST] Lifecycle 2026-10-22" (federation uuid `019b3f7d-02a2-40a3-845e-757cc770cce8`, platform event `fe21095b-046b-4cd4-ac8d-59ccb31f2ad3`)
- Pending club "QA Test Club" (coach "QA Test Coach") and its 12 "QA Test" athletes
- Write a one-off cleanup command/script; ask Ghassan before running it.
- Remind Ghassan to delete the saved Playwright login `state.json` from the earlier QA scratchpad (`/tmp/claude-0/-var-www-takeone/.../scratchpad/state.json`).

### 1.2 Data exposure in the enrol response — HIGH
After every enrolment the `regs` response returns **every event's registrations**. Scope the response to the target event only. Add a test asserting no other event's data is returned.

### 1.3 Minors' national IDs — HIGH
Full national IDs of 5–17-year-olds are shown in Athletes, Participants and the athlete dialog.
- Hide the National ID column from lists. Show masked (`•••••5042`) in the athlete drawer; reveal only for a permitted role, and log who revealed it.
- Search must still match full ID server-side.
- Validate ID on input: normalise Arabic-Indic digits (`٠-٩` → `0-9`), enforce the country's format (Bahrain CPR: 9 digits), flag letters/wrong length. Add a "Data issues" filter for invalid/missing IDs.

### 1.4 Unbound template placeholders
About 15 requests per page 404 on `/federation/app/{{ p.photo }}`; raw `{{ d.sex }}` etc. flash on load. Bind `src` only after render (`v-cloak`/`x-cloak` or equivalent), and set `<title>` server-side.

---

## 2. Phase 1 — Division integrity (server-side rules)

### 2.1 BUG: "One division per person" ignores existing double entries — CRITICAL
**What happened:** the draw was built manually, then the switch was turned on. The switch only blocks new placements. 5 athletes stayed in two divisions and fought in both (Saden Mohammad D+F, سلمان سعد ابراهيم L+O, Mohamed Moosa Husain M+Q, Nasser Mohamed Q+T, Abdulkarim Abdulla Q+T). Reproduced in QA: switching ON left "Faisal" in −67 and −75 after reload; he was scheduled in back-to-back bouts #1 and #2.

**Required behaviour:**
1. Enforce on the server, not only in the UI.
2. When the switch is turned ON (and on every save, Excel import, auto-place, merge and manual drag), scan for athletes in more than one division.
3. If conflicts exist, the switch shows "On · N conflicts" and the API returns the conflict list.
4. UI opens **Resolve double entries** (mockup `DoubleEntries.dc.html`): one row per athlete, a radio per division, suggested choice = the division whose age **and** weight range fit (e.g. Nasser, 15 y → Group T; Group Q is 13–14 y). When both fit, no pre-selection.
5. Applying moves the athlete out of the other division(s), re-seeds/redraws affected brackets, and shows each bracket's new size before confirming. If a division drops below the minimum (§2.3), hand off to the small-division flow.
6. **Generate draw** and **Start the day** are blocked while any conflict is open.
7. If bouts were already fought for the affected entries, warn that results will change and require explicit confirmation; log it.

**Acceptance:** feature tests for (a) toggle ON with existing doubles returns conflicts and does not silently pass; (b) manual placement into a second division is rejected when ON; (c) resolve endpoint removes the other entries and recalculates counts; (d) draw generation is refused while conflicts exist.

### 2.2 Weigh-in must respect the division range — HIGH
QA: 69 kg in a −67 kg division got a green ✓; not moved or flagged, although the dialog promises to "place them in the division this weight fits".
- On weigh-in, compare weight to the division range (and age to the age band).
- Out of range → amber state "69 kg · above range" plus actions in the same dialog: **Move to −75 kg** (suggested nearest valid division), **Keep with recorded reason** (merge), **Disqualify**.
- Never show a green ✓ for an out-of-range weight.

### 2.3 Minimum entrants and safe merges — HIGH
Managers legitimately merge lone athletes into nearby divisions. The app must **guide and record** this, not leave it as a silent manual move.

**New: Draw rules** (mockup `DrawRules.dc.html`)
- **Federation defaults:** Settings → Competition rules (one per season).
- **Per-event copy:** step 1 of the Draw tab. Copies defaults; changed values marked "changed from default".
- Rules:

| Rule | Default | Effect |
|---|---|---|
| Minimum athletes per division | 3 (configurable 2–4) | Division below it cannot be drawn without a decision |
| Maximum per bracket | 16 | |
| Bronze awarded from | 3 athletes | |
| One division per athlete | On | See §2.1 |
| Keep same club apart in round 1 | On | Seeding |
| Allow merging | On | |
| Merge direction | Up only (heavier/older) | |
| Max weight step | Next class, ≤ 10% of body weight | Larger gaps blocked, not just warned |
| Max age step | 1 age band | |
| Mixed gender allowed | Under 12 only (configurable: never) | |
| Club consent for minors | Required | |
| When a division is too small | Suggest nearest safe merge / Exhibition bout (unranked) / Withdraw and refund | |
| Notify the club | On | |
| Require a reason for each move | On | |

- **Live preview** strip: "19 divisions · 4 below the minimum · 5 double entries" with one card per flagged division (for the real event: Groups A, B, O, U with 2 athletes each).
- **Generate draw** disabled until rules are saved and every flagged division has a decision.
- Rules **lock** when the draw is generated. Changing them before the first bout = redraw with warning; after the first bout = not allowed.

**Merge flow:** at entry close / weigh-in lock → list divisions under the minimum → for each lone athlete suggest the nearest safe division (ranked by weight and age gap; unsafe options greyed with reason) → manager picks Merge / Combine two small divisions / Exhibition / Withdraw with refund → notify club (or record verbal consent) → athlete row shows badge "Moved up from Group X"; division shows "Merged"; change log stores who/when/why.

**Validation treats an approved, recorded merge as an exception, not an error.** Only moves that break the rules or lack a record stay flagged. Existing data: 4 such moves on Energy Zone Combat 4 (يوسف عبدالله السبيعي 99.9 kg in 100–128.5 kg; علي عبدالله قمبر 10 y / 26.9 kg in 8–9 y / 34.8–39.8 kg; سلطان احمد سلطان 11 y in 14–15 y; Nasser Mohamed 15 y in 13–14 y) → show "Moved up · no reason recorded".

### 2.4 Division definition validation — HIGH
- Reject ranges where min = max (Group T "77.6–77.6 kg").
- Division codes must be unique per event (two different "Group T"s exist).
- Generate readable names: "Boys 14–15 · +100 kg" / "Girls 13–16 · 45.4–48.3 kg", keep the short code as secondary. Sort by age → gender → weight.
- Warn when weight spread > ~10% of the lower bound (Group R spans 28.5 kg) or a youth division mixes genders against the rule.

### 2.5 Walkovers must not vanish — HIGH
One-entrant divisions show DRAWN, then disappear from Results and from "Still being fought". Either block them (per §2.3) or record a walkover champion and show it in Results.

### 2.6 Referee conflict of interest — HIGH
3 referees belong to competing clubs (Energy Zone, Shams Martial Arts Academy). Flag conflicts and prevent assigning a referee to a bout involving their own club. Add a coverage check (referees, judges, table officials, medical).

---

## 3. Phase 2 — One source of truth: status and counts

### 3.1 Event lifecycle — CRITICAL
Same event currently shows Planning (header/list), Upcoming (list tab), Finished (`/me/events`), Running (every division) and Live (Results).
- Single lifecycle: **Setup → Registration → Weigh-in → Draw → Live → Results → Closed**.
- Derived from data (dates, entries closed, weigh-in locked, draw locked, first bout scored, all finals done, results published, invoices reconciled) — never typed.
- Same status everywhere: event header, events list, list counters, Statistics, `/me/events`, club portal, public page.
- Each step has one **Next step** button (e.g. "Publish official results").
- Per-division status: Not drawn → Drawn (locked) → In progress → Completed → Official.
- Events list groups: Live/Today, Upcoming, **Needs closing** (past date, still open), Completed.
- Results page: "Final results" once completed (not "Live from the mats"); **Publish official results** = sign-off + timestamp → results PDF, club medal table, certificates.

### 3.2 One event id
The same event has two ids and two workspaces (`/federation/events/ec1d78a9…` and `/me/events/734a96a9…`). Use one canonical id; move the order editor into the Schedule tab; show the bracket read-only inside the federation Draw tab (no jump to the dark draw desk; hide "Unlock to set winners" on completed events).

### 3.3 Metrics service — HIGH
Currently: athletes 57 / 155 / 160 / 168 / 184 / 0; clubs 7 / 16 / 17; medals 118 / 134 / 142; divisions 19 vs 20 vs 22.
- One service with named, documented definitions: `registered_athletes`, `active_athletes` (distinct athletes with ≥1 entry this season), `entries`, `rostered_athletes`, `affiliated_clubs`, `divisions`, `medals`.
- "Active athletes" must count **distinct** athletes, not entries (155 = 57 + 98 entries).
- Every KPI gets a focusable definition tooltip.
- Overview/Draw/Clubs gender & division breakdowns come from the same query.
- Subscription tier must use the real athlete count (Settings shows "Athletes 0").

---

## 4. Phase 3 — Finance

- **Balance formula:** Outstanding = Billed − Discounts − Received. Never show negative "Credit" when nothing was received (Energy Zone: Billed 570, Received 0, "Awaiting 695" — four clubs show −15/−30/−40/−40). Add reconciliation tests.
- Invoiced entry fees count as **receivable**; show Collected and Outstanding. Header "Revenue" must match Financials.
- **Record payment** dialog: amount, method, reference, proof upload → creates a transaction; undo/void with audit trail. Replace the one-click "Mark as received".
- Federation Financials: first block **To confirm** (pending bank transfers by club/event with proof, Confirm/Reject). Statistics' "44 club payments awaiting confirmation · BHD 695.000" must link here and agree.
- One currency label: `BHD` with 3 decimals (not "BD EQUIV.").

---

## 5. Phase 4 — Mobile and tablet (quick wins first)

1. **Bottom nav → 5 slots:** Stats · Events · Athletes · Clubs · **More** (sheet: Calendar, Financials, Reports, Sub-federations, Settings). Today 9 × 52 px in 390 px → Settings unreachable (also in the Android app). Items ≥ 48 px tall, labels ≥ 11 px, real `<a href>`. Mockup `MobileHub.dc.html`.
2. **Draw tab:** whole page scrolls 606 px sideways at 390 (233 px at 768); rows have `min-width:1080px`; other tabs freeze until reload. Below 768 px: filters in a bottom sheet, rows as cards, sticky bottom action bar (Auto-place / Merge / ⋯). Fix the tab freeze. Mockup `MobileDraw.dc.html`.
3. **Scroll to top on every route change;** restore only on Back.
4. **Event tabs on phone:** show an event hub list (Participants, Draw, Results, Schedule, Officials, Clubs, Financials) with counts/status badges instead of 8 tabs (only 3 visible today). At 768 px, add edge fade and scroll active tab into view.
5. **All wide lists below 600 px → cards:** whole-row link, 44 px ⋯ actions sheet, full names that wrap (never clip — Arabic names currently lose first letters).
6. Athletes: paginate (25/page); long-press for selection instead of 20 px checkboxes.
7. Federation switcher: full-width bottom sheet (currently widens viewport to 436 px); show current federation name in header.
8. Statistics: medal table as cards (names break letter by letter); 2-column KPI grid instead of 1,717 px carousel; hide filter bar on scroll (3 sticky bars = 23% of viewport).
9. Menus (Printables) as bottom sheets ≤ 768 px. Edit event as full-screen sheet with **Delete** moved away from Cancel and behind confirmation.
10. Tap targets ≥ 44 px everywhere (calendar arrows 36, links 18, Leaflet zoom 30).

---

## 6. Phase 5 — Navigation, routes and lists (desktop)

- **Sidebar, bottom nav, event rows, athlete/club names, event tabs → real `<a href>`** with `aria-current`. Today they are `div`/`span`: no keyboard access, no middle-click, no Back.
- **Routes:** `/federation/{fed}/…` — federation in the URL (today in localStorage `fed-org-cur`, so reload in one tab can switch federation). Route for every screen, tab and view (`/clubs` tab, `/participants?view=by-division`, `/draw/{divisionId}`, Sub-federations). Unknown routes → real 404 with role-aware help (not silent redirect).
- Breadcrumb/title show the active federation: "Bahrain Karate Federation › Events".
- **Participants** (mockup `Participants.dc.html`): columns ☐ · Athlete (name + sub-line: TakeOne ID · gender · age · country · club) · Division (readable name + code / chips) · Weigh-in · Status · ⋯. Remove duplicated Gender/Age/"Karate"/division-ages columns and the National ID column. Views: All · **Needs attention** · By division. One primary action (+ Enroll athlete), Printables, More ⋯ (Import, Download, Withdrawal history). `nowrap` on weights; pin weigh-in and actions right. Pagination.
- **Event header:** compact (~120 px) on every tab except Overview; hide empty KPI cards ("57 / unlimited", "—"); fix trailing comma in venue; dates with year ("16 Oct 2026").
- **Overview** (mockup `Main.dc.html`): lifecycle bar + Next step, **Needs attention** list (each item with its own fix action), At a glance (Athletes 57 · Division entries 63 · Divisions 19 · Bouts 43 · Clubs 7 · Weighed in 57/57), Entry fees card.
- **Draw toolbar:** group Build / Output / ⋯; destructive "Clear every division" goes in ⋯ behind typed confirmation; lock draw once a bout is scored.
- Event Clubs tab: red "Remove" into row ⋯ menu with confirmation; make Clubs the delegation hub (athletes, team managers, invoice/balance, payments, medals). Officials tab = technical officials only; team managers move to Clubs.

---

## 7. Phase 6 — Schedule, scoring, create event

- **Auto-schedule:** label by the event's sport (karate event says "Round by round (World Taekwondo)"); compute real per-bout start times (all show 9:00 / 2:00 PM); enforce minimum rest between one athlete's bouts; recommend mat count; curfew for juniors (e.g. no under-12 bouts after 21:00); block or loudly warn past midnight (current plan ends 1:21 AM on 1 mat); default lunch break.
- Sort the time rail by time; flag overlaps (break 1:00–2:00 PM overlaps a running class); hide "No schedule yet" when a running order exists; remove empty "Untitled part" items; divisions count must match (20 vs 19).
- **Bout numbers:** automatic by default, manual override by exception, validate no gaps (today #1–9 unnumbered, #10–47 with gaps, all "set by hand").
- **Scoring (draw desk):** clicking a score box must not pick the competitor up ("Holding X"); Escape cancels a hold; visible **Confirm winner** on the bout card instead of the hidden "Declare results" toggle; success toast without ⚠ icon.
- **Create event from federation:** pre-fill host, sport and location from the current federation (today: host = Council, every sport, map = Dubai); sync dates on create (no "TBC"); after publish land on the event, not a language splash; offer **Create as test event** on the form; fix `/me/category-systems` 422 (host saved as `club:federation:<uuid>` — doubled prefix) so the weight-class preset works; wrap weigh-in, division edits, outcomes, day-order, one-division switch and schedule in the test recorder so purge undoes them.
- **Excel import:** DOB column as text, accept year-only; warn in preview when a row gets no division (blank DOB → "0 years").
- Podium certificate "Download PDF" is a silent no-op → disable with "Issue certificates first". Draw PDF podium must print full names.
- Native date inputs (mm/dd/yyyy) → `<x-fields.date>`.

---

## 8. Phase 7 — Accessibility

- One shared **overlay helper** for dialogs, sheets, menus: focus first control, trap Tab, Escape + backdrop close, return focus to trigger, `role=dialog` / `aria-modal` / `aria-labelledby`; listbox pattern for menus. Apply to: club Manage, Edit event, Enroll athlete, athlete detail, federation switcher, language menu (language currently cannot be changed by keyboard), merge panel.
- Semantic `<table>` with `<th scope>` and `aria-sort` for all lists (zero `<table>` today). No nested interactive controls (row `button` containing "Weighed in" button).
- Tabs: `<a href aria-current>` or proper `tablist`/`tab`/`aria-selected` (reuse the `/explore` pattern).
- Accessible names start with the visible label ("⤓ Report" is announced "The whole competition as one PDF report"); name "→" buttons by action; 17 identical "Manage" → "Manage <club>".
- Replace emoji/glyph icons (🔗 ⤓ ⎙ ▾ ✓ +) with Bootstrap Icons + `aria-hidden`.
- KPI values exposed with labels (`<dl>`); one `h1` per page, `h2` per section; header landmark; single `main` (`/explore` has nested `main`); skip link; labelled search inputs; label `/register` controls; closed panels `inert`/`hidden` (account menu with email is exposed on `/explore`); no `href="#"`.
- Contrast: table headers #97A1B4 = 2.60:1 at 9.6 px → ≥ 11 px #6B7689 (4.6:1); "No email/phone/address on file" 2.13:1 → one line "No contact details" #6B7689; gold eyebrows 4.3:1 → ≈ #7A5F18.
- Keep: gold `:focus-visible` outline (works well).

---

## 9. Phase 8 — Arabic / i18n

- Replace whole-text-node matching in `i18n-dom.js` with keyed `t('key', {n})` strings + ICU plurals (Arabic needs zero/one/two/few/many/other). 70–90% of labels stay English today because any string with a number never matches. Full untranslated list: Appendix B.
- Locale routing `/ar/...` (today 404; `?lang=` stripped). Set `<html lang dir>`. Save language on the account (today localStorage `fed-lang`).
- Wrap user data in `<bdi>` / `dir="auto"`; format dates, times, numbers, units and BHD with `Intl` for the locale, one digit system everywhere (e.g. `ar-u-nu-latn`); ص/م for times; "كغ" or `<bdi>` around value+unit.
- Division labels from structured data (no hand-built "8-9y, 27.1-31.5kg"; use U+2212 or isolate signed values like "−45 kg").
- Mirror arrows/chevrons (`bi-chevron-left rtl:rotate-180`), search icon position, charts.
- Bilingual names for athletes and clubs (`name_ar`, `name_en`), `Intl.Collator` sorting, `Intl.DisplayNames` for countries (list sorted wrongly), one initials helper (or gender avatar).
- Data normalisation on entry: capitalisation ("Shreya sarath"), typos ("Isa Kahlifa", "Sparta Worrior"), controlled sport names ("Basket Ball", "M M A").

---

## 10. Phase 9 — Remaining page fixes

| Page | Fix |
|---|---|
| Statistics | Tabs (Participation · Competitions · Clubs & Medals · Officials); "Action needed" strip linking to fixes; collapse untracked sections into one "Not tracked yet" rail; `align-items:start` (empty cards stretch to 770 px); scale activity bars to total and round to 100%; "+16 new clubs" vs "No previous season" contradiction; WKF result methods instead of "Ippon"; region "Bahrain Karate Federation" → "Unassigned region"; full-width filters at 1920 |
| Events | Grouped list, row link, next-step button per row, KPIs that add up across events, hide capacity bar when unlimited, year on dates, clean venue data ("Table Tennis, Federation"), no version strings in names |
| Clubs | Club profile page; columns Club (AR/EN) · Head coach · Active/Registered athletes · Affiliation valid until · Payments · Last event; meaningful status (Active/Expiring/Expired/Pending/Suspended); "Data issues" filter; flag foreign/guest clubs (Hero Karate Academy, Saudi Arabia); one coach source (dialog shows "—"); emails not broken mid-word |
| Calendar | Open on current month with Today button and today ring; events as bars; Agenda view with deadlines; week start from country (Sunday for Bahrain); 44 px arrows |
| Athletes | KPIs from same dataset as list; filter chips; Add/Export; pagination; sticky header; belt/grade column (currently shows weight); guardian contact for minors; TakeOne ID that doesn't encode nationality |
| Reports | Working CSV exports (all 4 link to `#`); real "last generated" time; hide reports with no data; report builder (report → scope → CSV/XLSX/PDF) |
| Settings | Tabs with own Save (Profile & branding, Region & locale, Structure, Staff & roles, Billing); federation name field empty; selects labelled by label not value; time zone from country, sorted, no deprecated IDs; billing shows real count vs tier and next invoice; remove duplicated Sub-federations block; clearer hierarchy names than "Sub-sub-federation" |
| Sidebar | Sub-federations route (404 today); crest 186 → ~96 px |

---

## 11. Design tokens used in the mockups

Approximation of TakeOne's look — replace with the real values in the codebase if they differ.

| Token | Value | Use |
|---|---|---|
| Font | Poppins 400/500/600/700 | All text |
| Ink | #172033 | Text, sidebar background |
| Primary button | #1F2A44 bg, #FFFFFF text | One per screen |
| Muted text | #5B6476 | Secondary text (≥ 4.5:1 on white) |
| Ground | #F5F6F8 | Page background |
| Card | #FFFFFF, border #E3E6EC, radius 14 px | |
| Divider | #EEF0F3 | |
| Gold (fills/indicators) | #A8781F / #D4A23A | Active nav, current step |
| Gold text | #7A5A12 | Eyebrows |
| Critical | bg #FDECEC, text #A12020 | |
| Warning | bg #FFF4E5, text #8A4B00 | |
| Success | bg #E8F5EE, text #156A3A | |
| Tap target | ≥ 44 px (48 px on phone bars) | |

---

## 12. Definition of done (per phase)

- [ ] Server rules enforced with tests (Phase 1, 3).
- [ ] Same status and counts on every screen for a seeded event (Phase 2).
- [ ] 390 px and 768 px: no horizontal page scroll on any federation page; Settings reachable (Phase 4).
- [ ] Keyboard-only: sidebar, events, athletes, tabs, all dialogs and menus usable; Escape closes; focus returns (Phase 7).
- [ ] Arabic: no English interface labels on the 13 audited pages; dates/numbers/units correct (Phase 8).
- [ ] No national ID of a minor visible in any list.
- [ ] Lighthouse/axe pass with no critical issues on Events, Participants, Draw.

---

## Appendix A — Mockup source files

Six static mockups (Design Component `.dc.html` format; the markup inside `<x-dc>` is plain HTML with inline styles and can be read as a visual spec). Use them as the target layout, not as production code.

| File | Screen |
|---|---|
| `Main.dc.html` | Event workspace · Overview with lifecycle bar, Needs attention, At a glance, Entry fees |
| `DrawRules.dc.html` | Draw tab · step 1 Draw rules with preview and locked Generate draw |
| `Participants.dc.html` | Participants · leaner table, flags, Needs attention view, pagination |
| `DoubleEntries.dc.html` | Resolve double entries dialog |
| `MobileHub.dc.html` | Phone · event hub + 5-slot bottom nav |
| `MobileDraw.dc.html` | Phone · Draw as cards with sticky action bar |


### A.Main — `Main.dc.html`

```html
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Event overview</title>
<script src="./support.js"></script>
</head>
<body>
<x-dc>
<helmet>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
<style>
body{margin:0;font-family:Poppins,system-ui,sans-serif}
a{color:#1F2A44}a:hover{color:#7A5A12}
</style>
</helmet>
<div style="min-height: 1000px; display: flex; flex-wrap: wrap; background: #F5F6F8; color: #172033; font-family: Poppins, system-ui, sans-serif; font-size: 14px;">

  <nav aria-label="Federation" style="flex: 1 1 220px; max-width: 232px; background: #172033; color: #FFFFFF; padding: 24px 16px; box-sizing: border-box; display: flex; flex-direction: column; gap: 4px;">
    <div style="display: flex; align-items: center; gap: 10px; padding: 0 8px 20px;">
      <div style="width: 40px; height: 40px; border-radius: 10px; background: #A8781F; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px;">BKF</div>
      <div style="display: flex; flex-direction: column;">
        <span style="font-weight: 600; font-size: 13px; line-height: 1.3;">Bahrain Karate Federation</span>
        <span style="font-size: 11px; color: #C9CFDA;">Federation Manager</span>
      </div>
    </div>
    <a href="#" style="color: #DDE2EA; text-decoration: none; padding: 10px 12px; border-radius: 8px;">Statistics</a>
    <a href="#" aria-current="page" style="color: #FFFFFF; text-decoration: none; padding: 10px 12px; border-radius: 8px; background: rgba(255,255,255,0.08); box-shadow: inset 3px 0 0 #D4A23A; font-weight: 600;">Events</a>
    <a href="#" style="color: #DDE2EA; text-decoration: none; padding: 10px 12px; border-radius: 8px;">Clubs</a>
    <a href="#" style="color: #DDE2EA; text-decoration: none; padding: 10px 12px; border-radius: 8px;">Athletes</a>
    <a href="#" style="color: #DDE2EA; text-decoration: none; padding: 10px 12px; border-radius: 8px;">Calendar</a>
    <a href="#" style="color: #DDE2EA; text-decoration: none; padding: 10px 12px; border-radius: 8px;">Financials</a>
    <a href="#" style="color: #DDE2EA; text-decoration: none; padding: 10px 12px; border-radius: 8px;">Reports</a>
    <a href="#" style="color: #DDE2EA; text-decoration: none; padding: 10px 12px; border-radius: 8px;">Sub-federations</a>
    <a href="#" style="color: #DDE2EA; text-decoration: none; padding: 10px 12px; border-radius: 8px;">Settings</a>
  </nav>

  <main style="flex: 999 1 560px; min-width: 0; padding: 24px 32px 40px; box-sizing: border-box; display: flex; flex-direction: column; gap: 20px;">

    <nav aria-label="Breadcrumb" style="font-size: 13px; color: #5B6476; display: flex; gap: 8px; flex-wrap: wrap;">
      <a href="#" style="color: #5B6476;">Bahrain Karate Federation</a><span aria-hidden="true">›</span>
      <a href="#" style="color: #5B6476;">Events</a><span aria-hidden="true">›</span>
      <span style="color: #172033;">Bahrain Energy Zone Combat 4</span>
    </nav>

    <section style="background: #FFFFFF; border: 1px solid #E3E6EC; border-radius: 14px; padding: 20px 24px; display: flex; flex-direction: column; gap: 18px;">
      <div style="display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-start; justify-content: space-between;">
        <div style="display: flex; flex-direction: column; gap: 6px;">
          <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <span style="font-size: 11px; font-weight: 600; letter-spacing: 0.06em; color: #7A5A12;">KARATE</span>
            <span style="font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 999px; background: #FFF4E5; color: #8A4B00;">Results · awaiting sign-off</span>
          </div>
          <h1 style="margin: 0; font-size: 24px; font-weight: 600; line-height: 1.25;">Bahrain Energy Zone Combat 4</h1>
          <span style="font-size: 13px; color: #5B6476;">Fri 16 Oct 2026 · Isa Sports City</span>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
          <button type="button" style="font: inherit; font-size: 13px; padding: 10px 14px; border-radius: 10px; border: 1px solid #D0D5DD; background: #FFFFFF; color: #172033; min-height: 44px;">Report PDF</button>
          <button type="button" aria-label="More actions" style="font: inherit; font-size: 16px; padding: 10px 14px; border-radius: 10px; border: 1px solid #D0D5DD; background: #FFFFFF; color: #172033; min-height: 44px;">⋯</button>
          <button type="button" style="font: inherit; font-size: 13px; font-weight: 600; padding: 10px 18px; border-radius: 10px; border: none; background: #1F2A44; color: #FFFFFF; min-height: 44px;">Next step: Publish official results</button>
        </div>
      </div>

      <ol aria-label="Event lifecycle" style="list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 6px;">
        <li style="display: flex; flex-direction: column; gap: 6px;"><div style="height: 6px; border-radius: 3px; background: #156A3A;"></div><span style="font-size: 12px; color: #156A3A; font-weight: 500;">✓ Setup</span></li>
        <li style="display: flex; flex-direction: column; gap: 6px;"><div style="height: 6px; border-radius: 3px; background: #156A3A;"></div><span style="font-size: 12px; color: #156A3A; font-weight: 500;">✓ Registration</span></li>
        <li style="display: flex; flex-direction: column; gap: 6px;"><div style="height: 6px; border-radius: 3px; background: #156A3A;"></div><span style="font-size: 12px; color: #156A3A; font-weight: 500;">✓ Weigh-in</span></li>
        <li style="display: flex; flex-direction: column; gap: 6px;"><div style="height: 6px; border-radius: 3px; background: #156A3A;"></div><span style="font-size: 12px; color: #156A3A; font-weight: 500;">✓ Draw</span></li>
        <li style="display: flex; flex-direction: column; gap: 6px;"><div style="height: 6px; border-radius: 3px; background: #156A3A;"></div><span style="font-size: 12px; color: #156A3A; font-weight: 500;">✓ Live</span></li>
        <li aria-current="step" style="display: flex; flex-direction: column; gap: 6px;"><div style="height: 6px; border-radius: 3px; background: #D4A23A;"></div><span style="font-size: 12px; color: #172033; font-weight: 600;">Results</span></li>
        <li style="display: flex; flex-direction: column; gap: 6px;"><div style="height: 6px; border-radius: 3px; background: #E3E6EC;"></div><span style="font-size: 12px; color: #5B6476;">Closed</span></li>
      </ol>
    </section>

    <nav aria-label="Event sections" style="display: flex; gap: 4px; flex-wrap: wrap; border-bottom: 1px solid #E3E6EC;">
      <a href="#" aria-current="page" style="text-decoration: none; padding: 12px 14px; font-weight: 600; color: #172033; box-shadow: inset 0 -3px 0 #D4A23A;">Overview</a>
      <a href="#" style="text-decoration: none; padding: 12px 14px; color: #5B6476;">Participants <span style="color: #5B6476;">57</span></a>
      <a href="#" style="text-decoration: none; padding: 12px 14px; color: #5B6476;">Officials <span>9</span></a>
      <a href="#" style="text-decoration: none; padding: 12px 14px; color: #5B6476;">Clubs <span>7</span></a>
      <a href="DrawRules.dc.html" style="text-decoration: none; padding: 12px 14px; color: #5B6476;">Draw <span>19</span></a>
      <a href="#" style="text-decoration: none; padding: 12px 14px; color: #5B6476;">Results</a>
      <a href="#" style="text-decoration: none; padding: 12px 14px; color: #5B6476;">Schedule</a>
      <a href="#" style="text-decoration: none; padding: 12px 14px; color: #5B6476;">Financials</a>
    </nav>

    <div style="display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-start;">

      <section aria-labelledby="attn" style="flex: 999 1 520px; min-width: 0; background: #FFFFFF; border: 1px solid #E3E6EC; border-radius: 14px; padding: 20px 24px; display: flex; flex-direction: column; gap: 4px;">
        <div style="display: flex; align-items: baseline; justify-content: space-between; gap: 12px; padding-bottom: 8px;">
          <h2 id="attn" style="margin: 0; font-size: 17px; font-weight: 600;">Needs attention · 6</h2>
          <span style="font-size: 12px; color: #5B6476;">Sorted by severity</span>
        </div>

        <div style="display: flex; gap: 14px; align-items: center; padding: 14px 0; border-top: 1px solid #EEF0F3;">
          <span style="flex: none; font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 6px; background: #FDECEC; color: #A12020; min-width: 58px; text-align: center;">Critical</span>
          <div style="flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px;">
            <span style="font-weight: 600;">5 athletes are in two divisions</span>
            <span style="font-size: 13px; color: #5B6476;">One division per person is on. Choose where each one competes.</span>
          </div>
          <a href="DoubleEntries.dc.html" style="flex: none; font-size: 13px; font-weight: 600; padding: 10px 14px; border-radius: 10px; background: #1F2A44; color: #FFFFFF; text-decoration: none; min-height: 44px; box-sizing: border-box; display: inline-flex; align-items: center;">Resolve</a>
        </div>

        <div style="display: flex; gap: 14px; align-items: center; padding: 14px 0; border-top: 1px solid #EEF0F3;">
          <span style="flex: none; font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 6px; background: #FFF4E5; color: #8A4B00; min-width: 58px; text-align: center;">High</span>
          <div style="flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px;">
            <span style="font-weight: 600;">4 divisions are below the minimum of 3</span>
            <span style="font-size: 13px; color: #5B6476;">Groups A, B, O and U have 2 athletes each.</span>
          </div>
          <a href="DrawRules.dc.html" style="flex: none; font-size: 13px; font-weight: 600; padding: 10px 14px; border-radius: 10px; border: 1px solid #D0D5DD; color: #172033; text-decoration: none; min-height: 44px; box-sizing: border-box; display: inline-flex; align-items: center;">Review merges</a>
        </div>

        <div style="display: flex; gap: 14px; align-items: center; padding: 14px 0; border-top: 1px solid #EEF0F3;">
          <span style="flex: none; font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 6px; background: #FFF4E5; color: #8A4B00; min-width: 58px; text-align: center;">High</span>
          <div style="flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px;">
            <span style="font-weight: 600;">4 athletes were moved up with no reason recorded</span>
            <span style="font-size: 13px; color: #5B6476;">Record who approved each merge and why.</span>
          </div>
          <button type="button" style="flex: none; font: inherit; font-size: 13px; font-weight: 600; padding: 10px 14px; border-radius: 10px; border: 1px solid #D0D5DD; background: #FFFFFF; color: #172033; min-height: 44px;">Add reasons</button>
        </div>

        <div style="display: flex; gap: 14px; align-items: center; padding: 14px 0; border-top: 1px solid #EEF0F3;">
          <span style="flex: none; font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 6px; background: #FFF4E5; color: #8A4B00; min-width: 58px; text-align: center;">High</span>
          <div style="flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px;">
            <span style="font-weight: 600;">3 referees belong to competing clubs</span>
            <span style="font-size: 13px; color: #5B6476;">Energy Zone · Shams Martial Arts Academy</span>
          </div>
          <button type="button" style="flex: none; font: inherit; font-size: 13px; font-weight: 600; padding: 10px 14px; border-radius: 10px; border: 1px solid #D0D5DD; background: #FFFFFF; color: #172033; min-height: 44px;">Check bouts</button>
        </div>

        <div style="display: flex; gap: 14px; align-items: center; padding: 14px 0; border-top: 1px solid #EEF0F3;">
          <span style="flex: none; font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 6px; background: #EEF1F6; color: #3B4558; min-width: 58px; text-align: center;">Medium</span>
          <div style="flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px;">
            <span style="font-weight: 600;">Results are not official yet</span>
            <span style="font-size: 13px; color: #5B6476;">All 19 divisions finished on 16 Oct.</span>
          </div>
          <button type="button" style="flex: none; font: inherit; font-size: 13px; font-weight: 600; padding: 10px 14px; border-radius: 10px; border: 1px solid #D0D5DD; background: #FFFFFF; color: #172033; min-height: 44px;">Publish</button>
        </div>

        <div style="display: flex; gap: 14px; align-items: center; padding: 14px 0; border-top: 1px solid #EEF0F3;">
          <span style="flex: none; font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 6px; background: #EEF1F6; color: #3B4558; min-width: 58px; text-align: center;">Medium</span>
          <div style="flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px;">
            <span style="font-weight: 600;">BHD 570.000 outstanding from 7 clubs</span>
            <span style="font-size: 13px; color: #5B6476;">No payments recorded yet.</span>
          </div>
          <button type="button" style="flex: none; font: inherit; font-size: 13px; font-weight: 600; padding: 10px 14px; border-radius: 10px; border: 1px solid #D0D5DD; background: #FFFFFF; color: #172033; min-height: 44px;">Record payments</button>
        </div>
      </section>

      <aside style="flex: 1 1 280px; min-width: 0; display: flex; flex-direction: column; gap: 20px;">
        <section aria-labelledby="glance" style="background: #FFFFFF; border: 1px solid #E3E6EC; border-radius: 14px; padding: 20px 24px; display: flex; flex-direction: column; gap: 14px;">
          <h2 id="glance" style="margin: 0; font-size: 17px; font-weight: 600;">At a glance</h2>
          <dl style="margin: 0; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px;">
            <div><dt style="font-size: 12px; color: #5B6476;">Athletes</dt><dd style="margin: 2px 0 0; font-size: 24px; font-weight: 600;">57</dd></div>
            <div><dt style="font-size: 12px; color: #5B6476;">Division entries</dt><dd style="margin: 2px 0 0; font-size: 24px; font-weight: 600;">63</dd></div>
            <div><dt style="font-size: 12px; color: #5B6476;">Divisions</dt><dd style="margin: 2px 0 0; font-size: 24px; font-weight: 600;">19</dd></div>
            <div><dt style="font-size: 12px; color: #5B6476;">Bouts</dt><dd style="margin: 2px 0 0; font-size: 24px; font-weight: 600;">43</dd></div>
            <div><dt style="font-size: 12px; color: #5B6476;">Clubs</dt><dd style="margin: 2px 0 0; font-size: 24px; font-weight: 600;">7</dd></div>
            <div><dt style="font-size: 12px; color: #5B6476;">Weighed in</dt><dd style="margin: 2px 0 0; font-size: 24px; font-weight: 600;">57/57</dd></div>
          </dl>
        </section>

        <section aria-labelledby="money" style="background: #FFFFFF; border: 1px solid #E3E6EC; border-radius: 14px; padding: 20px 24px; display: flex; flex-direction: column; gap: 10px;">
          <h2 id="money" style="margin: 0; font-size: 17px; font-weight: 600;">Entry fees</h2>
          <div style="display: flex; justify-content: space-between;"><span style="color: #5B6476;">Billed</span><span style="font-weight: 500;">BHD 570.000</span></div>
          <div style="display: flex; justify-content: space-between;"><span style="color: #5B6476;">Received</span><span style="font-weight: 500;">BHD 0.000</span></div>
          <div style="display: flex; justify-content: space-between; border-top: 1px solid #EEF0F3; padding-top: 10px;"><span style="font-weight: 600;">Outstanding</span><span style="font-weight: 600; color: #8A4B00;">BHD 570.000</span></div>
        </section>
      </aside>
    </div>
  </main>
</div>
</x-dc>
<script type="text/x-dc" data-dc-script data-props='{"$preview":{"width":1440,"height":1000}}'>
class Component extends DCLogic {
  renderVals() { return {}; }
}
</script>
</body>
</html>
```

### A.DrawRules — `DrawRules.dc.html`

```html
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Draw rules</title>
<script src="./support.js"></script>
</head>
<body>
<x-dc>
<helmet>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
<style>
body{margin:0;font-family:Poppins,system-ui,sans-serif}
a{color:#1F2A44}a:hover{color:#7A5A12}
</style>
</helmet>
<div style="min-height: 1000px; display: flex; flex-wrap: wrap; background: #F5F6F8; color: #172033; font-family: Poppins, system-ui, sans-serif; font-size: 14px;">

  <nav aria-label="Federation" style="flex: 1 1 220px; max-width: 232px; background: #172033; color: #FFFFFF; padding: 24px 16px; box-sizing: border-box; display: flex; flex-direction: column; gap: 4px;">
    <div style="display: flex; align-items: center; gap: 10px; padding: 0 8px 20px;">
      <div style="width: 40px; height: 40px; border-radius: 10px; background: #A8781F; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px;">BKF</div>
      <div style="display: flex; flex-direction: column;">
        <span style="font-weight: 600; font-size: 13px; line-height: 1.3;">Bahrain Karate Federation</span>
        <span style="font-size: 11px; color: #C9CFDA;">Federation Manager</span>
      </div>
    </div>
    <a href="#" style="color: #DDE2EA; text-decoration: none; padding: 10px 12px; border-radius: 8px;">Statistics</a>
    <a href="Main.dc.html" aria-current="page" style="color: #FFFFFF; text-decoration: none; padding: 10px 12px; border-radius: 8px; background: rgba(255,255,255,0.08); box-shadow: inset 3px 0 0 #D4A23A; font-weight: 600;">Events</a>
    <a href="#" style="color: #DDE2EA; text-decoration: none; padding: 10px 12px; border-radius: 8px;">Clubs</a>
    <a href="#" style="color: #DDE2EA; text-decoration: none; padding: 10px 12px; border-radius: 8px;">Athletes</a>
    <a href="#" style="color: #DDE2EA; text-decoration: none; padding: 10px 12px; border-radius: 8px;">Calendar</a>
    <a href="#" style="color: #DDE2EA; text-decoration: none; padding: 10px 12px; border-radius: 8px;">Financials</a>
    <a href="#" style="color: #DDE2EA; text-decoration: none; padding: 10px 12px; border-radius: 8px;">Reports</a>
    <a href="#" style="color: #DDE2EA; text-decoration: none; padding: 10px 12px; border-radius: 8px;">Sub-federations</a>
    <a href="#" style="color: #DDE2EA; text-decoration: none; padding: 10px 12px; border-radius: 8px;">Settings</a>
  </nav>

  <main style="flex: 999 1 560px; min-width: 0; padding: 24px 32px 40px; box-sizing: border-box; display: flex; flex-direction: column; gap: 20px;">

    <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between;">
      <div style="display: flex; flex-direction: column; gap: 2px;">
        <a href="Main.dc.html" style="font-size: 13px; color: #5B6476;">‹ Bahrain Energy Zone Combat 4</a>
        <h1 style="margin: 0; font-size: 22px; font-weight: 600;">Draw</h1>
      </div>
      <ol aria-label="Draw steps" style="list-style: none; margin: 0; padding: 0; display: flex; gap: 8px; flex-wrap: wrap;">
        <li aria-current="step" style="font-size: 13px; font-weight: 600; padding: 8px 14px; border-radius: 999px; background: #1F2A44; color: #FFFFFF;">1 · Draw rules</li>
        <li style="font-size: 13px; padding: 8px 14px; border-radius: 999px; background: #FFFFFF; border: 1px solid #E3E6EC; color: #5B6476;">2 · Divisions and brackets</li>
      </ol>
    </div>

    <section style="background: #FFFFFF; border: 1px solid #E3E6EC; border-radius: 14px; padding: 20px 24px; display: flex; flex-direction: column; gap: 4px;">
      <h2 style="margin: 0; font-size: 17px; font-weight: 600;">Set the rules before the draw</h2>
      <p style="margin: 0; font-size: 13px; color: #5B6476;">Copied from the federation defaults. Changes here apply to this event only. Rules lock when the draw is generated.</p>
    </section>

    <div style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 20px;">

      <fieldset style="margin: 0; background: #FFFFFF; border: 1px solid #E3E6EC; border-radius: 14px; padding: 20px 24px; display: flex; flex-direction: column; gap: 16px; min-width: 0;">
        <legend style="font-size: 15px; font-weight: 600; padding: 0 4px;">Division size</legend>
        <label style="display: flex; align-items: center; justify-content: space-between; gap: 12px;"><span>Minimum athletes</span><input type="number" value="3" min="2" style="font: inherit; width: 72px; min-height: 40px; padding: 0 10px; border: 1px solid #D0D5DD; border-radius: 8px; box-sizing: border-box;"></label>
        <label style="display: flex; align-items: center; justify-content: space-between; gap: 12px;"><span>Maximum per bracket</span><input type="number" value="16" style="font: inherit; width: 72px; min-height: 40px; padding: 0 10px; border: 1px solid #D0D5DD; border-radius: 8px; box-sizing: border-box;"></label>
        <label style="display: flex; align-items: center; justify-content: space-between; gap: 12px;"><span>Bronze awarded from</span><select style="font: inherit; min-height: 40px; padding: 0 8px; border: 1px solid #D0D5DD; border-radius: 8px; background: #FFFFFF;"><option>3 athletes</option><option>4 athletes</option></select></label>
        <label style="display: flex; align-items: center; justify-content: space-between; gap: 12px;"><span>One division per athlete</span><input type="checkbox" checked style="width: 22px; height: 22px; accent-color: #1F2A44;"></label>
        <div style="display: flex; gap: 10px; align-items: center; padding: 10px 12px; border-radius: 10px; background: #FDECEC; color: #A12020; font-size: 13px;">
          <span style="flex: 1;">5 athletes are already in two divisions</span>
          <a href="DoubleEntries.dc.html" style="color: #A12020; font-weight: 600;">Resolve</a>
        </div>
        <label style="display: flex; align-items: center; justify-content: space-between; gap: 12px;"><span>Keep same club apart in round 1</span><input type="checkbox" checked style="width: 22px; height: 22px; accent-color: #1F2A44;"></label>
      </fieldset>

      <fieldset style="margin: 0; background: #FFFFFF; border: 1px solid #E3E6EC; border-radius: 14px; padding: 20px 24px; display: flex; flex-direction: column; gap: 16px; min-width: 0;">
        <legend style="font-size: 15px; font-weight: 600; padding: 0 4px;">Safe merging</legend>
        <label style="display: flex; align-items: center; justify-content: space-between; gap: 12px;"><span>Allow merging</span><input type="checkbox" checked style="width: 22px; height: 22px; accent-color: #1F2A44;"></label>
        <label style="display: flex; align-items: center; justify-content: space-between; gap: 12px;"><span>Direction</span><select style="font: inherit; min-height: 40px; padding: 0 8px; border: 1px solid #D0D5DD; border-radius: 8px; background: #FFFFFF;"><option>Up only (heavier / older)</option><option>Up or down</option></select></label>
        <label style="display: flex; align-items: center; justify-content: space-between; gap: 12px;"><span>Max weight step</span><select style="font: inherit; min-height: 40px; padding: 0 8px; border: 1px solid #D0D5DD; border-radius: 8px; background: #FFFFFF;"><option>Next class, ≤ 10%</option><option>Next class only</option><option>≤ 5 kg</option></select></label>
        <label style="display: flex; align-items: center; justify-content: space-between; gap: 12px;"><span>Max age step</span><select style="font: inherit; min-height: 40px; padding: 0 8px; border: 1px solid #D0D5DD; border-radius: 8px; background: #FFFFFF;"><option>1 age band</option><option>Same band only</option></select></label>
        <label style="display: flex; align-items: center; justify-content: space-between; gap: 12px;"><span>Mixed gender allowed</span><select style="font: inherit; min-height: 40px; padding: 0 8px; border: 1px solid #D0D5DD; border-radius: 8px; background: #FFFFFF;"><option>Under 12 only</option><option>Never</option></select></label>
        <label style="display: flex; align-items: center; justify-content: space-between; gap: 12px;"><span>Club consent for minors</span><input type="checkbox" checked style="width: 22px; height: 22px; accent-color: #1F2A44;"></label>
      </fieldset>

      <fieldset style="margin: 0; background: #FFFFFF; border: 1px solid #E3E6EC; border-radius: 14px; padding: 20px 24px; display: flex; flex-direction: column; gap: 14px; min-width: 0;">
        <legend style="font-size: 15px; font-weight: 600; padding: 0 4px;">When a division is too small</legend>
        <label style="display: flex; align-items: center; gap: 10px; min-height: 40px;"><input type="radio" name="small" checked style="width: 20px; height: 20px; accent-color: #1F2A44;"><span>Suggest the nearest safe merge</span></label>
        <label style="display: flex; align-items: center; gap: 10px; min-height: 40px;"><input type="radio" name="small" style="width: 20px; height: 20px; accent-color: #1F2A44;"><span>Exhibition bout (not ranked)</span></label>
        <label style="display: flex; align-items: center; gap: 10px; min-height: 40px;"><input type="radio" name="small" style="width: 20px; height: 20px; accent-color: #1F2A44;"><span>Withdraw and refund</span></label>
        <div style="border-top: 1px solid #EEF0F3;"></div>
        <label style="display: flex; align-items: center; justify-content: space-between; gap: 12px;"><span>Notify the club</span><input type="checkbox" checked style="width: 22px; height: 22px; accent-color: #1F2A44;"></label>
        <label style="display: flex; align-items: center; justify-content: space-between; gap: 12px;"><span>Require a reason for each move</span><input type="checkbox" checked style="width: 22px; height: 22px; accent-color: #1F2A44;"></label>
      </fieldset>
    </div>

    <section aria-labelledby="preview" style="background: #FFFFFF; border: 1px solid #E3E6EC; border-radius: 14px; padding: 20px 24px; display: flex; flex-direction: column; gap: 12px;">
      <div style="display: flex; align-items: baseline; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
        <h2 id="preview" style="margin: 0; font-size: 17px; font-weight: 600;">Preview with these rules</h2>
        <span style="font-size: 13px; color: #5B6476;">19 divisions · 4 below the minimum · 5 double entries</span>
      </div>
      <div style="display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px;">
        <div style="border: 1px solid #F1D3A6; background: #FFF9F0; border-radius: 12px; padding: 14px; display: flex; flex-direction: column; gap: 6px;">
          <span style="font-weight: 600;">Group A</span><span style="font-size: 13px; color: #5B6476;">5–6 yrs · 17–24 kg · 2 athletes</span>
          <button type="button" style="font: inherit; font-size: 13px; font-weight: 600; min-height: 40px; border-radius: 8px; border: 1px solid #D0D5DD; background: #FFFFFF; color: #172033;">Choose an option</button>
        </div>
        <div style="border: 1px solid #F1D3A6; background: #FFF9F0; border-radius: 12px; padding: 14px; display: flex; flex-direction: column; gap: 6px;">
          <span style="font-weight: 600;">Group B</span><span style="font-size: 13px; color: #5B6476;">8–9 yrs · 22.7–24.5 kg · 2 athletes</span>
          <button type="button" style="font: inherit; font-size: 13px; font-weight: 600; min-height: 40px; border-radius: 8px; border: 1px solid #D0D5DD; background: #FFFFFF; color: #172033;">Choose an option</button>
        </div>
        <div style="border: 1px solid #F1D3A6; background: #FFF9F0; border-radius: 12px; padding: 14px; display: flex; flex-direction: column; gap: 6px;">
          <span style="font-weight: 600;">Group O</span><span style="font-size: 13px; color: #5B6476;">14–15 yrs · 59.3–65.9 kg · 2 athletes</span>
          <button type="button" style="font: inherit; font-size: 13px; font-weight: 600; min-height: 40px; border-radius: 8px; border: 1px solid #D0D5DD; background: #FFFFFF; color: #172033;">Choose an option</button>
        </div>
        <div style="border: 1px solid #F1D3A6; background: #FFF9F0; border-radius: 12px; padding: 14px; display: flex; flex-direction: column; gap: 6px;">
          <span style="font-weight: 600;">Group U</span><span style="font-size: 13px; color: #5B6476;">13–16 yrs · 45.4–48.3 kg · 2 athletes</span>
          <button type="button" style="font: inherit; font-size: 13px; font-weight: 600; min-height: 40px; border-radius: 8px; border: 1px solid #D0D5DD; background: #FFFFFF; color: #172033;">Choose an option</button>
        </div>
      </div>
    </section>

    <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: flex-end;">
      <span style="flex: 1 1 300px; font-size: 13px; color: #5B6476;">Generate draw unlocks when the rules are saved and every flagged division has a decision.</span>
      <button type="button" style="font: inherit; font-size: 14px; padding: 10px 18px; border-radius: 10px; border: 1px solid #D0D5DD; background: #FFFFFF; color: #172033; min-height: 44px;">Save rules</button>
      <button type="button" disabled style="font: inherit; font-size: 14px; font-weight: 600; padding: 10px 18px; border-radius: 10px; border: none; background: #C9CFDA; color: #3B4558; min-height: 44px;">Generate draw</button>
    </div>
  </main>
</div>
</x-dc>
<script type="text/x-dc" data-dc-script data-props='{"$preview":{"width":1440,"height":1000}}'>
class Component extends DCLogic {
  renderVals() { return {}; }
}
</script>
</body>
</html>
```

### A.Participants — `Participants.dc.html`

```html
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Participants</title>
<script src="./support.js"></script>
</head>
<body>
<x-dc>
<helmet>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
<style>
body{margin:0;font-family:Poppins,system-ui,sans-serif}
a{color:#1F2A44}a:hover{color:#7A5A12}
</style>
</helmet>
<div style="min-height: 900px; display: flex; flex-wrap: wrap; background: #F5F6F8; color: #172033; font-family: Poppins, system-ui, sans-serif; font-size: 14px;">

  <nav aria-label="Federation" style="flex: 1 1 220px; max-width: 232px; background: #172033; color: #FFFFFF; padding: 24px 16px; box-sizing: border-box; display: flex; flex-direction: column; gap: 4px;">
    <div style="display: flex; align-items: center; gap: 10px; padding: 0 8px 20px;">
      <div style="width: 40px; height: 40px; border-radius: 10px; background: #A8781F; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px;">BKF</div>
      <div style="display: flex; flex-direction: column;">
        <span style="font-weight: 600; font-size: 13px; line-height: 1.3;">Bahrain Karate Federation</span>
        <span style="font-size: 11px; color: #C9CFDA;">Federation Manager</span>
      </div>
    </div>
    <a href="#" style="color: #DDE2EA; text-decoration: none; padding: 10px 12px; border-radius: 8px;">Statistics</a>
    <a href="Main.dc.html" aria-current="page" style="color: #FFFFFF; text-decoration: none; padding: 10px 12px; border-radius: 8px; background: rgba(255,255,255,0.08); box-shadow: inset 3px 0 0 #D4A23A; font-weight: 600;">Events</a>
    <a href="#" style="color: #DDE2EA; text-decoration: none; padding: 10px 12px; border-radius: 8px;">Clubs</a>
    <a href="#" style="color: #DDE2EA; text-decoration: none; padding: 10px 12px; border-radius: 8px;">Athletes</a>
    <a href="#" style="color: #DDE2EA; text-decoration: none; padding: 10px 12px; border-radius: 8px;">Calendar</a>
    <a href="#" style="color: #DDE2EA; text-decoration: none; padding: 10px 12px; border-radius: 8px;">Financials</a>
    <a href="#" style="color: #DDE2EA; text-decoration: none; padding: 10px 12px; border-radius: 8px;">Reports</a>
    <a href="#" style="color: #DDE2EA; text-decoration: none; padding: 10px 12px; border-radius: 8px;">Sub-federations</a>
    <a href="#" style="color: #DDE2EA; text-decoration: none; padding: 10px 12px; border-radius: 8px;">Settings</a>
  </nav>

  <main style="flex: 999 1 560px; min-width: 0; padding: 24px 32px 40px; box-sizing: border-box; display: flex; flex-direction: column; gap: 16px;">

    <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; justify-content: space-between;">
      <div style="display: flex; flex-direction: column; gap: 2px;">
        <a href="Main.dc.html" style="font-size: 13px; color: #5B6476;">‹ Bahrain Energy Zone Combat 4</a>
        <h1 style="margin: 0; font-size: 22px; font-weight: 600;">Participants</h1>
      </div>
      <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <button type="button" aria-label="More: import, download, withdrawal history" style="font: inherit; font-size: 13px; padding: 10px 14px; border-radius: 10px; border: 1px solid #D0D5DD; background: #FFFFFF; color: #172033; min-height: 44px;">More ⋯</button>
        <button type="button" style="font: inherit; font-size: 13px; padding: 10px 14px; border-radius: 10px; border: 1px solid #D0D5DD; background: #FFFFFF; color: #172033; min-height: 44px;">Printables ▾</button>
        <button type="button" style="font: inherit; font-size: 13px; font-weight: 600; padding: 10px 18px; border-radius: 10px; border: none; background: #1F2A44; color: #FFFFFF; min-height: 44px;">+ Enroll athlete</button>
      </div>
    </div>

    <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
      <label style="flex: 1 1 280px; display: flex; flex-direction: column;"><span style="position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0);">Search participants</span><input type="search" placeholder="Search name, ID or club" style="font: inherit; min-height: 44px; padding: 0 14px; border: 1px solid #D0D5DD; border-radius: 10px; box-sizing: border-box;"></label>
      <div role="group" aria-label="View" style="display: flex; background: #E9ECF1; border-radius: 10px; padding: 3px;">
        <button type="button" aria-pressed="true" style="font: inherit; font-size: 13px; font-weight: 600; padding: 8px 14px; border-radius: 8px; border: none; background: #FFFFFF; color: #172033; min-height: 38px;">All 57</button>
        <button type="button" aria-pressed="false" style="font: inherit; font-size: 13px; padding: 8px 14px; border-radius: 8px; border: none; background: transparent; color: #8A4B00; min-height: 38px;">Needs attention 8</button>
        <button type="button" aria-pressed="false" style="font: inherit; font-size: 13px; padding: 8px 14px; border-radius: 8px; border: none; background: transparent; color: #3B4558; min-height: 38px;">By division</button>
      </div>
      <button type="button" style="font: inherit; font-size: 13px; padding: 8px 14px; border-radius: 10px; border: 1px solid #D0D5DD; background: #FFFFFF; color: #172033; min-height: 44px;">Filters · Gender, status, club, age, weight</button>
    </div>

    <div style="background: #FFFFFF; border: 1px solid #E3E6EC; border-radius: 14px; overflow-x: auto;">
      <table style="width: 100%; border-collapse: collapse; min-width: 880px;">
        <thead>
          <tr style="text-align: left; font-size: 12px; color: #4A5468; border-bottom: 1px solid #E3E6EC;">
            <th scope="col" style="padding: 12px 16px; width: 32px;"><input type="checkbox" aria-label="Select all" style="width: 18px; height: 18px;"></th>
            <th scope="col" aria-sort="ascending" style="padding: 12px 8px; font-weight: 600;">Athlete</th>
            <th scope="col" style="padding: 12px 8px; font-weight: 600;">Division</th>
            <th scope="col" style="padding: 12px 8px; font-weight: 600;">Weigh-in</th>
            <th scope="col" style="padding: 12px 8px; font-weight: 600;">Status</th>
            <th scope="col" style="padding: 12px 16px; width: 44px;"><span style="position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0);">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          <tr style="border-bottom: 1px solid #EEF0F3;">
            <td style="padding: 12px 16px;"><input type="checkbox" aria-label="Select Khaled Jasim Alhajri" style="width: 18px; height: 18px;"></td>
            <td style="padding: 12px 8px;"><a href="#" style="font-weight: 600; color: #172033; text-decoration: none;">Khaled Jasim Alhajri</a><div style="font-size: 12px; color: #5B6476;">BHR-KA750860 · Male · 8 · Bahrain · Energy Zone</div></td>
            <td style="padding: 12px 8px;">Boys 8–9 · <span style="white-space: nowrap;">27.1–31.5 kg</span><div style="font-size: 12px; color: #5B6476;">Group C</div></td>
            <td style="padding: 12px 8px; white-space: nowrap; color: #156A3A; font-weight: 500;">✓ 31.5 kg</td>
            <td style="padding: 12px 8px;"><span style="font-size: 12px; padding: 3px 8px; border-radius: 6px; background: #E8F5EE; color: #156A3A;">Approved</span></td>
            <td style="padding: 12px 16px;"><button type="button" aria-label="Actions for Khaled Jasim Alhajri" style="font: inherit; width: 40px; height: 40px; border-radius: 8px; border: none; background: transparent; color: #3B4558;">⋯</button></td>
          </tr>
          <tr style="border-bottom: 1px solid #EEF0F3; background: #FFF7F7;">
            <td style="padding: 12px 16px;"><input type="checkbox" aria-label="Select Nasser Mohamed" style="width: 18px; height: 18px;"></td>
            <td style="padding: 12px 8px;"><a href="#" style="font-weight: 600; color: #172033; text-decoration: none;">Nasser Mohamed</a><div style="font-size: 12px; color: #5B6476;">BHR-NM984309 · Male · 15 · Bahrain · Shams Martial Arts Academy</div></td>
            <td style="padding: 12px 8px;"><span style="font-size: 12px; font-weight: 600; padding: 3px 8px; border-radius: 6px; background: #FDECEC; color: #A12020;">In 2 divisions</span><div style="font-size: 12px; color: #5B6476; margin-top: 4px;">Group Q and Group T · <a href="DoubleEntries.dc.html" style="color: #A12020; font-weight: 600;">Resolve</a></div></td>
            <td style="padding: 12px 8px; white-space: nowrap; color: #156A3A; font-weight: 500;">✓ 77.6 kg</td>
            <td style="padding: 12px 8px;"><span style="font-size: 12px; padding: 3px 8px; border-radius: 6px; background: #E8F5EE; color: #156A3A;">Approved</span></td>
            <td style="padding: 12px 16px;"><button type="button" aria-label="Actions for Nasser Mohamed" style="font: inherit; width: 40px; height: 40px; border-radius: 8px; border: none; background: transparent; color: #3B4558;">⋯</button></td>
          </tr>
          <tr style="border-bottom: 1px solid #EEF0F3; background: #FFFBF4;">
            <td style="padding: 12px 16px;"><input type="checkbox" aria-label="Select علي عبدالله قمبر" style="width: 18px; height: 18px;"></td>
            <td style="padding: 12px 8px;"><a href="#" dir="auto" style="font-weight: 600; color: #172033; text-decoration: none;">علي عبدالله قمبر</a><div style="font-size: 12px; color: #5B6476;">BHR-AK231884 · Male · 10 · Bahrain · Alkaabi Fighter Academy</div></td>
            <td style="padding: 12px 8px;">Mixed 8–9 · <span style="white-space: nowrap;">34.8–39.8 kg</span><div style="font-size: 12px; margin-top: 4px;"><span style="font-weight: 600; padding: 2px 8px; border-radius: 6px; background: #FFF4E5; color: #8A4B00;">Moved up · no reason recorded</span></div></td>
            <td style="padding: 12px 8px; white-space: nowrap; color: #8A4B00; font-weight: 500;">26.9 kg · below range</td>
            <td style="padding: 12px 8px;"><span style="font-size: 12px; padding: 3px 8px; border-radius: 6px; background: #E8F5EE; color: #156A3A;">Approved</span></td>
            <td style="padding: 12px 16px;"><button type="button" aria-label="Actions for علي عبدالله قمبر" style="font: inherit; width: 40px; height: 40px; border-radius: 8px; border: none; background: transparent; color: #3B4558;">⋯</button></td>
          </tr>
          <tr style="border-bottom: 1px solid #EEF0F3;">
            <td style="padding: 12px 16px;"><input type="checkbox" aria-label="Select Vanniya Sri" style="width: 18px; height: 18px;"></td>
            <td style="padding: 12px 8px;"><a href="#" style="font-weight: 600; color: #172033; text-decoration: none;">Vanniya Sri</a><div style="font-size: 12px; color: #5B6476;">IND-VS684671 · Female · 8 · India · Energy Zone</div></td>
            <td style="padding: 12px 8px;">Mixed 8–9 · <span style="white-space: nowrap;">34.8–39.8 kg</span><div style="font-size: 12px; color: #5B6476;">Group D</div></td>
            <td style="padding: 12px 8px; white-space: nowrap; color: #156A3A; font-weight: 500;">✓ 34.8 kg</td>
            <td style="padding: 12px 8px;"><span style="font-size: 12px; padding: 3px 8px; border-radius: 6px; background: #E8F5EE; color: #156A3A;">Approved</span></td>
            <td style="padding: 12px 16px;"><button type="button" aria-label="Actions for Vanniya Sri" style="font: inherit; width: 40px; height: 40px; border-radius: 8px; border: none; background: transparent; color: #3B4558;">⋯</button></td>
          </tr>
          <tr style="border-bottom: 1px solid #EEF0F3; background: #FFFBF4;">
            <td style="padding: 12px 16px;"><input type="checkbox" aria-label="Select يوسف عبدالله السبيعي" style="width: 18px; height: 18px;"></td>
            <td style="padding: 12px 8px;"><a href="#" dir="auto" style="font-weight: 600; color: #172033; text-decoration: none;">يوسف عبدالله السبيعي</a><div style="font-size: 12px; color: #5B6476;">BHR-YA413745 · Male · 13 · Bahrain · Alkaabi Fighter Academy</div></td>
            <td style="padding: 12px 8px;">Boys 14–15 · <span style="white-space: nowrap;">100–128.5 kg</span><div style="font-size: 12px; margin-top: 4px;"><span style="font-weight: 600; padding: 2px 8px; border-radius: 6px; background: #FFF4E5; color: #8A4B00;">Moved up · no reason recorded</span></div></td>
            <td style="padding: 12px 8px; white-space: nowrap; color: #8A4B00; font-weight: 500;">99.9 kg · below range</td>
            <td style="padding: 12px 8px;"><span style="font-size: 12px; padding: 3px 8px; border-radius: 6px; background: #E8F5EE; color: #156A3A;">Approved</span></td>
            <td style="padding: 12px 16px;"><button type="button" aria-label="Actions for يوسف عبدالله السبيعي" style="font: inherit; width: 40px; height: 40px; border-radius: 8px; border: none; background: transparent; color: #3B4558;">⋯</button></td>
          </tr>
          <tr style="border-bottom: 1px solid #EEF0F3;">
            <td style="padding: 12px 16px;"><input type="checkbox" aria-label="Select Shreya Sarath" style="width: 18px; height: 18px;"></td>
            <td style="padding: 12px 8px;"><a href="#" style="font-weight: 600; color: #172033; text-decoration: none;">Shreya Sarath</a><div style="font-size: 12px; color: #5B6476;">IND-SS588292 · Female · 13 · India · Shams Martial Arts Academy</div></td>
            <td style="padding: 12px 8px;">Girls 13–16 · <span style="white-space: nowrap;">45.4–48.3 kg</span><div style="font-size: 12px; color: #5B6476;">Group U</div></td>
            <td style="padding: 12px 8px; white-space: nowrap; color: #156A3A; font-weight: 500;">✓ 45.4 kg</td>
            <td style="padding: 12px 8px;"><span style="font-size: 12px; padding: 3px 8px; border-radius: 6px; background: #E8F5EE; color: #156A3A;">Approved</span></td>
            <td style="padding: 12px 16px;"><button type="button" aria-label="Actions for Shreya Sarath" style="font: inherit; width: 40px; height: 40px; border-radius: 8px; border: none; background: transparent; color: #3B4558;">⋯</button></td>
          </tr>
          <tr>
            <td style="padding: 12px 16px;"><input type="checkbox" aria-label="Select Sama Yaser Matar" style="width: 18px; height: 18px;"></td>
            <td style="padding: 12px 8px;"><a href="#" style="font-weight: 600; color: #172033; text-decoration: none;">Sama Yaser Matar</a><div style="font-size: 12px; color: #5B6476;">LBN-SM157105 · Female · 5 · Lebanon · Camel Combat</div></td>
            <td style="padding: 12px 8px;">Mixed 5–6 · <span style="white-space: nowrap;">17–24 kg</span><div style="font-size: 12px; color: #5B6476;">Group A</div></td>
            <td style="padding: 12px 8px; white-space: nowrap; color: #156A3A; font-weight: 500;">✓ 17.3 kg</td>
            <td style="padding: 12px 8px;"><span style="font-size: 12px; padding: 3px 8px; border-radius: 6px; background: #E8F5EE; color: #156A3A;">Approved</span></td>
            <td style="padding: 12px 16px;"><button type="button" aria-label="Actions for Sama Yaser Matar" style="font: inherit; width: 40px; height: 40px; border-radius: 8px; border: none; background: transparent; color: #3B4558;">⋯</button></td>
          </tr>
        </tbody>
      </table>
    </div>
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; font-size: 13px; color: #5B6476;">
      <span>Showing 1–25 of 57 · National IDs are hidden for minors</span>
      <nav aria-label="Pages" style="display: flex; gap: 6px;">
        <a href="#" aria-current="page" style="min-width: 40px; min-height: 40px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; background: #1F2A44; color: #FFFFFF; text-decoration: none;">1</a>
        <a href="#" style="min-width: 40px; min-height: 40px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; border: 1px solid #D0D5DD; color: #172033; text-decoration: none;">2</a>
        <a href="#" style="min-width: 40px; min-height: 40px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; border: 1px solid #D0D5DD; color: #172033; text-decoration: none;">3</a>
      </nav>
    </div>
  </main>
</div>
</x-dc>
<script type="text/x-dc" data-dc-script data-props='{"$preview":{"width":1440,"height":900}}'>
class Component extends DCLogic {
  renderVals() { return {}; }
}
</script>
</body>
</html>
```

### A.DoubleEntries — `DoubleEntries.dc.html`

```html
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Resolve double entries</title>
<script src="./support.js"></script>
</head>
<body>
<x-dc>
<helmet>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
<style>
body{margin:0;font-family:Poppins,system-ui,sans-serif}
a{color:#1F2A44}a:hover{color:#7A5A12}
</style>
</helmet>
<div style="width: 1440px; height: 900px; box-sizing: border-box; background: rgba(23,32,51,0.55); display: flex; align-items: center; justify-content: center; font-family: Poppins, system-ui, sans-serif; font-size: 14px; color: #172033;">
  <section role="dialog" aria-modal="true" aria-labelledby="dlg-title" style="width: 860px; background: #FFFFFF; border-radius: 16px; box-shadow: 0 24px 64px rgba(0,0,0,0.25); display: flex; flex-direction: column; overflow: hidden;">

    <header style="padding: 22px 28px 16px; border-bottom: 1px solid #EEF0F3; display: flex; justify-content: space-between; gap: 16px; align-items: flex-start;">
      <div style="display: flex; flex-direction: column; gap: 4px;">
        <h2 id="dlg-title" style="margin: 0; font-size: 19px; font-weight: 600;">Resolve double entries · 5 athletes</h2>
        <p style="margin: 0; font-size: 13px; color: #5B6476;">One division per person is on. Choose where each athlete competes; they are removed from the other division.</p>
      </div>
      <button type="button" aria-label="Close" style="font: inherit; font-size: 18px; width: 44px; height: 44px; border-radius: 10px; border: none; background: #F2F4F7; color: #3B4558;">✕</button>
    </header>

    <div style="padding: 8px 28px; display: flex; flex-direction: column;">

      <fieldset style="margin: 0; border: none; border-bottom: 1px solid #EEF0F3; padding: 14px 0; display: grid; grid-template-columns: 220px minmax(0, 1fr) minmax(0, 1fr); gap: 12px; align-items: center;">
        <legend style="position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0);">Nasser Mohamed</legend>
        <div><div style="font-weight: 600;">Nasser Mohamed</div><div style="font-size: 12px; color: #5B6476;">15 yrs · 77.6 kg · Shams</div></div>
        <label style="display: flex; gap: 10px; align-items: center; padding: 10px 12px; border: 1px solid #E3E6EC; border-radius: 10px; min-height: 44px; box-sizing: border-box;"><input type="radio" name="nasser" style="width: 18px; height: 18px; accent-color: #1F2A44;"><span style="font-size: 13px;">Group Q · 13–14 yrs · 70.3–77.6 kg<br><span style="color: #8A4B00;">Age outside range</span></span></label>
        <label style="display: flex; gap: 10px; align-items: center; padding: 10px 12px; border: 2px solid #1F2A44; background: #F4F6FA; border-radius: 10px; min-height: 44px; box-sizing: border-box;"><input type="radio" name="nasser" checked style="width: 18px; height: 18px; accent-color: #1F2A44;"><span style="font-size: 13px;">Group T · 14–15 yrs · 77.6 kg<br><span style="color: #156A3A; font-weight: 600;">Suggested · fits age and weight</span></span></label>
      </fieldset>

      <fieldset style="margin: 0; border: none; border-bottom: 1px solid #EEF0F3; padding: 14px 0; display: grid; grid-template-columns: 220px minmax(0, 1fr) minmax(0, 1fr); gap: 12px; align-items: center;">
        <legend style="position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0);">Saden Mohammad</legend>
        <div><div style="font-weight: 600;">Saden Mohammad</div><div style="font-size: 12px; color: #5B6476;">9 yrs · 39.2 kg · Shams</div></div>
        <label style="display: flex; gap: 10px; align-items: center; padding: 10px 12px; border: 1px solid #E3E6EC; border-radius: 10px; min-height: 44px; box-sizing: border-box;"><input type="radio" name="saden" style="width: 18px; height: 18px; accent-color: #1F2A44;"><span style="font-size: 13px;">Group D · 8–9 yrs · 34.8–39.8 kg<br><span style="color: #5B6476;">Fits</span></span></label>
        <label style="display: flex; gap: 10px; align-items: center; padding: 10px 12px; border: 1px solid #E3E6EC; border-radius: 10px; min-height: 44px; box-sizing: border-box;"><input type="radio" name="saden" style="width: 18px; height: 18px; accent-color: #1F2A44;"><span style="font-size: 13px;">Group F · 9–10 yrs · 33.6–39.2 kg<br><span style="color: #5B6476;">Fits</span></span></label>
      </fieldset>

      <fieldset style="margin: 0; border: none; border-bottom: 1px solid #EEF0F3; padding: 14px 0; display: grid; grid-template-columns: 220px minmax(0, 1fr) minmax(0, 1fr); gap: 12px; align-items: center;">
        <legend style="position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0);">سلمان سعد ابراهيم</legend>
        <div><div dir="auto" style="font-weight: 600;">سلمان سعد ابراهيم</div><div style="font-size: 12px; color: #5B6476;">14 yrs · 59.3 kg · Alkaabi</div></div>
        <label style="display: flex; gap: 10px; align-items: center; padding: 10px 12px; border: 1px solid #E3E6EC; border-radius: 10px; min-height: 44px; box-sizing: border-box;"><input type="radio" name="salman" style="width: 18px; height: 18px; accent-color: #1F2A44;"><span style="font-size: 13px;">Group L · 13–14 yrs · 59.3–65.1 kg<br><span style="color: #5B6476;">Fits</span></span></label>
        <label style="display: flex; gap: 10px; align-items: center; padding: 10px 12px; border: 1px solid #E3E6EC; border-radius: 10px; min-height: 44px; box-sizing: border-box;"><input type="radio" name="salman" style="width: 18px; height: 18px; accent-color: #1F2A44;"><span style="font-size: 13px;">Group O · 14–15 yrs · 59.3–65.9 kg<br><span style="color: #5B6476;">Fits</span></span></label>
      </fieldset>

      <fieldset style="margin: 0; border: none; border-bottom: 1px solid #EEF0F3; padding: 14px 0; display: grid; grid-template-columns: 220px minmax(0, 1fr) minmax(0, 1fr); gap: 12px; align-items: center;">
        <legend style="position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0);">Mohamed Moosa Husain</legend>
        <div><div style="font-weight: 600;">Mohamed Moosa Husain</div><div style="font-size: 12px; color: #5B6476;">14 yrs · 70.3 kg · Bahrain Combat</div></div>
        <label style="display: flex; gap: 10px; align-items: center; padding: 10px 12px; border: 1px solid #E3E6EC; border-radius: 10px; min-height: 44px; box-sizing: border-box;"><input type="radio" name="moosa" style="width: 18px; height: 18px; accent-color: #1F2A44;"><span style="font-size: 13px;">Group M · 13–14 yrs · 69–71.35 kg<br><span style="color: #5B6476;">Fits</span></span></label>
        <label style="display: flex; gap: 10px; align-items: center; padding: 10px 12px; border: 1px solid #E3E6EC; border-radius: 10px; min-height: 44px; box-sizing: border-box;"><input type="radio" name="moosa" style="width: 18px; height: 18px; accent-color: #1F2A44;"><span style="font-size: 13px;">Group Q · 13–14 yrs · 70.3–77.6 kg<br><span style="color: #5B6476;">Fits</span></span></label>
      </fieldset>

      <fieldset style="margin: 0; border: none; padding: 14px 0; display: grid; grid-template-columns: 220px minmax(0, 1fr) minmax(0, 1fr); gap: 12px; align-items: center;">
        <legend style="position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0);">Abdulkarim Abdulla</legend>
        <div><div style="font-weight: 600;">Abdulkarim Abdulla</div><div style="font-size: 12px; color: #5B6476;">14 yrs · 77.6 kg · Shams</div></div>
        <label style="display: flex; gap: 10px; align-items: center; padding: 10px 12px; border: 1px solid #E3E6EC; border-radius: 10px; min-height: 44px; box-sizing: border-box;"><input type="radio" name="karim" style="width: 18px; height: 18px; accent-color: #1F2A44;"><span style="font-size: 13px;">Group Q · 13–14 yrs · 70.3–77.6 kg<br><span style="color: #5B6476;">Fits</span></span></label>
        <label style="display: flex; gap: 10px; align-items: center; padding: 10px 12px; border: 1px solid #E3E6EC; border-radius: 10px; min-height: 44px; box-sizing: border-box;"><input type="radio" name="karim" style="width: 18px; height: 18px; accent-color: #1F2A44;"><span style="font-size: 13px;">Group T · 14–15 yrs · 77.6 kg<br><span style="color: #5B6476;">Fits</span></span></label>
      </fieldset>
    </div>

    <div style="margin: 0 28px; padding: 12px 14px; border-radius: 10px; background: #FFF4E5; color: #8A4B00; font-size: 13px;">If all three Group Q athletes move out, Group Q drops from 5 to 2 athletes, below the minimum of 3. You'll be asked how to handle it next.</div>

    <footer style="padding: 18px 28px 22px; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;">
      <span style="font-size: 13px; color: #5B6476;">1 of 5 chosen · Bouts already fought will be flagged for review</span>
      <div style="display: flex; gap: 8px;">
        <button type="button" style="font: inherit; font-size: 14px; padding: 10px 18px; border-radius: 10px; border: 1px solid #D0D5DD; background: #FFFFFF; color: #172033; min-height: 44px;">Cancel</button>
        <button type="button" disabled style="font: inherit; font-size: 14px; font-weight: 600; padding: 10px 18px; border-radius: 10px; border: none; background: #C9CFDA; color: #3B4558; min-height: 44px;">Apply 5 choices</button>
      </div>
    </footer>
  </section>
</div>
</x-dc>
<script type="text/x-dc" data-dc-script data-props='{"$preview":{"width":1440,"height":900}}'>
class Component extends DCLogic {
  renderVals() { return {}; }
}
</script>
</body>
</html>
```

### A.MobileHub — `MobileHub.dc.html`

```html
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Event hub on phone</title>
<script src="./support.js"></script>
</head>
<body>
<x-dc>
<helmet>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
<style>
body{margin:0;font-family:Poppins,system-ui,sans-serif}
a{color:#1F2A44}a:hover{color:#7A5A12}
</style>
</helmet>
<div style="width: 390px; height: 844px; box-sizing: border-box; background: #F5F6F8; color: #172033; font-family: Poppins, system-ui, sans-serif; font-size: 14px; display: flex; flex-direction: column; overflow: hidden;">

  <header style="background: #172033; color: #FFFFFF; padding: 14px 16px; display: flex; align-items: center; gap: 10px;">
    <a href="#" aria-label="Back to events" style="color: #FFFFFF; text-decoration: none; width: 44px; height: 44px; display: inline-flex; align-items: center; justify-content: center; font-size: 22px;">‹</a>
    <button type="button" style="flex: 1; min-width: 0; font: inherit; text-align: left; background: transparent; border: none; color: #FFFFFF; display: flex; flex-direction: column; padding: 0;">
      <span style="font-size: 11px; color: #C9CFDA;">Bahrain Karate Federation ▾</span>
      <span style="font-weight: 600; font-size: 15px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Bahrain Energy Zone Combat 4</span>
    </button>
    <button type="button" aria-label="Event actions" style="font: inherit; width: 44px; height: 44px; border-radius: 10px; border: none; background: rgba(255,255,255,0.1); color: #FFFFFF; font-size: 18px;">⋯</button>
  </header>

  <div style="flex: 1; overflow: hidden; padding: 16px; display: flex; flex-direction: column; gap: 14px;">
    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
      <span style="font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 999px; background: #FFF4E5; color: #8A4B00;">Results · awaiting sign-off</span>
      <span style="font-size: 12px; color: #5B6476;">Fri 16 Oct 2026 · Isa Sports City</span>
    </div>

    <dl style="margin: 0; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px;">
      <div style="background: #FFFFFF; border: 1px solid #E3E6EC; border-radius: 12px; padding: 10px 12px;"><dt style="font-size: 11px; color: #5B6476;">Athletes</dt><dd style="margin: 0; font-size: 20px; font-weight: 600;">57</dd></div>
      <div style="background: #FFFFFF; border: 1px solid #E3E6EC; border-radius: 12px; padding: 10px 12px;"><dt style="font-size: 11px; color: #5B6476;">Divisions</dt><dd style="margin: 0; font-size: 20px; font-weight: 600;">19</dd></div>
      <div style="background: #FFFFFF; border: 1px solid #E3E6EC; border-radius: 12px; padding: 10px 12px;"><dt style="font-size: 11px; color: #5B6476;">Weighed in</dt><dd style="margin: 0; font-size: 20px; font-weight: 600;">57</dd></div>
    </dl>

    <a href="#" style="display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-radius: 12px; background: #FDECEC; color: #A12020; text-decoration: none; min-height: 56px; box-sizing: border-box;">
      <span style="flex: 1; display: flex; flex-direction: column;"><span style="font-weight: 600;">6 things need attention</span><span style="font-size: 12px;">5 double entries · 4 small divisions · …</span></span>
      <span aria-hidden="true" style="font-size: 18px;">›</span>
    </a>

    <nav aria-label="Event sections" style="background: #FFFFFF; border: 1px solid #E3E6EC; border-radius: 14px; display: flex; flex-direction: column;">
      <a href="#" style="display: flex; align-items: center; gap: 12px; padding: 0 16px; min-height: 56px; color: #172033; text-decoration: none; border-bottom: 1px solid #EEF0F3;"><span style="flex: 1; font-weight: 500;">Participants</span><span style="color: #5B6476;">57</span><span aria-hidden="true" style="color: #5B6476;">›</span></a>
      <a href="MobileDraw.dc.html" style="display: flex; align-items: center; gap: 12px; padding: 0 16px; min-height: 56px; color: #172033; text-decoration: none; border-bottom: 1px solid #EEF0F3;"><span style="flex: 1; font-weight: 500;">Draw</span><span style="font-size: 12px; font-weight: 600; padding: 2px 8px; border-radius: 6px; background: #FDECEC; color: #A12020;">5 conflicts</span><span aria-hidden="true" style="color: #5B6476;">›</span></a>
      <a href="#" style="display: flex; align-items: center; gap: 12px; padding: 0 16px; min-height: 56px; color: #172033; text-decoration: none; border-bottom: 1px solid #EEF0F3;"><span style="flex: 1; font-weight: 500;">Results</span><span style="color: #5B6476;">19</span><span aria-hidden="true" style="color: #5B6476;">›</span></a>
      <a href="#" style="display: flex; align-items: center; gap: 12px; padding: 0 16px; min-height: 56px; color: #172033; text-decoration: none; border-bottom: 1px solid #EEF0F3;"><span style="flex: 1; font-weight: 500;">Schedule</span><span style="color: #5B6476;">43 bouts</span><span aria-hidden="true" style="color: #5B6476;">›</span></a>
      <a href="#" style="display: flex; align-items: center; gap: 12px; padding: 0 16px; min-height: 56px; color: #172033; text-decoration: none; border-bottom: 1px solid #EEF0F3;"><span style="flex: 1; font-weight: 500;">Officials</span><span style="color: #5B6476;">9</span><span aria-hidden="true" style="color: #5B6476;">›</span></a>
      <a href="#" style="display: flex; align-items: center; gap: 12px; padding: 0 16px; min-height: 56px; color: #172033; text-decoration: none; border-bottom: 1px solid #EEF0F3;"><span style="flex: 1; font-weight: 500;">Clubs</span><span style="color: #5B6476;">7</span><span aria-hidden="true" style="color: #5B6476;">›</span></a>
      <a href="#" style="display: flex; align-items: center; gap: 12px; padding: 0 16px; min-height: 56px; color: #172033; text-decoration: none;"><span style="flex: 1; font-weight: 500;">Financials</span><span style="font-size: 12px; color: #8A4B00;">BHD 570 due</span><span aria-hidden="true" style="color: #5B6476;">›</span></a>
    </nav>
  </div>

  <nav aria-label="Main" style="background: #FFFFFF; border-top: 1px solid #E3E6EC; display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); padding: 6px 4px 18px;">
    <a href="#" style="min-height: 52px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px; text-decoration: none; color: #4A5468; font-size: 11px;"><svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"></path></svg>Stats</a>
    <a href="#" aria-current="page" style="min-height: 52px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px; text-decoration: none; color: #172033; font-weight: 600; font-size: 11px;"><svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#A8781F" stroke-width="1.8" stroke-linecap="round"><rect x="3" y="5" width="18" height="16" rx="2"></rect><path d="M3 10h18M8 3v4M16 3v4"></path></svg>Events</a>
    <a href="#" style="min-height: 52px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px; text-decoration: none; color: #4A5468; font-size: 11px;"><svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="7" r="4"></circle><path d="M4 21c1-4 4-6 8-6s7 2 8 6"></path></svg>Athletes</a>
    <a href="#" style="min-height: 52px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px; text-decoration: none; color: #4A5468; font-size: 11px;"><svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M3 21h18M5 21V9l7-5 7 5v12M9 21v-6h6v6"></path></svg>Clubs</a>
    <button type="button" style="font: inherit; min-height: 52px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px; border: none; background: transparent; color: #4A5468; font-size: 11px;"><svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"></path></svg>More</button>
  </nav>
</div>
</x-dc>
<script type="text/x-dc" data-dc-script data-props='{"$preview":{"width":390,"height":844}}'>
class Component extends DCLogic {
  renderVals() { return {}; }
}
</script>
</body>
</html>
```

### A.MobileDraw — `MobileDraw.dc.html`

```html
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Draw on phone</title>
<script src="./support.js"></script>
</head>
<body>
<x-dc>
<helmet>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
<style>
body{margin:0;font-family:Poppins,system-ui,sans-serif}
a{color:#1F2A44}a:hover{color:#7A5A12}
</style>
</helmet>
<div style="width: 390px; height: 844px; box-sizing: border-box; background: #F5F6F8; color: #172033; font-family: Poppins, system-ui, sans-serif; font-size: 14px; display: flex; flex-direction: column; overflow: hidden;">

  <header style="background: #FFFFFF; border-bottom: 1px solid #E3E6EC; padding: 10px 12px; display: flex; align-items: center; gap: 8px;">
    <a href="MobileHub.dc.html" aria-label="Back to event" style="color: #172033; text-decoration: none; width: 44px; height: 44px; display: inline-flex; align-items: center; justify-content: center; font-size: 22px;">‹</a>
    <h1 style="flex: 1; margin: 0; font-size: 17px; font-weight: 600;">Draw · 19 divisions</h1>
    <button type="button" style="font: inherit; font-size: 13px; min-height: 44px; padding: 0 12px; border-radius: 10px; border: 1px solid #D0D5DD; background: #FFFFFF; color: #172033;">Filters · 1</button>
  </header>

  <div style="flex: 1; overflow: hidden; padding: 12px 16px; display: flex; flex-direction: column; gap: 10px;">
    <a href="#" style="display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 12px; background: #FDECEC; color: #A12020; text-decoration: none; min-height: 48px; box-sizing: border-box; font-size: 13px;"><span style="flex: 1;"><strong>5 athletes in two divisions.</strong> Choose where they compete.</span><span aria-hidden="true">›</span></a>

    <div role="group" aria-label="Gender" style="display: flex; gap: 6px; flex-wrap: wrap;">
      <button type="button" aria-pressed="true" style="font: inherit; font-size: 13px; min-height: 40px; padding: 0 14px; border-radius: 999px; border: none; background: #1F2A44; color: #FFFFFF;">All</button>
      <button type="button" aria-pressed="false" style="font: inherit; font-size: 13px; min-height: 40px; padding: 0 14px; border-radius: 999px; border: 1px solid #D0D5DD; background: #FFFFFF; color: #172033;">Boys</button>
      <button type="button" aria-pressed="false" style="font: inherit; font-size: 13px; min-height: 40px; padding: 0 14px; border-radius: 999px; border: 1px solid #D0D5DD; background: #FFFFFF; color: #172033;">Girls</button>
      <button type="button" aria-pressed="false" style="font: inherit; font-size: 13px; min-height: 40px; padding: 0 14px; border-radius: 999px; border: 1px solid #D0D5DD; background: #FFFFFF; color: #172033;">Mixed</button>
    </div>

    <a href="#" style="display: flex; flex-direction: column; gap: 4px; padding: 14px; background: #FFFFFF; border: 1px solid #F1D3A6; border-radius: 12px; text-decoration: none; color: #172033;">
      <span style="display: flex; justify-content: space-between; gap: 8px;"><span style="font-weight: 600;">Group A · Mixed 5–6</span><span style="font-size: 12px; font-weight: 600; padding: 2px 8px; border-radius: 6px; background: #FFF4E5; color: #8A4B00;">Below minimum</span></span>
      <span style="font-size: 13px; color: #5B6476;">17–24 kg · 2 athletes · choose merge, exhibition or withdraw</span>
    </a>
    <a href="#" style="display: flex; flex-direction: column; gap: 4px; padding: 14px; background: #FFFFFF; border: 1px solid #F1D3A6; border-radius: 12px; text-decoration: none; color: #172033;">
      <span style="display: flex; justify-content: space-between; gap: 8px;"><span style="font-weight: 600;">Group B · Boys 8–9</span><span style="font-size: 12px; font-weight: 600; padding: 2px 8px; border-radius: 6px; background: #FFF4E5; color: #8A4B00;">Below minimum</span></span>
      <span style="font-size: 13px; color: #5B6476;">22.7–24.5 kg · 2 athletes</span>
    </a>
    <a href="#" style="display: flex; flex-direction: column; gap: 4px; padding: 14px; background: #FFFFFF; border: 1px solid #E3E6EC; border-radius: 12px; text-decoration: none; color: #172033;">
      <span style="display: flex; justify-content: space-between; gap: 8px;"><span style="font-weight: 600;">Group C · Boys 8–9</span><span style="font-size: 12px; font-weight: 600; padding: 2px 8px; border-radius: 6px; background: #E8F5EE; color: #156A3A;">Completed</span></span>
      <span style="font-size: 13px; color: #5B6476;">27.1–31.5 kg · 4 athletes</span>
    </a>
    <a href="#" style="display: flex; flex-direction: column; gap: 4px; padding: 14px; background: #FFFFFF; border: 1px solid #E3E6EC; border-radius: 12px; text-decoration: none; color: #172033;">
      <span style="display: flex; justify-content: space-between; gap: 8px;"><span style="font-weight: 600;">Group D · Mixed 8–9</span><span style="font-size: 12px; font-weight: 600; padding: 2px 8px; border-radius: 6px; background: #E8F5EE; color: #156A3A;">Completed</span></span>
      <span style="font-size: 13px; color: #5B6476;">34.8–39.8 kg · 4 athletes · 1 moved up</span>
    </a>
  </div>

  <div style="background: #FFFFFF; border-top: 1px solid #E3E6EC; padding: 10px 16px 22px; display: flex; gap: 8px;">
    <button type="button" style="flex: 1; font: inherit; font-size: 14px; font-weight: 600; min-height: 48px; border-radius: 10px; border: none; background: #1F2A44; color: #FFFFFF;">Auto-place</button>
    <button type="button" style="flex: 1; font: inherit; font-size: 14px; min-height: 48px; border-radius: 10px; border: 1px solid #D0D5DD; background: #FFFFFF; color: #172033;">Merge 4</button>
    <button type="button" aria-label="More draw actions" style="font: inherit; width: 48px; min-height: 48px; border-radius: 10px; border: 1px solid #D0D5DD; background: #FFFFFF; color: #172033; font-size: 18px;">⋯</button>
  </div>
</div>
</x-dc>
<script type="text/x-dc" data-dc-script data-props='{"$preview":{"width":390,"height":844}}'>
class Component extends DCLogic {
  renderVals() { return {}; }
}
</script>
</body>
</html>
```

---

## Appendix B — Untranslated interface strings in Arabic mode (from the hands-on test)

- **Shell:** "Sub-federations", the breadcrumb "BAHRAIN COMBAT SPORTS COUNCIL", "Federation Manager", the document title.
- **Statistics:**
  - Header and filters: "· Season", "Generate report", "Date range", "Full season", "Competition", "All competitions", "Discipline", "Age category", "All clubs", "Region", "All regions".
  - KPI cards: "No previous season to compare", "registered in total", "+16 new clubs this season", "Competitions this season", "0 upcoming · 2 completed", gold/silver/bronze, "Officials named", "16 referees · 12 managers".
  - "By sub-federation" with its description and column headers; "Its own records".
  - Charts: "Participation", "Athlete Participation Growth", range chips, chart legends and month names, "Athlete Composition".
  - Clubs: Sort / Score / Gold / Wins / Performance / Trend, "View all clubs (16)", "Club activity", "Activity distribution", "Highly active / Low activity / Inactive".
  - Competition analytics: "Competition Analytics", "Divisions drawn", "Bouts recorded", "Operational performance", "Season timeline", "Today".
  - Kumite / Kata analytics, "Medal table", "Effic.", "Bank transfers of BHD 695.000 to confirm".
- **Events:** "Combat sports season 2026", "+ Plan new event", "Active events", "3 total this season", "0 approvals pending", "no ticket sales yet", "Season revenue", "no revenue recorded yet", "All events", All / Upcoming / Completed, the search placeholder, "Event", "Athletes registered".
- **Athletes:** "Registered directory", "0 pending approval", "1 countries", "Via club invitations", "registered through the link", "All · 180", "Import athlete file", the search placeholder, "N years". "رقم Takeone" is half translated.
- **Clubs:** "Clubs", "+ Add club", "in the federation directory", "Registered", "affiliation approved", "Pending approval", "applied via the invitation link", "Athletes on rosters", "across 18 clubs", "All clubs", "18 shown", Cards / Table, "Coach ·", "No email/phone/address on file", "athletes on the roster", "Manage", "Review".
- **Calendar:** "Season schedule", month names, Mon…Sun, "This month", "No events scheduled this month."
- **Settings:**
  - The logo help text, 24-hour / 12-hour, UTC.
  - Sub-bodies: the description, "Karate · 0 athletes · 5 sub-bodies", "+ Sub", "Billed to …".
  - Team: "(you)", "All sports", "Events Manager".
  - Billing: "Current plan", "Per sport · 1 sport", "Billed monthly…", "/ month", "Next payment", "Payment due", "Annual · 2 months free", "Up to 300 athletes…", "Unlimited…", "Annual = 10 months", "Due now", "Subscriptions are paid by bank transfer."
- **Event header:** "← All events", "⤓ Report", "🔗 Invite clubs", "unlimited", "Check-ins", and the "Clubs" and "Results" tabs.
- **Overview:** "Readiness checklist", "Athletes entered", "57 from 7 clubs", "19 divisions", "Mixed", "57 of 57 weighed in", "19 of 19 divisions drawn", "Officials appointed", "Referees", "Team managers", "Referee (Shushin)", "Registrations by division".
- **Participants:** "Athlete registrations", "By weight category", "Printables", "Download", "Import from Excel", "+ Enroll athlete", "No picture", "Ages", "N years".
- **Draw:**
  - Header and actions: "Weight classes", "Entries", "Ready to draw", "One division per person: On", "Flags: Nationality", "⤓ Draw PDF", "Clear divisions", "✦ Auto-place", "⇄ Merge", "+ Add".
  - Rows: "Mixed", "Running", "✎ Edit", and the row sub-lines.
- **Results:** "Live from the mats", "Results & bouts", "Cover", "Download", "Results · 19", "Bouts · 43", "Draw ›", "Champion", "Silver", "Bronze".
- **Schedule:**
  - Header and controls: "Running order", the date line, the drag-help sentence, "Day + Mats / Day / Mats", "✦ Auto-schedule", "Renumber bouts", "+ Breaks, groups & times ↗".
  - Day summary: "Starts", "✎ Change", "Ends", "+1 day", "On the mats", "Lunch", "None", the past-midnight warning, "More mats finish the day sooner:", "Change mats", "20 divisions · 2 with no entrants".
  - Running-order labels: "Starter / Group / One class at a time / Round by round / Manual", "entrants / bouts / Awards / Semis", "Untitled part", "No entrants yet — nothing will run", "+ New group", "Mat 1", "numbered by hand".
  - Competition schedule: "Competition schedule", "No schedule yet", "Add the first day".
