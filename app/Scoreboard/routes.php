<?php

use App\Scoreboard\Sports\BrazilianJiuJitsu\Controllers\HallScreenController;
use App\Scoreboard\Sports\BrazilianJiuJitsu\Controllers\ScoreboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Brazilian Jiu-Jitsu Tournament — the package's own routes
|--------------------------------------------------------------------------
| Loaded automatically by App\Events\EventPackageServiceProvider, which looks
| for a routes.php beside a registered package. That is what keeps the package
| rule intact: adding this sport is adding one directory and one line in
| config/event_types.php, with no shared route file to edit — and DELETING it is
| deleting the same directory, rather than hunting through routes/web.php for
| the block that belonged to it.
|
| The sibling packages predate that and still declare their routes in
| routes/web.php; nothing here changes them.
|
| ── Two kinds of door, and why they are authorised differently ──────────────
|
|  · The SIGNED-IN door (/bjj/control/…) is the organiser's. Bound by the
|    event's UUID, never its id: this URL is read off a laptop screen at a mat,
|    and a guessable one invites somebody to try the next number along. Every
|    call re-checks EventAccess::canScore — the page is a convenience, the
|    command endpoint is the attack surface.
|
|  · The TOKEN door (/bjj/screen/…) belongs to a device. A wall screen and a
|    scoring tablet have nobody signed in to them, and a session left on an
|    unattended machine in a public hall would be a far worse thing to own than
|    the board itself. The token names ONE event and ONE mat, so there is
|    nothing in these URLs to tamper with, and a scoring table additionally
|    requires that the organiser who paired it may STILL score — re-checked on
|    every request, so removing them from the jury stops every console they
|    paired without anybody visiting the hall.
*/

/* ---------------- The screen's own doors: authorised by its token ---------- */

/*
 * The short address a screen is opened at, and the door it enrols through.
 *
 * Both are karate's, verbatim in shape: every hall screen must pair the same
 * way whatever sport ends up driving it, because the person setting one up in a
 * hall does not know which package that will be. `/new` hands over to the
 * sport-neutral waiting room for exactly that reason — the fleet is decided by
 * the EVENT at claim time, not by the address the screen was opened with.
 *
 * Ahead of the token route so the literal segments win over `{token}`.
 */
Route::get('/bjj/screen/new', \Illuminate\Routing\RedirectController::class)
    ->defaults('destination', '/screen')->defaults('status', 302)
    ->name('bjj-screen.new')->middleware('throttle:60,1');

Route::post('/bjj/screen/enroll', [HallScreenController::class, 'enroll'])
    ->name('bjj-screen.enroll')->middleware('throttle:court-enroll');

/*
 * The board's typefaces, self-hosted beside the package that draws with them.
 *
 * Public and tokenless on purpose: a face is not data, and the screen asking
 * for it has no session. Ahead of `{token}` so the literal segment wins.
 */
Route::get('/bjj/screen/font/{file}', [HallScreenController::class, 'font'])
    ->name('bjj-screen.font')->where('file', '[a-z0-9.-]+')->middleware('throttle:60,1');

Route::get('/bjj/screen/{token}', [HallScreenController::class, 'screen'])
    ->name('bjj-screen.board')->where('token', '[A-Za-z0-9]{40}')
    ->middleware('throttle:screen-token');

// The heartbeat, and the unpair signal, in one field. Asked often by design —
// see the note on ScreenDevice::present() for why the window is generous.
Route::get('/bjj/screen/{token}/status', [HallScreenController::class, 'status'])
    ->name('bjj-screen.status')->where('token', '[A-Za-z0-9]{40}')
    ->middleware('throttle:screen-token');

Route::get('/bjj/screen/{token}/payload', [HallScreenController::class, 'payload'])
    ->name('bjj-screen.payload')->where('token', '[A-Za-z0-9]{40}')
    ->middleware('throttle:screen-token');

// The livestream lower third: the same mat, 1920×160 on alpha.
Route::get('/bjj/screen/{token}/link', [HallScreenController::class, 'link'])
    ->name('bjj-screen.link')->where('token', '[A-Za-z0-9]{40}')
    ->middleware('throttle:screen-token');

Route::get('/bjj/screen/{token}/overlay', [HallScreenController::class, 'overlay'])
    ->name('bjj-screen.overlay')->where('token', '[A-Za-z0-9]{40}')
    ->middleware('throttle:screen-token');

// The event's own sounds, fetched by the board through its own token. Private
// storage and a per-slot address, like every other file a board fetches: a
// club's introduction music is licensed to them, not published to the internet.
Route::get('/bjj/screen/{token}/audio/{slot}', [HallScreenController::class, 'audio'])
    ->name('bjj-screen.audio')
    ->where('token', '[A-Za-z0-9]{40}')->where('slot', '[a-z_0-9]{1,24}')
    ->middleware('throttle:screen-token');

