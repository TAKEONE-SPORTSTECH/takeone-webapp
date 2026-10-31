# Explore — finding clubs near you

**Purpose.** `/explore` is where a signed-in member browses clubs, personal
trainers and events, nearest first. The code lives in the Clubs module (the
club owns its public face); this note sits with the member docs because it is
the member's front door. Consolidated 31 Oct 2026 from the Feb 2026 notes
`Documentation/EXPLORE_*.md` and `TODO_explore_clubs.md`, checked against the code.

## Main files

| What | Where |
|---|---|
| Controller | `app/Clubs/Controllers/PublicClubController.php` — `index`, `nearby`, `all`, `events`, private `calculateDistance()` |
| Routes | `app/Clubs/routes.php` — `clubs.explore` (`/explore`), `clubs.nearby` (`/clubs/nearby`), `clubs.all` (`/clubs/all`), `explore.events`; all behind `auth` + `two-factor` |
| Validation | `app/Clubs/Requests/NearbyClubsRequest.php` — latitude −90..90, longitude −180..180, radius 1..100 km |
| Classic views | `app/Clubs/resources/views/public/{explore,mobile/explore}.blade.php`, shared JS in `public/partials/explore-runtime.blade.php` |
| New UI (behind `NEW_UI`) | `App\Ui\Explore\ExplorePage` + `app/Ui/resources/views/explore/*` — see `docs/plans/major-change/new-ui.md` |

## Data flow (classic page)

1. Page loads and immediately asks `/clubs/all` (no location) so the list is
   never empty.
2. If the browser has geolocation, `watchPosition` (high accuracy, 10 s
   timeout, no cache) takes the **first** fix, then clears the watch — it does
   not keep tracking. With a fix, `/clubs/all?latitude=…&longitude=…` returns
   every club with `distance` (km, 2 dp), nearest first, clubs with no GPS last.
3. The location can also be picked on a Leaflet map in a modal; applying it
   re-fetches (`/clubs/nearby`, radius 50 km, for a non-"all" tab).
4. Distances use the Haversine formula (earth radius 6371 km) on
   `tenants.gps_lat` / `gps_long`. The user's location is never stored.
5. Category tabs are rendered only when they have data (clubs, personal
   trainers who opted in with `is_personal_trainer`, events). Placeholder
   categories were removed because the runtime fell back to listing clubs
   under the wrong label.

## Rules and limitations

- Geolocation needs HTTPS (localhost is exempt) and a WebView that grants it —
  see the Android shell notes in CLAUDE.md Part 6.
- Both JSON endpoints load **every** club and compute distance in PHP: no
  pagination, no throttle, no spatial index. Fine at today's size; see the
  backlog before the club count grows.
- `/clubs/nearby` still returns the owner's email and mobile; the new-UI
  Explore deliberately does not. See the backlog.
