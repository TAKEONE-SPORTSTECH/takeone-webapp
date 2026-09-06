# Karate Scoring Release Dependencies

> **Read-only review.** Nothing in `/var/www/takeone` was created, modified, staged, committed
> or deployed. No migration, test, build or artisan command was run. No `.env` or secret was
> opened. Every claim below cites a path and, where it matters, a class and method.
>
> Date: 2026-08-29 · Branch `development` · HEAD `c12d1ada0696d589c39b73a001e4ea4667a973e7`

---

## Executive Decision

> ## **Cannot be safely isolated; must include shared contract/domain changes**

A "Karate scoring only" commit is not possible. Three hard dependencies reach outside the
Karate package, and each one is a fatal error rather than a degraded feature:

1. **`Scoring.php` calls a class that does not exist yet.** The working-tree
   `app/Events/Sports/Karate/Tournament/Scoreboard/Scoring.php` imports
   `App\Events\Support\Cameras\CameraFleet` and calls `CameraFleet::observe()` on **every**
   command. That file is **untracked** — it is not in `HEAD`. Ship Scoring without it and
   every scoring command dies with `Class "…CameraFleet" not found`.
2. **The rule set has nowhere to persist.** `MatState::persistSettings()` writes
   `club_events.scoreboard_settings`, a column added by an **untracked** migration, and the
   `array` cast that makes it usable is an **uncommitted** change to `app/Models/ClubEvent.php`.
3. **The console page cannot render.** `ScoreboardController::control()` calls
   `route('karate-scoreboard.photo', …)`, a route name that **does not exist in `HEAD`**.
   Omit `routes/web.php` and the operator console 500s before it draws.

The `EventType` contract change is the one shared item that is *not* dangerous — see
§ EventType Contract Impact. It still must ship as an atomic pair with `AbstractEventType`.

**Minimum release unit: 22 files shipped atomically, plus 2 migrations run before the code
goes live.**

---

## Release Unit Table

### Required — must ship atomically (22)

