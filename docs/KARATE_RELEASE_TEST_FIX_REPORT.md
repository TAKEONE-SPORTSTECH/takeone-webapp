# Karate Release — Test Fix Report

> Isolated workspace `/tmp/takeone-karate-release-ws` (base commit `c12d1ada`, pending Karate
> release applied). **`/var/www/takeone` was not touched.**
> Date: 2026-08-29

## Summary

The two test failures the pending Karate release introduced are now resolved, and they were
resolved **by correcting the tests, not the application** — which is what the F1 and F2
investigations concluded was correct in both cases.

The full suite drops from **20 failures to 18**, and the 18 that remain are **precisely** the
set that already failed at `HEAD` before this release existed. **Zero release-caused failures
remain.**

---

## 1. Tests changed

Two files, both under `tests/`. No other file was modified.

### `tests/Feature/Events/MatchEventLogTest.php` — +2 / −2

Both occurrences of the dead config key replaced:

```php
- config(['play.event_log' => false]);
+ config(['events.match_log' => false]);
```

- **Line 192** — `test_nothing_is_recorded_when_the_log_is_switched_off`. This is the test that
  was failing.
- **Line 201** — `test_scoring_still_works_when_the_log_is_switched_off`. This one was
  *passing*, but **vacuously**: it set a key nothing reads, so it ran with logging switched
  **on** and never tested its own premise. It now tests what its name says.

Justification (F1): `config/play.php` was deleted with the Play disconnection and the key moved
to `config/events.php` as `match_log`. The env var behind it is unchanged (`EVENT_MATCH_LOG`),
so no deployment is affected and no backwards-compatible fallback is warranted.

### `tests/Feature/Events/CourtDisplayTest.php` — +46 / −2

`test_an_unknown_or_revoked_token_is_indistinguishable_from_a_wrong_one` was rewritten. The
method name is unchanged, because it still describes exactly what is being proven.

**The brief's instruction was followed: 404 was not simply swapped for 302.** The old test
asserted a status code on each response independently, which is why it went stale the moment
the status legitimately changed — while the security property it exists to protect had never
broken. The new test asserts the property itself, by comparing the responses **against each
other**:

| What is now asserted | Why |
|---|---|
| A paired token still returns `200` and renders the board | The control — the one case that is *legitimately* distinguishable |
| Revoked, fabricated, and a **second** differently-shaped fabricated token all `assertRedirect('/screen')` | Pins the documented recovery behaviour: a dead token sends the screen back to the pairing room rather than stranding a keyboard-less TV on a 404 |
| `assertSame` on **HTTP status** — revoked vs each fake | Same status |
| `assertSame` on the **`Location` header** — revoked vs each fake | Same redirect destination |
| `assertSame` on the **response body** — revoked vs each fake | Same body, byte for byte |
| `assertStringNotContainsString($token, …)` on body **and** `Location` of both | Neither response echoes the token or hints it was ever issued |

Two distinct fabricated tokens are used (`AAAA…` and `Zq7xxx…`) so an accidental match on one
particular string cannot pass the test.

The comment in the test records why it is written this way:

> *"a change that moves them all together is a redesign, while a change that moves one without
> the others is the defect this test exists to catch. Asserting `404` is how this test
> previously went stale without the property it guards ever having broken."*

---

## 2. Exact test results

All six targeted suites, run individually:

| Suite | Result |
|---|---|
| `MatchEventLogTest` | **10 passed** (24 assertions) — was 1 failed / 9 passed |
| `CourtDisplayTest` | 30 passed, **5 failed** — was 30 passed / 6 failed. The target test now **passes**; the 5 remaining are pre-existing (§4) |
| `KarateScoringSafetyTest` | **17 passed** (99 assertions) |
| `TaekwondoScoringSafetyTest` | **21 passed** (129 assertions) |
| `ScoringAuthorizationSafetyTest` | **21 passed** (199 assertions) |
| `KarateScoreboardSettingsSafetyTest` | **15 passed** (117 assertions) |

Both tests targeted by this task pass:

