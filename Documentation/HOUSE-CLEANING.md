# House Cleaning — the register

Everything this project accumulates is either **in use** or **on this list**. When
something stops being needed it is written here; it is **not** deleted in the same
breath. The rule and its reasoning live in `CLAUDE.md` → *House Cleaning — Nothing Is
Deleted In Passing, Nothing Is Left Forever*.

**Adding an entry.** Say what it is, where it lives, why it is no longer needed,
**what you checked to prove that**, the date, and the gate on removing it.

**Removing one.** Only in a deliberate house-cleaning pass, on an explicit go-ahead,
behind a verified backup (RULE #2), one category at a time, files before rows, and
re-verified after. Then move the entry to **Done** with a date — never erase it.

**Not on this list:** anything still in use, and anything whose replacement has not
cut over yet. A parallel migration's old path is registered when the new one is live
and verified, not while both are running.

---

## Open

### Dependencies
*Nothing registered.* A composer/npm package, CDN script or library that stops being
needed goes here the day it stops — and one added for a trial goes here the day it is
added, so "just for now" cannot quietly become permanent.

### Code

| Item | Why | Gate |
|---|---|---|
| `app/Events/Sports/{Karate,Taekwondo}/Tournament/{Advancement,Arrangement,CallNotifier,Enrolment,Roster,RunningOrder}.php` | Superseded by the shared base in `app/Events/Support/Tournament/`, which BJJ already extends (verified 2026-09-07: shared base present, `BrazilianJiuJitsu\Tournament\RunningOrder extends SharedRunningOrder`, the other two still carry full copies). ~1,160 lines of byte-identical triplication when it was audited 2026-08-30. | Port each sport onto the base as its own deliberate step, verify on a real event, then delete that sport's copy. Both are in the field — needs an explicit go-ahead (RULE #1). |
| `boutCardMenu` + the `.yt-card-menu*` block in `resources/views/components/bout-vs-card.blade.php` | Duplicates `<x-bout-video-delete>` (added 2026-09-07): same confirm, same request, same in-place removal. | Consolidate on request. Left in place deliberately — the swap edits a working, choreographed card and risks a visual regression on `/me/…/gallery` for no user-facing gain. |

| `resources/views/personal/desktop/event-{show,manage,bracket,gallery,next-up,bout}.blade.php`, for the three martial-arts tournament packages | Unreachable for those types since 2026-09-08: `BrandEventPage` and `SealEventPage` both force `is_mobile = true`, so every request under `/me/events/{uuid}` and `/e/{uuid}/admin` renders the MOBILE blade at every width (asked for explicitly; the same decision the public poster already made). Verified by rendering each with a desktop user-agent — the mobile blade comes back. They are still LIVE for every type that has not opted in (sparring, open mat, generic), which is why they stay. | Only if the branded surface is ever extended to every event type. Until then they are load-bearing for the other types, not dead. Do not edit them expecting an effect on a karate/taekwondo/BJJ event. |
| `App\Events\Support\SealedEventRoutes::$registered` — a PROCESS static | The test harness builds a fresh router per test while the flag survives the whole process, so the sealed mirror is registered by whichever test dispatches first and is ABSENT from every test after it: a test that renders `/e/{uuid}/admin/…` passes alone and fails in a suite. Found 2026-09-08 while pinning the seal's rewrite; that test now asserts `PublicEventSkin::rewriteBody()` directly and says why. Harmless in production (one request per php-fpm process). | Needs a way to reset per application instance without reintroducing the double-registration the static exists to prevent — the `Routing` event fires on every match, including internal dispatches. Its own docblock explains why `booted()` was not an option. Worth doing only alongside real sealed-surface feature tests. |
| `admin.club.events.participants` + `app/Clubs/resources/views/events/participants{,-mobile}.blade.php` | A second event roster, inside the club admin panel, alongside the event's own `/me/events/{uuid}/people` — which shows the same people plus the verification and payment controls, and which every club admin may already open (`EventAccess::resolveCanManage()` grants the host club's owner and its club admins). Since 2026-09-08 both club-admin event lists open the event's own branded surface instead, so this screen is no longer the way in. Left LIVE deliberately: its own routes still work and something may still link to it. | Verify nothing links here any more (the two lists were the only known callers), then remove the two views, the two routes and the controller actions together. Additive change only so far — nothing was taken away. |
| The BJJ thumb-first tablet console — `app/Scoreboard/resources/views/bjj/scoreboard/mobile/control.blade.php`, `.../react/control-mobile.blade.php`, `.../partials/console-mobile-styles.blade.php`, and `resources/js/islands/scoreboard/ConsoleMobile.jsx` (plus the `ConsoleMobileIsland` branch in `resources/js/islands/scoreboard-console.jsx`) | Unreferenced since 2026-09-08: `ScoreboardController::consoleView()` no longer branches on the device and always renders the 1920-scaled console, because an official who has learnt the instrument at the table must not meet a differently-shaped one on the tablet (the user's call, after the tablet APK showed the phone layout). Verified by grep — the only remaining mentions of all four are comments and the JSX's own header. | Reversing the decision is ONE line in `consoleView()`, which is exactly why these stay. Delete only on a go-ahead that the thumb console is not wanted back; take the `scoreboard-console.jsx` branch and the JSX island in the same step. |
| The BJJ console's Hardware panel — `#hardware` in `app/Scoreboard/resources/views/bjj/scoreboard/desktop/control.blade.php`, plus `ctl_hardware`, `ctl_hardware_empty` and `ctl_hardware_hint` in `{en,ar}/bjj_messages.php` | Unreachable since 2026-09-08: its button in the centre column became the score log, which had styles in `console-styles.blade.php` but no host element on this console and so no way in at all. The panel only ever said "Nothing connected" — it was a placeholder for pedals and referee remotes that do not exist yet. Left in place rather than deleted in passing; it is `hidden`, so it costs nothing. Verified by grep: nothing opens it. | Delete when it is decided that wired hardware is not coming, or give it an opener again if it is. Karate carries the same three lang keys (`en/karate_messages.php`) for its own panel — check whether that one is still reachable before touching those. |
| The live-broadcast plumbing in `resources/views/components/court-screens.blade.php` — `liveUrl` / `liveBase` / `liveStoreUrl` (declared `null` in the `x-data`) and the `reloadLive()`, `reserveStream()`, `cutStream()` and `liveQr` code they feed | Live broadcasting was removed from this server; the three URLs have been hardcoded `null` ever since, and the panel keeps only its RECORDING controls. They were not inert: `fetch(null)` requests the literal string `"null"` relative to the current page, so every console open fired two 404s — `/e/{uuid}/admin/null` inside the sealed event app (reported 2026-09-09). Guarded on 2026-09-09 with an early return at each call site rather than deleted in passing. Verified by grep: nothing sets these three to a real URL. | Delete the whole live half of the component, in one deliberate step, once it is confirmed live streaming is not coming back to this box. The two duplicate `reloadLive()` definitions in that file suggest the component itself wants splitting first. |
| `resources/views/entry/public/partials/cover-actions.blade.php` and `cover-desktop.blade.php` | The cover's old language strip (a scroller of `<form>`-per-language chips) and the desktop cover that included it. Replaced 2026-09-09 by the carousel in `cover.blade.php` + `cover-carousel.blade.php`, built to `drafts/HTML Templates/Cover Page + Language Selector.html`. Verified unreferenced: `cover-desktop` is included by nothing (the public event page serves the mobile blade at every width), and `cover-actions` is now included only by `cover-desktop`. | Delete both together once the carousel has been through an event day. They are the only remaining no-JS path to choosing a language (plain forms), so check that nothing depends on that first. |
### Schema
**Name a dead table or column here; never drop one in passing.**

| Item | Why | Gate |
|---|---|---|
| `event_recordings.play_video_id`, `play_video_key`, `play_url`, `play_revision`, `pushed_at`, `timeline_pulled_at`, `sync_error` | Dormant since `video.takeone.bh` was disconnected (2026-08-27). Nothing writes them; a handful of rows still carry a URL that renders as an outbound link. | When the last non-null `play_url` row is gone. |
| `play_timeline_rounds`, `play_timeline_points` | Orphaned mirror tables from the same split. Their models are deleted and nothing reads them. | Verified backup + one migration. Nobody has needed it yet. |
| `content_translations`, `content_translation_runs` (+ their models `App\Translation\Models\{ContentTranslation,TranslationRun}`) | The first content-translation store — one row per field per language. Replaced on 2026-09-09 by ONE JSON document per record (`translation_documents`), which is what the feature was asked to be. **Checked:** every row was copied forward by the `2026_09_09_090000` migration and verified against the live event (6 languages, 17 fields each, all rendering); `grep -rn 'ContentTranslation\|TranslationRun'` over `app/`, `resources/` and `tests/` returns only the two model files themselves and this line. | A verified backup + one migration. Not urgent: the tables are small and harmless, and keeping them a while is the cheap insurance if the JSON shape needs revisiting. Registered 2026-09-09. |

### Storage

| Item | Why | Gate |
|---|---|---|
| `storage/build-backup-180625/` (1.4 MB, untracked) | A stale copy of a Vite build (`assets/` + `manifest.json`, 2026-09-05). Nothing resolves through it — the app serves `public/build`. | Confirm no reference, then remove. |
| The legacy flat upload folders — `avatars/`, `documents/`, `payment-screenshots/`, `order-proofs/{id}/`, `perks/{slug}/`, `timeline/{slug}/`, `club-products/{id}/`, `packages/`, `achievements/`, `goal-proofs/`, `business-logos/`, `user-posts/`, `images/`, `temp/` | Predate `App\Support\StoragePath`. **Still read by the code that wrote them** — this is a MIGRATION item, not a deletion one. | A separate deliberate change behind a verified backup. Do not "tidy" these. |

### Data

| Item | Why | Gate |
|---|---|---|
| The demo showcase dataset | Built to be removable before go-live; manifest-tracked at `storage/app/private/demo/manifest.json`. | `php artisan demo:purge` as a go-live step. Verify it took the uploaded files with it. |

### Artefacts

| Item | Why | Gate |
|---|---|---|
| Most of `Documentation/` — 52 files, the majority one-off per-fix summaries (`CLUB_MODAL_FIXES.md`, `…_FIXES_APPLIED.md`, `…_FINAL_FIXES.md`, `ADMIN_MEMBERS_FIX.md`, `AUTHENTICATION_FIX.md`, `EXPLORE_*_UPDATE.md`, …) | They describe changes that shipped long ago and make the handful of living subsystem docs hard to find. | Keep the subsystem docs (`MCP.md`, `EVENTS*.md`, `OPEN-MAT.md`, `MATSIDE-CAMERA.md`, this file); fold or delete the rest on a go-ahead. |

### Secrets & access
**An unrevoked key is not clutter, it is an open door.** Each entry names who must act.

| Item | Why | Gate |
|---|---|---|
| The Google / Firebase service-account key | FCM was deleted 2026-09-01 (it had zero tokens, ever). The key itself still needs **revoking by hand** in the Google console. | Owner: the user. Revoke, then move to Done. |
| Our SSH `authorized_keys` entry and API token **on the `video.takeone.bh` box** | That integration was removed here on 2026-08-27 and its Sanctum token revoked on this side. The credentials on THAT box are somebody else's machine now and still stand. | Owner: the user, with whoever holds that box. |

### Memories

| Item | Why | Gate |
|---|---|---|
| `MEMORY.md` — 32 KB across 106 memory files (2026-09-07) | Over the loader's 24.4 KB ceiling, so it is being truncated: entries below the cut silently stop existing. Index lines carry paragraph-length detail that belongs in the topic files. | A compaction pass: merge siblings, retire what is no longer true, push detail down. Report what merged and what was retired. |

---

## Done

*Nothing yet.* Shape: `| item | removed on | by whom | what was verified after |`.

Worked examples of things that were already retired properly, for reference: the
Capacitor shell in `mobile/` (converted to Flutter, 2026-08-29), the `app/Play/*`
integration and `config/play.php` (2026-08-27), the `CourtDisplay/` hall-screen
duplication (folded into one `HallScreen` per sport during the Scoreboard split,
2026-09-01), the eventlab sandbox, and the boutcam lab endpoints with their
`LAB_LIVE_KEY` — all verified gone from the tree on 2026-09-07.
