# TakeOne — Federation Events Platform
Behaviour specification for developers. This describes how the prototype in this folder behaves and how the production platform must behave. Where the prototype simulates something (payments, SMS, file storage), the production requirement is stated.

---

## 1. Files in this package

| File | What it is |
|---|---|
| `Events Dashboard.dc.html` | Federation staff dashboard (login required). All admin screens. |
| `Club Invitation.dc.html` | Public invitation page that clubs open from a link to register athletes. |
| `federation-store.js` | Shared data layer (clubs, athletes, transfers, entries, invoices). In the prototype it persists to `localStorage`; in production it becomes the API. |
| `support.js` | Runtime that renders the `.dc.html` pages. Keep it next to the pages. |
| `image-slot.js` | Drag-and-drop image placeholder used by the invitation hero. |
| `uploads/` | Reference files supplied during design (e.g. the 2025–2026 age/weight PDF). |

Open the `.html` files through a local web server (e.g. `npx serve .`) rather than double-clicking, so scripts and relative files load.

---

## 2. Roles and access

### 2.1 Organisations (federation tree)
- Up to **3 levels**: Top-level federation → Sub-federation → Sub-sub-federation.
- Each body has: name, logo (circle or rectangle), parent, list of **sports**, athlete count per sport, status (`Active` / `Suspended`), and **payer** (`self` or `parent`).
- A parent can: see all events and statistics of bodies under it, create and suspend sub-bodies, change who pays for them, manage their staff.
- Suspended bodies lose access and are not billed.

### 2.2 Staff accounts
- Each staff account belongs to **one organisation** and optionally a **list of sports**.
- A user sees their organisation **and everything below it**, never above or beside it.
- If `sports` is set, the user sees **only those sports** everywhere: events list, event sport dropdown, athletes, statistics, registrations, billing lines. Sport-limited users do **not** see Settings (billing, structure, staff).
- Managers (no sport limit) see Settings → Branding, Federation structure, Staff & access, Subscription, Payment method, Invoices.
- Adding staff sends an invitation email with a one-time sign-in link (prototype shows a temporary password).

### 2.3 Sign-in and 2FA
- Email + password. "Keep me signed in" = persistent session; otherwise session ends when the browser closes.
- Optional TOTP 2FA (RFC 6238, 30 s, 6 digits, SHA-1) with a QR code (`otpauth://`) and **8 single-use backup codes**.
- Production: password hashing, rate limiting, and TOTP verification on the server. QR must be generated server-side.

---

## 3. SaaS billing (TakeOne → federations)
- Charged **per sport, per billing account**.
- **Billing account** = the nearest body (self or ancestor) whose payer is `self`. A top-level body always pays for itself.
- A sport is charged **once per billing account**, however many bodies in that account run it. Athlete counts for that sport are **summed** across the account to pick the tier.
- Tiers (placeholder prices, per sport per month): up to 300 athletes BHD 45 · up to 1,500 BHD 95 · unlimited BHD 180. Annual = 10 × monthly.
- Bodies billed to a parent see the parent's lines read-only and cannot pay.
- 14-day free trial per billing account. Status: `trial`, `active`, `pending` (bank transfer), `due`.
- Payment methods: card (Luhn + expiry + CVC check; only brand + last 4 stored) and bank transfer with invoice number as reference.
- Production: integrate a PSP (e.g. Tap, BenefitPay, Stripe). Never store full card data.

---

## 4. Events

