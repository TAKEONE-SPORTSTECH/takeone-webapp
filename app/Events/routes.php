<?php

/*
|--------------------------------------------------------------------------
| Events — every route an event surface answers on
|--------------------------------------------------------------------------
| Moved verbatim from routes/web.php (the public /e pages, entry claims,
| hall-screen pairing, cameras, Open Mat) and from app/Scoreboard/routes.php
| (the /me/events consoles). Every URI, name and middleware stack is the one
| it had there; the route table was diffed before and after.
|
| Registered by App\Support\Modules\ModuleServiceProvider inside `web`.
*/

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| The event page anybody may open
|--------------------------------------------------------------------------
| Documentation/EVENTS-PUBLIC-ENTRY.md, Phase B. Opt-in per event
| (`entry_mode = public`) and READ-ONLY — the whole surface is a GET. An event
| that was not deliberately made public 404s exactly as an unknown uuid does,
| so the address cannot be used to discover which events exist.
*/
// ⚠️ `/e/{uuid}` is GONE (owner's decision, 2026-09-27): the event's one
// address is `/me/events/{uuid}` (`me.events.show`, further down), for every
// reader. Old /e/ links answer "not found" — deliberately, not by accident.

/*
| Who came. ORGANISER ONLY — the count and the detail both. A public page that
| named its own visitors would be a leak, and one that let a stranger count them
| tells a competitor how an event is selling. Re-checked inside the controller,
| never inferred from the fact that the poster is public.
*/
Route::get('/me/events/{event:uuid}/visitors', [App\Events\Controllers\PublicEventController::class, 'visitors'])
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
Route::get('/me/events/{event:uuid}/app.webmanifest', [App\Events\Controllers\PublicEventController::class, 'manifest'])
    ->name('events.public.manifest')->middleware('throttle:public-event')->whereUuid('event');
Route::get('/me/events/{event:uuid}/icon-{size}.png', [App\Events\Controllers\PublicEventController::class, 'icon'])
    ->name('events.public.icon')->middleware('throttle:public-event')->whereUuid('event')->whereNumber('size');

// The event's attached files — rulebook, entry form, schedule. Open only while
// the organiser has the public page ON, and throttled: a download is cheap to
// ask for and expensive to serve. A separate door from the member route, which
// keeps its auth stack untouched.
Route::get('/me/events/{event:uuid}/downloads/{document:uuid}', [App\Events\Controllers\PublicEventController::class, 'document'])
    ->name('events.public.document')->middleware('throttle:30,1')->whereUuid('event')->whereUuid('document');

// The draw, as the hall wall shows it: read-only, no entrants bench, no faces
// and nobody's payment state — App\Events\Support\PublicEvent::draw() decides
// what survives. A separate door from `me.events.bracket.data`, which keeps its
// auth stack and its organiser's view untouched. Throttled a little above the
// page itself because the board re-fetches when a division is switched.
Route::get('/me/events/{event:uuid}/draw/data', [App\Events\Controllers\PublicEventController::class, 'drawData'])
    ->name('events.public.draw.data')->middleware('throttle:public-event')->whereUuid('event');

// The four doors out of the public event page — the draw, the officiating
// sheet, the footage and the entry list, each on its own page exactly as the
// member page's four rows are. ONE route with the section in the path rather
// than four: the four differ only in which body they render, and a `where`
// allowlist means an unknown section 404s at the router instead of reaching a
// view name built from user input.
// The sections live under /me/events/{uuid} too — see EventAddress.

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
Route::get('/me/events/{event:uuid}/door', [App\Events\Controllers\PublicEventController::class, 'manage'])
    ->name('events.public.manage')->middleware('throttle:30,1')->whereUuid('event');
Route::post('/me/events/{event:uuid}/door', [App\Events\Controllers\PublicEventController::class, 'signIn'])
    ->name('events.public.manage.signin')->middleware('throttle:login')->whereUuid('event');

// Signing OUT, without leaving the event. The platform's /logout lands on `/`,
// which for somebody using this as an installed app means being thrown out of
// the app entirely; this one lands on the poster.
Route::post('/me/events/{event:uuid}/sign-out', [App\Events\Controllers\PublicEventController::class, 'signOut'])
    ->name('events.public.sign-out')->middleware('throttle:30,1')->whereUuid('event');

Route::get('/me/events/{event:uuid}/enter', [App\Events\Controllers\PublicEntryController::class, 'show'])
    ->name('events.public.enter')->middleware('throttle:public-entry')->whereUuid('event');
Route::post('/me/events/{event:uuid}/enter', [App\Events\Controllers\PublicEntryController::class, 'store'])
    ->name('events.public.enter.store')->middleware('throttle:public-entry-write')->whereUuid('event');

// The same door for somebody who already has an account: they signed in
// through the ordinary login and came back, so this takes no name, no email
// and no password — only what they compete at. Throttled like the other one,
// and it 404s for a signed-out caller rather than redirecting an XHR to a
// login form.
Route::post('/me/events/{event:uuid}/enter/mine', [App\Events\Controllers\PublicEntryController::class, 'storeMine'])
    ->name('events.public.enter.mine')->middleware('throttle:public-entry-write')->whereUuid('event');

// Signing in as an ATHLETE on the event (the entry form's "Already have an
// account?"), and the entry desk's one-time code for a forgotten password.
// Both behind `events.entry_dedupe`. The code door is throttled like /login
// on top of the code's own five-try limit.
Route::get('/me/events/{event:uuid}/sign-in', [App\Events\Controllers\PublicEntryController::class, 'signInPage'])
    ->name('events.public.sign-in')->middleware('throttle:30,1')->whereUuid('event');
Route::post('/me/events/{event:uuid}/sign-in/code', [App\Events\Controllers\PublicEntryController::class, 'redeemDeskCode'])
    ->name('events.public.sign-in.code')->middleware('throttle:login')->whereUuid('event');

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
    Route::get('/me/events/{event:uuid}/my-entry', [App\Events\Controllers\EntryPanelController::class, 'show'])
        ->name('events.public.my-entry')->middleware('throttle:60,1')->whereUuid('event');

    // What a live update re-fetches. Its own (looser) limit because a realtime
    // nudge can arrive several times in a busy minute at a weigh-in desk.
    Route::get('/me/events/{event:uuid}/my-entry/state', [App\Events\Controllers\EntryPanelController::class, 'state'])
        ->name('events.public.my-entry.state')->middleware('throttle:120,1')->whereUuid('event');

    Route::put('/me/events/{event:uuid}/my-entry', [App\Events\Controllers\EntryPanelController::class, 'update'])
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
    Route::post('/me/events/{event:uuid}/my-entry/payment-proof', [App\Events\Controllers\EntryPanelController::class, 'paymentProof'])
        ->name('events.public.my-entry.payment-proof')->middleware('throttle:uploads')->whereUuid('event');

    Route::post('/me/events/{event:uuid}/my-entry/withdraw', [App\Events\Controllers\EntryPanelController::class, 'withdraw'])
        ->name('events.public.my-entry.withdraw')->middleware('throttle:10,1')->whereUuid('event');

    Route::delete('/me/events/{event:uuid}/my-entry/withdraw', [App\Events\Controllers\EntryPanelController::class, 'withdrawCancel'])
        ->name('events.public.my-entry.withdraw.cancel')->middleware('throttle:10,1')->whereUuid('event');
});

