<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\PlatformController;
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
Route::middleware(['auth', 'verified', 'role:super-admin'])->get('/market/forms-preview', function () {
    return view('market.forms-preview');
})->name('market.forms-preview');

/*
|--------------------------------------------------------------------------
| The event page anybody may open
|--------------------------------------------------------------------------
| Documentation/EVENTS-PUBLIC-ENTRY.md, Phase B. Opt-in per event
| (`entry_mode = public`) and READ-ONLY — the whole surface is a GET. An event
| that was not deliberately made public 404s exactly as an unknown uuid does,
| so the address cannot be used to discover which events exist.
*/
Route::get('/e/{event:uuid}', [App\Http\Controllers\PublicEventController::class, 'show'])
    ->name('events.public')
    // Counts the PEOPLE who open it — machines recognised and left out. Runs
    // after the response and cannot delay or break the page; see the class.
    ->middleware(['throttle:public-event', App\Http\Middleware\RecordEventVisit::class])
    ->whereUuid('event');

/*
| Who came. ORGANISER ONLY — the count and the detail both. A public page that
| named its own visitors would be a leak, and one that let a stranger count them
| tells a competitor how an event is selling. Re-checked inside the controller,
| never inferred from the fact that the poster is public.
*/
Route::get('/e/{event:uuid}/visitors', [App\Http\Controllers\PublicEventController::class, 'visitors'])
    ->name('events.public.visitors')->middleware('throttle:60,1')->whereUuid('event');

/*
| Phase C — enrolling from that page. The ONE place on the platform where a
| wholly unauthenticated stranger creates an account, so it is throttled hard
| per IP and everything it creates waits for the organiser's accept (unless
| that organiser turned auto-accept on for this one event). An event that was
| not published 404s here exactly as it does above.
*/
// The link wears the EVENT's identity, not the platform's — a shared
// competition that lands on somebody's home screen is that competition's app.
// App\Events\Support\PublicBrand decides what it is branded as; both doors
// 404 for an event nobody published, like every other public surface.
Route::get('/e/{event:uuid}/app.webmanifest', [App\Http\Controllers\PublicEventController::class, 'manifest'])
    ->name('events.public.manifest')->middleware('throttle:public-event')->whereUuid('event');
Route::get('/e/{event:uuid}/icon-{size}.png', [App\Http\Controllers\PublicEventController::class, 'icon'])
    ->name('events.public.icon')->middleware('throttle:public-event')->whereUuid('event')->whereNumber('size');

// The event's attached files — rulebook, entry form, schedule. Open only while
// the organiser has the public page ON, and throttled: a download is cheap to
// ask for and expensive to serve. A separate door from the member route, which
// keeps its auth stack untouched.
Route::get('/e/{event:uuid}/documents/{document:uuid}', [App\Http\Controllers\PublicEventController::class, 'document'])
    ->name('events.public.document')->middleware('throttle:30,1')->whereUuid('event')->whereUuid('document');

// The draw, as the hall wall shows it: read-only, no entrants bench, no faces
// and nobody's payment state — App\Events\Support\PublicEvent::draw() decides
// what survives. A separate door from `me.events.bracket.data`, which keeps its
// auth stack and its organiser's view untouched. Throttled a little above the
// page itself because the board re-fetches when a division is switched.
Route::get('/e/{event:uuid}/draw/data', [App\Http\Controllers\PublicEventController::class, 'drawData'])
    ->name('events.public.draw.data')->middleware('throttle:public-event')->whereUuid('event');

// The four doors out of the public event page — the draw, the officiating
// sheet, the footage and the entry list, each on its own page exactly as the
// member page's four rows are. ONE route with the section in the path rather
// than four: the four differ only in which body they render, and a `where`
// allowlist means an unknown section 404s at the router instead of reaching a
// view name built from user input.
Route::get('/e/{event:uuid}/{section}', [App\Http\Controllers\PublicEventController::class, 'section'])
    ->name('events.public.section')->middleware('throttle:public-event')->whereUuid('event')
    ->whereIn('section', ['draw', 'officials', 'gallery', 'participants']);

