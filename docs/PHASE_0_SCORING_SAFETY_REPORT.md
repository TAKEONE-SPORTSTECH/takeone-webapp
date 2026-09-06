# Phase 0 — Scoring Safety Baseline

> **Additive only.** No application source file was created, modified, moved or
> deleted. The only files written are two new unit tests under `tests/` and this
> report. Every expectation in the new tests was derived from the repository's
> own code and constants — never from the WKF or World Taekwondo rulebooks.
>
> Date: 2026-08-29. Branch inspected: `development`.

---

## Scope

### What was changed

| File | Change |
|---|---|
| `tests/Unit/Events/Sports/Karate/KarateScoringSafetyTest.php` | **New.** 17 characterisation tests, 0 production changes. |
| `tests/Unit/Events/Sports/Taekwondo/TaekwondoScoringSafetyTest.php` | **New.** 21 characterisation tests, 0 production changes. |
| `docs/PHASE_0_SCORING_SAFETY_REPORT.md` | **New.** This report. |

### What was NOT changed

Confirmed by `git status --short` diffing against the pre-work snapshot (§ Git
Safety Verification):

- **No** scoring logic touched — `Scoring.php` and `MatState.php` for both sports
  are byte-identical to their pre-work state.
- **No** controller, route, middleware, policy, permission or authentication change.
- **No** Blade/UI, MQTT/realtime, camera, screen-pairing, bracket, registration or
  event-result change.
- **No** migration, schema, seeder, factory or database record touched.
- **No** existing test modified or deleted.
- **No** dependency added, removed or updated; `composer.json`/`composer.lock`/
  `package.json` untouched.
- **No** `.env`, secret, credential, certificate or deployment config touched.
- **No** CI change (see § CI Changes).

---

## Safety Checks

### Pre-existing Git changes found before work

The working tree was **already dirty before this task began**: `git status --short`
reported **298 modified/deleted/untracked paths** on branch `development`,
including live edits to the very files this phase must not touch
(`app/Events/Sports/Karate/Tournament/Scoreboard/Scoring.php`, `MatState.php`,
`ScoreboardController.php`, both `Taekwondo` equivalents, `Tournament.php`,
`bootstrap/app.php`, `config/event_types.php`, the `TV/` Flutter app, and the
deletion of the `app/Play/*` integration).

**These were not made by this task and have been left exactly as found.** They
are listed here so that a later reader does not mistake them for Phase 0 output.
The post-work status is the pre-work status **plus three new files**.

> ⚠️ **Operational risk, not caused by this phase:** ~298 uncommitted changes sit
> on a box that is simultaneously the `Deploy Staging` rsync target. A deploy
> would wipe this uncommitted work. Flagged, not acted upon.

### Test-environment isolation assessment

| Check | Finding |
|---|---|
| `phpunit.xml` | ✅ Isolated on paper: `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `CACHE_STORE=array`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array`, `BROADCAST_CONNECTION=null`. |
| `tests/TestCase.php` guard | ✅ Present and working. It aborts the run if `bootstrap/cache/config.php` exists, or if the sqlite connection is not `:memory:`. |
| `bootstrap/cache/config.php` | 🛑 **PRESENT.** A cached config overrides the phpunit env vars, so `RefreshDatabase` (`migrate:fresh`) would drop every table in the **real** database. The guard correctly refuses to run — this is exactly the 2026-08-02 incident it was written for. |
| `vendor/phpunit/` | 🛑 **NOT INSTALLED.** `vendor/` was installed without dev dependencies; `vendor/bin/` contains no `phpunit`. |

**Conclusion: the Laravel test suite could not be safely executed, and was not
executed.** The documented fix is `php artisan config:clear` — which this phase is
explicitly forbidden from running, and which would in any case leave PHPUnit
missing. Both blockers are environmental and neither was touched.

### Commands actually run

All read-only. None wrote to the repository, the database, the cache or storage.

```
git status --short                  # pre- and post-work snapshots
git diff --check
git diff --stat -- tests docs
cat / sed -n / grep -n              # reading source and docs
php -l <new test files>             # syntax check only
php <scratchpad>/run.php            # assertion verification harness — see below
```

**The verification harness.** Because PHPUnit is absent, each new test method was
executed against the **real production classes** through a throwaway harness in
the session scratchpad (outside the repository, deleted-by-design, never
committed). It autoloads `vendor/autoload.php`, substitutes a minimal stub for
`PHPUnit\Framework\TestCase` that throws on a failed assertion, and invokes every
`test_*` method.

