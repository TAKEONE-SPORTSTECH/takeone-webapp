# Phase 1 — Isolated Test Environment and Scoring Authorization

> Date: 2026-08-29. Source commit: `c12d1ada0696d589c39b73a001e4ea4667a973e7` (`development`).
> All work was done in a disposable clone at `/tmp/takeone-phase1-isolated`.
> **The original repository at `/var/www/takeone` was not modified.**

---

## 1. Scope

### What this phase did

1. Assessed the current working directory for test safety — and found it **unsafe**.
2. Built an isolated, disposable workspace from a clean git clone of the source commit.
3. Installed dependencies **in the isolated workspace only**, obtaining PHPUnit.
4. Ran the Phase 0 Karate and Taekwondo characterization tests.
5. Ran the full test suite and recorded a baseline.
6. Added one new feature test file covering authorization and request validation on
   both live scoring command endpoints.
7. Produced this report.

### Explicit list of what was NOT changed

- **No application source file** was created, modified, moved or deleted — in either
  workspace. `git diff HEAD` in the isolated workspace is empty for every tracked file.
- **No scoring logic.** `Scoring.php`, `MatState.php`, `ScoreboardController.php`,
  `Tournament.php`, `Advancement.php` and every other sport file are untouched.
- **No** route, Blade view, JavaScript, MQTT/realtime, screen-pairing, camera,
  registration, bracket, permission, policy, middleware or authentication code.
- **No** migration, schema, seeder, factory, or existing test file.
- **No** `composer.json`, `composer.lock`, `package.json` or lock file edited. (Composer
  *installed* the locked dependencies into the isolated workspace's `vendor/`; it did not
  alter the manifests — `git status` shows them unmodified.)
- **No** CI configuration, deployment configuration or credential file.
- **No** dependency installed in the original repository.
- **No** suspected scoring defect was fixed. Findings are reported only.
- **No** BJJ support, no registry edit, no refactor.
- **No** commit, push, branch change, reset, restore, checkout or clean, in either workspace.
- **No** real MQTT broker, mail service, external API, storage bucket or payment service
  was contacted.

---

## 2. Workspace Safety

### Original workspace — `/var/www/takeone` — **UNSAFE, and not repaired**

Assessed and rejected on five independent grounds:

| # | Finding | Why it forbids a test run |
|---|---|---|
| 1 | **299 uncommitted paths** in `git status --short`, including live edits to `Scoring.php`, `MatState.php` and `ScoreboardController.php` for both sports | A dirty tree cannot be a reproducible baseline, and a test run could not be attributed to a known commit |
| 2 | **It is the `Deploy Staging` rsync target** and the live staging checkout | Explicitly excluded by the brief |
| 3 | **`bootstrap/cache/config.php` present** (71,529 bytes) | A cached config overrides `phpunit.xml`'s `DB_DATABASE=:memory:`, so `RefreshDatabase` would run `migrate:fresh` against the **real** database. This is the exact 2026-08-02 incident |
| 4 | **PHPUnit / dev dependencies absent** — `vendor/bin/` has no `phpunit` | The suite cannot run, and installing into this repo is forbidden |
| 5 | **`.env` holds real credentials** — keys present for `MAIL_PASSWORD`, `REALTIME_MQTT_PASSWORD`, `REALTIME_JWT_SECRET`, plus a non-`:memory:` database | A test run here could reach a real broker, a real mailbox and real data |

Per the brief, **none of this was repaired.** No `config:clear`, no `composer install`,
no `.env` edit, nothing written to that directory after the clone.

> Only the **keys** in `.env` were listed, by `grep` piped through `sed` that replaced every
> value with `<redacted>` before display. **No secret value was read, printed or stored**,
> and none appears in this report.

### Isolated workspace — `/tmp/takeone-phase1-isolated` — SAFE

