<?php

/**
 * The COPIED event flow.
 *
 * These are `routes/web.php`'s `/e/{uuid}` block and `app/Scoreboard/routes.php`'s
 * `/me/events` block, copied on 2026-09-05 and changed in exactly three ways:
 *
 *   1. the five controllers that were copied into this module point at the
 *      COPIES; everything else (bout video, cameras, hall screens) still points
 *      at the one shared implementation, because those were not copied;
 *   2. the paths and names are re-rooted — `/testcode/me/events/…` named
 *      `testcode.me.events.…`, and `/testcode/e/…` named `testcode.e…` — so a
 *      copied screen links to other copied screens and never back to the live
 *      ones;
 *   3. every group carries `sandbox-event`, which 404s the moment the event in
 *      the URL is not a sandbox twin. That is what makes this safe: the copied
 *      code is not merely unused by real events, it is unable to reach one.
 *
 * The originals are untouched and still serve every real event.
 */

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| The copied PUBLIC event page — /testcode/e/{uuid}
|--------------------------------------------------------------------------
*/
Route::prefix('testcode/e')->name('testcode.')->middleware('sandbox-event')->group(function () {

/*
|--------------------------------------------------------------------------
| The event page anybody may open
|--------------------------------------------------------------------------
| Documentation/EVENTS-PUBLIC-ENTRY.md, Phase B. Opt-in per event
| (`entry_mode = public`) and READ-ONLY — the whole surface is a GET. An event
| that was not deliberately made public 404s exactly as an unknown uuid does,
| so the address cannot be used to discover which events exist.
*/
Route::get('/{event:uuid}', [App\EventLab\Controllers\PublicEventController::class, 'show'])
    ->name('e')->middleware('throttle:60,1')->whereUuid('event');

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
Route::get('/{event:uuid}/app.webmanifest', [App\EventLab\Controllers\PublicEventController::class, 'manifest'])
    ->name('e.manifest')->middleware('throttle:60,1')->whereUuid('event');
Route::get('/{event:uuid}/icon-{size}.png', [App\EventLab\Controllers\PublicEventController::class, 'icon'])
    ->name('e.icon')->middleware('throttle:120,1')->whereUuid('event')->whereNumber('size');

// The event's attached files — rulebook, entry form, schedule. Open only while
// the organiser has the public page ON, and throttled: a download is cheap to
// ask for and expensive to serve. A separate door from the member route, which
// keeps its auth stack untouched.
Route::get('/{event:uuid}/documents/{document:uuid}', [App\EventLab\Controllers\PublicEventController::class, 'document'])
    ->name('e.document')->middleware('throttle:30,1')->whereUuid('event')->whereUuid('document');

// The draw, as the hall wall shows it: read-only, no entrants bench, no faces
// and nobody's payment state — App\Events\Support\PublicEvent::draw() decides
// what survives. A separate door from `me.events.bracket.data`, which keeps its
// auth stack and its organiser's view untouched. Throttled a little above the
// page itself because the board re-fetches when a division is switched.
Route::get('/{event:uuid}/draw/data', [App\EventLab\Controllers\PublicEventController::class, 'drawData'])
    ->name('e.draw.data')->middleware('throttle:120,1')->whereUuid('event');

// The four doors out of the public event page — the draw, the officiating
// sheet, the footage and the entry list, each on its own page exactly as the
// member page's four rows are. ONE route with the section in the path rather
// than four: the four differ only in which body they render, and a `where`
// allowlist means an unknown section 404s at the router instead of reaching a
// view name built from user input.
Route::get('/{event:uuid}/{section}', [App\EventLab\Controllers\PublicEventController::class, 'section'])
    ->name('e.section')->middleware('throttle:60,1')->whereUuid('event')
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
Route::get('/{event:uuid}/manage', [App\EventLab\Controllers\PublicEventController::class, 'manage'])
    ->name('e.manage')->middleware('throttle:30,1')->whereUuid('event');
Route::post('/{event:uuid}/manage', [App\EventLab\Controllers\PublicEventController::class, 'signIn'])
    ->name('e.manage.signin')->middleware('throttle:login')->whereUuid('event');

// Signing OUT, without leaving the event. The platform's /logout lands on `/`,
// which for somebody using this as an installed app means being thrown out of
// the app entirely; this one lands on the poster.
Route::post('/{event:uuid}/sign-out', [App\EventLab\Controllers\PublicEventController::class, 'signOut'])
    ->name('e.sign-out')->middleware('throttle:30,1')->whereUuid('event');

Route::get('/{event:uuid}/enter', [App\EventLab\Controllers\PublicEntryController::class, 'show'])
    ->name('e.enter')->middleware('throttle:30,1')->whereUuid('event');
Route::post('/{event:uuid}/enter', [App\EventLab\Controllers\PublicEntryController::class, 'store'])
    ->name('e.enter.store')->middleware('throttle:5,1')->whereUuid('event');

// The same door for somebody who already has an account: they signed in
// through the ordinary login and came back, so this takes no name, no email
// and no password — only what they compete at. Throttled like the other one,
// and it 404s for a signed-out caller rather than redirecting an XHR to a
// login form.
Route::post('/{event:uuid}/enter/mine', [App\EventLab\Controllers\PublicEntryController::class, 'storeMine'])
    ->name('e.enter.mine')->middleware('throttle:10,1')->whereUuid('event');

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
| re-checked inside App\EventLab\Support\EntryEditor on every write.
*/
Route::middleware('auth')->group(function () {
    Route::get('/{event:uuid}/my-entry', [App\EventLab\Controllers\EntryPanelController::class, 'show'])
        ->name('e.my-entry')->middleware('throttle:60,1')->whereUuid('event');

    // What a live update re-fetches. Its own (looser) limit because a realtime
    // nudge can arrive several times in a busy minute at a weigh-in desk.
    Route::get('/{event:uuid}/my-entry/state', [App\EventLab\Controllers\EntryPanelController::class, 'state'])
        ->name('e.my-entry.state')->middleware('throttle:120,1')->whereUuid('event');

    Route::put('/{event:uuid}/my-entry', [App\EventLab\Controllers\EntryPanelController::class, 'update'])
        ->name('e.my-entry.update')->middleware('throttle:20,1')->whereUuid('event');

    // Handing in a receipt. A write, throttled like one, and it marks nothing
    // paid — the organiser still decides.
    Route::post('/{event:uuid}/my-entry/payment-proof', [App\EventLab\Controllers\EntryPanelController::class, 'paymentProof'])
        ->name('e.my-entry.payment-proof')->middleware('throttle:10,1')->whereUuid('event');

    Route::post('/{event:uuid}/my-entry/withdraw', [App\EventLab\Controllers\EntryPanelController::class, 'withdraw'])
        ->name('e.my-entry.withdraw')->middleware('throttle:10,1')->whereUuid('event');

    Route::delete('/{event:uuid}/my-entry/withdraw', [App\EventLab\Controllers\EntryPanelController::class, 'withdrawCancel'])
        ->name('e.my-entry.withdraw.cancel')->middleware('throttle:10,1')->whereUuid('event');
});

// The club picker's search. Open because a club's name, mark and country are
// already public on /explore — what keeps it from being a scraping door is its
// shape: two characters minimum, ten results, throttled, four fields, and a
// SLUG rather than an id.
Route::get('/{event:uuid}/enter/clubs', [App\EventLab\Controllers\PublicEntryController::class, 'clubs'])
    ->name('e.enter.clubs')->middleware('throttle:30,1')->whereUuid('event');

});