// The club picker's search. Open because a club's name, mark and country are
// already public on /explore — what keeps it from being a scraping door is its
// shape: two characters minimum, ten results, throttled, four fields, and a
// SLUG rather than an id.
Route::get('/me/events/{event:uuid}/enter/clubs', [App\Events\Controllers\PublicEntryController::class, 'clubs'])
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
Route::get('/enter/{claim}', [App\Events\Controllers\EntryClaimController::class, 'show'])
    ->name('entry.claim')->middleware('throttle:30,1')->whereUuid('claim');
Route::post('/enter/{claim}', [App\Events\Controllers\EntryClaimController::class, 'store'])
    ->name('entry.claim.store')->middleware('throttle:10,1')->whereUuid('claim');

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

/*
|--------------------------------------------------------------------------
| The one event address — /me/events/{uuid}
|--------------------------------------------------------------------------
| Lifted out of the member group below so it can also answer a stranger, but
| ONLY through EventPageReader: with `events.unified_page` off (the default)
| that middleware runs exactly the stack this route had inside the group —
| auth, verified, two-factor — so nothing changes. With it on, a published
| event's page opens for anybody, as /e/{uuid} always has.
|
| `whereUuid` because it is now registered BEFORE `/me/events/create`, and
| without it "create" would be read as an event.
*/
// `throttle:public-event` (300/min per person or connection) because a stranger
// can reach these now, exactly as on /e/{uuid}.
// The rules screen's acceptance, and the club stamped on it after a
// registration (ClubEntryController::consent). Public like the screens — an
// invited club has no account — with the controller's own reading gate, CSRF
// from the web stack, and a tight throttle.
// "Register your club" on a CLUB-hosted event — its JSON doors (ClubEntryApiController,
// PlatformClubEntry). Public like the screens, gated in the controller; tight throttles.
Route::prefix('/me/events/{event:uuid}/club/api')->whereUuid('event')->name('events.club-entry.api.')->group(function () {
    Route::get('/clubs', [App\Events\Controllers\ClubEntryApiController::class, 'clubs'])->middleware('throttle:20,1')->name('clubs');
    Route::post('/code', [App\Events\Controllers\ClubEntryApiController::class, 'code'])->middleware('throttle:5,1')->name('code');
    Route::post('/verify', [App\Events\Controllers\ClubEntryApiController::class, 'verify'])->middleware('throttle:10,1')->name('verify');
    Route::post('/choose', [App\Events\Controllers\ClubEntryApiController::class, 'choose'])->middleware('throttle:10,1')->name('choose');
    Route::post('/check-club', [App\Events\Controllers\ClubEntryApiController::class, 'checkClub'])->middleware('throttle:20,1')->name('check-club');
    Route::post('/submit', [App\Events\Controllers\ClubEntryApiController::class, 'submit'])->middleware(['throttle:10,1', 'throttle:uploads'])->name('submit');
    Route::post('/withdraw', [App\Events\Controllers\ClubEntryApiController::class, 'withdraw'])->middleware('throttle:20,1')->name('withdraw');
    Route::post('/proof', [App\Events\Controllers\ClubEntryApiController::class, 'proof'])->middleware(['throttle:10,1', 'throttle:uploads'])->name('proof');
});

// The drawn picture a shared event link unfurls into (ShareCardController).
Route::get('/me/events/{event:uuid}/share-card.jpg', [App\Events\Controllers\ShareCardController::class, 'show'])
    ->name('events.share-card')->whereUuid('event')->middleware('throttle:public-event');

/*
 | The draw on a SCORING TABLET (App\Events\Support\TabletDraw). No `auth`: the
 | tablet is a paired DEVICE and its token is its identity, exactly like the
 | scoring console beside it. Reading rides on the pairing; every edit needs
 | the event's tablet PIN and runs as the organiser who set it. The page never
 | 404s (it redirects to the pairing room); the JSON doors 404 a dead token.
 */
Route::where(['token' => '[A-Za-z0-9]{40}'])->prefix('/tablet/{token}/draw')->name('tablet.draw')->group(function () {
    Route::get('/', [App\Events\Controllers\TabletDrawController::class, 'show'])->middleware('throttle:screen-token');
    Route::get('/data', [App\Events\Controllers\TabletDrawController::class, 'data'])->name('.data')->middleware('throttle:screen-token');
    Route::post('/unlock', [App\Events\Controllers\TabletDrawController::class, 'unlock'])->name('.unlock')->middleware('throttle:10,1');
    Route::post('/lock', [App\Events\Controllers\TabletDrawController::class, 'lock'])->name('.lock')->middleware('throttle:30,1');
    Route::put('/arrange', [App\Events\Controllers\TabletDrawController::class, 'arrange'])->name('.arrange')->middleware('throttle:bracket-arrange');
    Route::put('/admit', [App\Events\Controllers\TabletDrawController::class, 'admit'])->name('.admit')->middleware('throttle:member-write');
    Route::get('/candidates', [App\Events\Controllers\TabletDrawController::class, 'candidates'])->name('.candidates')->middleware('throttle:event-desk');
    Route::post('/unnamed', [App\Events\Controllers\TabletDrawController::class, 'unnamed'])->name('.unnamed')->middleware('throttle:admin-write');
    Route::get('/clubs', [App\Events\Controllers\TabletDrawController::class, 'clubs'])->name('.clubs')->middleware('throttle:60,1');
    Route::post('/outcomes/{unit}', [App\Events\Controllers\TabletDrawController::class, 'outcome'])->name('.outcome')->whereNumber('unit')->middleware('throttle:member-write');
    Route::put('/entrants/{registration}', [App\Events\Controllers\TabletDrawController::class, 'entrant'])->name('.entrant')->whereNumber('registration')->middleware('throttle:member-write');
});

Route::middleware(['throttle:30,1'])->group(function () {
    Route::post('/me/events/{event:uuid}/consent', [App\Events\Controllers\ClubEntryController::class, 'consent'])
        ->name('events.club-entry.consent')->whereUuid('event');
    Route::post('/me/events/{event:uuid}/consent/club', [App\Events\Controllers\ClubEntryController::class, 'consentClub'])
        ->name('events.club-entry.consent-club')->whereUuid('event');
});

