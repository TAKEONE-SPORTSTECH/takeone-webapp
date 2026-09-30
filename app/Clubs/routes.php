<?php

/*
| Club routes that are NOT the club admin's own workspace.
|
| Registered by App\Support\Modules\ModuleServiceProvider inside `web` — the
| same group routes/web.php is loaded in — and each block below re-declares
| verbatim the prefix, name prefix and middleware it carried when it lived
| there, so every URI, route name and middleware stack is unchanged.
|
| They are here rather than in routes-club-admin.php because their stack is a
| different one: the club API is a super-admin surface under /admin, and the two
| notification routes are global rather than club-scoped. Keeping them in
| routes/web.php would leave the shared file holding references to this module's
| private Controllers/ — the exact boundary ModuleBoundaryTest forbids.
*/

use App\Clubs\Controllers\ClubApiController;
use App\Clubs\Controllers\ClubNotificationController;
use App\Clubs\Controllers\PublicClubController;
use Illuminate\Support\Facades\Route;

// Club create/update + the lookup endpoints the club modal calls. Super-admin
// only, under the platform's /admin prefix.
Route::middleware(['auth', 'verified', 'two-factor', 'role:super-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/clubs', [ClubApiController::class, 'store'])->name('platform.clubs.store')->middleware('throttle:admin-write');
    Route::put('/clubs/{club}', [ClubApiController::class, 'update'])->name('platform.clubs.update')->middleware('throttle:admin-write');

    Route::get('/api/users', [ClubApiController::class, 'getUsers'])->name('api.users');
    Route::get('/api/users/lookup', [ClubApiController::class, 'lookupUsers'])->name('api.users.lookup')->middleware('throttle:admin-write');
    Route::get('/api/users/by-email', [ClubApiController::class, 'findUserByEmail'])->name('api.users.by-email')->middleware('throttle:admin-write');
    Route::get('/api/clubs/{id}', [ClubApiController::class, 'getClub'])->name('api.club');
    Route::post('/api/clubs/check-slug', [ClubApiController::class, 'checkSlug'])->name('api.clubs.check-slug')->middleware('throttle:admin-write');
});

// Mark notification as read / clear them all (global — not club-scoped).
Route::middleware(['auth', 'verified', 'two-factor'])->post('/notifications/mark-read', [ClubNotificationController::class, 'markRead'])->name('notifications.mark-read');
Route::middleware(['auth', 'verified', 'two-factor'])->delete('/notifications', [ClubNotificationController::class, 'clearAll'])->name('notifications.clear');

/*
|--------------------------------------------------------------------------
| The club page, open to anyone — the PUBLIC club browser
|--------------------------------------------------------------------------
| Moved verbatim from routes/web.php with PublicClubController (which was
| App\Http\Controllers\PlatformController). Every URI, route name, middleware
| and throttle below is unchanged; only the class the action names has moved.
|
| Two of these are deliberately OUTSIDE the auth group and must stay there —
| the QR page and the canonical club page are read by guests.
*/

// Public club page - no login required (used for QR code)
Route::get('/mobile/{country}/{slug}', [PublicClubController::class, 'showPublic'])->name('clubs.show.public');

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
    Route::get('/clubs/{slug}', [PublicClubController::class, 'show'])
        ->name('clubs.show')->middleware('throttle:60,1');
});

// Explore routes (accessible to authenticated users)
Route::middleware(['auth', 'two-factor'])->group(function () {

    Route::get('/explore', [PublicClubController::class, 'index'])->name('clubs.explore');

    Route::get('/clubs/nearby', [PublicClubController::class, 'nearby'])->name('clubs.nearby');
    Route::get('/clubs/all', [PublicClubController::class, 'all'])->name('clubs.all');
    // Explore → Events tab: open events only (not started + running now).
    Route::get('/explore/events', [PublicClubController::class, 'events'])->name('explore.events')->middleware('throttle:60,1');

    // Country-prefixed club routes
    // NOTE: clubs.show itself is PUBLIC and defined outside this group (see
    // near clubs.show.public). Everything below still requires a signed-in
    // user — joining, leaving, collecting a perk.
    Route::prefix('{country}')->where(['country' => '[a-z]{2,3}'])->group(function () {
        Route::get('/clubs/{slug}/packages-json', [PublicClubController::class, 'clubPackages'])->name('clubs.packages.json');
        Route::post('/clubs/join', [PublicClubController::class, 'joinClub'])->name('clubs.join')->middleware('verified', 'throttle:join-club');
        Route::post('/clubs/{slug}/events/{event}/join', [PublicClubController::class, 'joinEvent'])->name('clubs.events.join')->middleware('verified', 'throttle:join-club');
        Route::delete('/clubs/{slug}/events/{event}/leave', [PublicClubController::class, 'leaveEvent'])->name('clubs.events.leave')->middleware('verified', 'throttle:social');
        Route::post('/clubs/{slug}/perks/{perk}/collect', [PublicClubController::class, 'collectPerk'])->name('clubs.perks.collect')->middleware('verified', 'throttle:social');
        Route::post('/clubs/{slug}/timeline/{post}/like', [PublicClubController::class, 'toggleLike'])->name('clubs.timeline.like')->middleware('verified', 'throttle:social');
        Route::post('/clubs/{slug}/timeline/{post}/comments', [PublicClubController::class, 'addComment'])->name('clubs.timeline.comment')->middleware('verified', 'throttle:social');
        Route::delete('/clubs/{slug}/timeline/{post}/comments/{comment}', [PublicClubController::class, 'deleteComment'])->name('clubs.timeline.comment.delete')->middleware('verified', 'throttle:social');
    });
});


