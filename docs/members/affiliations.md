# Affiliations and skills — a member's club history

**Purpose.** The Affiliations tab of a member profile is the member's club
history: every club they belonged to, for how long, what they trained, who
taught them and which skills they came away with. Consolidated 31 Oct 2026 from
the Feb 2026 notes in `Documentation/` (AFFILIATIONS_*, SETUP_AFFILIATIONS,
TODO_affiliations*), checked against the code.

Verification of these records (club-confirm / peer vouch) is described in the
memory notes *Achievement verification* and *Skill provenance*; this file does
not repeat it.

## Main files

| What | Where |
|---|---|
| Affiliation (one member at one club) | `app/Clubs/Models/ClubAffiliation.php` — table `club_affiliations` |
| Skill learned during an affiliation | `app/Members/Models/SkillAcquisition.php` — table `skill_acquisitions` |
| Photos / certificates attached to one | `app/Members/Models/AffiliationMedia.php` — table `affiliation_media` |
| Subscriptions grouped under one | `club_member_subscriptions.club_affiliation_id` (migration `app/Clubs/database/migrations/2026_01_26_120002_…`) |
| Created automatically on joining | `App\Clubs\Support\SubscriptionService::syncAffiliation()`, `App\Members\Support\ProfileHistorySync`, `PlatformController` (join) |
| Member self-service endpoints | `MemberController` — `member.store-affiliation`, `update-`, `destroy-`, `…-instructor`, `…-skill`, `affiliation-activities`, media upload (`app/Members/routes.php`) |
| Desktop view | `app/Members/resources/views/profile/partials/affiliations-enhanced.blade.php` (member) and `family/partials/affiliations-enhanced.blade.php` (super-admin view) |
| Mobile view | `profile/mobile/show.blade.php` (`affiliationSheet`), `personal/affiliations.blade.php` |
| Data for the tab | `FamilyController::profile()/show()` and `MemberController` build `allSkills`, `distinctSkills`, `totalInstructors` |

## Data model

- `club_affiliations`: `member_id` (owner — **not** `user_id`), `tenant_id`
  (nullable: an off-platform club is free text in `club_name`), `club_name`,
  `logo`, `location`, `start_date`, `end_date` (null = ongoing), `coaches`
  (JSON), `description`, plus the shared verification columns.
- `coaches` holds two shapes: legacy plain name strings and `{name, user_id}`
  objects added through the UI. Always read it through
  `ClubAffiliation::instructorList()`, which normalises both.
- `skill_acquisitions`: `user_id` (denormalised owner — what every reader
  outside the tab queries), `club_affiliation_id` (nullable), `package_id`,
  `activity_id` or free-text `activity_name`, `instructor_id`
  (→ `club_instructors`), `skill_name`, `proficiency_level`
  (beginner/intermediate/advanced/expert), `start_date`, `end_date`,
  `duration_months`, `notes`, `icon`.

## Data flow

1. **Joining a club** → `SubscriptionService::syncAffiliation()` does
   `firstOrCreate` on (member, club) — one affiliation per member per club —
   links the subscription to it, recomputes the span from ALL that member's
   subscriptions at the club (earliest start; end stays NULL while any is
   active/pending), collects coach names from the package activities, and adds
   one `SkillAcquisition` per package activity not already on the affiliation.
2. **Profile history** → `ProfileHistorySync` writes club-sourced affiliation
   rows (idempotent, create-only, never touches a member's own entry).
3. **Self-service** → the member (or guardian) adds affiliations, instructors,
   skills and media by hand; these start `self_reported`.

## Display rules

- Age during the affiliation is computed from `birthdate` at start and end
  (`calculateAgeAtDate()`, guarded with `function_exists` — see CLAUDE.md
  coding conventions). No birthdate → no age shown.
- Summary row: total clubs, unique skills (distinct `skill_name`), total
  training duration, instructors.
- A skill filter narrows the timeline to affiliations carrying that skill.

## Rules and limitations

- **The Feb 2026 "each skill appears once per lifetime, no end date" rule was
  only ever implemented in `AffiliationsDataSeeder`.** Live code does not
  enforce it: `syncAffiliation()` de-duplicates per affiliation + activity,
  self-add accepts any name and an `end_date`. Only the summary card counts
  distinct names. Open question in `docs/plans/backlog.md`.
- ⚠️ **`database/seeders/CleanAndReseedAffiliations.php` deletes EVERY row of
  `skill_acquisitions`, `affiliation_media`, `club_member_subscriptions`,
  `club_package_activities`, `club_affiliations`, `club_packages` and
  `club_activities`** — real clubs' packages and members' subscriptions. Never
  run it (or `AffiliationsDataSeeder`, which writes fake clubs and users into
  the real tables) on this box. The old setup notes suggested both, and
  `migrate:fresh --seed`; all three are forbidden by CLAUDE.md RULE #1/#2.