/*
| The organiser's door, on the event's own page.
|
| The gear on the poster. The whole point of this surface is that a shared
| competition behaves like its own app — so the person RUNNING it should not
| have to leave it to sign in. GET decides which of three screens to show; POST
| hands the credentials to the platform's ONE login and only chooses where a
| success lands. Same `throttle:login` limiter as /login itself, because it IS
| /login with a different skin.
*/
Route::get('/e/{event:uuid}/manage', [App\Http\Controllers\PublicEventController::class, 'manage'])
    ->name('events.public.manage')->middleware('throttle:30,1')->whereUuid('event');
Route::post('/e/{event:uuid}/manage', [App\Http\Controllers\PublicEventController::class, 'signIn'])
    ->name('events.public.manage.signin')->middleware('throttle:login')->whereUuid('event');

// Signing OUT, without leaving the event. The platform's /logout lands on `/`,
// which for somebody using this as an installed app means being thrown out of
// the app entirely; this one lands on the poster.
Route::post('/e/{event:uuid}/sign-out', [App\Http\Controllers\PublicEventController::class, 'signOut'])
    ->name('events.public.sign-out')->middleware('throttle:30,1')->whereUuid('event');

Route::get('/e/{event:uuid}/enter', [App\Http\Controllers\PublicEntryController::class, 'show'])
    ->name('events.public.enter')->middleware('throttle:public-entry')->whereUuid('event');
Route::post('/e/{event:uuid}/enter', [App\Http\Controllers\PublicEntryController::class, 'store'])
    ->name('events.public.enter.store')->middleware('throttle:public-entry-write')->whereUuid('event');

// The same door for somebody who already has an account: they signed in
// through the ordinary login and came back, so this takes no name, no email
// and no password — only what they compete at. Throttled like the other one,
// and it 404s for a signed-out caller rather than redirecting an XHR to a
// login form.
Route::post('/e/{event:uuid}/enter/mine', [App\Http\Controllers\PublicEntryController::class, 'storeMine'])
    ->name('events.public.enter.mine')->middleware('throttle:public-entry-write')->whereUuid('event');

/*
|--------------------------------------------------------------------------
| "My entry" — the athlete's own control panel
|--------------------------------------------------------------------------
| A competitor enters ONCE (`unique(event_id, user_id)`, and deliberately so),
| which means whatever they typed on a phone at two in the morning is what they
| compete as. Until this existed they could change exactly two things — their
| proof of payment and which club they represent — and could not withdraw at
| all. Weight, belt, photograph, date of birth and gender were frozen, and those
| are the five fields most likely to be wrong AND the ones that decide their
| division.
|
| `auth` but NOT `verified`: the public door creates accounts unverified on
| purpose, and putting the only means of fixing a typo behind an email they have
| not opened yet would lock out exactly the people who need this. Authority is
| re-checked inside App\Events\Support\EntryEditor on every write.
*/
Route::middleware('auth')->group(function () {
    Route::get('/e/{event:uuid}/my-entry', [App\Http\Controllers\EntryPanelController::class, 'show'])
        ->name('events.public.my-entry')->middleware('throttle:60,1')->whereUuid('event');

    // What a live update re-fetches. Its own (looser) limit because a realtime
    // nudge can arrive several times in a busy minute at a weigh-in desk.
    Route::get('/e/{event:uuid}/my-entry/state', [App\Http\Controllers\EntryPanelController::class, 'state'])
        ->name('events.public.my-entry.state')->middleware('throttle:120,1')->whereUuid('event');

    Route::put('/e/{event:uuid}/my-entry', [App\Http\Controllers\EntryPanelController::class, 'update'])
        ->name('events.public.my-entry.update')->middleware('throttle:20,1')->whereUuid('event');

    /*
     | Proof of payment, from the person who owes it.
     |
     | The organiser's half of this shipped long ago (`me.events.verify.payment`
     | approves a proof, `me.events.verify.proof` streams it) and the entrant's
     | half did not exist: an official could approve a receipt that nobody had
     | any way to send. The only door in was the registration form, which a
     | competitor cannot open a second time.
     |
     | `throttle:uploads` because that is what this is — an image upload, and
     | the platform's other image endpoints are limited as one class rather than
     | each inventing its own number.
    */
    Route::post('/e/{event:uuid}/my-entry/payment-proof', [App\Http\Controllers\EntryPanelController::class, 'paymentProof'])
        ->name('events.public.my-entry.payment-proof')->middleware('throttle:uploads')->whereUuid('event');

    Route::post('/e/{event:uuid}/my-entry/withdraw', [App\Http\Controllers\EntryPanelController::class, 'withdraw'])
        ->name('events.public.my-entry.withdraw')->middleware('throttle:10,1')->whereUuid('event');

    Route::delete('/e/{event:uuid}/my-entry/withdraw', [App\Http\Controllers\EntryPanelController::class, 'withdrawCancel'])
        ->name('events.public.my-entry.withdraw.cancel')->middleware('throttle:10,1')->whereUuid('event');
});

