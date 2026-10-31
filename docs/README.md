# docs/ — TAKEONE documentation

The one documentation folder (the old `Documentation/` was merged in on 31 Oct 2026).
Rules for writing docs: `STANDARDS.md` → "Documentation". Project rules: `../CLAUDE.md`.
Design sources (mockups, handoffs) live in `../drafts/`, not here.

## platform/ — how the whole platform works
| Doc | What it covers |
|---|---|
| `map.md` | Map of the codebase: modules, shells, where things live |
| `admin-panels.md` | Roles & permissions, super-admin, `/admin` routes, club-admin middleware |
| `mcp.md` | The MCP server: every tool, auth scope, how to add one |
| `uploads.md` | Upload rules, `StoragePath`, the cropper package, known gaps |
| `frontend-css.md` | How `app.css` is organised, Bootstrap compat layer, responsive habits |
| `performance.md` | DB connections, caching, query batching |
| `account-recovery.md` | Admin password reset and member recovery |
| `data-promotion.md` | Promoting staging data to production |
| `house-cleaning.md` | Register of everything waiting to be removed — and what was |

## members/ — the person
`affiliations.md` (club history, skills) · `profile-records.md` (health/BMI, goals, tournaments, social) · `registration.md` (sign-up, verification)

## clubs/ — the tenant
`club-modal.md` (create/edit a club) · `explore.md` (finding clubs nearby)

## events/ — competition
| Doc | What it covers |
|---|---|
| `events.md` | **Start here.** Event-type packages, the contract, shared tournament base |
| `public-entry.md` | Public event pages and entry by strangers |
| `entry-billing.md` | Entry fees, channels, club invoices |
| `division-merging.md` | Merging small divisions safely |
| `open-mat.md` | Casual pickup-fight scoreboard |
| `matside-camera.md` | Mat cameras and desk replay |
| `bout-stat-sheet.md` | The bout page's Analytics tab (`BoutStats`, `<x-bout-analytics>`), behind `BOUT_ANALYTICS_TAB` |
| `vs-screen.md` | The one VS screen every mat, the bout page and the boutcam draw |
| `sports-event-types-spec.md` | HISTORICAL (Jun 2026) — kept for its rule-book research |

## plans/ — what is next
`backlog.md` (**every open item found in the old docs and audits**) · `future-plans.md` · `live-upgrade-plan.md` · `dev-board-plan.md` · `major-change/` (parties, new UI, desktop parity)

## research/
`competitor-research-2026-10.md` · `qr-designer-study.md` · `club-questionnaire/` · `federation-questionnaire/`

## audits/ — dated records, kept for their reasoning
`2026-08/SPORTS_ARCHITECTURE_AUDIT.md` (+ its `EVIDENCE_BUNDLE`) · `2026-08/STORAGE_AND_CLEANUP_AUDIT.md` — each has a 31 Oct 2026 status table.

## archive/
`mockup-backups/` — June 2026 snapshots of the events and schedule mockups.
