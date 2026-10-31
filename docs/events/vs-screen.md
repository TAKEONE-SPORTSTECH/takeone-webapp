# The VS screen — one design, every screen

> Unified 31 Oct 2026. Before that there were ten copies that had drifted apart.

The screen that introduces a bout — two corners' photographs, the gold divider,
event · round · division across the top, each fighter's badge, flag and
country, name, crest and club, their chips (record · belt · age·height·weight),
the big VS, and match · court · referee along the bottom.

## Where it lives

| Piece | File | What it is |
|---|---|---|
| Markup | `app/Scoreboard/resources/views/vs/arena.blade.php` | the 1920×1080 stage; needs `$rr` (red stands right) and `$L` (corner look, keys `aka`/`ao`) |
| Paint | `…/vs/paint.blade.php` | `chip()`, `statLine()`, `paintVs(s)`; `$vsFlag='nationality'` flies the athlete's own flag (Karate Combat style) |
| Helpers | `…/vs/helpers.blade.php` | safe URLs, text/background setters, names that shrink-then-condense (never wrap or truncate), flag URLs |
| Animations | `…/vs/keyframes.blade.php` | panel slide-ins, VS slam, shine, flash, exit wipe |
| Facts | `App\Scoreboard\Support\VsCorner` | `corner()`, `forBout()`, `record()`, `officiatingName()`, `announced()` (country spelled out), `look()` (the event's corner names/colours and side) |
| Layout | `App\Scoreboard\Support\VsLayout` | platform setting: info height, info size, show the stat chip |
| Framing | `App\Scoreboard\Models\PhotoVsFrame` | per-photo zoom and focal point, set in the console's Players tab |

## Who uses it

- **Karate mats, every style** (`karate/scoreboard/mat` + `styles/*/mat`) —
  include arena, paint, helpers, keyframes. Verified identical (ignoring
  indentation) to the pre-extraction render for all 7 styles × both sides.
- **Taekwondo mat** — arena + paint, in its own corner look. Corners built by
  `VsCorner::corner()`, keeping Taekwondo's own photo/crest resolver
  (`CompetitorPhoto`).
- **BJJ mat** — arena + paint; BJJ's `blue`/`white` corners are passed as
  `aka`/`ao`. Corners keep BJJ's club/flag rule (a table override wins) and
  gain `record`, `vs_frame`, `nationality` from `VsCorner`.
- **Bout page intro** (`personal/mobile/bout-video`) — arena, paint, helpers,
  keyframes inside its own wrapper (scale into the player, hold the video at
  0:00, Skip countdown). Data: `BoutVideoController` → `vsIntro`
  (`VsCorner::forBout() + look()`). The desktop bout page has no intro.
- **Boutcam** (`flutter/TV/lab-app/…/VsScreen.kt`, native) — draws the same
  layout from the camera plan. The plan's `vs` now carries `layout`,
  `red_right`, `division`, `referee` and `corners.{red,blue}` (record, belt,
  age, height, weight, vs_frame, country, badge, colours) via
  `CameraController::vsShared()`. The phone keeps its own info-height setting.

## Not unified (registered in house-cleaning)

- The BJJ **React** board (`resources/js/islands/scoreboard/Board.jsx`, behind
  `REACT_SCOREBOARD`, off) draws its own VS in JSX.
- `taekwondo/scoreboard/partials/vs-half.blade.php` is no longer rendered.
- Taekwondo and BJJ still carry their own copies of the script helpers
  (setName/flagUrl…) next to the shared paint.
