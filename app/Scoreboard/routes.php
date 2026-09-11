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

/* ---------------- The organiser's own door ---------------- */

Route::middleware(['auth', 'verified', 'two-factor'])->group(function () {
    Route::get('/bjj/control/{event:uuid}', [ScoreboardController::class, 'control'])
        ->name('bjj-scoreboard.control');

    // Throttled well above a human at a keyboard, but not unbounded: this is a
    // write path an appointed official holds for the length of a competition,
    // and every call of it appends a ledger row.
    Route::post('/bjj/control/{event:uuid}', [ScoreboardController::class, 'command'])
        ->name('bjj-scoreboard.command')->middleware('throttle:300,1');

    // The console's own re-read. Cheap and bounded — one state row, twelve
    // queued matches and the log of the bout on the mat — but throttled anyway,
    // because it is reachable by anyone entitled to score and a socket nudge is
    // what normally calls it.
    Route::get('/bjj/control/{event:uuid}/state', [ScoreboardController::class, 'consoleState'])
        ->name('bjj-scoreboard.console-state')->middleware('throttle:120,1');

    // The same whole-draw catalogue as the token door beside it, through the
    // organiser's own session. One console, two front doors.
    Route::get('/bjj/control/{event:uuid}/catalogue', [ScoreboardController::class, 'catalogueRead'])
        ->name('bjj-scoreboard.catalogue')->middleware('throttle:120,1');

    Route::post('/bjj/control/{event:uuid}/photo/{side}', [ScoreboardController::class, 'photo'])
        ->name('bjj-scoreboard.photo')->where('side', 'blue|white')
        ->middleware('throttle:uploads');
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
});

