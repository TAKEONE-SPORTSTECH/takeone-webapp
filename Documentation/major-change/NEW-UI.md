# New UI — build plan (branch `new-ui`)

The approved design is the concept artifact "TAKEONE Explore Concept"
(https://claude.ai/artifact/KfNq4Mgy5iVcFcAS4fzNiE, version 11, 2026-10-03).
The goal is to ship THAT design, faithfully, and replace the old one.

## How it is shipped without breaking anything (RULE #1)

`/var/www/takeone` IS the live stage site: a saved file is live at once, and a
branch does not isolate the working tree. So:

- Every new screen is a **new file** beside the old one. Old views, layouts and
  controllers are not edited, except for the one-line switch that chooses which
  view to render.
- One flag, `NEW_UI` (`config('features.new_ui')`), values:
  - `off` (default) — everyone gets the old design, exactly as today.
  - `preview` — the old design stays the default; a person allowed to preview
    can turn the new one on for their own session (`/me/new-ui/on`, `/off`).
  - `on` — everyone gets the new design.
- Turning the flag back to `off` restores the old design instantly.
- Retiring the old views is a separate, final step (House Cleaning register).

## Decisions taken with the owner (2026-10-03)

| Decision | Note |
|---|---|
| Content sits **below** every banner, with a gap — never riding up over it | Overrides CLAUDE.md Design Rule #6's overlap for the new UI. CLAUDE.md to be updated at cut-over. |
| The Play side of the Play/Manage switch is the person's **round** profile picture | A deliberate exception to "Profile pictures are portrait 3:4", for this one control only. |
| Avatar = who you are (account menu); Play/Manage = what you are doing; organisation picker at the top of the Manage sidebar = where you are managing | Replaces the two-option Personal/Business switcher. |
| AI assistant opens from an animated "dancing robot" next to Chats | Custom SVG, like `<x-belt-icon>`; honours reduced motion. |
| Explore tabs: For you · Activities · Offers · Clubs · Events · Coaches · Venues · Clinics · Specialists · Federations · Shop | A tab with no data behind it is not rendered (existing honesty rule). |
| Map is a switch, off by default; OpenStreetMap | Leaflet, tiles over https. |
| Light and dark theme | Dark needs every existing page made dark-ready — its own phase. |

Icons: the concept's hand-drawn glyphs are rendered with their Bootstrap Icons
equivalents (CLAUDE.md: Bootstrap Icons exclusively). The robot, medals and
belts stay drawn components.

## Phases

Each phase: build behind the flag → verify in a browser (desktop + phone) →
owner review → next. Any migration takes a verified backup first (RULE #2).

| # | Phase | Contents | State |
|---|---|---|---|
| 0 | Switch | `NEW_UI` flag, preview page `/me/new-ui`, `App\Ui\NewUi` | ✅ 2026-10-04 — stage runs `NEW_UI=preview` |
| 1 | Shell | v2 stylesheet (concept tokens); desktop top bar; phone header + bottom tabs (Feed · Schedule · Challenge · Events · Market); Play/Manage pill with round avatar; robot AI button; account menu | ✅ 2026-10-04 (robot button waits for phase 6; Manage opens today's screens until phase 5) |
| 2 | Explore | hero + search (What · Where · Who); tab bar; For you rows; Activities catalogue + activity page; Clubs/Events/Coaches/Shop/Federations lists; map switch; activity chips | ✅ first cut 2026-10-04 — For you, Activities, Clubs (+ map), Events, Coaches. Since added (2026-10-04): map switch on every list tab incl. All (map beside the rails); Shop tab (published products → existing product page) and Federations tab (card opens that federation's events, as there is no public federation page); Offers/Venues/Clinics/Specialists shown as designed "coming soon" tabs. Still to do: the new activity page (tiles open the existing one), Who filter, real data for the four coming-soon tabs |
| 3 | Feed | stories band, composer, All/Following/My clubs, post kinds (club update, result, poll, highlight, challenge, offer), desktop side columns | ✅ 2026-10-04 — reuses newsFeed() + post-card unchanged. Stories no longer exist (dropped July 2026): the band shows your clubs. Result/challenge/offer post kinds wait for data |
| 4 | Challenge | record band, invitations, live duels, challenges with rings, leaderboard, rivals | ✅ 2026-10-04 — same data and endpoints. Streak / club rank / rivals not computed yet: record shows points, W–L, live duels, joined |
| 5 | Manage | sidebar shell, organisation picker, overview dashboards (business, club, venue, federation); existing admin screens open inside | ✅ 2026-10-04 — `/me/manage`; figures via `ChainDashboardService::forClubs` (same rules as the chain dashboard); menu opens the existing admin screens |
| 6 | Ask TAKEONE AI | chat page; new "find" skill on the existing Copilot; server-side search tools with the same permissions as the UI | ✅ 2026-10-04 — `/me/ask` + robot button. `App\Ui\Ask\AskEngine`: built-in reader first, the AI only when the reader can't tell, and its output is held to what the message says; the search is plain queries; plans per child from real packages |
| 7 | Dark theme | tokens + every existing page dark-ready | ✅ first cut 2026-10-04 — System/Light/Dark in the account menu and phone drawer (cookie `t1_theme`, default light). Remaps the classic screens' Tailwind tokens, so most old pages follow; inline-styled colours remain light |
| 8 | Data the design needs | trainer hourly rate + availability; offers; venue bookings; clinics/specialists as tenants; class age groups; sibling discounts | |
| 9 | Cut-over | `NEW_UI=on`, verify, then retire old views as a separate step | |

## Where things are

- Flag + decision: `config/features.php` `new_ui`, `app/Ui/NewUi.php`.
- Switch points in old files (each a guarded `@if`/`if`, off = unchanged):
  `resources/views/layouts/app.blade.php` (stylesheet + top bar),
  `app/Members/resources/views/layouts/personal-mobile.blade.php` (bottom tabs),
  `resources/views/partials/mobile-header.blade.php` (phone header),
  `app/Clubs/Controllers/PublicClubController.php@index` (Explore).
- New files: `app/Ui/**`, `public/css/takeone-v2.css`, `public/vendor/leaflet/` (vendored, BSD-2).
- Tests: `tests/Feature/Ui/` (30 tests).
- Also touched (guarded or additive): `partials/personal-desktop-subnav` (hidden in new UI), `PersonalMobileController@home`, `ChallengeController@index`, `ChainDashboardService::forClubs` (extracted from `build`), `bootstrap/app.php` (`t1_theme` cookie unencrypted).

## Data gaps found while building (stage, 2026-10-04)

8 clubs (2 with map coordinates) · 13 events · 53 activities (0 pictures, 10
descriptions) · 0 personal trainers · 0 offers · 0 shop products · no venue
booking, clinics or specialists · no public federation page. A tab with
nothing behind it is not shown.
