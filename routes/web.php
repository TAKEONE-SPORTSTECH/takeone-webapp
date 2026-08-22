<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\InstructorReviewController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\PlatformController;
use App\Http\Controllers\TrainerController;
use App\Http\Controllers\TwoFactorController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    if (Auth::check()) {
        return redirect(\App\Support\Landing::url($request));
    }

    return redirect()->route('login');
});

// Two-Factor Authentication challenge (no auth required — user is between login and session)
Route::get('/two-factor-challenge', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
Route::post('/two-factor-challenge', [TwoFactorController::class, 'verifyChallenge'])->name('two-factor.verify')->middleware('throttle:6,1');

// Security Settings (requires full auth + 2FA if enabled)
Route::middleware(['auth', 'verified', 'two-factor'])->group(function () {
    Route::get('/security', [TwoFactorController::class, 'show'])->name('security.show');
    Route::post('/security/two-factor/setup', [TwoFactorController::class, 'setup'])->name('security.2fa.setup');
    Route::get('/security/two-factor/setup', [TwoFactorController::class, 'setup'])->name('security.2fa.setup.get');
    Route::post('/security/two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('security.2fa.confirm');
    Route::post('/security/two-factor/disable', [TwoFactorController::class, 'disable'])->name('security.2fa.disable');
    Route::post('/security/two-factor/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])->name('security.2fa.recovery-codes');
    Route::post('/security/password', [TwoFactorController::class, 'changePassword'])->name('security.password.change')->middleware('throttle:6,1');

    // Business (Chain) — create & manage your own chain
    Route::get('/business/create', [App\Http\Controllers\BusinessController::class, 'setup'])->name('business.setup');
    Route::post('/business', [App\Http\Controllers\BusinessController::class, 'store'])->name('business.store')->middleware('throttle:6,1');

    // Switch between Personal and Business view modes (Facebook-style)
    Route::post('/switch-view', [App\Http\Controllers\BusinessController::class, 'switchView'])->name('view.switch');
});

// Business (Chain) dashboard — requires an APPROVED business
Route::middleware(['auth', 'verified', 'two-factor', 'business'])->prefix('business')->name('business.')->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\BusinessDashboardController::class, 'index'])->name('dashboard');
    Route::post('/clubs', [App\Http\Controllers\BusinessClubController::class, 'store'])->name('clubs.store')->middleware('throttle:admin-write');
});

// Market item creators — PREVIEW of the reusable product/category form
// components (form UI only, no DB yet). Drop the components into club admin /
// seller area / personal mobile when wiring the real backend.
Route::middleware(['auth', 'verified'])->get('/market/forms-preview', function () {
    return view('market.forms-preview');
})->name('market.forms-preview');

// Public version manifest polled by the installed Android app to detect updates.
Route::get('/app/manifest.json', [App\Http\Controllers\MobileAppController::class, 'manifest'])->name('app.manifest');

// The hall board a screen opens. Unauthenticated BY DESIGN: a wall screen
// has no keyboard and nobody to sign in, so the device's own token is its
// identity. The URL carries no event and no court — both are read off the paired
// device — so there is no identifier here to tamper with or enumerate.
Route::get('/court/{token}', [\App\Events\Sports\Taekwondo\Tournament\CourtDisplay\CourtDisplayController::class, 'board'])
    ->name('court-display.board')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');
// A screen's first boot: it has nothing on its SD card and asks for an identity.
// What it receives is an UNCLAIMED device that can show a pairing code and
// nothing else, so the endpoint grants no access to any data — but it does write
// a row, hence the hard throttle.
Route::post('/court/enroll', [\App\Events\Sports\Taekwondo\Tournament\CourtDisplay\CourtDisplayController::class, 'enroll'])
    ->name('court-display.enroll')->middleware('throttle:court-enroll');

// "Have I been claimed yet?" — one boolean, polled by an unpaired screen.
Route::get('/court/{token}/status', [\App\Events\Sports\Taekwondo\Tournament\CourtDisplay\CourtDisplayController::class, 'status'])
    ->name('court-display.status')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');

/*
|--------------------------------------------------------------------------
| Screens — one address, any screen, any job
|--------------------------------------------------------------------------
| Sport-neutral on purpose. A person carrying a television into a hall does not
| know which package will own it and should not have to: they open /screen, the
| screen shows a QR, an organiser scans it and says what it is — a scoreboard,
| an upcoming-matches board, or the scoring table — and only THEN is it adopted
| into that event's fleet with a token of that fleet's own.
|
| Open, like every screen endpoint, because a wall has nobody signed in to it.
| What an unclaimed screen can render is its own pairing code and nothing else.
*/
// Creates a waiting row and redirects to it, so this GET is now the enrolment
// and carries the enrolment's limit. A reload costs nothing: the cookie means
// the same machine resolves to the screen it already is.
Route::get('/screen', [\App\Events\Support\ScreenPairingController::class, 'screen'])
    ->name('screen.new')->middleware('throttle:court-enroll');
Route::post('/screen/enroll', [\App\Events\Support\ScreenPairingController::class, 'enroll'])
    ->name('screen.enroll')->middleware('throttle:court-enroll');
// The app itself, for a television that does not have it yet. Ahead of the
// token route so the literal segment wins, and deliberately SHORT: this address
// gets typed with a remote control, one letter at a time, on a TV that has
// nothing on it but a sideloader.
Route::get('/screen/app', [\App\Events\Support\ScreenPairingController::class, 'app'])
    ->name('screen.app')->middleware('throttle:screen-app');
// The same, for the scoring table. A second address rather than a query string
// because this one is also typed by hand: `/screen/tab` is four characters more
// than `/screen/app`, and `?device=tab` is a punctuation lesson on a remote.
Route::get('/screen/tab', [\App\Events\Support\ScreenPairingController::class, 'app'])
    ->name('screen.app.tab')->defaults('variant', 'tab')->middleware('throttle:screen-app');
Route::get('/screen/{token}', [\App\Events\Support\ScreenPairingController::class, 'show'])
    ->name('screen.show')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');
Route::get('/screen/{token}/status', [\App\Events\Support\ScreenPairingController::class, 'status'])
    ->name('screen.status')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');

Route::middleware(['auth', 'verified', 'two-factor'])->group(function () {
    Route::get('/screen/claim/{code}', [\App\Events\Support\ScreenPairingController::class, 'claim'])
        ->name('screen.claim')->where('code', '[A-Z0-9]{6}')->middleware('throttle:30,1');
    Route::post('/screen/claim/{code}', [\App\Events\Support\ScreenPairingController::class, 'storeClaim'])
        ->name('screen.claim.store')->where('code', '[A-Z0-9]{6}')->middleware('throttle:member-write');
    Route::get('/screen/paired', [\App\Events\Support\ScreenPairingController::class, 'claimed'])
        ->name('screen.claimed');
});

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
Route::get('/court/{token}/payload', [\App\Events\Sports\Taekwondo\Tournament\CourtDisplay\CourtDisplayController::class, 'payload'])
    ->name('court-display.payload')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');

// The device agent's realtime credentials — subscribe-only, one topic. Held by
// the agent rather than the page, because the board's own animation load can
// starve an inbound socket message in the renderer for minutes.
Route::get('/court/{token}/link', [\App\Events\Sports\Taekwondo\Tournament\CourtDisplay\CourtDisplayController::class, 'link'])
    ->name('court-display.link')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');

// Claiming a screen — the page its QR points at. Authenticated, because the
// pairing code is printed on a wall in a public hall and is worth nothing on its
// own: every event offered is one the signed-in organiser can already manage.
Route::middleware(['auth', 'verified', 'two-factor'])->group(function () {
    Route::get('/court/claim/{code}', [\App\Events\Sports\Taekwondo\Tournament\CourtDisplay\CourtDisplayController::class, 'claim'])
        ->name('court-display.claim')->where('code', '[A-Z0-9]{6}')->middleware('throttle:30,1');
    Route::post('/court/claim/{code}', [\App\Events\Sports\Taekwondo\Tournament\CourtDisplay\CourtDisplayController::class, 'storeClaim'])
        ->name('court-display.claim.store')->where('code', '[A-Z0-9]{6}')->middleware('throttle:member-write');
    Route::get('/court/screen/{device}', [\App\Events\Sports\Taekwondo\Tournament\CourtDisplay\CourtDisplayController::class, 'claimed'])
        ->name('court-display.claimed')->whereNumber('device');
});

// Court-display webfonts. Public and unauthenticated by necessity — a hall screen
// has no session — but they are static typefaces served from a fixed whitelist,
// so there is nothing here to scope to a viewer.
Route::get('/court-display/font/{file}', [\App\Events\Sports\Taekwondo\Tournament\CourtDisplay\CourtDisplayController::class, 'font'])
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

Route::get('/karate/court/{token}', [\App\Events\Sports\Karate\Tournament\CourtDisplay\CourtDisplayController::class, 'board'])
    ->name('karate-court-display.board')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');
Route::post('/karate/court/enroll', [\App\Events\Sports\Karate\Tournament\CourtDisplay\CourtDisplayController::class, 'enroll'])
    ->name('karate-court-display.enroll')->middleware('throttle:court-enroll');

Route::get('/karate/court/{token}/status', [\App\Events\Sports\Karate\Tournament\CourtDisplay\CourtDisplayController::class, 'status'])
    ->name('karate-court-display.status')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');

Route::get('/karate/court/{token}/payload', [\App\Events\Sports\Karate\Tournament\CourtDisplay\CourtDisplayController::class, 'payload'])
    ->name('karate-court-display.payload')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');

Route::get('/karate/court/{token}/link', [\App\Events\Sports\Karate\Tournament\CourtDisplay\CourtDisplayController::class, 'link'])
    ->name('karate-court-display.link')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');

Route::middleware(['auth', 'verified', 'two-factor'])->group(function () {
    Route::get('/karate/court/claim/{code}', [\App\Events\Sports\Karate\Tournament\CourtDisplay\CourtDisplayController::class, 'claim'])
        ->name('karate-court-display.claim')->where('code', '[A-Z0-9]{6}')->middleware('throttle:30,1');
    Route::post('/karate/court/claim/{code}', [\App\Events\Sports\Karate\Tournament\CourtDisplay\CourtDisplayController::class, 'storeClaim'])
        ->name('karate-court-display.claim.store')->where('code', '[A-Z0-9]{6}')->middleware('throttle:member-write');
    Route::get('/karate/court/screen/{device}', [\App\Events\Sports\Karate\Tournament\CourtDisplay\CourtDisplayController::class, 'claimed'])
        ->name('karate-court-display.claimed')->whereNumber('device');
});

Route::get('/karate/court-display/font/{file}', [\App\Events\Sports\Karate\Tournament\CourtDisplay\CourtDisplayController::class, 'font'])
    ->name('karate-court-display.font')->where('file', '[a-z0-9.-]+')->middleware('throttle:60,1');
// A sound this screen plays. Authorised by the screen's own token, like every
// other file a board fetches, and served from private storage: a club's
// introduction music is licensed to them, not published to the internet.
Route::get('/karate/court/{token}/audio/{slot}', [\App\Events\Sports\Karate\Tournament\CourtDisplay\CourtDisplayController::class, 'audio'])
    ->name('karate-court-display.audio')
    ->where('token', '[A-Za-z0-9]{40}')->where('slot', '[a-z_0-9]{1,24}')
    ->middleware('throttle:screen-token');

// The mat's live state, for a screen that just loaded or reconnected. Same
// contract as the board payload above: the DEVICE's token authorises it, because
// a wall screen has nobody signed in to it, and that token already names one
// event and one mat — there is nothing here to tamper with.
Route::get('/karate/court/{token}/state', [\App\Events\Sports\Karate\Tournament\Scoreboard\ScoreboardController::class, 'state'])
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
    Route::get('/court/{token}/control', [\App\Events\Sports\Taekwondo\Tournament\Scoreboard\ScoreboardController::class, 'tokenControl'])
        ->name('taekwondo-scoreboard.token-control')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');
    Route::post('/court/{token}/command', [\App\Events\Sports\Taekwondo\Tournament\Scoreboard\ScoreboardController::class, 'tokenCommand'])
        ->name('taekwondo-scoreboard.token-command')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');
    Route::post('/court/{token}/photo', [\App\Events\Sports\Taekwondo\Tournament\Scoreboard\ScoreboardController::class, 'tokenPhoto'])
        ->name('taekwondo-scoreboard.token-photo')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:uploads');
});