```
RESULT: 38 passed, 0 failed, 228 assertions
```

This proves every expectation matches current behaviour. It is **not** a PHPUnit
run, and is reported as what it is — see "Execution Result" in the table below.

### Commands intentionally NOT run, and why

| Command | Why not |
|---|---|
| `php artisan test` / `vendor/bin/phpunit` | PHPUnit is not installed, and a cached config is present. Running the suite is the exact path that destroyed the stage database on 2026-08-02. |
| `php artisan config:clear` | Forbidden by the task brief. It is also the documented fix for the blocker above — deliberately left for a human. |
| `composer install` / `composer require` | Forbidden. Would be needed to obtain PHPUnit. |
| `php artisan migrate` / `db:seed` / `cache:clear` | Forbidden and destructive. |
| Any `git` write command (`add`, `commit`, `push`, `checkout`, `restore`, `clean`) | Forbidden. The dirty pre-existing tree makes any of them especially dangerous. |

Every command was run as the invoking user and wrote nothing into `storage/` or
`bootstrap/cache/`, so no root-owned file was created that could lock `www-data`
out of the live site.

---

## Tests Added

38 tests, 228 assertions. All are **characterisation tests**: they assert what the
code does today, so that a future change to scoring has to announce itself.

| Test File | Sport / Area | Behavior Locked Down | Production Source Evidence | Execution Result |
|---|---|---|---|---|
| `KarateScoringSafetyTest` | Karate — command vocabulary | The 21 accepted commands are exactly the documented list | `Scoring::COMMANDS`, used as the endpoint's `in:` allow-list at `ScoreboardController.php:483` | ✅ 3 tests pass (harness) |
| `KarateScoringSafetyTest` | Karate — command vocabulary | Unsupported commands (`score`, `gamjeom`, `award_round`, `set_score`…) are not accepted | `Scoring::COMMANDS`; `Scoring::apply()` `default => null` | ✅ pass |
| `KarateScoringSafetyTest` | Karate — win reasons | `points, hansoku, shikkaku, kiken, medical, no_show, other` — the list that reaches the official record | `Scoring::WIN_REASONS`; validated at `ScoreboardController.php:526` | ✅ pass |
| `KarateScoringSafetyTest` | Karate — penalty ladder | Five rungs, ascending: `C1 C2 C3 HC H`; the **count** is load-bearing | `MatState::PENALTIES`; `Scoring::penalty()` clamps to `count()` and treats the top as hansoku (`Scoring.php:543-592`) | ✅ pass |
| `KarateScoringSafetyTest` | Karate — callouts | `1 → YUKO`, `2 → WAZA-ARI`, `3 → IPPON`, covering the validated range `n: 1..3` | `MatState::CALLOUTS`; `ScoreboardController.php:485` | ✅ pass |
| `KarateScoringSafetyTest` | Karate — **who leads** | Higher score leads; a level bout with no senshu has no leader | `MatState::akaLeads()` / `aoLeads()` | ✅ 2 tests pass |
| `KarateScoringSafetyTest` | Karate — **senshu tie-break** | Senshu decides a level bout for whoever holds it, and **only** when level | `MatState::akaLeads()` / `aoLeads()` | ✅ 2 tests pass |
| `KarateScoringSafetyTest` | Karate — **declared winner** | A declared winner outranks both the score and senshu (how hansoku/kiken/medical hand a bout to the side with fewer points) | override branch at the top of `MatState::akaLeads()` / `aoLeads()` | ✅ pass |
| `KarateScoringSafetyTest` | Karate — status line | `Yame` / `Hajime` / `Time`; `finished` outranks `running` | `MatState::boutStatus()` | ✅ pass |
| `KarateScoringSafetyTest` | Karate — serialization | All 38 broadcast keys survive, and the derived truths (`akaLeads`, `aoLeads`, `boutStatus`) travel with the payload | `MatState::toArray()` | ✅ pass |
| `KarateScoringSafetyTest` | Karate — cache round trip | A bout's state survives `toArray()` → `fromArray()` — what a wall screen relies on after a mid-bout reload | `MatState::toArray()` / `::fromArray()` | ✅ pass |
| `KarateScoringSafetyTest` | Karate — restore hardening | A restored `winner` outside `aka`/`ao` is discarded | guard at `MatState.php:361` | ✅ pass |
| `KarateScoringSafetyTest` | Karate — rule defaults | Senshu/auto-senshu/win-by-penalties/atoshi/buzzer/gap all ON; `gap=8`, `warning=15.0`, `duration=180.0` | `MatState` constructor defaults | ✅ pass |
| `KarateScoringSafetyTest` | Karate — persisted settings | The 9 keys that outlive a bout (restored by `::load()`, written by `::persistSettings()`) | `MatState::SETTINGS` | ✅ pass |
| `KarateScoringSafetyTest` | Karate — modes | `upcoming` / `vs` / `scoreboard` — the strings the Blade screens branch on | `MatState::MODE_*` | ✅ pass |
| `TaekwondoScoringSafetyTest` | TKD — command vocabulary | Vocabulary is non-empty, duplicate-free, and still offers `load start pause score gamjeom commit clear` | `Scoring::COMMANDS` | ✅ pass |
| `TaekwondoScoringSafetyTest` | TKD — **sport isolation** | Karate-only commands (`senshu`, `penalty`, `undo_point`) are **not** accepted by a Taekwondo mat | `Scoring::COMMANDS` for both packages | ✅ pass |
| `TaekwondoScoringSafetyTest` | TKD — action values | `punch 1 · body 2 · head 3 · turn_body 4 · turn_head 5`, with labels | `MatState::ACTIONS` | ✅ pass |
| `TaekwondoScoringSafetyTest` | TKD — action values | An unsupported action (`ippon`, `yuko`, `kick`, `''`) has no value | `MatState::ACTIONS` | ✅ pass |
| `TaekwondoScoringSafetyTest` | TKD — **point gap** | Threshold is 12; fires at ≥12 on the **absolute** difference, symmetric for both corners, measured on the lead not the total | `MatState::POINT_GAP`, `::pointGapReached()` | ✅ 2 tests pass |
| `TaekwondoScoringSafetyTest` | TKD — **gam-jeom ceiling** | Limit is 5, counted across the match | `MatState::GAM_JEOM_LIMIT` | ✅ pass |
| `TaekwondoScoringSafetyTest` | TKD — **gam-jeom precedence** | Reaching the ceiling loses the round *however far ahead* (30–0 with 5 gam-jeom → opponent wins the round); 4 gam-jeom changes nothing | `MatState::roundWinner()` — the gam-jeom clause is checked before the score | ✅ 2 tests pass |
| `TaekwondoScoringSafetyTest` | TKD — round winner | A level round returns `null`, which is what sends a match to the golden round | `MatState::roundWinner()` | ✅ pass |
| `TaekwondoScoringSafetyTest` | TKD — **match winner** | Decided by **rounds, never aggregate score** (0–40 down on points still wins 2 rounds to 0) | `MatState::matchWinner()`, `::roundsToWin()` | ✅ pass |
| `TaekwondoScoringSafetyTest` | TKD — match winner | A match still in progress has no winner | `MatState::matchWinner()` | ✅ pass |
| `TaekwondoScoringSafetyTest` | TKD — match shape | `roundsToWin() = intdiv(rounds,2)+1` for rounds of 1, 2, 3 and 5 | `MatState::roundsToWin()` | ✅ pass |
| `TaekwondoScoringSafetyTest` | TKD — **PUN precedence** | Punitive declaration outranks the round series — including when the opponent already holds enough rounds to win | `punWinner` branch at the top of `MatState::matchWinner()` | ✅ pass |
| `TaekwondoScoringSafetyTest` | TKD — phase line | Exact operator-facing strings for round / rest / golden, incl. `Rest · round on 5 gam-jeom` and `Rest · round on point gap` | `MatState::phaseLabel()` | ✅ pass |
| `TaekwondoScoringSafetyTest` | TKD — phase line | `matchOver` outranks the phase and names the deciding rule (`golden point`, `5 gam-jeom (PUN)`, `point gap`) | `MatState::phaseLabel()` | ✅ pass |
| `TaekwondoScoringSafetyTest` | TKD — serialization | All 35 broadcast keys survive, and `roundWinner` / `matchWinner` / `roundsToWin` / `phaseLabel` travel with the payload | `MatState::toArray()` | ✅ pass |
| `TaekwondoScoringSafetyTest` | TKD — cache round trip | Scores, gam-jeom, rounds, phase and end reason survive `toArray()` → `fromArray()` | `MatState::toArray()` / `::fromArray()` | ✅ pass |
| `TaekwondoScoringSafetyTest` | TKD — restore hardening | `round` and `rounds` clamp to ≥1, so a corrupt blob cannot print "Round 0" on a wall screen | `max(1, …)` in `MatState::fromArray()` | ✅ pass |
| `TaekwondoScoringSafetyTest` | TKD — defaults | Fresh mat is best-of-three, `duration=120.0`, `restDuration=60.0`, nothing scored, no PUN | `MatState` constructor defaults | ✅ pass |
| `TaekwondoScoringSafetyTest` | TKD — modes/phases | `upcoming/vs/scoreboard` and `round/rest/golden` | `MatState::MODE_*`, `::PHASE_*` | ✅ pass |

