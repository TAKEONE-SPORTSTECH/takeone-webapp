<?php

use App\Printables\Controllers\DesignerController;
use App\Printables\Middleware\EnsurePrintablesEnabled;
use Illuminate\Support\Facades\Route;

/*
| /me/printables — the one designer. An owner is named by its KIND's public
| reference (an event certificate context is `club_event.{uuid}`, a member's own
| design is `me`); a saved design and a picture by their uuid. Every action asks
| the kind whether this user may design that owner.
*/
Route::prefix('/printables')->group(function () {
    $uuid = '[0-9a-fA-F-]{36}';

    Route::middleware(EnsurePrintablesEnabled::class)->group(function () use ($uuid) {
        Route::get('/{kind}/{ref}', [DesignerController::class, 'page'])
            ->where(['kind' => '[a-z_]{2,40}', 'ref' => '[A-Za-z0-9_.-]{2,80}'])
            ->name('printables.design');
        // Real-person preview: who can be shown, and one of them filled in (DesignerController::people/person).
        Route::get('/{kind}/{ref}/people', [DesignerController::class, 'people'])
            ->where(['kind' => '[a-z_]{2,40}', 'ref' => '[A-Za-z0-9_.-]{2,80}'])
            ->name('printables.people')->middleware('throttle:60,1');
        Route::get('/{kind}/{ref}/people/{id}', [DesignerController::class, 'person'])
            ->where(['kind' => '[a-z_]{2,40}', 'ref' => '[A-Za-z0-9_.-]{2,80}', 'id' => $uuid])
            ->name('printables.person')->middleware('throttle:60,1');
        // Start a design as a copy of another of the same owner (DesignerController::copy).
        Route::post('/{kind}/{ref}/copy', [DesignerController::class, 'copy'])->name('printables.copy')->middleware('throttle:member-write');
        Route::post('/{kind}/{ref}', [DesignerController::class, 'create'])
            ->where(['kind' => '[a-z_]{2,40}', 'ref' => '[A-Za-z0-9_.-]{2,80}'])
            ->name('printables.create')->middleware('throttle:member-write');

        Route::put('/d/{uuid}', [DesignerController::class, 'save'])->where('uuid', $uuid)->name('printables.save')->middleware('throttle:member-write');
        Route::post('/d/{uuid}/pictures', [DesignerController::class, 'upload'])->where('uuid', $uuid)->name('printables.upload')->middleware('throttle:uploads');
        Route::get('/d/{uuid}/print', [DesignerController::class, 'print'])->where('uuid', $uuid)->name('printables.print')->middleware('throttle:30,1');

        Route::get('/p/{uuid}', [DesignerController::class, 'asset'])->where('uuid', $uuid)->name('printables.asset')->middleware('throttle:media-read');
        Route::delete('/p/{uuid}', [DesignerController::class, 'destroyAsset'])->where('uuid', $uuid)->name('printables.asset.destroy')->middleware('throttle:member-write');
    });
});
