# TakeOne — Federation Events Platform (prototype)

This package contains the working prototype of the TakeOne federation dashboard and the public club invitation page, plus all supporting code. This README explains **how to run it, how the code is organised, and how each part works**. For the product rules the production platform must follow, read `PLATFORM_SPEC.md`.

---

## 1. Quick start

1. Unzip the package.
2. Serve the folder with any static web server (scripts and relative files do not load reliably from `file://`):
   ```bash
   npx serve .
   # or
   python3 -m http.server 8080
   ```
3. Open `Events Dashboard.dc.html` in the browser.
4. Sign in with a demo account (password for all: **Fed@2026**):

| Email | Role | Sees |
|---|---|---|
| `sara@federation.bh` | Federation Manager, top-level federation | All federations, all sports, Settings |
| `karate@federation.bh` | Karate Events Officer | Karate only, no Settings |
| `tkd@federation.bh` | Taekwondo Coordinator | Taekwondo only, no Settings |

5. Open the club side from the dashboard (**Invite clubs → Open the invitation page**) or directly: `Club Invitation.dc.html`. Add `?club=c-bku` to open it as a registered club without a verification code.

To start fresh, clear this site's data in the browser (DevTools → Application → Local storage → Clear). All demo data is re-seeded on next load.

---

## 2. Files

| File | Purpose |
|---|---|
| `Events Dashboard.dc.html` | Federation staff dashboard: login, events, registrations, divisions, draw and brackets, athletes, transfers, statistics, settings, billing, staff, translations. |
| `Club Invitation.dc.html` | Public, mobile-first invitation page: welcome, rules, club identification, athletes, review and payment, confirmation. English / Arabic (RTL). |
| `Club Invitation v1.dc.html` | Earlier single-page version of the invitation, kept for reference. |
| `Karate Statistics.dc.html` | Stand-alone copy of the statistics page (the same page is embedded in the dashboard). |
| `federation-store.js` | Shared data layer used by both pages. All reads and writes go through `window.FedStore`. |
| `i18n-dom.js` | Applies stored translations from the strings table to the whole page. |
| `translations.seed.js` | Initial strings-table rows (English key → Arabic, French). |
| `image-slot.js` | Drag-and-drop image placeholder web component (invitation hero artwork). |
| `support.js` | Runtime that renders `.dc.html` files (templates + logic). Must sit next to the pages. |
| `PLATFORM_SPEC.md` | Behaviour specification: roles, billing, events, karate weight rules, invitation flow, transfers, data rules. |
| `uploads/` | Source material supplied during design, including the 2025–2026 karate age/weight PDF. |

---

## 3. How a `.dc.html` page works

Each page is a single HTML file with three parts:

1. **Template** — inside `<x-dc>…</x-dc>`. Plain HTML with inline styles and `{{ name }}` holes.
   - `{{ user.name }}` inserts a value.
   - `onClick="{{ handler }}"` binds an event.
   - `<sc-for list="{{ rows }}" as="r">…</sc-for>` repeats markup.
   - `<sc-if value="{{ flag }}">…</sc-if>` shows markup conditionally.
   - `<helmet>` at the top holds fonts, `@keyframes` and external `<script>` tags.
2. **Logic** — `<script type="text/x-dc" data-dc-script>` containing `class Component extends DCLogic { … }`. It works like a React class component:
   - `state = {…}` holds UI state.
   - `renderVals()` returns every value and handler the template uses. Holes cannot contain expressions, so all computing happens here.
   - `componentDidMount()` / `componentWillUnmount()` set up listeners, timers and initial loads.
3. **Props** — the `data-props` attribute on the script tag lists tweakable settings (for example `demoState` on the statistics page).

`support.js` reads the file, renders the template with React, and re-renders whenever `setState` is called.

**Porting to a real front end:** each template maps directly to JSX, and each `renderVals()` becomes the body of a component's render. Inline styles can be moved into your CSS system unchanged.

---

## 4. Data layer — `federation-store.js`

Both pages share data through `window.FedStore`. In the prototype it stores JSON in `localStorage` and fires a `fedstore-change` event on every write, so an open dashboard updates when a club submits from another tab.

**In production, replace the body of each function with an API call. Keep the same function names and the pages will not need changes.**

### 4.1 Keys (prototype storage → production tables)