/*
|--------------------------------------------------------------------------
| The copied MEMBER event flow — /testcode/me/events/…
|--------------------------------------------------------------------------
| The original group's own middleware and prefix, kept verbatim, with the
| sandbox guard added.
*/
Route::middleware(['auth', 'verified', 'two-factor', 'sandbox-event'])
    ->prefix('testcode/me')->name('testcode.me.')->group(function () {
// The /me member surfaces (home, feed, schedule, family tree, profile,
// packages, payments, videos, people, settings, locale) live in the Members
// module: app/Members/routes-member.php, registered by ModuleServiceProvider
// under /me with the `me.` prefix and the same middleware stack.
    // Events — real, DB-backed (club_events).
    Route::get('/events', [App\EventLab\Controllers\PersonalEventController::class, 'index'])->name('events');
    Route::get('/events/create', [App\EventLab\Controllers\PersonalEventController::class, 'create'])->name('events.create');
    Route::post('/events', [App\EventLab\Controllers\PersonalEventController::class, 'store'])->name('events.store')->middleware('throttle:member-write');
    Route::get('/events/{event:uuid}', [App\EventLab\Controllers\PersonalEventController::class, 'show'])->name('events.show');
    // The organiser's console. `show` is what a visitor came to read; this is
    // what the people running the event came to do. Organiser or an appointed
    // official only — the action refuses everyone else.
    Route::get('/events/{event:uuid}/manage', [App\EventLab\Controllers\PersonalEventController::class, 'manage'])->name('events.manage');
    Route::get('/events/{event:uuid}/edit', [App\EventLab\Controllers\PersonalEventController::class, 'edit'])->name('events.edit');
    Route::put('/events/{event:uuid}', [App\EventLab\Controllers\PersonalEventController::class, 'update'])->name('events.update')->middleware('throttle:member-write');
    Route::patch('/events/{event:uuid}/cancel', [App\EventLab\Controllers\PersonalEventController::class, 'cancelEvent'])->name('events.cancel-event')->middleware('throttle:member-write');
    Route::put('/events/{event:uuid}/results', [App\EventLab\Controllers\PersonalEventController::class, 'setResults'])->name('events.results')->middleware('throttle:member-write');
    Route::delete('/events/{event:uuid}', [App\EventLab\Controllers\PersonalEventController::class, 'destroy'])->name('events.destroy')->middleware('throttle:member-write');
    Route::get('/events/{event:uuid}/brackets', [App\EventLab\Controllers\PersonalEventController::class, 'bracket'])->name('events.bracket');

    // One bout, and where in the event it sat — the page a match video on the
    // video platform links back to (Documentation/VIDEO-INTEGRATION.md §6.6).
    // Keyed by the EVENT's uuid plus the bout's match number: event_matches has
    // no public identifier of its own, and the unguessable part of the link is
    // the event uuid, which the viewer already holds.
    Route::get('/events/{event:uuid}/bout/{matchNo}', [App\EventLab\Controllers\PersonalEventController::class, 'bout'])
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
    // Deleting the footage itself — platform staff only, enforced in the
    // controller. Throttled like any other destructive write: competition video
    // cannot be filmed again, so this is the one button on the page with no
    // undo behind it.
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
    Route::get('/events/{event:uuid}/gallery', [App\EventLab\Controllers\PersonalEventController::class, 'gallery'])->name('events.gallery');
    Route::get('/events/{event:uuid}/gallery/data', [App\EventLab\Controllers\PersonalEventController::class, 'galleryData'])
        ->name('events.gallery.data')->middleware('throttle:media-read');
    // Organiser corrections to one bout: corners, scores, winner, the names on
    // the sheet, and the video link. The mat is authoritative while a bout is
    // being fought; this is authoritative once it is finished, and every change
    // is appended to the officiating log.
    // The entrants who may stand in one bout: this event, this bout's category.
    Route::get('/events/{event:uuid}/bout/{matchNo}/competitors', [App\EventLab\Controllers\PersonalEventController::class, 'boutCompetitors'])
        ->whereNumber('matchNo')->name('events.bout.competitors');
    Route::put('/events/{event:uuid}/bout/{matchNo}', [App\EventLab\Controllers\PersonalEventController::class, 'updateBout'])
        ->whereNumber('matchNo')->name('events.bout.update')->middleware('throttle:member-write');
    // The same board, full screen and free of chrome, for organisers arranging a
    // draw. Redirects back to the bracket for anyone who may not arrange.
    Route::get('/events/{event:uuid}/brackets/manage', [App\EventLab\Controllers\PersonalEventController::class, 'manageBracket'])->name('events.bracket.manage');

    // Who's joined — the roster that used to render inline on the event screen.
    Route::get('/events/{event:uuid}/people', [App\EventLab\Controllers\PersonalEventController::class, 'people'])->name('events.people');

    // The officiating sheet — who is running the competition. Reading only, open
    // to anyone the event is visible to; appointing lives on the edit screen and
    // the appointment paperwork (email, phone, fee) stays on events.officials,
    // which is organiser-guarded.
    Route::get('/events/{event:uuid}/officiating', [App\EventLab\Controllers\PersonalEventController::class, 'officiating'])->name('events.officiating');

    // Documents attached to an event (rulebook, entry form, schedule).
    // Upload/delete are organiser-only; download is anyone the event reaches —
    // each is re-checked in the controller, never inferred from the URL.
    Route::post('/events/{event:uuid}/documents', [App\EventLab\Controllers\EventDocumentController::class, 'store'])->name('events.documents.store')->middleware('throttle:uploads');
    Route::get('/events/{event:uuid}/documents/{document:uuid}', [App\EventLab\Controllers\EventDocumentController::class, 'download'])->name('events.documents.download');
    Route::delete('/events/{event:uuid}/documents/{document:uuid}', [App\EventLab\Controllers\EventDocumentController::class, 'destroy'])->name('events.documents.destroy')->middleware('throttle:member-write');
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
    | Both halves call the same App\EventLab\Support\EntryEditor, so there is one
    | set of rules about what may change and when, not two that drift.
    */
    Route::get('/events/{event:uuid}/entrants/{registration}', [App\EventLab\Controllers\EntryPanelController::class, 'entrant'])
        ->name('events.entrant')->whereNumber('registration')->middleware('throttle:120,1');

    Route::put('/events/{event:uuid}/entrants/{registration}', [App\EventLab\Controllers\EntryPanelController::class, 'updateEntrant'])
        ->name('events.entrant.update')->whereNumber('registration')->middleware('throttle:member-write');

    // The withdrawal queue. An athlete asks to come out; the organiser decides.
    // Nobody leaves a bracket without the person running it knowing.
    Route::get('/events/{event:uuid}/withdrawals', [App\EventLab\Controllers\EntryPanelController::class, 'withdrawals'])
        ->name('events.withdrawals')->middleware('throttle:120,1');

    Route::post('/events/{event:uuid}/withdrawals/{withdrawal}', [App\EventLab\Controllers\EntryPanelController::class, 'decideWithdrawal'])
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
    Route::post('/events/{event:uuid}/competitors/{registration}/photo', [App\EventLab\Controllers\PersonalEventController::class, 'competitorPhoto'])
        ->name('events.competitors.photo')->middleware('throttle:uploads');
    Route::delete('/events/{event:uuid}/competitors/{registration}/photo', [App\EventLab\Controllers\PersonalEventController::class, 'competitorPhotoDestroy'])
        ->name('events.competitors.photo.destroy')->middleware('throttle:member-write');
    Route::put('/events/{event:uuid}/verify/{registration}/weigh-in', [App\EventLab\Controllers\PersonalEventController::class, 'verifyWeighIn'])->name('events.verify.weigh-in')->middleware('throttle:member-write');
    Route::put('/events/{event:uuid}/verify/{registration}/payment', [App\EventLab\Controllers\PersonalEventController::class, 'verifyPayment'])->name('events.verify.payment')->middleware('throttle:member-write');
    Route::get('/events/{event:uuid}/verify/{registration}/proof', [App\EventLab\Controllers\PersonalEventController::class, 'verifyProof'])->name('events.verify.proof');

    // Run-day checklist, and the start it gates. Writing the list is the
    // organiser's (canManage); clearing an item is any appointed official's
    // (canOfficiate); starting — which locks the draw — is the organiser's
    // alone. Items bind by uuid, never their row id.
    Route::post('/events/{event:uuid}/checklist', [App\EventLab\Controllers\PersonalEventController::class, 'storeChecklistItem'])->name('events.checklist.store')->middleware('throttle:member-write');
    Route::put('/events/{event:uuid}/checklist/{checklistItem:uuid}', [App\EventLab\Controllers\PersonalEventController::class, 'toggleChecklistItem'])->name('events.checklist.toggle')->middleware('throttle:member-write');
    Route::delete('/events/{event:uuid}/checklist/{checklistItem:uuid}', [App\EventLab\Controllers\PersonalEventController::class, 'destroyChecklistItem'])->name('events.checklist.destroy')->middleware('throttle:member-write');
    Route::post('/events/{event:uuid}/start', [App\EventLab\Controllers\PersonalEventController::class, 'startEvent'])->name('events.start')->middleware('throttle:member-write');

    // Officials (the jury). Appointing is the organiser's call, so these are all
    // canManage-gated; being an official only ever grants arranging the draw.
    Route::get('/events/{event:uuid}/officials', [App\EventLab\Controllers\PersonalEventController::class, 'officials'])->name('events.officials');
    Route::post('/events/{event:uuid}/officials', [App\EventLab\Controllers\PersonalEventController::class, 'storeOfficial'])->name('events.officials.store')->middleware('throttle:member-write');
    Route::put('/events/{event:uuid}/officials/{official}', [App\EventLab\Controllers\PersonalEventController::class, 'updateOfficial'])->name('events.officials.update')->whereNumber('official')->middleware('throttle:member-write');
    Route::delete('/events/{event:uuid}/officials/{official}', [App\EventLab\Controllers\PersonalEventController::class, 'destroyOfficial'])->name('events.officials.destroy')->middleware('throttle:member-write');
    // Bracket screen data + hand-arranging the draw. Generic to every bracketed
    // type (the package decides what a legal arrangement is), so this is one
    // surface rather than a route per sport.
    Route::get('/events/{event:uuid}/brackets/data', [App\EventLab\Controllers\PersonalEventController::class, 'bracketData'])->name('events.bracket.data')->middleware('throttle:120,1');
    Route::put('/events/{event:uuid}/brackets/arrange', [App\EventLab\Controllers\PersonalEventController::class, 'arrangeBracket'])->name('events.bracket.arrange')->middleware('throttle:bracket-arrange');
    Route::put('/events/{event:uuid}/brackets/clear', [App\EventLab\Controllers\PersonalEventController::class, 'clearBracket'])->name('events.bracket.clear')->middleware('throttle:member-write');

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
    Route::get('/events/{event:uuid}/divisions', [App\EventLab\Controllers\PersonalEventController::class, 'divisions'])->name('events.divisions')->middleware('throttle:60,1');
    Route::post('/events/{event:uuid}/divisions', [App\EventLab\Controllers\PersonalEventController::class, 'storeDivision'])->name('events.divisions.store')->middleware('throttle:admin-write');
    Route::patch('/events/{event:uuid}/divisions/{division}', [App\EventLab\Controllers\PersonalEventController::class, 'updateDivision'])->name('events.divisions.update')->whereNumber('division')->middleware('throttle:admin-write');
    Route::delete('/events/{event:uuid}/divisions/{division}', [App\EventLab\Controllers\PersonalEventController::class, 'destroyDivision'])->name('events.divisions.destroy')->whereNumber('division')->middleware('throttle:admin-write');
    Route::get('/events/{event:uuid}/divisions/{division}/candidates', [App\EventLab\Controllers\PersonalEventController::class, 'divisionCandidates'])->name('events.divisions.candidates')->whereNumber('division')->middleware('throttle:120,1');
    Route::put('/events/{event:uuid}/divisions/{division}/members', [App\EventLab\Controllers\PersonalEventController::class, 'updateDivisionMembers'])->name('events.divisions.members')->whereNumber('division')->middleware('throttle:admin-write');
    // Manager actions + outcome recording are owned by the event's TYPE PACKAGE
    // (CLAUDE.md → "Events Are Self-Contained Packages"): one route each, the
    // package decides which actions exist and what they do. Adding an event type
    // never adds a route here.
    Route::post('/events/{event:uuid}/actions/{action}', [App\EventLab\Controllers\PersonalEventController::class, 'performAction'])->name('events.action')->where('action', '[a-z_]{1,40}')->middleware('throttle:member-write');
    Route::post('/events/{event:uuid}/outcomes/{unit}', [App\EventLab\Controllers\PersonalEventController::class, 'recordOutcome'])->name('events.outcome')->whereNumber('unit')->middleware('throttle:member-write');
    Route::post('/events/{event:uuid}/expenses', [App\EventLab\Controllers\PersonalEventController::class, 'addExpense'])->name('events.expenses.add')->middleware('throttle:member-write');
    Route::delete('/events/{event:uuid}/expenses/{expense}', [App\EventLab\Controllers\PersonalEventController::class, 'deleteExpense'])->name('events.expenses.delete')->whereNumber('expense')->middleware('throttle:member-write');
    Route::put('/events/{event:uuid}/categories/{category}', [App\EventLab\Controllers\PersonalEventController::class, 'saveCategory'])->name('events.category.save')->middleware('throttle:member-write');
    // Club/coach entry — a squad in one submission. Each athlete still passes
    // the same gate as self-entry; this is convenience, not a bypass.
    // Run day: the athlete's countdown (and the coach's squad view of it).
    // Venue board — a hall screen for a mat (?mat=Mat+1) or the whole venue.
    Route::get('/events/{event:uuid}/board', [App\EventLab\Controllers\PersonalEventController::class, 'board'])->name('events.board');
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
    Route::get('/events/{event:uuid}/next-up', [App\EventLab\Controllers\PersonalEventController::class, 'nextUp'])->name('events.next-up');
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
    Route::get('/events/{event:uuid}/entry-roster', [App\EventLab\Controllers\PersonalEventController::class, 'entryRoster'])->name('events.entry-roster')->middleware('throttle:60,1');
    Route::post('/events/{event:uuid}/entries', [App\EventLab\Controllers\PersonalEventController::class, 'storeEntries'])->name('events.entries')->middleware('throttle:admin-write');
    // ...and taking them back out. Organiser only — entering an athlete is a
    // club's act, striking a name off the list is the competition's. Every rule
    // about whether an entry MAY go lives in EntryService::remove().
    Route::delete('/events/{event:uuid}/entries', [App\EventLab\Controllers\PersonalEventController::class, 'removeEntries'])->name('events.entries.destroy')->middleware('throttle:admin-write');
    // Turning the public page on or off for this event. Organiser only, and
    // the only writer of `entry_mode` (EVENTS-PUBLIC-ENTRY.md, Phase B).
    Route::post('/events/{event:uuid}/public', [App\EventLab\Controllers\PersonalEventController::class, 'setEntryMode'])->name('events.public.toggle')->middleware('throttle:admin-write');

    // The picture the public cover opens onto — `images[0]`, replaced in place.
    // Byte-validated by StoresBase64Images, filed under events/{uuid}/branding
    // by StoragePath, and only writable by somebody who may manage the event.
    Route::post('/events/{event:uuid}/cover', [App\EventLab\Controllers\PersonalEventController::class, 'setCover'])->name('events.cover.store')->middleware('throttle:uploads');
    Route::delete('/events/{event:uuid}/cover', [App\EventLab\Controllers\PersonalEventController::class, 'clearCover'])->name('events.cover.destroy')->middleware('throttle:admin-write');
    // Whether a public entry needs a yes. A separate decision from publishing
    // the page, so an unreviewed door never opens as a side effect of sharing.
    Route::post('/events/{event:uuid}/public/auto-accept', [App\EventLab\Controllers\PersonalEventController::class, 'setPublicAutoAccept'])->name('events.public.auto-accept')->middleware('throttle:admin-write');
    // When the draw becomes readable: always | start_day | hidden. Disclosure
    // only — it never changes who may ARRANGE a draw, and the organiser and
    // their officials read it at every setting.
    Route::post('/events/{event:uuid}/draw-reveal', [App\EventLab\Controllers\PersonalEventController::class, 'setDrawReveal'])->name('events.draw-reveal')->middleware('throttle:admin-write');
    // The queue of strangers who followed that link and asked to compete. The
    // accept is the gate — until it happens the request is an entry in nothing
    // (EVENTS-PUBLIC-ENTRY.md, Door C).
    Route::get('/events/{event:uuid}/public-entries', [App\EventLab\Controllers\PersonalEventController::class, 'publicEntries'])->name('events.public-entries')->middleware('throttle:60,1');
    Route::post('/events/{event:uuid}/public-entries/{entry}/accept', [App\EventLab\Controllers\PersonalEventController::class, 'acceptPublicEntry'])->name('events.public-entries.accept')->whereUuid('entry')->middleware('throttle:admin-write');
    Route::post('/events/{event:uuid}/public-entries/{entry}/decline', [App\EventLab\Controllers\PersonalEventController::class, 'declinePublicEntry'])->name('events.public-entries.decline')->whereUuid('entry')->middleware('throttle:admin-write');
    // Entering somebody not on the roster: a NAME commits the entry, and a
    // single-use link lets the athlete supply what the coach could only have
    // guessed at (Documentation/EVENTS-PUBLIC-ENTRY.md, Door B).
    Route::post('/events/{event:uuid}/entries/unnamed', [App\EventLab\Controllers\PersonalEventController::class, 'storeUnnamedEntry'])->name('events.entries.unnamed')->middleware('throttle:admin-write');
    Route::get('/events/{event:uuid}/entry-links', [App\EventLab\Controllers\PersonalEventController::class, 'entryClaimLinks'])->name('events.entry-links')->middleware('throttle:60,1');
    Route::delete('/events/{event:uuid}/entry-links/{claim}', [App\EventLab\Controllers\PersonalEventController::class, 'revokeEntryClaim'])->name('events.entry-links.revoke')->whereUuid('claim')->middleware('throttle:admin-write');
    Route::post('/events/{event:uuid}/entry-links/{claim}/relink', [App\EventLab\Controllers\PersonalEventController::class, 'regenerateEntryClaim'])->name('events.entry-links.relink')->whereUuid('claim')->middleware('throttle:admin-write');
    // A club's say over its own name: who has entered themselves claiming it,
    // and rejecting a claim (which never removes the athlete from the event).
    Route::get('/events/{event:uuid}/claims', [App\EventLab\Controllers\PersonalEventController::class, 'entryClaims'])->name('events.claims');
    Route::post('/events/{event:uuid}/claims/{user}/disown', [App\EventLab\Controllers\PersonalEventController::class, 'disownClaim'])->name('events.claims.disown')->whereNumber('user')->middleware('throttle:admin-write');
    Route::post('/events/{event:uuid}/register', [App\EventLab\Controllers\PersonalEventController::class, 'register'])->name('events.register')->middleware('throttle:member-write');
    Route::post('/events/{event:uuid}/ticket', [App\EventLab\Controllers\PersonalEventController::class, 'ticket'])->name('events.ticket')->middleware('throttle:member-write');
    Route::delete('/events/{event:uuid}/register', [App\EventLab\Controllers\PersonalEventController::class, 'cancel'])->name('events.cancel')->middleware('throttle:member-write');
    // Owner moderation of registrations (remove / block this event / blacklist club-wide).
    Route::post('/events/{event:uuid}/participants/{user}/moderate', [App\EventLab\Controllers\PersonalEventController::class, 'moderateParticipant'])->name('events.participant.moderate')->whereNumber('user')->middleware('throttle:member-write');
    Route::delete('/events/{event:uuid}/bans/{user}', [App\EventLab\Controllers\PersonalEventController::class, 'liftBan'])->name('events.ban.lift')->whereNumber('user')->middleware('throttle:member-write');
// Challenges & 1v1 duels live in the Challenges module:
// app/Challenges/routes-member.php, registered under the same /me prefix
// with the `me.` name prefix and the same middleware stack.

    /*
     * The clubs standing behind the event — NEW in the sandbox, not a copy.
     *
     * Reads are cheap and the organiser's console polls none of them; the
     * writes are throttled like every other write on the platform. Every
     * method re-checks that the actor may manage this event.
     */
    // The screen itself, and the same list as JSON for the panel's own updates.
    Route::get('/events/{event:uuid}/clubs', [App\EventLab\Controllers\EventClubsController::class, 'page'])
        ->name('events.clubs')->middleware('throttle:60,1')->whereUuid('event');

    Route::get('/events/{event:uuid}/clubs/list', [App\EventLab\Controllers\EventClubsController::class, 'index'])
        ->name('events.clubs.list')->middleware('throttle:60,1')->whereUuid('event');

    Route::get('/events/{event:uuid}/clubs/search', [App\EventLab\Controllers\EventClubsController::class, 'search'])
        ->name('events.clubs.search')->middleware('throttle:30,1')->whereUuid('event');

    Route::post('/events/{event:uuid}/clubs', [App\EventLab\Controllers\EventClubsController::class, 'store'])
        ->name('events.clubs.store')->middleware('throttle:20,1')->whereUuid('event');

    Route::post('/events/{event:uuid}/clubs/invite', [App\EventLab\Controllers\EventClubsController::class, 'invite'])
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
    Route::put('/events/{event:uuid}/clubs/{eventClub}', [App\EventLab\Controllers\EventClubsController::class, 'update'])
        ->name('events.clubs.update')->middleware('throttle:20,1')->whereUuid('event');

    Route::delete('/events/{event:uuid}/clubs/{eventClub}', [App\EventLab\Controllers\EventClubsController::class, 'destroy'])
        ->name('events.clubs.destroy')->middleware('throttle:20,1')->whereUuid('event');

    Route::get('/events/{event:uuid}/clubs/entrants', [App\EventLab\Controllers\EventClubsController::class, 'entrants'])
        ->name('events.clubs.entrants')->middleware('throttle:60,1')->whereUuid('event');

    Route::post('/events/{event:uuid}/clubs/{eventClub}/athletes', [App\EventLab\Controllers\EventClubsController::class, 'assign'])
        ->name('events.clubs.athletes')->middleware('throttle:30,1')->whereUuid('event');

    // Turn the temporary clubs that competed into real ones. Refused before the
    // event is over, because "who competed" is not a fact until then.
    Route::post('/events/{event:uuid}/clubs/promote', [App\EventLab\Controllers\EventClubsController::class, 'promote'])
        ->name('events.clubs.promote')->middleware('throttle:6,1')->whereUuid('event');

    /*
     * The club's own answer. Under the member prefix because only a signed-in
     * club owner can give it, and it is refused for anybody but the person the
     * invitation was addressed to.
     */
    Route::post('/events/{event:uuid}/clubs/{eventClub}/respond', [App\EventLab\Controllers\EventClubsController::class, 'respond'])
        ->name('events.clubs.respond')->middleware('throttle:20,1')->whereUuid('event');

});