// The Taekwondo mat's live state, for a screen that just loaded or reconnected.
// Same contract as its board payload: the DEVICE's token authorises it, and that
// token already names one event and one mat.
Route::get('/court/{token}/state', [\App\Events\Sports\Taekwondo\Tournament\Scoreboard\ScoreboardController::class, 'state'])
    ->name('taekwondo-scoreboard.state')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');

/*
| The Taekwondo scoring table. Same shape and the same rules as the Karate block
| below — the two sports differ in what a point is worth and in the fact that a
| WT match is a series of rounds, not in who is allowed to score one.
*/
Route::middleware(['auth', 'verified', 'two-factor'])->group(function () {
    Route::get('/taekwondo/control/{event:uuid}', [\App\Events\Sports\Taekwondo\Tournament\Scoreboard\ScoreboardController::class, 'control'])
        ->name('taekwondo-scoreboard.control');
    Route::post('/taekwondo/control/{event:uuid}', [\App\Events\Sports\Taekwondo\Tournament\Scoreboard\ScoreboardController::class, 'command'])
        ->name('taekwondo-scoreboard.command')->middleware('throttle:300,1');
    // A competitor picture added at the desk. Throttled far below the command
    // endpoint — this one writes a file, and a scoring official has no reason
    // to send more than a handful a minute.
    Route::post('/taekwondo/control/{event:uuid}/photo', [\App\Events\Sports\Taekwondo\Tournament\Scoreboard\ScoreboardController::class, 'photo'])
        ->name('taekwondo-scoreboard.photo')->middleware('throttle:uploads');
});

/*
| The Karate scoring table. Bound by the event's UUID because the URL is read
| off a laptop at a mat, and every call re-checks EventAccess::canScore — the
| page is a convenience, the command endpoint is the attack surface.
*/
Route::middleware(['auth', 'verified', 'two-factor'])->group(function () {
    Route::get('/karate/control/{event:uuid}', [\App\Events\Sports\Karate\Tournament\Scoreboard\ScoreboardController::class, 'control'])
        ->name('karate-scoreboard.control');
    // Throttled well above a human at a keyboard, but not unbounded: this is a
    // write path an appointed official holds for the length of a competition.
    Route::post('/karate/control/{event:uuid}', [\App\Events\Sports\Karate\Tournament\Scoreboard\ScoreboardController::class, 'command'])
        ->name('karate-scoreboard.command')->middleware('throttle:300,1');
});

/*
| The Karate scoring table as a PAIRED SCREEN. No session: the device token is
| the identity, it names one event and one mat, and every request re-checks
| that the organiser who paired it may still score. Outside the auth group for
| that reason — a tablet at a mat has nobody signed in to it.
*/
Route::get('/karate/court/{token}/control', [\App\Events\Sports\Karate\Tournament\Scoreboard\ScoreboardController::class, 'tokenControl'])
    ->name('karate-scoreboard.token-control')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');
// Uploads from the scoring table itself: the event's sounds, and a face for
// whoever is in a corner right now. Authorised by the same token that already
// writes results — and only on the control surface. See the controller.
Route::post('/karate/court/{token}/audio/{slot}', [\App\Events\Sports\Karate\Tournament\Scoreboard\ScoreboardController::class, 'tokenAudio'])
    ->name('karate-scoreboard.token-audio')
    ->where('token', '[A-Za-z0-9]{40}')->where('slot', '[a-z_0-9]{1,24}')
    ->middleware('throttle:uploads');
Route::delete('/karate/court/{token}/audio/{slot}', [\App\Events\Sports\Karate\Tournament\Scoreboard\ScoreboardController::class, 'tokenAudioDestroy'])
    ->name('karate-scoreboard.token-audio-destroy')
    ->where('token', '[A-Za-z0-9]{40}')->where('slot', '[a-z_0-9]{1,24}')
    ->middleware('throttle:screen-token');
Route::post('/karate/court/{token}/photo/{side}', [\App\Events\Sports\Karate\Tournament\Scoreboard\ScoreboardController::class, 'tokenPhoto'])
    ->name('karate-scoreboard.token-photo')
    ->where('token', '[A-Za-z0-9]{40}')->where('side', 'aka|ao')
    ->middleware('throttle:uploads');
Route::post('/karate/court/{token}/command', [\App\Events\Sports\Karate\Tournament\Scoreboard\ScoreboardController::class, 'tokenCommand'])
    ->name('karate-scoreboard.token-command')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');

Route::middleware(['auth', 'verified', 'two-factor'])->group(function () {
});