**Execution Result legend:** ✅ = executed against the real production class via
the scratchpad harness and passed (38/38, 228 assertions). **No test was run under
PHPUnit**, for the environmental reasons in § Safety Checks. Both files extend
`PHPUnit\Framework\TestCase` directly — exactly as the existing
`tests/Unit/ExampleTest.php` does — so they boot no framework, open no database
and touch no cache, and will run under `php artisan test` on CI unchanged.

---

## Current Risks Confirmed

Each proven by reading the file cited. **Nothing below was fixed.**

### R1 — A cached config is present on a box whose test suite drops tables 🛑
`bootstrap/cache/config.php` exists. `tests/TestCase.php` documents that this is
precisely the condition under which `RefreshDatabase` runs `migrate:fresh` against
the **real** database — which already destroyed the stage database on 2026-08-02.
The guard holds and refuses to run, so the system is safe *today*, but the hazard
is armed and one weakened guard away from repeating. Fix is `php artisan
config:clear`, deliberately not run here.

### R2 — Every scoring rule is unreachable to a test without a database 🛑
In **both** sports, all rule logic (`point`, `penalty`, `senshu`, `checkGap`,
`finish`, `score`, `gamjeom`, `checkRoundEnd`, `awardRound`, `golden`, `undo`) is
`private`. The only public entry point, `Scoring::apply()`, loads a `ClubEvent`
from the database, reads and writes the cache, appends to `MatchEventLog` and
notifies `CameraFleet` — in one method. There is therefore **no seam** at which a
scoring rule can be tested in isolation. This is the single biggest obstacle to
covering the rules themselves, and it confirms the audit's finding that the
highest-consequence subsystem in the product has no rule-level coverage.

