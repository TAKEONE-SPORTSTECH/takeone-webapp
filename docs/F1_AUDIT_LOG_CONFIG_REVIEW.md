# F1 — Audit Log Config Review

> Read-only investigation in the isolated workspace `/tmp/takeone-karate-release-ws`
> (base commit `c12d1ada`, pending Karate release applied). `/var/www/takeone` was not touched.
> Date: 2026-08-29

## Decision

> ## **SAFE: Rename is intentional; update test/config documentation only.**

The rename is deliberate, documented in the new config file itself, and — the decisive point —
**the deployment-facing environment variable did not change**. Both the old and the new config
key read `env('EVENT_MATCH_LOG', true)`. Only the internal config path moved
(`play.event_log` → `events.match_log`), and the single application call site moved with it.

### ⚠️ Correction to the previous report

`docs/KARATE_RELEASE_SAFETY_REPORT.md` (§5, F1) stated:

> *"anyone who had the officiating log disabled through the old key will find it **silently
> re-enabled** after this release."*

**That is wrong, and this investigation disproves it.** It assumed the env var was renamed
alongside the config key. It was not:

| | Config key | Backing env var |
|---|---|---|
| Before (`config/play.php:34`, at `HEAD`) | `play.event_log` | `env('EVENT_MATCH_LOG', true)` |
| After (`config/events.php:27`) | `events.match_log` | `env('EVENT_MATCH_LOG', true)` |

A deployment with `EVENT_MATCH_LOG=false` in its environment keeps the log switched off across
this release, with no action required. **There is no operational impact and no migration step.**

## Evidence

| # | Check | Path | Finding |
|---|---|---|---|
| 1 | Does `config/events.php` exist and define `match_log`? | `config/events.php:27` | **Yes.** `'match_log' => env('EVENT_MATCH_LOG', true)`. Its docblock states the move explicitly: *"It lived in config/play.php until the video-platform integration was removed, which was always the wrong home: the log is ours and has nothing to do with anybody else's platform."* It also names the kill switch for operators: *"set EVENT_MATCH_LOG=false"* |
| 2 | Does `config/play.php` still exist? | `config/play.php` | **Intentionally removed.** `git status` shows ` D config/play.php`, one of the 12 deletions in the documented Play disconnection (2026-08-27). `config('play.event_log')` therefore now resolves to `NULL` — confirmed at runtime |
| 3a | `play.event_log` references | `tests/Feature/Events/MatchEventLogTest.php:192` and `:201` | **Two stale test call sites — the only ones in the codebase.** No application code references the old key |
| 3b | `events.match_log` references | `app/Events/Support/MatchEventLog.php:67` | **One application call site**, correctly updated: `if (! config('events.match_log', true)) { return; }`. Also referenced in `docs/SPORTS_ARCHITECTURE_AUDIT.md` and `…_EVIDENCE_BUNDLE.md`, which already describe the new key |
| 3c | `event_log` / `match_log` elsewhere | `resources/views/...`, `lang/{en,ar}/member.php` | Unrelated — the `*_personal_event_log` translation keys for the member profile's "Personal Event Log" section. No collision |
| 4 | Could a deployment still use `PLAY_EVENT_LOG`? | whole repo, `.env.example` | **No — `PLAY_EVENT_LOG` does not appear anywhere and never did.** The env var has always been `EVENT_MATCH_LOG`. `.env.example` defines none of the three names, so the default (`true`, logging on) applies unless an environment sets it |

### The two stale test call sites

Only one of them fails, and the other is arguably the more misleading:

| Test | Result | Why |
|---|---|---|
| `test_nothing_is_recorded_when_the_log_is_switched_off` (`:192`) | **FAILS** — `Failed asserting that 1 is identical to 0` at `:196` | It sets a key nothing reads, so the log stays on and one row is written |
| `test_scoring_still_works_when_the_log_is_switched_off` (`:201`) | **PASSES — but vacuously** | It sets the same dead key, so it actually runs with logging **on**. It no longer tests what its name claims. A green test asserting nothing is worth flagging alongside the red one |

