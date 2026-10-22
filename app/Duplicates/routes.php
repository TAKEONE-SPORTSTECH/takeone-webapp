<?php

use App\Duplicates\Controllers\DuplicatesController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| The duplicate watch's one screen — super-admin only
|--------------------------------------------------------------------------
| The SAME middleware stack the super-admin's own room uses
| (app/Platform/routes.php): auth, verified, two-factor, role:super-admin.
| A finding names people and clubs across every tenant; nobody else may read
| one. Findings are addressed by their uuid.
*/
Route::middleware(['auth', 'verified', 'two-factor', 'role:super-admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/duplicates', [DuplicatesController::class, 'index'])->name('duplicates.index');
        Route::get('/duplicates/list', [DuplicatesController::class, 'list'])->middleware('throttle:60,1')->name('duplicates.list');
        Route::post('/duplicates/{finding:uuid}/dismiss', [DuplicatesController::class, 'dismiss'])->middleware('throttle:admin-write')->name('duplicates.dismiss');
        Route::post('/duplicates/{finding:uuid}/fix', [DuplicatesController::class, 'fix'])->middleware('throttle:admin-write')->name('duplicates.fix');
    });