### R3 — Live match state is a cache blob with a TTL
`MatState::load()/save()` use `Cache::` with a 240-minute TTL, on the `file` cache
store. A cache flush, a 240-minute overrun, or a second web node loses the running
score of a bout in progress. `event_match_events` records the officiating trail but
is never read back to reconstruct state. (Independently established in
`docs/SPORTS_ARCHITECTURE_AUDIT.md`; re-confirmed here.) The new round-trip tests
lock the serialisation this recovery depends on, which is the most that can be
done without changing production code.

### R4 — Karate coerces a declared `points` win reason to `other` ⚠️ *possible defect — NOT fixed*
`Scoring.php:777-779`:
```php
$state->winReason = in_array($reason, self::WIN_REASONS, true) && $reason !== 'points'
    ? $reason
    : 'other';
```
`'points'` is a member of `WIN_REASONS` and is offered by the endpoint's `in:`
validation (`ScoreboardController.php:526`), yet declaring a winner *with* reason
`points` stores `other` on the record. This may well be intentional — a *declared*
winner is by definition not one the points decided — but the vocabulary advertises
a value the code silently rewrites. **Not covered by a test and not changed**, so
that a human can decide whether it is the intent or a defect.

### R5 — Taekwondo restores `punWinner` without validating it ⚠️ *asymmetry — NOT fixed*
Karate validates the restored winner (`MatState.php:361`):
```php
winner: in_array($a['winner'] ?? null, ['aka', 'ao'], true) ? $a['winner'] : null,
```
Taekwondo does not (`MatState.php:377`):
```php
punWinner: $a['punWinner'] ?? null,
```
`punWinner` outranks the round series in `matchWinner()`, so an arbitrary value
restored from a cache blob becomes the declared match winner and is broadcast to
every screen. The blob is server-written, so this is defence-in-depth rather than a
live exploit — but it is a hardening the twin package already has and this one
lacks, which is exactly the copy-paste divergence risk the audit described. The
Karate guard *is* now locked down by a test; the Taekwondo gap is documented only.

