# F2 — Screen Token Security Review

> Read-only investigation in the isolated workspace `/tmp/takeone-karate-release-ws`
> (base commit `c12d1ada`, pending Karate release applied). `/var/www/takeone` was not touched.
> Date: 2026-08-29

## Decision

> ## **SAFE: Revoked and fabricated tokens are indistinguishable.**

Established both by reading the code and by observing the actual responses. The two cases are
not merely similar — they are **byte-identical**: same status, same redirect target, same
response body (identical SHA-1), same header names.

The failing assertion in `CourtDisplayTest` is a **stale expectation about the status code**
(404 → 302), not a regression in the property that test exists to protect.

## Evidence

Measured with a temporary probe run outside the repository (see § Method). All four requests
used a route-valid 40-character alphanumeric token.

| Case | HTTP Status | Redirect Target | Response Difference | Token Existence Leaked? |
|---|---:|---|---|---|
| **A** — real token, still paired *(control)* | `200` | — (renders the board) | Renders the mat board | n/a — a *valid* token is legitimately distinguishable; that is the feature |
| **B** — real token, **revoked** | `302` | `http://localhost/screen` | body 338 bytes, `sha1 2694061eb4b9…` | **No** |
| **C** — fabricated token (`AAAA…`, 40 chars) | `302` | `http://localhost/screen` | body 338 bytes, `sha1 2694061eb4b9…` | **No** |
| **D** — second fabricated token (`Zq7xxx…`) | `302` | `http://localhost/screen` | body 338 bytes, `sha1 2694061eb4b9…` | **No** |

**B, C and D are indistinguishable on every axis measured:**

- **Status** — all `302`.
- **Redirect target** — all `http://localhost/screen` (`route('screen.new')`).
- **Body** — all 338 bytes with the same SHA-1 prefix `2694061eb4b9`, i.e. the identical
  Symfony redirect page.
- **Headers** — identical header-name sets for the revoked and fabricated cases:
  `cache-control, date, location, content-type, x-ratelimit-limit, x-ratelimit-remaining, x-request-id, set-cookie`.
  Nothing in that set names the token or its history. (`x-request-id` and `set-cookie` vary
  per request by design, and vary identically for both cases.)

### Timing

Static structure gives no obvious signal. Both cases run **exactly one** indexed `SELECT`
against `court_displays` that returns zero rows, then take the same `return` on the same line.
Neither performs a write: `touchSeen()` is only reached when a device resolves, so a revoked
token does **not** leave a database write that a fabricated one lacks. There is no extra query,
no extra branch and no differing exception path between the two.

One honest caveat: a revoked token *hits* the `token_hash` index and is then filtered out by
`revoked_at IS NULL`, whereas a fabricated token misses the index entirely. That is a
theoretically different work profile inside SQLite. **Static analysis cannot rule out a
micro-timing difference**, and this review did not attempt statistical timing measurement.
Given the attack requires distinguishing two single-row-miss index lookups over a network,
against a rate-limited endpoint (`throttle:screen-token`), this is not a practical concern —
but it is what is *not* proven, as opposed to what is.

## Code Path

| File | Method | Role |
|---|---|---|
| `routes/web.php:68` | `Route::get('/court/{token}', …)->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token')` | The public door. The regex constraint means any token of the wrong shape 404s at routing, equally for real and fake, so it leaks nothing. Rate-limited |
| `app/Events/Sports/Taekwondo/Tournament/CourtDisplay/CourtDisplayController.php` | `board(Request $request, string $token)` | `$device = CourtDisplayDevice::resolve($token);` then a **single** guard: `if (! $device) { return redirect()->route('screen.new'); }` |
| `app/Events/Sports/Taekwondo/Tournament/CourtDisplay/CourtDisplayDevice.php` | `resolve(?string $token): ?self` | Returns `null` for both cases from **one** query: `where('token_hash', static::hash($token))->whereNull('revoked_at')->first()`. A revoked row is excluded by the same `WHERE` that a non-existent hash misses |
| `app/Events/Sports/Taekwondo/Tournament/CourtDisplay/CourtDisplayDevice.php` | `revoke()` | Sets `revoked_at = now()`; the row survives but stops resolving |
| `app/Events/Sports/Taekwondo/Tournament/CourtDisplay/CourtDisplayDevice.php` | `unclaim()` | The console's "unpair" — deliberately **not** `revoke()`, so the screen keeps a working token and returns to a pairing code |

