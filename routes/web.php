<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    if (Auth::check()) {
        return redirect(\App\Support\Landing::url($request));
    }

    return redirect()->route('login');
});

// Two-factor challenge + /security now live in the Members module (app/Members/routes.php);
// business setup, the view switcher and /business/* in the Clubs module (app/Clubs/routes.php).
// Same URIs, names and middleware.

// Market item creators — PREVIEW of the reusable product/category form
// components (form UI only, no DB yet). Drop the components into club admin /
// seller area / personal mobile when wiring the real backend.
Route::middleware(['auth', 'verified', 'role:super-admin'])->get('/market/forms-preview', function () {
    return view('market.forms-preview');
})->name('market.forms-preview');

// The public event pages (/e/…), entry claims, hall-screen pairing, cameras
// and Open Mat live in the Events module: app/Events/routes.php (same URIs,
// names and middleware).


// The one door to stored files. There is a single storage root and no
// public symlink: App\Support\FileAccess decides in code who may read a path,
// and denies anything it has not been taught about.
Route::get('/file/{path}', [App\Http\Controllers\FileController::class, 'show'])
    ->where('path', '.*')->name('file.show')->middleware('throttle:240,1');

// The public Android app-version manifest lives in the Members module:
// app/Members/routes.php.




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


// Sign-in, registration, password reset and e-mail verification live in the
// Members module: app/Members/routes.php (same URIs, names and middleware).

// The public club browser — /explore, /{country}/clubs/{slug}, the QR page at
// /mobile/{country}/{slug}, joining, perks and the club timeline — lives in the
// Clubs module: app/Clubs/routes.php, served by PublicClubController (which was
// App\Http\Controllers\PlatformController).

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

    // Stop impersonating — available to the (impersonated) session, restores the admin.
    Route::post('/impersonate/leave', [App\Http\Controllers\ImpersonationController::class, 'stop'])->name('impersonate.leave');
});

// Platform Admin routes (Super Admin only)
// "Write with AI" moved to app/Ai/routes.php with its module.

// TEST/LAB — activity video-section design exploration (mobile). Static mock,
// no data access. Defined BEFORE the {activity:uuid} route so the literal path
// isn't captured by uuid binding. Remove before go-live.
Route::get('/lab/activity-video', function () {
    $activity = \App\Platform\Models\ActivityCatalog::where('uuid', '7c4afd25-1a69-48c1-8ba2-c1b136af0cea')->first();

    return view('lab.activity-video', ['activity' => $activity]);
})->middleware(['auth', 'verified', 'role:super-admin', 'throttle:60,1'])->name('lab.activity-video');


Route::middleware(['auth', 'verified', 'two-factor', 'role:super-admin'])->prefix('admin')->name('admin.')->group(function () {

    // Start impersonating a user (super-admin only — enforced by group middleware).
    Route::post('/impersonate/{user}', [App\Http\Controllers\ImpersonationController::class, 'start'])->name('impersonate.start')->middleware('throttle:admin-write');



    // AI provider settings (text / voice / image — local or cloud)

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






    // Business (Chain) approvals live in the Clubs module: app/Clubs/routes.php.
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
