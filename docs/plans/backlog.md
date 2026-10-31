# Backlog — open items carried over from old notes

Open items found while merging `Documentation/` into `docs/` (31 Oct 2026).
Each line: the item · where it came from · where to look. Done items were
dropped. Items marked **(found)** were not in any old note — they turned up
while checking the old claims against the code.

## Members — affiliations and skills

- **(found) Skills added on joining a club have no `user_id`.** `syncAffiliation()` creates `SkillAcquisition` rows without the owner, so the people profile, MCP `get_member`, the trainer page, `BeltRank` and `EntryService` (all query `where('user_id', …)`) never see them · consolidation check · `app/Clubs/Support/SubscriptionService.php` (`skillAcquisitions()->create`, ~line 339); compare `MemberController::storeAffiliationSkill()` which sets it.
- Decide whether a skill may appear once per person for life (the Feb 2026 decision) or once per club; today only the seeder follows the old rule · `FINAL_AFFILIATIONS_IMPLEMENTATION.md` · `SubscriptionService::syncAffiliation()`, `MemberController::storeAffiliationSkill()`.
- Mobile parity: add a skill and add an instructor to an affiliation from the phone (desktop only today; mobile can add an affiliation, and only for one's own profile) · `TODO_AFFILIATIONS_IMPLEMENTATION.md` + memory *Skill provenance* · `app/Members/resources/views/profile/mobile/show.blade.php` (`affiliationSheet`).
- Retire or fence `CleanAndReseedAffiliations` (wipes clubs' packages, activities and members' subscriptions) and `AffiliationsDataSeeder` (fake clubs/users, hard-coded password) — register in house cleaning · `SETUP_AFFILIATIONS.md` · `database/seeders/`, `docs/platform/house-cleaning.md`.
- Idea: export / print a member's affiliation history as PDF · `AFFILIATIONS_ENHANCEMENT_COMPLETE.md` · `profile/partials/affiliations-enhanced.blade.php`.
- Idea: skill progression chart over time, and comparing two members · `AFFILIATIONS_ENHANCEMENT_COMPLETE.md` · same partial.
- Skill filter and tournament sport filter are native `<select>` (Design Rule #4) · consolidation check · `profile/partials/affiliations-enhanced.blade.php` (`#skillFilter`), `profile/show.blade.php` (`#sportFilter`).

## Members — goals and health

- Delete a goal (no route exists) · `TODO_goals.md` · `app/Members/routes.php` (`member.update-goal`), `MemberController`.
- Goal progress history (a progress-history table instead of overwriting `current_progress_value`) · `TODO_goals.md` · `app/Members/Models/Goal.php`.
- Goal categories / types · `TODO_goals.md` · `Goal` (only `icon_type` today).
- Notifications on goal deadline and on completion (only the daily encouragement exists) · `TODO_goals.md` · `app/Members/Commands/SendDailyGoalEncouragement.php`.
- Share goals between family members · `TODO_goals.md` · `MemberController::storeGoal()`.
- Goal progress trend charts · `TODO_goals.md` · `app/Members/resources/views/personal/progress.blade.php`.
- **(found)** Editing a health record does not re-derive BMI on the server (create does) · consolidation check · `MemberController::updateHealth()` vs `storeHealth()`.

## Explore (Clubs module)

- **(found)** `/clubs/nearby` returns every nearby club owner's email and mobile to any signed-in user — trim to what the card shows (the new-UI page already does) · `EXPLORE_CLUBS_IMPLEMENTATION.md` (documented the fields) · `PublicClubController::nearby()`.
- `/clubs/nearby` and `/clubs/all` have no throttle, no pagination and load every club; add rate limits, paging or a bounding-box query, and an index on `tenants.gps_lat/gps_long` · `EXPLORE_CLUBS_IMPLEMENTATION.md` (security + performance notes) · `app/Clubs/routes.php`, `PublicClubController::all()`.
- **(found)** Classic explore loads Leaflet from unpkg (self-host rule) although `public/vendor/leaflet/` exists; `<x-fields.location-map>` does the same · consolidation check · `public/partials/explore-runtime.blade.php` lines 6/15, `resources/views/components/fields/support/location-map-runtime.blade.php`.
- **(found)** Classic explore map uses an `http://` tile URL (mixed content on HTTPS) and hides the OSM attribution OSM requires · consolidation check · `explore-runtime.blade.php` `initMap()`.
- **(found)** The location-picker map opens on Dubai when no fix exists, not Bahrain · consolidation check · `explore-runtime.blade.php` `openMapModal()`.
- Feature tests for `clubs.nearby` / `clubs.all` (validation, distance order, clubs without GPS last) — the old manual test checklist was never ticked · `TODO_explore_clubs.md` · `tests/Feature/`.
- Ideas: adjustable radius, marker clustering, saved/favourite clubs, km/miles toggle · `EXPLORE_CLUBS_IMPLEMENTATION.md`, `EXPLORE_LOCATION_SORTING_UPDATE.md`.

## Registration

- **(found)** The email-verify link logs the holder in and marks the session two-factor-verified, for 24 h, even after the email is already verified — effectively a 2FA-skipping login link; review · consolidation check · `app/Members/routes.php` (`verification.verify`), `WelcomeEmail` (`auth.verification.expire`).
- **(found)** `/register` requires a birthdate, while CLAUDE.md says birthdate is never required on any form — decide which is right · consolidation check · `RegisteredUserController::store()`.

## Security and platform (from the Mar 2026 enterprise-readiness audit and the Feb admin notes)

- ~~Unsafe image uploads~~ — **fixed 31 Oct 2026**: both savers now use `StoresBase64Images` (test in `UploadSecurityTest`). Left: move them to `StoragePath` folders · `ClubEventController`, `ClubAchievementController`.
- `/admin/backup` JSON restore runs MySQL-only `SET FOREIGN_KEY_CHECKS` (broken on Postgres), trusts table names from the uploaded file, and returns `$e->getMessage()` · admin notes · `restoreBackup()`.
- JSON backup download covers a fixed list of 28 tables (misses every newer table); `exportAuthUsers()` exports every password hash; neither is audit-logged · admin notes · `downloadBackup()`, `exportAuthUsers()`.
- `SuperAdminSeeder` has the password hard-coded and stores gender `'m'` (breaks Male/Female). Change the live super-admin password if it still matches · `SUPER_ADMIN_CREDENTIALS.md` · `database/seeders/SuperAdminSeeder.php`.
- Admin password resets (`member.reset-password`, regenerate-password) don't end the member's other sessions; only forgot-password and role changes do · audit #14 · `MemberController`, `AdminPasswordReset`.
- 2FA is optional for super-admins (`RequiresTwoFactor` only challenges users who turned it on) · audit · `RequiresTwoFactor`.
- No GDPR self-service export / account deletion; `HealthRecord` values stored unencrypted · audit.
- No automatic tenant scoping (no `BelongsToTenant` global scope); every query adds `tenant_id` by hand · audit.
- `ClubGalleryController` loads every image with `->get()` (unbounded) · audit · `ClubGalleryController.php:23`.
- Member search uses `LIKE '%…%'` (no Scout / full-text) · audit · `MemberLookup`, `PlatformController`.
- Duplicate subscriptions checked in code only — no unique DB constraint · audit #4 · `SubscriptionService`.
- `<x-club-modal>` loads `qrcodejs` (jsDelivr) and `cropme` (unpkg) from CDNs, and runs its own cropper instead of `<x-takeone-cropper :inline>` · club-modal notes · `resources/views/components/club-modal*`.
- `/admin/members/{id}` binds the numeric id (super-admin only) · admin notes.
- Unbuilt from the Jan admin plan: Policy classes (only one exists), a club settings table / member-code prefixes, member invitations · `TODO_ADMIN_PANEL.md`.

## Scoring and events (from the 29 Aug release audits, re-checked 31 Oct 2026)

- Karate: declaring a winner with reason `points` stores `other` — owner to decide if intended · `PHASE_0_SCORING_SAFETY_REPORT.md` · `app/Scoreboard/Sports/Karate/Mat/Scoring.php:945`.
- Taekwondo: cached `punWinner` restored without checking it is `aka`/`ao` (Karate checks) · phase-0 report · `app/Scoreboard/Sports/Taekwondo/Mat/MatState.php:377`.
- Taekwondo: both corners at the gam-jeom limit → `roundWinner()` picks by check order (probably unreachable) · phase-0 report · `MatState.php:228–232`.
- No test seam for scoring rules (Karate's 25 private methods sit behind `Scoring::apply()`, which needs DB, cache, log and cameras) · phase-0 report.
- Live bout state is a cache blob (240-min TTL) nothing rebuilds from `event_match_events` · phase-0 report + `docs/audits/2026-08/SPORTS_ARCHITECTURE_AUDIT.md` DATA-001.
- Missing tests: device-token scoring doors (`*-scoreboard.token-command`); 2FA middleware on scoring routes for a 2FA user; every registered event type satisfies `EventType`; scoring survives a missing `event_cameras` table (`CameraFleet::observe()`) · phase-0/phase-1 reports.
- Document `EVENT_MATCH_LOG` in `.env.example` · `F1_AUDIT_LOG_CONFIG_REVIEW.md`.
- SEC-004: `PublicClubController::joinEvent()` (`clubs.events.join`) creates an entry directly — skips the enrolment gate, bumps `spots_taken` raw, no visibility check · `SPORTS_ARCHITECTURE_AUDIT.md`.

## Storage (from `docs/audits/2026-08/STORAGE_AND_CLEANUP_AUDIT.md`, still open)

- APKs served straight from `public/app/`.
- `WizardRegistrationController::uploadTemp()` keeps the client's file extension; `moveTempFile()` is dead code.
- `SwitchDatabase` rewrites `.env` with no lock or backup.
- `Tenant`, `ClubAffiliation`, `ClubFacility`, `ClubPackage`, `User` don't declare `$fileUploads` — deleting one orphans its files.