// The mat's live state, for a screen that just loaded or reconnected.
Route::get('/bjj/screen/{token}/state', [ScoreboardController::class, 'state'])
    ->name('bjj-scoreboard.state')->where('token', '[A-Za-z0-9]{40}')
    ->middleware('throttle:screen-token');

/* ---------------- The scoring table as a PAIRED screen ---------------- */

Route::get('/bjj/screen/{token}/control', [ScoreboardController::class, 'tokenControl'])
    ->name('bjj-scoreboard.token-control')->where('token', '[A-Za-z0-9]{40}')
    ->middleware('throttle:screen-token');

// Uploading a sound FROM the scoring table. The organiser's own door
// (me.events.screen-audio.*) is the same feature through a session; this is the
// paired tablet, whose whole identity is its token.
Route::post('/bjj/screen/{token}/audio/{slot}', [ScoreboardController::class, 'tokenAudio'])
    ->name('bjj-scoreboard.token-audio')
    ->where('token', '[A-Za-z0-9]{40}')->where('slot', '[a-z_0-9]{1,24}')
    ->middleware('throttle:uploads');

Route::delete('/bjj/screen/{token}/audio/{slot}', [ScoreboardController::class, 'tokenAudioDestroy'])
    ->name('bjj-scoreboard.token-audio-destroy')
    ->where('token', '[A-Za-z0-9]{40}')->where('slot', '[a-z_0-9]{1,24}')
    ->middleware('throttle:screen-token');

Route::post('/bjj/screen/{token}/command', [ScoreboardController::class, 'tokenCommand'])
    ->name('bjj-scoreboard.token-command')->where('token', '[A-Za-z0-9]{40}')
    ->middleware('throttle:300,1');

// The cameras on this console's own mat, and orders to one of them. A READ and
// a WRITE, authorised exactly as the command endpoint beside them is — the
// device token names one event and one mat, and the controller resolves the
// mat from the DEVICE rather than the request.
Route::get('/bjj/screen/{token}/cameras', [ScoreboardController::class, 'tokenCameras'])
    ->name('bjj-scoreboard.token-cameras')->where('token', '[A-Za-z0-9]{40}')
    ->middleware('throttle:120,1');
Route::post('/bjj/screen/{token}/cameras/{camera}', [ScoreboardController::class, 'tokenCameraCommand'])
    ->name('bjj-scoreboard.token-cameras.command')->where('token', '[A-Za-z0-9]{40}')->whereNumber('camera')
    ->middleware('throttle:120,1');

// The whole console, re-read after the socket says this mat moved. A READ, and
// authorised exactly as the command endpoint beside it is.
Route::get('/bjj/screen/{token}/console-state', [ScoreboardController::class, 'tokenConsoleState'])
    ->name('bjj-scoreboard.token-console-state')->where('token', '[A-Za-z0-9]{40}')
    ->middleware('throttle:screen-token');

// The event's WHOLE draw — every bout, every division and the entrant roster —
// so the console's Weight class, Member and Arcade tabs can answer a question
// the twelve-deep mat queue cannot. A READ, nothing else: it writes nothing and
// loading one of its bouts is the ordinary `load` command above.
Route::get('/bjj/screen/{token}/catalogue', [ScoreboardController::class, 'tokenCatalogue'])
    ->name('bjj-scoreboard.token-catalogue')->where('token', '[A-Za-z0-9]{40}')
    ->middleware('throttle:screen-token');

Route::post('/bjj/screen/{token}/photo/{side}', [ScoreboardController::class, 'tokenPhoto'])
    ->name('bjj-scoreboard.token-photo')
    ->where('token', '[A-Za-z0-9]{40}')->where('side', 'blue|white')
    ->middleware('throttle:uploads');

// The Corners tab from a paired table — the device's mat, never the request's.
Route::post('/bjj/screen/{token}/corners', [ScoreboardController::class, 'tokenCorners'])
    ->name('bjj-scoreboard.token-corners')->where('token', '[A-Za-z0-9]{40}')
    ->middleware('throttle:screen-token');
Route::post('/bjj/screen/{token}/corner-look', [ScoreboardController::class, 'tokenCornerLook'])
    ->name('bjj-scoreboard.token-corner-look')->where('token', '[A-Za-z0-9]{40}')
    ->middleware('throttle:screen-token');

/* ---------------- The organiser's own door ---------------- */

