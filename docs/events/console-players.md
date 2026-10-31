# The console Players tab

Find an entrant at the scoring table and correct them — name, club,
nationality, date of birth or age, gender, weight, belt — **change their
photo**, **frame it for the VS screen**, and add / move / remove their weight
classes. Organisers only (`EventAccess::canManage`); an official who may only
score never sees it and every door refuses them.

| Sport | Where | Code |
|---|---|---|
| Karate (every style) | Bouts card → Players | its own copy in `karate/scoreboard/partials/bouts-card` (see house-cleaning: to be moved onto the shared tab) |
| Taekwondo | Running order → Players | shared |
| BJJ | Bouts card → Players | shared |

## The shared tab

- **Server** — `App\Scoreboard\Support\ConsolePlayers` (list / show / save /
  VS framing / VS preview), behind `ConsolePlayersController`. Every write goes
  through `App\Events\Support\EntryEditor` (the People page's editor), which
  refreshes every sport's mats through `Fleet::entryChanged`. The VS framing
  is `PhotoVsFrame`, keyed by the photo the VS screen shows, so it follows the
  picture onto every sport's wall.
- **Doors** — for each sport, the paired table (`/court/{token}/players…`,
  `/bjj/screen/{token}/players…`, acting as the organiser who paired it,
  re-checked every request) and the signed-in console
  (`/{sport}/control/{event}/players…`). The sport is fixed by the route.
  `ConsolePlayers::console()` builds a console's addresses, or NULL when the
  viewer may not manage — then no tab is drawn.
- **Views** — `scoreboard::shared.players.pane` (put it where the console keeps
  its tab) and `scoreboard::shared.players.runtime` (once per console: styles,
  its own 3:4 cropper saving 600x800, the VS framing editor, the script). Its
  own palette and `cp…` ids, so it reads the same in every console; its two
  pop-ups lift themselves to `<body>` and fit 1920x1080 themselves. A console
  marks whatever opens the tab `data-players-open`.
- **VS preview** — `scoreboard::shared.players.vs-preview`: THE shared VS
  screen (`scoreboard::vs.arena`) in the event's corner look, driven over
  `postMessage` like Karate's.
- **Strings** — `scoreboard::players` (11 locales, lifted from `karate_messages`).

## Tests

`tests/Feature/Devices/ConsolePlayersTabTest.php` (both sports, both doors,
photo bytes, VS framing, scorer refused, other event unreachable, cross-sport
token refused) — part of the hall-devices ship gate
(`scripts/hall-devices-check.sh tests`).
