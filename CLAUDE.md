# TAKEONE Project — Claude Instructions

## Project Overview

Laravel 12 SaaS platform for sports clubs (TAKEONE-SPORTSTECH). Multi-tenant architecture.

- **Stack:** PHP 8.2+, Laravel 12, Tailwind CSS 4, Vite 7, jQuery 3.7, Chart.js 4, Select2 4, Alpine.js 3
- **Custom package:** `takeone/cropper` (GitHub: TAKEONE-SPORTSTECH/laravel-image-cropper)
- **Auth:** Laravel Sanctum + email verification + optional 2FA

---

## Architecture

- **Multi-tenant:** Each sports club is a "tenant" with its own admin panel
- **Roles:** super-admin (platform), club owners/admins, regular members
- **Key directories:**
  - `app/Http/Controllers/Admin/` — ClubAdminController, PlatformController, ClubApiController, ClubMemberAdminController, etc.
  - `app/Http/Controllers/` — MemberController, FamilyController, InvoiceController, TrainerController, InstructorReviewController
  - `app/Models/` — Tenant, User, ClubInstructor, ClubPackage, ClubGalleryImage, ClubFacility, ClubActivity, ClubTransaction, ClubMemberSubscription, ClubReview, ClubSocialLink, ClubMessage, ClubAffiliation, Role, Permission, Membership, Invoice, Attendance, Goal, HealthRecord, TournamentEvent, PerformanceResult, SkillAcquisition, AffiliationMedia, NotesMedia, UserRelationship
  - `app/Services/` — FamilyService
  - `resources/views/` — admin/club/*, admin/platform/*, platform/, auth/, family/, trainer/

- **Trainer/Instructor data model:** `bio`, `skills`, `experience_years`, `is_personal_trainer` live on `User`. `ClubInstructor` holds only club-specific data: `tenant_id`, `user_id`, `role`, `rating`. Routes `/trainer/{user}` and `/t/{user}` use User model binding (User ID, not ClubInstructor ID).

---

## Route Structure

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

---

## Mobile / Desktop Separation — STRICT

**Rule:** Any view that has both a mobile and a desktop experience MUST be implemented as **two separate Blade files**, never a single file branching with responsive classes for fundamentally different layouts.

- Keep desktop and mobile markup in distinct files so editing one can never break the other.
- Convention: place them under device-named subfolders, e.g.
  - `resources/views/<feature>/desktop/<view>.blade.php`
  - `resources/views/<feature>/mobile/<view>.blade.php`
  - and, when needed, separate layouts `layouts/<name>-desktop.blade.php` / `layouts/<name>-mobile.blade.php`.
- The controller (or layout) selects the file using the shared `$isMobile` flag provided by the `DetectDevice` middleware: `view($isMobile ? '<feature>.mobile.<view>' : '<feature>.desktop.<view>')`.
- This rule applies to **new** views going forward (e.g. the Business/Chain experience). Existing CSS-responsive pages are not required to be split unless explicitly requested.

> Note: ordinary minor responsive tweaks (a header that stacks via `flex-col sm:flex-row`) do NOT require a separate file — only views whose mobile and desktop layouts genuinely diverge.

---

## Design Rules — STRICT

### 1. Never modify existing UI design
Do not change existing layout, styling, colors, or visual structure unless **explicitly instructed** to do so. When adding features, reuse existing HTML structure and class patterns exactly. Do not introduce new visual elements, color changes, or layout shifts as a side effect of logic changes.

### 2. Reuse existing patterns
When adding new UI, match the exact patterns already used in the same view — same button styles, same card structure, same spacing classes. No speculative improvements.

### 3. No unsolicited refactors
Do not clean up surrounding code, add docstrings, rename variables, or reorganize logic unless specifically asked.

### 4. No native OS-rendered form popups (`<select>`, `<input type="date|time">`) for styled UI
Never use a native `<select>`/`<option>`, or a native `<input type="date">` / `type="time"` / `type="datetime-local"`, when the control is part of the styled UI. The OS renders these popups (the option list, the calendar/clock) with sharp corners and its own colors — they cannot be themed and look inconsistent with the design system. Build a custom **Alpine** control instead: a `rounded-xl` bordered trigger button (chevron that rotates on open, purple focus ring) plus an absolutely-positioned `rounded-xl` panel (`bg-white border border-gray-100 shadow-lg overflow-hidden`) with a fade/slide `x-transition`, `hover:bg-muted/60` rows, and a `text-primary` highlight (check / selected state). Close on `@click.outside` and `@keydown.escape`. Keep the panel as smooth/rounded as the trigger. Reuse an existing `<x-*-dropdown>` component when one fits (see the Blade Component Library); otherwise match the reference implementations in `resources/views/personal/challenge-create.blade.php` — the **Win condition / Stake** dropdowns and the **Deadline** calendar popover (stores an ISO `YYYY-MM-DD` string, disables past days, has Clear / Today actions).

---

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

### Reference implementation
`/me/schedule`: `scheduleData()` returns the schedule as JSON; `pushScheduleRefresh($userIds)` publishes `{action:'refresh'}` on the `schedule` channel to every affected user; the list view's `realtime:schedule` handler calls `reloadData()` on `refresh` (and patches individual cards on `created/updated/deleted`). Personal-session edits use the targeted-patch shape; club-class/substitute changes use the refresh shape.

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

---

## Technical Notes

- **Storage permissions:** `umask(0002)` set in artisan for group-writable storage
- **Primary color:** 65% lightness (HSL)
- **Bootstrap replacement:** The project uses a custom JS bridge (`app.blade.php`) that handles `data-bs-*` attributes without Bootstrap CSS/JS. Do not add Bootstrap JS or assume Bootstrap modal/tab APIs work natively — they go through this bridge.
- **No npm test framework** — verify UI features manually or via dev server
- **No native browser dialogs** — never use `alert()`, `confirm()`, `prompt()`, or any native browser dialog. Always use `window.showToast(...)` for notifications and custom modals for confirmations.

---

## Mobile Responsiveness

Status: Complete. Key patterns:
- `overflow-x-auto` for tables and tab containers
- `flex-col sm:flex-row` for responsive headers
- `z-40` on sidebar when open (overlay was blocking clicks — resolved in commit `f314f2e`)

---

## Mobile Forms Must Be Mobile-Friendly — STRICT

**Rule:** Any form, modal, or sheet shown in a mobile view MUST be fully usable on a phone — the entire form (including every field AND the submit/cancel actions) must be reachable and visible. Never ship a mobile form where content is clipped off-screen or the submit button can't be reached. This is a default requirement whenever the user is talking about a mobile view — they should not have to ask for it.

**Required patterns:**
- **Use a bottom-sheet (or full-screen) layout** for non-trivial mobile forms: `fixed inset-x-0 bottom-0 max-h-[92vh] flex flex-col`, with a `flex-shrink-0` header, a `flex-1 overflow-y-auto` scrollable body, and a `flex-shrink-0` sticky footer holding the actions. The body scrolls; the actions stay reachable.
- **Respect the safe area** on the sticky footer: `padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));`.
- **⚠️ Teleport fixed overlays to `<body>`.** The mobile shell's `#shell-content` carries `.mobile-stagger`, whose `m-rise` animation leaves a `transform` on every direct child. A CSS `transform` makes that element the containing block for any `position: fixed` descendant — so a bottom-sheet's `bottom-0` / `max-h-[92vh]` resolve against the tiny wrapper instead of the viewport and the form gets clipped. Wrap any fixed sheet/FAB/overlay in `<template x-teleport="body">…</template>` (inside an Alpine `x-data` scope) so it escapes the transformed ancestor. Use `z-[60]+` for teleported sheets so they sit above the bottom tab bar (`z-40`).
- Inputs full-width (`w-full`), comfortable tap targets, and the design-system input/button tokens. Keep [[Mobile = Creative + Animated]] in mind — animated, on-palette, not a stripped-down desktop form.

> Reference implementation: `resources/views/components/schedule-session-modal.blade.php` (teleported bottom-sheet with scrollable body + sticky footer).

---

## Skills

### `frontend-design`
Use the `frontend-design` skill whenever the user asks to build or design new UI — pages, components, modals, cards, sections, or any visual interface work. It generates production-grade, polished frontend code that avoids generic AI aesthetics.

**Trigger on:** "build a page", "create a component", "design a section", "add a new view", "make a modal/card/form", or any request that involves writing new Blade/HTML/CSS.

**Do not trigger on:** pure backend changes, bug fixes to existing UI, or minor copy/label edits.

The skill must still respect all Design Rules above — it enhances quality but does not override the rule against modifying existing UI without instruction.

#### Design System — the skill MUST follow these patterns exactly

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
- Page wrapper: `max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4` (member pages) or `space-y-6` inside `admin-club` layout
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

**Rule:** Before building any new UI element, check this catalog. If a component covers the need, use it — do not re-implement it inline. When a new reusable component is created, add it here immediately.

All components live in `resources/views/components/` and are called as `<x-{name}>`.

### Dropdowns / Form Selects

| Tag | Purpose | Key props |
|-----|---------|-----------|
| `<x-birthdate-dropdown>` | Day/Month/Year cascading picker with live age badge | `name`, `id`, `value`, `label`, `minAge`, `maxAge`, `minYear`, `maxYear`, `required`, `error` |
| `<x-blood-type-dropdown>` | Blood type selector (A+, A-, B+, etc.) | `name`, `id`, `value`, `label`, `required`, `error` |
| `<x-call-code-dropdown>` | Country dial-code `<select>` | `name`, `id`, `value`, `label`, `required`, `error` |
| `<x-country-code-dropdown>` | Searchable calling-code picker (Alpine) | `name`, `id`, `value`, `required`, `error` |
| `<x-country-dropdown>` | Searchable country name picker (Alpine) | `name`, `id`, `value`, `label`, `required`, `error` |
| `<x-currency-dropdown>` | Searchable currency picker (Alpine) | `name`, `id`, `value`, `label`, `required`, `error` |
| `<x-gender-dropdown>` | Gender selector | `name`, `id`, `value`, `label`, `required`, `error` |
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

> **`<x-stat-card>` sparkline alignment rule:** Always pass `:spark-data` from the same domain as the card's value (e.g. revenue card → monthly revenue array, not monthly member counts). When no real time-series exists yet, pass `array_fill(0, 12, 0)` as a flat baseline — never reuse another card's unrelated data array. The component is `flex flex-col` with `mt-auto` on the sparkline, so it always pins to the bottom of the card in equal-height grid rows. Do not remove these classes from the component.
>
> **`<x-stat-card>` constrained eager-loading:** When loading models for stat card data via constrained eager loads (e.g. `user:id,name,...`), always include `updated_at` in the column list. The `member-card` component uses `$member->updated_at->timestamp` for image cache-busting — omitting it causes a null-dereference that silently breaks the AJAX response.
>
> **`@json()` in Blade with nested arrays:** Do NOT write `@json($arr['key'] ?? [0,0,...,0])` — Blade's bracket-matcher chokes on `['key']` followed by `?? [literal array]` inside a single `@json()` call. Pre-assign to a `@php` variable first, then use `@json($variable)`.
| `<x-financial-chart>` | Monthly income/expense Chart.js bar chart with drill-down modal | `monthlyData`, `transactions`, `currency`, `canvasId`, `maintainAspectRatio`, `canvasHeightAttr`, `containerClass` |
| `<x-location-map>` | Leaflet map with address search and lat/lng hidden inputs | `id`, `latName`, `lngName`, `addressName`, `lat`, `lng`, `address`, `defaultLat`, `defaultLng`, `height`, `required` |
| `<x-client-paginator>` | Client-side pagination for any list filtered via JS. Renders the container div and injects the `ClientPaginator` JS class (once per page). Instantiate in JS: `new ClientPaginator({ itemsSelector, containerId, perPage, countBadgeId, scrollTargetId, labelSingular, labelPlural, filterFn })` then call `.refresh()` when filters change. Registered in `window._pagers[id]` for inline `onclick` access. | `id` (required), `perPage` (default `20`) |

### Form Utilities

| Tag | Purpose | Key props |
|-----|---------|-----------|
| `<x-rich-text-editor>` | WYSIWYG rich-text editor (Alpine + `contenteditable`). Toolbar: bold/italic/underline/strikethrough, text color, H1/H2/H3/paragraph/quote, bullet+numbered lists, indent, align, link (inline URL bar, no native prompt)/unlink, horizontal rule, undo/redo, clear. Submits HTML via a hidden `<textarea name="{name}">`. Supports `dir="rtl"`. | `name`, `id`, `value`, `dir`, `placeholder`, `minHeight` |
| `<x-image-upload>` | Inline image upload with crop preview (uses takeone-cropper) | `id`, `name`, `width`, `height`, `shape`, `folder`, `filename`, `uploadUrl`, `currentImage`, `placeholder`, `placeholderIcon`, `buttonText`, `rounded`, `showPreview` |
| `<x-takeone-cropper>` | Raw cropper widget (used inside `image-upload`). Pass `:uploadAsIs="true"` to show a second "Upload As Is" button alongside "Crop & Apply". Customize its label with `uploadAsIsText`. | `id`, `width`, `height`, `shape`, `folder`, `filename`, `uploadUrl`, `currentImage`, `buttonText`, `buttonClass`, `mode` (ajax\|form), `inputName`, `canvasHeight`, `uploadAsIs`, `uploadAsIsText` |
| `<x-schedule-time-picker>` | Days-of-week multi-select + start/end time inputs | `id`, `daysName`, `startTimeName`, `endTimeName`, `selectedDays`, `startTime`, `endTime`, `required`, `showLabels` |
| `<x-social-links-editor>` | Editable list of social media links (add/remove/reorder) | `links`, `containerId` |
| `<x-social-link-row>` | Single social link row — used internally by `social-links-editor` | `index`, `link` |

---

## Coding Conventions

- PHP 8.2 — use named arguments, match expressions, null-safe operator where appropriate
- Blade components live in `resources/views/components/`
- Use `@stack('scripts')` / `@stack('modals')` to inject page-specific JS and modals
- AJAX responses return `response()->json(['success' => true/false, 'message' => '...'])`
- Authorization checks follow the pattern: super-admin → own profile → family relationship → club admin of member
- Throttle middleware is applied on all write routes (`throttle:member-write`, `throttle:admin-write`, `throttle:uploads`, etc.)