```
✓ nothing is recorded when the log is switched off
✓ scoring still works when the log is switched off
✓ an unknown or revoked token is indistinguishable from a wrong one
```

**Note on the gate.** The brief said to run the full suite "if those pass". Five of the six
suites pass outright; `CourtDisplayTest` retains 5 failures that are **pre-existing at `HEAD`**
and untouched by this task (proven in §4). The full suite was therefore run, and this
qualification is stated rather than glossed over.

---

## 3. Full-suite result

```
Tests:  18 failed, 1 skipped, 741 passed (2731 assertions)
Duration: 47.0s
```

Run after `npm ci` + `npm run build` (assets were already built in this workspace from the
previous phase, so no build command was re-run here).

| | Failed | Passed |
|---|---:|---:|
| Before these fixes | 20 | 739 |
| **After these fixes** | **18** | **741** |
| Change | **−2** | **+2** |

---

## 4. Remaining failures — all pre-existing, none release-caused

The current failing-test names were diffed against the set measured at `HEAD` (no pending
release, assets built) during the earlier release review:

```
current failures                  : 18
pre-existing at HEAD              : 18
failures NOT pre-existing         : (none)
pre-existing failures now fixed   : (none)
```

**The two sets are identical.** Every remaining failure predates the Karate release, and this
task fixed no pre-existing test — correctly, since none was in scope.

The 18 span: `BulkEntryTest` (3), `CourtDisplayTest` (5), `CourtScreenPairingTest` (1),
`EventConsoleTest` (2), `OfficialVerificationTest` (3), `TaekwondoTournamentPackageTest` (1),
`ExploreEventsTest` (2), `PeoplePublicProfileMobileTest` (1). They are a separate, older
problem that the Karate release neither caused nor fixed, and they were deliberately left
alone.

---

## 5. Confirmation: application code was not changed

Verified mechanically, not asserted.

- The set of tracked files modified in the workspace was compared against the 187 files the
  release patch touches. The **only** additions are the two test files named above:
  ```
  tests/Feature/Events/CourtDisplayTest.php
  tests/Feature/Events/MatchEventLogTest.php
  ```
- `git diff HEAD --numstat` restricted to `app/`, `routes/`, `config/`, `database/` is
  **identical to the release patch's own numstat** for those paths — so not one application,
  route, config or migration file differs from the state the patch produced.
- Change sizes: `MatchEventLogTest.php` **+2 / −2**, `CourtDisplayTest.php` **+46 / −2**.
  Nothing else in either file was touched.
- No controller, scoring engine, `MatState`, model, migration, UI/Blade, MQTT/realtime, camera
  or permission file was edited.
- No Composer, NPM, build, migration, seeder, cache, queue or deployment command was run. The
  only commands executed were `php artisan test` with the six filters and once unfiltered.
- No `.env` or secret value was read.

## 6. Confirmation: `/var/www/takeone` was not touched

`git status --short` in the live repository reads **301** paths, unchanged before and after
this task. No file there was created, modified, deleted or staged, and nothing was committed,
pushed, deployed, migrated, reset, cleaned, stashed or branched — in either location.

## 7. Release position

Both defects flagged against the pending Karate release are now closed as **stale test
expectations, corrected in the tests**:

- **F1** — audit-log config rename: SAFE, env var unchanged. Test fixed.
- **F2** — screen-token responses: SAFE, revoked and fabricated are byte-identical. Test fixed,
  and now asserts the property instead of a status code.

What remains before release is unchanged and is the human work described in
`docs/KARATE_SCORING_RELEASE_DEPENDENCIES.md`: the 22-file atomic release unit, the
migration-before-code deployment order, and the staging screen/camera rehearsal.

**One item to carry forward:** `docs/KARATE_RELEASE_SAFETY_REPORT.md` still contains the claim
— disproved by F1 — that the audit log would be "silently re-enabled" for anyone using the old
key. The correction is recorded in `docs/F1_AUDIT_LOG_CONFIG_REVIEW.md`; the older report has
not been amended.
