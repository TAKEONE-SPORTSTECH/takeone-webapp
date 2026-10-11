<?php

use App\Certificates\Controllers\CertificateFileController;
use App\Certificates\Controllers\DesignController;
use App\Certificates\Controllers\ManageController;
use App\Certificates\Controllers\MyCertificatesController;
use App\Certificates\Middleware\EnsureCertificatesEnabled;
use Illuminate\Support\Facades\Route;

/*
| /me/certificates — a member's own, and the organiser's console + design room.
| Certificates are addressed by uuid; a context by its alias + public key
| (/for/club_event/{event-uuid}). Every action re-authorises: the policy for a
| certificate, the context provider for a context.
*/
Route::middleware(EnsureCertificatesEnabled::class)->group(function () {
    $uuid = '[0-9a-fA-F-]{36}';
    $alias = '[a-z_]{2,40}';

    Route::get('/certificates', [MyCertificatesController::class, 'index'])->name('certificates.index');
    Route::get('/certificates/list', [MyCertificatesController::class, 'list'])->name('certificates.list')->middleware('throttle:60,1');

    Route::get('/certificates/{cert}/pdf', [CertificateFileController::class, 'pdf'])->where('cert', $uuid)->name('certificates.pdf')->middleware('throttle:60,1');
    Route::get('/certificates/{cert}/image', [CertificateFileController::class, 'image'])->where('cert', $uuid)->name('certificates.image')->middleware('throttle:30,1');
    Route::get('/certificates/{cert}/print', [CertificateFileController::class, 'print'])->where('cert', $uuid)->name('certificates.print')->middleware('throttle:30,1');

    Route::post('/certificates/{cert}/revoke', [ManageController::class, 'revoke'])->where('cert', $uuid)->name('certificates.revoke')->middleware('throttle:member-write');
    Route::delete('/certificates/{cert}', [ManageController::class, 'destroy'])->where('cert', $uuid)->name('certificates.destroy')->middleware('throttle:member-write');

    // Officials' badges (App\Events\Support\EventBadges): the list, and one badge by its random token.
    Route::get('/badges/for/{key}', [\App\Certificates\Controllers\BadgeController::class, 'index'])->where('key', $uuid)->name('badges.index')->middleware('throttle:60,1');
    Route::get('/badges/{token}/image', [\App\Certificates\Controllers\BadgeController::class, 'image'])->where('token', '[A-Za-z0-9]{32}')->name('badges.image')->middleware('throttle:60,1');
    Route::get('/badges/{token}/pdf', [\App\Certificates\Controllers\BadgeController::class, 'pdf'])->where('token', '[A-Za-z0-9]{32}')->name('badges.pdf')->middleware('throttle:60,1');

    Route::prefix('/certificates/for/{context}/{key}')->where(['context' => $alias, 'key' => $uuid])->group(function () {
        Route::get('/', [ManageController::class, 'show'])->name('certificates.manage')->middleware('throttle:60,1');
        Route::put('/settings', [ManageController::class, 'settings'])->name('certificates.settings')->middleware('throttle:member-write');
        Route::post('/generate', [ManageController::class, 'generate'])->name('certificates.generate')->middleware('throttle:10,1');
        Route::post('/approve', [ManageController::class, 'approve'])->name('certificates.approve')->middleware('throttle:10,1');
        Route::delete('/drafts', [ManageController::class, 'destroyDrafts'])->name('certificates.drafts.destroy')->middleware('throttle:10,1');
        Route::match(['get', 'post'], '/bundle/{type}', [ManageController::class, 'bundle'])->where('type', '[a-z_]{2,32}')->name('certificates.bundle')->middleware('throttle:30,1');
        Route::get('/bundle/{type}/download', [ManageController::class, 'bundleDownload'])->where('type', '[a-z_]{2,32}')->name('certificates.bundle.download')->middleware('throttle:20,1');
    });

    Route::prefix('/certificates/design/{context}/{key}')->where(['context' => $alias, 'key' => $uuid])->group(function () {
        Route::get('/', [DesignController::class, 'page'])->name('certificates.design');
        Route::get('/state', [DesignController::class, 'state'])->name('certificates.design.state')->middleware('throttle:60,1');
        Route::put('/{type}', [DesignController::class, 'update'])->where('type', '[a-z_]{2,32}')->name('certificates.design.update')->middleware('throttle:member-write');
        Route::match(['get', 'post'], '/{type}/preview', [DesignController::class, 'preview'])->where('type', '[a-z_]{2,32}')->name('certificates.design.preview')->middleware('throttle:30,1');
        Route::get('/{type}/signature/{slot}', [DesignController::class, 'signature'])->where(['type' => '[a-z_]{2,32}', 'slot' => '[12]'])->name('certificates.design.signature')->middleware('throttle:60,1');
        Route::post('/{type}/signature/{slot}', [DesignController::class, 'storeSignature'])->where(['type' => '[a-z_]{2,32}', 'slot' => '[12]'])->name('certificates.design.signature.store')->middleware('throttle:uploads');
        Route::delete('/{type}/signature/{slot}', [DesignController::class, 'destroySignature'])->where(['type' => '[a-z_]{2,32}', 'slot' => '[12]'])->name('certificates.design.signature.destroy')->middleware('throttle:member-write');
        Route::post('/{type}/artwork', [DesignController::class, 'storeArtwork'])->where('type', '[a-z_]{2,32}')->name('certificates.design.artwork.store')->middleware('throttle:uploads');
        Route::delete('/{type}/artwork', [DesignController::class, 'destroyArtwork'])->where('type', '[a-z_]{2,32}')->name('certificates.design.artwork.destroy')->middleware('throttle:member-write');
    });
});