// The club picker's search. Open because a club's name, mark and country are
// already public on /explore — what keeps it from being a scraping door is its
// shape: two characters minimum, ten results, throttled, four fields, and a
// SLUG rather than an id.
Route::get('/e/{event:uuid}/enter/clubs', [App\Http\Controllers\PublicEntryController::class, 'clubs'])
    ->name('events.public.enter.clubs')->middleware('throttle:public-entry')->whereUuid('event');

/*
|--------------------------------------------------------------------------
| Completing an entry somebody else committed — the claim link
|--------------------------------------------------------------------------
| Documentation/EVENTS-PUBLIC-ENTRY.md, Door B. Open, because the person who
| opens it has no account: a coach entered them by NAME and this is where they
| supply what only they know. The link itself is the credential — a public uuid
| plus a secret half, single-use, expiring, bound to ONE entry — and every kind
| of failure renders the same page, so a stranger cannot learn which links are
| real. Throttled hard for the same reason.
*/
Route::get('/enter/{claim}', [App\Http\Controllers\EntryClaimController::class, 'show'])
    ->name('entry.claim')->middleware('throttle:30,1')->whereUuid('claim');
Route::post('/enter/{claim}', [App\Http\Controllers\EntryClaimController::class, 'store'])
    ->name('entry.claim.store')->middleware('throttle:10,1')->whereUuid('claim');

// The one door to stored files. There is a single storage root and no
// public symlink: App\Support\FileAccess decides in code who may read a path,
// and denies anything it has not been taught about.
Route::get('/file/{path}', [App\Http\Controllers\FileController::class, 'show'])
    ->where('path', '.*')->name('file.show')->middleware('throttle:240,1');

// The public Android app-version manifest lives in the Members module:
// app/Members/routes.php.



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
// And the camera. Typed on a phone rather than a remote, but kept in the same
// shape as its siblings so the three addresses are learnable as a set.
Route::get('/screen/cam', [\App\Events\Support\ScreenPairingController::class, 'app'])
    ->name('screen.app.cam')->defaults('variant', 'cam')->middleware('throttle:screen-app');
Route::get('/screen/{token}', [\App\Events\Support\ScreenPairingController::class, 'show'])
    ->name('screen.show')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');
Route::get('/screen/{token}/status', [\App\Events\Support\ScreenPairingController::class, 'status'])
    ->name('screen.status')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');

/*
|--------------------------------------------------------------------------
| Live — a mat, broadcasting
|--------------------------------------------------------------------------
|
| A phone in the hall publishes over WHIP; viewers watch over WHEP (sub-second)
| or LL-HLS (works anywhere, plain HTTPS). The media plane is MediaMTX on
| loopback, proxied by Apache at /live-rtc and /live-hls — it owns no policy at
| all: it asks this application about every publish and every read.
|
| Every broadcast is RECORDED, and when the phone stops the recording is ingested
| as an ordinary media file under the bout it was of. A streamed fight and a
| filmed one end up in the same place.
|
*/
Route::middleware(['auth', 'verified'])->group(function () {
});