## Test Results

`php artisan test --filter=MatchEventLogTest`

> **1 failed, 9 passed** (24 assertions)

The single failure is `test_nothing_is_recorded_when_the_log_is_switched_off`:
`Failed asserting that 1 is identical to 0` at `tests/Feature/Events/MatchEventLogTest.php:196`.

Notably, `test_a_broken_log_does_not_stop_the_bout` still passes — the property that matters
most (the recorder can never break a live mat) is intact.

### Runtime proof of both keys

Static reading was confirmed by a temporary probe placed **outside** the repository
(`/tmp/f1probe/F1ProbeTest.php`), run with the workspace's already-installed PHPUnit and its
existing isolated configuration, then deleted. Each case ran one `load` command and counted
`event_match_events` rows:

| Case | Rows written | Meaning |
|---|---:|---|
| `config(['events.match_log' => false])` | **0** | ✅ The new key **does** disable logging — the kill switch works |
| `config(['play.event_log' => false])` | **1** | ✅ The old key has **no effect** — exactly the test failure, reproduced from first principles |
| No config override (default) | **1** | Logging on by default, as intended |
| Observed values | `events.match_log = true`, `play.event_log = NULL` | The old path is gone, not shadowed |

Both halves of the question asked are therefore answered affirmatively and by observation, not
inference: the new key works, and the old key is inert.

## Required Action

> ## **Update only the outdated test.**

Change both call sites in `tests/Feature/Events/MatchEventLogTest.php` (lines 192 and 201) from
`config(['play.event_log' => false])` to `config(['events.match_log' => false])`. That fixes the
failing test and restores the second test to actually testing its stated behaviour.

**No such edit was made here** — this brief forbids modifying tests, and the change is the
maintainer's to make.

No backwards-compatible config fallback is needed. A fallback would exist to protect
deployments still setting the old key, and there are none to protect: the env var is unchanged,
`PLAY_EVENT_LOG` never existed, and `config('play.event_log')` was an internal path that only
application code and these two tests ever read.

Optional, not required: `.env.example` documents none of these keys. Adding a commented
`# EVENT_MATCH_LOG=true` line would make the kill switch discoverable to an operator who needs
it mid-event — a documentation nicety, not a release blocker.

## Release position

**F1 is not a blocker.** Combined with F2 (SAFE, stale status-code expectation), both of the
two new test failures introduced by the pending Karate release are **stale test expectations,
not regressions**. The remaining 18 failures in the full suite are pre-existing at `HEAD` and
unrelated to this release.

## Safety Confirmation

- ✅ **No application code changed.** The workspace still shows exactly the 187 tracked
  modifications from the release patch — all from `TRACKED_CHANGES.patch`, none authored here.
  `app/Events/Support/MatchEventLog.php` is untouched.
- ✅ **No test changed.** `tests/Feature/Events/MatchEventLogTest.php` is byte-identical to its
  patched state; the failing test remains failing and the vacuous one remains passing.
- ✅ **No config file changed.** `config/events.php` was read only; `config/play.php` remains
  deleted by the release patch, not by this investigation.
- ✅ **No migration, deploy, cache, build, Composer or NPM command was run.** The only command
  executed was the already-installed PHPUnit binary — once against `MatchEventLogTest`, once
  against a probe file outside the repository. `RefreshDatabase` migrated only the per-test
  in-memory SQLite database.
- ✅ **No secret or `.env` value was read.** `.env.example` was searched with `grep` for three
  specific key names only; the deleted `config/play.php` was inspected at `HEAD` with a grep
  restricted to its `event_log` line, so no API URL, token or credential was printed.
- ✅ **`/var/www/takeone` was not touched.**
- ✅ **Temporary artefacts removed:** `/tmp/f1probe/` deleted after the run.
- ✅ **One report file created:** `docs/F1_AUDIT_LOG_CONFIG_REVIEW.md`