The single-branch design is deliberate and documented in the controller itself:

> *"Still ONE response for a bad token and a revoked screen: a wall screen is scanned by
> whoever walks past it, and differing replies would tell them which tokens are real."*

The redirect (rather than a 404) is also deliberate, and is the reason the test's expectation
is now stale. The same comment explains it: a 404 leaves a television — or a tablet with no
BACK key — parked on an error page it cannot leave, recoverable only by clearing the app's
data. `/screen` shows a fresh pairing code, which is a state somebody in the hall can act on.
**The change traded a 404 for a redirect while preserving the single-response property.**

## Security Impact

None identified. An attacker probing `/court/{token}` learns only whether a token is
*currently valid and paired* (`200`) or *not usable* (`302` to `/screen`) — and the latter is
returned identically for a token that never existed, a token that was revoked, and a token
belonging to a deleted event. No response reveals that a token was ever valid, so the endpoint
does not map which tokens are real, and revoked tokens cannot be separated from noise to
narrow a search. The endpoint is additionally rate-limited (`throttle:screen-token`) and the
40-character token space is not realistically enumerable. The destination `/screen` grants
nothing: an unclaimed screen can render its own pairing code and nothing else.

## Required Action

> ## **Update only the failing test expectation.**

`tests/Feature/Events/CourtDisplayTest.php::test_an_unknown_or_revoked_token_is_indistinguishable_from_a_wrong_one`
asserts `assertNotFound()` on both requests. The behaviour it is really guarding — that the two
answer *identically* — still holds; only the status changed from 404 to a 302 redirect.

The stronger replacement, which would not have gone stale, is to assert the two responses
against **each other** rather than against a hard-coded status: same status, same `Location`,
same body. **No such edit was made here** — the brief forbids modifying tests, and the change
is the maintainer's to make.

Release position: **F2 is not a blocker.** The remaining Karate-release item is F1, the
`config('play.event_log')` → `config('events.match_log')` rename, which is a separate decision.

## Method

Static reading came first and was decisive on its own. It was then confirmed at runtime by a
temporary PHPUnit probe placed **outside** the repository at `/tmp/f2probe/F2ProbeTest.php`
and executed with the workspace's existing, already-installed PHPUnit and its existing
isolated configuration:

```
vendor/bin/phpunit --configuration phpunit.xml /tmp/f2probe/F2ProbeTest.php
```

The probe issued the four requests above and printed status, `Location`, body length, body
SHA-1 and header names. It created no file inside the repository and asserted nothing about
application behaviour. **It has since been deleted.** It was necessary because the existing
test aborts at its first failed assertion, so the fabricated-token branch never executes — the
precise gap flagged as unverified in the previous phase's report.

The suite runs on in-memory SQLite with array cache/session, sync queue and realtime disabled,
so no real database, broker or screen was involved.

## Safety Verification

- ✅ **No application code changed.** `git status --porcelain` in the workspace shows the same
  187 tracked modifications as before this investigation — all from the pending-release patch,
  none authored here. `git diff` on
  `app/Events/Sports/Taekwondo/Tournament/CourtDisplay/CourtDisplayController.php` and
  `…/CourtDisplayDevice.php` is unchanged from the patch state.
- ✅ **No test changed.** `tests/Feature/Events/CourtDisplayTest.php` is untouched — verified
  by checksum before and after. The failing test remains failing.
- ✅ **No migration, deploy, config or cache command was run.** No Composer, NPM, build, queue,
  seeder or database command either. The only command executed was the already-installed
  PHPUnit binary against a file outside the repository; its `RefreshDatabase` migrations ran
  solely against the per-test in-memory SQLite database.
- ✅ **`/var/www/takeone` was not touched** during the investigation.
- ✅ **Temporary artefacts removed:** `/tmp/f2probe/` was deleted after the run.
- ✅ **One report file created:** `docs/F2_SCREEN_TOKEN_SECURITY_REPORT.md`