Route::middleware(['auth', 'verified', 'two-factor'])->group(function () {
    Route::get('/bjj/control/{event:uuid}', [ScoreboardController::class, 'control'])
        ->name('bjj-scoreboard.control');

    // Throttled well above a human at a keyboard, but not unbounded: this is a
    // write path an appointed official holds for the length of a competition,
    // and every call of it appends a ledger row.
    Route::post('/bjj/control/{event:uuid}', [ScoreboardController::class, 'command'])
        ->name('bjj-scoreboard.command')->middleware('throttle:mat-command');

    // The console's own re-read. Cheap and bounded — one state row, twelve
    // queued matches and the log of the bout on the mat — but throttled anyway,
    // because it is reachable by anyone entitled to score and a socket nudge is
    // what normally calls it.
    Route::get('/bjj/control/{event:uuid}/state', [ScoreboardController::class, 'consoleState'])
        ->name('bjj-scoreboard.console-state')->middleware('throttle:mat-read');

    // The same whole-draw catalogue as the token door beside it, through the
    // organiser's own session. One console, two front doors.
    Route::get('/bjj/control/{event:uuid}/catalogue', [ScoreboardController::class, 'catalogueRead'])
        ->name('bjj-scoreboard.catalogue')->middleware('throttle:mat-read');

    Route::post('/bjj/control/{event:uuid}/photo/{side}', [ScoreboardController::class, 'photo'])
        ->name('bjj-scoreboard.photo')->where('side', 'blue|white')
        ->middleware('throttle:uploads');

    // The Corners tab (App\Scoreboard\Corners): which side of THIS mat the
    // red position stands on, and each corner's name and colour for the event.
    Route::post('/bjj/control/{event:uuid}/corners', [ScoreboardController::class, 'corners'])
        ->name('bjj-scoreboard.corners')->middleware('throttle:admin-write');
    Route::post('/bjj/control/{event:uuid}/corner-look', [ScoreboardController::class, 'cornerLook'])
        ->name('bjj-scoreboard.corner-look')->middleware('throttle:admin-write');
});

/* ---------------- Scan-to-claim: an organiser placing a screen ------------ */

Route::middleware(['auth', 'verified', 'two-factor'])->group(function () {
    Route::get('/bjj/screen/claim/{code}', [HallScreenController::class, 'claim'])
        ->name('bjj-screen.claim')->where('code', '[A-Z0-9]{6}')->middleware('throttle:30,1');
    Route::post('/bjj/screen/claim/{code}', [HallScreenController::class, 'storeClaim'])
        ->name('bjj-screen.claim.store')->where('code', '[A-Z0-9]{6}')->middleware('throttle:member-write');
    Route::get('/bjj/screen/paired/{device}', [HallScreenController::class, 'claimed'])
        ->name('bjj-screen.claimed')->whereNumber('device');
});


/*
|--------------------------------------------------------------------------
| Karate and Taekwondo — moved here from routes/web.php, 2026-09-01
|--------------------------------------------------------------------------
| Verbatim, in the order they were declared in, with every comment they
| carried. Not one URL, route name, constraint, throttle or middleware group
| changed: the module registers this file inside `web`, which is exactly the
| group routes/web.php declared them in, so a screen already flashed with one
| of these addresses cannot tell the difference.
|
| They are here rather than there because the code they point at is here. A
| route file naming a module's controller from outside it is the thing that
| stops the two verticals being worked on separately, and ModuleBoundaryTest
| now fails the build for it.
*/
// The hall board a screen opens. Unauthenticated BY DESIGN: a wall screen
// has no keyboard and nobody to sign in, so the device's own token is its
// identity. The URL carries no event and no court — both are read off the paired
// device — so there is no identifier here to tamper with or enumerate.
Route::get('/court/{token}', [\App\Scoreboard\Sports\Taekwondo\Controllers\CourtDisplayController::class, 'board'])
    ->name('court-display.board')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');

// A screen's first boot: it has nothing on its SD card and asks for an identity.
// What it receives is an UNCLAIMED device that can show a pairing code and
// nothing else, so the endpoint grants no access to any data — but it does write
// a row, hence the hard throttle.
Route::post('/court/enroll', [\App\Scoreboard\Sports\Taekwondo\Controllers\CourtDisplayController::class, 'enroll'])
    ->name('court-display.enroll')->middleware('throttle:court-enroll');

// "Have I been claimed yet?" — one boolean, polled by an unpaired screen.
Route::get('/court/{token}/status', [\App\Scoreboard\Sports\Taekwondo\Controllers\CourtDisplayController::class, 'status'])
    ->name('court-display.status')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');

// The OLD per-fleet enrolment door, kept only so a television already
// bookmarked at it still lands somewhere useful. `/screen` is the one address a
// hall display is pointed at now, for every sport and every job: it enrols into
// the sport-neutral waiting room and the CLAIM decides which fleet, which mat
// and which surface (see HallScreenRouter). Two doors per sport was a fact
// about our storage that an operator in a hall had to memorise.
// Still ahead of `/court/{token}` so the literal segment wins over the token.
// GET only, and via RedirectController rather than a closure, so `route:cache`
// keeps working.
Route::get('/court/new', \Illuminate\Routing\RedirectController::class)
    ->defaults('destination', '/screen')->defaults('status', 302)
    ->name('court-display.new')->middleware('throttle:60,1');

