<?php

use App\Platform\Controllers\ActivityCatalogController;
use App\Platform\Controllers\PlatformActivityController;
use App\Platform\Controllers\PlatformController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| The platform's own surfaces
|--------------------------------------------------------------------------
|
| One public page and the super-admin's room.
|
| ⚠️ Every middleware stack below is reproduced EXACTLY as it stood in
| routes/web.php, the group included. A module must never quietly opt out of
| session, CSRF, auth, two-factor or a throttle by changing address.
|
| PlatformController here is this module's own — the SUPER-ADMIN's screens. The
| public club browser that used to share the name now lives in App\Clubs, under
| a name that says what it does.
*/

/*
 * The global activity directory, read-only and public: every club's
 * activities point at a row here, so the page is reachable without an
 * account. Bound on the uuid, throttled because it is cheap to call.
 */
// PUBLIC viewer for a global-directory activity — rich, shareable content page
// (QR-linked). No auth: general sport knowledge, no tenant/personal data. Bound
// by a non-guessable uuid and throttled to blunt scraping/enumeration; guests
// are prompted to sign in on the page itself.
Route::get('/activity/{activity:uuid}', [ActivityCatalogController::class, 'show'])
    ->middleware('throttle:120,1')
    ->name('activity.show');

/*
 * The super-admin's room. two-factor is part of this stack rather than an
 * extra: these screens delete clubs, rewrite members and restore backups.
 */