| Path | Git Status | Role | Required? | Why | Risk if omitted |
|---|---|---|---|---|---|
| `app/Events/Sports/Karate/Tournament/Scoreboard/Scoring.php` | modified | The scoring engine (+219/−13) | **Required** | Applies every command; owns the new rules, auto-end and gap logic | The release has no content |
| `app/Events/Sports/Karate/Tournament/Scoreboard/MatState.php` | modified | Mat state + rule set (+114/−1) | **Required** | Declares `SETTINGS`, `senshuRule`, `gap`, `warning`; `load()`/`persistSettings()` | `Scoring` references properties that do not exist → fatal |
| `app/Events/Sports/Karate/Tournament/Scoreboard/ScoreboardController.php` | modified | HTTP endpoint (+170/−10) | **Required** | Validates the widened vocabulary (`rules`, `level`, `winner`, `reason`) and the console payload | New commands rejected by the old validator; console cannot be built |
| `app/Events/Sports/Karate/Tournament/Tournament.php` | modified | Event-type package (+13) | **Required** | Overrides `reloadHallScreens()`; `recordOutcome()` is called by `Scoring::commit()` | Hall screens never reload after a screen-media change |
| `app/Events/Sports/Karate/Karate.php` | modified | Sport definition | **Required** | Sport-level vocabulary the package resolves | Package resolution inconsistent with its Tournament |
| `app/Events/Sports/Karate/Tournament/CourtDisplay/CourtDisplayController.php` | modified | Public board endpoint | **Required** | Serves the payload the wall screen renders from the same `MatState` | Wall board reads a shape the console no longer writes |
| `…/resources/views/scoreboard/control.blade.php` | modified | Operator console (+1272/−602) | **Required** | The UI for every new control; largest change in the tree | Officials cannot reach the new rules at all |
| `…/resources/views/scoreboard/mat.blade.php` | modified | Mat view | **Required** | Renders the same state shape | Renders stale fields |
| `…/resources/views/court-display/board.blade.php` | modified | Wall board | **Required** | Draws senshu, penalties, the gap and the auto-end hold | Hall shows a bout state that no longer matches the table |
| `…/resources/views/court-display/screen.blade.php` | modified | Screen shell | **Required** | Screen bootstrap for the board | Screen cannot mount the board |
| `…/Tournament/resources/lang/en/messages.php` | modified | Strings | **Required** | `Scoring::apply()` throws `__('event-karate_tournament::messages.commit_no_bout')` | Raw translation keys shown to officials mid-bout |
| `…/Tournament/resources/lang/ar/messages.php` | modified | Strings (AR) | **Required** | Same, Arabic | Untranslated console in Arabic |
| `app/Events/Sports/Karate/resources/lang/en/messages.php` | modified | Sport strings | **Required** | Sport-level vocabulary | Missing keys |
| `app/Events/Sports/Karate/resources/lang/ar/messages.php` | modified | Sport strings (AR) | **Required** | Same, Arabic | Missing keys |
| `app/Models/ClubEvent.php` | modified | Model (+2) | **Required** | Adds `'scoreboard_settings'` to `$fillable` and the `=> 'array'` cast | Without the cast the JSON is a raw string; `(array)` yields garbage and `persistSettings()` writes a string |
| `routes/web.php` | modified | Routing | **Required** | Defines `karate-scoreboard.photo`, referenced by `ScoreboardController::control()` | **Console 500s** — `RouteNotFoundException` at render |
| `app/Events/Contracts/EventType.php` | modified | Contract (+14) | **Required** | Declares `reloadHallScreens(ClubEvent): void` | See below — must pair with the abstract |
| `app/Events/AbstractEventType.php` | modified | Base class (+33) | **Required** | Supplies the no-op default for `reloadHallScreens()` **and** `matPanel()` | Interface method with no default → **every** event package fatals |
| `app/Events/Support/Cameras/CameraFleet.php` | untracked | Camera fan-out | **Required** | `Scoring::apply()` calls `CameraFleet::observe()` unconditionally | **Fatal `Class not found` on every scoring command** |
| `app/Events/Support/Cameras/CameraChannel.php` | untracked | Camera transport | **Required** | `CameraFleet::observe()` calls `CameraChannel::sendMany()` | Fatal inside `observe()` |
| `app/Models/EventCamera.php` | untracked | Model | **Required** | `CameraFleet` imports it and calls `EventCamera::whereIn(...)->update(...)` | Fatal inside `observe()` |
| `app/Models/EventCameraClip.php` | untracked | Model | **Required** | `EventCamera::clips()` returns `hasMany(EventCameraClip::class)` | Fatal if the relation is touched; ship for coherence |

### Required before deployment — migration prerequisites (2)

| Path | Git Status | Role | Required? | Why | Risk if omitted |
|---|---|---|---|---|---|
| `database/migrations/2026_08_24_150000_add_scoreboard_settings_to_club_events.php` | untracked | Adds `club_events.scoreboard_settings` | **Required before deployment — HARD** | `MatState::persistSettings()` runs an UPDATE on that column | **500 on the first `rules` or `duration` command.** Reads degrade safely; writes do not — see § Migration Compatibility |
| `database/migrations/2026_08_23_120000_create_event_cameras_table.php` | untracked | Creates `event_cameras`, `event_camera_clips` | **Required before deployment — SOFT** | `CameraFleet::forCourt()` queries `event_cameras` | Cameras silently do nothing. **Not** fatal: the query sits inside `rescue()` in `CameraFleet::observe()` |

### Recommended — tests, docs, supporting (9)