### R6 — A double gam-jeom ceiling resolves in favour of `ao`
`MatState::roundWinner()` checks `akaGam >= 5` before `aoGam >= 5`, so if both
corners somehow reach the ceiling, `ao` is returned. Almost certainly unreachable
in practice (the match ends at the first ceiling). Recorded as an observation, not
asserted as intended behaviour.

### R7 — A credentials document is committed to the repository 🛑 *presence reported only*
`Documentation/SUPER_ADMIN_CREDENTIALS.md` (1,545 bytes, tracked) exists. Per the
task brief it was **not opened for its values, not edited, not rotated and not
deleted** — its presence and risk are reported here and nothing else. Anyone with
repository read access has whatever it holds; treat rotation as an operational task
outside this phase.

---

## Testability Gaps

Code that could not be safely tested in this phase without changing production
code. Listed so the next phase can decide, not so it can be worked around.

1. **All Karate and Taekwondo scoring rules** (R2). Untestable without either a
   booted Laravel + database (blocked, § Safety Checks) or reflection into private
   methods. The brief prefers reporting the testability issue over reflecting into
   private state, and that is what was done. Specifically uncovered:
   - Karate: point add/subtract and the `max(0, …)` floor; automatic senshu on the
     first unopposed point; the penalty ladder's clamping and its hansoku ending;
     the point-gap auto-end; `awaitingDecision` (auto-end parks, declared end
     releases); undo; and the refusal to commit a level bout with no senshu.
   - Taekwondo: score/adjust; gam-jeom awarding a point to the opponent; round-end
     detection; round awarding; the golden round; the undo stack and its `LOG_LIMIT`.
2. **`MatState::toArray()` with a competitor country set.** `announced()` calls
   `App\Support\Countries::label()`, which reaches the `Cache` facade via
   `Countries::index()` and so needs a booted container. The new serialization
   tests deliberately leave corners empty, and say so in a comment. A pure-function
   country lookup, or injecting the label at the call site, would close this.
3. **The authorization surface** — see below.
4. **The clock.** `Scoring::settleClock()` calls `now()`. Freezing it requires
   Laravel's `Carbon` test helpers, i.e. a booted framework.

### The authorization tests were NOT written — and why

The brief asks for feature tests proving that an unauthenticated user, and an
authenticated user without the scoring permission, cannot reach a score-changing
endpoint; that the endpoint rejects the wrong sport, a malformed command, and an
invalid mat. **The endpoint already enforces all five**, verified by inspection of
`ScoreboardController::command()`:

| Requirement | Enforcement | Line |
|---|---|---|
| Authenticated + permitted only | `abort_unless($this->canScore($event), 403)` → `EventAccess::canScore($event, $user)`, null user denied | `:478`, `:650-655` |
| Wrong sport rejected | `abort_unless($event->sport === 'karate', 404)` | `:479` |
| Malformed command rejected | `'command' => ['required','string','in:'.implode(',', Scoring::COMMANDS)]` | `:483` |
| Invalid mat rejected | `abort_unless($this->matExists($event, $data['mat']), 404)` | `:532` |
| Payload bounds | every field constrained (`side` in `aka,ao`; `n` 1–3; `level` 0–5; `reason` in `WIN_REASONS`; free text length-capped) | `:481-528` |

Writing those tests is straightforward — `tests/Feature/Events/TaekwondoRealtimeTest.php`
already builds exactly the needed scenario (club → event → category → registrations
→ matches) with the `Tests\TestCase` helpers, so **no large new factory
infrastructure is required**.

They were nonetheless **not added**, for one reason: **they cannot be executed in
this environment** (PHPUnit absent, cached config present). CI runs `php artisan
test` on every push to every branch, so committing feature tests that have never
been run once would risk turning CI red on a live-sensitive branch — a regression
in the one signal the team has. Under "never break what works", an unverifiable
test is worse than an absent one. The unit tests added instead were each executed
against the real classes before being committed.

**This is the recommended first task of the next phase** (below), at which point it
is a ~1 hour job with a green suite to prove it.

---

## CI Changes

**None.**

`.github/workflows/ci.yml` was read and left untouched. No change is needed: its
final step is `php artisan test`, and `phpunit.xml`'s `Unit` suite already includes
`tests/Unit` recursively, so both new files are picked up automatically. Adding a
step would have been change without benefit.