// Personal (member) mobile experience — shared mobile shell
Route::middleware(['auth', 'verified', 'two-factor'])->prefix('me')->name('me.')->group(function () {
    Route::get('/', [App\Http\Controllers\PersonalMobileController::class, 'home'])->name('home');

    // Native push (FCM) device-token registration for the Android app.
    Route::post('/push-tokens', [App\Http\Controllers\PushTokenController::class, 'store'])->name('push-tokens.store')->middleware('throttle:member-write');
    Route::delete('/push-tokens', [App\Http\Controllers\PushTokenController::class, 'destroy'])->name('push-tokens.destroy')->middleware('throttle:member-write');

    // "Get the App / Update" hub (renders in the mobile shell).
    Route::get('/app', [App\Http\Controllers\MobileAppController::class, 'page'])->name('app');

    // Member-authored personal feed posts (text + images, likes, comments).
    Route::post('/posts', [App\Http\Controllers\UserPostController::class, 'store'])->name('posts.store')->middleware('throttle:uploads');
    Route::put('/posts/{post}', [App\Http\Controllers\UserPostController::class, 'update'])->name('posts.update')->middleware('throttle:member-write');
    Route::delete('/posts/{post}', [App\Http\Controllers\UserPostController::class, 'destroy'])->name('posts.destroy')->middleware('throttle:member-write');
    Route::post('/posts/{post}/like', [App\Http\Controllers\UserPostController::class, 'like'])->name('posts.like')->middleware('throttle:member-write');
    Route::post('/posts/{post}/vote', [App\Http\Controllers\UserPostController::class, 'vote'])->name('posts.vote')->middleware('throttle:member-write');
    Route::post('/posts/{post}/view', [App\Http\Controllers\UserPostController::class, 'view'])->name('posts.view')->middleware('throttle:member-write');
    Route::get('/posts/{post}/viewers', [App\Http\Controllers\UserPostController::class, 'viewers'])->name('posts.viewers');
    Route::get('/posts/{post}/likers', [App\Http\Controllers\UserPostController::class, 'likers'])->name('posts.likers');
    Route::post('/posts/{post}/comment', [App\Http\Controllers\UserPostController::class, 'comment'])->name('posts.comment')->middleware('throttle:member-write');
    // Super-admin moderation: hide a post from public view (reversible) or unhide it.
    Route::post('/posts/{post}/hide', [App\Http\Controllers\UserPostController::class, 'hide'])->name('posts.hide')->middleware('throttle:member-write');
    Route::post('/posts/{post}/unhide', [App\Http\Controllers\UserPostController::class, 'unhide'])->name('posts.unhide')->middleware('throttle:member-write');
    Route::get('/schedule', [App\Http\Controllers\PersonalMobileController::class, 'schedule'])->name('schedule');
    Route::get('/schedule/data', [App\Http\Controllers\PersonalMobileController::class, 'scheduleData'])->name('schedule.data');
    Route::post('/schedule', [App\Http\Controllers\PersonalMobileController::class, 'store'])->name('schedule.store')->middleware('throttle:member-write');
    Route::get('/schedule/synced/{token}', [App\Http\Controllers\PersonalMobileController::class, 'scheduleSyncedShow'])->name('schedule.synced');
    Route::put('/schedule/synced/{token}', [App\Http\Controllers\PersonalMobileController::class, 'scheduleSyncedUpdate'])->name('schedule.synced.update')->middleware('throttle:admin-write');
    // Substitute trainer for a single dated occurrence of a club class.
    Route::get('/schedule/synced/{token}/substitutes', [App\Http\Controllers\PersonalMobileController::class, 'substituteSearch'])->name('schedule.substitute.search');
    Route::post('/schedule/synced/{token}/substitute', [App\Http\Controllers\PersonalMobileController::class, 'substituteAssign'])->name('schedule.substitute.assign')->middleware('throttle:admin-write');
    Route::delete('/schedule/synced/{token}/substitute', [App\Http\Controllers\PersonalMobileController::class, 'substituteRemove'])->name('schedule.substitute.remove')->middleware('throttle:admin-write');
    // Mark / unmark attendance for one dated occurrence of a club class.
    Route::post('/schedule/synced/{token}/attendance', [App\Http\Controllers\PersonalMobileController::class, 'attendanceToggle'])->name('schedule.attendance.toggle')->middleware('throttle:admin-write');
    // Cancel / restore a club class for a date or range (credits enrolled members).
    // Trainee engagement: emoji reaction + rate the trainer.
    Route::post('/schedule/synced/{token}/react', [App\Http\Controllers\PersonalMobileController::class, 'reactClass'])->name('schedule.react')->middleware('throttle:member-write');
    Route::post('/schedule/synced/{token}/rate', [App\Http\Controllers\PersonalMobileController::class, 'rateClassTrainer'])->name('schedule.rate')->middleware('throttle:social');
    Route::post('/schedule/synced/{token}/rate-class', [App\Http\Controllers\PersonalMobileController::class, 'rateClass'])->name('schedule.rate.class')->middleware('throttle:social');
    Route::delete('/schedule/synced/{token}/rate-class', [App\Http\Controllers\PersonalMobileController::class, 'rateClassDestroy'])->name('schedule.rate.class.destroy')->middleware('throttle:social');
    Route::post('/schedule/synced/{token}/cancel', [App\Http\Controllers\PersonalMobileController::class, 'classCancel'])->name('schedule.cancel')->middleware('throttle:admin-write');
    Route::delete('/schedule/synced/{token}/cancel', [App\Http\Controllers\PersonalMobileController::class, 'classUncancel'])->name('schedule.uncancel')->middleware('throttle:admin-write');
    // Clear a one-off training-program variation for a single dated occurrence (reverts to the recurring plan).
    Route::delete('/schedule/synced/{token}/program', [App\Http\Controllers\PersonalMobileController::class, 'programReset'])->name('schedule.program.reset')->middleware('throttle:admin-write');
    Route::get('/schedule/{session}', [App\Http\Controllers\PersonalMobileController::class, 'scheduleShow'])->name('schedule.show')->whereNumber('session');
    Route::put('/schedule/{session}', [App\Http\Controllers\PersonalMobileController::class, 'update'])->name('schedule.update')->whereNumber('session')->middleware('throttle:member-write');
    Route::delete('/schedule/{session}', [App\Http\Controllers\PersonalMobileController::class, 'destroy'])->name('schedule.destroy')->whereNumber('session')->middleware('throttle:member-write');
    Route::get('/affiliations', [App\Http\Controllers\PersonalMobileController::class, 'affiliations'])->name('affiliations');
    // Family tree (kinship graph) — page + JSON window + writes
    Route::get('/family', [App\Http\Controllers\FamilyTreeController::class, 'index'])->name('family');
    Route::get('/family/data', [App\Http\Controllers\FamilyTreeController::class, 'data'])->name('family.data');
    Route::post('/family/relative', [App\Http\Controllers\FamilyTreeController::class, 'addRelative'])->name('family.relative')->middleware('throttle:member-write');
    Route::post('/family/respond', [App\Http\Controllers\FamilyTreeController::class, 'respond'])->name('family.respond')->middleware('throttle:member-write');
    Route::delete('/family/relative', [App\Http\Controllers\FamilyTreeController::class, 'removeRelative'])->name('family.relative.remove')->middleware('throttle:member-write');
    Route::get('/profile', [App\Http\Controllers\PersonalMobileController::class, 'profile'])->name('profile');
    Route::get('/packages', [App\Http\Controllers\PersonalMobileController::class, 'packages'])->name('packages');
    Route::get('/progress', [App\Http\Controllers\PersonalMobileController::class, 'progress'])->name('progress');
    Route::get('/payments', [App\Http\Controllers\PersonalMobileController::class, 'payments'])->name('payments');
    // Settle an outstanding subscription bill by uploading proof of payment.
    Route::post('/payments/{subscription}/settle', [App\Http\Controllers\PersonalMobileController::class, 'settlePayment'])->name('payments.settle')->middleware('throttle:uploads');
    // Events — real, DB-backed (club_events).
    Route::get('/events', [App\Http\Controllers\PersonalEventController::class, 'index'])->name('events');
    Route::get('/events/create', [App\Http\Controllers\PersonalEventController::class, 'create'])->name('events.create');
    Route::post('/events', [App\Http\Controllers\PersonalEventController::class, 'store'])->name('events.store')->middleware('throttle:member-write');
    Route::get('/events/{event:uuid}', [App\Http\Controllers\PersonalEventController::class, 'show'])->name('events.show');
    // The organiser's console. `show` is what a visitor came to read; this is
    // what the people running the event came to do. Organiser or an appointed
    // official only — the action refuses everyone else.
    Route::get('/events/{event:uuid}/manage', [App\Http\Controllers\PersonalEventController::class, 'manage'])->name('events.manage');
    Route::get('/events/{event:uuid}/edit', [App\Http\Controllers\PersonalEventController::class, 'edit'])->name('events.edit');
    Route::put('/events/{event:uuid}', [App\Http\Controllers\PersonalEventController::class, 'update'])->name('events.update')->middleware('throttle:member-write');
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

    // Officials' desk: weigh-ins and payment checks, on a screen of its own.
    // "Who's joined" (events.people) is the reading surface and carries no
    // controls for anyone. Each action authorises against its own role — a
    // weigh-in official cannot approve money.
    Route::get('/events/{event:uuid}/verify', [App\Http\Controllers\PersonalEventController::class, 'verify'])->name('events.verify');
    // A face for a competitor, for the introduction screen. The organiser's own
    // photo, on the ENTRY rather than on the person — see the controller.
    Route::post('/events/{event:uuid}/competitors/{registration}/photo', [App\Http\Controllers\PersonalEventController::class, 'competitorPhoto'])
        ->name('events.competitors.photo')->middleware('throttle:uploads');
    Route::delete('/events/{event:uuid}/competitors/{registration}/photo', [App\Http\Controllers\PersonalEventController::class, 'competitorPhotoDestroy'])
        ->name('events.competitors.photo.destroy')->middleware('throttle:member-write');
    Route::put('/events/{event:uuid}/verify/{registration}/weigh-in', [App\Http\Controllers\PersonalEventController::class, 'verifyWeighIn'])->name('events.verify.weigh-in')->middleware('throttle:member-write');
    Route::put('/events/{event:uuid}/verify/{registration}/payment', [App\Http\Controllers\PersonalEventController::class, 'verifyPayment'])->name('events.verify.payment')->middleware('throttle:member-write');
    Route::get('/events/{event:uuid}/verify/{registration}/proof', [App\Http\Controllers\PersonalEventController::class, 'verifyProof'])->name('events.verify.proof');

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
    Route::get('/events/{event:uuid}/court/{court}/preview', [\App\Events\Sports\Taekwondo\Tournament\CourtDisplay\CourtDisplayController::class, 'preview'])->name('events.court-display.preview')->where('court', '[^/]{1,40}')->middleware('throttle:60,1');
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
    Route::get('/events/{event:uuid}/next-up', [App\Http\Controllers\PersonalEventController::class, 'nextUp'])->name('events.next-up');
    // A sparring session as JSON, for its console to re-read after a nudge —
    // one coach queues a bout and every other console follows without a reload.
    Route::get('/events/{event:uuid}/sparring', [\App\Events\Sparring\SparringLauncherController::class, 'state'])->name('events.sparring')->middleware('throttle:120,1');
    // Throttled: it takes a search term, and a searchable endpoint is one
    // someone will try to hammer.
    Route::get('/events/{event:uuid}/entry-roster', [App\Http\Controllers\PersonalEventController::class, 'entryRoster'])->name('events.entry-roster')->middleware('throttle:60,1');
    Route::post('/events/{event:uuid}/entries', [App\Http\Controllers\PersonalEventController::class, 'storeEntries'])->name('events.entries')->middleware('throttle:admin-write');
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
    Route::get('/market', [App\Http\Controllers\PersonalMobileController::class, 'market'])->name('market');
    Route::get('/market/{product}', [App\Http\Controllers\PersonalMobileController::class, 'marketShow'])->name('market.show')->whereNumber('product');
    // Shop orders (member side): place an order + see my orders.
    Route::get('/orders', [App\Http\Controllers\OrderController::class, 'index'])->name('orders');
    Route::post('/orders', [App\Http\Controllers\OrderController::class, 'store'])->name('orders.store')->middleware('throttle:member-write');
    Route::post('/orders/{order}/receive', [App\Http\Controllers\OrderController::class, 'receive'])->name('orders.receive')->middleware('throttle:member-write');
    // Challenges & 1v1 duels (real, DB-backed).
    Route::get('/challenge', [App\Http\Controllers\ChallengeController::class, 'index'])->name('challenge');
    Route::get('/challenge/create', [App\Http\Controllers\ChallengeController::class, 'create'])->name('challenge.create');
    Route::post('/challenge/duels', [App\Http\Controllers\ChallengeController::class, 'store'])->name('challenge.store')->middleware('throttle:member-write');
    Route::get('/challenge/history', [App\Http\Controllers\ChallengeController::class, 'history'])->name('challenge.history');
    Route::get('/challenge/duel/{duel}', [App\Http\Controllers\ChallengeController::class, 'duel'])->name('challenge.duel')->whereNumber('duel');
    Route::post('/challenge/duel/{duel}/accept', [App\Http\Controllers\ChallengeController::class, 'accept'])->name('challenge.duel.accept')->middleware('throttle:member-write');
    Route::post('/challenge/duel/{duel}/decline', [App\Http\Controllers\ChallengeController::class, 'decline'])->name('challenge.duel.decline')->middleware('throttle:member-write');
    Route::post('/challenge/duel/{duel}/cancel', [App\Http\Controllers\ChallengeController::class, 'cancel'])->name('challenge.duel.cancel')->middleware('throttle:member-write');
    Route::post('/challenge/duel/{duel}/report', [App\Http\Controllers\ChallengeController::class, 'report'])->name('challenge.duel.report')->middleware('throttle:member-write');
    Route::post('/challenge/duel/{duel}/confirm', [App\Http\Controllers\ChallengeController::class, 'confirm'])->name('challenge.duel.confirm')->middleware('throttle:member-write');
    Route::post('/challenge/duel/{duel}/dispute', [App\Http\Controllers\ChallengeController::class, 'dispute'])->name('challenge.duel.dispute')->middleware('throttle:member-write');
    Route::put('/challenge/duel/{duel}', [App\Http\Controllers\ChallengeController::class, 'updateDuel'])->name('challenge.duel.update')->middleware('throttle:member-write');
    Route::delete('/challenge/duel/{duel}', [App\Http\Controllers\ChallengeController::class, 'destroyDuel'])->name('challenge.duel.destroy')->middleware('throttle:member-write');
    Route::post('/challenge/duel/{duel}/media', [App\Http\Controllers\ChallengeController::class, 'addMedia'])->name('challenge.duel.media.add')->middleware('throttle:uploads');
    Route::delete('/challenge/duel/{duel}/media/{media}', [App\Http\Controllers\ChallengeController::class, 'deleteMedia'])->name('challenge.duel.media.delete')->whereNumber('media')->middleware('throttle:member-write');
    Route::get('/challenge/duel/{duel}/witness-search', [App\Http\Controllers\ChallengeController::class, 'searchWitnesses'])->name('challenge.duel.witness.search')->middleware('throttle:60,1');
    Route::post('/challenge/duel/{duel}/witnesses', [App\Http\Controllers\ChallengeController::class, 'addWitness'])->name('challenge.duel.witness.add')->middleware('throttle:member-write');
    Route::patch('/challenge/duel/{duel}/witnesses/{witness}/respond', [App\Http\Controllers\ChallengeController::class, 'respondWitness'])->name('challenge.duel.witness.respond')->whereNumber('witness')->middleware('throttle:member-write');
    Route::patch('/challenge/duel/{duel}/witnesses/{witness}/feedback', [App\Http\Controllers\ChallengeController::class, 'witnessFeedback'])->name('challenge.duel.witness.feedback')->whereNumber('witness')->middleware('throttle:member-write');
    Route::delete('/challenge/duel/{duel}/witnesses/{witness}', [App\Http\Controllers\ChallengeController::class, 'deleteWitness'])->name('challenge.duel.witness.delete')->whereNumber('witness')->middleware('throttle:member-write');
    Route::get('/challenge/{challenge}', [App\Http\Controllers\ChallengeController::class, 'show'])->name('challenge.show')->whereNumber('challenge');
    Route::post('/challenge/{challenge}/join', [App\Http\Controllers\ChallengeController::class, 'join'])->name('challenge.join')->middleware('throttle:member-write');
    Route::post('/challenge/{challenge}/leave', [App\Http\Controllers\ChallengeController::class, 'leave'])->name('challenge.leave')->middleware('throttle:member-write');
    Route::post('/challenge/{challenge}/progress', [App\Http\Controllers\ChallengeController::class, 'progress'])->name('challenge.progress')->middleware('throttle:member-write');
    Route::get('/settings', [App\Http\Controllers\PersonalMobileController::class, 'settings'])->name('settings');

    // People discovery — search directory (public profile lives at /people/{uuid}).
    Route::get('/people', [App\Http\Controllers\PeopleController::class, 'index'])->name('people');
    Route::get('/people/search', [App\Http\Controllers\PeopleController::class, 'search'])->name('people.search');
    Route::put('/discoverable', [App\Http\Controllers\PersonalMobileController::class, 'updateDiscoverable'])->name('discoverable.update')->middleware('throttle:member-write');
    // Mark an app section/feed-tab as seen (clears its unseen red-dot).
    Route::post('/seen', [App\Http\Controllers\PersonalMobileController::class, 'markSectionSeen'])->name('seen');

    // Switch the active UI language (persists to user + session; client reloads).
    Route::put('/locale', [App\Http\Controllers\LocaleController::class, 'update'])->name('locale.update');
});

// Single-post permalink — unguessable token, members-only (auth-gated).
Route::middleware(['auth', 'verified', 'two-factor'])
    ->get('/p/{post:token}', [App\Http\Controllers\UserPostController::class, 'show'])
    ->name('posts.show');

// Member walls + social graph (follow / connect / block)
Route::middleware(['auth', 'verified', 'two-factor'])->prefix('u')->name('wall.')->group(function () {
    // Backward-compat: legacy /u/{id} links (old notifications/QRs) → member profile.
    Route::get('/{id}', function ($id) {
        $user = \App\Models\User::find($id);
        abort_unless($user, 404);

        return redirect()->route('member.show', $user->uuid);
    })->whereNumber('id')->name('legacy');

    // The social "wall" is retired: /u/{slug} now redirects to the member profile.
    Route::get('/{user:slug}', [App\Http\Controllers\WallController::class, 'show'])->name('show');

    Route::post('/{user:slug}/follow', [App\Http\Controllers\ConnectionController::class, 'follow'])->name('follow')->middleware('throttle:member-write');
    Route::delete('/{user:slug}/follow', [App\Http\Controllers\ConnectionController::class, 'unfollow'])->name('unfollow')->middleware('throttle:member-write');
    Route::post('/{user:slug}/block', [App\Http\Controllers\ConnectionController::class, 'block'])->name('block')->middleware('throttle:member-write');
    Route::delete('/{user:slug}/block', [App\Http\Controllers\ConnectionController::class, 'unblock'])->name('unblock')->middleware('throttle:member-write');
});

