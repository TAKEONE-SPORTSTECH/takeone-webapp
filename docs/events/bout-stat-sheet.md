# Bout stat sheet — on the bout page, under the video

> Status: **BUILT, ON on stage** (31 Oct 2026) behind `BOUT_ANALYTICS_TAB` (`config/media.php` → `analytics_tab`), default OFF.
> Component: `<x-bout-analytics>` (`resources/views/components/bout-analytics.blade.php`).
> Design source: private artifact "Bout 18 stat sheet" —
> https://claude.ai/artifact/7GaAqVwvjyZB97CSiwBXRh (drawn from match 6849, event 104).
> Engine: `app/Media/BoutStats.php` · tests: `tests/Feature/BoutStatsTest.php`.

## What it is

Every bout that was scored on a TAKEONE mat gets a stat sheet under its video:
the result and how it was won, key moments, a chart of how the score moved,
red against blue, the bout in thirds, every action (tap to jump the video
there), tempo and officiating. **Nobody types a number in** — everything is
read from that bout's scoring log (`event_match_events`) and its bout row.

## What already exists (do not rebuild)

| Piece | Where | Today |
|---|---|---|
| The numbers | `App\Media\BoutStats::for($match)` | LIVE — score, winner, senshu, first score, lead changes, comeback, decided-at, late share, thirds, stoppages, award time, per-corner points by value / penalties / per minute / time leading / biggest lead / longest run, steps, every event |
| Stat-sheet PDF | `me.events.bout.stats.pdf`, view `media::stats.bout-pdf` | LIVE, offered on both bout pages |
| Closing card of a sent video | `CameraController` → `BoutStats` | LIVE |
| MCP | `bout_stats` tool (`BoutStatsTool`) | LIVE |

So the job is **showing it on the page**, not computing it.

## Check against the real log (match 6849, 31 Oct 2026)

Every figure on the design matches `BoutStats` for that bout: 10–9, six lead
changes, 7–9 with 3.9 s left, 5 points in the last 14 s, 58 % of points in the
last 30 s, thirds 1–3 / 4–0 / 5–6, penalty (C1) at 0:44, 4:10 on the mat, 36 %
fighting, 11 s average from yame to the point. Corrections for the build:

1. **Match number.** The design says *Match 18*; the bout row is `match_no = 17`
   and the page URL uses 17. The build reads `match_no` — never a typed number.
2. **Stoppages.** The design says 11; the engine already says **10** (the final
   pause is the bell, not a yame). The engine is right.
3. **"On the buzzer".** The clock reached 0.00, *then* the 3 was entered 7 s
   later. The log cannot say whether the technique landed before the bell. Word
   it **"awarded at the bell"**, never "on the buzzer", when a point is logged at
   0.00 after time ran out.
4. **How it was won.** This bout's `win_reason` is `other` with no note. Show
   the reason only when one is recorded (senshu, hantei, kiken, hansoku…);
   otherwise just the score.

## Plan