### 4.1 Create / edit event (same form)
Details: name, **sport** (dropdown limited to the user's visible sports), competition types for that sport (checkboxes, one or more), capacity (**Unlimited** by default, or a number).
About: rich-text editor (headings, bold/italic/underline, lists, quote, links, colours, emoji picker) with **Import & format**: Word/PDF/Excel/TXT is read in the browser, sent to an AI model that restructures it into the editor's formatting without inventing content, previewed, then replace or append.
Schedule: enrollment opens, registration deadline, weigh-in (date + time), draw date, start and end (date + time), number of mats, optional daily break.
Location: map pin (lat/long) with a location text below the map, optional Google Maps link.
Branding: event logo (circle/rect cropper), portrait artwork **9:16**, landscape artwork **16:9**.
Documents: multiple files, each with a title. Shown to clubs on the invitation rules page.
Entry & tickets: free or paid; fee lines; late fee and its start date; spectator tickets.
Requirements: free-text list.

### 4.2 Registrations, divisions, draw
- Registrations arrive from the club invitation page and appear in the event with Pending/Approved status.
- Divisions are built **only from registered athletes** (never from an empty template).
- Draw filters (gender, age category, division) list only categories that have entrants.
- Brackets are single-elimination with byes. **Fair seeding**: athletes from the same club are placed in different halves/quarters so they cannot meet before the latest possible round.
- Results: open a match card, pick the winner and method (points, senshu, hantei, kiken, hansoku, etc.); the winner advances automatically.

---

## 5. Weight categories — KARATE ONLY
The automatic classification uses the **2025–2026 karate age/weight table** (source PDF in `uploads/`). It applies **only when the event sport is Karate**. Other martial arts have different tables and must not use it.

Age categories (age on first competition day): Mini poussins 4–5 · Poussins 6–7 · Pupilles 8–9 · Benjamins 10–11 · Minimes 12–13 · Cadets 14–15 · Juniors 16–17 · Séniors 18–34 · Vétérans 1–5 (35+).

Kumite weight classes by gender and age category are defined in `karateWeights()` (dashboard) and `classifyKarate()` (invitation). Result label format: `Karate · Male · Cadets · Kumite -63 kg`.

For any other sport the invitation page does **not** auto-classify; the federation assigns categories after weigh-in. To add a sport, add a separate table and a `classify<Sport>()` function — never extend the karate table.

---

## 6. Club invitation page (public link)

Mobile-first, with a separate desktop layout. Language selector (English / Arabic, RTL).

1. **Welcome**: event artwork, name, dates, venue, Start registration.
2. **Rules**: one formatted rules document + organiser documents (download) + acceptance checkboxes. Cannot continue without accepting.
3. **Club**:
   - "Is your club already registered?"
   - *Yes*: enter club email or phone → 6-digit code → verified; club details and roster load. A club's **personal link** (`?club=<id>`) skips the code.
   - *No*: new club form (logo, name, coach, phone with country code as one field, email, address). Saved as Pending for federation approval.
   - **Team officials** (required): team manager + 2 referees, each with name and 3:4 photo; referees with optional license number.
4. **Athletes**:
   - Registered clubs tick athletes from their roster and enter **today's weight**.
   - Athletes from another club: look up by national ID or Takeone ID → **Request transfer** (see §7).
   - New athletes: name, national ID/passport, gender, date of birth, weight, nationality (flag + ISO3 + name, searchable, preselected from location), optional 3:4 photo with cropper (camera or file; pan, zoom, tilt; face guide).
   - A national ID that already exists cannot be added as new.
   - Karate events show the live category next to each athlete.
5. **Review & pay**: summary grouped by category, per-athlete fees by participation type, team entries, late fee, one payment for the whole club.
6. **Done**: reference number; the coach can come back with the same link to add or withdraw athletes until the deadline (late fee after the deadline; locked after the draw). Withdrawals before the deadline are credited.

---

## 7. Athlete identity and club transfers
- Every athlete has one permanent **Takeone ID** = nationality ISO3 + running number (e.g. `BHR-00021`), and a national ID that is unique across the platform.
- An athlete belongs to one club at a time and keeps a **club history** (club, from, to).
- Transfer: new club requests → old club is notified (release / object, 7-day window) → federation approves or declines on Athletes → Club transfers. On approval the athlete moves and history is updated.
- Until approved, the athlete cannot be entered for the new club.

---

## 8. Dashboard layout
- Left sidebar: federation logo (full width) with name below, navigation. Sidebar fits the window height with no page scrollbar.
- Top bar: federation switcher (tree, search, limited to the user's scope) with path and page title, messages (chat heads), notifications, profile menu (Settings & billing, Security & 2FA, Log out).
- Notifications are generated from real data: pending approvals, new club submissions, transfer requests, trial ending, bank transfer pending, 2FA off.
- Messages: draggable floating chat heads per club; drag to the bottom target to dismiss.
- Statistics: a page inside the dashboard (light theme, fills the content area); all numbers are mock until connected to real data.

---

## 9. Data rules
- Statistics and financial figures must come from real records. Show "—" and an empty state when there is no data (no fake revenue).
- Tables use fixed column grids so rows stay aligned; Takeone ID is the first column.
- Athlete photos are 3:4, never circles.
- Delete / bulk delete / clear-all exist for testing and must be restricted to managers in production.

---

## 10. Known prototype shortcuts (must be real in production)
- `localStorage` storage → server database and API.
- Verification codes, invitations and emails are shown on screen → send by SMS/email.
- Card payment is simulated → PSP integration.
- Uploaded documents over 2.5 MB are not carried to the invitation page → object storage with signed URLs.
- Old club has no page to reply to a transfer → email with release/object links.
