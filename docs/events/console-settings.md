# The scoring console's Settings pop-up

ONE file for every combat sport: `app/Scoreboard/resources/views/shared/settings.blade.php`
(`@include('scoreboard::shared.settings', [...])`). It replaced eight inline copies
(seven Karate consoles + BJJ, ~1,100 lines) on 2026-11-01, and gave Taekwondo a
Settings pop-up for the first time (its separate Corners and Cameras pop-ups moved in).

## Tabs — each shown only where the console's engine can honour it

| Tab | Option | Who has it today |
|---|---|---|
| Rules | `rulesView` — the sport's own partial (`karate/…/settings-rules`, `bjj/…/settings-rules`) | Karate (all styles; Kyokushin without Senshu), BJJ |
| Corners | always; `perScreen` adds per-screen sides (control · board · upcoming) | all, with per-screen sides everywhere (each sport's screens read `MatSides::redRight(…, $screen)`) |
| Points + Fouls | `customScoring` = `'points'` (Combat's words) / `'buttons'` (Kyokushin's) | Combat, Kyokushin |
| Timer | `timer` = `['dur'=>[m,s], 'warn'=>[m,s], 'warnId'=>'ato'\|'warn', 'warnInk'=>css]` | Karate, BJJ |
| Sounds | `sounds` true (+ `soundLabels` overrides) | Karate, BJJ |
| Cameras | `cameraUrl` | all |
| Photo guide / VS layout | `photoGuideUrl` / `vsLayoutUrl` (super-admin) | Karate |

## Rules of the file

- **It is THE Settings pop-up — no sport's name is on it.** Its words are
  `scoreboard::settings` (11 languages; title "Settings"), its panels live in
  `shared/settings/` (photo-guide, vs-layout). The only per-sport words are the
  Rules tab (the sport's own partial) and an optional rule-book line under the
  warning timer (`timer['hint']` — Karate passes its WKF *atoshi baraku* note).

- **Every id, class and data-attribute a console's script reads is unchanged**
  (`.stab`/`data-tab`, `.spanel`/`data-panel`, `data-rule`, `#durMin`,
  `#atoMin`/`#warnMin`, `.audioPick`, `#btnRows`, `#btnApply`…), so Karate and BJJ
  drive it with the scripts they already had. Verified by rendering every console
  before and after: identical apart from the ✕'s new `aria-label` and colour
  fallbacks.
- `ownScript` gives a console with no Settings script (Taekwondo) open/close/tab
  switching; `#btnSettings` opens it.
- `chrome` = `'console'` (Karate/BJJ `.modal`) or `'sheet'` (Taekwondo `.sheet`).
- Its own locals are named so a nested panel cannot pick them up by accident (a
  helper once called `$w` overrode the VS preview's own `$w` width).
- **A sport gains a tab by teaching its ENGINE the setting, then switching the option
  on** — never by adding a branch to this file.

## Not yet everywhere (needs engine/endpoint work, not markup)

- Taekwondo: Rules, Timer (its rounds × minutes stay beside Start next), Sounds.
- BJJ and Taekwondo: Points/Fouls (engines don't read `Styles\Custom*`), Photo guide
  and VS layout (endpoints and previews are Karate's).
