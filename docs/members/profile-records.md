# Profile records — health, goals, tournaments, contact details

**Purpose.** What the member profile's self-service tabs store and the few
rules that are not obvious from the views. Consolidated 31 Oct 2026 from the
Feb 2026 notes in `Documentation/` (TODO_height_bmi, TODO_radar_chart,
TODO_goals, TODO_tournaments, TODO_social, FAMILY_EDIT_UPDATES), checked
against the code. Create flows for goals / attendance / event log are listed
in CLAUDE.md Part 9 and not repeated here.

## Main files

| Area | Where |
|---|---|
| Profile pages | `app/Members/resources/views/profile/show.blade.php` (desktop), `profile/mobile/show.blade.php`, `family/show.blade.php` (super-admin twin) |
| Writes | `app/Members/Controllers/MemberController.php` — routes `member.*` and `family.*` in `app/Members/routes.php` both point here |
| Models | `app/Members/Models/{HealthRecord,Goal,TournamentEvent,PerformanceResult,NotesMedia}.php` |
| Daily goal nudge | `app/Members/Commands/SendDailyGoalEncouragement.php` (`goals:daily-encouragement`) |

## Health records and BMI

- `health_records`: `height` (cm, validated 50–250), `weight`, `bmi`,
  body-fat %, body-water %, muscle mass, bone mass, visceral fat, BMR,
  protein %, body age, `recorded_at`.
- BMI = weight(kg) / height(m)². The modal computes it live in JS
  (`calculateBMI()`); on **create** the server also derives it (1 decimal)
  when weight and height are sent and BMI is not. **Update does not
  re-derive it server-side.** No height → no BMI.
- The Body Composition radar (Chart.js `radar`, `fill: true`,
  `borderWidth: 3`) compares two chosen records, height included.

## Goals

- `goals`: title, description, `start_date`, `target_date`,
  `current_progress_value`, `target_value`, `unit`, `status`,
  `priority_level`, `icon_type`, `before_proof`/`after_proof`,
  `completed_at`. Progress % is computed on the model.
- Success rate = completed ÷ total × 100. Summary cards filter the list.
- Only the owner or a guardian sees the edit control; updates are AJAX.
- There is no delete route for goals.

## Tournaments (self-reported achievements)

- `tournament_events` (title, type, sport, date, time, location,
  participants_count, optional `club_affiliation_id`) → many
  `performance_results` (medal_type, points, description) and `notes_media`
  (note text, media link).
- Award counts on the profile come from `performance_results`; only VERIFIED
  claims count as medals (memory: *Achievement verification*).
- Desktop filters the table by sport.

## Contact details on a person

- `users.mobile` is JSON `{code, number}` (registration writes the same shape).
- `users.social_links` is JSON `{platform: url}`; the form posts a list of
  `{platform, url}` rows and `MemberController::update()` folds it into the
  map (one URL per platform). Displayed sorted by platform name (`ksort`).
- `users.motto`, max 500 characters, shown in place of the old hard-coded quote.
- Editing a family member goes through the same form and controller as a
  member (`family.edit` redirects to `member.edit`; `family.update` and
  `family.upload-picture` call `MemberController`). The old multipart
  `uploadFamilyMemberPicture()` and its `public/images/profiles` folder are
  gone; pictures follow *A Person's Photo Has One Home* (CLAUDE.md).