/*
|--------------------------------------------------------------------------
| Media — the video this platform holds itself
|--------------------------------------------------------------------------
|
| Bytes, authorised. Every URL here is bound by a media file's uuid and every
| request re-checks the event's own visibility rule — the playlist AND each
| segment, because a segment URL that outlives its check is a leaked video.
|
| Signed in, always: there is no public video surface yet, and adding one is a
| deliberate decision about consent (VIDEO-INTEGRATION.md §5.4), not a default.
|
| Read-only. Media is created by the ingest path (a camera filing a clip), never
| by a GET.
|
*/
Route::middleware(['auth', 'verified', 'throttle:media-read'])->group(function () {
    Route::get('/media/{file}/hls/{path?}', [\App\Media\Http\MediaStreamController::class, 'hls'])
        ->name('media.hls')->where('path', '[A-Za-z0-9_\-/\.]+');
    Route::get('/media/{file}/poster', [\App\Media\Http\MediaStreamController::class, 'poster'])
        ->name('media.poster');
    Route::get('/media/{file}/original', [\App\Media\Http\MediaStreamController::class, 'original'])
        ->name('media.original');
});

/*
|--------------------------------------------------------------------------
| Cameras — the phones filming a mat
|--------------------------------------------------------------------------
|
| A camera is a screen's opposite: it renders nothing and it writes. So it does
| not get a page, it gets four JSON endpoints — exist, ask what I am, say I am
| alive, file a clip — and it is authorised the same way a screen is, by a
| token that reaches its own row and nothing else. There is no session and no
| CSRF here because there is no browser and no logged-in user to forge against.
|
| Claiming happens through the SHARED door (/screen/claim/{code}): an organiser
| holding a phone should not have to know whether the thing in front of them is
| a television or a lens.
*/
// Filming a mat with the phone already in your hand — no app, no install.
//
// Open like /screen and for the same reason: what it yields is an UNCLAIMED
// camera that can read nothing and film nothing until an authenticated organiser
// puts it on a mat. It speaks the same four token endpoints below, so the server
// cannot tell it from the APK and neither can the console.
//
// This is the address a camera QR points at. A phone with the app installed is
// offered the app by Android; a phone without one lands here and works anyway,
// which is the whole point — there is no arrangement of devices in a hall that
// leaves somebody unable to film.
Route::get('/camera', [\App\Events\Support\Cameras\BrowserCameraController::class, 'show'])
    ->name('camera.web')->middleware('throttle:screen-app');

Route::post('/camera/enroll', [\App\Events\Support\Cameras\CameraController::class, 'enroll'])
    ->name('camera.enroll')->middleware('throttle:court-enroll');
Route::get('/camera/{token}/config', [\App\Events\Support\Cameras\CameraController::class, 'config'])
    ->name('camera.config')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');
Route::post('/camera/{token}/telemetry', [\App\Events\Support\Cameras\CameraController::class, 'telemetry'])
    ->name('camera.telemetry')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');
// The publish credential for a camera that also carries a live feed. Its own
// token is the authorisation, so no shared key is ever compiled into the app,
// and what it reaches is fixed by the camera's row: one stream, on the event and
// court it was claimed onto. Limited like every other token route.
Route::post('/camera/{token}/live', [\App\Events\Support\Cameras\CameraController::class, 'live'])
    ->name('camera.live')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');
Route::post('/camera/{token}/clip', [\App\Events\Support\Cameras\CameraController::class, 'clip'])
    ->name('camera.clip')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:screen-token');
// A clip deleted at the mat. The phone holds the video, so it is the authority
// on whether the video still exists — this only keeps the index honest.
Route::delete('/camera/{token}/clip/{clip}', [\App\Events\Support\Cameras\CameraController::class, 'deleteClip'])
    ->name('camera.clip.delete')->where('token', '[A-Za-z0-9]{40}')->whereNumber('clip')->middleware('throttle:screen-token');
// The clip's bytes, on their way to TAKEONE Play. Chunked and resumable — the
// far end is a phone on a hall's wifi. Its own limiter, because an upload is
// hundreds of requests where every other camera call is one.
Route::post('/camera/{token}/clip/{clip}/upload', [\App\Events\Support\Cameras\CameraController::class, 'upload'])
    ->name('camera.clip.upload')->where('token', '[A-Za-z0-9]{40}')->whereNumber('clip')->middleware('throttle:camera-upload');

