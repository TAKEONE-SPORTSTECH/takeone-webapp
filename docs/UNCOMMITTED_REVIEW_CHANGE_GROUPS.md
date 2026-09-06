# Proposed Commit Groups

**Nothing here has been staged, committed or altered.** These are proposals for the project
owner, derived from paths only.

Ordering matters: groups are listed so that each one's dependencies are already committed by
the time you reach it. Groups 1–3 are the safe warm-up; 6–9 are the ones that decide bouts.

---

### Group 1 — Documentation

**Purpose:**
Markdown only, no runtime effect. Includes the Phase 0/1 safety reports, the architecture
audit, and subsystem docs for the newer work. Committing these first gives the rest of the
review something to refer to.

**Candidate paths:**
`docs/PHASE_0_SCORING_SAFETY_REPORT.md`, `docs/PHASE_1_TEST_ENVIRONMENT_AND_AUTHORIZATION_REPORT.md`,
`docs/SPORTS_ARCHITECTURE_AUDIT.md`, `docs/SPORTS_ARCHITECTURE_EVIDENCE_BUNDLE.md`,
`Documentation/OPEN-MAT.md`, `Documentation/MATSIDE-CAMERA.md`, `Documentation/MCP.md` (modified),
`Documentation/VIDEO-INTEGRATION.md` (deleted), `Documentation/play-patch/`, `CLAUDE.md` (+215/−75)

**Dependencies / review notes:**
`CLAUDE.md` is the project's governing rulebook — read its diff properly rather than waving it
through; it encodes STRICT rules that shape all later work. `VIDEO-INTEGRATION.md` is deleted
as part of the Play disconnection, so it pairs with Group 2.

**Risk:** Low

**Suggested validation after future commit:** Static review only.

---

### Group 2 — Play integration removal (legacy)

**Purpose:**
Deletes the disconnected `video.takeone.bh` integration — 12 tracked deletions, self-contained
and already documented as intentional (disconnected 2026-08-27).

**Candidate paths:**
`app/Play/{BoutPayload,MirrorBoutTimeline,PlayClient}.php`,
`app/Console/Commands/{PlayHealth,PlayPullTimeline,PlayToken}.php`,
`app/Http/Controllers/Api/PlayIntegrationController.php`, `app/Jobs/PushBoutToPlay.php`,
`app/Models/{PlayTimelinePoint,PlayTimelineRound}.php`, `config/play.php`,
`Documentation/VIDEO-INTEGRATION.md`

**Dependencies / review notes:**
Confirm nothing still references `config('play.*')` or these classes — `routes/web.php` and
`bootstrap/app.php` are both modified in this tree and may hold the matching removals, which
would drag Group 8 in. Grep before committing. The dormant `event_recordings.play_*` columns
and the orphaned `play_timeline_*` tables are deliberately left in place.

**Risk:** Low–Medium (only if a reference survives)

**Suggested validation after future commit:** Static review, plus a grep for `Play`/`play.` in
a safe isolated clone.

---

### Group 3 — Test additions

**Purpose:**
Three new test files, no runtime effect. The safety net for everything below.

**Candidate paths:**
`tests/Unit/Events/Sports/Karate/KarateScoringSafetyTest.php`,
`tests/Unit/Events/Sports/Taekwondo/TaekwondoScoringSafetyTest.php`,
`tests/Feature/Events/ScoringAuthorizationSafetyTest.php`,
`tests/Feature/McpServerTest.php` (modified, +138)

**Dependencies / review notes:**
⚠️ **Ordering trap.** The Karate unit test asserts the *configurable rules* feature that lives
in Group 9. Commit this group **before** Group 9 and 5 of its tests fail; commit it after, and
all 38 pass. Either commit Groups 3 and 9 together, or accept a red build in between.
The authorization test passes against `HEAD` today (verified in Phase 1) and is safe alone.

**Risk:** Low

**Suggested validation after future commit:** Targeted PHPUnit in a safe isolated clone —
note the suite also needs `npm ci && npm run build` first, or ~150 unrelated tests fail on a
missing Vite manifest.

---

### Group 4 — Configuration

**Purpose:**
Three new config files backing the media/live/event work, plus the event-type registry entry.

**Candidate paths:**
`config/events.php`, `config/live.php`, `config/media.php`, `config/event_types.php` (modified)

**Dependencies / review notes:**
`config/event_types.php` registers event packages — it is the wiring for Group 7's Open Mat.
The new config files are consumed by Groups 5 and 7, so this must land before or with them.
Check every new key has a safe default when its env var is absent.

**Risk:** Low–Medium

**Suggested validation after future commit:** Static review; confirm defaults in an isolated
clone with no `.env` overrides.

---

### Group 5 — Match video, media vaults and live streaming (new subsystem)

**Purpose:**
The in-house media layer: pluggable vaults, the HLS pipeline, live streams and bout video.
Almost entirely new, untracked files plus their schema.