| Path | Git Status | Role | Required? | Why | Risk if omitted |
|---|---|---|---|---|---|
| `tests/Unit/Events/Sports/Karate/KarateScoringSafetyTest.php` | untracked | Characterization | **Recommended — commit together** | Asserts exactly the rules this release adds. Fails against `HEAD`; passes once this unit lands | Red build if committed before; no regression net if never |
| `tests/Feature/Events/ScoringAuthorizationSafetyTest.php` | untracked | Authorization | **Recommended** | Locks 403/404/422 on the endpoint this release rewrites | Endpoint rewrite proceeds with no authorization net |
| `tests/Unit/Events/Sports/Taekwondo/TaekwondoScoringSafetyTest.php` | untracked | Characterization | **Recommended** | Proves the shared contract change did not disturb the other sport | Cross-sport regression invisible |
| `app/Events/Support/ScreenMedia.php` | modified | Screen audio (+4) | **Recommended** | `ScoreboardController` imports it for the audio panel | Panel may reference an older shape |
| `app/Events/Support/ScreenMediaController.php` | modified | Screen audio (+11) | **Recommended** | Calls `->reloadHallScreens($event)` — the reason the contract changed | The new contract method has no caller; harmless but incoherent |
| `app/Events/Support/HallScreenRouter.php` | modified | Screen routing (+83) | **Recommended** | Routes a paired screen to the right sport's board | Cross-sport screen misrouting (a bug this file's docblock already records) |
| `app/Events/Support/ScreenPairingController.php` | modified | Pairing (+113/−…) | **Recommended** | Pairing flow feeding the console's screens panel | Console screens panel may not match the pairing it talks to |
| `app/Events/Support/MatchEventLog.php` | modified (1 line) | Audit trail | **Recommended** | `config('play.event_log')` → `config('events.match_log')` | None — both forms fall back to `true`. Safe either way |
| `config/events.php` | untracked | Config | **Recommended** | Backs the key above | None — `config('events.match_log', true)` defaults to `true` when the file is absent |

### Optional — related, not required (3)

| Path | Git Status | Role | Required? | Why | Risk if omitted |
|---|---|---|---|---|---|
| `database/migrations/2026_08_26_100000_add_broadcasting_to_event_cameras.php` | untracked | Adds `broadcasting`, `live_stream_id` | **Optional** | Karate scoring never reads either column; `live_stream_id` belongs to the live-stream subsystem | None for Karate scoring |
| `app/Events/Support/Cameras/CameraConsoleController.php` | untracked | Camera console UI | **Optional** | No reference from any Karate scoring or console file (`grep` for `camera` in `control.blade.php` returns nothing) | None for Karate scoring |
| `app/Events/Support/Cameras/CameraController.php` | untracked | Camera device endpoint | **Optional** | Same — the phone's own endpoint, not on the scoring path | None for Karate scoring |

### Excluded — unrelated after review

Taekwondo package files (their own release unit), Open Mat, the media/live-stream subsystem
(`app/Media/*`, `app/Events/Support/Live/*`), TV/mobile (`TV/**`), generic UI/profile Blade,
`Documentation/*` beyond the Karate reports, and all remaining migrations. **`config/play.php`
(deleted) and the 11 other Play deletions are excluded** — `MatchEventLog` no longer reads
`config('play.*')`, and the fallback default makes either ordering safe.

---

## Dependency Flow

Traced statically through the working tree. Each hop cites file and method.

```
Karate operator command  (POST /karate/control/{event:uuid})
│
├─ route  ················  routes/web.php:444  name('karate-scoreboard.command')
│                          middleware ['auth','verified','two-factor'] + throttle:300,1
│                          ⚠ routes/web.php:450 name('karate-scoreboard.photo') is NEW —
│                            absent from HEAD, and control() builds a URL from it
│
├─ ScoreboardController::command()
│     ·  abort_unless($this->canScore($event), 403)  → EventAccess::canScore()
│     ·  abort_unless($event->sport === 'karate', 404)
│     ·  $request->validate([... 'command' => in:Scoring::COMMANDS ...])
│     ·  abort_unless($this->matExists($event, $data['mat']), 404)
│     └─ $this->scoring->apply($event, $mat, $command, $data)
│
├─ Scoring::apply()
│     ├─ MatState::load($event, $court)
│     │     └─ READS  $event->scoreboard_settings          ← ClubEvent cast 'array'
│     ├─ $this->settleClock($state) → autoEnd()/finish()
│     ├─ match($command) → point() / penalty() / senshu() / rules() / commit() …
│     │     └─ rules(), durationCommand()
│     │           └─ MatState::persistSettings($event)
│     │                 └─ WRITES $event->forceFill(['scoreboard_settings'=>…])->saveQuietly()
│     │                    ⚠ UNGUARDED — no rescue/try. Missing column ⇒ QueryException ⇒ 500
│     ├─ MatchEventLog::record(... sport:'karate' ...)      best-effort (try/catch, line 66/95)
│     │     └─ table event_match_events — migrations ALREADY COMMITTED
│     ├─ CameraFleet::observe($event,$court,$command,$matchId,$bout)
│     │     ⚠ class is UNTRACKED — fatal if omitted
│     │     └─ rescue(fn() => …)  ← everything below is best-effort
│     │           ├─ EventCamera::whereIn(...)->update(['recording'…])   table event_cameras
│     │           └─ CameraChannel::sendMany($cameras, [...])            MQTT
│     └─ $state->save($event, $court)   → Cache::put, 240-minute TTL
│
├─ commit only → Tournament::recordOutcome()  → writes event_matches, advances the draw
│
└─ ScreenChannel::notifyCourt($event, $court, …)      public display
      ·  guarded by \Realtime()->enabled()
      ·  wrapped in rescue(...)                        ← best-effort
      └─ MQTT topic per CourtDisplayDevice → wall board redraws from MatState::toArray()
```