Route::middleware(['auth', 'verified', 'two-factor'])->group(function () {
    Route::get('/screen/claim/{code}', [\App\Events\Support\ScreenPairingController::class, 'claim'])
        ->name('screen.claim')->where('code', '[A-Z0-9]{6}')->middleware('throttle:30,1');
    Route::post('/screen/claim/{code}', [\App\Events\Support\ScreenPairingController::class, 'storeClaim'])
        ->name('screen.claim.store')->where('code', '[A-Z0-9]{6}')->middleware('throttle:member-write');
    Route::get('/screen/paired', [\App\Events\Support\ScreenPairingController::class, 'claimed'])
        ->name('screen.claimed');
});



















Route::middleware(['auth', 'verified', 'two-factor'])->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Open Mat — /openmat
    |--------------------------------------------------------------------------
    | Its own top-level address, NOT under /me, because this is the one screen
    | in the product somebody is told out loud: "go to takeone.bh/openmat".
    | It has to be short enough to say across a dojo and type on a phone with
    | one hand, which /me/open-mat was not.
    |
    | ONE route to get on a mat: there is no launcher and no form. /openmat
    | resolves the club, the sport and the mat by itself and redirects to the
    | console, because every question asked before the two corner cards appear
    | is a question asked at the worst possible moment. Everything afterwards is
    | the mat's console under /me/events, because an open mat IS an event
    | (App\Events\OpenMat).
    |
    | Any signed-in member, deliberately: not a coach, not an admin. The whole
    | premise is that two people decided to fight thirty seconds ago.
    |
    | Taking a corner is reached by somebody who is NOT holding the console —
    | they scanned the QR on the mat, or typed its six characters. Signed in,
    | because taking a corner puts a name and a face on a wall screen and files
    | a bout on a record; an anonymous join would be a way of standing in as
    | somebody else. The code is public by design and short-lived, and the mat
    | behind it is re-checked on every request.
    */
    Route::get('/openmat', [\App\Events\OpenMat\OpenMatController::class, 'index'])->name('openmat');
    Route::get('/openmat/join/{code}', [\App\Events\OpenMat\OpenMatController::class, 'join'])
        ->name('openmat.join')->where('code', '[A-Za-z0-9]{6}')->middleware('throttle:30,1');
    Route::post('/openmat/join/{code}', [\App\Events\OpenMat\OpenMatController::class, 'take'])
        ->name('openmat.take')->where('code', '[A-Za-z0-9]{6}')->middleware('throttle:member-write');

    // The same mat, on a named sport — "openmat slash bjj". Registered AFTER
    // /openmat/join so the six-character join code can never be read as a sport
    // name, and constrained to the sports OpenMat actually runs so an unknown
    // one is a router 404 rather than something the controller has to decide.
    Route::get('/openmat/{sport}', [\App\Events\OpenMat\OpenMatController::class, 'sport'])
        ->name('openmat.sport')->whereIn('sport', \App\Events\OpenMat\OpenMat::sports());
});

Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login')->middleware('no-store');
Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
/*
| "Who is signing in?" — the second half of a telephone sign-in.
|
| A telephone belongs to a household. When one number and one password answer
| for more than one person (a parent and a child, the ordinary case at a
| junior competition), the password is already proven and only the identity is
| open. Guarded by a SERVER-held shortlist in the session, not by anything the
| caller sends, and throttled like the sign-in it continues.
*/
Route::get('/login/choose', [AuthenticatedSessionController::class, 'choose'])
    ->name('login.choose')->middleware(['no-store', 'throttle:login']);
Route::post('/login/choose', [AuthenticatedSessionController::class, 'chose'])
    ->name('login.chose')->middleware('throttle:login');

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
    $user = \App\Members\Models\User::findOrFail($id);

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
    $user = \App\Members\Models\User::where('email', $request->email)->first();
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

// The safe public profile /people/{uuid} lives in the Members module:
// app/Members/routes.php.

