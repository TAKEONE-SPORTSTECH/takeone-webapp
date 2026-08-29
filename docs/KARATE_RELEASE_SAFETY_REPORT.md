# Karate Release Safety Report

> Isolated, disposable workspace: `/tmp/takeone-karate-release-ws`
> Base commit: `c12d1ada0696d589c39b73a001e4ea4667a973e7` (`development`)
> Date: 2026-08-29
>
> **`/var/www/takeone` was not touched.** No commit, push, deploy, migration against a real
> database, reset, clean, stash or cleanup was performed anywhere.

---

## 1. Safety checks — all passed

A correction to the task's premise, recorded because it changed what had to be done first:
**the session did not start in a clone containing the pending release.** The shell was in
`/var/www/takeone`, and the pre-existing clone at `/tmp/takeone-phase1-isolated` sits at
`HEAD`, which does **not** contain the Karate work (0 occurrences of `senshuRule`, no
`CameraFleet`, no `scoreboard_settings` migration).

A fresh disposable workspace was therefore built, and the pending release reconstructed into
it from the existing preservation bundle — `TRACKED_CHANGES.patch` applied with
`git apply --binary` (dry-run clean first) plus `UNTRACKED_SAFE_FILES.tar.gz` extracted.
Nothing was copied out of the live working tree by hand.

| # | Check | Expected | Result |
|---|---|---|---|
| 1 | `git status --short` empty | clean clone | **0 lines** at clone time ✅ (afterwards it shows the applied release, which is the point) |
| 2 | No `bootstrap/cache/config.php` | absent | **absent**, before and after `composer install` ✅ |
| 3 | PHPUnit installed | present | **PHPUnit 11.5.50** ✅ |
| 4 | Test DB | SQLite `:memory:` | `phpunit.xml`: `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` ✅ |
| 5 | Cache / session / mail | array drivers | `CACHE_STORE=array`, `SESSION_DRIVER=array`, `MAIL_MAILER=array` ✅ |
| 6 | Queue | sync | `QUEUE_CONNECTION=sync` ✅ |
| 7 | MQTT / realtime disabled | off | `BROADCAST_CONNECTION=null`; `REALTIME_ENABLED=false`; package default is `env('REALTIME_ENABLED', false)` ✅ |
| 8 | Pending Karate release present | all files | **13/13 present and content-verified** — see §2 ✅ |

Supporting facts: no `.env` was copied from the live repository (a test-only one was written
in the workspace with a locally generated `APP_KEY` — no real secret, and it is git-ignored).
The `tests/TestCase.php` guard is intact and did not fire. All commands ran as
`actionsrunner`, never `root`.

---

## 2. Release files — all present

Existence **and content** were both checked; a file present but lacking the release's changes
would have passed a naive check.

| Component | Path | Content proof |
|---|---|---|
| Karate scoring engine | `app/Events/Sports/Karate/Tournament/Scoreboard/Scoring.php` | imports `…Cameras\CameraFleet` (1); `'rules'` command present (1) |
| Karate mat state | `…/Scoreboard/MatState.php` | `senshuRule` (4 occurrences); `const SETTINGS` (1) |
| Karate endpoint | `…/Scoreboard/ScoreboardController.php` | present |
| Model cast | `app/Models/ClubEvent.php` | `'scoreboard_settings' => 'array'` (1); in `$fillable` (1) |
| Contract | `app/Events/Contracts/EventType.php` | `reloadHallScreens` declared (1) |
| Abstract default | `app/Events/AbstractEventType.php` | `function reloadHallScreens` (1) |
| Camera fan-out | `app/Events/Support/Cameras/CameraFleet.php` | present |
| Camera transport | `app/Events/Support/Cameras/CameraChannel.php` | present |
| Camera models | `app/Models/EventCamera.php`, `app/Models/EventCameraClip.php` | present |
| Karate routes | `routes/web.php` | `karate-scoreboard.photo` (1) |
| Settings migration | `database/migrations/2026_08_24_150000_add_scoreboard_settings_to_club_events.php` | `json('scoreboard_settings')` (1) |
| Cameras migration | `database/migrations/2026_08_23_120000_create_event_cameras_table.php` | present |