| Property | Value |
|---|---|
| Path | `/tmp/takeone-phase1-isolated` (outside the repository, outside the web root) |
| Created by | `git clone --no-hardlinks --branch development /var/www/takeone` |
| Commit | `c12d1ada0696d589c39b73a001e4ea4667a973e7` — identical to the original's `HEAD` |
| Branch | `development` |
| Tree state at creation | **clean** — `git status --short` returned 0 lines |
| Owner | `actionsrunner` (all commands run as that user, never as `root`) |

**Why a clone and not a copy.** A clone transfers only committed objects. Everything
sensitive in the original is either untracked or git-ignored, so none of it can cross:

| Asset | Result | How confirmed |
|---|---|---|
| `.env` (real credentials) | **not copied** | `ls .env` → absent; `.gitignore:3` ignores it |
| `storage/` contents | **not copied** | `find storage -type f` excluding `.gitignore`/`.gitkeep` → empty |
| `bootstrap/cache/config.php` | **not copied** | absent; `bootstrap/cache/` held only `.gitignore` |
| Database files | **not copied** | `find . -name "*.sqlite*"` (outside `.git`) → empty; `.gitignore:15` |
| Uploaded media | **not copied** | covered by the `storage/` and `public/storage` ignores |
| The 299 uncommitted source changes | **not copied** | `git status --short` in the clone → 0 lines |
| `vendor/` | **not copied** | absent until `composer install` was run in the clone |

**One deliberate exception, declared.** The two Phase 0 test files were **never committed**
(they were untracked in the original), so the clone did not contain them and the brief's
step 3 — run the Phase 0 tests — would have been impossible. They were therefore
reproduced in the isolated workspace as **new test files under `tests/`**, which the brief
expressly permits. They are byte-identical to the originals (md5 `146e191c…` Karate,
`453850…` Taekwondo). They contain **test code only** — no secrets, no application source,
no configuration. Nothing else was brought across from the dirty tree.

### Test-only environment file

The clone has no `.env`, and Laravel feature tests need an `APP_KEY`. A test-only `.env`
was written **in the isolated workspace only**, containing exactly the safe values the
brief specifies:

```
APP_ENV=testing            DB_CONNECTION=sqlite      CACHE_STORE=array
APP_DEBUG=true             DB_DATABASE=:memory:      SESSION_DRIVER=array
APP_KEY=<generated locally from /dev/urandom>        QUEUE_CONNECTION=sync
REALTIME_ENABLED=false     BROADCAST_CONNECTION=null MAIL_MAILER=array
```

The `APP_KEY` was generated locally from `/dev/urandom` for this throwaway workspace. It is
**not a real secret**, has never been used anywhere else, and its value is not reproduced
here. The file lives only under `/tmp` and is git-ignored (`.gitignore:3`), so it can never
be committed.

### Confirmation the original workspace was not modified

- `git status --short | wc -l` → **299 before, 299 after**.
- `md5sum` of both sports' `Scoring.php` unchanged across the phase.
- Every command after the clone ran with `cwd` inside `/tmp/takeone-phase1-isolated`.
- The single read of the original after cloning was `git show HEAD:<file>` and `git diff`
  — read-only operations — used to diagnose the Phase 0 test failures (§5).

---

## 3. Test Isolation Verification

All checks performed in `/tmp/takeone-phase1-isolated` **before** running any test.