// The board as JSON, so a paired screen can redraw in place instead of
// reloading — a wall going black on every result is worse than a stale queue.
Route::get('/court/{token}/payload', [\App\Scoreboard\Sports\Taekwondo\Controllers\CourtDisplayController::class, 'payload'])
    ->name('court-display.payload')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');

// The device agent's realtime credentials — subscribe-only, one topic. Held by
// the agent rather than the page, because the board's own animation load can
// starve an inbound socket message in the renderer for minutes.
Route::get('/court/{token}/link', [\App\Scoreboard\Sports\Taekwondo\Controllers\CourtDisplayController::class, 'link'])
    ->name('court-display.link')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');

// Claiming a screen — the page its QR points at. Authenticated, because the
// pairing code is printed on a wall in a public hall and is worth nothing on its
// own: every event offered is one the signed-in organiser can already manage.
Route::middleware(['auth', 'verified', 'two-factor'])->group(function () {
    Route::get('/court/claim/{code}', [\App\Scoreboard\Sports\Taekwondo\Controllers\CourtDisplayController::class, 'claim'])
        ->name('court-display.claim')->where('code', '[A-Z0-9]{6}')->middleware('throttle:30,1');
    Route::post('/court/claim/{code}', [\App\Scoreboard\Sports\Taekwondo\Controllers\CourtDisplayController::class, 'storeClaim'])
        ->name('court-display.claim.store')->where('code', '[A-Z0-9]{6}')->middleware('throttle:member-write');
    Route::get('/court/screen/{device}', [\App\Scoreboard\Sports\Taekwondo\Controllers\CourtDisplayController::class, 'claimed'])
        ->name('court-display.claimed')->whereNumber('device');
});

// Court-display webfonts. Public and unauthenticated by necessity — a hall screen
// has no session — but they are static typefaces served from a fixed whitelist,
// so there is nothing here to scope to a viewer.
Route::get('/court-display/font/{file}', [\App\Scoreboard\Sports\Taekwondo\Controllers\CourtDisplayController::class, 'font'])
    ->name('court-display.font')->where('file', '[a-z0-9.-]+')->middleware('throttle:60,1');

/*
|--------------------------------------------------------------------------
| Karate hall screens
|--------------------------------------------------------------------------
| The same surface as the Taekwondo block above, for the Karate Tournament
| package's own screen fleet: its own controller, its own devices table
| (karate_court_displays), its own screen build and systemd unit.
|
| Separate rather than shared BY DESIGN. Both packages resolve a device from a
| bare token, so one route set over one table could hand a Karate screen a
| Taekwondo board. A screen belongs to one fleet, and the URL it was flashed
| with is what decides which. Everything else — throttles, the reasons each
| endpoint is or is not authenticated — is identical, so the notes above apply
| here unchanged.
*/
// Karate's old enrolment door, redirected for the same reason as Taekwondo's
// above: the fleet is now decided by the EVENT at claim time, not by the
// address a screen was opened with, so there is nothing left for a second
// per-sport URL to decide. Ahead of the token route so the literal wins.
Route::get('/karate/court/new', \Illuminate\Routing\RedirectController::class)
    ->defaults('destination', '/screen')->defaults('status', 302)
    ->name('karate-court-display.new')->middleware('throttle:60,1');

Route::get('/karate/court/{token}', [\App\Scoreboard\Sports\Karate\Controllers\CourtDisplayController::class, 'board'])
    ->name('karate-court-display.board')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');

Route::post('/karate/court/enroll', [\App\Scoreboard\Sports\Karate\Controllers\CourtDisplayController::class, 'enroll'])
    ->name('karate-court-display.enroll')->middleware('throttle:court-enroll');

Route::get('/karate/court/{token}/status', [\App\Scoreboard\Sports\Karate\Controllers\CourtDisplayController::class, 'status'])
    ->name('karate-court-display.status')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');

Route::get('/karate/court/{token}/payload', [\App\Scoreboard\Sports\Karate\Controllers\CourtDisplayController::class, 'payload'])
    ->name('karate-court-display.payload')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');

Route::get('/karate/court/{token}/link', [\App\Scoreboard\Sports\Karate\Controllers\CourtDisplayController::class, 'link'])
    ->name('karate-court-display.link')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');

