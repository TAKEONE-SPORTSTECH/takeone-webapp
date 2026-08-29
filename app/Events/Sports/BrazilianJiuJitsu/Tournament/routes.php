<?php

use App\Events\Sports\BrazilianJiuJitsu\Tournament\HallScreen\HallScreenController;
use App\Events\Sports\BrazilianJiuJitsu\Tournament\Scoreboard\ScoreboardController;
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

// The mat's live state, for a screen that just loaded or reconnected.
Route::get('/bjj/screen/{token}/state', [ScoreboardController::class, 'state'])
    ->name('bjj-scoreboard.state')->where('token', '[A-Za-z0-9]{40}')
    ->middleware('throttle:screen-token');

/* ---------------- The scoring table as a PAIRED screen ---------------- */

Route::get('/bjj/screen/{token}/control', [ScoreboardController::class, 'tokenControl'])
    ->name('bjj-scoreboard.token-control')->where('token', '[A-Za-z0-9]{40}')
    ->middleware('throttle:screen-token');

Route::post('/bjj/screen/{token}/command', [ScoreboardController::class, 'tokenCommand'])
    ->name('bjj-scoreboard.token-command')->where('token', '[A-Za-z0-9]{40}')
    ->middleware('throttle:300,1');

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