| Check | Expected Safe Condition | Actual Result | Pass/Fail |
|---|---|---|---|
| Working directory | Isolated workspace, not the original repo | `pwd` = `/tmp/takeone-phase1-isolated`; every test command run from there | ✅ Pass |
| Git working tree | Clean, no tracked or untracked changes | `git status --short` → 0 lines at creation | ✅ Pass |
| Git commit | Same as original `HEAD` | `c12d1ada…` in both | ✅ Pass |
| **Test database** | Disposable `:memory:` SQLite | `phpunit.xml`: `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`; test `.env` agrees. No `*.sqlite` file exists in the workspace | ✅ Pass |
| **Cache** | No shared cache server | `phpunit.xml`: `CACHE_STORE=array` — in-process, per test | ✅ Pass |
| **Session** | No shared session store | `phpunit.xml`: `SESSION_DRIVER=array` | ✅ Pass |
| **Queue** | No worker, no queue server | `phpunit.xml`: `QUEUE_CONNECTION=sync` — jobs run inline | ✅ Pass |
| **Mail** | No SMTP connection | `phpunit.xml`: `MAIL_MAILER=array` — mail collected in memory. The real Gmail credentials were never present in this workspace | ✅ Pass |
| **Broadcast / MQTT** | No broker connection | `phpunit.xml`: `BROADCAST_CONNECTION=null`; test `.env`: `REALTIME_ENABLED=false`. No MQTT host/username/password/JWT secret exists in this workspace at all | ✅ Pass |
| **Config cache** | Must not exist | `bootstrap/cache/config.php` absent before and after. After `composer install`, `bootstrap/cache/` holds only `packages.php` + `services.php` (package discovery, git-ignored) | ✅ Pass |
| **PHPUnit availability** | Installed | `vendor/bin/phpunit` → PHPUnit 11.5.50 | ✅ Pass |
| **Laravel test guard** | `tests/TestCase.php` permits the run | Byte-identical to the original (md5 verified). Both of its conditions satisfied: no cached config, and the sqlite database is `:memory:`. It did not fire | ✅ Pass |
| External APIs / storage | None reachable or configured | No API keys, no bucket credentials, no storage disks beyond the local filesystem in `/tmp` | ✅ Pass |
| Process user | Never `root` | All commands run via `sudo -u actionsrunner` | ✅ Pass |

**Only after every row above passed was any test command executed.**

---

## 4. Commands Run

Every command below ran as `actionsrunner`, with `cwd = /tmp/takeone-phase1-isolated`
unless stated otherwise.

| # | Command | Where | Outcome |
|---|---|---|---|
| 1 | `git status --short`, `git rev-parse HEAD`, `ls`, `grep`, `sed` | original (read-only) | Established the original is unsafe (§2) |
| 2 | `git clone --no-hardlinks --branch development /var/www/takeone /tmp/takeone-phase1-isolated` | run as `actionsrunner` | Clean clone at `c12d1ada…` |
| 3 | `composer install --no-interaction --prefer-dist --no-progress` | isolated | Success; PHPUnit 11.5.50 available. `COMPOSER_HOME` pointed at a throwaway `/tmp` directory |
| 4 | `php artisan test --filter=KarateScoringSafetyTest` | isolated | **5 failed, 12 passed** — cause diagnosed in §5 |
| 5 | `git show HEAD:<file>`, `git diff --stat HEAD -- <scoring dirs>` | original (read-only) | Proved the failure cause: 549 uncommitted lines |
| 6 | `php artisan test --filter=TaekwondoScoringSafetyTest` | isolated | **21 passed** (129 assertions) |
| 7 | `php artisan test` | isolated | 155 failed, 1 skipped, 563 passed (1,944 assertions) — baseline before new tests |
| 8 | `php artisan test --filter=TaekwondoRealtimeTest` | isolated | 1 failed, 4 passed — the one failure is the Blade-rendering test (Vite manifest) |
| 9 | `php artisan test --filter=ScoringAuthorizationSafetyTest` | isolated | **21 passed** (199 assertions) |
| 10 | `php artisan test` | isolated | 155 failed, 1 skipped, **584 passed** (2,143 assertions) — failures unchanged, passes up by exactly the 21 new tests |
| 11 | `git diff --check`, `git status --short` | both | §9 |

### Commands deliberately NOT run, and why