**13 present, 0 missing.**

---

## 3. Tests created

Exactly one new file: **`tests/Feature/Events/KarateScoreboardSettingsSafetyTest.php`** —
15 tests, 117 assertions.

Every expectation is read out of the release's own code, never from the WKF rulebook.
Persistence is asserted through a genuinely cold read: `MatState::forget()` then
`MatState::load()`, so a value that only survived in the cache entry would fail — which is the
exact bug this release exists to fix.

| Requested coverage | Tests |
|---|---|
| Null `scoreboard_settings` uses defaults safely | `…no_saved_settings_falls_back_to_the_built_in_defaults`, `…empty_settings_array_still_falls_back…` |
| Authorized organizer can save rules/settings | `…organiser_can_save_the_mats_rules`, `…every_documented_setting_is_persisted` |
| Settings survive a fresh `MatState` load | `…saved_rules_survive_the_cache_entry_being_lost`, plus `…score_itself_does_not_survive…` which pins the deliberate opposite half of the design |
| Duration persists after reload | `…bout_length_persists_after_the_mat_is_reloaded`, `…warning_longer_than_the_bout_is_pulled_back_to_fit_it` |
| Invalid settings rejected, saved settings unchanged | `…gap_outside_the_accepted_range…`, `…non_boolean_rule_flag…`, `…bout_length_outside_the_accepted_range…`, `…unauthorised_user_cannot_save_settings` |
| Taekwondo cannot use the Karate settings endpoint | `…taekwondo_event_cannot_use_the_karate_settings_endpoint`, `…refused_for_a_mat_the_event_does_not_run` |
| Saving settings changes no score/winner/status/result | `…leaves_the_match_record_completely_untouched` (plus an `assertResultUntouched()` helper invoked by nine other tests) |

---

## 4. Targeted test results — all passed

| Suite | Result |
|---|---|
| `KarateScoringSafetyTest` | **17 passed** (99 assertions) |
| `TaekwondoScoringSafetyTest` | **21 passed** (129 assertions) |
| `ScoringAuthorizationSafetyTest` | **21 passed** (199 assertions) |
| `KarateScoreboardSettingsSafetyTest` *(new)* | **15 passed** (117 assertions) |
| **Total** | **74 passed, 0 failed** (544 assertions) |

Worth recording: `KarateScoringSafetyTest` **failed 5/17 against `HEAD`** in Phase 1 and passes
17/17 here. That is direct confirmation that this workspace contains the pending release and
that the Phase 1 diagnosis (tests written against the working tree, not the commit) was correct.

---

## 5. Full suite

`npm ci` and `npm run build` succeeded (`public/build/manifest.json` produced), then:

> **739 passed · 20 failed · 1 skipped** (2,716 assertions, 47.6s)

For contrast, the Phase 1 run at `HEAD` **without** built assets was 563 passed / 155 failed —
the ~150 `Vite manifest not found` failures are gone, as predicted.

### Are the 20 failures caused by this release?

Rather than assume, the same nine failing classes were run **at `HEAD` with assets built**, in
the separate clone, and the failing-test names diffed:

| | Failures |
|---|---|
| `HEAD` (no pending release), assets built | **18** |
| Release workspace, assets built | **20** |
| Fixed by the release | 0 |
| **Introduced by the release** | **2** |

**18 of the 20 are pre-existing at `HEAD`** and have nothing to do with this release
(`BulkEntryTest`, `OfficialVerificationTest`, `ExploreEventsTest`,
`PeoplePublicProfileMobileTest`, `EventConsoleTest`, most of `CourtDisplayTest`,
`CourtScreenPairingTest`, `TaekwondoTournamentPackageTest`). **None were fixed.** In line with
the brief, none were touched.