// The public trainer page /t/{user} and the signed-in /trainer/{user} live
// in the Trainers module: app/Trainers/routes.php.

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
})->middleware(['auth', 'verified', 'role:super-admin', 'throttle:60,1'])->name('lab.activity-video');

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
    Route::get('/clubs/{club}/edit', [App\Http\Controllers\Admin\PlatformController::class, 'editClub'])->name('platform.clubs.edit');
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

    /*
     * The platform's own languages. Super-admin only, and inside this group's
     * existing role gate — these are the PRODUCT's words, not a tenant's, and
     * a run spends money at a paid provider.
     */
    Route::get('/languages', [App\Http\Controllers\Admin\LanguageController::class, 'index'])->name('languages.index');
    Route::get('/languages/{locale}/strings', [App\Http\Controllers\Admin\LanguageController::class, 'show'])->name('languages.strings');
    Route::get('/languages/{locale}/status', [App\Http\Controllers\Admin\LanguageController::class, 'status'])->name('languages.status');
    Route::put('/languages/{locale}', [App\Http\Controllers\Admin\LanguageController::class, 'update'])->name('languages.update')->middleware('throttle:admin-write');
    // Starts work at a paid provider, so it carries the translate ceiling on
    // top of the admin-write one — the same guard the public door has.
    Route::post('/languages/{locale}/translate', [App\Http\Controllers\Admin\LanguageController::class, 'translate'])
        ->name('languages.translate')->middleware(['throttle:admin-write', 'throttle:translate']);

    // Which model writes the event translations — Claude, a local Ollama, or
    // anything else configured above. Separate from the Copilot's default on
    // purpose; see AiProviderController::translationPrimary().
    Route::post('/ai/translation-primary', [App\Http\Controllers\Admin\AiProviderController::class, 'translationPrimary'])->name('ai.translation-primary')->middleware('throttle:admin-write');

    // Storage — the media vaults video is kept on. None attached is the default
    // and a complete configuration; attaching one moves new media onto it.
    Route::get('/storage', [App\Http\Controllers\Admin\MediaVaultController::class, 'index'])->name('storage.index');
    Route::post('/storage/vaults', [App\Http\Controllers\Admin\MediaVaultController::class, 'store'])->name('storage.store')->middleware('throttle:admin-write');
    Route::put('/storage/vaults/{vault}', [App\Http\Controllers\Admin\MediaVaultController::class, 'update'])->name('storage.update')->middleware('throttle:admin-write');
    Route::delete('/storage/vaults/{vault}', [App\Http\Controllers\Admin\MediaVaultController::class, 'destroy'])->name('storage.destroy')->middleware('throttle:admin-write');
    Route::post('/storage/vaults/{vault}/test', [App\Http\Controllers\Admin\MediaVaultController::class, 'test'])->name('storage.test')->middleware('throttle:admin-write');
    Route::post('/storage/vaults/{vault}/drain', [App\Http\Controllers\Admin\MediaVaultController::class, 'drain'])->name('storage.drain')->middleware('throttle:admin-write');

    // Copilot ("Coach") — page-aware AI assistant (thin slice: create a club)
    Route::post('/copilot/message', [App\Http\Controllers\Admin\CopilotController::class, 'message'])->name('copilot.message')->middleware('throttle:copilot');
    Route::post('/copilot/apply', [App\Http\Controllers\Admin\CopilotController::class, 'apply'])->name('copilot.apply')->middleware('throttle:copilot');
    Route::post('/copilot/stt', [App\Http\Controllers\Admin\CopilotController::class, 'stt'])->name('copilot.stt')->middleware('throttle:copilot');
    Route::post('/copilot/tts', [App\Http\Controllers\Admin\CopilotController::class, 'tts'])->name('copilot.tts')->middleware('throttle:copilot');

    // Club create/update and the club-modal lookup endpoints now live in the
    // Clubs module: app/Clubs/routes.php (same prefix, names and middleware).

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

// Club Admin routes (Club owners and admins) now live in the Clubs module:
// app/Clubs/routes-club-admin.php, registered by ModuleServiceProvider under
// admin/club/{club} with the `admin.club.` prefix and the same middleware stack.

// The global notification routes (mark-read / clear) live in the Clubs module:
// app/Clubs/routes.php.

// Messenger — platform-wide direct messages — lives in the Members module:
// app/Members/routes.php.

// The member surfaces that shared this group (profile, family, bills,
// attestations) live in the Members module: app/Members/routes.php; the
// instructor-review routes that shared it live in the Trainers module:
// app/Trainers/routes.php.