**Candidate paths:**
`app/Media/*`, `app/Events/Support/Live/*`, `app/Models/EventCamera.php`,
`app/Models/EventCameraClip.php`, `resources/views/events/live/*`,
`resources/views/admin/storage/*`, and migrations
`2026_08_25_17{0000,0100,0200}_*` (media vaults/files), `2026_08_25_18/19*_live_streams*`,
`2026_08_26_09/10*`, `2026_08_23_120000_create_event_cameras_table.php`,
`2026_08_27_080000_create_bout_coach_notes_table.php`,
`2026_08_27_130000_create_bout_comments_table.php`

**Dependencies / review notes:**
Needs Group 4's `config/media.php` and `config/live.php`. **Migrations must land in the same
commit as the code that reads them** — a deploy that runs one without the other breaks the
event console. Requires a verified backup before migrating (RULE #2). Review upload paths
against the `StoragePath` rules and confirm every media route re-checks authorization per
segment.

**Risk:** High — new schema, file handling and a public streaming surface

**Suggested validation after future commit:** Verified DB backup → migrate in an isolated
clone → targeted tests → manual browser test of one bout video end to end.

---

### Group 6 — Screen pairing, court display and camera fleet

**Purpose:**
The hall-screen fleet: pairing/claim flow, the console's screens panel, and the new camera
control surface.

**Candidate paths:**
`app/Events/Support/{HallScreenRouter,ScreenMedia,ScreenMediaController,ScreenPairingController}.php`,
`app/Events/Support/Cameras/{CameraChannel,CameraConsoleController,CameraController,CameraFleet}.php`,
`resources/views/components/{court-screens,screen-pairing,event-screen-audio}.blade.php`,
`resources/views/events/screen/{claim,new}.blade.php`

**Dependencies / review notes:**
`court-screens.blade.php` is +809/−26 — a substantial rewrite of the console panel, not a
tweak. `CameraFleet` is called from **both** sports' `Scoring::apply()`, so this group is
coupled to Groups 8 and 9; if the call sites are new, they cannot be separated. Pairing bugs
strand a wall screen mid-event with no keyboard to recover it.

**Risk:** High — event-day hardware, and coupled to the scoring engines

**Suggested validation after future commit:** Isolated clone tests → **staging hardware/screen
rehearsal** with a real paired display → MQTT display test.

---

### Group 7 — Open Mat event type and shared event domain

**Purpose:**
The Open Mat casual-scoreboard package and the surrounding event/tournament domain changes.

**Candidate paths:**
`app/Events/OpenMat/*`, migrations `2026_08_25_1{2,6}0000_create_open_mat_*`,
`2026_08_25_200000_create_open_mat_codes_table.php`, `2026_08_26_060000_add_club_to_open_mat_tables.php`,
`app/Events/{AbstractEventType,Contracts/EventType}.php` (modified),
`app/Events/Support/MatchEventLog.php`, `app/Sports/Combat/{CombatSport,AbstractCombatSport}.php`,
`app/Models/{ClubEvent,EventRecording}.php`, `app/Http/Controllers/PersonalEventController.php`,
`app/Http/Requests/TournamentRequest.php`

**Dependencies / review notes:**
⚠️ **`app/Events/Contracts/EventType.php` is modified** — a change to the shared contract that
*every* event package implements. If a method was added, all packages must satisfy it, which
couples this group to Groups 8 and 9 and to any package not in this tree. Establish that first;
it may force a merge with the scoring groups. Needs Group 4's registry entry.

**Risk:** High — shared contract plus new schema

**Suggested validation after future commit:** Verified backup → migrate in an isolated clone →
full targeted suite → manual walkthrough of one Open Mat session.

---

### Group 8 — Taekwondo live scoring

**Purpose:**
The smaller of the two scoring changes: +46 lines across the engine and its endpoint, plus
console/screen views and language files.

**Candidate paths:**
`app/Events/Sports/Taekwondo/Tournament/Scoreboard/{Scoring,ScoreboardController}.php`,
`app/Events/Sports/Taekwondo/Tournament/CourtDisplay/CourtDisplayController.php`,
`app/Events/Sports/Taekwondo/Taekwondo.php`,
`app/Events/Sports/Taekwondo/Tournament/resources/views/{scoreboard/{control,mat},court-display/{board,screen}}.blade.php`,
`app/Events/Sports/Taekwondo/resources/lang/{en,ar}/messages.php`

**Dependencies / review notes:**
Taekwondo's `MatState.php` is **not** modified, which is why its Phase 0 characterization tests
pass against `HEAD` unchanged — this group is genuinely smaller and better understood than
Group 9. Verify whether the +27 in `ScoreboardController` touches the authorization block; if
so, re-run the Phase 1 authorization tests before and after. May depend on Group 6 if it adds
the `CameraFleet::observe()` call.

**Risk:** High — decides official results

**Suggested validation after future commit:** Isolated clone → Phase 0 + Phase 1 targeted
tests → manual scoring rehearsal on a spare mat → MQTT display test.

---

### Group 9 — Karate live scoring: configurable rules and the operator console

**Purpose:**
The largest and most consequential change in the tree. Introduces the per-mat rule set
(`senshuRule`, `autoSenshu`, `winByPenalties`, `gapOn`, `gap`, `warning`), `MatState::SETTINGS`
persistence, the `rules` command, the auto-end/`awaitingDecision` flow, and a near-rewrite of
the operator console (+1272/−602).

**Candidate paths:**
`app/Events/Sports/Karate/Tournament/Scoreboard/{Scoring,MatState,ScoreboardController}.php`,
`app/Events/Sports/Karate/Tournament/{Tournament.php,CourtDisplay/CourtDisplayController.php}`,
`app/Events/Sports/Karate/Karate.php`,
`app/Events/Sports/Karate/Tournament/resources/views/{scoreboard/{control,mat},court-display/{board,screen}}.blade.php`,
`app/Events/Sports/Karate/{Tournament/,}resources/lang/{en,ar}/messages.php`,
`database/migrations/2026_08_24_150000_add_scoreboard_settings_to_club_events.php`

**Dependencies / review notes:**
- **The migration is untracked and belongs in this commit.** `MatState::load()` reads
  `club_events.scoreboard_settings`; without the column the mat throws on load. Code and
  migration must land together — this is the single most important pairing in the tree.
- Commit **with** Group 3, or the Karate characterization tests fail on either side of it.
- Two open Phase 0 findings live in this code and are **unfixed by design**: a declared winner
  with reason `points` is coerced to `other`; and Taekwondo restores `punWinner` unvalidated
  while Karate validates `winner`. Decide on both during review.
- Likely depends on Group 6 (`CameraFleet::observe()`) and possibly Group 7's contract change.

**Risk:** High — the highest in the tree. This is the code that decides Karate bouts

**Suggested validation after future commit:** Verified backup → migrate in an isolated clone →
Phase 0 + Phase 1 targeted tests (all 38 + 21 should pass **after** this lands) → full suite
with assets built → **staging hardware rehearsal**: run a full bout on a real mat with a paired
wall screen, exercising senshu, the point gap, the penalty ladder and the auto-end confirmation.

---

### Group 10 — TV / mobile native app (boutcam)

**Purpose:**
The Flutter kiosk and the new camera app — 116 paths, largely untracked.

**Candidate paths:**
`TV/lib/*`, `TV/lab-app/*`, `TV/android*/*`, `TV/assets/*`, `TV/build.sh`, `TV/pubspec.{yaml,lock}`,
`TV/tools-make-icons.php`, `TV/test/*`, `public/lab/*`

**Dependencies / review notes:**
⚠️ **21 of these files are not in this bundle's archive** (`.dart`/`.kt`/`.kts`/`.properties` fall
outside the allowlist) — see `SENSITIVE_FILES_EXCLUDED.md` §3. Back them up by hand before doing
anything else with this tree. Independent of the web app at runtime, but `public/lab/*` and the
lab endpoints were explicitly flagged for deletion after the measurement run — confirm before
committing them.

