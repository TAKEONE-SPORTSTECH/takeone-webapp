<?php

use App\Events\League\LeagueController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| League — the package's own doors
|--------------------------------------------------------------------------
| Registered by App\Events\EventPackageServiceProvider inside the `web` group,
| so these carry the session, the CSRF check and the same auth stack every
| other member route has. The event is bound by uuid, never by id, and every
| method re-checks authority through EventAccess.
*/

Route::middleware(['auth', 'verified', 'two-factor'])
    ->prefix('me/events/{event:uuid}/league')
    ->name('me.events.league.')
    ->group(function () {
        Route::get('/data', [LeagueController::class, 'data'])->name('data')->middleware('throttle:60,1');

        Route::post('/teams', [LeagueController::class, 'storeTeam'])->name('teams.store')->middleware('throttle:member-write');
        Route::delete('/teams/{team}', [LeagueController::class, 'destroyTeam'])->name('teams.destroy')->middleware('throttle:member-write');

        Route::post('/fixtures', [LeagueController::class, 'generate'])->name('fixtures.generate')->middleware('throttle:member-write');
        Route::put('/fixtures/{fixture}', [LeagueController::class, 'updateFixture'])->name('fixtures.update')->middleware('throttle:member-write');
    });
