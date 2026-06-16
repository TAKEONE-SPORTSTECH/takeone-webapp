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

## Financials Feature Status

- Step 1 ✅ `PlatformController@joinClub` auto-creates a `ClubTransaction` on package registration
- Step 2 ✅ `getMonthlyFinancials()` uses real DB query — dashboard chart works
- Step 3 ✅ "Cash to Collect" sums `amount_due` from unpaid subscriptions
- Step 4 🔜 Mark subscription as paid from members admin page — **not started**

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

### UI / Display

| Tag | Purpose | Key props |
|-----|---------|-----------|
| `<x-toast-notification>` | Global toast container — **include once per layout**; call `window.showToast(type, message)` from JS | `position` (default `top-right`) |
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