// Authentication routes
Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login')->middleware('no-store');
Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

// Passwordless "magic link" login — request a one-time signed link by email,
// then click it to sign in. The verify route is signature-protected.
Route::post('/login/link', [App\Http\Controllers\Auth\MagicLinkController::class, 'send'])
    ->name('login.magic')->middleware('throttle:login');
Route::get('/login/link/{user}', [App\Http\Controllers\Auth\MagicLinkController::class, 'login'])
    ->name('login.magic.verify')->middleware('signed', 'throttle:6,1');
// Public form renderer (surveys/intake via link or QR).
Route::get('/f/{form:uuid}', [App\Http\Controllers\FormController::class, 'show'])->name('forms.show');
Route::post('/f/{form:uuid}', [App\Http\Controllers\FormController::class, 'submit'])->name('forms.submit')->middleware('throttle:20,1');

Route::get('/register', [RegisteredUserController::class, 'create'])->name('register')->middleware('no-store');
Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:register');
Route::get('/register/wizard/packages', [App\Http\Controllers\Auth\WizardRegistrationController::class, 'packages'])->name('register.wizard.packages')->middleware('throttle:60,1');
Route::post('/register/wizard/upload-temp', [App\Http\Controllers\Auth\WizardRegistrationController::class, 'uploadTemp'])->name('register.wizard.upload')->middleware('throttle:uploads');
Route::post('/register/wizard/lookup', [App\Http\Controllers\Auth\WizardRegistrationController::class, 'lookup'])->name('register.wizard.lookup')->middleware('throttle:10,1');
Route::post('/register/wizard/verify-otp', [App\Http\Controllers\Auth\WizardRegistrationController::class, 'verifyOtp'])->name('register.wizard.verify')->middleware('throttle:15,1');
Route::post('/register/wizard/submit', [App\Http\Controllers\Auth\WizardRegistrationController::class, 'submit'])->name('register.wizard.submit')->middleware('throttle:register');
// Dedicated club-registration URL (distinct from platform /register). The 2-3
// letter country constraint keeps it from clashing with /register/wizard/*.
Route::get('/register/{country}/{slug}', [RegisteredUserController::class, 'createForClub'])
    ->name('register.club')->where('country', '[a-z]{2,3}')->middleware('no-store');

// Password reset routes
Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request')->middleware('no-store');
Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email')->middleware('throttle:password-reset');
Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset')->middleware('no-store');
Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.update')->middleware('throttle:password-reset');

// Email verification routes
Route::get('/email/verify', function (Request $request) {
    if ($request->user()->hasVerifiedEmail()) {
        $intended = session()->pull('url.intended', \App\Support\Landing::url($request));
        session()->forget('club.context');

        return redirect($intended);
    }

    return view('auth.verify-email');
})->middleware('auth')->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', function (Request $request, $id, $hash) {
    $user = \App\Models\User::findOrFail($id);

    if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
        abort(403, 'Invalid verification link.');
    }

    $intended = $request->query('intended') ?: session()->pull('url.intended', \App\Support\Landing::url($request));
    session()->forget('club.context');

    // Log the user in and set the 2FA session flag so all middleware passes.
    if (! auth()->check()) {
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('two_factor.verified', true);
    }

    if ($user->hasVerifiedEmail()) {
        return redirect($intended)->with('success', 'Your email is already verified.');
    }

    $user->markEmailAsVerified();

    // Redirect to the verify-email page with verified=true so the success
    // message shows in the same auth card the user is familiar with.
    // The page will auto-redirect them to the app after 3 seconds.
    return redirect()->route('verification.notice')
        ->with('verified', true)
        ->with('verified_intended', $intended);
})->middleware(['signed'])->name('verification.verify');

Route::post('/email/verification-notification', function (Request $request) {
    $request->user()->sendEmailVerificationNotification();

    return back()->with('resent', true);
})->middleware(['auth', 'throttle:verification'])->name('verification.send');

// Public resend — for users who are not yet logged in (e.g. walk-in registrations).
Route::post('/email/resend-verification', function (Request $request) {
    $request->validate(['email' => 'required|email']);
    $user = \App\Models\User::where('email', $request->email)->first();
    if ($user && ! $user->hasVerifiedEmail()) {
        $user->sendEmailVerificationNotification();
    }

    // Always return the same message to avoid email enumeration.
    return redirect()->route('login')
        ->with('info', 'If that email exists and is unverified, a new verification link has been sent.');
})->middleware('throttle:verification')->name('verification.resend.public');

// Public club page - no login required (used for QR code)
Route::get('/mobile/{country}/{slug}', [PlatformController::class, 'showPublic'])->name('clubs.show.public');

/*
 * The club page, open to anyone.
 *
 * Not a new exposure: showPublic() above already serves the SAME page from the
 * SAME controller to guests at /mobile/{country}/{slug}. This makes the
 * canonical URL behave the same way, so a link shared from the video platform
 * (VIDEO-INTEGRATION.md 6.6) does not dead-end on a login screen.
 *
 * Everything that CHANGES anything — join, leave, collect a perk — stays behind
 * auth in the group further down. A guest reads the page and nothing more, and
 * the top bar is hidden for them (layouts/app.blade.php).
 */
Route::prefix('{country}')->where(['country' => '[a-z]{2,3}'])->group(function () {
    Route::get('/clubs/{slug}', [PlatformController::class, 'show'])
        ->name('clubs.show')->middleware('throttle:60,1');
});

/*
 * The safe public profile, open to anyone.
 *
 * Deliberately narrower for a guest than for a member: a signed-in viewer may
 * see any profile they are not blocked by, but an ANONYMOUS visitor only sees a
 * member who is discoverable and is not a minor. Discoverability is the member's
 * own opt-in to being found, and a minor's name, photo and club do not go to the
 * open internet on the strength of a shared link. Anything else 404s — the same
 * answer a non-existent uuid gets, so the difference discloses nothing.
 */
Route::get('/people/{uuid}', [App\Http\Controllers\PeopleController::class, 'show'])
    ->name('people.show')->middleware('throttle:60,1');

// Public trainer page - no login required (used for QR code)
Route::get('/t/{user}', [TrainerController::class, 'showPublic'])->name('trainer.show.public');

// Printable QR posters (club page, club registration, member profile, event).
Route::middleware(['auth', 'verified', 'two-factor'])->prefix('qr')->name('qr.')->group(function () {
    Route::get('/club/{club}/page', [App\Http\Controllers\QrController::class, 'clubPoster'])->name('club.page');
    Route::get('/club/{club}/register', [App\Http\Controllers\QrController::class, 'clubRegisterPoster'])->name('club.register');
    Route::get('/member/{user}', [App\Http\Controllers\QrController::class, 'memberPoster'])->name('member');
    Route::get('/member/{user}/svg', [App\Http\Controllers\QrController::class, 'memberSvg'])->name('member.svg');
    Route::get('/event/{event:uuid}', [App\Http\Controllers\QrController::class, 'eventPoster'])->name('event');
});

// Explore routes (accessible to authenticated users)
Route::middleware(['auth', 'two-factor'])->group(function () {
    Route::get('/explore', [PlatformController::class, 'index'])->name('clubs.explore');

    // Stop impersonating — available to the (impersonated) session, restores the admin.
    Route::post('/impersonate/leave', [App\Http\Controllers\ImpersonationController::class, 'stop'])->name('impersonate.leave');
    Route::get('/clubs/nearby', [PlatformController::class, 'nearby'])->name('clubs.nearby');
    Route::get('/clubs/all', [PlatformController::class, 'all'])->name('clubs.all');
    // Explore → Events tab: open events only (not started + running now).
    Route::get('/explore/events', [PlatformController::class, 'events'])->name('explore.events')->middleware('throttle:60,1');
    Route::get('/trainer/{user}', [TrainerController::class, 'show'])->name('trainer.show');

    // Country-prefixed club routes
    // NOTE: clubs.show itself is PUBLIC and defined outside this group (see
    // near clubs.show.public). Everything below still requires a signed-in
    // user — joining, leaving, collecting a perk.
    Route::prefix('{country}')->where(['country' => '[a-z]{2,3}'])->group(function () {
        Route::get('/clubs/{slug}/packages-json', [PlatformController::class, 'clubPackages'])->name('clubs.packages.json');
        Route::post('/clubs/join', [PlatformController::class, 'joinClub'])->name('clubs.join')->middleware('verified', 'throttle:join-club');
        Route::post('/clubs/{slug}/events/{event}/join', [PlatformController::class, 'joinEvent'])->name('clubs.events.join')->middleware('verified', 'throttle:join-club');
        Route::delete('/clubs/{slug}/events/{event}/leave', [PlatformController::class, 'leaveEvent'])->name('clubs.events.leave')->middleware('verified', 'throttle:social');
        Route::post('/clubs/{slug}/perks/{perk}/collect', [PlatformController::class, 'collectPerk'])->name('clubs.perks.collect')->middleware('verified', 'throttle:social');
        Route::post('/clubs/{slug}/timeline/{post}/like', [PlatformController::class, 'toggleLike'])->name('clubs.timeline.like')->middleware('verified', 'throttle:social');
        Route::post('/clubs/{slug}/timeline/{post}/comments', [PlatformController::class, 'addComment'])->name('clubs.timeline.comment')->middleware('verified', 'throttle:social');
        Route::delete('/clubs/{slug}/timeline/{post}/comments/{comment}', [PlatformController::class, 'deleteComment'])->name('clubs.timeline.comment.delete')->middleware('verified', 'throttle:social');
    });
});

// Platform Admin routes (Super Admin only)
// "Write with AI" for rich-text fields — available to any authenticated user
// (generation only; no data access or writes).
Route::post('/ai/compose', [App\Http\Controllers\AiComposeController::class, 'compose'])
    ->middleware(['auth', 'verified', 'throttle:copilot'])
    ->name('ai.compose');

// TEST/LAB — activity video-section design exploration (mobile). Static mock,
// no data access. Defined BEFORE the {activity:uuid} route so the literal path
// isn't captured by uuid binding. Remove before go-live.
Route::get('/lab/activity-video', function () {
    $activity = \App\Models\ActivityCatalog::where('uuid', '7c4afd25-1a69-48c1-8ba2-c1b136af0cea')->first();

    return view('lab.activity-video', ['activity' => $activity]);
})->middleware('throttle:60,1')->name('lab.activity-video');

// PUBLIC viewer for a global-directory activity — rich, shareable content page
// (QR-linked). No auth: general sport knowledge, no tenant/personal data. Bound
// by a non-guessable uuid and throttled to blunt scraping/enumeration; guests
// are prompted to sign in on the page itself.
Route::get('/activity/{activity:uuid}', [App\Http\Controllers\ActivityCatalogController::class, 'show'])
    ->middleware('throttle:120,1')
    ->name('activity.show');