**Persistence summary.** Only two things outlive the request: `event_matches` (via
`recordOutcome()` on `commit`) and `club_events.scoreboard_settings` (via `persistSettings()`).
The running score itself is **cache only** — `MatState::save()`, 240-minute TTL — so a cache
flush mid-bout loses it and nothing rebuilds it from `event_match_events`.

---

## Migration Compatibility

Static analysis of
`database/migrations/2026_08_24_150000_add_scoreboard_settings_to_club_events.php`.

| Question | Finding |
|---|---|
| Does it add the exact column/type the code expects? | **Yes.** `$table->json('scoreboard_settings')->nullable()` matches `ClubEvent`'s `'scoreboard_settings' => 'array'` cast — Laravel JSON-encodes on write, decodes to array on read |
| Additive and safe for existing rows? | **Yes.** A single nullable `ADD COLUMN`; existing rows get `NULL`. No backfill, no data rewrite, no index |
| Reversible `down()`? | **Yes** — `dropColumn('scoreboard_settings')`. ⚠️ Dropping it **discards every configured rule set**; on SQLite older than 3.35 `dropColumn` is a table rebuild. Take a verified backup before rolling back |
| Does the code handle a null/missing/empty value? | **On read, yes.** `MatState::load()` does `foreach ((array) $event->scoreboard_settings as …)` — `(array) null` is `[]`, so the loop is skipped and the constructor defaults stand, which the migration's own docblock states are the previous hard-coded values. **On write, no** — see below |
| Model cast compatible? | **Yes**, and the cast is itself uncommitted: `app/Models/ClubEvent.php:143` `'scoreboard_settings' => 'array'`, plus `:83` in `$fillable`. `persistSettings()` uses `forceFill()`, so `$fillable` is belt-and-braces rather than load-bearing |
| Any existing migration adding a similar field? | **No.** `grep` across `database/migrations/` returns this file only — no collision, no duplicate |
| Depends on earlier uncommitted migrations? | **No.** It anchors with `->after('day_courts')`; `day_courts` comes from `2026_06_20_170000_add_court_config_to_events.php`, which is **committed**. This migration can therefore run against `HEAD`'s schema. (On SQLite, Laravel ignores `after()` — column order differs, which is harmless) |
| Does any code read `scoreboard_settings` before the migration could run? | **Yes — `MatState::load()`, on every single command.** But the read is safe: an attribute absent from the table returns `null`, and `(array) null` is `[]` |
| **Could migration order cause a live request failure?** | **YES — and this is the release's sharpest edge.** If the code is deployed before the migration runs, the first `rules` or `duration` command reaches `MatState::persistSettings()` → `forceFill([...])->saveQuietly()` → `UPDATE club_events SET scoreboard_settings = …` → *no such column* → **QueryException → HTTP 500 at a live scoring table**. It is not swallowed: the call sites at `Scoring.php:623` and `:648` have no `rescue()` or `try`. Worse, the guard `if (((array) $event->scoreboard_settings) !== $settings)` **cannot prevent it** — before the migration the left side is `[]` and the right side is a 9-key array, so the write is always attempted |

**Conclusion: the migration must complete before the new code serves traffic.** Deploy order
is `artisan down` → migrate → release code → `artisan up`, never code-first.

