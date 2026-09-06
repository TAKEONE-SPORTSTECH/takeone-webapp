<?php

namespace App\Scoreboard;

use App\Support\Modules\AbstractModule;

/**
 * The mat: a scoring table, a wall board, and the screens they drive.
 *
 * ── Why this is not part of App\Events ──────────────────────────────────────
 *
 * Two reasons, and they are the user's own:
 *
 *  1. WORK ON ONE MUST NOT DISTURB THE OTHER. Changing how a draw advances, how
 *     entries are billed or how a division is built is event work; changing what
 *     a point is worth, how the clock behaves or what a wall screen paints is mat
 *     work. They were in one folder, so every edit to either sat in reading
 *     distance of the other. Now they do not.
 *
 *  2. THE MAT MAY BE A PRODUCT ON ITS OWN. A scoring table rented to a hall that
 *     runs no TAKEONE event is a plausible business, and it is only plausible if
 *     the mat does not require a `club_events` row to exist. That is not true
 *     yet — see "What still ties this to an event" below — but it cannot ever
 *     become true while the code lives inside the event package.
 *
 * ── What the mat owns ───────────────────────────────────────────────────────
 *
 * Its own tables (`bjj_mat_states`, `bjj_match_events`, `bjj_screen_devices`),
 * its own routes (the token door a device opens and the signed-in door an
 * organiser opens), its own board and console views, and its own vocabulary.
 * The rule book stays per sport, because a point in jiu-jitsu is not a point in
 * karate: `Sports/<Sport>/Mat/Scoring.php` is the only place that decides what a
 * command means.
 *
 * ── What still ties this to an event, and what it would take to cut it ──────
 *
 * The mat is handed a `ClubEvent` and reads four things off the event side:
 *
 *   · the OWNER            — id, uuid, title, colour, mats, scoreboard settings
 *   · WHO MAY OPERATE IT   — App\Events\Support\EventAccess::canScore / canManage
 *   · WHAT TO PUT ON IT    — EventMatch + ClubEventRegistration, queued by
 *                            RunningOrder
 *   · WHERE THE RESULT GOES — already abstract: Scoring::commit() hands the
 *                            outcome back through
 *                            EventTypeRegistry::for($event)->recordOutcome(),
 *                            so the mat does not know what a draw is.
 *
 * The fourth is the shape the other three want: a host interface the event side
 * implements, and a second implementation for a rented mat with typed names and
 * no draw behind it. Note that App\Events\OpenMat is ALREADY that second
 * implementation in everything but name — it runs this scoreboard with no entry
 * list, no draw and no division — which is the strongest evidence the seam is
 * real and worth finishing.
 *
 * ── Where the sports are ────────────────────────────────────────────────────
 *
 * All three: `Sports/{BrazilianJiuJitsu,Karate,Taekwondo}/`, each with the same
 * three layers.
 *
 *   Controllers/  PRIVATE. The only way in is Fleet, and ModuleBoundaryTest
 *                 fails the build if anything outside reaches past it.
 *   HallScreen/   the wall board: the device, its pairing, its channel, its
 *                 payload — and the webfonts it draws itself in.
 *   Mat/          the scoring table: MatState, and Scoring, which is the one
 *                 place that decides what a command means for that sport.
 *
 * They keep three separate implementations on purpose, and that is NOT the same
 * question as this module's existence. Each resolves a device from a bare token
 * against its own table, so one route set over one table could hand a Karate
 * screen a Taekwondo board. What they now share is the DOOR. Collapsing the
 * duplicated parts behind it is the open debt CLAUDE.md records under "Shared
 * Stays Shared" — a job this move makes possible rather than one it does.
 *
 * BJJ moved first, on its own, because it is not in the field and the other two
 * are; Karate and Taekwondo followed once the shape was proven.
 */
class Scoreboard extends AbstractModule
{
    public function key(): string
    {
        return 'scoreboard';
    }
}