Route::middleware(['auth', 'verified', 'two-factor', 'role:super-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [App\Http\Controllers\Admin\PlatformController::class, 'home'])->name('platform.index');

    // Start impersonating a user (super-admin only — enforced by group middleware).
    Route::post('/impersonate/{user}', [App\Http\Controllers\ImpersonationController::class, 'start'])->name('impersonate.start')->middleware('throttle:admin-write');

    // All Clubs Management
    Route::get('/clubs', [App\Http\Controllers\Admin\PlatformController::class, 'clubs'])->name('platform.clubs');
    Route::get('/clubs/create', [App\Http\Controllers\Admin\PlatformController::class, 'createClub'])->name('platform.clubs.create');
    Route::post('/clubs', [App\Http\Controllers\Admin\ClubApiController::class, 'store'])->name('platform.clubs.store')->middleware('throttle:admin-write');
    Route::get('/clubs/{club}/edit', [App\Http\Controllers\Admin\PlatformController::class, 'editClub'])->name('platform.clubs.edit');
    Route::put('/clubs/{club}', [App\Http\Controllers\Admin\ClubApiController::class, 'update'])->name('platform.clubs.update')->middleware('throttle:admin-write');
    Route::delete('/clubs/{club}', [App\Http\Controllers\Admin\PlatformController::class, 'destroyClub'])->name('platform.clubs.destroy')->middleware('throttle:admin-write');
    Route::post('/clubs/{club}/upload-logo', [App\Http\Controllers\Admin\PlatformController::class, 'uploadClubLogo'])->name('platform.clubs.upload-logo')->middleware('throttle:uploads');
    Route::post('/clubs/{club}/upload-cover', [App\Http\Controllers\Admin\PlatformController::class, 'uploadClubCover'])->name('platform.clubs.upload-cover')->middleware('throttle:uploads');

    // Global activity directory (shared catalog reused across clubs)
    Route::get('/activities', [App\Http\Controllers\Admin\PlatformActivityController::class, 'index'])->name('platform.activities');
    Route::post('/activities', [App\Http\Controllers\Admin\PlatformActivityController::class, 'store'])->name('platform.activities.store')->middleware('throttle:admin-write');
    Route::post('/activities/generate-content', [App\Http\Controllers\Admin\PlatformActivityController::class, 'generateContent'])->name('platform.activities.generate')->middleware('throttle:copilot');
    Route::post('/activities/verify-video', [App\Http\Controllers\Admin\PlatformActivityController::class, 'verifyVideo'])->name('platform.activities.verify-video')->middleware('throttle:copilot');
    Route::put('/activities/{activity:uuid}', [App\Http\Controllers\Admin\PlatformActivityController::class, 'update'])->name('platform.activities.update')->middleware('throttle:admin-write');
    Route::post('/activities/{activity:uuid}/image', [App\Http\Controllers\Admin\PlatformActivityController::class, 'generateImage'])->name('platform.activities.image')->middleware('throttle:admin-write');
    Route::post('/activities/upload-image', [App\Http\Controllers\Admin\PlatformActivityController::class, 'uploadImageStore'])->name('platform.activities.upload-image')->middleware('throttle:uploads');
    Route::post('/activities/{activity:uuid}/set-image', [App\Http\Controllers\Admin\PlatformActivityController::class, 'setImage'])->name('platform.activities.set-image')->middleware('throttle:admin-write');
    Route::delete('/activities/{activity:uuid}', [App\Http\Controllers\Admin\PlatformActivityController::class, 'destroy'])->name('platform.activities.destroy')->middleware('throttle:admin-write');

    // AI provider settings (text / voice / image — local or cloud)
    Route::get('/ai', [App\Http\Controllers\Admin\AiProviderController::class, 'index'])->name('ai.index');
    Route::post('/ai/providers', [App\Http\Controllers\Admin\AiProviderController::class, 'store'])->name('ai.store')->middleware('throttle:admin-write');
    Route::put('/ai/providers/{provider}', [App\Http\Controllers\Admin\AiProviderController::class, 'update'])->name('ai.update')->middleware('throttle:admin-write');
    Route::delete('/ai/providers/{provider}', [App\Http\Controllers\Admin\AiProviderController::class, 'destroy'])->name('ai.destroy')->middleware('throttle:admin-write');
    Route::post('/ai/providers/{provider}/test', [App\Http\Controllers\Admin\AiProviderController::class, 'test'])->name('ai.test')->middleware('throttle:admin-write');

    // Copilot ("Coach") — page-aware AI assistant (thin slice: create a club)
    Route::post('/copilot/message', [App\Http\Controllers\Admin\CopilotController::class, 'message'])->name('copilot.message')->middleware('throttle:copilot');
    Route::post('/copilot/apply', [App\Http\Controllers\Admin\CopilotController::class, 'apply'])->name('copilot.apply')->middleware('throttle:copilot');
    Route::post('/copilot/stt', [App\Http\Controllers\Admin\CopilotController::class, 'stt'])->name('copilot.stt')->middleware('throttle:copilot');
    Route::post('/copilot/tts', [App\Http\Controllers\Admin\CopilotController::class, 'tts'])->name('copilot.tts')->middleware('throttle:copilot');

    // Club API endpoints for modal
    Route::get('/api/users', [App\Http\Controllers\Admin\ClubApiController::class, 'getUsers']);
    Route::get('/api/clubs/{id}', [App\Http\Controllers\Admin\ClubApiController::class, 'getClub']);
    Route::post('/api/clubs/check-slug', [App\Http\Controllers\Admin\ClubApiController::class, 'checkSlug'])->middleware('throttle:admin-write');

    // All Members Management
    Route::get('/members', [App\Http\Controllers\Admin\PlatformController::class, 'members'])->name('platform.members');
    Route::post('/members', [App\Http\Controllers\Admin\PlatformController::class, 'storeMember'])->name('platform.members.store')->middleware('throttle:admin-write');
    Route::get('/members/{id}', [App\Http\Controllers\Admin\PlatformController::class, 'showMember'])->name('platform.members.show');
    Route::get('/members/{id}/edit', [App\Http\Controllers\Admin\PlatformController::class, 'editMember'])->name('platform.members.edit');
    Route::put('/members/{id}', [App\Http\Controllers\Admin\PlatformController::class, 'updateMember'])->name('platform.members.update')->middleware('throttle:admin-write');
    Route::delete('/members/{id}', [App\Http\Controllers\Admin\PlatformController::class, 'destroyMember'])->name('platform.members.destroy')->middleware('throttle:admin-write');
    Route::post('/members/{id}/upload-picture', [App\Http\Controllers\Admin\PlatformController::class, 'uploadMemberPicture'])->name('platform.members.upload-picture')->middleware('throttle:uploads');
    Route::delete('/members/{id}/profile-picture', [App\Http\Controllers\Admin\PlatformController::class, 'removeMemberPicture'])->name('platform.members.remove-picture')->middleware('throttle:admin-write');
    Route::put('/members/{id}/profile-picture/visibility', [App\Http\Controllers\Admin\PlatformController::class, 'updateMemberPictureVisibility'])->name('platform.members.picture-visibility')->middleware('throttle:admin-write');
    Route::post('/members/{id}/health', [App\Http\Controllers\Admin\PlatformController::class, 'storeMemberHealth'])->name('platform.members.store-health')->middleware('throttle:admin-write');
    Route::put('/members/{id}/health/{recordId}', [App\Http\Controllers\Admin\PlatformController::class, 'updateMemberHealth'])->name('platform.members.update-health')->middleware('throttle:admin-write');
    Route::post('/members/{id}/tournament', [App\Http\Controllers\Admin\PlatformController::class, 'storeMemberTournament'])->name('platform.members.store-tournament')->middleware('throttle:admin-write');
    Route::get('/members/{user}/popup', [App\Http\Controllers\Admin\PlatformController::class, 'memberPopup'])->name('platform.members.popup');
    Route::get('/members/{user}/enroll-data', [App\Http\Controllers\Admin\PlatformController::class, 'memberEnrollData'])->name('platform.members.enroll-data');
    Route::post('/members/{user}/verify-email', [App\Http\Controllers\Admin\PlatformController::class, 'verifyMemberEmail'])->name('platform.members.verify-email')->middleware('throttle:admin-write');

    // Database Backup & Restore
    // The error log. Read-only, super-admin only (inherited from this group), and
    // throttled: it reads a file that grows on the worst day of the year.
    Route::get('/logs', [App\Http\Controllers\Admin\PlatformController::class, 'logs'])
        ->middleware('throttle:60,1')->name('platform.logs');
    Route::get('/settings', [App\Http\Controllers\Admin\PlatformController::class, 'settings'])->name('platform.settings');
    Route::put('/settings', [App\Http\Controllers\Admin\PlatformController::class, 'updateSettings'])->name('platform.settings.update')->middleware('throttle:admin-write');
    Route::put('/settings/whatsapp', [App\Http\Controllers\Admin\PlatformController::class, 'updateWhatsAppSettings'])->name('platform.settings.whatsapp.update')->middleware('throttle:admin-write');
    Route::post('/settings/whatsapp/test', [App\Http\Controllers\Admin\PlatformController::class, 'testWhatsAppConnection'])->name('platform.settings.whatsapp.test')->middleware('throttle:admin-write');
    Route::post('/settings/whatsapp/send-test', [App\Http\Controllers\Admin\PlatformController::class, 'sendTestWhatsAppMessage'])->name('platform.settings.whatsapp.send-test')->middleware('throttle:admin-write');

    // DANGER ZONE — wipe the platform back to its clean baseline (super-admin only, very tightly throttled).
    Route::post('/settings/reset-baseline', [App\Http\Controllers\Admin\PlatformController::class, 'resetBaseline'])->name('platform.settings.reset-baseline')->middleware('throttle:reset-baseline');

    Route::get('/backup', [App\Http\Controllers\Admin\PlatformController::class, 'backup'])->name('platform.backup');
    Route::get('/backup/download', [App\Http\Controllers\Admin\PlatformController::class, 'downloadBackup'])->name('platform.backup.download');
    Route::post('/backup/restore', [App\Http\Controllers\Admin\PlatformController::class, 'restoreBackup'])->name('platform.backup.restore')->middleware('throttle:backup');
    Route::get('/backup/export-users', [App\Http\Controllers\Admin\PlatformController::class, 'exportAuthUsers'])->name('platform.backup.export-users');

    // Audit Log
    Route::get('/audit-log', [App\Http\Controllers\Admin\PlatformController::class, 'auditLog'])->name('platform.audit-log');

    // Business (Chain) approvals
    Route::get('/businesses', [App\Http\Controllers\Admin\BusinessApprovalController::class, 'index'])->name('platform.businesses');
    Route::post('/businesses/{business}/approve', [App\Http\Controllers\Admin\BusinessApprovalController::class, 'approve'])->name('platform.businesses.approve')->middleware('throttle:admin-write');
    Route::post('/businesses/{business}/reject', [App\Http\Controllers\Admin\BusinessApprovalController::class, 'reject'])->name('platform.businesses.reject')->middleware('throttle:admin-write');
    Route::put('/businesses/{business}', [App\Http\Controllers\Admin\BusinessApprovalController::class, 'update'])->name('platform.businesses.update')->middleware('throttle:admin-write');
    Route::get('/businesses/{business}/history', [App\Http\Controllers\Admin\BusinessApprovalController::class, 'history'])->name('platform.businesses.history');
    Route::get('/businesses/{business}/clubs', [App\Http\Controllers\Admin\BusinessApprovalController::class, 'clubs'])->name('platform.businesses.clubs');
    Route::post('/businesses/{business}/clubs/attach', [App\Http\Controllers\Admin\BusinessApprovalController::class, 'attachClub'])->name('platform.businesses.clubs.attach')->middleware('throttle:admin-write');
    Route::post('/businesses/{business}/clubs/detach', [App\Http\Controllers\Admin\BusinessApprovalController::class, 'detachClub'])->name('platform.businesses.clubs.detach')->middleware('throttle:admin-write');
    Route::post('/businesses/{business}/transfer-owner', [App\Http\Controllers\Admin\BusinessApprovalController::class, 'transferOwner'])->name('platform.businesses.transfer-owner')->middleware('throttle:admin-write');
    Route::delete('/businesses/{business}', [App\Http\Controllers\Admin\BusinessApprovalController::class, 'destroy'])->name('platform.businesses.destroy')->middleware('throttle:admin-write');
});