### The 2 failures this release introduces — NOT fixed, for human decision

**F1 — `MatchEventLogTest::test_nothing_is_recorded_when_the_log_is_switched_off`**
Expected 0 audit rows, got 1. Cause is a one-line rename in
`app/Events/Support/MatchEventLog.php`: `config('play.event_log', true)` →
`config('events.match_log', true)`. The test still sets the old key, so the kill-switch no
longer responds to it.
*Almost certainly a stale test — but there is an operational edge:* anyone who had the
officiating log disabled through the old key will find it **silently re-enabled** after this
release. Decide whether that is intended, then update the test to the new key.

**F2 — `CourtDisplayTest::test_an_unknown_or_revoked_token_is_indistinguishable_from_a_wrong_one`** ⚠️ **security-relevant — review this one first**
The test asserts that a revoked screen token and a fabricated one answer **identically**, "or
the difference maps which tokens are real". Under this release, `GET /court/{token}` with a
revoked token returns **302** where the test expects **404**.
The likely cause is benign: `HallScreenRouter` introduces a canonical screen URL and its
docblock states "The old doors redirect here", so `/court/{token}` now redirects rather than
answering directly. **If both a revoked and a fabricated token redirect identically, the
anti-enumeration property still holds and only the asserted status code is stale.**
**This was not verified** — PHPUnit aborts the test at its first failed assertion, so the
fabricated-token branch never executed. Confirming that both cases are indistinguishable is a
required human check before release, not a test to be edited into passing.

---

## 6. Exact files created

Inside `/tmp/takeone-karate-release-ws` only:

| Path | Note |
|---|---|
| `tests/Feature/Events/KarateScoreboardSettingsSafetyTest.php` | The only file authored in this task |
| `docs/KARATE_RELEASE_SAFETY_REPORT.md` | This report |
| `.env` | Test-only values, locally generated `APP_KEY`, git-ignored, workspace-only |
| `vendor/`, `node_modules/`, `public/build/`, `bootstrap/cache/{packages,services}.php` | Generated by `composer install` / `npm ci` / `npm run build`; all git-ignored |

Nothing was created, modified or deleted in `/var/www/takeone`.

---

## 7. No existing application code was changed

Verified mechanically, not asserted: the set of tracked files modified in the workspace was
compared against the set of files the release patch touches.

```
tracked files modified in workspace : 187
files touched by TRACKED_CHANGES.patch : 187
diff of the two path lists            : IDENTICAL
```

Every tracked modification in this workspace came from applying the pending release patch.
**None was authored here.** The one file authored in this task is the new test, confirmed
absent from the preservation archive.

No scoring logic, route, Blade view, migration, model, config, UI, MQTT, camera or permission
file was edited. The two failing tests above were left failing.

---

## 8. Confirmation of what was NOT done

- ❌ No commit, push, branch, merge, rebase, reset, restore, checkout, stash, clean or deploy —
  in either workspace or in `/var/www/takeone`.
- ❌ No migration run against any real database. `RefreshDatabase` migrated only the
  per-test in-memory SQLite database, verified by `phpunit.xml` and the `TestCase` guard.
- ❌ No production or staging `.env`, credential or secret was read, copied or printed.
- ❌ No cleanup or deletion of anything, including the pre-existing preservation bundle and the
  Phase 1 clone (assets were built inside the latter for the `HEAD` comparison; no source
  file there was changed).
- ❌ No existing test file was edited, including the two now failing.

---

## 9. Recommended next human action

Decide on **F2** first — confirm that a revoked and a fabricated screen token remain mutually
indistinguishable under the new `/court/{token}` redirect. If they do, the fix is to update
that test's expected status; if they do not, this release opens a token-enumeration path on a
wall screen anyone can walk up to, and the router needs a change before release.

Then decide **F1** (intended rename vs. an audit log silently re-enabled), and note that the
18 pre-existing failures are a separate, older problem that this release neither caused nor
fixed.