Route::middleware(['auth', 'verified', 'two-factor'])->group(function () {
    Route::get('/karate/court/claim/{code}', [\App\Scoreboard\Sports\Karate\Controllers\CourtDisplayController::class, 'claim'])
        ->name('karate-court-display.claim')->where('code', '[A-Z0-9]{6}')->middleware('throttle:30,1');
    Route::post('/karate/court/claim/{code}', [\App\Scoreboard\Sports\Karate\Controllers\CourtDisplayController::class, 'storeClaim'])
        ->name('karate-court-display.claim.store')->where('code', '[A-Z0-9]{6}')->middleware('throttle:member-write');
    Route::get('/karate/court/screen/{device}', [\App\Scoreboard\Sports\Karate\Controllers\CourtDisplayController::class, 'claimed'])
        ->name('karate-court-display.claimed')->whereNumber('device');
});

Route::get('/karate/court-display/font/{file}', [\App\Scoreboard\Sports\Karate\Controllers\CourtDisplayController::class, 'font'])
    ->name('karate-court-display.font')->where('file', '[a-z0-9.-]+')->middleware('throttle:60,1');

// A sound this screen plays. Authorised by the screen's own token, like every
// other file a board fetches, and served from private storage: a club's
// introduction music is licensed to them, not published to the internet.
Route::get('/karate/court/{token}/audio/{slot}', [\App\Scoreboard\Sports\Karate\Controllers\CourtDisplayController::class, 'audio'])
    ->name('karate-court-display.audio')
    ->where('token', '[A-Za-z0-9]{40}')->where('slot', '[a-z_0-9]{1,24}')
    ->middleware('throttle:screen-token');

// The mat's live state, for a screen that just loaded or reconnected. Same
// contract as the board payload above: the DEVICE's token authorises it, because
// a wall screen has nobody signed in to it, and that token already names one
// event and one mat — there is nothing here to tamper with.
Route::get('/karate/court/{token}/state', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'state'])
    ->name('karate-scoreboard.state')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');

/*
| The scoring table on a screen that was PAIRED rather than signed in — a tablet
| carried to the mat, scanned once, and handed between officials all day.
|
| Authorised by the device token, which is scoped to one event and one mat, and
| only when that screen was paired as a control by an organiser who could score.
| That organiser's right is re-checked on every request, so removing them from
| the jury stops every console they paired. No CSRF token to carry, because
| there is no session to ride: the bearer of the token IS the credential, and it
| never leaves the device's own URL bar.
*/
Route::withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])->group(function () {
    Route::get('/court/{token}/control', [\App\Scoreboard\Sports\Taekwondo\Controllers\ScoreboardController::class, 'tokenControl'])
        ->name('taekwondo-scoreboard.token-control')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');
    Route::post('/court/{token}/command', [\App\Scoreboard\Sports\Taekwondo\Controllers\ScoreboardController::class, 'tokenCommand'])
        ->name('taekwondo-scoreboard.token-command')->where('token', '[A-Za-z0-9]{40}')
        // NOT `screen-token`. That limiter is 60/min and was written for a
        // PASSIVE wall screen — "a screen may only starve itself". A scoring
        // TABLE is a write path: every point, penalty and clock nudge is one
        // POST, and it shares the bucket with its own page load and its status
        // heartbeat. A busy mat exhausted 60 in under a minute and the tablet
        // went dead mid-bout with a 429 no official could act on. 300/min is
        // what the signed-in consoles already use, and what BJJ's equivalent
        // route has always used.
        ->middleware('throttle:300,1');
    Route::get('/court/{token}/cameras', [\App\Scoreboard\Sports\Taekwondo\Controllers\ScoreboardController::class, 'tokenCameras'])
        ->name('taekwondo-scoreboard.token-cameras')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:120,1');
    Route::post('/court/{token}/cameras/{camera}', [\App\Scoreboard\Sports\Taekwondo\Controllers\ScoreboardController::class, 'tokenCameraCommand'])
        ->name('taekwondo-scoreboard.token-cameras.command')->where('token', '[A-Za-z0-9]{40}')->whereNumber('camera')->middleware('throttle:120,1');
    Route::post('/court/{token}/photo', [\App\Scoreboard\Sports\Taekwondo\Controllers\ScoreboardController::class, 'tokenPhoto'])
        ->name('taekwondo-scoreboard.token-photo')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:uploads');
    // Corners from a paired table — the device's mat, never the request's
    // (App\Scoreboard\Corners\MatSides / CornerLook).
    Route::post('/court/{token}/corners', [\App\Scoreboard\Sports\Taekwondo\Controllers\ScoreboardController::class, 'tokenCornerSide'])
        ->name('taekwondo-scoreboard.token-corners')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');
    Route::post('/court/{token}/corner-look', [\App\Scoreboard\Sports\Taekwondo\Controllers\ScoreboardController::class, 'tokenCornerLook'])
        ->name('taekwondo-scoreboard.token-corner-look')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');
});

// The Taekwondo mat's live state, for a screen that just loaded or reconnected.
// Same contract as its board payload: the DEVICE's token authorises it, and that
// token already names one event and one mat.
Route::get('/court/{token}/state', [\App\Scoreboard\Sports\Taekwondo\Controllers\ScoreboardController::class, 'state'])
    ->name('taekwondo-scoreboard.state')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');