| Command | Why not |
|---|---|
| `php artisan config:clear` / `cache:clear` / `config:cache` | Forbidden by the brief. Also unnecessary: the isolated workspace never had a config cache |
| `php artisan migrate` / `migrate:fresh` / `db:seed` | Forbidden as standalone commands. `RefreshDatabase` performs migrations **inside the `:memory:` database only**, per the verified isolation above |
| `queue:*`, `horizon`, any deployment script | Forbidden; nothing in this phase needs a worker (`QUEUE_CONNECTION=sync`) |
| `composer install` **in the original repository** | Forbidden. This is why the original still cannot run its own suite |
| `npm ci` / `npm run build` | **Not authorized** — the brief permits Composer installation only. This is the direct cause of most full-suite failures (§5); CI runs these two commands before `php artisan test` |
| Any `git` write command (`add`, `commit`, `push`, `reset`, `restore`, `checkout`, `clean`) | Forbidden in both workspaces |
| Any edit to the original repository after the clone | Forbidden |

---

## 5. Existing Test Baseline

### Karate characterization test — **5 failed, 12 passed** ⚠️ *understood, and not a scoring defect*

Failing: `the accepted command vocabulary is exactly the documented list`,
`the broadcast state carries every critical display value`,
`state survives a round trip through the cache blob`,
`the event level rule defaults are wkf as normally run`,
`the settings that outlive a bout are exactly the documented list`.

**Root cause — the single most important finding of this phase.** The Phase 0 tests were
written against the **uncommitted working tree** of `/var/www/takeone`, which is the code
actually deployed on staging. The isolated workspace is at **`HEAD` (`c12d1ada`)**, which
does not contain that work. Proven directly:

```
$ git show HEAD:app/Events/Sports/Karate/Tournament/Scoreboard/MatState.php | grep -c senshuRule
0
$ grep -c senshuRule app/Events/Sports/Karate/Tournament/Scoreboard/MatState.php   # working tree
4

$ git diff --stat HEAD -- app/Events/Sports/{Karate,Taekwondo}/Tournament/Scoreboard/
 Karate/.../MatState.php              | 115 +++++-
 Karate/.../ScoreboardController.php  | 180 ++++++++-
 Karate/.../Scoring.php               | 232 ++++++++++++--
 Taekwondo/.../ScoreboardController.php | 29 ++-
 Taekwondo/.../Scoring.php            |  19 ++
 5 files changed, 549 insertions(+), 26 deletions(-)
```

The five failures are exactly the tests that assert the configurable-rules feature
(`senshuRule`, `autoSenshu`, `winByPenalties`, `gap`, `warning`, `MatState::SETTINGS`, and
the extra `rules` command) — all of which exist **only** in the uncommitted work.

This also explains the split with Taekwondo: its `MatState` was **not** modified in the
working tree (only `Scoring.php` +19 and `ScoreboardController.php` +29), so its Phase 0
tests, which target `MatState` exclusively, pass unchanged at `HEAD`.

**Nothing was changed in response.** Not the production code, and not the Phase 0 tests —
they correctly characterize the deployed working-tree code; they simply cannot be satisfied
by a commit that predates it. Both readings are true at once, which is the point.

### Taekwondo characterization test — **21 passed** (129 assertions) ✅

Every Phase 0 Taekwondo expectation holds at `HEAD` unchanged.

### Full suite — **155 failed, 1 skipped, 584 passed** (2,143 assertions, 221s)

**The failures are overwhelmingly environmental, not application defects.** The string
`Vite manifest not found at: .../public/build/manifest.json` appears **607 times** in one
run. Any test that renders a Blade page through `layouts/app.blade.php` throws before it can
assert anything, because the frontend assets were never built.

CI builds them (`npm ci` → `npm run build`) **before** `php artisan test`; this phase is not
authorized to run npm, so that step is missing here. Demonstrated cleanly on the style
reference: `TaekwondoRealtimeTest` has 4 data/realtime tests that **pass**, and exactly one
page-rendering test that fails on the Vite manifest.

Breakdown of the 155:

| Cause | Count | Nature |
|---|---|---|
| Missing Vite manifest (un-built frontend assets) | the large majority — 607 error occurrences across ~40 test classes | Environmental. Resolved by `npm ci && npm run build` |
| Phase 0 Karate characterization | 5 | Version divergence, explained above |