Small reversible steps; the page section sits behind a flag, OFF by default
(RULE #1). No migration, no new table, no new endpoint.

**Step 1 — engine additions (additive only).** In `BoutStats`, add fields; never
change existing ones (the PDF, the closing card and MCP read them):
- `at_bell` on an event — logged at clock 0 after time ran out.
- `win_reason` / `win_note` from the bout row, null when `other` and blank.
- `verdict` — a one-line summary built from existing fields, in en/ar lang
  strings (`events::events.bout_verdict_*`), e.g. "Came back from 7–9 with 3.9 s
  left; the lead changed hands six times."
- Extend `BoutStatsTest` with match-6849-shaped fixture rows: the four
  corrections above, plus a level bout settled by senshu and an encho bout.

**Where it lives (decided 31 Oct 2026).** Not a new section under the video.
The bout page already has a Highlights view — the player on top, then tabs
Match · Points · Review · Comments, where tapping a point replays it at normal
speed and then in slow motion (`playPointReplay`). The sheet is a new
**Analytics** tab in that tab strip, after Points, in all three renderers:
the portrait view (`.mrv-tab` / `.mrv-pane`), the fullscreen drawer
(`.fsh-tab`) and the desktop pane (`.tab-button`). It reuses their existing
classes — sticky `.mrv-rhead` section heads, `.mrv-note-card` cards, the
`.hlp-*` point row — and adds no new styling to anything that exists. Key
moments and the chart's dots call the same `playPointReplay`, so a tap behaves
exactly like a tap in Points. The full point list stays in Points (not
duplicated); the PDF row stays in Match. Preview (private):
https://claude.ai/artifact/LxTEf8gF69PgDx24TZiGdM

**Score bar fonts (done 31 Oct 2026, on stage).** All three web score bars —
the mobile watch page (`.msb`), the bar burned into a sent video
(`media::share.scoreboard`) and the gallery card (`<x-bout-vs-card>`, `.sbm`) —
now use the boutcam's faces from `ScoreBar.kt`: scores 48 px Barlow Condensed
Black with a 0 3 10 shadow (were Zen Old Mincho serif), clock and names Black,
the discipline line Barlow Bold, the feed's points Black 15. Arabic falls to
Tajawal at 76 % (the camera's 25 → 19). Fonts are the self-hosted files in
`public/fonts/designer/`. The standalone drafts carry the same change.

**Step 2 — one shared component.** `<x-bout-stat-sheet :stats="…" :color="…">`
in `resources/views/components/`, drawing the design's sections from the
`BoutStats` array. Server-rendered (the chart is inline SVG built in PHP —
no chart library), escaped output only, names exactly as on the bout row.
Each "every action" row carries its bout-clock second and dispatches a seek
event; the page's player listens. Added to the component table in CLAUDE.md.

**Step 3 — both bout pages.** `personal/mobile/bout-video` and
`personal/desktop/bout-video` are the standalone designs (Design Rule #6's one
exception), so the sheet takes **their** dark palette and type, not the app
shell's. `BoutVideoController::show()` already calls `BoutStats::for()` to decide
the PDF link — compute it **once** and pass it to the view. Same access as the
page: whoever may watch the bout may read its sheet. Mobile and desktop land
together. Flag: `BOUT_STAT_SHEET` in `config/media.php`, default OFF.

**Step 4 — MCP + docs.** Surface the new fields in `bout_stats`, add a
`McpServerTest` case, update `docs/platform/mcp.md`.

**Step 5 — verify, then switch on.** Render match 6849 on mobile, desktop and
inside the Android app, compare against the design, test a bout with no log
(the section must not render), a BJJ bout and an encho bout. Then switch the
flag on for stage.

## Later — gaps the design marks, each its own decision

- **Technique and target per point** (tsuki/geri, jodan/chudan) — needs a tap
  on the scoring tablet.
- **Which submission** (BJJ) — same.
- **Referee and judges per bout** — not recorded per bout today.
- **Win chance at every moment** — needs many more bouts to be honest.

## As built (31 Oct 2026)

- **Engine** (`BoutStats`, additive): `at_bell` on each point, `win_reason` /
  `win_note` (null for a bare `other`), `moments` (first · run · behind ·
  decider — one point, one name, decider wins), `corners` (the sport's words),
  and `BoutStats::verdict()` (en/ar, `events::events.bout_an_*`).
- **Page**: `BoutVideoController::show()` computes the sheet once and passes
  `analytics` (null while the switch is off or nothing was logged → no tab).
  Mobile view: the portrait tab strip (`.mrv-tabs.has-analytics` lets five tabs
  size to their words), the fullscreen drawer and the wide pane. Desktop: a
  server-drawn `<template id="boutAnalyticsTpl">` cloned by `renderPanel()`.
- **Replay**: a key moment or chart dot finds its video time in the timeline by
  the score after the point; mobile reuses `data-mrv-seek` → `playPointReplay`,
  desktop `data-seek` → `seek()`. A bout with no footage has nothing to tap.
- **Note**: events with a branded surface (`BrandEventPage`) render the phone
  layout at every width, so the desktop variant only shows on unbranded events.
- **MCP**: `bout_stats` now also returns `moments`, `corners`, `win_reason`,
  `win_note`, `at_bell` and `verdict`.
- **Tests**: `BoutStatsTest` (moments + verdict, at the bell, tab only while
  switched on on both pages, outsider sees nothing), `McpServerTest`.