/*
| The Karate scoring table as a PAIRED SCREEN. No session: the device token is
| the identity, it names one event and one mat, and every request re-checks
| that the organiser who paired it may still score. Outside the auth group for
| that reason — a tablet at a mat has nobody signed in to it.
*/
Route::get('/karate/court/{token}/control', [\App\Scoreboard\Sports\Karate\Controllers\ScoreboardController::class, 'tokenControl'])
    ->name('karate-scoreboard.token-control')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');

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
Route::middleware(['auth', 'verified', 'two-factor', \App\Http\Middleware\BrandEventPage::class])
    ->prefix('me')->name('me.')->group(function () {
// The /me member surfaces (home, feed, schedule, family tree, profile,
// packages, payments, videos, people, settings, locale) live in the Members
// module: app/Members/routes-member.php, registered by ModuleServiceProvider
// under /me with the `me.` prefix and the same middleware stack.
    /*
     * ⚠️ `source-text` on the EDITING routes only.
     *
     * Reading `$event->title` now returns the READER's language
     * (App\Traits\TranslatesAttributes). On a form that is a source-destroying
     * bug: an organiser who switched the event to Chinese to check it, then
     * tapped Edit, would be shown the machine's Chinese in the box and would
     * save it over their own Arabic — irrecoverably. See
     * App\Http\Middleware\ReadsSourceContent for the full note.
     *
     * The console, the poster and the bracket deliberately do NOT carry it:
     * they display, and an organiser reading their own competition in Chinese
     * should see Chinese.
     */
    // Events — real, DB-backed (club_events).
    Route::get('/events', [App\Http\Controllers\PersonalEventController::class, 'index'])->name('events');
    Route::get('/events/create', [App\Http\Controllers\PersonalEventController::class, 'create'])->name('events.create')->middleware('source-text');
    Route::post('/events', [App\Http\Controllers\PersonalEventController::class, 'store'])->name('events.store')->middleware(['throttle:member-write', 'source-text']);
    Route::get('/events/{event:uuid}', [App\Http\Controllers\PersonalEventController::class, 'show'])->name('events.show');
    // The organiser's console. `show` is what a visitor came to read; this is
    // what the people running the event came to do. Organiser or an appointed
    // official only — the action refuses everyone else.
    Route::get('/events/{event:uuid}/manage', [App\Http\Controllers\PersonalEventController::class, 'manage'])->name('events.manage');
    Route::get('/events/{event:uuid}/edit', [App\Http\Controllers\PersonalEventController::class, 'edit'])->name('events.edit')->middleware('source-text');
    Route::put('/events/{event:uuid}', [App\Http\Controllers\PersonalEventController::class, 'update'])->name('events.update')->middleware(['throttle:member-write', 'source-text']);
    Route::patch('/events/{event:uuid}/cancel', [App\Http\Controllers\PersonalEventController::class, 'cancelEvent'])->name('events.cancel-event')->middleware('throttle:member-write');
    Route::put('/events/{event:uuid}/results', [App\Http\Controllers\PersonalEventController::class, 'setResults'])->name('events.results')->middleware('throttle:member-write');
    Route::delete('/events/{event:uuid}', [App\Http\Controllers\PersonalEventController::class, 'destroy'])->name('events.destroy')->middleware('throttle:member-write');
    Route::get('/events/{event:uuid}/brackets', [App\Http\Controllers\PersonalEventController::class, 'bracket'])->name('events.bracket');

    // One bout, and where in the event it sat — the page a match video on the
    // video platform links back to (Documentation/VIDEO-INTEGRATION.md §6.6).
    // Keyed by the EVENT's uuid plus the bout's match number: event_matches has
    // no public identifier of its own, and the unguessable part of the link is
    // the event uuid, which the viewer already holds.
    Route::get('/events/{event:uuid}/bout/{matchNo}', [App\Http\Controllers\PersonalEventController::class, 'bout'])
        ->whereNumber('matchNo')->name('events.bout');

    /*
     * Watching a bout back, and writing on it.
     *
     * The page and its notes live on their own controller because they are their
     * own subject — the footage, its angles and the highlights bar derived from
     * the officiating log. Access is NOT the event's alone: the two athletes who
     * fought a bout reach their own footage whatever the event's scope and after
     * it is archived, which BoutVideoController states and enforces, and which
     * MediaStreamController enforces again on every byte.
     */
    Route::get('/events/{event:uuid}/bout/{matchNo}/video', [App\Http\Controllers\BoutVideoController::class, 'show'])
        ->whereNumber('matchNo')->name('events.bout.video');
    // The highlights lists as JSON, so the watch page can re-read them after a
    // coach note is written without reloading the whole bout.
    Route::get('/events/{event:uuid}/bout/{matchNo}/video/data', [App\Http\Controllers\BoutVideoController::class, 'matchData'])
        ->whereNumber('matchNo')->name('events.bout.video.data');
    // Deleting the footage itself — whoever may MANAGE the event, enforced in
    // the controller (EventAccess::canManage, so the jury is outside it).
    // Throttled like any other destructive write: competition video cannot be
    // filmed again, so this is the one button on the page with no undo behind
    // it.
    Route::delete('/events/{event:uuid}/bout/{matchNo}/video', [App\Http\Controllers\BoutVideoController::class, 'destroyVideo'])
        ->whereNumber('matchNo')->name('events.bout.video.destroy')->middleware('throttle:admin-write');
    Route::post('/events/{event:uuid}/bout/{matchNo}/notes', [App\Http\Controllers\BoutVideoController::class, 'storeNote'])
        ->whereNumber('matchNo')->name('events.bout.notes.store')->middleware('throttle:member-write');
    Route::put('/events/{event:uuid}/bout/{matchNo}/notes/{note:uuid}', [App\Http\Controllers\BoutVideoController::class, 'updateNote'])
        ->whereNumber('matchNo')->name('events.bout.notes.update')->middleware('throttle:member-write');
    Route::delete('/events/{event:uuid}/bout/{matchNo}/notes/{note:uuid}', [App\Http\Controllers\BoutVideoController::class, 'destroyNote'])
        ->whereNumber('matchNo')->name('events.bout.notes.destroy')->middleware('throttle:member-write');

    // The conversation under a bout. Anyone who may watch may join it.
    Route::post('/events/{event:uuid}/bout/{matchNo}/comments', [App\Http\Controllers\BoutVideoController::class, 'storeComment'])
        ->whereNumber('matchNo')->name('events.bout.comments.store')->middleware('throttle:social');
    Route::delete('/events/{event:uuid}/bout/{matchNo}/comments/{comment:uuid}', [App\Http\Controllers\BoutVideoController::class, 'destroyComment'])
        ->whereNumber('matchNo')->name('events.bout.comments.destroy')->middleware('throttle:member-write');
    Route::post('/events/{event:uuid}/bout/{matchNo}/comments/{comment:uuid}/like', [App\Http\Controllers\BoutVideoController::class, 'likeComment'])
        ->whereNumber('matchNo')->name('events.bout.comments.like')->middleware('throttle:social');

    // The event's own gallery: every filmed bout, grouped by division.
    Route::get('/events/{event:uuid}/gallery', [App\Http\Controllers\PersonalEventController::class, 'gallery'])->name('events.gallery');
    Route::get('/events/{event:uuid}/gallery/data', [App\Http\Controllers\PersonalEventController::class, 'galleryData'])
        ->name('events.gallery.data')->middleware('throttle:media-read');
    // Organiser corrections to one bout: corners, scores, winner, the names on
    // the sheet, and the video link. The mat is authoritative while a bout is
    // being fought; this is authoritative once it is finished, and every change
    // is appended to the officiating log.
    // The entrants who may stand in one bout: this event, this bout's category.
    Route::get('/events/{event:uuid}/bout/{matchNo}/competitors', [App\Http\Controllers\PersonalEventController::class, 'boutCompetitors'])
        ->whereNumber('matchNo')->name('events.bout.competitors');
    Route::put('/events/{event:uuid}/bout/{matchNo}', [App\Http\Controllers\PersonalEventController::class, 'updateBout'])
        ->whereNumber('matchNo')->name('events.bout.update')->middleware('throttle:member-write');
    // The same board, full screen and free of chrome, for organisers arranging a
    // draw. Redirects back to the bracket for anyone who may not arrange.
    Route::get('/events/{event:uuid}/brackets/manage', [App\Http\Controllers\PersonalEventController::class, 'manageBracket'])->name('events.bracket.manage');

    // Who's joined — the roster that used to render inline on the event screen.
    Route::get('/events/{event:uuid}/people', [App\Http\Controllers\PersonalEventController::class, 'people'])->name('events.people');

    // The officiating sheet — who is running the competition. Reading only, open
    // to anyone the event is visible to; appointing lives on the edit screen and
    // the appointment paperwork (email, phone, fee) stays on events.officials,
    // which is organiser-guarded.
    Route::get('/events/{event:uuid}/officiating', [App\Http\Controllers\PersonalEventController::class, 'officiating'])->name('events.officiating');

    // Documents attached to an event (rulebook, entry form, schedule).
    // Upload/delete are organiser-only; download is anyone the event reaches —
    // each is re-checked in the controller, never inferred from the URL.
    Route::post('/events/{event:uuid}/documents', [App\Http\Controllers\EventDocumentController::class, 'store'])->name('events.documents.store')->middleware('throttle:uploads');
    Route::get('/events/{event:uuid}/documents/{document:uuid}', [App\Http\Controllers\EventDocumentController::class, 'download'])->name('events.documents.download');
    Route::delete('/events/{event:uuid}/documents/{document:uuid}', [App\Http\Controllers\EventDocumentController::class, 'destroy'])->name('events.documents.destroy')->middleware('throttle:member-write');
    // What this event's screens play: the introduction, the celebration, and the
    // noise a point makes. Uploaded by an organiser here; read by the screens
    // through their own token-authorised route, never through this one.
    Route::post('/events/{event:uuid}/screen-audio/{slot}', [App\Events\Support\ScreenMediaController::class, 'store'])
        ->name('events.screen-audio.store')->where('slot', '[a-z_0-9]{1,24}')->middleware('throttle:uploads');
    Route::get('/events/{event:uuid}/screen-audio/{slot}', [App\Events\Support\ScreenMediaController::class, 'show'])
        ->name('events.screen-audio.show')->where('slot', '[a-z_0-9]{1,24}')->middleware('throttle:60,1');
    Route::delete('/events/{event:uuid}/screen-audio/{slot}', [App\Events\Support\ScreenMediaController::class, 'destroy'])
        ->name('events.screen-audio.destroy')->where('slot', '[a-z_0-9]{1,24}')->middleware('throttle:member-write');

    /*
    | Fixing an entry — the organiser's twin of the athlete's "my entry" panel.
    |
    | Declared here rather than beside the public routes so it inherits the
    | platform stack AND is mirrored into the sealed console for free (any named
    | route under `me/events/{event}/` is cloned to `/e/{uuid}/admin/…` by
    | App\Events\Support\SealedEventRoutes — nothing else to do).
    |
    | Both halves call the same App\Events\Support\EntryEditor, so there is one
    | set of rules about what may change and when, not two that drift.
    */
    Route::get('/events/{event:uuid}/entrants/{registration}', [App\Http\Controllers\EntryPanelController::class, 'entrant'])
        ->name('events.entrant')->whereNumber('registration')->middleware('throttle:120,1');

    Route::put('/events/{event:uuid}/entrants/{registration}', [App\Http\Controllers\EntryPanelController::class, 'updateEntrant'])
        ->name('events.entrant.update')->whereNumber('registration')->middleware('throttle:member-write');

    // The withdrawal queue. An athlete asks to come out; the organiser decides.
    // Nobody leaves a bracket without the person running it knowing.
    Route::get('/events/{event:uuid}/withdrawals', [App\Http\Controllers\EntryPanelController::class, 'withdrawals'])
        ->name('events.withdrawals')->middleware('throttle:120,1');

    Route::post('/events/{event:uuid}/withdrawals/{withdrawal}', [App\Http\Controllers\EntryPanelController::class, 'decideWithdrawal'])
        ->name('events.withdrawals.decide')->whereUuid('withdrawal')->middleware('throttle:member-write');

    // Officials' desk: weigh-ins and payment checks, on a screen of its own.
    // "Who's joined" (events.people) is the reading surface and carries no
    // controls for anyone. Each action authorises against its own role — a
    // weigh-in official cannot approve money.
    /* The verification DESK page was removed on 2026-09-03 — its sheet opens
       from a person's card on the entry list (me.events.people) instead. The
       three endpoints it wrote through are below and unchanged. */
    // A face for a competitor, for the introduction screen. The organiser's own
    // photo, on the ENTRY rather than on the person — see the controller.
    Route::post('/events/{event:uuid}/competitors/{registration}/photo', [App\Http\Controllers\PersonalEventController::class, 'competitorPhoto'])
        ->name('events.competitors.photo')->middleware('throttle:uploads');
    Route::delete('/events/{event:uuid}/competitors/{registration}/photo', [App\Http\Controllers\PersonalEventController::class, 'competitorPhotoDestroy'])
        ->name('events.competitors.photo.destroy')->middleware('throttle:member-write');
    Route::put('/events/{event:uuid}/verify/{registration}/weigh-in', [App\Http\Controllers\PersonalEventController::class, 'verifyWeighIn'])->name('events.verify.weigh-in')->middleware('throttle:member-write');
    Route::put('/events/{event:uuid}/verify/{registration}/payment', [App\Http\Controllers\PersonalEventController::class, 'verifyPayment'])->name('events.verify.payment')->middleware('throttle:member-write');
    Route::get('/events/{event:uuid}/verify/{registration}/proof', [App\Http\Controllers\PersonalEventController::class, 'verifyProof'])->name('events.verify.proof');
    // What the entry is being charged for. Same gate as the payment itself
    // (EventAccess::canVerifyPayments) — it writes the money record.
    Route::put('/events/{event:uuid}/verify/{registration}/fees', [App\Http\Controllers\PersonalEventController::class, 'verifyFees'])->name('events.verify.fees')->middleware('throttle:member-write');

    // Run-day checklist, and the start it gates. Writing the list is the
    // organiser's (canManage); clearing an item is any appointed official's
    // (canOfficiate); starting — which locks the draw — is the organiser's
    // alone. Items bind by uuid, never their row id.
    Route::post('/events/{event:uuid}/checklist', [App\Http\Controllers\PersonalEventController::class, 'storeChecklistItem'])->name('events.checklist.store')->middleware('throttle:member-write');
    Route::put('/events/{event:uuid}/checklist/{checklistItem:uuid}', [App\Http\Controllers\PersonalEventController::class, 'toggleChecklistItem'])->name('events.checklist.toggle')->middleware('throttle:member-write');
    Route::delete('/events/{event:uuid}/checklist/{checklistItem:uuid}', [App\Http\Controllers\PersonalEventController::class, 'destroyChecklistItem'])->name('events.checklist.destroy')->middleware('throttle:member-write');
    Route::post('/events/{event:uuid}/start', [App\Http\Controllers\PersonalEventController::class, 'startEvent'])->name('events.start')->middleware('throttle:member-write');

    // Officials (the jury). Appointing is the organiser's call, so these are all
    // canManage-gated; being an official only ever grants arranging the draw.
    Route::get('/events/{event:uuid}/officials', [App\Http\Controllers\PersonalEventController::class, 'officials'])->name('events.officials');
    Route::post('/events/{event:uuid}/officials', [App\Http\Controllers\PersonalEventController::class, 'storeOfficial'])->name('events.officials.store')->middleware('throttle:member-write');
    Route::put('/events/{event:uuid}/officials/{official}', [App\Http\Controllers\PersonalEventController::class, 'updateOfficial'])->name('events.officials.update')->whereNumber('official')->middleware('throttle:member-write');
    Route::delete('/events/{event:uuid}/officials/{official}', [App\Http\Controllers\PersonalEventController::class, 'destroyOfficial'])->name('events.officials.destroy')->middleware('throttle:member-write');
    // Bracket screen data + hand-arranging the draw. Generic to every bracketed
    // type (the package decides what a legal arrangement is), so this is one
    // surface rather than a route per sport.
    Route::get('/events/{event:uuid}/brackets/data', [App\Http\Controllers\PersonalEventController::class, 'bracketData'])->name('events.bracket.data')->middleware('throttle:120,1');
    Route::put('/events/{event:uuid}/brackets/arrange', [App\Http\Controllers\PersonalEventController::class, 'arrangeBracket'])->name('events.bracket.arrange')->middleware('throttle:bracket-arrange');
    Route::put('/events/{event:uuid}/brackets/clear', [App\Http\Controllers\PersonalEventController::class, 'clearBracket'])->name('events.bracket.clear')->middleware('throttle:member-write');

    /*
     | Groups — building a bracket BY HAND.
     |
     | A division is the group a bracket is cut from. These let an organiser make
     | one on the day rather than only on the event form: two brackets merged
     | because four people did not show, a group invented for a category nobody
     | planned, a strong junior moved up.
     |
     | Split by what the act actually is. Creating, renaming and deleting a group
     | is MANAGING the event. Putting people in and out of one is the same act as
     | moving them around a draw, so it needs only canArrange — the appointed
     | jury may do it without being able to edit the event.
     |
     | The division is a numeric id ALWAYS scoped to the event uuid in the path,
     | so an id belonging to another event resolves to nothing. The uuid is the
     | unguessable part; a division id is only meaningful inside it — the same
     | argument the bout route above makes for match numbers.
     */
    // The weight-classes PAGE. Same URI as the store endpoint, different verb —
    // the list and the thing that adds to it are one resource.
    Route::get('/events/{event:uuid}/divisions', [App\Http\Controllers\PersonalEventController::class, 'divisions'])->name('events.divisions')->middleware(['throttle:60,1', 'source-text']);
    Route::post('/events/{event:uuid}/divisions', [App\Http\Controllers\PersonalEventController::class, 'storeDivision'])->name('events.divisions.store')->middleware(['throttle:admin-write', 'source-text']);
    // The ORDER of the list. Declared before the {division} routes for
    // readability only — `order` is not a number, so it could never have
    // matched them. Organiser-only: this is arranging the event's own
    // structure, not arranging a draw.
    Route::put('/events/{event:uuid}/divisions/order', [App\Http\Controllers\PersonalEventController::class, 'reorderDivisions'])->name('events.divisions.order')->middleware('throttle:admin-write');
    Route::patch('/events/{event:uuid}/divisions/{division}', [App\Http\Controllers\PersonalEventController::class, 'updateDivision'])->name('events.divisions.update')->whereNumber('division')->middleware(['throttle:admin-write', 'source-text']);
    Route::delete('/events/{event:uuid}/divisions/{division}', [App\Http\Controllers\PersonalEventController::class, 'destroyDivision'])->name('events.divisions.destroy')->whereNumber('division')->middleware('throttle:admin-write');
    Route::get('/events/{event:uuid}/divisions/{division}/candidates', [App\Http\Controllers\PersonalEventController::class, 'divisionCandidates'])->name('events.divisions.candidates')->whereNumber('division')->middleware('throttle:120,1');
    Route::put('/events/{event:uuid}/divisions/{division}/members', [App\Http\Controllers\PersonalEventController::class, 'updateDivisionMembers'])->name('events.divisions.members')->whereNumber('division')->middleware('throttle:admin-write');
    // Manager actions + outcome recording are owned by the event's TYPE PACKAGE
    // (CLAUDE.md → "Events Are Self-Contained Packages"): one route each, the
    // package decides which actions exist and what they do. Adding an event type
    // never adds a route here.
    Route::post('/events/{event:uuid}/actions/{action}', [App\Http\Controllers\PersonalEventController::class, 'performAction'])->name('events.action')->where('action', '[a-z_]{1,40}')->middleware('throttle:member-write');
    Route::post('/events/{event:uuid}/outcomes/{unit}', [App\Http\Controllers\PersonalEventController::class, 'recordOutcome'])->name('events.outcome')->whereNumber('unit')->middleware('throttle:member-write');
    Route::post('/events/{event:uuid}/expenses', [App\Http\Controllers\PersonalEventController::class, 'addExpense'])->name('events.expenses.add')->middleware('throttle:member-write');
    Route::delete('/events/{event:uuid}/expenses/{expense}', [App\Http\Controllers\PersonalEventController::class, 'deleteExpense'])->name('events.expenses.delete')->whereNumber('expense')->middleware('throttle:member-write');
    Route::put('/events/{event:uuid}/categories/{category}', [App\Http\Controllers\PersonalEventController::class, 'saveCategory'])->name('events.category.save')->middleware('throttle:member-write');
    // Club/coach entry — a squad in one submission. Each athlete still passes
    // the same gate as self-entry; this is convenience, not a bypass.
    // Run day: the athlete's countdown (and the coach's squad view of it).
    // Venue board — a hall screen for a mat (?mat=Mat+1) or the whole venue.
    Route::get('/events/{event:uuid}/board', [App\Http\Controllers\PersonalEventController::class, 'board'])->name('events.board');
    // Court display — rehearse the hall screen in a browser. The screen
    // itself never calls this: it renders a cached copy of the same view against
    // board state pushed over MQTT. Organiser-only, because the court comes
    // straight off the URL.
    Route::get('/events/{event:uuid}/court/{court}/preview', [\App\Scoreboard\Sports\Taekwondo\Controllers\CourtDisplayController::class, 'preview'])->name('events.court-display.preview')->where('court', '[^/]{1,40}')->middleware('throttle:60,1');
    // Hall screens, from inside the event console: scan the QR on a screen and it is
    // pointed at this event and one of its mats. The event comes from the URL
    // and is authorized per request — the scanned code is public by design.
    // Dispatched by the EVENT's sport, never hardcoded to one package: these
    // were pointed at Taekwondo for every event, so pairing from a Karate
    // console wrote a Taekwondo device row against a Karate event — a screen
    // that could then be served by nobody and unpaired from nowhere.
    Route::get('/events/{event:uuid}/screens', [\App\Events\Support\HallScreenRouter::class, 'screens'])->name('events.screens')->middleware('throttle:60,1');
    Route::post('/events/{event:uuid}/screens', [\App\Events\Support\HallScreenRouter::class, 'pair'])->name('events.screens.pair')->middleware('throttle:admin-write');
    Route::delete('/events/{event:uuid}/screens/{device}', [\App\Events\Support\HallScreenRouter::class, 'revoke'])->name('events.screens.revoke')->whereNumber('device')->middleware('throttle:admin-write');
    // The cameras on this event's mats. Sport-neutral, so these go straight to
    // the fleet rather than through the per-sport screen dispatcher: a lens
    // pointed at a mat is the same device whatever is being fought on it.
    Route::get('/events/{event:uuid}/cameras', [\App\Events\Support\Cameras\CameraConsoleController::class, 'index'])->name('events.cameras')->middleware('throttle:60,1');
    Route::delete('/events/{event:uuid}/cameras/{camera}', [\App\Events\Support\Cameras\CameraConsoleController::class, 'unpair'])->name('events.cameras.unpair')->whereNumber('camera')->middleware('throttle:admin-write');
    // Switch one camera's feed on or off. The phone obeys over its own channel,
    // and is refused a publish credential either way while it is off.
    // Upload, purge or play footage the camera is already holding. The phone
    // enforces what may actually be deleted; this only carries the ask.
    Route::post('/events/{event:uuid}/cameras/{camera}/footage', [\App\Events\Support\Cameras\CameraConsoleController::class, 'footage'])->name('events.cameras.footage')->whereNumber('camera')->middleware('throttle:admin-write');
    Route::post('/events/{event:uuid}/cameras/{camera}/broadcast', [\App\Events\Support\Cameras\CameraConsoleController::class, 'broadcast'])->name('events.cameras.broadcast')->whereNumber('camera')->middleware('throttle:admin-write');
    // The SAME cameras, asked about from the scoring table rather than the
    // event console: one mat, and orders about how it films and what it does
    // with what it filmed. Authorised by canScore (whoever runs this mat runs
    // the lenses pointed at it) and scoped to the named mat inside the
    // controller. The paired-tablet door is each sport's own token route.
    Route::get('/events/{event:uuid}/mat-cameras', [\App\Events\Support\Cameras\MatCameraController::class, 'index'])->name('events.mat-cameras')->middleware('throttle:120,1');
    Route::post('/events/{event:uuid}/mat-cameras/{camera}', [\App\Events\Support\Cameras\MatCameraController::class, 'command'])->name('events.mat-cameras.command')->whereNumber('camera')->middleware('throttle:admin-write');
    Route::get('/events/{event:uuid}/next-up', [App\Http\Controllers\PersonalEventController::class, 'nextUp'])->name('events.next-up');
    // A sparring session as JSON, for its console to re-read after a nudge —
    // one coach queues a bout and every other console follows without a reload.
    Route::get('/events/{event:uuid}/sparring', [\App\Events\Sparring\SparringLauncherController::class, 'state'])->name('events.sparring')->middleware('throttle:120,1');
    // An open mat as JSON, for its console to re-read after a realtime nudge —
    // the opponent takes a corner on their own phone and the console follows
    // without a reload.
    Route::get('/events/{event:uuid}/openmat', [\App\Events\OpenMat\OpenMatController::class, 'state'])->name('events.openmat')->middleware('throttle:120,1');
    // Finding somebody to put on the mat. Throttled hard: it takes a search
    // term, and a searchable endpoint is one somebody will hammer. The pool it
    // may return is narrow by design — see OpenMatSession::searchOpponents.
    Route::get('/events/{event:uuid}/openmat/search', [\App\Events\OpenMat\OpenMatController::class, 'search'])->name('events.openmat.search')->middleware('throttle:60,1');
    // The mat's join QR as an SVG. Takes a MAT, not a URL — see the controller.
    Route::get('/events/{event:uuid}/openmat/qr', [\App\Events\OpenMat\OpenMatController::class, 'qr'])->name('events.openmat.qr')->middleware('throttle:60,1');
    // Throttled: it takes a search term, and a searchable endpoint is one
    // someone will try to hammer.
    Route::get('/events/{event:uuid}/entry-roster', [App\Http\Controllers\PersonalEventController::class, 'entryRoster'])->name('events.entry-roster')->middleware('throttle:60,1');
    Route::post('/events/{event:uuid}/entries', [App\Http\Controllers\PersonalEventController::class, 'storeEntries'])->name('events.entries')->middleware('throttle:admin-write');
    // The organiser's own lookup: anyone on the platform they may add to THIS
    // event. Separate from the roster above because the authority is different
    // (running this event, not administering a club) and because it reaches
    // wider — so it needs a query, honours the member's discovery opt-out, and
    // is throttled hard. See EntryService::searchPeople().
    Route::get('/events/{event:uuid}/entry-search', [App\Http\Controllers\PersonalEventController::class, 'entrySearch'])->name('events.entry-search')->middleware('throttle:30,1');
    // ...and taking them back out. Organiser only — entering an athlete is a
    // club's act, striking a name off the list is the competition's. Every rule
    // about whether an entry MAY go lives in EntryService::remove().
    Route::delete('/events/{event:uuid}/entries', [App\Http\Controllers\PersonalEventController::class, 'removeEntries'])->name('events.entries.destroy')->middleware('throttle:admin-write');
    // Turning the public page on or off for this event. Organiser only, and
    // the only writer of `entry_mode` (EVENTS-PUBLIC-ENTRY.md, Phase B).
    Route::post('/events/{event:uuid}/public', [App\Http\Controllers\PersonalEventController::class, 'setEntryMode'])->name('events.public.toggle')->middleware('throttle:admin-write');

    // The picture the public cover opens onto — `images[0]`, replaced in place.
    // Byte-validated by StoresBase64Images, filed under events/{uuid}/branding
    // by StoragePath, and only writable by somebody who may manage the event.
    Route::post('/events/{event:uuid}/cover', [App\Http\Controllers\PersonalEventController::class, 'setCover'])->name('events.cover.store')->middleware('throttle:uploads');
    Route::delete('/events/{event:uuid}/cover', [App\Http\Controllers\PersonalEventController::class, 'clearCover'])->name('events.cover.destroy')->middleware('throttle:admin-write');
    // Whether a public entry needs a yes. A separate decision from publishing
    // the page, so an unreviewed door never opens as a side effect of sharing.
    Route::post('/events/{event:uuid}/public/auto-accept', [App\Http\Controllers\PersonalEventController::class, 'setPublicAutoAccept'])->name('events.public.auto-accept')->middleware('throttle:admin-write');
    // When the draw becomes readable: always | start_day | hidden. Disclosure
    // only — it never changes who may ARRANGE a draw, and the organiser and
    // their officials read it at every setting.
    Route::post('/events/{event:uuid}/draw-reveal', [App\Http\Controllers\PersonalEventController::class, 'setDrawReveal'])->name('events.draw-reveal')->middleware('throttle:admin-write');
    // The queue of strangers who followed that link and asked to compete. The
    // accept is the gate — until it happens the request is an entry in nothing
    // (EVENTS-PUBLIC-ENTRY.md, Door C).
    Route::get('/events/{event:uuid}/public-entries', [App\Http\Controllers\PersonalEventController::class, 'publicEntries'])->name('events.public-entries')->middleware('throttle:60,1');
    Route::post('/events/{event:uuid}/public-entries/{entry}/accept', [App\Http\Controllers\PersonalEventController::class, 'acceptPublicEntry'])->name('events.public-entries.accept')->whereUuid('entry')->middleware('throttle:admin-write');
    Route::post('/events/{event:uuid}/public-entries/{entry}/decline', [App\Http\Controllers\PersonalEventController::class, 'declinePublicEntry'])->name('events.public-entries.decline')->whereUuid('entry')->middleware('throttle:admin-write');
    // Entering somebody not on the roster: a NAME commits the entry, and a
    // single-use link lets the athlete supply what the coach could only have
    // guessed at (Documentation/EVENTS-PUBLIC-ENTRY.md, Door B).
    Route::post('/events/{event:uuid}/entries/unnamed', [App\Http\Controllers\PersonalEventController::class, 'storeUnnamedEntry'])->name('events.entries.unnamed')->middleware('throttle:admin-write');
    Route::get('/events/{event:uuid}/entry-links', [App\Http\Controllers\PersonalEventController::class, 'entryClaimLinks'])->name('events.entry-links')->middleware('throttle:60,1');
    Route::delete('/events/{event:uuid}/entry-links/{claim}', [App\Http\Controllers\PersonalEventController::class, 'revokeEntryClaim'])->name('events.entry-links.revoke')->whereUuid('claim')->middleware('throttle:admin-write');
    Route::post('/events/{event:uuid}/entry-links/{claim}/relink', [App\Http\Controllers\PersonalEventController::class, 'regenerateEntryClaim'])->name('events.entry-links.relink')->whereUuid('claim')->middleware('throttle:admin-write');
    // A club's say over its own name: who has entered themselves claiming it,
    // and rejecting a claim (which never removes the athlete from the event).
    Route::get('/events/{event:uuid}/claims', [App\Http\Controllers\PersonalEventController::class, 'entryClaims'])->name('events.claims');
    Route::post('/events/{event:uuid}/claims/{user}/disown', [App\Http\Controllers\PersonalEventController::class, 'disownClaim'])->name('events.claims.disown')->whereNumber('user')->middleware('throttle:admin-write');
    Route::post('/events/{event:uuid}/register', [App\Http\Controllers\PersonalEventController::class, 'register'])->name('events.register')->middleware('throttle:member-write');
    Route::post('/events/{event:uuid}/ticket', [App\Http\Controllers\PersonalEventController::class, 'ticket'])->name('events.ticket')->middleware('throttle:member-write');
    Route::delete('/events/{event:uuid}/register', [App\Http\Controllers\PersonalEventController::class, 'cancel'])->name('events.cancel')->middleware('throttle:member-write');
    // Owner moderation of registrations (remove / block this event / blacklist club-wide).
    Route::post('/events/{event:uuid}/participants/{user}/moderate', [App\Http\Controllers\PersonalEventController::class, 'moderateParticipant'])->name('events.participant.moderate')->whereNumber('user')->middleware('throttle:member-write');
    Route::delete('/events/{event:uuid}/bans/{user}', [App\Http\Controllers\PersonalEventController::class, 'liftBan'])->name('events.ban.lift')->whereNumber('user')->middleware('throttle:member-write');

    /*
     * The clubs standing behind the event.
     *
     * Reads are cheap and the organiser's console polls none of them; the
     * writes are throttled like every other write on the platform. Every
     * method re-checks that the actor may manage this event — the group's
     * auth stack says WHO is signed in, never which events are theirs.
     */
    // The screen itself, and the same list as JSON for the panel's own updates.
    Route::get('/events/{event:uuid}/clubs', [App\Http\Controllers\EventClubsController::class, 'page'])
        ->name('events.clubs')->middleware('throttle:60,1')->whereUuid('event');

    Route::get('/events/{event:uuid}/clubs/list', [App\Http\Controllers\EventClubsController::class, 'index'])
        ->name('events.clubs.list')->middleware('throttle:60,1')->whereUuid('event');

    Route::get('/events/{event:uuid}/clubs/search', [App\Http\Controllers\EventClubsController::class, 'search'])
        ->name('events.clubs.search')->middleware('throttle:30,1')->whereUuid('event');

    Route::post('/events/{event:uuid}/clubs', [App\Http\Controllers\EventClubsController::class, 'store'])
        ->name('events.clubs.store')->middleware('throttle:20,1')->whereUuid('event');

    Route::post('/events/{event:uuid}/clubs/invite', [App\Http\Controllers\EventClubsController::class, 'invite'])
        ->name('events.clubs.invite')->middleware('throttle:20,1')->whereUuid('event');

    /*
     * ⚠️ `{eventClub}`, never `{club}`.
     *
     * AppServiceProvider registers a GLOBAL `Route::bind('club', …)` that
     * resolves any `{club}` parameter to a Tenant by id-or-slug with
     * firstOrFail(). An explicit binder beats implicit model binding, so a
     * route named `{club}` here looked for a TENANT whose slug was this row's
     * uuid, found none, and answered 404 before the controller ran. The delete
     * button reported "Not found" for a club sitting right there on the screen.
     */
    // Editing one that was written down here. A club with an account is not
    // edited from inside a competition — see EventClubsController::update().
    Route::put('/events/{event:uuid}/clubs/{eventClub}', [App\Http\Controllers\EventClubsController::class, 'update'])
        ->name('events.clubs.update')->middleware('throttle:20,1')->whereUuid('event');

    Route::delete('/events/{event:uuid}/clubs/{eventClub}', [App\Http\Controllers\EventClubsController::class, 'destroy'])
        ->name('events.clubs.destroy')->middleware('throttle:20,1')->whereUuid('event');

    Route::get('/events/{event:uuid}/clubs/entrants', [App\Http\Controllers\EventClubsController::class, 'entrants'])
        ->name('events.clubs.entrants')->middleware('throttle:60,1')->whereUuid('event');

    Route::post('/events/{event:uuid}/clubs/{eventClub}/athletes', [App\Http\Controllers\EventClubsController::class, 'assign'])
        ->name('events.clubs.athletes')->middleware('throttle:30,1')->whereUuid('event');

    // Turn the temporary clubs that competed into real ones. Refused before the
    // event is over, because "who competed" is not a fact until then. Nothing
    // runs this on a timer — see App\Console\Commands\PromoteEventClubs.
    Route::post('/events/{event:uuid}/clubs/promote', [App\Http\Controllers\EventClubsController::class, 'promote'])
        ->name('events.clubs.promote')->middleware('throttle:6,1')->whereUuid('event');

    /*
     * The club's own answer. Under the member prefix because only a signed-in
     * club owner can give it, and it is refused for anybody but the person the
     * invitation was addressed to.
     */
    /* The invitation flow is switched OFF (2026-09-06). Looking a club up adds
       it; nobody is asked and nobody answers, so there is nothing to respond to
       and the route is not registered. EventClubsController::respond() is left
       in place, unrouted, for the day it comes back. */

// Challenges & 1v1 duels live in the Challenges module:
// app/Challenges/routes-member.php, registered under the same /me prefix
// with the `me.` name prefix and the same middleware stack.
});