/*
| The Taekwondo scoring table. Same shape and the same rules as the Karate block
| below — the two sports differ in what a point is worth and in the fact that a
| WT match is a series of rounds, not in who is allowed to score one.
*/
Route::middleware(['auth', 'verified', 'two-factor'])->group(function () {
    Route::get('/taekwondo/control/{event:uuid}', [\App\Scoreboard\Sports\Taekwondo\Controllers\ScoreboardController::class, 'control'])
        ->name('taekwondo-scoreboard.control');
    Route::post('/taekwondo/control/{event:uuid}', [\App\Scoreboard\Sports\Taekwondo\Controllers\ScoreboardController::class, 'command'])
        ->name('taekwondo-scoreboard.command')->middleware('throttle:300,1');
    // A competitor picture added at the desk. Throttled far below the command
    // endpoint — this one writes a file, and a scoring official has no reason
    // to send more than a handful a minute.
    Route::post('/taekwondo/control/{event:uuid}/photo', [\App\Scoreboard\Sports\Taekwondo\Controllers\ScoreboardController::class, 'photo'])
        ->name('taekwondo-scoreboard.photo')->middleware('throttle:uploads');
    // Which side of THIS mat red stands on, and the event's corner names and
    // colours. Same canScore() as the commands.
    Route::post('/taekwondo/control/{event:uuid}/corners', [\App\Scoreboard\Sports\Taekwondo\Controllers\ScoreboardController::class, 'cornerSide'])
        ->name('taekwondo-scoreboard.corners')->middleware('throttle:admin-write');
    Route::post('/taekwondo/control/{event:uuid}/corner-look', [\App\Scoreboard\Sports\Taekwondo\Controllers\ScoreboardController::class, 'cornerLook'])
        ->name('taekwondo-scoreboard.corner-look')->middleware('throttle:admin-write');
});

/*
| The Karate scoring table. Bound by the event's UUID because the URL is read
| off a laptop at a mat, and every call re-checks EventAccess::canScore — the
| page is a convenience, the command endpoint is the attack surface.
*/
Route::middleware(['auth', 'verified', 'two-factor'])->group(function () {
    Route::get('/karate/control/{event:uuid}', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'control'])
        ->name('karate-scoreboard.control');
    // Throttled well above a human at a keyboard, but not unbounded: this is a
    // write path an appointed official holds for the length of a competition.
    Route::post('/karate/control/{event:uuid}', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'command'])
        ->name('karate-scoreboard.command')->middleware('throttle:300,1');
    // A face for a corner, from the organiser's own door — the same body the
    // paired tablet posts to, behind the same canScore() check as the commands
    // above. The mat travels in the payload and is checked against the event.
    Route::post('/karate/control/{event:uuid}/photo/{side}', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'photo'])
        ->name('karate-scoreboard.photo')->where('side', 'aka|ao')
        ->middleware('throttle:uploads');
    // The console re-reading its own state + Bouts list when its live link
    // says the mat or the running order moved. Read-only, same canScore().
    Route::get('/karate/control/{event:uuid}/console', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'consoleState'])
        ->name('karate-scoreboard.console-state')->middleware('throttle:120,1');
    // The whole draw, read-only, for the console's Bouts tabs. Same canScore().
    Route::get('/karate/control/{event:uuid}/catalogue', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'catalogueRead'])
        ->name('karate-scoreboard.catalogue')->middleware('throttle:mat-read');
    // The Bouts card's Players tab — ORGANISERS only (checked in the controller).
    Route::get('/karate/control/{event:uuid}/players', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'players'])
        ->name('karate-scoreboard.players')->middleware('throttle:mat-read');
    Route::get('/karate/control/{event:uuid}/players/{registration}', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'player'])
        ->name('karate-scoreboard.player')->whereNumber('registration')->middleware('throttle:mat-read');
    Route::put('/karate/control/{event:uuid}/players/{registration}', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'playerUpdate'])
        ->name('karate-scoreboard.player.update')->whereNumber('registration')->middleware('throttle:admin-write');
    // How a person's photo is placed on the VS screen — never cropped (PhotoVsFrame).
    Route::post('/karate/control/{event:uuid}/players/{registration}/vs-frame', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'playerVsFrame'])
        ->name('karate-scoreboard.player.vs-frame')->whereNumber('registration')->middleware('throttle:admin-write');
    Route::get('/karate/control/{event:uuid}/geo-country', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'geoCountry'])
        ->name('karate-scoreboard.geo-country')->middleware('throttle:mat-read');
    // The Bouts card's Clubs tab — ORGANISERS only (checked in the controller).
    Route::get('/karate/control/{event:uuid}/clubs', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'clubs'])
        ->name('karate-scoreboard.clubs')->middleware('throttle:mat-read');
    Route::put('/karate/control/{event:uuid}/clubs/{clubKey}', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'clubUpdate'])
        ->name('karate-scoreboard.club.update')->whereUuid('clubKey')->middleware('throttle:admin-write');
    // The Bouts card's Exhibitions tab — list, and link one to its draw bout.
    // ORGANISERS only (checked in the controller).
    Route::get('/karate/control/{event:uuid}/exhibitions', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'exhibitions'])
        ->name('karate-scoreboard.exhibitions')->middleware('throttle:mat-read');
    Route::post('/karate/control/{event:uuid}/exhibitions/{exhibition}/link', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'exhibitionLink'])
        ->name('karate-scoreboard.exhibition.link')->whereUuid('exhibition')->middleware('throttle:admin-write');
    Route::post('/karate/control/{event:uuid}/exhibitions/{exhibition}/unlink', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'exhibitionUnlink'])
        ->name('karate-scoreboard.exhibition.unlink')->whereUuid('exhibition')->middleware('throttle:admin-write');
    // Which side of THIS mat the red corner physically stands on. Same
    // canScore() as the commands; the mat travels in the body.
    Route::post('/karate/control/{event:uuid}/corners', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'corners'])
        ->name('karate-scoreboard.corners')->middleware('throttle:admin-write');
    // The corners' NAMES and COLOURS for the whole event (Mat\CornerLook).
    Route::post('/karate/control/{event:uuid}/corner-look', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'cornerLook'])
        ->name('karate-scoreboard.corner-look')->middleware('throttle:admin-write');
    // The cropper's face guide — platform-wide, super-admin only (the
    // controller checks); the event is just the console it was set from.
    Route::post('/karate/control/{event:uuid}/photo-guide', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'photoGuide'])
        ->name('karate-scoreboard.photo-guide')->middleware('throttle:admin-write');
    // Where the VS screen puts the competitors' details — platform-wide,
    // super-admin only (the controller checks).
    Route::post('/karate/control/{event:uuid}/vs-layout', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'vsLayout'])
        ->name('karate-scoreboard.vs-layout')->middleware('throttle:admin-write');
    // The VS screen as a preview inside the console (the mat page, frozen on the introduction).
    Route::get('/karate/control/{event:uuid}/vs-preview', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'vsPreview'])
        ->name('karate-scoreboard.vs-preview')->middleware('throttle:mat-read');
});

