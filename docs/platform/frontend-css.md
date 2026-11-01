# Front-end CSS — Tailwind, the Bootstrap compatibility layer, `tf-*`

How the stylesheet is organised, and why Bootstrap class names still work
although Bootstrap is not loaded. The design rules themselves are in CLAUDE.md
Part 5. Checked against the code on 31 Oct 2026.

## Main file

`resources/css/app.css` (Tailwind CSS 4, built by Vite). The bundle is
**prebuilt**: a Tailwind class used nowhere else does nothing until the assets
are rebuilt, which is why several components set sizes inline.

In file order:

1. **Base reset** (`@layer base`). Plain `button`, `input`, `textarea` and
   `select` get a neutral reset, **except** elements carrying the Bootstrap
   classes (`.btn`, `.form-control`, `.form-select`, `.form-check-input`), so the
   two systems do not fight. `--primary` (raw HSL channels) and the theme tokens
   are defined here.
2. **Bootstrap compatibility layer** (`@layer components`). Bootstrap
   *component* class names written with Tailwind `@apply`: buttons (`.btn`,
   `.btn-primary`, `.btn-outline-*`, `.btn-sm`, …), cards, forms (`.form-control`,
   `.form-select`, `.input-group`, `.is-invalid`, …), tables, badges, alerts,
   nav/tabs, modals, list groups, progress, spinners, and some spacing helpers
   (`me-*` and friends).
3. **`tf-*` theme classes** (30): repeated Tailwind runs pulled out once —
   form controls (`tf-input`, `tf-select`, `tf-textarea`, `tf-file`, `tf-time`,
   `tf-label`, `tf-error`, `tf-input-group`), the custom-dropdown parts
   (`tf-dropdown-trigger|menu|panel|item|item-sm`), the auth pages
   (`tf-auth-bg[-scroll]`, `tf-auth-box[-lg]`, `tf-auth-card`, `tf-auth-btn[-outline]`,
   `tf-auth-link`, `tf-auth-grain`, plus the global `@keyframes slideIn`), and
   layout (`tf-container`, `tf-card[-light]`, `tf-section-title`,
   `tf-empty[-icon]`, `tf-stat-grid`, `tf-stat-card`).
4. **Page and component sections** (`PAGE-SPECIFIC STYLES` onward): CSS moved
   out of inline `<style>` blocks, one commented section per page or component
   (club public page, explore, family, profile modal, cropper widget, toast, club
   modal, admin screens, …).
5. **The mobile motion system and RTL mirroring** (`m-hero`, `m-card`,
   `mobile-stagger`, …; mirrored Bootstrap Icons) — see CLAUDE.md Part 6.

## Bootstrap is not loaded

No Bootstrap CSS or JS is shipped in the app shell; the switch was finished in
Feb 2026. The one exception is the standalone bout-review page
(`app/Events/resources/views/personal/mobile/bout-video.blade.php`), which
loads Bootstrap 5.3 from jsDelivr inside its own shell.

- **Styles:** the compatibility layer above.
- **Behaviour:** Alpine.js, and the `data-bs-*` bridge in
  `resources/views/layouts/app.blade.php` (CLAUDE.md, *Technical Notes*).
- **Icons:** only the Bootstrap Icons font. It is still loaded from jsDelivr in
  the layouts, which is one of the two exceptions *Self-Host Everything* allows.

## Rules

- **New UI uses the design-system tokens** (`bg-primary`, `rounded-xl`, …).
  Bootstrap class names are only for files that already use them (CLAUDE.md,
  *What to avoid*).
- **Reach for a `tf-*` class** before re-typing a long Tailwind run for an input,
  label, error, dropdown or auth surface. To change one of these looks across the
  platform, edit its `@apply` line in `app.css`.
- **Inline styles that stay inline:** dynamic conditional classes (logic, not
  styling), email templates, standalone print pages (`invoices/receipt`), and
  styles inside `window.open()` print popups.
- **Responsive habits** (all still in use): wrap tables and tab rows in
  `overflow-x-auto`; give a Chart.js canvas with `maintainAspectRatio: false` a
  sized parent (`h-48 md:h-72`); clamp pop-overs to the screen with
  `width: min(320px, calc(100vw - 2rem))`. Mobile and desktop views that differ
  in layout are separate files (CLAUDE.md, *Mobile / Desktop Separation*).