The camera migration is the opposite case: `CameraFleet::observe()` performs its
`EventCamera` query **inside** `rescue()`, so a missing `event_cameras` table degrades to
"cameras do nothing" rather than failing the bout. Still run it before deploying — a silent
loss of match footage is expensive in its own way.

---

## EventType Contract Impact

`app/Events/Contracts/EventType.php` is **modified**, adding exactly one method:

```php
public function reloadHallScreens(ClubEvent $event): void;
```

**Every implementation, and whether it is affected:**

| Implementation | Declaration | Modified? | Satisfies the new method? |
|---|---|---|---|
| `app/Events/AbstractEventType.php` | `class AbstractEventType implements EventType` | **Yes (+33)** | **Supplies the no-op default** — plus a default `matPanel()` |
| `app/Events/Sports/Karate/Tournament/Tournament.php` | `extends AbstractEventType` | Yes (+13) | Overrides it → `ScreenChannel::notifyCourt($event, null, ['action' => 'reload'])` |
| `app/Events/Sports/Taekwondo/Tournament/Tournament.php` | `extends AbstractEventType` | **No** | Inherits the default ✅ |
| `app/Events/Generic/GenericEvent.php` | `extends AbstractEventType` | **No** | Inherits the default ✅ |
| `app/Events/Sparring/Sparring.php` | `extends AbstractEventType` | **No** | Inherits the default ✅ |
| `app/Events/OpenMat/OpenMat.php` | `extends AbstractEventType` (untracked/new) | n/a — new file | Inherits the default ✅ |

**Compile/runtime risk:** one method **added**; none changed or removed. Because all five
concrete classes extend `AbstractEventType`, and the abstract supplies a default, the change
is **backwards compatible** — no untouched package breaks.

**The one hard rule:** `EventType.php` and `AbstractEventType.php` must be committed **in the
same commit**. An interface method without the abstract's default makes `AbstractEventType`
itself non-instantiable and every event package fatal on resolve.

**Separability:** the Karate scoring release **can** carry this contract pair safely — it does
not drag in Open Mat, Sparring or Taekwondo, because none of them needs a code change. Note
that the *motivation* is elsewhere: the only callers of `reloadHallScreens()` are
`ScreenMediaController.php:52` and `:100`. Karate is a consumer of the change, not its cause.

**A related, lower-severity observation (not a defect to fix here):** `matPanel()` was added to
`AbstractEventType` but **not** to the `EventType` interface, while both
`Karate/…/ScoreboardController.php:86` and `Taekwondo/…/ScoreboardController.php:78` call
`app(EventTypeRegistry::class)->for($event)->matPanel(...)` on an interface-typed value.
Runtime is fine (every concrete type extends the abstract), but static analysis will flag it,
and a future package that implements `EventType` directly would fatal. Worth a decision;
**not changed here**.

**Packages to test before committing the contract pair:** all five, since all five resolve
through the registry — Karate, Taekwondo, Generic, Sparring, Open Mat.

---

## Realtime, Screen, and Camera Impact

| Dependency | Call site | Classification | Notes |
|---|---|---|---|
| `club_events.scoreboard_settings` write | `MatState::persistSettings()` ← `Scoring::rules()`, `durationCommand()` | **Required for score correctness** | Unguarded; the only synchronous DB write on the non-commit path |
| `event_matches` write | `Tournament::recordOutcome()` ← `Scoring::commit()` | **Required for score correctness** | The official record; the one command that makes a result a fact |
| `MatchEventLog::record()` | `Scoring::apply()`, every command | **Optional / best-effort observability** | `try`/`catch` at lines 66/95 — "a mat must never stop because an audit row did not insert". Tables already committed |
| `CameraFleet::observe()` | `Scoring::apply()`, every command | **Optional / best-effort observability** — *but the class itself is a hard requirement* | Body wrapped in `rescue()`; the **static call is not**, so a missing class is fatal while a missing table is not |
| `CameraChannel::sendMany()` | `CameraFleet::observe()` | **External/hardware — manual rehearsal** | Pushes record/stop to phones over MQTT |
| `ScreenChannel::notifyCourt()` | `ScoreboardController::command()` after apply | **Required for public-display correctness** | `Realtime()->enabled()` guard + `rescue()`. Score stays right; the hall goes stale |
| `ScreenChannel::notify()` / `credentials()` | pairing + screen bootstrap | **External/hardware — manual rehearsal** | Mints per-device JWTs; subscribe-only ACL |
| `CourtDisplayController::payload()` | wall board polling/refresh | **Required for public-display correctness** | Renders from the same `MatState::toArray()` shape |
| `Tournament::reloadHallScreens()` | `ScreenMediaController` | **Required for public-display correctness** | The contract change's only caller |