**No failure was investigated as an application defect, and none was fixed** — per the
brief, unrelated pre-existing failures are documented, not repaired.

**Critically: the 21 new authorization tests added zero failures.** Passes rose from 563 to
584 — exactly the 21 new tests — while the failure count stayed at 155.

---

## 6. Authorization Tests Added

One new file: `tests/Feature/Events/ScoringAuthorizationSafetyTest.php` — **21 tests, 199
assertions, all passing on the first run.** Built on `Tests\TestCase`'s existing helpers
(`createUser`, `createClub`) and modelled on `TaekwondoRealtimeTest::scenario()`. **No new
factory infrastructure was created.** All data is fake and per-test.

Every refusal test also calls `assertNothingScored()`, which asserts on **both** layers of
state: the mat's live `MatState` (no bout loaded, both scores 0, mode still `upcoming`) and
the `event_matches` row that becomes the official record (`status` still `upcoming`,
`winner`, `a_score`, `b_score` all still null). A guard that returned the right status code
while still applying the command would fail these tests — a status assertion alone would
not catch it.

| Test | Sport | Endpoint | Expected Existing Behavior | Result | Evidence |
|---|---|---|---|---|---|
| an authorised organiser may command the mat | TKD + Karate | `POST /{sport}/control/{event:uuid}` | **2xx**, and the command really reaches the mat | ✅ 2 pass | `EventAccess::canScore()` → `canManage()`: `$event->created_by === $user->id` |
| an unauthenticated request cannot command the mat | TKD + Karate | same | **401** for a JSON request | ✅ 2 pass | `auth` middleware, `routes/web.php` group `['auth','verified','two-factor']` |
| a signed in user without scoring authority is refused | TKD + Karate | same | **403** | ✅ 2 pass | `abort_unless($this->canScore($event), 403)` |
| a karate event is refused by the taekwondo endpoint | Karate→TKD | TKD endpoint | **404** | ✅ pass | `abort_unless($event->sport === 'taekwondo', 404)` |
| a taekwondo event is refused by the karate endpoint | TKD→Karate | Karate endpoint | **404** | ✅ pass | `abort_unless($event->sport === 'karate', 404)` |
| a command outside the sport's vocabulary is rejected | TKD + Karate | same | **422**, error on `command` | ✅ 2 pass | `'command' => in:<Scoring::COMMANDS>` |
| a karate command is rejected by the taekwondo endpoint (`senshu`) | TKD | TKD endpoint | **422**, error on `command` | ✅ pass | `senshu` absent from Taekwondo's `Scoring::COMMANDS` |
| a taekwondo command is rejected by the karate endpoint (`gamjeom`) | Karate | Karate endpoint | **422**, error on `command` | ✅ pass | `gamjeom` absent from Karate's `Scoring::COMMANDS` |
| a mat the event does not run is rejected | TKD + Karate | same | **404** | ✅ 2 pass | `abort_unless($this->matExists($event, $data['mat']), 404)` |
| a missing mat is rejected | TKD + Karate | same | **422**, error on `mat` | ✅ 2 pass | `'mat' => ['required','string','max:40']` |
| a side outside the two corners is rejected | TKD + Karate | same | **422**, error on `side` | ✅ 2 pass | `'side' => in:aka,ao` |
| a taekwondo scoring action outside the approved five is rejected | TKD | TKD endpoint | **422**, error on `action` | ✅ pass | `'action' => in:<array_keys(MatState::ACTIONS)>` |
| a karate point outside one to three is rejected | Karate | Karate endpoint | **422**, error on `n` | ✅ pass | `'n' => ['integer','min:1','max:3']` |
| a taekwondo adjustment outside its range is rejected | TKD | TKD endpoint | **422**, error on `n` | ✅ pass | `'n' => ['integer','min:-5','max:5']` |