| localStorage key | Production table | Contents |
|---|---|---|
| `takeone_clubs` | `clubs` | id, name, coach, email, phone, address, logo, status |
| `takeone_athletes` | `athletes` | Takeone ID, national ID, name, gender, DOB, nationality, photo, clubId, history[] |
| `takeone_transfers` | `transfers` | athleteId, fromClubId, toClubId, status, old-club reply, dates |
| `takeone_club_regs` | `entries` / `registrations` | per-club submissions: officials, athletes, weights, types, totals |
| `takeone_strings` | `strings` | `{ "English text": { ar, fr, updated } }` |
| `takeone_string_keys` | `strings` (missing rows) | English texts seen on screen with no row yet |
| `fed-orgs` | `organisations` | federation tree: parentId, sports, athletes per sport, payer, status |
| `fed-users` | `users` | staff accounts: orgId, sports[], role, TOTP secret, backup codes |
| `fed-billing:<orgId>` | `subscriptions` | per billing account: cycle, status, card (brand + last 4), invoices |
| `fed-region` | `organisation_settings` | country, time zone, currency |
| `fed-lang`, `fed-lang-country` | user preference | chosen UI language and flag |
| `takeone_event_sport`, `takeone_event_currency` | `events` | sport and currency of the event shown on the invitation page |

### 4.2 Main functions

- **Clubs:** `clubs()`, `club(id)`, `findClub(emailOrPhone)`, `saveClub(obj)`
- **Athletes:** `athletes()`, `roster(clubId)`, `athlete(id)`, `byNatId(nationalId)`, `lookup(query)`, `saveAthlete(obj)` (assigns the Takeone ID), `nextId(iso3)`
- **Transfers:** `requestTransfer(athleteId, toClubId, byName)`, `oldClubReply(id, released)`, `decideTransfer(id, approve)` (moves the athlete and writes club history)
- **Event:** `event()` (name, sport, currency, dates, fees), `eventDocs()` / `setEventDocs(list)`
- **Strings:** `strings()`, `stringKeys()`, `registerStrings(keys)`, `saveString(key, lang, value)`, `importStrings(json)`
- **Testing:** `reset()` restores the seed data

---

## 5. Dashboard — how each area works

### 5.1 Sign-in and 2FA
- `authUsers()` loads staff accounts; `curUser()` is the signed-in one.
- "Keep me signed in" stores the session in `localStorage`; otherwise in `sessionStorage`.
- 2FA uses TOTP (RFC 6238). The secret is generated in the browser, the QR code encodes an `otpauth://` link, and codes are checked with Web Crypto HMAC-SHA1, accepting ±1 time step. Eight single-use backup codes are created.
- **Production:** hash passwords, rate-limit sign-in, verify TOTP on the server, and generate the QR code server-side.

### 5.2 Federation tree, scope and switcher
- `orgs()` returns the tree. `orgLevel`, `orgPath` and `subtree` walk it.
- `scopeRoot()` is the signed-in user's own organisation. The switcher and every list only show `subtree(scopeRoot())`.
- `visibleSports()` = sports of the current organisation and its children, intersected with the user's `sports[]` when set.
- Sport-limited users do not see Settings.

### 5.3 Billing (TakeOne → federations)
- `billAccount(org)` walks up to the nearest body that pays for itself.
- `billLines(account, cycle)` groups every sport in that account, sums athletes per sport, and applies `sportTier()` (≤300 BHD 45, ≤1,500 BHD 95, unlimited BHD 180 per month; annual = 10 × monthly).
- Card payment validates the number (Luhn check), expiry and security code, then simulates processing. Bank transfer creates a pending invoice with a reference number.
- **Production:** connect a payment provider (Tap, BenefitPay, Stripe). Never store full card numbers.

### 5.4 Events
- The Create and Edit forms share one component: details, sport dropdown (limited to `visibleSports()`), competition types, capacity (unlimited by default), rich-text About, schedule, map pin (Leaflet + OpenStreetMap), logo and artwork croppers (9:16 and 16:9), titled documents, fees, tickets, requirements.
- **Rich-text editor:** a `contenteditable` area driven by `document.execCommand` for formatting, with an emoji picker (flags use image files so they show on Windows).
- **Import & format:** reads DOCX (mammoth), PDF (pdf.js), XLSX (SheetJS) or TXT in the browser, asks the AI helper to restructure the text into allowed HTML tags, cleans the result with `cleanHtml()`, shows a preview, then replaces or appends. If the AI is unavailable, `basicFormat()` is used instead.

### 5.5 Registrations, divisions, draw
- Registrations come from `takeone_club_regs`. Divisions are built only from registered athletes.
- **Karate only:** `karateWeights()` holds the 2025–2026 age categories and kumite weight classes. Other sports are not auto-classified.
- **Fair draw:** athletes are seeded so members of the same club land in different halves and quarters; byes fill the bracket to a power of two.
- **Results:** clicking a match opens a result form with winner and method; the winner advances to the next round.

### 5.6 Athletes and transfers
- The Athletes page lists athletes with the Takeone ID first, a 3:4 photo, contact details, national ID and sports with grades. Clicking a row opens the profile with club history from the store.
- **Club transfers** lists requests from the invitation page. The federation records the old club's release, then approves or declines. Approving moves the athlete and closes the previous club-history entry.