Route::middleware(['throttle:public-event', \App\Events\Middleware\EventPageReader::class, \App\Events\Middleware\BrandEventPage::class])
    ->prefix('me')->name('me.')->group(function () {
        Route::get('/events/{event:uuid}', [App\Events\Controllers\PersonalEventController::class, 'show'])
            ->name('events.show')->whereUuid('event')
            // Counts the PEOPLE who open the event — what /e/{uuid} counted
            // before it went. After the response; it cannot break the page.
            ->middleware(App\Events\Middleware\RecordEventVisit::class);

        // The event's SECTIONS, on the same terms: member-only exactly as
        // before while the flag is off; with it on, a published event's
        // sections open for anybody and render the public section pages.
        Route::get('/events/{event:uuid}/brackets/manage', [App\Events\Controllers\PersonalEventController::class, 'manageBracket'])
            ->name('events.bracket.manage')->whereUuid('event');
        Route::get('/events/{event:uuid}/officiating', [App\Events\Controllers\PersonalEventController::class, 'officiating'])
            ->name('events.officiating')->whereUuid('event');
        Route::get('/events/{event:uuid}/gallery', [App\Events\Controllers\PersonalEventController::class, 'gallery'])
            ->name('events.gallery')->whereUuid('event');

        // Sections that only the public door had until now. They 404 with the
        // flag off, so nothing links to them until it is on.
        Route::get('/events/{event:uuid}/participants', [App\Events\Controllers\PersonalEventController::class, 'participantsPage'])
            ->name('events.participants')->whereUuid('event');
        Route::get('/events/{event:uuid}/results', [App\Events\Controllers\PersonalEventController::class, 'resultsPage'])
            ->name('events.results.page')->whereUuid('event');
        Route::get('/events/{event:uuid}/table', [App\Events\Controllers\PersonalEventController::class, 'tablePage'])
            ->name('events.table')->whereUuid('event');

        // "Register your club" — one address per screen, every host the same
        // screens (ClubEntryController; config `events.club_entry`).
        foreach (App\Events\Controllers\ClubEntryController::STEPS as $n => $path) {
            Route::get('/events/{event:uuid}/'.$path, [App\Events\Controllers\ClubEntryController::class, 'page'])
                ->defaults('step', $n)->name('events.club-entry.'.str_replace('/', '-', $path))->whereUuid('event');
        }
    });