**Status codes were derived, not assumed.** Each was read out of the controller and route
definitions first, then confirmed by the test run. The 401 (rather than a 302 redirect) is
specifically the JSON contract: these tests use `postJson`, and Laravel's `auth` middleware
redirects a browser navigation but returns 401 to a JSON request. The distinction is stated
in a comment in the test itself.

**Why the positive control matters.** Without `an authorised organiser may command the mat`,
all thirteen refusal tests could pass against a route that was simply broken for everybody.
That test proves the endpoint accepts a legitimate organiser and genuinely writes to the mat,
which is what makes the refusals meaningful.

---

## 7. Findings

### Security and integrity findings demonstrated by the tests ✅

Every guard on the live scoring endpoints holds under direct attack, **in both sports**, and
in every refused case **nothing moved** — not the mat, not the record:

1. **Authentication is enforced at the endpoint**, not merely on the operator page.
2. **Authorization is per-event.** A signed-in member of the very same club — who can see the
   event — cannot score it. Visibility does not imply authority.
3. **The sport packages are genuinely isolated at the request layer.** A Karate event cannot
   be driven by Taekwondo's engine or vice versa, and each sport's command vocabulary is
   enforced by the endpoint rather than merely declared in a constant. This upgrades the
   Phase 0 unit-level assertion into an end-to-end guarantee.
4. **The mat namespace cannot be minted.** A caller cannot create cache entries by naming an
   arbitrary mat string.
5. **Payload bounds hold** — corners, action vocabulary and numeric ranges.

### ⚠️ FINDING P1-1 — The deployed staging code is not the committed code

**This is the most consequential finding of the phase, and it is an operational risk, not a
code defect.** `/var/www/takeone` carries **299 uncommitted paths**, including **549 changed
lines in the live scoring subsystem** that exist nowhere in git.

Consequences, all demonstrated during this phase:
- **CI does not test the code that is running.** CI builds from the commit; the commit lacks
  this work entirely.
- **A clean-clone test environment cannot reproduce staging behaviour** — proven by the five
  Karate characterization failures.
- **The work is one deploy away from deletion.** `/var/www/takeone` is simultaneously the
  `Deploy Staging` rsync target; a deploy overwrites uncommitted changes.

**Needs a human decision: commit this work, or deliberately discard it.** Nothing else in
this report can be fully trusted about staging until that is resolved. Not acted upon here —
committing is explicitly forbidden, and rightly so, as it is the author's call.

### ⚠️ FINDING P1-2 — The full suite cannot pass without a frontend build

155 failures, driven by 607 `Vite manifest not found` errors. Fixable with
`npm ci && npm run build`, which is not authorized in this phase. Worth noting that this makes
the suite **unusable as a local regression signal** unless the developer knows to build assets
first — which is not documented anywhere in `CLAUDE.md`.

### Carried forward from Phase 0, still unfixed (correctly)

- **The original repository still cannot run its own tests**: cached config present, PHPUnit
  absent. Both were deliberately left alone.
- The Phase 0 findings (Karate coercing a declared `points` win reason to `other`; Taekwondo
  restoring `punWinner` without validating it against `aka|ao`) remain open. **Neither was
  fixed, and neither is covered by the new tests** — this phase is authorization only.

### Testability limitations

1. **Scoring rules remain untestable through the endpoint** without a great deal more setup,
   and all rule methods are still `private` behind `Scoring::apply()`. Unchanged from Phase 0
   and deliberately out of scope here.
2. **The `two-factor` middleware was not independently exercised.** It sits on both scoring
   routes and did not interfere, but no test asserts its behaviour for a user who has 2FA
   enabled. A gap worth naming, not filled here.
3. **The paired-screen token endpoints** (`*-scoreboard.token-command`) were **not** covered.
   They are a second, session-less front door to the same scoring engine, authorized by a
   device token instead of a user. They deserve their own tests; adding them was beyond the
   brief's "smallest necessary tests" instruction.
4. **`MatState` assertions read the array cache**, which is safe and in-process here. They
   would not be safe against a shared cache store, so these tests must never be run outside an
   isolated environment.

