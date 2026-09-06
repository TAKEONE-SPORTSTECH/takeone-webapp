<?php

/**
 * The sandbox's routes.
 *
 * Registered by App\Support\Modules\ModuleServiceProvider inside the `web`
 * middleware with NO prefix, so the prefix is declared here: everything the
 * experiment can reach lives under /testcode and is named `testcode.*`.
 *
 * `auth` + `role:super-admin` gate the group, and every controller method checks
 * again. Writes are throttled like any other write on the platform — a tool
 * being internal and temporary does not make it cheap to hammer.
 */

use App\EventLab\Controllers\LabController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:super-admin'])
    ->prefix('testcode')
    ->name('testcode.')
    ->group(function () {
        Route::get('/', [LabController::class, 'index'])
            ->name('home')
            ->middleware('throttle:60,1');

        Route::post('/seed', [LabController::class, 'seed'])
            ->name('seed')
            ->middleware('throttle:10,1');

        // A sandbox TWIN: a real club_events row the existing screens can be
        // tried on. Making one only adds; removing one is refused unless the
        // event carries the sandbox title prefix.
        Route::post('/twin', [LabController::class, 'twin'])
            ->name('twin')
            ->middleware('throttle:10,1');

        Route::delete('/twin/{clone:uuid}', [LabController::class, 'untwin'])
            ->name('twin.purge')
            ->middleware('throttle:10,1');

        Route::get('/event/{event}', [LabController::class, 'show'])
            ->name('event')
            ->middleware('throttle:60,1');

        Route::post('/event/{event}/quote', [LabController::class, 'quote'])
            ->name('quote')
            ->middleware('throttle:60,1');

        Route::post('/event/{event}/entrant/{entrant}/duplicate', [LabController::class, 'markDuplicate'])
            ->name('entrant.duplicate')
            ->middleware('throttle:30,1');
    });

/*
 * The COPIED event flow — /testcode/e/… and /testcode/me/events/….
 *
 * A separate file because it is a copy, not new work: keeping it apart is what
 * lets it be diffed against the originals it came from, and deleted in one
 * piece. Loaded here so it lands inside the same `web` group as everything else
 * in this module — session, CSRF and the device detector all still apply.
 */
require __DIR__.'/routes-copied.php';