**Reading of this table:** a Karate bout can be scored correctly with the broker down and no
cameras present. What cannot be missing is `CameraFleet.php` (class resolution) and the
`scoreboard_settings` column (unguarded write). Everything else on the realtime path fails
soft by design.

---

## Test Coverage and Gaps

### Existing tests that touch this release unit

| Test | Relevance |
|---|---|
| `tests/Feature/Events/CourtDisplayTest.php` | Court-display payload — the public board this release changes |
| `tests/Feature/Events/CourtScreenPairingTest.php` | Pairing flow feeding the console's screens panel |
| `tests/Feature/Events/MatchEventLogTest.php` | The audit trail written by `Scoring::apply()` |
| `tests/Feature/Events/NextBoutTest.php` | Running order after `commit` |
| `tests/Feature/Events/TaekwondoTournamentPackageTest.php`, `TaekwondoRealtimeTest.php` | Cross-sport regression guard for the contract change |

**No existing test asserts a single Karate scoring rule.**

### New tests that should be committed with this unit

| Test | Why |
|---|---|
| `tests/Unit/Events/Sports/Karate/KarateScoringSafetyTest.php` | 17 tests; asserts precisely the rules this release introduces. **Fails against `HEAD` and passes only once this unit lands** — commit it with the unit, never before |
| `tests/Feature/Events/ScoringAuthorizationSafetyTest.php` | 21 tests over the endpoint being rewritten. Passes against `HEAD` today, so it is safe to commit first as a pre-existing net |
| `tests/Unit/Events/Sports/Taekwondo/TaekwondoScoringSafetyTest.php` | Proves the shared contract change did not disturb the other sport |

### Missing tests — gaps a safe release should close

1. **`persistSettings()` round-trip.** Nothing asserts that a `rules` command writes
   `club_events.scoreboard_settings` and that a cold `MatState::load()` restores it. This is
   the feature's entire point and its only unguarded DB write.
2. **Behaviour with a NULL `scoreboard_settings`.** No test covers an event that predates the
   migration falling back to defaults.
3. **The rules themselves through the endpoint.** Every rule method in `Scoring` is `private`
   behind `apply()`, which needs a DB, cache, `MatchEventLog` and `CameraFleet`. No test
   drives auto-senshu, the point gap, the penalty ladder → hansoku, or the
   `awaitingDecision` hold end to end.
4. **`CameraFleet::observe()` degradation.** Nothing asserts that a missing `event_cameras`
   table leaves scoring unaffected — the `rescue()` is load-bearing and untested.
5. **Contract conformance.** No test asserts every registry entry satisfies `EventType`.
6. **Karate authorization parity.** The new authorization test covers Karate and Taekwondo,
   but not the token-paired screen door (`karate-scoreboard.token-command`), a second
   session-less route into the same engine.

### Tests that require built frontend assets

Anything rendering a Blade page through `layouts/app.blade.php` — `CourtDisplayTest`,
`EventPagesRenderTest`, `EventConsoleTest`, and any future console-render test. Phase 1
measured **607 `Vite manifest not found` errors / 155 failures** without a build. Run
`npm ci && npm run build` in the isolated clone first.

### Manual staging checks

See the next section.

---

## Required Manual Rehearsal

Automated tests cannot reach these. Each needs a human at a mat.