Route::middleware(['auth', 'verified', 'two-factor', 'role:super-admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [PlatformController::class, 'home'])->name('platform.index');

        // All Clubs Management
        Route::get('/clubs', [PlatformController::class, 'clubs'])->name('platform.clubs');

        Route::get('/clubs/create', [PlatformController::class, 'createClub'])->name('platform.clubs.create');

        Route::get('/clubs/{club}/edit', [PlatformController::class, 'editClub'])->name('platform.clubs.edit');

        Route::delete('/clubs/{club}', [PlatformController::class, 'destroyClub'])->name('platform.clubs.destroy')->middleware('throttle:admin-write');

        Route::post('/clubs/{club}/upload-logo', [PlatformController::class, 'uploadClubLogo'])->name('platform.clubs.upload-logo')->middleware('throttle:uploads');

        Route::post('/clubs/{club}/upload-cover', [PlatformController::class, 'uploadClubCover'])->name('platform.clubs.upload-cover')->middleware('throttle:uploads');

        // Global activity directory (shared catalog reused across clubs)
        Route::get('/activities', [PlatformActivityController::class, 'index'])->name('platform.activities');

        Route::post('/activities', [PlatformActivityController::class, 'store'])->name('platform.activities.store')->middleware('throttle:admin-write');

        Route::post('/activities/generate-content', [PlatformActivityController::class, 'generateContent'])->name('platform.activities.generate')->middleware('throttle:copilot');

        Route::post('/activities/verify-video', [PlatformActivityController::class, 'verifyVideo'])->name('platform.activities.verify-video')->middleware('throttle:copilot');

        Route::put('/activities/{activity:uuid}', [PlatformActivityController::class, 'update'])->name('platform.activities.update')->middleware('throttle:admin-write');

        Route::post('/activities/{activity:uuid}/image', [PlatformActivityController::class, 'generateImage'])->name('platform.activities.image')->middleware('throttle:admin-write');

        Route::post('/activities/upload-image', [PlatformActivityController::class, 'uploadImageStore'])->name('platform.activities.upload-image')->middleware('throttle:uploads');

        Route::post('/activities/{activity:uuid}/set-image', [PlatformActivityController::class, 'setImage'])->name('platform.activities.set-image')->middleware('throttle:admin-write');

        Route::delete('/activities/{activity:uuid}', [PlatformActivityController::class, 'destroy'])->name('platform.activities.destroy')->middleware('throttle:admin-write');

        // All Members Management
        Route::get('/members', [PlatformController::class, 'members'])->name('platform.members');
        // Scan for duplicates — literal paths, registered before /members/{id}.
        Route::get('/members/duplicates', [PlatformController::class, 'memberDuplicates'])->name('platform.members.duplicates')->middleware('throttle:member-scan');
        Route::post('/members/duplicates/dismiss', [PlatformController::class, 'dismissMemberDuplicate'])->name('platform.members.duplicates.dismiss')->middleware('throttle:admin-write');

        Route::post('/members', [PlatformController::class, 'storeMember'])->name('platform.members.store')->middleware('throttle:admin-write');

        Route::get('/members/{id}', [PlatformController::class, 'showMember'])->name('platform.members.show');

        Route::get('/members/{id}/edit', [PlatformController::class, 'editMember'])->name('platform.members.edit');

        Route::put('/members/{id}', [PlatformController::class, 'updateMember'])->name('platform.members.update')->middleware('throttle:admin-write');

        Route::delete('/members/{id}', [PlatformController::class, 'destroyMember'])->name('platform.members.destroy')->middleware('throttle:admin-write');

        // A person made a member of a club with no package — they trained there before the platform.
        Route::post('/members/{id}/club-membership', [PlatformController::class, 'addClubMembership'])->name('platform.members.club-membership')->middleware('throttle:admin-write');
        // One person, one account: fold a duplicate into the account being kept (undoable).
        Route::get('/members/{id}/merge-candidates', [PlatformController::class, 'mergeCandidates'])->name('platform.members.merge-candidates')->middleware('throttle:60,1');
        Route::get('/members/merge/preview', [PlatformController::class, 'mergePreview'])->name('platform.members.merge-preview')->middleware('throttle:60,1');
        Route::post('/members/merge', [PlatformController::class, 'mergeMembers'])->name('platform.members.merge')->middleware('throttle:admin-write');
        Route::post('/members/merges/{uuid}/undo', [PlatformController::class, 'undoMemberMerge'])->name('platform.members.merge-undo')->middleware('throttle:admin-write');

        Route::post('/members/{id}/upload-picture', [PlatformController::class, 'uploadMemberPicture'])->name('platform.members.upload-picture')->middleware('throttle:uploads');

        Route::delete('/members/{id}/profile-picture', [PlatformController::class, 'removeMemberPicture'])->name('platform.members.remove-picture')->middleware('throttle:admin-write');

        Route::put('/members/{id}/profile-picture/visibility', [PlatformController::class, 'updateMemberPictureVisibility'])->name('platform.members.picture-visibility')->middleware('throttle:admin-write');

        Route::post('/members/{id}/health', [PlatformController::class, 'storeMemberHealth'])->name('platform.members.store-health')->middleware('throttle:admin-write');

        Route::put('/members/{id}/health/{recordId}', [PlatformController::class, 'updateMemberHealth'])->name('platform.members.update-health')->middleware('throttle:admin-write');

        Route::post('/members/{id}/tournament', [PlatformController::class, 'storeMemberTournament'])->name('platform.members.store-tournament')->middleware('throttle:admin-write');

        Route::get('/members/{user}/popup', [PlatformController::class, 'memberPopup'])->name('platform.members.popup');

        Route::get('/members/{user}/enroll-data', [PlatformController::class, 'memberEnrollData'])->name('platform.members.enroll-data');

        Route::post('/members/{user}/verify-email', [PlatformController::class, 'verifyMemberEmail'])->name('platform.members.verify-email')->middleware('throttle:admin-write');

        // Database Backup & Restore
        // The error log. Read-only, super-admin only (inherited from this group), and
        // throttled: it reads a file that grows on the worst day of the year.
        Route::get('/logs', [PlatformController::class, 'logs'])
        ->middleware('throttle:60,1')->name('platform.logs');

        Route::get('/settings', [PlatformController::class, 'settings'])->name('platform.settings');

        Route::put('/settings', [PlatformController::class, 'updateSettings'])->name('platform.settings.update')->middleware('throttle:admin-write');

        Route::put('/settings/corner-side', [PlatformController::class, 'updateCornerSide'])->name('platform.settings.corner-side')->middleware('throttle:admin-write');

        Route::put('/settings/whatsapp', [PlatformController::class, 'updateWhatsAppSettings'])->name('platform.settings.whatsapp.update')->middleware('throttle:admin-write');

        Route::post('/settings/whatsapp/test', [PlatformController::class, 'testWhatsAppConnection'])->name('platform.settings.whatsapp.test')->middleware('throttle:admin-write');

        Route::post('/settings/whatsapp/send-test', [PlatformController::class, 'sendTestWhatsAppMessage'])->name('platform.settings.whatsapp.send-test')->middleware('throttle:admin-write');

        // DANGER ZONE — wipe the platform back to its clean baseline (super-admin only, very tightly throttled).
        Route::post('/settings/reset-baseline', [PlatformController::class, 'resetBaseline'])->name('platform.settings.reset-baseline')->middleware('throttle:reset-baseline');

        Route::get('/backup', [PlatformController::class, 'backup'])->name('platform.backup');

        Route::get('/backup/download', [PlatformController::class, 'downloadBackup'])->name('platform.backup.download');

        Route::post('/backup/restore', [PlatformController::class, 'restoreBackup'])->name('platform.backup.restore')->middleware('throttle:backup');

        Route::get('/backup/export-users', [PlatformController::class, 'exportAuthUsers'])->name('platform.backup.export-users');

        // Audit Log
        Route::get('/audit-log', [PlatformController::class, 'auditLog'])->name('platform.audit-log');

        // Paid featuring on /me/events — review the proofs, set the fee (App\Events\Support\EventFeaturing).
        Route::get('/featured-events', [\App\Platform\Controllers\FeaturedEventsController::class, 'index'])->name('platform.featured-events');
        Route::get('/featured-events/list', [\App\Platform\Controllers\FeaturedEventsController::class, 'list'])->middleware('throttle:60,1')->name('platform.featured-events.list');
        Route::put('/featured-events/fee', [\App\Platform\Controllers\FeaturedEventsController::class, 'fee'])->middleware('throttle:admin-write')->name('platform.featured-events.fee');
        Route::get('/featured-events/{featureRequest:uuid}/proof', [\App\Platform\Controllers\FeaturedEventsController::class, 'proof'])->middleware('throttle:120,1')->name('platform.featured-events.proof');
        Route::post('/featured-events/{featureRequest:uuid}/{decision}', [\App\Platform\Controllers\FeaturedEventsController::class, 'decide'])
            ->whereIn('decision', ['approve', 'reject', 'revoke'])->middleware('throttle:admin-write')->name('platform.featured-events.decide');

    });