### 5.7 Notifications, messages, top bar
- Notifications are built from live data: pending approvals, new club submissions, transfer requests, trial status, pending bank transfer, 2FA off.
- Messages open as floating chat heads that can be dragged, and dismissed by dropping them on the target at the bottom of the screen.
- The top bar has the federation switcher, a language flag, messages, notifications and the profile menu.

### 5.8 Settings
- **Branding:** logo and name per organisation.
- **Country, time & currency:** choosing a country fills in its time zone and currency. The live clock shows 24-hour and 12-hour time plus UTC. **Sync clock** compares the device clock with the server `Date` header and shows the corrected time if the device is off.
- **Federation structure:** create sub-bodies (3 levels maximum), set who pays, suspend or reactivate.
- **Staff & access:** invite staff to an organisation and optionally limit them to certain sports.
- **Subscription, payment method, invoices.**
- **Translations:** edit the strings table (see section 7).

### 5.9 Statistics
A light-theme analytics page inside the dashboard: KPI cards, participation growth chart, composition donut, club ranking, competitions, separate kata and kumite panels, medals and rankings, map by region, federation health, officials, insights. **All figures are mock data** until connected to real records.

---

## 6. Club invitation — how it works

1. **Welcome:** event artwork, name, dates, venue and a language switch (English / Arabic, RTL).
2. **Rules:** one formatted rules document, the organiser's documents to download, and acceptance checkboxes. You cannot continue without accepting.
3. **Club:**
   - A registered club verifies with its email or phone and a 6-digit code (shown on screen in the prototype), or opens its personal link `?club=<id>`.
   - A new club fills in its details and is saved as Pending for federation approval.
   - Every club names a team manager and two referees, each with a 3:4 photo.
   - Phone number and country code are one combined field.
4. **Athletes:**
   - Tick athletes from the club's roster and enter today's weight.
   - Athletes from another club: look up by national ID or Takeone ID and request a transfer.
   - New athletes: national ID (duplicates are blocked), nationality with flag, optional photo with a cropper (pan, zoom, tilt, face guide).
   - For Karate events, each athlete's category appears as the details are entered.
5. **Review & pay:** entries grouped by category, fees per athlete and participation type, one payment for the whole club.
6. **Done:** a reference number. The coach can return with the same link to add or withdraw athletes until the deadline.

---

## 7. Translations (strings table)

- Every UI text is a row: the English text is the key, with one value per language (`ar`, `fr`).
- `i18n-dom.js` walks the page, replaces text nodes and `placeholder` / `title` / `aria-label` attributes that have a stored translation, and watches for new content with a `MutationObserver`. Texts without a translation stay in English.
- Texts seen on screen with no row are registered as **Missing** and appear in **Settings → Translations**, where staff type the translation. Export and Import JSON allow bulk editing.
- Names, numbers, IDs, emails, phone numbers and amounts are never translated. Areas marked `data-no-tr` (the rich-text editor, the translations table) are skipped.
- A safety stop pauses live translation if the page changes too fast.
- `translations.seed.js` provides about 200 starting rows.
- **Production:** keep `strings` as a database table (key, language, value, updated_by, updated_at) and serve the rows for the user's language on page load.

---

## 8. External libraries (loaded from CDNs)

| Library | Used for |
|---|---|
| React (via `support.js`) | rendering |
| Leaflet 1.9.4 + OpenStreetMap / CARTO tiles | event location map, statistics map |
| mammoth 1.6 | reading `.docx` files |
| pdf.js 3.11 | reading PDF files |
| SheetJS 0.20 | reading Excel files |
| flagcdn.com | flag images |
| api.qrserver.com | 2FA QR code (prototype only; generate server-side in production) |
| Google Fonts — Poppins | typography |

---

## 9. Moving to production — checklist

- [ ] Replace `federation-store.js` internals with API calls; keep the function signatures.
- [ ] Server authentication: hashed passwords, sessions, rate limits, TOTP verification.
- [ ] Send verification codes, staff invitations and transfer notices by SMS and email.
- [ ] Connect a payment provider for subscriptions and club entry fees.
- [ ] Store uploads (photos, logos, artwork, documents) in object storage with signed URLs.
- [ ] Load the strings table from the database; review every Arabic and French row.
- [ ] Use the organisation's saved time zone for every date and time displayed.
- [ ] Connect the Statistics page to real records.
- [ ] Restrict delete, bulk delete and clear-all to managers, and log every change.
- [ ] Add a page where the previous club can release or object to a transfer.

---

## 10. Known limits of the prototype

- All data lives in the browser. Another browser or computer starts with the demo seed.
- Codes, invitations and payments are simulated.
- Documents over 2.5 MB are not passed from the dashboard to the invitation page.
- Only the club-history timeline on the athlete profile reads from the shared store; the athletes directory list still uses its built-in sample.
- The AI document import needs the built-in AI helper; outside this environment it falls back to basic formatting.