Route::middleware(['auth', 'verified', 'two-factor', \App\Events\Middleware\BrandEventPage::class])
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
    Route::get('/events', [App\Events\Controllers\PersonalEventController::class, 'index'])->name('events');
    Route::get('/events/create', [App\Events\Controllers\PersonalEventController::class, 'create'])->name('events.create')->middleware('source-text');
    Route::post('/events', [App\Events\Controllers\PersonalEventController::class, 'store'])->name('events.store')->middleware(['throttle:member-write', 'source-text']);
    // `me.events.show` is registered ABOVE this group — see "The one event
    // address". Same URI, same name, same controller action.
    // The organiser's console. `show` is what a visitor came to read; this is
    // what the people running the event came to do. Organiser or an appointed
    // official only — the action refuses everyone else.
    Route::get('/events/{event:uuid}/manage', [App\Events\Controllers\PersonalEventController::class, 'manage'])->name('events.manage');
    Route::get('/events/{event:uuid}/edit', [App\Events\Controllers\PersonalEventController::class, 'edit'])->name('events.edit')->middleware('source-text');
    Route::put('/events/{event:uuid}', [App\Events\Controllers\PersonalEventController::class, 'update'])->name('events.update')->middleware(['throttle:member-write', 'source-text']);
    /*
     * The code that has to be typed before an event is called off or erased.
     *
     * Issued by the server, checked by the server — see
     * App\Events\Support\DestructiveChallenge for why it is not a browser's
     * business. Throttled like any other write: asking for codes in bulk is the
     * only way to make this endpoint interesting.
     */
    /*
     * The running order, as a board: read it, drag one bout, or lay the whole
     * day out again. Reading needs `canScore` (the table runs off it); the two
     * writes are the organiser's — see App\Events\Support\Tournament\OrderBoard.
     */
    Route::get('/events/{event:uuid}/order', [App\Events\Controllers\PersonalEventController::class, 'runningOrderPage'])->name('events.order.page');
    Route::get('/events/{event:uuid}/running-order', [App\Events\Controllers\PersonalEventController::class, 'runningOrder'])->name('events.order')->middleware('throttle:120,1');
    Route::put('/events/{event:uuid}/running-order', [App\Events\Controllers\PersonalEventController::class, 'moveInRunningOrder'])->name('events.order.move')->middleware('throttle:admin-write');
    Route::post('/events/{event:uuid}/running-order/auto', [App\Events\Controllers\PersonalEventController::class, 'autoRunningOrder'])->name('events.order.auto')->middleware('throttle:member-write');

    /*
     * The dress rehearsal: run the competition before the day, then take it
     * back. Both doors are organiser-only (checked in the controller) and
     * throttled — see App\Events\Support\Rehearsal.
     */
    Route::post('/events/{event:uuid}/rehearsal', [App\Events\Controllers\PersonalEventController::class, 'beginRehearsal'])->name('events.rehearsal.begin')->middleware('throttle:member-write');
    Route::delete('/events/{event:uuid}/rehearsal', [App\Events\Controllers\PersonalEventController::class, 'endRehearsal'])->name('events.rehearsal.end')->middleware('throttle:member-write');

    Route::post('/events/{event:uuid}/danger-code', [App\Events\Controllers\PersonalEventController::class, 'dangerCode'])->name('events.danger-code')->middleware('throttle:member-write');
    Route::patch('/events/{event:uuid}/cancel', [App\Events\Controllers\PersonalEventController::class, 'cancelEvent'])->name('events.cancel-event')->middleware('throttle:member-write');
    Route::put('/events/{event:uuid}/results', [App\Events\Controllers\PersonalEventController::class, 'setResults'])->name('events.results')->middleware('throttle:member-write');
    Route::delete('/events/{event:uuid}', [App\Events\Controllers\PersonalEventController::class, 'destroy'])->name('events.destroy')->middleware('throttle:member-write');
    Route::get('/events/{event:uuid}/brackets', [App\Events\Controllers\PersonalEventController::class, 'bracket'])->name('events.bracket');

    // One bout, and where in the event it sat — the page a match video on the
    // video platform links back to (Documentation/VIDEO-INTEGRATION.md §6.6).
    // Keyed by the EVENT's uuid plus the bout's match number: event_matches has
    // no public identifier of its own, and the unguessable part of the link is
    // the event uuid, which the viewer already holds.
    Route::get('/events/{event:uuid}/bout/{matchNo}', [App\Events\Controllers\PersonalEventController::class, 'bout'])
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
    Route::get('/events/{event:uuid}/bout/{matchNo}/video', [App\Events\Controllers\BoutVideoController::class, 'show'])
        ->whereNumber('matchNo')->name('events.bout.video');
    // The highlights lists as JSON, so the watch page can re-read them after a
    // coach note is written without reloading the whole bout.
    Route::get('/events/{event:uuid}/bout/{matchNo}/video/data', [App\Events\Controllers\BoutVideoController::class, 'matchData'])
        ->whereNumber('matchNo')->name('events.bout.video.data');
    // Deleting the footage itself — whoever may MANAGE the event, enforced in
    // the controller (EventAccess::canManage, so the jury is outside it).
    // Throttled like any other destructive write: competition video cannot be
    // filmed again, so this is the one button on the page with no undo behind
    // it.
    Route::delete('/events/{event:uuid}/bout/{matchNo}/video', [App\Events\Controllers\BoutVideoController::class, 'destroyVideo'])
        ->whereNumber('matchNo')->name('events.bout.video.destroy')->middleware('throttle:admin-write');
    Route::post('/events/{event:uuid}/bout/{matchNo}/notes', [App\Events\Controllers\BoutVideoController::class, 'storeNote'])
        ->whereNumber('matchNo')->name('events.bout.notes.store')->middleware('throttle:member-write');
    Route::put('/events/{event:uuid}/bout/{matchNo}/notes/{note:uuid}', [App\Events\Controllers\BoutVideoController::class, 'updateNote'])
        ->whereNumber('matchNo')->name('events.bout.notes.update')->middleware('throttle:member-write');
    Route::delete('/events/{event:uuid}/bout/{matchNo}/notes/{note:uuid}', [App\Events\Controllers\BoutVideoController::class, 'destroyNote'])
        ->whereNumber('matchNo')->name('events.bout.notes.destroy')->middleware('throttle:member-write');

    // The conversation under a bout. Anyone who may watch may join it.
    Route::post('/events/{event:uuid}/bout/{matchNo}/comments', [App\Events\Controllers\BoutVideoController::class, 'storeComment'])
        ->whereNumber('matchNo')->name('events.bout.comments.store')->middleware('throttle:social');
    Route::delete('/events/{event:uuid}/bout/{matchNo}/comments/{comment:uuid}', [App\Events\Controllers\BoutVideoController::class, 'destroyComment'])
        ->whereNumber('matchNo')->name('events.bout.comments.destroy')->middleware('throttle:member-write');
    Route::post('/events/{event:uuid}/bout/{matchNo}/comments/{comment:uuid}/like', [App\Events\Controllers\BoutVideoController::class, 'likeComment'])
        ->whereNumber('matchNo')->name('events.bout.comments.like')->middleware('throttle:social');

    // The event's own gallery: every filmed bout, grouped by division.
    // `/events/{uuid}/gallery` is registered ABOVE this group, beside
    // `me.events.show` — see "The one event address". Same URI, name, action.
    Route::get('/events/{event:uuid}/gallery/data', [App\Events\Controllers\PersonalEventController::class, 'galleryData'])
        ->name('events.gallery.data')->middleware('throttle:media-read');
    // Organiser corrections to one bout: corners, scores, winner, the names on
    // the sheet, and the video link. The mat is authoritative while a bout is
    // being fought; this is authoritative once it is finished, and every change
    // is appended to the officiating log.
    // The entrants who may stand in one bout: this event, this bout's category.
    Route::get('/events/{event:uuid}/bout/{matchNo}/competitors', [App\Events\Controllers\PersonalEventController::class, 'boutCompetitors'])
        ->whereNumber('matchNo')->name('events.bout.competitors');
    Route::put('/events/{event:uuid}/bout/{matchNo}', [App\Events\Controllers\PersonalEventController::class, 'updateBout'])
        ->whereNumber('matchNo')->name('events.bout.update')->middleware('throttle:member-write');
    // The same board, full screen and free of chrome, for organisers arranging a
    // draw. Redirects back to the bracket for anyone who may not arrange.
    // `/events/{uuid}/brackets/manage` is registered ABOVE this group, beside
    // `me.events.show` — see "The one event address". Same URI, name, action.

    // Who's joined — the roster that used to render inline on the event screen.
    Route::get('/events/{event:uuid}/people', [App\Events\Controllers\PersonalEventController::class, 'people'])->name('events.people');

    // The officiating sheet — who is running the competition. Reading only, open
    // to anyone the event is visible to; appointing lives on the edit screen and
    // the appointment paperwork (email, phone, fee) stays on events.officials,
    // which is organiser-guarded.
    // `/events/{uuid}/officiating` is registered ABOVE this group, beside
    // `me.events.show` — see "The one event address". Same URI, name, action.

    // Documents attached to an event (rulebook, entry form, schedule).
    // Upload/delete are organiser-only; download is anyone the event reaches —
    // each is re-checked in the controller, never inferred from the URL.
    Route::post('/events/{event:uuid}/documents', [App\Events\Controllers\EventDocumentController::class, 'store'])->name('events.documents.store')->middleware('throttle:uploads');
    Route::get('/events/{event:uuid}/documents/{document:uuid}', [App\Events\Controllers\EventDocumentController::class, 'download'])->name('events.documents.download');
    Route::delete('/events/{event:uuid}/documents/{document:uuid}', [App\Events\Controllers\EventDocumentController::class, 'destroy'])->name('events.documents.destroy')->middleware('throttle:member-write');
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
    Route::get('/events/{event:uuid}/entrants/{registration}', [App\Events\Controllers\EntryPanelController::class, 'entrant'])
        ->name('events.entrant')->whereNumber('registration')->middleware('throttle:120,1');

    Route::put('/events/{event:uuid}/entrants/{registration}', [App\Events\Controllers\EntryPanelController::class, 'updateEntrant'])
        ->name('events.entrant.update')->whereNumber('registration')->middleware('throttle:member-write');

    // The withdrawal queue. An athlete asks to come out; the organiser decides.
    // Nobody leaves a bracket without the person running it knowing.
    Route::get('/events/{event:uuid}/withdrawals', [App\Events\Controllers\EntryPanelController::class, 'withdrawals'])
        ->name('events.withdrawals')->middleware('throttle:120,1');

    Route::post('/events/{event:uuid}/withdrawals/{withdrawal}', [App\Events\Controllers\EntryPanelController::class, 'decideWithdrawal'])
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
    Route::post('/events/{event:uuid}/competitors/{registration}/photo', [App\Events\Controllers\PersonalEventController::class, 'competitorPhoto'])
        ->name('events.competitors.photo')->middleware('throttle:uploads');
    Route::delete('/events/{event:uuid}/competitors/{registration}/photo', [App\Events\Controllers\PersonalEventController::class, 'competitorPhotoDestroy'])
        ->name('events.competitors.photo.destroy')->middleware('throttle:member-write');
    /* ===== THE DESK =====
       Signing a weight, approving a receipt, correcting the fee types and
       enrolling somebody in a second division are one person's work at a
       queue, so they share ONE bucket sized for a queue (`event-desk`, see
       AppServiceProvider). They were on `member-write` (30/min — a member
       editing their own health records) and `admin-write`, and an organiser
       ran out of both on an event morning. */
    Route::put('/events/{event:uuid}/verify/{registration}/weigh-in', [App\Events\Controllers\PersonalEventController::class, 'verifyWeighIn'])->name('events.verify.weigh-in')->middleware('throttle:event-desk');
    Route::put('/events/{event:uuid}/verify/{registration}/payment', [App\Events\Controllers\PersonalEventController::class, 'verifyPayment'])->name('events.verify.payment')->middleware('throttle:event-desk');
    Route::get('/events/{event:uuid}/verify/{registration}/proof', [App\Events\Controllers\PersonalEventController::class, 'verifyProof'])->name('events.verify.proof');
    // What the entry is being charged for. Same gate as the payment itself
    // (EventAccess::canVerifyPayments) — it writes the money record.
    Route::put('/events/{event:uuid}/verify/{registration}/fees', [App\Events\Controllers\PersonalEventController::class, 'verifyFees'])->name('events.verify.fees')->middleware('throttle:event-desk');

    // Run-day checklist, and the start it gates. Writing the list is the
    // organiser's (canManage); clearing an item is any appointed official's
    // (canOfficiate); starting — which locks the draw — is the organiser's
    // alone. Items bind by uuid, never their row id.
    Route::post('/events/{event:uuid}/checklist', [App\Events\Controllers\PersonalEventController::class, 'storeChecklistItem'])->name('events.checklist.store')->middleware('throttle:member-write');
    Route::put('/events/{event:uuid}/checklist/{checklistItem:uuid}', [App\Events\Controllers\PersonalEventController::class, 'toggleChecklistItem'])->name('events.checklist.toggle')->middleware('throttle:member-write');
    Route::delete('/events/{event:uuid}/checklist/{checklistItem:uuid}', [App\Events\Controllers\PersonalEventController::class, 'destroyChecklistItem'])->name('events.checklist.destroy')->middleware('throttle:member-write');
    Route::post('/events/{event:uuid}/start', [App\Events\Controllers\PersonalEventController::class, 'startEvent'])->name('events.start')->middleware('throttle:member-write');

    // Officials (the jury). Appointing is the organiser's call, so these are all
    // canManage-gated; being an official only ever grants arranging the draw.
    Route::get('/events/{event:uuid}/officials', [App\Events\Controllers\PersonalEventController::class, 'officials'])->name('events.officials');
    Route::post('/events/{event:uuid}/officials', [App\Events\Controllers\PersonalEventController::class, 'storeOfficial'])->name('events.officials.store')->middleware('throttle:member-write');
    // The other door: somebody the platform has never met, written down at the
    // desk. Mat roles only — see the controller.
    // The face on the sheet — the same photo flow a competitor's picture goes
    // through, stored against the APPOINTMENT.
    Route::post('/events/{event:uuid}/officials/{official}/photo', [App\Events\Controllers\PersonalEventController::class, 'officialPhoto'])->name('events.officials.photo')->whereNumber('official')->middleware('throttle:uploads');
    Route::post('/events/{event:uuid}/officials/person', [App\Events\Controllers\PersonalEventController::class, 'storeOfficialPerson'])->name('events.officials.person')->middleware('throttle:member-write');
    Route::put('/events/{event:uuid}/officials/{official}', [App\Events\Controllers\PersonalEventController::class, 'updateOfficial'])->name('events.officials.update')->whereNumber('official')->middleware('throttle:member-write');
    Route::delete('/events/{event:uuid}/officials/{official}', [App\Events\Controllers\PersonalEventController::class, 'destroyOfficial'])->name('events.officials.destroy')->middleware('throttle:member-write');
    // Bracket screen data + hand-arranging the draw. Generic to every bracketed
    // type (the package decides what a legal arrangement is), so this is one
    // surface rather than a route per sport.
    Route::get('/events/{event:uuid}/brackets/data', [App\Events\Controllers\PersonalEventController::class, 'bracketData'])->name('events.bracket.data')->middleware('throttle:120,1');
    /* The draw as an A4 PDF — the real board printed by headless Chromium
       (App\Events\Support\BracketPdf). The most expensive read there is, so
       organiser/jury only and throttled hard; 404 while the flag is off. */
    Route::get('/events/{event:uuid}/brackets/pdf', [App\Events\Controllers\BracketPdfController::class, 'show'])->name('events.bracket.pdf')->middleware('throttle:6,1');
    /* The tablet PIN — lends a scoring tablet the organiser's authority to edit
       the draw in an emergency (App\Events\Support\TabletDraw). Organiser only. */
    Route::get('/events/{event:uuid}/draw-pin', [App\Events\Controllers\DrawPinController::class, 'show'])->name('events.draw-pin')->middleware('throttle:60,1');
    Route::put('/events/{event:uuid}/draw-pin', [App\Events\Controllers\DrawPinController::class, 'update'])->name('events.draw-pin.update')->middleware('throttle:member-write');
    Route::delete('/events/{event:uuid}/draw-pin', [App\Events\Controllers\DrawPinController::class, 'destroy'])->name('events.draw-pin.destroy')->middleware('throttle:member-write');
    Route::put('/events/{event:uuid}/brackets/arrange', [App\Events\Controllers\PersonalEventController::class, 'arrangeBracket'])->name('events.bracket.arrange')->middleware('throttle:bracket-arrange');
    Route::put('/events/{event:uuid}/brackets/clear', [App\Events\Controllers\PersonalEventController::class, 'clearBracket'])->name('events.bracket.clear')->middleware('throttle:member-write');
    /* Whether THIS championship plays third place off. Re-cuts the play-off
       bouts on the way through, so the draw agrees with the setting the moment
       it changes — see App\Events\Support\ThirdPlaceSwitch. */
    Route::put('/events/{event:uuid}/brackets/third-place', [App\Events\Controllers\PersonalEventController::class, 'setThirdPlace'])->name('events.bracket.third-place')->middleware('throttle:member-write');
    // Admitting an athlete to a division that is already being fought. Throttled
    // with arranging rather than with ordinary writes: it is a desk action taken
    // in bursts on a competition morning, not a member write.
    Route::get('/events/{event:uuid}/brackets/admit-candidates', [App\Events\Controllers\PersonalEventController::class, 'lateEntryCandidates'])->name('events.bracket.candidates')->middleware('throttle:event-desk');
    Route::put('/events/{event:uuid}/brackets/admit', [App\Events\Controllers\PersonalEventController::class, 'admitLateEntry'])->name('events.bracket.admit')->middleware('throttle:bracket-arrange');

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
    Route::get('/events/{event:uuid}/divisions', [App\Events\Controllers\PersonalEventController::class, 'divisions'])->name('events.divisions')->middleware(['throttle:60,1', 'source-text']);
    Route::post('/events/{event:uuid}/divisions', [App\Events\Controllers\PersonalEventController::class, 'storeDivision'])->name('events.divisions.store')->middleware(['throttle:admin-write', 'source-text']);
    // The ORDER of the list. Declared before the {division} routes for
    // readability only — `order` is not a number, so it could never have
    // matched them. Organiser-only: this is arranging the event's own
    // structure, not arranging a draw.
    // Who a proposed division would hold. A READ that writes nothing, but
    // throttled like a lookup: it walks the whole entry list per call.
    Route::post('/events/{event:uuid}/divisions/preview', [App\Events\Controllers\PersonalEventController::class, 'previewDivision'])->name('events.divisions.preview')->middleware('throttle:120,1');
    Route::put('/events/{event:uuid}/divisions/order', [App\Events\Controllers\PersonalEventController::class, 'reorderDivisions'])->name('events.divisions.order')->middleware('throttle:admin-write');
    Route::patch('/events/{event:uuid}/divisions/{division}', [App\Events\Controllers\PersonalEventController::class, 'updateDivision'])->name('events.divisions.update')->whereNumber('division')->middleware(['throttle:admin-write', 'source-text']);
    Route::delete('/events/{event:uuid}/divisions/{division}', [App\Events\Controllers\PersonalEventController::class, 'destroyDivision'])->name('events.divisions.destroy')->whereNumber('division')->middleware('throttle:admin-write');
    Route::post('/events/{event:uuid}/divisions/{division}/duplicate', [App\Events\Controllers\PersonalEventController::class, 'duplicateDivision'])->name('events.divisions.duplicate')->whereNumber('division')->middleware('throttle:admin-write');
    Route::get('/events/{event:uuid}/divisions/{division}/candidates', [App\Events\Controllers\PersonalEventController::class, 'divisionCandidates'])->name('events.divisions.candidates')->whereNumber('division')->middleware('throttle:120,1');
    Route::put('/events/{event:uuid}/divisions/{division}/members', [App\Events\Controllers\PersonalEventController::class, 'updateDivisionMembers'])->name('events.divisions.members')->whereNumber('division')->middleware('throttle:event-desk');
    /*
     * Every group as it stands for ONE person — the enrolment sheet behind a
     * participant card. The mirror of `divisions.candidates`, which asks the
     * same question from the group's end; compatibility is computed server-side
     * in both, by the same DivisionRange, so the two can never disagree.
     *
     * Bound by the athlete's UUID because the card that opens it is on the
     * public entry list, where the public key is the only identifier present
     * (CLAUDE.md → Unpredictable Resource Identifiers). Authorisation is the
     * picker's own and is re-checked in the controller; the uuid is how it is
     * addressed, never why it is allowed.
     */
    Route::get('/events/{event:uuid}/people/{uuid}/divisions', [App\Events\Controllers\PersonalEventController::class, 'personDivisions'])->name('events.person.divisions')->whereUuid('uuid')->middleware('throttle:60,1');
    // Manager actions + outcome recording are owned by the event's TYPE PACKAGE
    // (CLAUDE.md → "Events Are Self-Contained Packages"): one route each, the
    // package decides which actions exist and what they do. Adding an event type
    // never adds a route here.
    Route::post('/events/{event:uuid}/actions/{action}', [App\Events\Controllers\PersonalEventController::class, 'performAction'])->name('events.action')->where('action', '[a-z_]{1,40}')->middleware('throttle:member-write');
    Route::post('/events/{event:uuid}/outcomes/{unit}', [App\Events\Controllers\PersonalEventController::class, 'recordOutcome'])->name('events.outcome')->whereNumber('unit')->middleware('throttle:member-write');
    Route::post('/events/{event:uuid}/expenses', [App\Events\Controllers\PersonalEventController::class, 'addExpense'])->name('events.expenses.add')->middleware('throttle:member-write');
    Route::delete('/events/{event:uuid}/expenses/{expense}', [App\Events\Controllers\PersonalEventController::class, 'deleteExpense'])->name('events.expenses.delete')->whereNumber('expense')->middleware('throttle:member-write');
    Route::put('/events/{event:uuid}/categories/{category}', [App\Events\Controllers\PersonalEventController::class, 'saveCategory'])->name('events.category.save')->middleware('throttle:member-write');
    // Club/coach entry — a squad in one submission. Each athlete still passes
    // the same gate as self-entry; this is convenience, not a bypass.
    // Run day: the athlete's countdown (and the coach's squad view of it).
    // Venue board — a hall screen for a mat (?mat=Mat+1) or the whole venue.
    Route::get('/events/{event:uuid}/board', [App\Events\Controllers\PersonalEventController::class, 'board'])->name('events.board');
    // Court display — rehearse the hall screen in a browser. The screen
    // itself never calls this: it renders a cached copy of the same view against
    // board state pushed over MQTT. Organiser-only, because the court comes
    // straight off the URL.
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
    Route::get('/events/{event:uuid}/next-up', [App\Events\Controllers\PersonalEventController::class, 'nextUp'])->name('events.next-up');
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
    Route::get('/events/{event:uuid}/entry-roster', [App\Events\Controllers\PersonalEventController::class, 'entryRoster'])->name('events.entry-roster')->middleware('throttle:60,1');
    Route::post('/events/{event:uuid}/entries', [App\Events\Controllers\PersonalEventController::class, 'storeEntries'])->name('events.entries')->middleware('throttle:admin-write');
    // The organiser's own lookup: anyone on the platform they may add to THIS
    // event. Separate from the roster above because the authority is different
    // (running this event, not administering a club) and because it reaches
    // wider — so it needs a query, honours the member's discovery opt-out, and
    // is throttled hard. See EntryService::searchPeople().
    Route::get('/events/{event:uuid}/entry-search', [App\Events\Controllers\PersonalEventController::class, 'entrySearch'])->name('events.entry-search')->middleware('throttle:30,1');
    // ...and taking them back out. Organiser only — entering an athlete is a
    // club's act, striking a name off the list is the competition's. Every rule
    // about whether an entry MAY go lives in EntryService::remove().
    Route::delete('/events/{event:uuid}/entries', [App\Events\Controllers\PersonalEventController::class, 'removeEntries'])->name('events.entries.destroy')->middleware('throttle:admin-write');
    // Turning the public page on or off for this event. Organiser only, and
    // the only writer of `entry_mode` (EVENTS-PUBLIC-ENTRY.md, Phase B).
    Route::post('/events/{event:uuid}/public', [App\Events\Controllers\PersonalEventController::class, 'setEntryMode'])->name('events.public.toggle')->middleware('throttle:admin-write');

    // The picture the public cover opens onto — `images[0]`, replaced in place.
    // Byte-validated by StoresBase64Images, filed under events/{uuid}/branding
    // by StoragePath, and only writable by somebody who may manage the event.
    Route::post('/events/{event:uuid}/cover', [App\Events\Controllers\PersonalEventController::class, 'setCover'])->name('events.cover.store')->middleware('throttle:uploads');
    Route::delete('/events/{event:uuid}/cover', [App\Events\Controllers\PersonalEventController::class, 'clearCover'])->name('events.cover.destroy')->middleware('throttle:admin-write');
    // Whether a public entry needs a yes. A separate decision from publishing
    // the page, so an unreviewed door never opens as a side effect of sharing.
    Route::post('/events/{event:uuid}/public/auto-accept', [App\Events\Controllers\PersonalEventController::class, 'setPublicAutoAccept'])->name('events.public.auto-accept')->middleware('throttle:admin-write');
    // When the draw becomes readable: always | start_day | hidden. Disclosure
    // only — it never changes who may ARRANGE a draw, and the organiser and
    // their officials read it at every setting.
    Route::post('/events/{event:uuid}/draw-reveal', [App\Events\Controllers\PersonalEventController::class, 'setDrawReveal'])->name('events.draw-reveal')->middleware('throttle:admin-write');
    // Club payments — the proofs clubs sent, and the organiser's decision (ClubPaymentsController).
    Route::get('/events/{event:uuid}/club-payments', [App\Events\Controllers\ClubPaymentsController::class, 'index'])->name('events.club-payments')->middleware('throttle:60,1');
    Route::get('/events/{event:uuid}/club-payments/{invoice}/proof', [App\Events\Controllers\ClubPaymentsController::class, 'proof'])->whereUuid('invoice')->name('events.club-payments.proof');
    Route::post('/events/{event:uuid}/club-payments/{invoice}', [App\Events\Controllers\ClubPaymentsController::class, 'decide'])->whereUuid('invoice')->name('events.club-payments.decide')->middleware('throttle:admin-write');
    // Listed or hidden — whether the event appears in any list (ClubEvent::isHidden). Organiser only.
    Route::post('/events/{event:uuid}/visibility', [App\Events\Controllers\PersonalEventController::class, 'setVisibility'])->name('events.visibility')->middleware('throttle:admin-write');
    // How athletes enter — individuals, clubs, or both (App\Events\Support\EntryChannels). Organiser only.
    Route::post('/events/{event:uuid}/entry-channels', [App\Events\Controllers\PersonalEventController::class, 'setEntryChannels'])->name('events.entry-channels')->middleware('throttle:admin-write');
    // The rule book an event runs under: category and belt systems (App\Events\Support\CategorySystems).
    Route::get('/category-systems', [App\Events\Controllers\CategorySystemController::class, 'index'])->name('category-systems')->middleware('throttle:60,1');
    Route::post('/category-systems', [App\Events\Controllers\CategorySystemController::class, 'store'])->name('category-systems.store')->middleware('throttle:admin-write');
    Route::put('/events/{event:uuid}/rulebook', [App\Events\Controllers\CategorySystemController::class, 'update'])->name('events.rulebook')->middleware('throttle:admin-write');
    // Paid featuring on /me/events — the organiser's side (App\Events\Support\EventFeaturing).
    Route::get('/events/{event:uuid}/feature', [App\Events\Controllers\EventFeatureController::class, 'show'])->name('events.feature')->middleware('throttle:60,1');
    Route::post('/events/{event:uuid}/feature', [App\Events\Controllers\EventFeatureController::class, 'store'])->name('events.feature.store')->middleware('throttle:uploads');

    /*
     * ⚠️ These live under /day-order, NOT /running-order, and must stay there.
     * `/running-order` already belongs to the BOUT order board above
     * (`events.order`, `events.order.move`) — two routes registered on one
     * URI+method do not coexist: the second silently REPLACES the first and its
     * NAME stops resolving, so every `route('me.events.order')` in a view
     * throws. That shipped for an hour on 2026-09-17 and 500'd the bout board.
     *
     * The two boards are different things and both are wanted: that one orders
     * BOUTS on MATS, this one orders the PARTS OF THE DAY — the divisions plus
     * the ceremonies and breaks that have no bouts at all.
     *
     * The running order: what happens on a competition day, in order.
     *
     * The organiser arranges PARTS; every clock time is worked out from the
     * day's start, the draw and the bout length (App\Events\Support\RunSheet).
     * Organiser-only throughout — a visitor reads the finished order on the
     * public event page, never through these.
     *
     * The slot binds on its uuid, never its id (CLAUDE.md → Unpredictable
     * Resource Identifiers), and every write re-checks that the slot belongs to
     * the event in the URL.
     */
    Route::get('/events/{event:uuid}/day-order', [App\Events\Controllers\PersonalEventController::class, 'runOrder'])->name('events.run-order')->middleware('throttle:60,1');
    Route::get('/events/{event:uuid}/day-order/data', [App\Events\Controllers\PersonalEventController::class, 'runOrderData'])->name('events.run-order.data')->middleware('throttle:120,1');
    Route::post('/events/{event:uuid}/day-order', [App\Events\Controllers\PersonalEventController::class, 'storeRunSlot'])->name('events.run-order.store')->middleware('throttle:admin-write');
    Route::put('/events/{event:uuid}/day-order', [App\Events\Controllers\PersonalEventController::class, 'reorderRunOrder'])->name('events.run-order.reorder')->middleware('throttle:admin-write');
    Route::put('/events/{event:uuid}/day-order/{slot}', [App\Events\Controllers\PersonalEventController::class, 'updateRunSlot'])->name('events.run-order.update')->whereUuid('slot')->middleware('throttle:admin-write');
    Route::delete('/events/{event:uuid}/day-order/{slot}', [App\Events\Controllers\PersonalEventController::class, 'destroyRunSlot'])->name('events.run-order.destroy')->whereUuid('slot')->middleware('throttle:admin-write');
    // The queue of strangers who followed that link and asked to compete. The
    // accept is the gate — until it happens the request is an entry in nothing
    // (EVENTS-PUBLIC-ENTRY.md, Door C).
    Route::get('/events/{event:uuid}/public-entries', [App\Events\Controllers\PersonalEventController::class, 'publicEntries'])->name('events.public-entries')->middleware('throttle:60,1');
    Route::post('/events/{event:uuid}/public-entries/{entry}/accept', [App\Events\Controllers\PersonalEventController::class, 'acceptPublicEntry'])->name('events.public-entries.accept')->whereUuid('entry')->middleware('throttle:admin-write');
    Route::post('/events/{event:uuid}/public-entries/{entry}/decline', [App\Events\Controllers\PersonalEventController::class, 'declinePublicEntry'])->name('events.public-entries.decline')->whereUuid('entry')->middleware('throttle:admin-write');
    // Entering somebody not on the roster: a NAME commits the entry, and a
    // single-use link lets the athlete supply what the coach could only have
    // guessed at (Documentation/EVENTS-PUBLIC-ENTRY.md, Door B).
    Route::post('/events/{event:uuid}/entries/unnamed', [App\Events\Controllers\PersonalEventController::class, 'storeUnnamedEntry'])->name('events.entries.unnamed')->middleware('throttle:admin-write');
    // The pre-draw duplicate check: likely pairs among the entrants, and settling one (EventDuplicates).
    Route::get('/events/{event:uuid}/duplicates', [App\Events\Controllers\EventDuplicatesController::class, 'index'])->name('events.duplicates')->middleware('throttle:60,1');
    Route::post('/events/{event:uuid}/duplicates', [App\Events\Controllers\EventDuplicatesController::class, 'resolve'])->name('events.duplicates.resolve')->middleware('throttle:admin-write');
    // The entry desk issues a one-time sign-in code for an athlete who forgot their password (DeskSignInCode).
    Route::post('/events/{event:uuid}/entries/{entry}/sign-in-code', [App\Events\Controllers\EntrySignInCodeController::class, 'store'])->name('events.entries.sign-in-code')->whereNumber('entry')->middleware('throttle:admin-write');
    Route::get('/events/{event:uuid}/entry-links', [App\Events\Controllers\PersonalEventController::class, 'entryClaimLinks'])->name('events.entry-links')->middleware('throttle:60,1');
    Route::delete('/events/{event:uuid}/entry-links/{claim}', [App\Events\Controllers\PersonalEventController::class, 'revokeEntryClaim'])->name('events.entry-links.revoke')->whereUuid('claim')->middleware('throttle:admin-write');
    Route::post('/events/{event:uuid}/entry-links/{claim}/relink', [App\Events\Controllers\PersonalEventController::class, 'regenerateEntryClaim'])->name('events.entry-links.relink')->whereUuid('claim')->middleware('throttle:admin-write');
    // A club's say over its own name: who has entered themselves claiming it,
    // and rejecting a claim (which never removes the athlete from the event).
    Route::get('/events/{event:uuid}/claims', [App\Events\Controllers\PersonalEventController::class, 'entryClaims'])->name('events.claims');
    Route::post('/events/{event:uuid}/claims/{user}/disown', [App\Events\Controllers\PersonalEventController::class, 'disownClaim'])->name('events.claims.disown')->whereNumber('user')->middleware('throttle:admin-write');
    Route::post('/events/{event:uuid}/register', [App\Events\Controllers\PersonalEventController::class, 'register'])->name('events.register')->middleware('throttle:member-write');
    Route::post('/events/{event:uuid}/ticket', [App\Events\Controllers\PersonalEventController::class, 'ticket'])->name('events.ticket')->middleware('throttle:member-write');
    Route::delete('/events/{event:uuid}/register', [App\Events\Controllers\PersonalEventController::class, 'cancel'])->name('events.cancel')->middleware('throttle:member-write');
    // Owner moderation of registrations (remove / block this event / blacklist club-wide).
    Route::post('/events/{event:uuid}/participants/{user}/moderate', [App\Events\Controllers\PersonalEventController::class, 'moderateParticipant'])->name('events.participant.moderate')->whereNumber('user')->middleware('throttle:member-write');
    Route::delete('/events/{event:uuid}/bans/{user}', [App\Events\Controllers\PersonalEventController::class, 'liftBan'])->name('events.ban.lift')->whereNumber('user')->middleware('throttle:member-write');
    /*
     * Who is barred, read by the console's own sheet.
     *
     * A door of its own rather than a payload on the console, so a moderated
     * member's name is only sent to a browser that ASKED for it — the list used
     * to ride in the page's client-side search index on every load, drawn
     * nowhere, which is disclosure without a feature (fixed 2026-09-15).
     *
     * Organiser only, and re-checked in the controller: this is the one list on
     * the event that is about people's conduct rather than their competing.
     */
    Route::get('/events/{event:uuid}/bans', [App\Events\Controllers\PersonalEventController::class, 'bans'])->name('events.bans')->middleware('throttle:60,1');

    /*
     * The clubs standing behind the event.
     *
     * Reads are cheap and the organiser's console polls none of them; the
     * writes are throttled like every other write on the platform. Every
     * method re-checks that the actor may manage this event — the group's
     * auth stack says WHO is signed in, never which events are theirs.
     */
    // The screen itself, and the same list as JSON for the panel's own updates.
    // Pairs and teams (EventSides) — organiser only, JSON, re-authorised in the
    // controller. Sides are addressed by uuid inside their event.
    Route::get('/events/{event:uuid}/sides', [App\Events\Controllers\EventSidesController::class, 'index'])
        ->name('events.sides')->middleware('throttle:60,1')->whereUuid('event');
    Route::post('/events/{event:uuid}/sides', [App\Events\Controllers\EventSidesController::class, 'store'])
        ->name('events.sides.store')->middleware('throttle:admin-write')->whereUuid('event');
    Route::patch('/events/{event:uuid}/sides/{side}', [App\Events\Controllers\EventSidesController::class, 'update'])
        ->name('events.sides.update')->middleware('throttle:admin-write')->whereUuid('event')->whereUuid('side');
    Route::delete('/events/{event:uuid}/sides/{side}', [App\Events\Controllers\EventSidesController::class, 'destroy'])
        ->name('events.sides.destroy')->middleware('throttle:admin-write')->whereUuid('event')->whereUuid('side');
    Route::post('/events/{event:uuid}/sides/{side}/members', [App\Events\Controllers\EventSidesController::class, 'addMember'])
        ->name('events.sides.members.add')->middleware('throttle:admin-write')->whereUuid('event')->whereUuid('side');
    Route::delete('/events/{event:uuid}/sides/{side}/members/{member}', [App\Events\Controllers\EventSidesController::class, 'removeMember'])
        ->name('events.sides.members.remove')->middleware('throttle:admin-write')->whereUuid('event')->whereUuid('side')->whereNumber('member');

    Route::get('/events/{event:uuid}/clubs', [App\Events\Controllers\EventClubsController::class, 'page'])
        ->name('events.clubs')->middleware('throttle:60,1')->whereUuid('event');

    Route::get('/events/{event:uuid}/clubs/list', [App\Events\Controllers\EventClubsController::class, 'index'])
        ->name('events.clubs.list')->middleware('throttle:60,1')->whereUuid('event');

    Route::get('/events/{event:uuid}/clubs/search', [App\Events\Controllers\EventClubsController::class, 'search'])
        ->name('events.clubs.search')->middleware('throttle:30,1')->whereUuid('event');

    Route::post('/events/{event:uuid}/clubs', [App\Events\Controllers\EventClubsController::class, 'store'])
        ->name('events.clubs.store')->middleware('throttle:20,1')->whereUuid('event');

    Route::post('/events/{event:uuid}/clubs/invite', [App\Events\Controllers\EventClubsController::class, 'invite'])
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
    Route::put('/events/{event:uuid}/clubs/{eventClub}', [App\Events\Controllers\EventClubsController::class, 'update'])
        ->name('events.clubs.update')->middleware('throttle:20,1')->whereUuid('event');

    Route::delete('/events/{event:uuid}/clubs/{eventClub}', [App\Events\Controllers\EventClubsController::class, 'destroy'])
        ->name('events.clubs.destroy')->middleware('throttle:20,1')->whereUuid('event');

    Route::get('/events/{event:uuid}/clubs/entrants', [App\Events\Controllers\EventClubsController::class, 'entrants'])
        ->name('events.clubs.entrants')->middleware('throttle:60,1')->whereUuid('event');

    Route::post('/events/{event:uuid}/clubs/{eventClub}/athletes', [App\Events\Controllers\EventClubsController::class, 'assign'])
        ->name('events.clubs.athletes')->middleware('throttle:30,1')->whereUuid('event');

    // Turn the temporary clubs that competed into real ones. Refused before the
    // event is over, because "who competed" is not a fact until then. Nothing
    // runs this on a timer — see App\Events\Commands\PromoteEventClubs.
    Route::post('/events/{event:uuid}/clubs/promote', [App\Events\Controllers\EventClubsController::class, 'promote'])
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