| # | Rehearsal | What it proves |
|---|---|---|
| 1 | **Full bout on a real mat with a paired wall screen** | Console and board agree point by point |
| 2 | **Senshu** — first unopposed point, then level the score | Auto-senshu awards once and breaks the tie on the board |
| 3 | **Point gap** — reach the configured gap | Bout auto-ends, board holds the celebration, table is asked how it ended |
| 4 | **Penalty ladder to `H`** | Hansoku hands the bout to the other corner with fewer points, and the board says so |
| 5 | **Auto-end confirmation** (`awaitingDecision`) | The hall sees no winner until an official confirms |
| 6 | **Set rules in the morning, reload after > 4 hours** | Settings survive the cache TTL — the feature's whole purpose |
| 7 | **Second console on the same mat** | Both consoles converge on identical state |
| 8 | **Broker pulled mid-bout** | Scoring continues; screens recover on reconnect |
| 9 | **Cameras paired, `load` → `start` → `finish`** | Phones roll and cut on the mat's clock |
| 10 | **Arabic console + RTL board** | New strings render in both languages |
| 11 | **Screen reload after a screen-audio change** | `reloadHallScreens()` reaches the wall |

---

## Proposed Future Commit Boundaries

**Proposals only. Nothing has been staged or committed.**

- **Commit A — Authorization safety net (optional, first).**
  `tests/Feature/Events/ScoringAuthorizationSafetyTest.php`. Passes against `HEAD` today, so
  it can land alone and gives the rewrite a net. **Risk: Low.**

- **Commit B — The Karate scoring release unit (the 22 Required files + both migrations +
  the two characterization tests).** Atomic. Splitting it further produces a commit that does
  not boot: the contract pair, `CameraFleet` + its models, `ClubEvent`'s cast, `routes/web.php`
  and the Karate package are mutually dependent as traced above. **Risk: High.**

  If a reviewer wants smaller pieces, the only defensible sub-split is:
  - **B1** `EventType.php` + `AbstractEventType.php` + `ScreenMediaController.php` (contract +
    its caller — backwards compatible, all five packages keep working);
  - **B2** `CameraFleet.php`, `CameraChannel.php`, `EventCamera.php`, `EventCameraClip.php`,
    `create_event_cameras_table` (dormant until something calls it);
  - **B3** everything else, which is the Karate package itself.

  B1 and B2 are each independently safe. **B3 is not safe without both.**

- **Commit C — Screen/pairing support** (`HallScreenRouter`, `ScreenPairingController`,
  `ScreenMedia`, `MatchEventLog`, `config/events.php`). Improves screens; not required for
  scoring. **Risk: Medium.**

- **Commit D — Karate documentation.** `docs/PHASE_0_…`, `docs/PHASE_1_…`. **Risk: Low.**

Everything else in the 300-path tree — Taekwondo, Open Mat, media/live, TV/mobile, generic UI —
stays outside this release.

---

## Proposed Future Deployment Sequence

**Do not execute any of this from this review. Each step is for a human.**

1. **Backup verification.** `php artisan takeone:backup`; confirm it verified by reading back,
   and that the artifact is timestamped and **off this box**. No backup → stop.
2. **Review.** Read the full diff of the 22 Required files, especially the +1272/−602 console.
   Decide the two open Phase 0 findings: the declared-`points` win reason coerced to `other`,
   and Taekwondo's unvalidated `punWinner` restore.
3. **Commit** along the boundaries above. Migrations in the same commit as their code.
4. **Isolated-clone tests.** Fresh clone of the commit, `composer install`,
   `npm ci && npm run build`, then the full suite. Expect the 38 Phase 0 + 21 Phase 1 tests to
   pass; the ~150 Vite failures should disappear once assets are built.
5. **Migration rehearsal on a disposable SQLite copy.** Copy the production DB
   (`VACUUM INTO`, never `cp`), run `migrate` against the copy, confirm `scoreboard_settings`
   and `event_cameras` exist and that existing `club_events` rows are untouched with
   `scoreboard_settings = NULL`. Then rehearse `migrate:rollback` on that same copy.
6. **Frontend asset build** in the isolated clone; confirm `public/build/manifest.json` exists.
7. **Screen/camera rehearsal** on staging hardware — the 11 checks above.
8. **Deployment, in this order — the order is the whole point:**
   `php artisan down` → deploy code → **`php artisan migrate --force`** → `config:cache`,
   `route:cache`, `view:cache` → `php artisan up`.
   ⚠️ **The migration must complete before the new code serves a request.** Code-first means a
   500 on the first `rules` or `duration` command.
   ⚠️ Never leave a config cache in place while tests run (`tests/TestCase.php` guard).
