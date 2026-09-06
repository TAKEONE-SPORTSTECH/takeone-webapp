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
use Illuminate\Support\Facades\Route;

// Club create/update + the lookup endpoints the club modal calls. Super-admin
// only, under the platform's /admin prefix.
Route::middleware(['auth', 'verified', 'two-factor', 'role:super-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/clubs', [ClubApiController::class, 'store'])->name('platform.clubs.store')->middleware('throttle:admin-write');
    Route::put('/clubs/{club}', [ClubApiController::class, 'update'])->name('platform.clubs.update')->middleware('throttle:admin-write');

    Route::get('/api/users', [ClubApiController::class, 'getUsers'])->name('api.users');
    Route::get('/api/clubs/{id}', [ClubApiController::class, 'getClub'])->name('api.club');
    Route::post('/api/clubs/check-slug', [ClubApiController::class, 'checkSlug'])->name('api.clubs.check-slug')->middleware('throttle:admin-write');
});

// Mark notification as read / clear them all (global — not club-scoped).
Route::middleware(['auth', 'verified', 'two-factor'])->post('/notifications/mark-read', [ClubNotificationController::class, 'markRead'])->name('notifications.mark-read');
Route::middleware(['auth', 'verified', 'two-factor'])->delete('/notifications', [ClubNotificationController::class, 'clearAll'])->name('notifications.clear');