/*
|--------------------------------------------------------------------------
| Business (chain) — setup, the view switcher, the chain dashboard, and the
| super-admin's approvals. Moved verbatim from routes/web.php.
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', 'two-factor'])->group(function () {
    // Business (Chain) — create & manage your own chain
    Route::get('/business/create', [\App\Clubs\Controllers\BusinessController::class, 'setup'])->name('business.setup');
    Route::post('/business', [\App\Clubs\Controllers\BusinessController::class, 'store'])->name('business.store')->middleware('throttle:6,1');

    // Switch between Personal and Business view modes (Facebook-style)
    Route::post('/switch-view', [\App\Clubs\Controllers\BusinessController::class, 'switchView'])->name('view.switch');
});

// The common misspelling of /business, so a typed address still lands.
Route::redirect('/busniess', '/business');

// /business — the chain owner's front door (/club stays the single-club door).
// Outside the `business` guard because it is also where that guard sends a
// super-admin who has not picked a business yet.
Route::middleware(['auth', 'verified', 'two-factor'])
    ->get('/business', [\App\Clubs\Controllers\BusinessDashboardController::class, 'home'])
    ->name('business.home');

// Business (Chain) dashboard — requires an APPROVED business
Route::middleware(['auth', 'verified', 'two-factor', 'business'])->prefix('business')->name('business.')->group(function () {
    Route::get('/dashboard', [\App\Clubs\Controllers\BusinessDashboardController::class, 'index'])->name('dashboard');
    Route::post('/clubs', [\App\Clubs\Controllers\BusinessClubController::class, 'store'])->name('clubs.store')->middleware('throttle:admin-write');
});

Route::middleware(['auth', 'verified', 'two-factor', 'role:super-admin'])->prefix('admin')->name('admin.')->group(function () {
    // Business (Chain) approvals
    Route::get('/businesses', [\App\Clubs\Controllers\BusinessApprovalController::class, 'index'])->name('platform.businesses');
    Route::post('/businesses', [\App\Clubs\Controllers\BusinessApprovalController::class, 'store'])->name('platform.businesses.store')->middleware('throttle:admin-write');
    Route::post('/businesses/{business}/approve', [\App\Clubs\Controllers\BusinessApprovalController::class, 'approve'])->name('platform.businesses.approve')->middleware('throttle:admin-write');
    Route::post('/businesses/{business}/reject', [\App\Clubs\Controllers\BusinessApprovalController::class, 'reject'])->name('platform.businesses.reject')->middleware('throttle:admin-write');
    Route::put('/businesses/{business}', [\App\Clubs\Controllers\BusinessApprovalController::class, 'update'])->name('platform.businesses.update')->middleware('throttle:admin-write');
    Route::get('/businesses/{business}/history', [\App\Clubs\Controllers\BusinessApprovalController::class, 'history'])->name('platform.businesses.history');
    Route::get('/businesses/{business}/clubs', [\App\Clubs\Controllers\BusinessApprovalController::class, 'clubs'])->name('platform.businesses.clubs');
    Route::post('/businesses/{business}/clubs/attach', [\App\Clubs\Controllers\BusinessApprovalController::class, 'attachClub'])->name('platform.businesses.clubs.attach')->middleware('throttle:admin-write');
    Route::post('/businesses/{business}/clubs/detach', [\App\Clubs\Controllers\BusinessApprovalController::class, 'detachClub'])->name('platform.businesses.clubs.detach')->middleware('throttle:admin-write');
    Route::post('/businesses/{business}/transfer-owner', [\App\Clubs\Controllers\BusinessApprovalController::class, 'transferOwner'])->name('platform.businesses.transfer-owner')->middleware('throttle:admin-write');
    Route::delete('/businesses/{business}', [\App\Clubs\Controllers\BusinessApprovalController::class, 'destroy'])->name('platform.businesses.destroy')->middleware('throttle:admin-write');
});

/*
|--------------------------------------------------------------------------
| /club — the manager's front door. One managed club → its dashboard,
| several → a picker, none → home. Access to each club is still decided by
| the admin panel itself on every request.
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'two-factor'])
    ->get('/club', [\App\Clubs\Controllers\ClubEntryController::class, 'index'])
    ->name('club.entry');