/*
| The Karate scoring table as a PAIRED SCREEN. No session: the device token is
| the identity, it names one event and one mat, and every request re-checks
| that the organiser who paired it may still score. Outside the auth group for
| that reason — a tablet at a mat has nobody signed in to it.
*/
Route::get('/karate/court/{token}/control', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'tokenControl'])
    ->name('karate-scoreboard.token-control')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');

// The paired table re-reading itself — see consoleState(). JSON door: a dead
// token answers 404, which is how the console tells "gone" from "wifi".
Route::get('/karate/court/{token}/console', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'tokenConsoleState'])
    ->name('karate-scoreboard.token-console-state')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');

// The whole draw for the paired table's Bouts tabs — the device's event only.
Route::get('/karate/court/{token}/catalogue', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'tokenCatalogue'])
    ->name('karate-scoreboard.token-catalogue')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');

// The Players tab through the table's token — only when the organiser who
// paired it may MANAGE the event (checked in the controller).
Route::get('/karate/court/{token}/players', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'tokenPlayers'])
    ->name('karate-scoreboard.token-players')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');
Route::get('/karate/court/{token}/players/{registration}', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'tokenPlayer'])
    ->name('karate-scoreboard.token-player')->where('token', '[A-Za-z0-9]{40}')->whereNumber('registration')->middleware('throttle:screen-token');
Route::put('/karate/court/{token}/players/{registration}', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'tokenPlayerUpdate'])
    ->name('karate-scoreboard.token-player.update')->where('token', '[A-Za-z0-9]{40}')->whereNumber('registration')->middleware('throttle:screen-token');
Route::post('/karate/court/{token}/players/{registration}/vs-frame', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'tokenPlayerVsFrame'])
    ->name('karate-scoreboard.token-player.vs-frame')->where('token', '[A-Za-z0-9]{40}')->whereNumber('registration')->middleware('throttle:screen-token');
Route::get('/karate/court/{token}/vs-preview', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'tokenVsPreview'])
    ->name('karate-scoreboard.token-vs-preview')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');
// Where the tablet is, as a country — seeds a blank nationality (Players tab).
Route::get('/karate/court/{token}/geo-country', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'tokenGeoCountry'])
    ->name('karate-scoreboard.token-geo-country')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');
// The Clubs tab through the table's token — organisers only.
Route::get('/karate/court/{token}/clubs', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'tokenClubs'])
    ->name('karate-scoreboard.token-clubs')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');
