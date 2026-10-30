# Margin: the development board (action plan)

> Working name **Margin**: the things you notice in the margin of the day, before they're gone.
> Code module: `app/DevBoard/`. Status: **plan only, nothing built.** Written 26 Oct 2026.

---

## 1. The problem, in one line

You see something (a bug, an idea, a screen that looks wrong), and by the time you're at a
desk it's gone. Margin has to be **faster to open than your memory is to fade**, and pleasant
enough that you actually go back to the notes later.

Two people use it: **you and your assistant**. It's one shared board, and you work on it together live.

---

## 2. What the best tools do (research summary)

| Tool | What it gets right | What we take |
|---|---|---|
| **Trello / Jira** (Atlassian Pragmatic DnD) | The card lifts on grab and the destination shows exactly where it will land | A lift animation and a clear drop indicator |
| **Linear** | Speed. A card opens in place, never on a new page | Cards open as a sheet over the board, no navigation |
| **Apple Freeform / tldraw / Excalidraw** | Ink that feels like ink: pressure, smoothing, tapered ends (tldraw uses *perfect-freehand*) | Pressure-aware strokes with `perfect-freehand` and `getCoalescedEvents()` for smooth lines |
| **Skitch / CleanShot / ShareX** | Annotation in seconds with arrows, numbered steps, highlighter and blur | The same tools, plus numbered pins that link to checklist steps |
| **Things 3 / Apple Notes** | Calm, typographic, small on mobile and never shouting | Compact mobile sizing and quiet chrome |

**Drag-and-drop lessons that recur:**
- **Long-press to start the drag on touch.** Set `touch-action:none` on the handle only, never the whole board, so the page still scrolls.
- **Auto-scroll near the edges** is required on mobile, not a polish item.
- **Optimistic moves**: the card moves on drop, and if the server refuses, it springs back with an explanation.
- **Store order as a position value** (fractional index), not an array index, so a move writes **one row**, not every row after it.
- **Give a non-drag fallback**: "Move to…" in the card menu, for accessibility and for one-handed use.