// Club Admin routes (Club owners and admins)
Route::middleware(['auth', 'verified', 'two-factor', 'tenant', 'throttle:admin-write'])->prefix('admin/club/{club}')->name('admin.club.')->group(function () {
    // Dashboard & club details
    // Bare club-admin root → dashboard, so /admin/club/{club} never dead-ends on a 405
    // (only PUT/DELETE live at `/`). Auth/tenant scope still enforced by the group middleware.
    Route::get('/', fn ($club) => redirect()->route('admin.club.dashboard', $club))->name('home');
    Route::get('/dashboard', [App\Http\Controllers\Admin\ClubAdminController::class, 'dashboard'])->name('dashboard');
    // Shop — club store: products held in stock or dropshipped.
    Route::get('/shop', [App\Http\Controllers\Admin\ClubShopController::class, 'shop'])->name('shop');
    Route::post('/shop/products', [App\Http\Controllers\Admin\ClubShopController::class, 'storeProduct'])->name('shop.products.store');
    Route::put('/shop/products/{product}', [App\Http\Controllers\Admin\ClubShopController::class, 'updateProduct'])->name('shop.products.update');
    Route::delete('/shop/products/{product}', [App\Http\Controllers\Admin\ClubShopController::class, 'destroyProduct'])->name('shop.products.destroy');
    Route::post('/shop/products/{product}/stock-mute', [App\Http\Controllers\Admin\ClubShopController::class, 'muteStockAlert'])->name('shop.products.stock-mute');
    Route::post('/shop/categories', [App\Http\Controllers\Admin\ClubShopController::class, 'storeCategory'])->name('shop.categories.store');
    Route::put('/shop/categories/{category}', [App\Http\Controllers\Admin\ClubShopController::class, 'updateCategory'])->name('shop.categories.update');
    Route::delete('/shop/categories/{category}', [App\Http\Controllers\Admin\ClubShopController::class, 'destroyCategory'])->name('shop.categories.destroy');
    // Incoming shop orders the club fulfils.
    Route::get('/orders', [App\Http\Controllers\Admin\ClubOrderController::class, 'index'])->name('orders');
    Route::patch('/orders/{order}/status', [App\Http\Controllers\Admin\ClubOrderController::class, 'updateStatus'])->name('orders.status');
    Route::get('/details', [App\Http\Controllers\Admin\ClubAdminController::class, 'details'])->name('details');
    Route::put('/', [App\Http\Controllers\Admin\ClubAdminController::class, 'update'])->name('update');
    Route::put('/settings/whatsapp', [App\Http\Controllers\Admin\ClubAdminController::class, 'updateWhatsAppSettings'])->name('settings.whatsapp.update');
    Route::post('/settings/whatsapp/test', [App\Http\Controllers\Admin\ClubAdminController::class, 'testWhatsAppConnection'])->name('settings.whatsapp.test');
    Route::post('/settings/whatsapp/send-test', [App\Http\Controllers\Admin\ClubAdminController::class, 'sendTestWhatsAppMessage'])->name('settings.whatsapp.send-test');
    Route::delete('/', [App\Http\Controllers\Admin\ClubAdminController::class, 'destroy'])->name('destroy');
    Route::post('/social-links', [App\Http\Controllers\Admin\ClubAdminController::class, 'storeSocialLink'])->name('social-links.store');
    Route::delete('/social-links/{link}', [App\Http\Controllers\Admin\ClubAdminController::class, 'destroySocialLink'])->name('social-links.destroy');
    Route::post('/transfer-ownership', [App\Http\Controllers\Admin\ClubAdminController::class, 'transferOwnership'])->name('transfer-ownership')->middleware('throttle:admin-write');
    Route::post('/create-owner', [App\Http\Controllers\Admin\ClubAdminController::class, 'createOwner'])->name('create-owner')->middleware('throttle:admin-write');

    // Gallery
    Route::get('/gallery', [App\Http\Controllers\Admin\ClubGalleryController::class, 'gallery'])->name('gallery');
    Route::post('/gallery/upload', [App\Http\Controllers\Admin\ClubGalleryController::class, 'uploadGallery'])->name('gallery.upload')->middleware('throttle:uploads');
    Route::post('/gallery/reorder', [App\Http\Controllers\Admin\ClubGalleryController::class, 'reorderGallery'])->name('gallery.reorder');
    Route::post('/gallery/youtube', [App\Http\Controllers\Admin\ClubGalleryController::class, 'saveYoutubeUrl'])->name('gallery.youtube');
    Route::delete('/gallery/{image}', [App\Http\Controllers\Admin\ClubGalleryController::class, 'destroyGalleryImage'])->name('gallery.destroy');

    // Facilities
    Route::get('/facilities', [App\Http\Controllers\Admin\ClubFacilityController::class, 'facilities'])->name('facilities');
    Route::post('/facilities', [App\Http\Controllers\Admin\ClubFacilityController::class, 'storeFacility'])->name('facilities.store');
    Route::get('/facilities/{facility}', [App\Http\Controllers\Admin\ClubFacilityController::class, 'getFacility'])->name('facilities.show');
    Route::put('/facilities/{facility}', [App\Http\Controllers\Admin\ClubFacilityController::class, 'updateFacility'])->name('facilities.update');
    Route::delete('/facilities/{facility}', [App\Http\Controllers\Admin\ClubFacilityController::class, 'destroyFacility'])->name('facilities.destroy');
    Route::post('/facilities/{facility}/toggle', [App\Http\Controllers\Admin\ClubFacilityController::class, 'toggleFacility'])->name('facilities.toggle');
    Route::post('/facilities/{facility}/upload-image', [App\Http\Controllers\Admin\ClubFacilityController::class, 'uploadFacilityImage'])->name('facilities.upload-image')->middleware('throttle:uploads');

    // Instructors
    Route::get('/instructors', [App\Http\Controllers\Admin\ClubInstructorController::class, 'instructors'])->name('instructors');
    Route::post('/instructors/reorder', [App\Http\Controllers\Admin\ClubInstructorController::class, 'reorderInstructors'])->name('instructors.reorder')->middleware('throttle:admin-write');
    Route::post('/instructors', [App\Http\Controllers\Admin\ClubInstructorController::class, 'storeInstructor'])->name('instructors.store');
    Route::post('/instructors/{instructor}/upload-photo', [App\Http\Controllers\Admin\ClubInstructorController::class, 'uploadInstructorPhoto'])->name('instructors.upload-photo')->middleware('throttle:uploads');
    Route::put('/instructors/{instructor}', [App\Http\Controllers\Admin\ClubInstructorController::class, 'updateInstructor'])->name('instructors.update');
    Route::delete('/instructors/{instructor}', [App\Http\Controllers\Admin\ClubInstructorController::class, 'destroyInstructor'])->name('instructors.destroy');
    Route::get('/instructors/{instructor}/termination-preview', [App\Http\Controllers\Admin\ClubInstructorController::class, 'terminationPreview'])->name('instructors.termination-preview');
    Route::get('/instructors-prefill/{user}', [App\Http\Controllers\Admin\ClubInstructorController::class, 'instructorPrefill'])->name('instructors.prefill')->middleware('throttle:60,1');

    // Activities
    Route::get('/activities', [App\Http\Controllers\Admin\ClubActivityController::class, 'activities'])->name('activities');
    Route::get('/activities/library', [App\Http\Controllers\Admin\ClubActivityController::class, 'activityLibrary'])->name('activities.library');
    Route::post('/activities', [App\Http\Controllers\Admin\ClubActivityController::class, 'storeActivity'])->name('activities.store');
    Route::put('/activities/{activity}', [App\Http\Controllers\Admin\ClubActivityController::class, 'updateActivity'])->name('activities.update');
    Route::delete('/activities/{activity}', [App\Http\Controllers\Admin\ClubActivityController::class, 'destroyActivity'])->name('activities.destroy');

    // Activity equipment catalog (gear required to practice the activity)
    Route::get('/activities/{activity}/equipment', [App\Http\Controllers\Admin\ClubActivityController::class, 'equipment'])->name('activities.equipment');
    Route::post('/activities/{activity}/equipment', [App\Http\Controllers\Admin\ClubActivityController::class, 'storeEquipment'])->name('activities.equipment.store')->middleware('throttle:admin-write');
    Route::put('/activities/{activity}/equipment/{equipment}', [App\Http\Controllers\Admin\ClubActivityController::class, 'updateEquipment'])->name('activities.equipment.update')->middleware('throttle:admin-write');
    Route::delete('/activities/{activity}/equipment/{equipment}', [App\Http\Controllers\Admin\ClubActivityController::class, 'destroyEquipment'])->name('activities.equipment.destroy')->middleware('throttle:admin-write');

    // Events
    Route::get('/events', [App\Http\Controllers\Admin\ClubEventController::class, 'events'])->name('events');
    // Sparring — the club's own scoreboard for training. Two routes and no
    // more: the launcher, and the one tap that opens a session. Everything
    // afterwards is the session's console under /me/events, because a session
    // IS an event (see App\Events\Sparring\Sparring).
    Route::get('/sparring', [\App\Events\Sparring\SparringLauncherController::class, 'index'])->name('sparring');
    Route::post('/sparring', [\App\Events\Sparring\SparringLauncherController::class, 'store'])->name('sparring.store')->middleware('throttle:admin-write');
    Route::post('/events', [App\Http\Controllers\Admin\ClubEventController::class, 'storeEvent'])->name('events.store');
    Route::put('/events/{event}', [App\Http\Controllers\Admin\ClubEventController::class, 'updateEvent'])->name('events.update');
    Route::delete('/events/{event}', [App\Http\Controllers\Admin\ClubEventController::class, 'destroyEvent'])->name('events.destroy');
    Route::patch('/events/{event}/archive', [App\Http\Controllers\Admin\ClubEventController::class, 'archiveEvent'])->name('events.archive');
    Route::get('/events/{event}/participants', [App\Http\Controllers\Admin\ClubEventController::class, 'participants'])->name('events.participants');
    Route::get('/events/{event}/participants/{registration}/proof', [App\Http\Controllers\Admin\ClubEventController::class, 'participantProof'])->name('events.participants.proof');
    Route::post('/events/{event}/participants/{registration}/paid', [App\Http\Controllers\Admin\ClubEventController::class, 'markParticipantPaid'])->name('events.participants.paid')->middleware('throttle:admin-write');
    Route::delete('/events/{event}/participants/{registration}', [App\Http\Controllers\Admin\ClubEventController::class, 'removeParticipant'])->name('events.participants.remove')->middleware('throttle:admin-write');

    // Timeline
    Route::get('/timeline', [App\Http\Controllers\Admin\ClubTimelineController::class, 'timeline'])->name('timeline');
    Route::post('/timeline', [App\Http\Controllers\Admin\ClubTimelineController::class, 'storeTimelinePost'])->name('timeline.store');
    Route::put('/timeline/{post}', [App\Http\Controllers\Admin\ClubTimelineController::class, 'updateTimelinePost'])->name('timeline.update');
    Route::delete('/timeline/{post}', [App\Http\Controllers\Admin\ClubTimelineController::class, 'destroyTimelinePost'])->name('timeline.destroy');

    // Perks
    Route::get('/perks', [App\Http\Controllers\Admin\ClubPerkController::class, 'perks'])->name('perks');
    Route::post('/perks', [App\Http\Controllers\Admin\ClubPerkController::class, 'storePerk'])->name('perks.store');
    Route::put('/perks/{perk}', [App\Http\Controllers\Admin\ClubPerkController::class, 'updatePerk'])->name('perks.update');
    Route::delete('/perks/{perk}', [App\Http\Controllers\Admin\ClubPerkController::class, 'destroyPerk'])->name('perks.destroy');

    // Achievements
    Route::get('/achievements', [App\Http\Controllers\Admin\ClubAchievementController::class, 'achievements'])->name('achievements');
    Route::post('/achievements', [App\Http\Controllers\Admin\ClubAchievementController::class, 'storeAchievement'])->name('achievements.store');
    Route::put('/achievements/{achievement}', [App\Http\Controllers\Admin\ClubAchievementController::class, 'updateAchievement'])->name('achievements.update');
    Route::delete('/achievements/{achievement}', [App\Http\Controllers\Admin\ClubAchievementController::class, 'destroyAchievement'])->name('achievements.destroy');
    // Member self-claimed achievement verification queue (club attests claims naming this club).
    Route::get('/achievements/verifications', [App\Http\Controllers\Admin\ClubAchievementController::class, 'verifications'])->name('achievements.verifications');
    Route::post('/achievements/verifications/{type}/{uuid}/confirm', [App\Http\Controllers\Admin\ClubAchievementController::class, 'confirmVerification'])->whereIn('type', ['achievement', 'skill', 'affiliation', 'work'])->name('achievements.verifications.confirm')->middleware('throttle:admin-write');
    Route::post('/achievements/verifications/{type}/{uuid}/reject', [App\Http\Controllers\Admin\ClubAchievementController::class, 'rejectVerification'])->whereIn('type', ['achievement', 'skill', 'affiliation', 'work'])->name('achievements.verifications.reject')->middleware('throttle:admin-write');

    // Packages
    Route::get('/packages', [App\Http\Controllers\Admin\ClubPackageController::class, 'packages'])->name('packages');
    Route::post('/packages', [App\Http\Controllers\Admin\ClubPackageController::class, 'storePackage'])->name('packages.store');
    Route::put('/packages/{package}', [App\Http\Controllers\Admin\ClubPackageController::class, 'updatePackage'])->name('packages.update');
    Route::delete('/packages/{package}', [App\Http\Controllers\Admin\ClubPackageController::class, 'destroyPackage'])->name('packages.destroy');

    // Members
    Route::get('/members', [App\Http\Controllers\Admin\ClubMemberAdminController::class, 'members'])->name('members');
    Route::post('/members', [App\Http\Controllers\Admin\ClubMemberAdminController::class, 'storeMember'])->name('members.store');
    Route::post('/members/walk-in', [App\Http\Controllers\Admin\ClubMemberAdminController::class, 'walkInRegistration'])->name('members.walk-in')->middleware('throttle:walk-in');
    Route::get('/members/search', [App\Http\Controllers\Admin\ClubMemberAdminController::class, 'searchUsers'])->name('members.search');
    Route::post('/members/resolve-qr', [App\Http\Controllers\Admin\ClubMemberAdminController::class, 'resolveQr'])->name('members.resolve-qr');
    Route::get('/members/cards', [App\Http\Controllers\Admin\ClubMemberAdminController::class, 'membersCards'])->name('members.cards');
    Route::get('/members/{user}/popup', [App\Http\Controllers\Admin\ClubMemberAdminController::class, 'memberPopup'])->name('members.popup');
    Route::get('/members/popup-demo', [App\Http\Controllers\Admin\ClubMemberAdminController::class, 'memberPopupDemo'])->name('members.popup-demo');
    Route::get('/members/{user}/enroll-packages', [App\Http\Controllers\Admin\ClubMemberAdminController::class, 'enrollPackages'])->name('members.enroll-packages');
    Route::post('/members/{user}/enroll', [App\Http\Controllers\Admin\ClubMemberAdminController::class, 'enrollMember'])->name('members.enroll');
    Route::post('/members/enroll-batch', [App\Http\Controllers\Admin\ClubMemberAdminController::class, 'enrollBatch'])->name('members.enroll-batch')->middleware('throttle:admin-write');
    Route::delete('/members/{user}/remove', [App\Http\Controllers\Admin\ClubMemberAdminController::class, 'removeMember'])->name('members.remove')->middleware('throttle:admin-write');
    Route::post('/members/{user}/verify-email', [App\Http\Controllers\Admin\ClubMemberAdminController::class, 'verifyMemberEmail'])->name('members.verify-email')->middleware('throttle:admin-write');
    Route::get('/members/import-template', [App\Http\Controllers\Admin\ClubMemberAdminController::class, 'importTemplate'])->name('members.import-template');
    Route::post('/members/import', [App\Http\Controllers\Admin\ClubMemberAdminController::class, 'importMembers'])->name('members.import')->middleware('throttle:admin-write');
    Route::post('/subscriptions/{subscription}/approve-payment', [App\Http\Controllers\Admin\ClubMemberAdminController::class, 'approvePayment'])->name('subscriptions.approve-payment');
    Route::get('/subscriptions/{subscription}/payment-proof', [App\Http\Controllers\Admin\ClubMemberAdminController::class, 'servePaymentProof'])->name('subscriptions.payment-proof');
    Route::post('/subscriptions/{subscription}/refund', [App\Http\Controllers\Admin\ClubMemberAdminController::class, 'refundPayment'])->name('subscriptions.refund');
    Route::get('/subscriptions/{subscription}/refund-proof', [App\Http\Controllers\Admin\ClubMemberAdminController::class, 'serveRefundProof'])->name('subscriptions.refund-proof');

    // Roles
    Route::get('/roles', [App\Http\Controllers\Admin\ClubRoleController::class, 'roles'])->name('roles');
    Route::post('/roles', [App\Http\Controllers\Admin\ClubRoleController::class, 'storeRole'])->name('roles.store');
    Route::delete('/roles', [App\Http\Controllers\Admin\ClubRoleController::class, 'destroyRole'])->name('roles.destroy');
    // Per-member access: read current effective permissions + save a standard role or custom permission set.
    Route::get('/roles/member/{user}/permissions', [App\Http\Controllers\Admin\ClubRoleController::class, 'memberPermissions'])->name('roles.member.permissions');
    Route::post('/roles/member/permissions', [App\Http\Controllers\Admin\ClubRoleController::class, 'storeMemberPermissions'])->name('roles.member.permissions.store')->middleware('throttle:admin-write');
    Route::post('/roles/definitions', [App\Http\Controllers\Admin\ClubRoleController::class, 'createRole'])->name('roles.def.store');
    Route::put('/roles/definitions/{role}', [App\Http\Controllers\Admin\ClubRoleController::class, 'updateRole'])->name('roles.def.update');
    Route::delete('/roles/definitions/{role}', [App\Http\Controllers\Admin\ClubRoleController::class, 'deleteRole'])->name('roles.def.destroy');

    // Financials
    Route::get('/financials', [App\Http\Controllers\Admin\ClubFinancialController::class, 'financials'])->name('financials');
    Route::post('/financials/income', [App\Http\Controllers\Admin\ClubFinancialController::class, 'storeIncome'])->name('financials.income');
    Route::post('/financials/expense', [App\Http\Controllers\Admin\ClubFinancialController::class, 'storeExpense'])->name('financials.expense');
    Route::put('/financials/{transaction}', [App\Http\Controllers\Admin\ClubFinancialController::class, 'updateTransaction'])->name('financials.update');
    Route::delete('/financials/{transaction}', [App\Http\Controllers\Admin\ClubFinancialController::class, 'destroyTransaction'])->name('financials.destroy');
    Route::post('/financials/recurring', [App\Http\Controllers\Admin\ClubFinancialController::class, 'storeRecurringExpense'])->name('financials.recurring.store');
    Route::put('/financials/recurring/{recurringExpense}', [App\Http\Controllers\Admin\ClubFinancialController::class, 'updateRecurringExpense'])->name('financials.recurring.update');
    Route::delete('/financials/recurring/{recurringExpense}', [App\Http\Controllers\Admin\ClubFinancialController::class, 'destroyRecurringExpense'])->name('financials.recurring.destroy');
    Route::patch('/financials/recurring/{recurringExpense}/toggle', [App\Http\Controllers\Admin\ClubFinancialController::class, 'toggleRecurringExpense'])->name('financials.recurring.toggle');
    Route::get('/financials/test-data', [App\Http\Controllers\Admin\ClubFinancialController::class, 'testData'])->name('financials.test-data');
    Route::post('/financials/mode', [App\Http\Controllers\Admin\ClubFinancialController::class, 'switchMode'])->name('financials.mode')->middleware('throttle:admin-write');

    // Messages
    Route::get('/messages', [App\Http\Controllers\Admin\ClubMessageController::class, 'messages'])->name('messages');
    Route::get('/messages/thread/{user}', [App\Http\Controllers\Admin\ClubMessageController::class, 'conversation'])->name('messages.thread');
    Route::post('/messages/send', [App\Http\Controllers\Admin\ClubMessageController::class, 'sendMessage'])->name('messages.send')->middleware('throttle:admin-write');

    // Analytics
    Route::get('/analytics', [App\Http\Controllers\Admin\ClubAnalyticsController::class, 'analytics'])->name('analytics');

    // Notifications
    Route::get('/notifications', [App\Http\Controllers\Admin\ClubNotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications', [App\Http\Controllers\Admin\ClubNotificationController::class, 'store'])->name('notifications.store')->middleware('throttle:admin-write');
});

// Mark notification as read (global — not club-scoped)
Route::middleware(['auth', 'verified', 'two-factor'])->post('/notifications/mark-read', [App\Http\Controllers\Admin\ClubNotificationController::class, 'markRead'])->name('notifications.mark-read');
Route::middleware(['auth', 'verified', 'two-factor'])->delete('/notifications', [App\Http\Controllers\Admin\ClubNotificationController::class, 'clearAll'])->name('notifications.clear');

// Messenger — platform-wide direct messages (Facebook-style). Specific paths
// are registered before the {conversation} binding so they aren't swallowed.
Route::middleware(['auth', 'verified', 'two-factor'])->group(function () {
    Route::get('/messages', [App\Http\Controllers\MessengerController::class, 'index'])->name('messages.index');
    Route::get('/messages/conversations', [App\Http\Controllers\MessengerController::class, 'conversations'])->name('messages.conversations');
    Route::get('/messages/unread-count', [App\Http\Controllers\MessengerController::class, 'unreadCount'])->name('messages.unread');
    Route::get('/messages/search-users', [App\Http\Controllers\MessengerController::class, 'searchUsers'])->name('messages.search-users');
    Route::get('/messages/link-preview', [App\Http\Controllers\MessengerController::class, 'linkPreview'])->name('messages.link-preview')->middleware('throttle:60,1');
    Route::post('/messages/start/{user}', [App\Http\Controllers\MessengerController::class, 'start'])->name('messages.start')->middleware('throttle:member-write');
    Route::get('/messages/{conversation}', [App\Http\Controllers\MessengerController::class, 'show'])->name('messages.show');
    Route::get('/messages/{conversation}/thread', [App\Http\Controllers\MessengerController::class, 'thread'])->name('messages.thread');
    Route::post('/messages/{conversation}/send', [App\Http\Controllers\MessengerController::class, 'send'])->name('messages.send')->middleware('throttle:member-write');
    Route::patch('/messages/{conversation}/messages/{message}', [App\Http\Controllers\MessengerController::class, 'editMessage'])->name('messages.edit')->middleware('throttle:member-write');
    Route::delete('/messages/{conversation}/messages/{message}', [App\Http\Controllers\MessengerController::class, 'deleteMessage'])->name('messages.delete')->middleware('throttle:member-write');
    Route::post('/messages/{conversation}/messages/{message}/hide', [App\Http\Controllers\MessengerController::class, 'deleteMessageForMe'])->name('messages.hide')->middleware('throttle:member-write');
    Route::post('/messages/{conversation}/attachments', [App\Http\Controllers\MessengerController::class, 'uploadFile'])->name('messages.upload')->middleware('throttle:uploads');
    Route::get('/messages/{conversation}/attachments/{message}', [App\Http\Controllers\MessengerController::class, 'serveAttachment'])->name('messages.attachment');
    Route::post('/messages/{conversation}/read', [App\Http\Controllers\MessengerController::class, 'read'])->name('messages.read');
    Route::delete('/messages/{conversation}', [App\Http\Controllers\MessengerController::class, 'deleteConversation'])->name('messages.delete-conversation')->middleware('throttle:member-write');
});

// Unified Member routes
Route::middleware(['auth', 'verified', 'two-factor'])->group(function () {
    // Redirect old /profile route to /member/{uuid}
    Route::get('/profile', function () {
        return redirect()->route('member.show', Auth::user()->uuid);
    });

    // Redirect old routes to /family/members
    Route::get('/family', function () {
        return redirect()->route('members.index');
    });
    Route::get('/members', function () {
        return redirect()->route('members.index');
    });

    // Members listing (family dashboard)
    Route::get('/family/members', [MemberController::class, 'index'])->name('members.index');
    Route::get('/family/members/create', [MemberController::class, 'create'])->name('members.create');
    Route::post('/members', [MemberController::class, 'store'])->name('members.store')->middleware('throttle:member-write');

    // Individual member routes
    Route::get('/member/{uuid}', [MemberController::class, 'show'])->name('member.show');
    Route::get('/member/{id}/edit', [MemberController::class, 'edit'])->name('member.edit');
    Route::put('/member/{id}', [MemberController::class, 'update'])->name('member.update')->middleware('throttle:member-write');
    Route::delete('/member/{id}/confirm-delete', [MemberController::class, 'confirmDelete'])->name('member.confirm-delete');
    Route::delete('/member/{id}', [MemberController::class, 'destroy'])->name('member.destroy')->middleware('throttle:member-write');
    Route::post('/member/{id}/upload-picture', [MemberController::class, 'uploadPicture'])->name('member.upload-picture')->middleware('throttle:uploads');
    Route::delete('/member/{id}/profile-picture', [MemberController::class, 'removeProfilePicture'])->name('member.remove-picture')->middleware('throttle:member-write');
    Route::put('/member/{id}/profile-picture/visibility', [MemberController::class, 'updateProfilePictureVisibility'])->name('member.picture-visibility')->middleware('throttle:member-write');

    // A profile holds several pictures; {photo} is a uuid and is always resolved
    // inside {id}'s own photos, so another profile's uuid resolves to nothing.
    Route::post('/member/{id}/photos', [App\Http\Controllers\UserPhotoController::class, 'store'])->name('member.photos.store')->middleware('throttle:uploads');
    Route::put('/member/{id}/photos/{photo}/avatar', [App\Http\Controllers\UserPhotoController::class, 'setAvatar'])->name('member.photos.avatar')->middleware('throttle:member-write');
    Route::delete('/member/{id}/photos/{photo}', [App\Http\Controllers\UserPhotoController::class, 'destroy'])->name('member.photos.destroy')->middleware('throttle:member-write');
    Route::post('/member/{id}/upload-document', [MemberController::class, 'uploadDocument'])->name('member.upload-document')->middleware('throttle:uploads');
    Route::delete('/member/{id}/document', [MemberController::class, 'deleteDocument'])->name('member.delete-document')->middleware('throttle:member-write');
    Route::post('/member/{id}/reset-password', [MemberController::class, 'resetPassword'])->name('member.reset-password')->middleware('throttle:member-write');
    Route::post('/member/{id}/regenerate-password', [MemberController::class, 'regeneratePassword'])->name('member.regenerate-password')->middleware('throttle:member-write');
    Route::post('/member/{id}/health', [MemberController::class, 'storeHealth'])->name('member.store-health')->middleware('throttle:member-write');
    Route::put('/member/{id}/health/{recordId}', [MemberController::class, 'updateHealth'])->name('member.update-health')->middleware('throttle:member-write');
    Route::post('/member/{id}/tournament', [MemberController::class, 'storeTournament'])->name('member.store-tournament')->middleware('throttle:member-write');
    Route::put('/member/{id}/tournament/{uuid}', [MemberController::class, 'updateTournament'])->name('member.tournament.update')->middleware('throttle:member-write');
    Route::delete('/member/{id}/tournament/{uuid}', [MemberController::class, 'destroyTournament'])->name('member.tournament.destroy')->middleware('throttle:member-write');
    Route::post('/member/{id}/tournament/{uuid}/request-verification', [MemberController::class, 'requestTournamentVerification'])->name('member.tournament.request-verification')->middleware('throttle:member-write');
    Route::get('/member/{id}/tournament/{uuid}/evidence', [MemberController::class, 'tournamentEvidence'])->name('member.tournament.evidence');
    Route::post('/member/{id}/goal', [MemberController::class, 'storeGoal'])->name('member.store-goal')->middleware('throttle:member-write');
    Route::post('/member/{id}/attendance', [MemberController::class, 'storeAttendance'])->name('member.store-attendance')->middleware('throttle:member-write');
    Route::post('/member/{id}/event-log', [MemberController::class, 'storeMemberEvent'])->name('member.store-event')->middleware('throttle:member-write');
    Route::put('/member/goal/{goalId}', [MemberController::class, 'updateGoal'])->name('member.update-goal')->middleware('throttle:member-write');

    // Certifications (member-owned, self-managed)
    Route::post('/member/{id}/certification', [MemberController::class, 'storeCertification'])->name('member.store-certification')->middleware('throttle:member-write');
    Route::put('/member/certification/{certificationId}', [MemberController::class, 'updateCertification'])->name('member.update-certification')->whereNumber('certificationId')->middleware('throttle:member-write');
    Route::delete('/member/certification/{certificationId}', [MemberController::class, 'destroyCertification'])->name('member.destroy-certification')->whereNumber('certificationId')->middleware('throttle:member-write');

    // Work history (member-owned, self-managed)
    Route::post('/member/{id}/work-history', [MemberController::class, 'storeWorkHistory'])->name('member.store-work')->middleware('throttle:member-write');
    Route::put('/member/work-history/{workId}', [MemberController::class, 'updateWorkHistory'])->name('member.update-work')->whereNumber('workId')->middleware('throttle:member-write');
    Route::delete('/member/work-history/{workId}', [MemberController::class, 'destroyWorkHistory'])->name('member.destroy-work')->whereNumber('workId')->middleware('throttle:member-write');

    // Affiliation routes
    Route::post('/member/{id}/affiliations', [MemberController::class, 'storeAffiliation'])->name('member.store-affiliation')->middleware('throttle:member-write');
    Route::put('/member/{id}/affiliations/{affiliationId}', [MemberController::class, 'updateAffiliation'])->name('member.update-affiliation')->middleware('throttle:member-write');
    Route::delete('/member/{id}/affiliations/{affiliationId}', [MemberController::class, 'destroyAffiliation'])->name('member.destroy-affiliation')->middleware('throttle:member-write');
    Route::get('/member/{id}/affiliations/{affiliationId}/activities', [MemberController::class, 'affiliationActivities'])->name('member.affiliation-activities')->middleware('throttle:60,1');
    Route::get('/member/{id}/instructor-search', [MemberController::class, 'instructorSearch'])->name('member.instructor-search')->middleware('throttle:60,1');
    Route::post('/member/{id}/affiliations/{affiliationId}/instructors', [MemberController::class, 'storeAffiliationInstructor'])->name('member.store-affiliation-instructor')->middleware('throttle:member-write');
    Route::delete('/member/{id}/affiliations/{affiliationId}/instructors', [MemberController::class, 'destroyAffiliationInstructor'])->name('member.destroy-affiliation-instructor')->middleware('throttle:member-write');
    Route::post('/member/{id}/affiliations/{affiliationId}/skills', [MemberController::class, 'storeAffiliationSkill'])->name('member.store-affiliation-skill')->middleware('throttle:member-write');
    Route::post('/member/{id}/affiliations/{affiliationId}/skills/{uuid}/request-verification', [MemberController::class, 'requestSkillVerification'])->name('member.skill.request-verification')->middleware('throttle:member-write');
    Route::delete('/member/{id}/affiliations/{affiliationId}/skills/{skillId}', [MemberController::class, 'destroyAffiliationSkill'])->name('member.destroy-affiliation-skill')->middleware('throttle:member-write');
    Route::post('/member/{id}/affiliations/{affiliationId}/media/upload-image', [MemberController::class, 'uploadAffiliationMediaImage'])->name('member.affiliation-media.upload-image')->middleware('throttle:uploads');
    Route::post('/member/{id}/affiliations/{affiliationId}/media', [MemberController::class, 'storeAffiliationMedia'])->name('member.store-affiliation-media')->middleware('throttle:uploads');
    Route::delete('/member/{id}/affiliations/{affiliationId}/media/{mediaId}', [MemberController::class, 'destroyAffiliationMedia'])->name('member.destroy-affiliation-media')->middleware('throttle:member-write');
    Route::post('/member/{id}/affiliations/{uuid}/request-verification', [MemberController::class, 'requestAffiliationVerification'])->name('member.affiliation.request-verification')->middleware('throttle:member-write');
    Route::post('/member/{id}/work-history/{uuid}/request-verification', [MemberController::class, 'requestWorkVerification'])->name('member.work.request-verification')->middleware('throttle:member-write');

    // Keep old family routes for backward compatibility (redirect to new routes)
    Route::get('/family/create', function () {
        return redirect()->route('members.create');
    })->name('family.create');
    Route::post('/family', [FamilyController::class, 'store'])->name('family.store')->middleware('throttle:member-write');
    Route::post('/family/lookup', [FamilyController::class, 'lookup'])->name('family.lookup')->middleware('throttle:member-write');
    Route::get('/family/search-existing', [FamilyController::class, 'searchExisting'])->name('family.search-existing');
    Route::post('/family/link-existing', [FamilyController::class, 'linkExisting'])->name('family.link-existing')->middleware('throttle:member-write');
    Route::get('/family/{id}', function ($id) {
        $user = \App\Models\User::findOrFail($id);

        return redirect()->route('member.show', $user->uuid);
    })->name('family.show');
    Route::get('/family/{id}/edit', function ($id) {
        return redirect()->route('member.edit', $id);
    })->name('family.edit');
    Route::put('/family/{id}', [MemberController::class, 'update'])->name('family.update')->middleware('throttle:member-write');
    Route::post('/family/{id}/health', [MemberController::class, 'storeHealth'])->name('family.store-health')->middleware('throttle:member-write');
    Route::put('/family/{id}/health/{recordId}', [MemberController::class, 'updateHealth'])->name('family.update-health')->middleware('throttle:member-write');
    Route::put('/family/goal/{goalId}', [MemberController::class, 'updateGoal'])->name('family.update-goal')->middleware('throttle:member-write');
    Route::post('/family/{id}/goal', [MemberController::class, 'storeGoal'])->name('family.store-goal')->middleware('throttle:member-write');
    Route::post('/family/{id}/attendance', [MemberController::class, 'storeAttendance'])->name('family.store-attendance')->middleware('throttle:member-write');
    Route::post('/family/{id}/event-log', [MemberController::class, 'storeMemberEvent'])->name('family.store-event')->middleware('throttle:member-write');
    Route::post('/family/{id}/certification', [MemberController::class, 'storeCertification'])->name('family.store-certification')->middleware('throttle:member-write');
    Route::post('/family/{id}/work-history', [MemberController::class, 'storeWorkHistory'])->name('family.store-work')->middleware('throttle:member-write');
    Route::post('/family/{id}/tournament', [MemberController::class, 'storeTournament'])->name('family.store-tournament')->middleware('throttle:member-write');
    Route::put('/family/{id}/tournament/{uuid}', [MemberController::class, 'updateTournament'])->name('family.tournament.update')->middleware('throttle:member-write');
    Route::delete('/family/{id}/tournament/{uuid}', [MemberController::class, 'destroyTournament'])->name('family.tournament.destroy')->middleware('throttle:member-write');
    Route::post('/family/{id}/tournament/{uuid}/request-verification', [MemberController::class, 'requestTournamentVerification'])->name('family.tournament.request-verification')->middleware('throttle:member-write');

    // Peer/coach attestation for a member's self-claimed record (achievement | skill), bound by uuid.
    Route::post('/attestations/{type}/{uuid}/vouch', [App\Http\Controllers\AchievementVouchController::class, 'vouch'])->whereIn('type', ['achievement', 'skill', 'affiliation', 'work'])->name('attestations.vouch')->middleware('throttle:member-write');
    Route::post('/family/{id}/upload-picture', [MemberController::class, 'uploadPicture'])->name('family.upload-picture')->middleware('throttle:uploads');
    Route::delete('/family/{id}', [MemberController::class, 'destroy'])->name('family.destroy')->middleware('throttle:member-write');
    Route::get('/family/dashboard', function () {
        return redirect()->route('members.index');
    });

    // Bills routes
    Route::get('/bills', [InvoiceController::class, 'index'])->name('bills.index');
    Route::get('/bills/{id}', [InvoiceController::class, 'show'])->name('bills.show');
    Route::get('/bills/{id}/receipt', [InvoiceController::class, 'receipt'])->name('bills.receipt');
    Route::get('/bills/{id}/pay', [InvoiceController::class, 'pay'])->name('bills.pay');
    Route::get('/bills/pay-all', [InvoiceController::class, 'payAll'])->name('bills.pay-all');

    // Instructor Review routes
    Route::get('/instructor/{instructorId}/reviews', [InstructorReviewController::class, 'index'])->name('instructor.reviews.index');
    Route::post('/instructor/{instructorId}/reviews', [InstructorReviewController::class, 'store'])->name('instructor.reviews.store')->middleware('throttle:social');
    Route::put('/instructor/reviews/{reviewId}', [InstructorReviewController::class, 'update'])->name('instructor.reviews.update')->middleware('throttle:social');
});