---

## 8. File Changes

Every file created or modified, all inside `/tmp/takeone-phase1-isolated`:

| Path | Status | Note |
|---|---|---|
| `tests/Feature/Events/ScoringAuthorizationSafetyTest.php` | **Created** | The 21 authorization tests |
| `docs/PHASE_1_TEST_ENVIRONMENT_AND_AUTHORIZATION_REPORT.md` | **Created** | This report |
| `tests/Unit/Events/Sports/Karate/KarateScoringSafetyTest.php` | Reproduced | Phase 0 file, byte-identical; absent from the source commit (§2) |
| `tests/Unit/Events/Sports/Taekwondo/TaekwondoScoringSafetyTest.php` | Reproduced | Phase 0 file, byte-identical; absent from the source commit (§2) |
| `.env` | Created | Test-only values, git-ignored, `/tmp` only, no real secret |
| `vendor/`, `composer.lock` unchanged, `bootstrap/cache/{packages,services}.php` | Generated | `composer install` output; all git-ignored |

**In `/var/www/takeone`: no file created, modified or deleted.**

---

## 9. Git Safety Verification

### Original workspace — `/var/www/takeone`

| Moment | `git status --short \| wc -l` |
|---|---|
| Before Phase 1 | **299** |
| After Phase 1 | **299** |

`md5sum` of `app/Events/Sports/{Karate,Taekwondo}/Tournament/Scoreboard/Scoring.php` is
unchanged across the phase. No write of any kind occurred after the clone; the only
post-clone access was read-only (`git show`, `git diff`).

### Isolated workspace — `/tmp/takeone-phase1-isolated`

| Moment | `git status --short` |
|---|---|
| Immediately after clone | *(empty — 0 lines)* |
| After all work | `?? tests/Feature/Events/ScoringAuthorizationSafetyTest.php`<br>`?? tests/Unit/Events/` |

- `git diff --check` → **clean**, no whitespace errors.
- `git diff --stat HEAD` → **empty**. **Not one tracked file was modified.** Everything added
  is new and untracked; `.env` and `vendor/` do not appear because they are git-ignored.
- Only the two allowed categories changed: new test files under `tests/`, and a new Markdown
  report under `docs/`.

### Confirmation

- ✅ **No application source file** changed, in either workspace.
- ✅ **No route, Blade view, JavaScript, MQTT, camera, screen-pairing, bracket, registration,
  permission, policy, middleware or authentication file** changed.
- ✅ **No migration, schema, seeder, factory or existing test file** changed.
- ✅ **No configuration, credential, CI or deployment file** changed.
- ✅ **No dependency manifest or lock file** changed (`composer.json`/`composer.lock`/
  `package.json` are unmodified; only the ignored `vendor/` tree was populated).
- ✅ **Nothing was committed or pushed**, and no branch was created, switched or deleted.
- ✅ **No modification was copied back** to the original workspace.
- ✅ No destructive git command was run anywhere.

---

## 10. Recommended Next Step

**One step, and it needs a human, not a code change: decide what happens to the 299
uncommitted paths in `/var/www/takeone` — in particular the 549 changed lines in the live
scoring subsystem (Finding P1-1).**

Concretely: review that work and either commit it to `development`, or deliberately discard
it. Nothing larger should be attempted first, because every downstream activity depends on it:

- The Phase 0 Karate characterization tests can only be reconciled once "the current code" has
  a single meaning.
- CI is currently testing a version of the scoring engine that nobody is running.
- The work is one staging deploy away from being destroyed.

Once that commit exists, re-run this phase's isolated-workspace procedure against it. The
Karate tests should then pass as they do against the working tree, and the environment will
for the first time be testing what is actually deployed.

**No refactor is proposed.** Not a shared tournament kernel, not a scoring seam, not a durable
match-state store. The open Phase 0 findings should also stay open until there is a green,
trustworthy baseline that can prove a change altered nothing else.