Sources: [21st.dev, kanban DnD](https://21st.dev/blog/react-kanban-drag-drop-components) ·
[SaaS DnD reordering patterns](https://www.saasui.design/blog/saas-drag-and-drop-reordering-ux-patterns) ·
[Eleken, drag-and-drop UI](https://www.eleken.co/blog-posts/drag-and-drop-ui) ·
[HTML5 DnD vs libraries](https://www.techinterview.org/post/3233475140/build-drag-drop-kanban-board-frontend/) ·
[Atlassian: the journey of Pragmatic DnD](https://www.atlassian.com/blog/design/designed-for-delight-built-for-performance) ·
[Pragmatic DnD docs](https://www.atlassian.design/components/pragmatic-drag-and-drop) ·
[DnD libraries compared 2026](https://www.pkgpulse.com/guides/dnd-kit-vs-react-beautiful-dnd-vs-pragmatic-drag-drop-2026) ·
[perfect-freehand README](https://cdn.jsdelivr.net/npm/perfect-freehand@1.2.3/README.md) ·
[getCoalescedEvents](https://api.flutter.dev/flutter/package-web_web/PointerEvent/getCoalescedEvents.html) ·
[Skitch for designers](https://www.hackdesign.org/toolkit/skitch/) ·
[Markup tools roundup](https://www.screensnap.pro/blog/best-markup-tools)

---

## 3. The design concept: "Ink on Night Paper"

Something we haven't seen elsewhere, built **inside** the TakeOne palette (purple primary,
Inter, Bootstrap Icons):

- **The board is a deep aubergine desk** (primary hue, very dark) with a faint dot grid, like
  a designer's cutting mat. **Cards are warm paper slips** that sit on it with a soft, real
  shadow. Paper on a dark desk gives instant contrast and a tactile feel that no kanban template has.
- **Lanes are not boxes.** Each lane is a thin luminous **thread** running down the desk, with
  its name and count at the top. Cards hang from the thread. When a card is dragged near a
  lane, the thread **glows** in that lane's colour.
- **Picking up a card** (the signature moment): it lifts by 4%, tilts by about 2° toward
  the finger, its shadow deepens and spreads, and the cards beneath close the gap with a
  spring. On drop, the card **settles** with a small overshoot, and the thread pulses once.
- **Ink is the accent colour.** Everything drawn (pen, arrows, pins) defaults to the primary
  purple, with five more ink colours. A card that has a drawing shows a tiny ink-stroke mark in its corner.
- **Author identity is a colour, not an avatar.** You are one ink and she is another. Every
  note, stroke and comment carries its author's ink, so you can read who wrote what at a glance.
- **Motion respects `prefers-reduced-motion`**: tilt and spring turn off and fades remain.

### Sizing rule, mobile (deliberately small, not big)
- Card title 13px/600, body 12px, meta 11px. Card padding 10–12px, gap 8px.
- Visual elements stay compact; **tap targets reach 44px through padding**, not through big visuals.
- Hero band follows Design Rule #6 at the compact `pb-10` variant, so the board starts high.

---

## 4. Mobile first: how the screens work

### 4.1 The board (one lane at a time, with drag across lanes)
A horizontal kanban is unusable on a phone. Margin's answer:

- **One lane fills the screen.** The lane names sit in a slim **lane rail** under the hero
  (`Inbox 4 · Planned 7 · Doing 2 · Done`). Swipe or tap to change lane.
- **Drag a card and the "lane dock" rises** from the bottom: the other lanes appear as glowing
  drop pads. Drop on a pad to move the card there, or keep dragging inside the list to reorder.
  No sideways scrolling while holding a card. That's what makes cross-lane drag *work* on a phone.
- The default lanes are **Inbox → Planned → Doing → Review → Done**, and lanes themselves can be dragged to reorder or renamed.
- Filters are chips: *Mine · Hers · Has pictures · Has sketch · Due soon*.

### 4.2 Quick capture (the reason this exists)
- A small **ink-drop FAB**, visible only to the two of you, on every page of the personal shell.
- Tap → capture sheet: **Type · Photo · Sketch** (voice-to-text later). Two taps from
  any screen to a saved note. The card lands in **Inbox**, already saved, with no "save" step.
- It remembers **which page you were on** and attaches the link, so "this screen looks wrong" carries its own context.

### 4.3 The card (a bottom sheet, Design Rule #8)
The card is a **stack of items** that can **themselves be dragged to reorder**:
- **Note**: text with the `<x-prose-text>` subset (headings, bullets, bold). Edit in place, delete with a confirm, edit history kept.
- **Picture**: tap opens the **markup studio** (4.4).
- **Sketch**: a blank paper canvas (4.5).
- **Checklist / action plan**: steps you can drag to reorder, with an assignee and due date per step.
  Ticking the last step offers to move the card to *Review*.
- **Link**: a page of the platform, captured automatically or pasted.
- **Comments** at the bottom: a thread between the two of you.
- **Header** holds the title, lane chip, priority (Low/Normal/High/Urgent), assignee (you / her / both) and due date.

### 4.4 Markup studio (marking on pictures)
A full-screen dark stage, the picture centred, with a floating tool capsule:
**Pen · Highlighter · Arrow · Box/Circle · Text · Numbered pin · Blur · Eraser · Undo/Redo**.
- Pinch to zoom and two fingers to pan while drawing with one finger (or an Apple Pencil or S-Pen with real pressure).
- **Numbered pins link to checklist steps**: drop pin ③ on the picture and step ③ appears in
  the card's action plan. Tapping the step highlights the pin. This turns a marked-up screenshot directly into a to-do list.
- Strokes are **stored as vectors**, never burned into the photo, so they stay editable and erasable, and the original picture is never altered.
  A flattened PNG preview is generated for the card thumbnail.

### 4.5 Sketch canvas
Same engine as the markup studio, on paper instead of a photo: a pannable canvas, dot-grid paper, the same tools.

### 4.6 Desktop (after mobile is solid)
- All lanes side by side as threads across the desk, with cards dragged between them directly.
- Card opens as a **right-side panel**, so the board stays visible behind it.
- Keyboard: `N` new card, `/` search, `1–5` move to lane, `E` edit, `Del` delete.
- Paste a screenshot (`Ctrl+V`) anywhere and it becomes a new Inbox card with that picture.
- Drag image files from the computer onto a card to attach them.

---

## 5. Working together (you + assistant)

- **One shared board.** Every change appears live on the other screen over MQTT. Moves are a
  targeted patch, and anything heavier is a refresh signal (per the Realtime rule).
- **Presence**: a small ink dot shows when the other person is looking at the same card. If they're editing a note you open, you see it's being edited.
- **No lost edits**: each note carries a `version`. If two people save the same note, the second sees a
  "it changed while you were editing, keep which?" sheet instead of silently overwriting.
- **Assign and mention**: assign a card or a step, or `@mention` in a comment, and the other person gets a
  notification (`UserNotification::notifyUser`) that deep-links to the card.
- **Activity strip** on each card: "You moved this to Doing · 2h", "She added 3 pins · 10m".
- **Nothing is hard-deleted from the UI**: deleted cards and notes go to a **Bin** for 30 days, then purge (files first, then rows).

---

## 6. Access and security

- **Access = `App\DevBoard\Support\BoardAccess::allows()`**: the super-admin, or a row in the module's own
  `devboard_members` table (which also holds that person's ink colour). The assistant gets **only** that row,
  nothing else on the platform. (Changed from a shared `devboard.use` permission during phase 0: a module-owned
  table keeps the module deletable and touches no shared role tables.)
- Every route sits behind `auth` + `verified` + `BoardAccess`, and every query re-checks it. Cards
  are addressed by **uuid**, never an id. Routes are throttled (`throttle:member-write`, `throttle:uploads`).
- Pictures go through real-byte checks (`StoresBase64Images`, no SVG) and are stored **privately** under
  `StoragePath` → `platform/devboard/{card-uuid}/…`, served only through an authorising controller.
- Stroke JSON is validated server-side (shape, point-count cap, colour whitelist) so a crafted payload can't bloat storage.
- Feature flag **`DEVBOARD`**, default OFF, turned on for stage.
- **Assistant account**: created via a one-off command with a strong generated password shown once,
  **forced password change on first login**, and email verification on.

---

## 7. Technical design

### 7.1 Libraries: two small ones, both **vendored into `public/vendor/`** (no CDN)
| Need | Library | Why |
|---|---|---|
| Drag and drop | **SortableJS** (MIT) | Works on touch *and* mouse, framework-free, already used on the club gallery (via CDN; we vendor it). Used in `forceFallback` mode so we control the ghost (the tilt and lift). Pragmatic DnD is built on native HTML5 DnD, which is weak on mobile touch, and mobile is our first target. |
| Ink | **perfect-freehand** (MIT, a few KB) | The stroke engine behind tldraw. Pressure, smoothing, tapered ends. We render to `<canvas>` / SVG ourselves. |

Everything else is Alpine + Blade + plain JS, per the project's frontend rules. No React.
Both libraries are registered in `HOUSE-CLEANING.md` as tracked dependencies.

### 7.2 Data (all new tables, nothing existing altered)
```
devboard_members      id, user_id (unique), ink, added_by
devboard_lanes        id, uuid, name, color, position
devboard_cards        id, uuid, lane_id, title, priority, assignee_id, due_on,
                      source_url, position (fractional), created_by, version,
                      deleted_at (bin)
devboard_items        id, uuid, card_id, type(note|image|sketch|checklist|link),
                      position, body (note text / link), path (image), preview_path,
                      strokes (json: vector layer), created_by, updated_by, version, deleted_at
devboard_steps        id, uuid, item_id, text, done_at, assignee_id, due_on, pin_no, position
devboard_comments     id, uuid, card_id, user_id, body, created_at, deleted_at
devboard_activity     id, card_id, user_id, action, meta(json), created_at
```
`position` is a fractional key, so one move updates **one row**.

### 7.3 Module layout
```
app/DevBoard/
├── DevBoard.php                  Module (one line in config/modules.php)
├── Models/  Controllers/  Services/  Commands/
├── routes-member.php             /me/margin…, me.margin.* names
├── resources/views/{mobile,desktop}/…   resources/lang/{en,ar}/…
└── resources/js/                 board.js (DnD), ink.js (canvas engine), markup.js
```
Two components are built **reusable** and added to the CLAUDE.md library: `<x-ink-canvas>` (the drawing
engine, usable elsewhere later, e.g. annotating a bout frame) and `<x-sortable-list>` (DnD list with the lift/tilt ghost).

### 7.4 MCP
Tools: `list_devboard_cards`, `get_devboard_card` (notes, steps, pins, image links),
`create_devboard_card`, `move_devboard_card`, `add_devboard_comment`, `tick_devboard_step`.
Scoped to holders of `devboard.use`. That lets you tell Claude *"work on the top card in Planned"* and it reads your notes and pins directly.

---

## 8. Build phases (each one shippable and testable on its own)

| # | Phase | What you can do at the end |
|---|---|---|
| 0 | **Groundwork**: backup, module scaffold, flag, permission, migrations, vendored libraries | Nothing visible yet. The flag is OFF. |
| 1 | **Board + drag** (mobile): lanes, cards, lane rail, lane dock, reorder, create, edit, delete to Bin | Capture text cards and drag them through the pipeline |
| 2 | **Card sheet**: notes (edit/delete/history), checklist steps (drag to reorder), priority, assignee, due | Full action plans on a card |
| 3 | **Pictures + markup studio**: upload/camera, the tools, vector layer, numbered pins linked to steps | Photograph a problem, mark it up, get a to-do list from it |
| 4 | **Sketch canvas** | Draw ideas freehand |
| 5 | **Together**: assistant account, MQTT live sync, presence, versions, mentions, notifications, activity | Both of you work on the board at the same time |
| 6 | **Quick-capture FAB** + page-context link | Two taps from anywhere to a saved idea |
| 7 | **Desktop**: thread lanes, side panel, keyboard, paste screenshot, file drop | Full desktop experience |
| 8 | **MCP tools + tests + docs** (feature, authorisation, IDOR, upload, DnD position) | Claude can read and act on the board |

Each phase: backup before migrating, verify on stage, run the relevant tests, and report plainly what was and wasn't verified.

---

## 9. Open before building

1. **The assistant's full name and email**, needed for her account (phase 5, or earlier if she should test from phase 1).
2. Approve the **design concept** (section 3). Option: first build a clickable **design mock** of the
   mobile board and the markup studio, so you can see and feel the drag before any backend exists.
3. Confirm the name **Margin**, or pick another.

---

## 10. Progress log: WHERE WE STOPPED (update after every step)

> Read this first when resuming. Never redo a ticked step.

| Phase | Status | Date | Notes |
|---|---|---|---|
| 0 Groundwork | ✅ done | 26 Oct 2026 | See below |
| 1 Board + drag (mobile) | ✅ done | 26 Oct 2026 | See below. Waiting on the owner's phone test |
| 2 Card sheet | ⏳ next | | |
| 3 Pictures + markup | ⬜ | | |
| 4 Sketch | ⬜ | | |
| 5 Together (+ assistant account) | ⬜ | | needs her name + email |
| 6 Quick capture FAB | ⬜ | | |
| 7 Desktop | ⬜ | | |
| 8 MCP + tests + docs | ⬜ | | |

### Phase 0, done 26 Oct 2026
- Backup `storage/app/backups/db-20261026-151306.sqlite`, integrity `ok`. (The uploads half of
  `takeone:backup` fails on its 2 GB cap. That was already failing and is unrelated.)
- Module `app/DevBoard/` registered in `config/modules.php` (key `devboard`).
- Flag `config('devboard.enabled')` ← `DEVBOARD_ENABLED`, **not yet in `.env`, so OFF**.
- Migration `app/DevBoard/database/migrations/2026_10_26_180000_create_devboard_tables.php` **has run**
  (7 tables: members, lanes, cards, items, steps, comments, activity). Lanes are seeded lazily by the board
  service on first open (not by the migration). `lanes_db = 0` is expected until then.
- Models: `Lane, Card, Item, Step, Comment, Activity, BoardMember` + `Concerns\HasBoardUuid`.
  `Item` uses `DeletesUploadedFiles` (`path`, `preview_path` on `local`).
- `Support\BoardAccess::allows()/ink()`.
- Vendored: `public/vendor/sortablejs/Sortable.min.js` (1.15.6) and
  `public/vendor/perfect-freehand/perfect-freehand.min.js` (1.2.2, wrapped as `window.PerfectFreehand`), each with LICENSE + README.
- `config:cache` rebuilt as www-data; site verified 200; app boots with the module.
- **No routes yet**, so no `route:cache` needed.

### Phase 1, done 26 Oct 2026
- **Live on stage at `/me/margin`** (`DEVBOARD_ENABLED=true` in `.env`, config + route cache rebuilt; routes 1154 → 1164).
  Only the super-admin can open it until phase 5 adds the assistant.
- Backend: `Services/Board.php` (lazy default lanes, fractional positions + rebalance, payload, activity log),
  `Controllers/BoardController.php` (show, data, store, update w/ version → 409, move, destroy → Bin, bin, restore,
  lane rename, lane move), `Middleware/EnsureBoardAccess.php` (404 for anyone else), `routes-member.php` (registered only
  while the flag is on; throttle 120/min writes).
- Front end: a standalone page `resources/views/mobile/board.blade.php` + `partials/styles` + `partials/script`
  (plain JS that owns its DOM; SortableJS in fallback mode). Lane rail (tap / swipe / long-press to reorder lanes),
  one lane per screen, lane title renamed in place, composer (Enter saves; leaving with text SAVES), cards with
  author ink, priority corner folds, counts, the **lane dock** while dragging, the card sheet (title, priority,
  move-to fallback, Bin), Bin sheet with restore, Undo toast, filters (All / Mine / For me / Urgent), refresh on return to the tab.
- Display font Instrument Serif vendored at `public/vendor/instrument-serif/`.
- Default lane colours deepened (Inbox #7c6bf5, Planned #3b82f6, Doing #d97706, Review #db2777, Done #059669), and the 5 live rows were updated.
- **Verified:** page renders as www-data (200); non-member → 404 JSON / redirect page; write paths (create, reorder,
  cross-lane, foreign-neighbour refusal, 60-squeeze rebalance, 409 conflict, bin, restore, blank title) inside a
  rolled-back transaction; headless Chromium drove drag-reorder, the dock drop, capture and the sheet with
  0 JS errors. Screenshots reviewed at 390×844.
- **Not verified yet:** on a real phone / inside the Android app (long-press feel, haptics), RTL.
- The desktop uses the same mobile page (centred column) until phase 7.
- Pre-existing, unrelated: `ModuleBoundaryTest` fails on `app/Events/Support/EventCertificates.php → App\Certificates\Controllers`.

- Later the same day: while dragging over the lane dock, the card now shrinks to a small token (`body.over-dock`) so the pads stay visible.
  A preview page was published for the owner (claude.ai artifact 56Lppdq9TqqnhDMVb5DXog).

### Phase 2a: formatted notes + menu link, done 26 Oct 2026
- **What the owner asked for:** "i dont see any link in the webapp taking me to the notes page. and not all notes are drawing,
  some time i need to ad text images and word formating and all."
- **Way in:** a "Margin · ideas" row (`bi-vector-pen`) in every account menu, shown only to board people
  (`BoardAccess::linkFor()`, memoised per request, false when the routes are not registered):
  `personal-mobile` drawer, `app.blade` profile dropdown + drawer, the new-UI `shell/account-menu`, `partials/mobile-account-links`.
- **Notes:** each card has formatted notes: headings (H1 serif, H2 italic serif), bold, italic, underline, strike, bullet and numbered lists,
  quote, text colour (7 inks), links (typed in the toolbar, http(s) only), divider, clear, undo/redo, and **pictures inside the text**
  (pick, paste or drop). The page is warm paper with a rose margin rule, and an ink toolbar that rides on the keyboard
  (visualViewport). It autosaves 0.9 s after typing stops, on close, and on leaving the app. A note nobody wrote in is never created.
  Notes on the card sheet are tiles (first line as a serif title, an excerpt, a thumbnail, the editor's ink signature) and are **drag-reorderable**.
  Delete has Undo. On a stale save (409) the other version is kept and yours becomes a new note, so nothing is lost.
- Backend: `Services/Notes.php`, `Services/NoteHtml.php` (runs the platform `HtmlSanitizer`; pictures are lifted out and put back
  only as `<img data-asset="uuid" alt="">` for a picture of THIS card), `Services/Pictures.php` (sniffs the real bytes jpg/png/webp/gif,
  rejects over 40 MP, GD decode, EXIF upright, re-encode to WebP at 2400 px plus a 720 px preview, private disk
  `platform/devboard/cards/{card-uuid}/`), `Controllers/NoteController.php`, `Models/Asset.php`, `Card::assets()` (purged on force delete).
  Routes `me.margin.notes[.store|.update|.move|.destroy|.restore]`, `me.margin.pictures.store` (30/min) and `.show` (600/min, private immutable cache).
  A note is always resolved through its card.
- Migration `2026_10_26_200000_create_devboard_assets_table` (additive), run after DB backup `db-20261026-203357.sqlite` (integrity ok, 825 users).
  Config + route cache rebuilt as www-data.
- Front end: `partials/notes.blade.php` (included inside the board script's closure), `partials/notes-styles.blade.php`, a `#note-sheet` in the board view.
- **Verified:** `tests/Feature/DevBoard/MarginNotesTest.php` 8/8 (sanitising, picture re-encode + card scoping, non-image refused,
  409, delete/restore/reorder, cross-card 404, outsiders refused, menu link). Headless Chromium (390×844, touch) with a stubbed API
  wrote a note with H1, bold, list, quote, picture and colour, with autosave POST then PATCH and 0 JS errors; screenshots reviewed.
- **Not verified:** a real phone keyboard (iOS/Android WebView toolbar position), RTL, the Android app's file picker for pictures.
- Not built yet from the phase-2 list: note history, checklist steps, assignee, due date. MCP tools are still phase 8.

**Next (phase 2b):** checklist steps (drag to reorder), assignee, due date; then phase 3 markup on pictures.

---

## 11. TakeNote: the phone app (decided 27 Oct 2026)

**What the owner asked for:** "can the not section be an app on its own on the mobile and yet connected to the takeone
project notes … when i am outside … i want to record it directly with all kinds of notes and multimedia be it a sound
or video or picture or a pen written note or a nicely formated note … it opens as if its own note application but with the name of takenote".
Design approved from the clickable mockup (claude.ai artifact 4QTYkQYcBaVRShwMdBQsfj, 13 screens): "amazing design i agree let move forward".

**How it fits:** TakeNote is the board seen as a notebook. **Every card is one note; the note's page is the card's first note item.**
A new note is a new card at the top of the first lane (Inbox). No second store: the phone and the desk read the same rows.
Installed as a web app (works on Android and iPhone, no store, no APK rebuild). A Flutter `note` variant (share-to, widget)
stays optional for later and needs a new release key (the old one is lost).

| # | Step | Status |
|---|---|---|
| T1 | App shell: own address, icon, install, sign-in, Notes list, write + photo, search, filters, bin/undo | ✅ 27 Oct 2026 |
| T2 | Voice notes + short video (record, upload, play inside a note and on the board) | ✅ 27 Oct 2026 |
| T3 | Draw (perfect-freehand, already vendored) + mark up a photo | ✅ 27 Oct 2026 |
| T4 | Send to the board sheet (lane, who, when), replies, presence, notifications over MQTT | 🟡 board + send done 27 Oct; replies / presence / notifications next |
| T5 | No signal: notes saved on the phone, sent when back online | ⬜ |
| T6 | Assistant's account (needs her name + email), phone tests, MCP tools (with board phase 8) | ⬜ |

### T1, done 27 Oct 2026
- **Live on stage at `/me/takenote`** (also `/takenote`). Flag `TAKENOTE_ENABLED=true` in `.env` (needs `DEVBOARD_ENABLED` too; default OFF).
- **Shared editor first (Shared Stays Shared):** the note editor and its autosave moved out of `partials/notes` into
  `partials/editor.blade.php` (`window.MarginEditor.create/saver`) and `partials/editor-styles.blade.php`. The board's
  note sheet now uses it; re-verified with the board's headless drive (upload → POST → PATCH, 0 errors).
- Backend: `Services/TakeNote.php` (list newest-first, open, create in Inbox, write with page version → 409, title follows the
  first line), `Controllers/TakeNoteController.php` (`me.takenote.data|show|store|update|destroy|restore`; `destroy?discard=1`
  only removes the caller's own blank, unanswered, <1 day old note), `Controllers/TakeNoteAppController.php` + new
  `routes.php` (`takenote.app` page, `takenote.manifest`, `takenote.worker`). `Board::person()` added (public, additive).
  Pictures use the board's own picture door. No migration.
- The app page answers **signed out** with TakeNote's own sign-in, which posts to the normal `/login` (throttle, lockout,
  verification, 2FA all still apply) and returns to the app via `url.intended`. The page carries no notes; data is behind the member stack.
- Installable: manifest (scope `/me/takenote`, standalone, shortcut "New note"), service worker `/me/takenote/sw.js` with
  `Service-Worker-Allowed: /me/takenote` (controls only the app; caches the shell + fonts/icons, never notes).
  Icons in `public/images/takenote/` (32, 180, 192, 512, maskable 512), rendered from the mockup's logo.
- Front end: `resources/views/takenote/{app,head,styles,script,sign-in,worker}.blade.php`. Capture bar: Photo · Write · Board
  (Voice / Video / Draw are added in their own steps, never as dead buttons). Back button closes a note (history).
  A written note opens on its title line (serif H1); a photo note on a plain page.
- **Verified:** `tests/Feature/DevBoard/TakeNoteTest.php` 8/8 + MarginNotesTest 8/8; HTTP on stage (`/takenote` 302 → app,
  sign-in 200 posting to /login, manifest + worker 200 with the right header); headless phone drive with a stubbed API
  (write → one POST, photo → create + upload + PATCH, an empty note creates nothing, search, back) with 0 JS errors; screenshots reviewed.
- **Not verified:** installing on a real phone (Android Chrome / iPhone Safari), the real keyboard, signing in through the app.

### T2, done 27 Oct 2026: voice notes and short videos
- **What the owner asked for:** "go ahead with voice and video next".
- Migration `2026_10_27_120000_add_media_to_devboard_assets_table` (additive: kind, status, mime, duration_ms, meta, source_path),
  run after DB backup `db-20261027-085541.sqlite` (integrity ok, 825 users). (The uploads half of the backup still fails on its 2 GB cap, as before.)
- `Services/Clips.php`: ffprobe checks the real file (container allow-list, an audio stream for a voice note, a video stream for a
  video); stored under a server name with a neutral `.bin` extension; **always re-encoded before it is served** (voice → AAC .m4a 64k
  mono with 48 waveform peaks, in the request; video → H.264/AAC .mp4, long side ≤1280, + a WebP poster, on the `media` queue via
  `Jobs/PrepareClip`); the length cap is enforced by the encode (`-t`: voice 15 min, video 60 s); the upload is deleted after.
  Reuses `App\Media\Ffmpeg` for the binaries. Limits in `config/devboard.php` (`media_mb` 10, `voice_seconds`, `video_seconds`).
- Routes `me.margin.media.store` (20/min), `.media.show` (Range, so phones can seek; `?poster=1`), `.media.status`. The picture
  door now refuses recordings. In a note a recording is `<img data-asset data-kind="audio|video">`, and the **server** writes the
  kind from the asset row (`NoteHtml`). Note payloads carry `media` (kind, status, duration, peaks); `assets` stays pictures only.
- `partials/recorder.blade.php` = `window.MarginRecorder` (shared): voice screen (live waveform, timer, pause, 15-min cap, stop
  saves) and camera screen (preview, flip, 1-min ring, choose from gallery, 1 Mbps so a minute stays under 10 MB); Wake Lock
  while recording; inline "throw it away?" panel; file-input fallback where the browser cannot record.
- The shared editor shows recordings as **clips**: a voice note is a play button over its waveform (tap to seek), a video a
  player that says "Preparing the video…" and polls until ready. Mic and video tools in the toolbar; both on the board too.
- TakeNote: capture bar Voice · Photo · Write · Video · Board; tiles lead with what the note holds (rose voice + length, teal video
  + poster); a note with only a recording is named "Voice note · 27 Oct 11:00" until words arrive.
- **Verified:** MarginClipsTest 5/5 with REAL ffmpeg (WebM/Opus voice → m4a + peaks; H.264 video → mp4 + poster + Range; a fake
  .webm and a silent video refused; server decides the kind; card scoping; outsiders refused; TakeNote list + naming), all
  DevBoard tests 21/21; headless phone with a fake mic + camera drove voice and video from the capture bar to a saved note (0 JS
  errors); the board and TakeNote drives re-run clean.
- **Not verified:** a real phone (mic/camera permission prompts, iPhone Safari recording, screen-off behaviour), a real video on
  the live media worker. ⚠️ Inside the TAKEONE Android app (WebView) the shell asks Android only for the CAMERA runtime permission,
  not RECORD_AUDIO, so voice may fail there until the shell is updated + rebuilt; the installed TakeNote web app (Chrome) is unaffected.

### T3, done 27 Oct 2026: drawing and photo markup
- **What the owner asked for:** "go ahead with draw and photo markup next".
- No migration: a drawing is a picture asset (`kind=image`) whose `meta.ink` holds `{kind: sketch|markup, w, h, base, strokes}`.
- `Services/Ink.php`: stores the phone-rendered picture through `Pictures` (bytes checked, re-encoded WebP) and validates the
  strokes strictly (types pen/marker/arrow/ellipse/text, `#rrggbb`, width 0.5–160, numeric points inside the page, ≤5000 points a
  stroke, ≤60 000 a drawing, ≤3000 strokes, text ≤300 chars with control characters dropped). A markup must name a `base` picture
  of THE SAME card; a sketch must not have one. Marks are never burnt into the only copy: the original photo stays.
- Routes `me.margin.ink.store` (30/min) and `me.margin.ink.show` (strokes + base; a plain photo opens as its own base, no strokes).
- `partials/ink.blade.php` = `window.MarginInk` (shared; loads the vendored perfect-freehand): sketch on dotted paper (1200×1600
  page units) or mark up a photo (its own size, ≤2400): pen (pressure from a stylus), highlighter, arrow, circle, text, stroke
  eraser, 6 colours, undo/redo (+ Ctrl/Cmd-Z), a second finger never draws, inline "leave without saving?" panel.
- Editor: a Draw tool (sky) in the toolbar; tapping a picture shows a "Mark up" chip that reopens the drawing (or the photo
  under it) with every stroke editable; the result replaces the picture in place. On the board too.
- TakeNote: capture bar is now Voice · Photo · Write · Video · Draw (as in the mockup); the board link moved to a round button
  in the header. A drawing note leads with "Drawing" (sky) and is named "Drawing · 27 Oct 12:30" until words arrive.
- **Verified:** MarginInkTest 6/6 (sketch kept with strokes, markup keeps its photo, base must be this card's, 9 kinds of bad
  strokes refused, outsiders 404, TakeNote lead/name); all DevBoard tests 27/27; headless phone drew a sketch with every tool
  (eraser + undo included) and marked up a photo from the chip, saved and swapped in place, 0 JS errors; earlier drives re-run clean.
- **Not verified:** a real phone or stylus (feel, palm rest, pressure), RTL.

### 27 Oct 2026 (later): the note page, and a real annotation tool
- **What the owner said:** "when i want to open a note to look at it or read it, it opens it in a sheet … i want something
  professional"; then "your proposed design were amazing, but you implimented something else :(" (the built note page was the
  board's sheet editor, not mockup screen 9). Research (Apple Notes, Keep, Bear, Notion, Samsung Notes) and a new mockup
  (artifact 6QYeaP6TmfYBjW8UdvD99w) were approved: "yes that looks greate".
- `partials/page.blade.php` = `window.MarginPage` (shared): a full page that opens an existing note for READING (large title,
  lane + "Edited by … · time", no keyboard, no toolbar); tap the words (or the pencil) to write exactly there, tools above the
  keyboard (across the top on a desk), Done back to reading; long notes shrink the title into the bar and get a back-to-top
  button; Back closes it (history). The board no longer opens notes in a sheet; the board and TakeNote both use this page.
  The editor leaves `[data-ui]` decorations out of what it saves.
- **Annotation (modelled on Screenpresso):** every mark is now an object you can select, move, resize, rotate (15° snap),
  recolour, change thickness, re-style, duplicate or delete, then and later. New: rectangle, speech bubble (tail you drag, text
  that wraps and grows the bubble), numbered steps, blur (photos), and arrow styles straight / dashed / two-headed / curved
  (drag the bend) / block. Select tool, double-tap text to edit, keyboard Delete / arrows / Ctrl-D / Ctrl-Z.
  Server `Ink::clean` accepts the new types with strict shapes (point counts per type, `s` from a list, `n` 1–999, `r` ≤ 7 rad,
  bubble text ≤ 600 chars).
- **Verified:** DevBoard tests 28/28 (new test for the annotation objects + 7 bad shapes); headless drives: reading/editing on
  phone and desk, board note page, writing, voice/video, sketch, and a full annotation session (curved yellow arrow, rotated +
  moved rectangle, bubble with text, steps 1–3 with duplicate/delete/undo, blur) saved with every property intact; 0 JS errors.
- **Not verified:** a real phone / stylus; pinch-zoom inside the drawing (not built).

### 27 Oct 2026: TakeNote as an APK
- **What the owner asked for:** "i need it to be an apk too".
- A fifth variant of the Flutter project (`flutter/TV`): `./build.sh note [base-url]` → `dist/takenote-1.0.apk`,
  application id `bh.takeone.note` (installs beside the TAKEONE app), label "TakeNote", its own launcher icon
  (`android_note_res/`, adaptive + monochrome, the note-and-pen mark), manifest `android_note/` (internet, camera, microphone;
  no location, no notification service, no self-update yet). It is the member app's shell (`lib/src/app/shell.dart`) opened on
  `/me/takenote` (`Config.appHome`), a pocket app (status bar, rotation, back; no kiosk habits: `Device.isPocket`).
- **Microphone fix for both pocket apps:** the shell now asks Android for RECORD_AUDIO when a page asks to record
  (`ensureMicrophonePermission`, request 7314), as it already did for the camera — voice notes recorded silence before.
  The TAKEONE app gets this on its next rebuild; nothing else in it changed.
- Signed with the build machine's DEBUG key (the release keystore is lost): fine to side-load; every later TakeNote APK must be
  built on this machine (same key) or installed fresh. A Play listing needs a new release key first.

### 27 Oct 2026: recordings land where the cursor is, and everything can be dragged
- **What the owner said:** "when i record … i should be able to place it between text … currently what ever i record stays up and
  wont move, i need a drag an drop option. and when i record while i am in the edit window and my cursor is at a specific
  position, make the recording be placed there".
- `placeBlock()` in the shared editor: a recording (and a new drawing) goes straight AFTER the paragraph the cursor is in (before
  it when the cursor is at its very start), never splitting a word (it used to land mid-word); with no cursor it goes at the END,
  not the top. The cursor then waits on the line after it.
- Drag and drop: every recording has a grip (⋮⋮) in edit mode; a tapped picture shows **Move** beside **Mark up**. Dragging shows
  a lifted copy and a purple drop line between paragraphs, scrolls the page near its edges, and saves the new order. A picture
  sharing a paragraph with words travels alone into its own paragraph. Works on the board too (same editor).
- **Verified:** headless phone: cursor mid-paragraph → voice note after that paragraph, unsplit; dragged up above it by the grip;
  photo moved to the end by its Move handle; saved HTML in the new order; every earlier drive re-run clean; DevBoard tests 28/28.
  No change to the APK needed: it shows the live web, so this reaches the installed app at once.

### 27 Oct 2026: the board inside TakeNote, and "Send to the board"
- **What the owner said:** "go ahead with send to the board next, even the board make it withing the note taking app, the board
  design currently is ugely … not suited for the app … even the navigation is problematic its confucing". Mockup approved
  (artifact Nzhrbo4K18eHz3iJDy5hkU): "amazing go ahead".
- **Notes / Board switch** at the top of TakeNote (remembered per phone). The Board view is ONE scrolling list grouped by lane
  (Linear / Things style, not one swiped column): a pipeline bar, lane headers with counts that fold (Done starts folded,
  remembered), the same tiles. Tiles now show who a note is for and its due date (today/tomorrow amber, overdue red).
- **Drag between lanes** (SortableJS, hold 220 ms on touch): the other lanes fold into "Drop here" strips; the one under the
  finger lights up; a note dropped on a strip goes to the TOP of that lane; within its own lane it lands exactly where dropped.
  The note's old place is held open while dragging so nothing jumps under the finger.
- **Send to the board** sheet (Design Rule #8 band): lanes as cards with counts, who picks it up (people on the board, or nobody),
  by when (no date / today / tomorrow / in a week / a month calendar, day before month). From the bar at the foot of a note still
  in Inbox, or the note's lane chip any time. Toast "Sent to Doing, for Sara" with Undo.
- Server: `POST me.takenote.place` (lane + above/below neighbours of that lane, assignee must be a board person, due Y-m-d or
  null; only sent fields change) → `TakeNote::place()` over `Board::move()`; data now carries `lanes`; summaries carry
  `position`, `assignee`, `due_on`. Links to the old board page removed from TakeNote (the page itself still works).
- **Verified:** TakeNoteTest +1 (send, top-of-lane, between neighbours, clear, refusals, outsiders) → DevBoard 29/29; headless
  phone: board view, drag Inbox → Planned (lands at its top), send sheet with a calendar day + Undo, and every earlier drive.
  Two bugs found and fixed on the way: the Notes list showing above the board, and a brand-new note not opening.
- **Not built yet:** replies on a note, "assistant is reading", phone notifications when a note is sent to someone (needs the
  assistant's account), saving with no signal (T5).

### 27 Oct 2026: gaps at the top and bottom of the APK, fixed (confirmed on the owner's phone)
- Cause: the app shell (Flutter `SafeArea`) already keeps the page clear of the system bars, and newer Android WebViews ALSO
  report those bars to the page as `env(safe-area-inset-*)`, so TakeNote added the space twice.
- Fix (web side, no APK rebuild): every safe-area use in DevBoard views reads `var(--sa-t|b|l|r, env(...))`; inside the app
  (`window.Capacitor.isNativePlatform()`, the shell's bridge) `<html class="in-shell">` sets them to 0. Browsers are unchanged.
  Owner: "no its gone :)".

### 27 Oct 2026: nothing uploaded outlives its note
- **What the owner asked for:** "make sure when i delete a not every thing uploaded in it get deleted".
- Found: deleting a note puts it in the Bin (Undo), and the Bin promised "kept for 30 days, then gone for good", but nothing
  ever emptied it, so binned notes and their files stayed forever. Uploads taken OUT of a note (a photo deleted from the text, a
  recording removed, a drawing re-done) also stayed forever.
- `Commands/Sweep.php` → `php artisan devboard:sweep [--apply] [--days=30]`, dry run by default; scheduled
  `devboard:sweep --apply` daily 04:30 (after the 03:30 backup) in `routes/console.php`. It force-deletes cards binned > 30
  days (Card::booted removes items and assets one by one, files first, then the empty card folder), single notes binned > 30
  days, and uploads no note of their card uses any more once they are a day old. Kept: anything in a note (binned notes count,
  they can come back), the photo under a drawing that is in use, and uploads less than a day old (a note may not have saved yet).
- **Verified:** MarginSweepTest 2/2 (files really gone from disk; still-restorable and in-use files untouched; dry run deletes
  nothing); DevBoard 31/31; a dry run against stage reported nothing to delete yet.

### 27 Oct 2026: writing direction per paragraph (English and Arabic in one note)
- **Built:** the page no longer has one direction. A paragraph with none set finds its own from its first letters
  (`unicode-bidi: plaintext; text-align: start` in editor-styles), so Arabic goes right and English left, line by line.
  Two tools (LTR `bi-text-left`, RTL `bi-text-right`) fix the paragraphs under the caret — like Word's text direction;
  pressing the lit one again hands the paragraph back to automatic. A list item turns its whole list so the bullets move.
  NoteHtml carries `dir="ltr|rtl"` on p/h1–h4/li/blockquote/div/ul/ol past the shared sanitiser (marker U+E001); any other
  value is dropped. Works in TakeNote and the board's notes (same shared editor).
- **Verified:** MarginNotesTest new case (dir kept, junk dir and smuggled marker stripped); DevBoard 32/32; Playwright drive
  tn-dir (auto direction, RTL on/off, saved HTML) + tn-read/tn-place/tn-board, 0 JS errors. Not yet tried on the phone.
- **Hardened (same day, from a security review):** a writer could type the markers as HTML entities (e.g. inside a link's
  href) and the restore step would splice an `<img>` into the attribute. NoteHtml now strips every encoded spelling of
  U+E000/U+E001 before sanitising and restores markers in text only, never inside a tag. MarginNotesTest covers it; 33/33.

### 27 Oct 2026: tools that fit, and TakeNote on a desk inside the website
- **Toolbars fixed (phone):** the editing bar was ~950px of tools in a 360px row. Now one row that always fits (picture ·
  voice · video · draw | Aa · colour · link | undo · redo, widths shared evenly); "Aa" opens the text formatting
  (bold … direction, divider, plain text) as a panel above it, wrapped onto two lines. On a wide screen the formatting
  sits inline and "Aa" is not shown. The drawing tray: tools on two even rows of six, options and colours narrowed to
  fit, and rows padded so selection rings are no longer clipped. Measured: no row wider than the screen at 360/412px.
- **Desk view:** `/me/notes` (me.notes) — a normal website page (header band, QR to open the phone app) framing
  `/me/notes/app` (me.notes.app, X-Frame-Options SAMEORIGIN), both behind the member stack + EnsureBoardAccess
  (TakeNoteDeskController). takenote/desk + desk-styles: notes down the left (new note + capture, search with "/",
  Notes|Board, filters), the note opens in the pane beside them (MarginPage `host` option: no history entry, another
  note simply replaces it), the board stands as columns where a dropped card lands exactly where it is dropped.
  Same script and config as the phone (config moved to takenote/config, phone output byte-identical). The desktop
  profile menu now opens TakeNote; the mobile drawer is unchanged.
- **Verified:** DevBoard 34/34 (new: desk page for board people only, frame header, outsiders bounced); Playwright
  tn-desk (empty pane, open, switch note, edit, board, drag Inbox→Doing, site page) + tn-crop/tn-read/tn-board/tn-dir,
  0 JS errors. ModuleBoundaryTest fails on a pre-existing Events→Certificates reference (not this work).
- **Changed the same day (owner: "it must be the full view … like it is its own website"):** no frame and no site page.
  `/me/notes` (me.notes, TakeNoteDeskController@show) now IS the desk notebook, full screen; a small round arrow in the
  corner goes back to TAKEONE. `/me/notes/app` and takenote/web were removed (never released). DevBoard 34/34, tn-desk 0 errors.
- **Title field (owner asked):** TakeNote's note page (phone and desk) has a Title box above the text (MarginPage
  `titleField`). It IS the note's first `<h1>` — split off on open, put back on save — so storage, the list, the board
  and search are unchanged and every old note shows its title in the box. Enter goes on to the text; a new written
  note starts in the Title box. Verified in tn-desk (open, edit title → PATCH starts `<h1>…`, new note POST).
- **Browsers painting the site dark:** every light page's `<meta name="color-scheme">` is now `only light` (was
  `light`, which Chrome's "darken websites" does not take as an opt-out; the CSS already said `only light`). Not
  verifiable headless — to be checked on the owner's phone. Samsung Internet's own dark mode may still ignore it.
- **Fonts (owner: "the fonts look like toons … add multi font selection for Arabic and English … use the certificates
  designer's fonts"):** Instrument Serif retired from TakeNote. takenote/fonts: a Fonts sheet (phone head + desk head,
  `[data-fonts]`) choosing one English (designer's sans/serif/mono groups, 140) and one Arabic (designer's arabic group,
  47) typeface from `App\Printables\Support\Fonts::forClient()` → public/fonts/designer — no new files, no CDN. The pair
  becomes `--note` (each script takes its own font in the same line); titles 700. Kept per device (localStorage, faces
  stored with it; takenote/head applies it before the first paint, same-origin /fonts/designer/ URLs only). Rows load
  their font only when scrolled into view. Defaults Inter + IBM Plex Sans Arabic. Verified tn-fonts (pick Lora + Amiri,
  survives reload) + tn-read/board/desk, DevBoard 34/34.
- **NEXT (asked 27 Oct):** make the board and its cards behave exactly like Trello.

### 27 Oct 2026: the board, as Trello's
- **Owner:** "make the board and the board cards like trello … do it exactly the same." takenote/board rewritten:
  grey 272px lists side by side on the desk background, each scrolling on its own (phone: one list at a time,
  scroll-snapped, the next peeking); list header name · count · collapse (to a narrow strip, remembered per device);
  white cards with a cover (first picture / video poster), priority as a coloured label, title, badges (due: yellow
  soon, red overdue, green when done; text; comments; attachments; voice; video) and the assignee's avatar; hover:
  outline, the round "mark complete" (to the last list, again → back to the first, with Undo) and the pencil
  (quick edit = the plan sheet, now with Label). Drag: tilt 4°, grey slot, lands exactly where dropped in any list.
  "Add a card" composer per list: Enter adds at the bottom and stays open for the next, Esc / tap away closes.
  Server (additive): notes report `priority`; store takes optional `lane` (bottom of that list); place takes `priority`.
- **Verified:** DevBoard 35/35 (new: add-a-card bottom + label + bad label/lane refused); tn-trello desk (drag between
  lists, two cards added in a row, mark complete, quick-edit label, collapse) and phone (paged lists), tn-read/desk/place,
  0 JS errors. Fixed on the way: a stale "tap away" listener from the composer swallowed the next click.
- **Asked next (27 Oct):** the OPEN card as Trello's card back, plus attaching links and files. Researched; mockup for
  approval: https://claude.ai/artifact/Mo62QVbCYXneDg36pjVioN (desk window, phone full screen, Attach popover).
  Plan once approved: card back = list pill (move) · cover · ✓ + title · Add/Labels/Dates/Checklist/Members/Attachment ·
  Members/Labels/Due row · Description (= the note, our editor) · Attachments (links + files, new table
  devboard_attachments, private storage, real-byte allow-list, 25 MB, download through a guarded route; backup first)
  · Checklists (existing devboard_steps) · Comments and activity (existing devboard_comments / devboard_activity).
  NOTHING BUILT YET for this — waiting for "go".

### 27 Oct 2026: notes ≠ cards, and the open card (Trello's card back) — DONE (screens verified headless)
- **Owner:** "build it and keep in mind the notes are not the cards, we can convert a note into a card. don't mix
  between notes and action plan cards." Mockup approved: https://claude.ai/artifact/Mo62QVbCYXneDg36pjVioN
- **Done (server):** backup db-20261027-135440.sqlite verified; migration 2026_10_27_200000 (devboard_cards.kind
  note|card + cover_uuid; devboard_attachments) — the 2 live rows became notes. TakeNote::list() = notes only,
  ::cards() = cards only (data endpoint returns both); Board (Margin web board) = cards only; new note = note, "Add a
  card" = card; convert note↔card (me.takenote.convert); place refuses a note (422). Services\Attachments (files by
  bytes: pdf/office/txt/csv/zip/pictures, 10 MB, private, server names, download-only with nosniff+sandbox; links
  http(s) only), Services\CardBack (detail, comments own-only edit, checklists = items type checklist + steps,
  cover from a card picture). CardBackController + 15 routes me.takenote.cards.*; CardBackTest 5 cases; DevBoard 40/40.
- **Done (screens):** S.notes / S.cards kept apart (upsert routes by kind); the board draws cards only. A card opens as
  the card back: partials/page gained opt-in window (desk window over a dimmed board), cover, crumbEl (the list pill =
  move/plan), chips(), below, aside — notes unchanged. takenote/cardback: chips (label, person, due, checklist,
  attachments, + Add → attachment / checklist / dates-person-label), attachments grid (link · file · picture with
  Make cover · download · remove-with-confirm), checklists (progress, tick, add with Enter, remove), comments and
  activity (own-only edit/delete, "Comments only", runs of edits folded), Attach sheet (Link | File, progress per
  file, refusals explained, drop files anywhere on the card). Notes get "Make it a card" (footer + ⋯) through the plan
  sheet in convert mode (list, person, date, label), toast with Open; cards get "Make it a note" (Undo) and Delete.
  Fixes on the way: Escape with a sheet open closed the whole card; clicks in attachments/checklist/comments started
  writing; dark form controls on the paper card (color-scheme light).
- **Verified:** DevBoard 40/40; tn-cardback desk (open from board, link, PDF upload, checklist + tick, comment,
  note → card in Planned) and phone (card back full screen, comment from the footer box), tn-trello/read/fonts/ink/place,
  all 0 JS errors. NOT yet on a real phone. Limits: files 10 MB (server ceiling); cover from attached pictures only
  (not yet from a picture in the text); no live push to the other person (60 s refresh, as before).
- **QUEUED (owner, 27 Oct, while the card back was being built):** (1) folders in the notebook — keep notes inside
  folders; (2) a note map / mind-map view "just like the map in Trilium Notes". Not started; after the card back.

### 28 Oct 2026: folders, the note map, and links between notes
- **Owner:** "the notes need folders so I keep notes inside folders, also a view for mind map or note map just like the
  map in Trilium Notes" → "go ahead, make this app an amazing thing".
- **Data:** backup db-20261027-143037.sqlite verified (integrity ok); migration 2026_10_28_090000 — devboard_folders
  (tree via parent_id, 4 deep max, colour, position) + devboard_cards.folder_id. ⚠️ For ~1 minute the new code ran
  before the migration (the data endpoint read a table not yet there) — schema FIRST next time.
- **Server:** Services\Folders (create/update — no loops, depth ≤ 4 —, delete hands notes and sub-folders up a level,
  file a note); routes me.takenote.folders.store|update|destroy, me.takenote.file (notes only; cards 422);
  data returns folders; a note's summary carries folder + links (NoteHtml::noteLinks: hrefs to …/me/takenote?note={uuid}).
  NotebookFoldersTest 3 cases. DevBoard 43/43.
- **Screens:** takenote/folders — phone: a chip row (All · crumb › folder ⋯ · sub-folders · New folder), desk: a tree
  in the side column (fold/unfold remembered, ⋯ settings); drag a note onto any folder to file it (Undo); folder
  sheet: name, colour, sits in, new sub-folder, delete (two taps, notes move up). A note shows its folder as its chip
  (tap → move sheet); tiles show the folder instead of a board list. takenote/map — Notes · Map · Board; a canvas
  force layout (no library): root, folder hubs, note dots by kind, tree lines + glowing curved link arrows; modes
  Both | Folders | Links; hover lights neighbours + tip; drag pins a dot; click opens a note, a folder hub zooms to
  its notes (double-click lists it); wheel / pinch zoom, pan, fit; positions kept between redraws. Links: the
  editor's link tool (noteLinks option, shared editor) finds notes by name; links to notes look like chips and open
  the note in the app (page onLink); every note lists "Linked from" and "Links to"; /me/takenote?note= opens a note.
- **Verified:** tn-folders desk (tree, into folder, new folder, drag → filed, folder chip, links/backlinks, follow a
  link, link-by-name, map + zoom) and phone (chips, new folder inside, map), tn-cardback/trello/read/fonts/ink — 0 JS
  errors. NOT yet on a real phone. Not built: dragging folders onto folders (use "Sits in"), live push.
- **28 Oct (later): @ links, Ctrl+L, and the map like Trilium's.** Editor (shared, opt-in noteLinks): typing "@" + letters
  opens a list at the caret (arrows / Enter / Tab / tap; Esc closes; MarginPage ignores Escape while it is open);
  Ctrl+L / ⌘L opens the link box; links go in by DOM insertion (execCommand dragged computed styles in). Map: centred
  on a note (or folder) with "Steps out" 1 · 2 · 3 · All (remembered), click = re-centre (camera follows, centre breathes,
  new dots grow out of a neighbour), double-click = open, a card with "Open note", ✕ = whole notebook; "Show on map"
  in every note's ⋯. Desk toolbar wraps to a second line instead of being cut off on narrow desks. Verified tn-map2
  (recorded video in the views page), tn-folders/cardback/trello/read/ink 0 errors, DevBoard 43/43.
- **NEXT (owner, 28 Oct): "the tools for the cards are not like Trello, it's missing a lot, please investigate more."**

### 28 Oct 2026: ALL of Trello's card tools — DONE on stage (owner: "yes build all of them")
Plan, in order (tick each here as it lands):
1. [x] Backup db-20261027-151943.sqlite verified (9 cards, 2 folders) → migration 2026_10_28_120000 RUN on stage. Models: Label (COLORS), Card::labels/members/watchers + new casts:
   devboard_labels (6 seeded: Urgent/High/Low named + 3 unnamed), devboard_card_label, devboard_card_members,
   devboard_card_watchers, devboard_comment_reactions; cards + start_on, due_time, reminder_minutes, reminded_at,
   done_at, cover_color, cover_size, archived_at, is_template. Assignee → first member; priority → label.
   RUN THE MIGRATION BEFORE ANY CODE THAT READS THESE.
2. [x] Server: Controllers\CardToolsController + 15 routes (me.takenote.labels.*, cards.{labels,members,watch,dates,
   complete,cover-style,archive,template,copy,steps.plan,steps.card,comments.react}, me.takenote.archived);
   TakeNote::cards() leaves archived out, ::archived(); summary carries labels[], members[], watchers[ids], start_on,
   due_time, reminder, done, cover_color/size, steps [x,y], template, archived; data carries `labels`; comments carry
   reactions; comment store takes `mentions` → CardTools::commented(); place() keeps assignee ↔ members in step;
   a picture cover clears a colour cover. tests/Feature/DevBoard/CardToolsTest (5) — DevBoard 48/48.
3. [x] Screens: takenote/cardtools.blade.php (popover on desk / sheet with band on phone): Labels (create/edit/colour/
   delete), Members, Dates (calendar start+due, time, reminder), Cover (size, colours, attached pictures), Move (list +
   position), Copy / from template, Share (copy link `?card=`), quick menu (pencil, right-click, E), Filter, Archived
   items, templates button at each list foot, shortcuts L D M Space C E. Card front + card back chips rebuilt
   (board.blade, cardback.blade); checklist item person/date/→card + hide checked; reactions (icons) + @people.
   `?card=` opens a card from a link/notification. Driven headless: desk + phone, 0 JS errors ($S/pw/tn-tools.cjs).
4. [x] `devboard:remind` (Commands/Remind), every minute in routes/console.php; notifications: added as member,
   mention, comment on a watched card, reminder (UserNotification::notifyUser → realtime push).
- NOT done: the board doesn't push live to the other person yet (realtime), MCP tools for card tools (phase 8).
- **DONE 28 Oct:** notes take files too (same card file door; any of ~35 kinds checked by bytes — documents, sheets, slides,
  text, pictures, sound, video, archives; never programs/HTML/SVG). Shown as a row of chips (icon + name, tap opens) under
  the note; ⋯ → Attach a file, or drop files on the note. Assistant account created 28 Oct: Eman Alqaisia (board person #2).
- **DONE 28 Oct — links with a title + emoji (shared editor, so board notes get them too):** the link box has "Text to
  show"; selected words become the text; the caret inside a link edits that link; a pasted bare address becomes a link
  titled with the site's name. Emoji panel (toolbar smile): all 1,898 Unicode 15.1 emoji + skin tones + search + recents,
  from public/data/emoji/emoji.json (generated from Unicode's emoji-test.txt; README beside it). Drawn by the device.
  ONE panel (window.MarginEmoji.panel, in partials/editor) — also in card comments and the checklist "Add an item" box
  (cardback emojiFor(field): a smile button + the panel in a dark box, inserting at the remembered cursor), and
  editing a checklist item: tap its words → edit in place (Enter/Save, Esc/Cancel) with the same emoji button.
  Titles: the toolbar's emoji go into the note/card TITLE when the cursor was last there (MarginEditor option
  emojiTarget, set by partials/page); the board's "Add a card" box has the emoji button too; so does a label's name
  (Labels → create/edit) and a folder's name (new folder + folder sheet). Popovers now stay on screen when their content grows (ResizeObserver + clamp).
  and in comment REACTIONS: WhatsApp's six + "+" for any emoji; server takes the old six icon kinds or any emoji on
  that list (CardTools::reactionAllowed), at most 20 per person per comment. kind is varchar(12) — fine on SQLite;
  widen it before any MySQL move (ZWJ emoji run longer).
- **Owner asked mid-build (28 Oct):** "in the note how do I attach a file? a PDF or any other format?" → answer: today only
  CARDS take files (Attach); notes take pictures/voice/video/drawings. QUEUED after the card tools: file attachments in
  notes too (same Attachments service, a file chip placed in the text + a list under the note).


### 28 Oct 2026: the notebook's side, re-thought like the notes apps — MOCKUP, awaiting approval
- **Owner:** "the side pan of the takenote is not designed well … see other apps how the notes are visible and how they
  behave both on desktop and mobile" + "when I open a task card it's rounded on one corner only".
- **Card corners FIXED:** `.mp.is-window` had a 20px radius but no `overflow: hidden`, so the bar, cover and comments
  column painted over three corners. Added the clip in partials/page (pop-ups live on `<body>`, nothing is cut). Verified
  by screenshot (cb-desk-1).
- **Side panel diagnosis:** one 340px column stacks brand, big title, 5 capture buttons, search, Notes/Map/Board, filters
  and the whole folder tree — the first note starts ~710px down (2 notes visible on a laptop).
- **Proposed (mockup artifact KqpEHbQprdGW9wj9qg8cC9):** desk = three columns (folder rail with Views · Smart · Folders,
  collapsible to a 56px strip; compact note list grouped Pinned/Today/Previous 7 days/month with thumbnails; the note
  with capture tools in its own toolbar). Phone = Apple-Notes drill-down: Folders screen → folder list (inset grouped
  rows, swipe Pin/Move/Bin, bottom bar: quick voice · count · new note) → note (capture tools in its 7-button bar).
- **NEXT:** owner approves / changes the mockup, then build it (desk + phone), keeping every existing id the script uses.
- **BUILT 28 Oct 2026 (owner: "it seems nice let implement it").** Backup db-20261027-183037.sqlite verified (integrity ok,
  9 cards = live) → migration 2026_10_28_180000 adds `devboard_cards.pinned_at` (NULL = not pinned). `POST
  me.takenote.pin {pinned}` (TakeNoteController::pin, notebook rows only, throttled, EnsureBoardAccess; does NOT bump
  updated_at); summaries carry `pinned`. New partial takenote/layout (inside the script's closure): rows grouped
  Pinned/Today/Yesterday/Previous 7 days/Previous 30 days/month, `rowDate` (21:12 · Yesterday · Sat · 12 Oct), pin,
  smart lists ⟷ folders reset each other, phone screens (`body[data-screen]` home|list; going in is a history step, so
  Android back comes out; a note sits on top), swipe Pin/Move/Bin, desk folder column folds to a strip (Ctrl+\,
  remembered). The tile is now a row (script tile()). Desk = rail · list · pane (desk + desk-styles); phone = Folders
  screen + list + bottom bar (app + new phone-styles). Every id the script binds is kept (#cap-* hidden where unused).
  Note ⋯ menu has Pin/Unpin. New strings nb_* (en, ar). Old chip/capture-bar CSS registered in HOUSE-CLEANING.
- **Verified:** headless nb-check (desk 1440: list, open note, folder, Pinned list, fold/unfold, board; phone 390:
  Folders screen, into Events, swipe → Pin → row moves to Pinned, back → Folders, note → back → list), 0 JS errors.
  NotebookPinTest 2/2; DevBoard files all OK except the 2 known in_notebook failures (CardBackTest,
  NotebookFoldersTest — pre-existing). NOT yet on a real phone / the APK; Arabic layout not looked at.

- **28 Oct 2026: the browser painting the paper dark yellow (owner).** Every TakeNote surface now declares `color-scheme:
  dark` (the paper's `light` is gone; its fields, ticks, placeholders and scrollbars are painted light by hand in
  takenote/styles), head adds a real `@media (prefers-color-scheme: dark)` rule (Samsung Internet's dark mode skips
  sites that have one) and `<meta name="darkreader-lock">`. Probed in Chrome 129 with force-dark + dark preference:
  any color-scheme declaration keeps the page out (paper measured 251,247,239 vs 35,32,27 without); desk + phone
  screenshots keep cream paper. Samsung Internet not testable headless — confirm on the owner's phone.

### 28 Oct 2026: friends (each with a notebook of their own) + six languages — DONE on stage
- **Owner:** "make the notes app multi language … friends from around the world … don't share my notes with them,
  let them have their own notes" → invite-only; languages Brazilian Portuguese, Turkish, English, Arabic, Japanese, Chinese.
- **Data:** backup db-20261027-191730.sqlite verified (integrity ok, 9 cards) → migration 2026_10_28_200000:
  `devboard_notebooks` (uuid, is_main, owner_id unique, email, invited_by, joined_at, revoked_at) + nullable
  `notebook_id` on cards, folders, lanes, labels; every existing row → main notebook #1 (owner user 1). 0 rows left null.
- **Isolation:** Support\Notebooks decides whose notebook a request works in (own → a waiting invite claimed by a
  VERIFIED account with that email → main for super-admins/devboard_members as before). Models scope themselves:
  Concerns\InNotebook (cards, folders, lanes, labels; new rows born in the current notebook, or main off the web) and
  Concerns\InNotebookThroughCard (assets, attachments, items, comments, activity; steps through their item). The scope
  resolves the signed-in person's notebook itself, once per request, because route model binding runs BEFORE
  EnsureBoardAccess (found by the isolation test: binding ran unscoped). Off the web nothing is filtered (reminders,
  sweep, clip jobs). Board::people() on a friend's notebook = that friend only (so nobody can be added/assigned/told).
- **Friends:** TakeNoteFriendsController (me.takenote.friends[.store|.destroy], only the main notebook's people, 404
  otherwise; answers never reveal whether an email has an account; max 100; remove = revoked_at, notes kept, invite
  again restores). TAKENOTE_FRIENDS (default off) is ON in stage .env. Friends sheet: invite by email, copy the link,
  waiting/joined, two-tap remove. A TAKEONE notification goes to an existing verified account.
- **Languages:** TakeNote's own list (config devboard.locales: en, ar, pt_BR, tr, ja, zh) — NOT the platform list.
  Middleware TakeNoteLocale: device cookie → account's chosen users.locale → browser Accept-Language → en. Language
  sheet (me.takenote.locale sets the cookie, page reloads). lang/{pt_BR,tr,ja,zh}/messages.php translated (531 keys
  each, parity + placeholder checks pass); dates follow (pt-BR, zh-CN…, en-GB for English).
- **Verified:** NotebookFriendsTest 5/5 (friend sees none of the owner's notes/folders/pictures by uuid, and the
  reverse; friends can't invite; unverified can't claim; revoke/re-invite; language cookie + browser language).
  DevBoard all green except the 2 known in_notebook failures; TakeNoteTest had 2 raw-DB asserts switched to
  withoutGlobalScopes (model reads are now viewer-scoped). Live kernel as user 1 and 2071: 3 notes, 1 card, 2 folders,
  5 lanes, 6 labels — identical to the raw DB. Japanese UI + sheets screenshotted, 0 JS errors.
- **Not yet:** a real friend signing up end to end on a phone. (Invitation email: DONE, see below.) (Was: invitation EMAIL — the owner shares the link —
  mail is queued and has been unreliable).
- **28 Oct 2026: the invitation EMAIL — DONE.** App\DevBoard\Mail\TakeNoteInvite (queued; `rescue` so a failed send
  never fails the invite) + views devboard::mail.invite (HTML, TakeNote's violet band on paper, RTL for Arabic, no
  emoji, icon served from this site) and mail.invite-text. Language: the invited account's own users.locale when
  TakeNote speaks it, else the inviter's TakeNote language. Strings mi_* in all six; fr_sent now says the link was
  emailed. Sent on every new or renewed invitation, never on a refused one. Mail queue checked healthy (6 workers,
  0 waiting, last mail failure Sept). NotebookFriendsTest 6/6 (queued to the right address in the right language;
  renders in all six). Rendered EN + AR and looked at. Still to do: one real friend, end to end, on a phone.

### 28 Oct 2026: invite LINKS — sign up and write straight away (owner: "let them register straight away with the link")
- **Data:** backup db-20261027-211319.sqlite verified → migration 2026_10_28_210000: devboard_notebooks.invite_hash
  (SHA-256 of a 40-char secret, unique), label, via_link. The link is shown ONCE to the owner; never stored readable.
- **Flow:** Friends sheet → "Or send a link" (optional "who is it for", Copy / Share) → me.takenote.friends.link.
  takenote.join (GET/POST /me/takenote/join/{token}, TakeNoteJoinController, throttled, TakeNote's language): signed
  out → TakeNote's own door (name, email, password ×2; "I already have an account" → platform login, back here) →
  a normal TAKEONE account + the normal Registered/confirmation email → claimed atomically in the same transaction
  (two people, one link: one wins, no orphan account) → signed in (remember) → /me/takenote. Signed in without TakeNote
  → claimed; with TakeNote → straight in, link NOT used up. Used/removed/unknown → one "already used" answer (404).
- **Unconfirmed email, TakeNote only:** routes-member.php → routes-takenote.php, loaded from routes.php under
  me./me with ['auth', VerifiedOrLinkFriend, 'two-factor'] — VerifiedOrLinkFriend lets a via_link owner through and
  otherwise CALLS the platform's own `verified` unchanged. Route table diffed before/after: 1230 → 1233, the 3 new
  named routes only, 0 other differences (TakeNote/board routes: `verified` → VerifiedOrLinkFriend, nothing else).
  The platform's login still refuses unconfirmed accounts, so a second device needs the emailed confirmation.
- **Verified:** NotebookFriendsTest 8/8 (link sign-up → unverified → TakeNote opens, empty notebook, owner's notes
  404, /me/people still demands confirmation; link single-use; signed-in claim; owner doesn't consume; friend can't
  make links; plain unverified still 403). DevBoard/Mail/auth files green except the 2 known. Join page (pt-BR) and
  Friends sheet looked at.
- **28 Oct 2026: sign-ups come back to TakeNote (owner: "after she registered it took her to the TAKEONE page").**
  What happened: an EMAIL invite; she used the sign-in door's "Create an account" → the platform's /register →
  /email/verify → confirmed from Gmail → the platform's landing page (verify handler's default). Her notebook was
  claimed fine (user 2072). Fix: takenote.signup (GET/POST /me/takenote/signup, TakeNoteJoinController) — TakeNote's
  own sign-up from its sign-in door; queues the platform's WelcomeEmail with intended = takenote.app (the signed
  verify link already honours ?intended=), sets url.intended, and shows TakeNote's own "check your email" (resend via
  verification.send). Invite-link sign-ups now queue the same email (was event Registered → a link with no intended).
  takenote.app for a signed-in person without TakeNote → takenote.signup ('check' if unconfirmed, 'noaccess' 403 if
  confirmed but not invited) instead of the 404 → TAKEONE home; with friends off it still 404s. Friends sheet: the
  link box is now FIRST; the plain-address "Copy the link" (what she was sent the first time) is gone.
  NotebookFriendsTest 10/10 (sign-up → one confirmation email carrying intended → opened with no session → verified →
  TakeNote opens, notebook from the invitation; uninvited → invite-only page).
- **28 Oct 2026: TakeNote on the live site → a full live upgrade.** The live server is on 5 Aug code (old layout, 262
  migrations vs 399). Plan: Documentation/LIVE-UPGRADE-PLAN.md (freeze → rehearsal on a copy of live → prepare server
  → window → after). Not started; owner decides step by step. Friends stay on stage meanwhile.

### 28 Oct 2026 (evening): named connections between notes (Trilium's relations), reading-first notes — DONE on stage
- **Notes open to read**: a tap on a note's page no longer starts writing (`MarginPage` option `tapToWrite: false`, TakeNote
  notes only; cards and the Margin board unchanged) — the pen does. "Make it a card" / "Open the card" moved from the
  foot of the note into its ⋯ menu.
- **Connections** (`TAKENOTE_RELATIONS`, on for stage; migration `2026_10_29_090000_create_devboard_relations`;
  `Models\Relation`, `TakeNoteRelationController`, `takenote/relations`): two notes joined with a few words
  ("depends on", six suggestions per language), pointing one way, one per pair (making it again either way renames it),
  never touching either note's text. `Relation`'s global scope requires BOTH notes to pass `Card`'s own scopes —
  notebook, shared-folder fence, bin — so a uuid from anywhere else is a 404 and a connection half outside a share is
  invisible. Cap 3000 per notebook. Made: the map's note card → **Connect** → tap the other note (the whole notebook
  opens while picking, names on, bigger finger reach, a banner with Cancel; Esc cancels), **Shift-drag** note to note on
  a desk, or **Connect to a note…** in a note's ⋯ menu / under the note (search sheet). Drawn as green curved lines with
  their name on a pill (a dot when zoomed out); a tap on the name opens it: rename, turn round, remove, open either note.
  The note's card on the map lists its connections. `NoteRelationsTest` 5/5. Not yet: card backs, MCP tools, realtime.

### 28 Oct 2026 (afternoon): the editor like Word, checklists, link buttons, shared folders, 1 GB, the board's drag — DONE on stage
- **Editor** (`partials/editor`): Word's Home-tab order (Undo/Redo · font · paragraph · styles · insert), Word's own ¶→/←¶
  text-direction glyphs + Font Color "A" + Clear Formatting drawn as SVG; loose top-level words/links wrapped into
  paragraphs on load; picture chips: Crop (canvas crop, re-uploaded) + Remove with Undo; recordings removable while reading;
  pictures capped at min(70vh, 640px); link bar (Edit · Button · Open · Join the line above · ×); `data-look="button"`
  and **checklists** (`ul[data-check]`, `li[data-checked]`, tap a box — reading or writing) carried through
  `NoteHtml` by private markers (E002–E004), forged markers stripped. Bullets/numbers no longer leave `<p><ul>` and
  keep the caret (`listCommand`/`tidyLists`).
- **Ink**: the note's keyboard is put away on open; the tray hides while typing; bubble/text → back to select after one;
  a tap while typing finishes it; a tap on a bubble writes in it.
- **Cards**: from the Notes list everything opens as a note ("Open the card" at its foot); the board still opens the card
  back. Card checklists: a form + "+" (phone keyboards never sent a bare Enter), one tap one checklist; checklist
  sections take their words' direction (`sideOf`); comments: Reply (@Name), an @ button, Edit · Delete on the reactions
  line, Cancel; member avatars no longer stretched (`.ct-prow > span:not(.av)`).
- **Shared folders** (`TAKENOTE_SHARING`, on for stage; migration `2026_10_28_230000_create_devboard_folder_shares`):
  `Notebooks::resolve()` puts a request inside the OWNER's notebook fenced to the shared folder's tree (session key
  `takenote.space`, re-checked every request); `InNotebook` fences cards by `folder_id` and folders by `id`
  (`$notebookFence`), and keeps saves inside the fence; `InNotebookThroughCard` fences everything under a card. The
  owner's lists and labels can't be renamed/deleted from a share (403), nor the shared folder itself. Invitations moved
  to `Support\FriendInvites` (friends + sharing). A share binds to an account only once its email is CONFIRMED
  (security review). `FolderSharingTest` 10/10.
- **1 GB per notebook** (`TAKENOTE_QUOTA_MB`, `Support\Storage`): assets + attachments incl. the bin; checked at
  picture, clip, ink, file and card-copy; a donut gauge in the folder column. A share's files count against its owner.
- **Board drag**: whole-height drop areas while dragging + the aimed list lit; cards lift from labels/dates too;
  a held finger no longer opens the quick menu; edge auto-scroll (phones ~one list per ½ s) with snap off mid-drag.
- **Open:** TakeNote has NO MCP tools at all (the whole app, not only sharing) — a gap to close as its own task.
  Verified headless with real input events on a private copy of stage (deleted). Not yet on a real phone.