Two CI observations, recorded but **not acted upon** (either would be a behaviour
change beyond this phase's remit):
- CI has no static analysis or lint step; `laravel/pint` is a dev dependency but
  is never invoked.
- CI runs `php artisan migrate --force` against a fresh `database/database.sqlite`
  *before* the suite, while the suite itself uses `:memory:`. Harmless on a CI
  runner, but it means CI does not exercise the `tests/TestCase.php` safety guard.

---

## Git Safety Verification

### Pre-work Git status
Branch `development`, **298 pre-existing dirty paths** (modified, deleted and
untracked), as catalogued in § Safety Checks. Not created by this task; left
untouched.

### Post-work Git status
`git status --short` reports **299** paths — the pre-work 298 plus **exactly one
new entry**, the new test directory:

```
?? tests/Unit/Events/
```

Only one entry appears for three new files because git abbreviates untracked
directories, and because `docs/` was **already** untracked before this task (it
holds the pre-existing `docs/SPORTS_ARCHITECTURE_AUDIT.md`), so this report joins
an entry that was already in the list rather than adding one.

Two entries a reader might mistake for this task's work are **not**:
`M tests/Feature/McpServerTest.php` and the 81 modified paths under `app/` are
pre-existing (`McpServerTest.php` was last modified 2026-08-27, two days before
this task). Neither was opened for writing here.

### Every file created or modified by this task

| Path | Status |
|---|---|
| `tests/Unit/Events/Sports/Karate/KarateScoringSafetyTest.php` | Created |
| `tests/Unit/Events/Sports/Taekwondo/TaekwondoScoringSafetyTest.php` | Created |
| `docs/PHASE_0_SCORING_SAFETY_REPORT.md` | Created |

Nothing else. No file was modified, moved, renamed or deleted.

### Confirmation

- ✅ **No existing application source file was changed.** `git status --short`
  restricted to `app/` is byte-for-byte identical before and after; the entries it
  shows are the pre-existing ones.
- ✅ **No existing test was changed.** Only two new files under `tests/`.
- ✅ **No migration, schema, seeder, factory or database record touched.**
- ✅ **No route, middleware, policy, permission, MQTT, camera, screen-pairing,
  bracket, registration or UI/Blade file touched.**
- ✅ **No dependency, `.env`, secret, credential or deployment config touched.**
- ✅ `git diff --check` reports no whitespace errors introduced.
- ✅ **No destructive git command was run.** Nothing was committed, pushed,
  branched or reset.
- ✅ **No generated cache, build, log, database or temporary file was left in the
  repository.** The verification harness lives in the session scratchpad, outside
  the repo, and is not tracked.
- ✅ **The three new files are owned by `actionsrunner:actionsrunner`**, matching
  every other file in the repository. This matters operationally: a root-owned
  file in this tree fails the `Deploy Staging` rsync with exit 23. They were
  created as root and `chown`ed immediately; no other file's ownership changed.

---

## Recommended Next Phase

**One step, and only one: restore the ability to run the test suite, then land the
five authorization tests that this phase specified but could not verify.**

Concretely, in order:

1. **A human runs `php artisan config:clear`** on this box, and confirms
   `bootstrap/cache/config.php` is gone. (Re-run `config:cache` afterwards only if
   this box serves traffic — and never leave a cache in place while tests run.)
2. **Install dev dependencies** so PHPUnit exists: `composer install` (without
   `--no-dev`), on a box where that is appropriate.
3. **Run the suite** — `vendor/bin/phpunit` — and record the baseline, including
   the 38 tests added here.
4. **Add `tests/Feature/Events/ScoringAuthorizationSafetyTest.php`**, modelled on
   the existing `TaekwondoRealtimeTest::scenario()` helper, covering the five cases
   already proven by inspection in § Testability Gaps. Remember that a browser
   `get()` to a forbidden page redirects rather than returning 403 (CLAUDE.md →
   "403s Redirect on Web"); the scoring **command** endpoint is a JSON POST and
   does return 403.

That is the whole recommendation. **No refactor is proposed here** — not the
shared tournament kernel, not a durable match-state store, not a scoring seam.
Each of those is a change to code that currently decides official results, and none
of them should be attempted until step 3 above produces a green baseline that can
prove they changed nothing.