Route::put('/karate/court/{token}/clubs/{clubKey}', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'tokenClubUpdate'])
    ->name('karate-scoreboard.token-club.update')->where('token', '[A-Za-z0-9]{40}')->whereUuid('clubKey')->middleware('throttle:screen-token');

// The Exhibitions tab through the table's token — organisers only.
Route::get('/karate/court/{token}/exhibitions', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'tokenExhibitions'])
    ->name('karate-scoreboard.token-exhibitions')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');
Route::post('/karate/court/{token}/exhibitions/{exhibition}/link', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'tokenExhibitionLink'])
    ->name('karate-scoreboard.token-exhibition.link')->where('token', '[A-Za-z0-9]{40}')->whereUuid('exhibition')->middleware('throttle:screen-token');
Route::post('/karate/court/{token}/exhibitions/{exhibition}/unlink', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'tokenExhibitionUnlink'])
    ->name('karate-scoreboard.token-exhibition.unlink')->where('token', '[A-Za-z0-9]{40}')->whereUuid('exhibition')->middleware('throttle:screen-token');

// The same switch from a paired table — the device's mat, never the request's.
Route::post('/karate/court/{token}/corners', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'tokenCorners'])
    ->name('karate-scoreboard.token-corners')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');

Route::post('/karate/court/{token}/corner-look', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'tokenCornerLook'])
    ->name('karate-scoreboard.token-corner-look')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');

// Uploads from the scoring table itself: the event's sounds, and a face for
// whoever is in a corner right now. Authorised by the same token that already
// writes results — and only on the control surface. See the controller.
Route::post('/karate/court/{token}/audio/{slot}', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'tokenAudio'])
    ->name('karate-scoreboard.token-audio')
    ->where('token', '[A-Za-z0-9]{40}')->where('slot', '[a-z_0-9]{1,24}')
    ->middleware('throttle:uploads');

Route::delete('/karate/court/{token}/audio/{slot}', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'tokenAudioDestroy'])
    ->name('karate-scoreboard.token-audio-destroy')
    ->where('token', '[A-Za-z0-9]{40}')->where('slot', '[a-z_0-9]{1,24}')
    ->middleware('throttle:screen-token');

Route::post('/karate/court/{token}/photo/{side}', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'tokenPhoto'])
    ->name('karate-scoreboard.token-photo')
    ->where('token', '[A-Za-z0-9]{40}')->where('side', 'aka|ao')
    ->middleware('throttle:uploads');

Route::post('/karate/court/{token}/command', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'tokenCommand'])
    ->name('karate-scoreboard.token-command')->where('token', '[A-Za-z0-9]{40}')
        // NOT `screen-token`. That limiter is 60/min and was written for a
        // PASSIVE wall screen — "a screen may only starve itself". A scoring
        // TABLE is a write path: every point, penalty and clock nudge is one
        // POST, and it shares the bucket with its own page load and its status
        // heartbeat. A busy mat exhausted 60 in under a minute and the tablet
        // went dead mid-bout with a 429 no official could act on. 300/min is
        // what the signed-in consoles already use, and what BJJ's equivalent
        // route has always used.
        ->middleware('throttle:300,1');

// The cameras on this console's own mat, and orders to one of them. Same
// authorisation as the command route above: the token names one event and one
// mat, and the controller reads the mat off the DEVICE, never the request.
Route::get('/karate/court/{token}/cameras', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'tokenCameras'])
    ->name('karate-scoreboard.token-cameras')->where('token', '[A-Za-z0-9]{40}')
    ->middleware('throttle:120,1');
Route::post('/karate/court/{token}/cameras/{camera}', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'tokenCameraCommand'])
    ->name('karate-scoreboard.token-cameras.command')->where('token', '[A-Za-z0-9]{40}')->whereNumber('camera')
    ->middleware('throttle:120,1');

// Personal (member) mobile experience — shared mobile shell.
//
// `BrandEventPage` is on the whole group rather than route-by-route, so an
// event page added here tomorrow is branded with nothing to remember. It is
// inert on every route that carries no `{event}` (and on every event whose
// package has not opted in), so the /me pages that are not an event are
// untouched — see the middleware's own notes.
Route::middleware(['auth', 'verified', 'two-factor', \App\Events\Middleware\BrandEventPage::class])
    ->prefix('me')->name('me.')->group(function () {
    // Every other /me event route lives in the Events module: app/Events/routes.php.
    // This one stays because it is served by the Scoreboard's own controller.
    Route::get('/events/{event:uuid}/court/{court}/preview', [\App\Scoreboard\Sports\Taekwondo\Controllers\CourtDisplayController::class, 'preview'])->name('events.court-display.preview')->where('court', '[^/]{1,40}')->middleware('throttle:60,1');
});