9. **Post-deployment verification.** Confirm the column exists; open one Karate console and
   confirm it renders (proves `karate-scoreboard.photo` resolves); issue a `meta` command and
   confirm 2xx; set a rule and confirm it persists across a console reload; confirm a paired
   wall screen redraws; confirm `MatchEventLog` rows still append.
10. **Rollback** — see below.

---

## Rollback Requirements

| Layer | Rollback | Caveat |
|---|---|---|
| Code | Revert the release commit and redeploy | Safe. Reverting code **without** rolling back the migration is fine — an extra nullable column is inert to old code |
| Migration | `migrate:rollback` → `dropColumn('scoreboard_settings')` | **Destroys every configured rule set.** On SQLite < 3.35 it is a table rebuild — take a verified backup first. Prefer leaving the column in place |
| Camera tables | `dropColumn` / `dropIfExists` | Discards camera pairings and clip rows. Prefer leaving them |
| Cache | Running mats hold state in the file cache with a 240-minute TTL | ⚠️ **A cache flush during rollback wipes the live score of every bout in progress**, and nothing rebuilds it from `event_match_events`. Never flush the cache mid-competition |
| Screens | Wall boards reload from the server | Re-pairing not required; a `resync` command recovers a stuck screen |

**Preferred rollback: revert the code, leave both migrations applied.** The columns are
nullable and additive, so old code ignores them, and no data is lost.

**Point of no return:** once officials have configured rule sets on live events, rolling the
migration back discards real competition configuration. After that, roll back code only.

---

## Review Limitations

1. **Static analysis only.** Nothing was executed — no migration, no test, no build, no
   database query. Every conclusion comes from reading code and Git metadata.
2. **The working tree is uncommitted and unverified.** The 22 files were read as they sit on
   disk; they have never been through CI, because CI builds from `HEAD` and `HEAD` does not
   contain them.
3. **Runtime behaviour is inferred, not observed.** The 500-on-missing-column conclusion is
   read from `persistSettings()` having no `rescue()`; it was not reproduced.
4. **Dependencies were traced through imports and static calls.** A dependency reached only
   via a string class name, a container binding, an event listener or a Blade `@include` could
   have been missed.
5. **Blade internals were not read line by line.** `control.blade.php` (+1272/−602) was
   checked for camera and route references but not fully reviewed; a JS-built URL inside it
   could add a route dependency this review did not catch.
6. **No `.env` or secret was opened**, so environment-driven behaviour (`REALTIME_ENABLED`,
   cache store, DB driver) is taken from committed config defaults and prior phases' notes.
7. **Taekwondo was examined only where it shares code with Karate.** Its own release unit was
   not analysed.
8. **Test-gap findings are from reading test names and the Phase 0/1 reports**, not from a
   coverage run.

---

## Final Safety Verification

| Check | Before | After |
|---|---|---|
| Branch | `development` | `development` |
| HEAD | `c12d1ada0696d589c39b73a001e4ea4667a973e7` | `c12d1ada0696d589c39b73a001e4ea4667a973e7` |
| `git status --short` count | **300** | **300** |
| Git operation in progress | none | none |
| `git diff --check` | clean | clean |

- ✅ **No file in `/var/www/takeone` was created, modified, renamed, moved, deleted, chmod'ed,
  chown'ed or staged.**
- ✅ No `git` command that changes state was run — no add, commit, push, stash, reset, restore,
  checkout, switch, clean, merge, rebase or cherry-pick.
- ✅ No `php artisan`, Composer, NPM, PHPUnit, migration, seeder, queue, Horizon, Docker,
  database, cache, build or deployment command was run.
- ✅ No `.env` or secret value was read, printed or copied; no patch content was inspected for
  any credential-suggesting path.
- ✅ The existing preservation bundle at
  `/tmp/takeone-uncommitted-review-20260829-025123/` was not altered.
- ✅ Exactly one file was created, outside the repository:
  `/tmp/takeone-karate-scoring-release-review-20260829-030458/KARATE_SCORING_RELEASE_DEPENDENCIES.md`