**Risk:** Medium — separate deliverable, no effect on the live web app

**Suggested validation after future commit:** Build the APK in isolation; install on one device.

---

### Group 11 — Web/UI, routes and remaining Blade

**Purpose:**
The residual 152 UI paths: member/profile views, components, language files, and `routes/web.php`
(+262).

**Candidate paths:**
`resources/views/**` (not already claimed above), `lang/**`, `routes/web.php`,
`bootstrap/app.php`, `app/Http/Controllers/{MemberController,PersonalMobileController,Admin/PlatformController}.php`,
`app/Providers/AppServiceProvider.php`, `app/Mcp/Servers/TakeOneServer.php`

**Dependencies / review notes:**
⚠️ **`routes/web.php` is the tie that binds.** Its +262 lines almost certainly register the
endpoints for Groups 5, 6, 7 and 9, and remove Group 2's. It therefore **cannot be committed
independently** — split it by hunk into whichever group owns each route, or commit it last and
accept that intermediate commits have unroutable controllers. `bootstrap/app.php` has the same
problem for global middleware. This is the group most likely to force several others together.

**Risk:** Medium in isolation, **High** because of `routes/web.php`

**Suggested validation after future commit:** Build frontend assets in an isolated clone →
full suite → manual browser test of the main member and admin journeys.

---

## If the groups cannot be separated

They may well not be. `routes/web.php`, `bootstrap/app.php`, `app/Events/Contracts/EventType.php`
and `CameraFleet` each touch several groups at once. If splitting by hunk proves impractical,
the honest fallback is **one high-risk commit** covering Groups 4–9 and 11, with a message that
names what it contains — a single reviewable commit beats a chain of commits that individually
do not boot.

What must **not** be sacrificed to that fallback: every migration ships with the code that reads
it, and a verified `takeone:backup` exists before anything is migrated.
