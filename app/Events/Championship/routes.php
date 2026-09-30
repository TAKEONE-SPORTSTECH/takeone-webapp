<?php

use App\Events\Championship\ChampionshipController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Sport championship — the package's own doors
|--------------------------------------------------------------------------
| Registered by App\Events\EventPackageServiceProvider inside `web`, with the
| same auth stack as every member route. Event bound by uuid; every action
| re-checks EventAccess::canManage and that the event is a championship.
*/

Route::middleware(['auth', 'verified', 'two-factor'])
    ->prefix('me/events/{event:uuid}/competition')
    ->name('me.events.competition.')
    ->whereUuid('event')
    ->group(function () {
        Route::get('/data', [ChampionshipController::class, 'data'])->name('data')->middleware('throttle:60,1');
        Route::post('/divisions/{division}/draw', [ChampionshipController::class, 'build'])->name('draw')->whereNumber('division')->middleware('throttle:admin-write');
        Route::post('/divisions/{division}/knockout', [ChampionshipController::class, 'knockout'])->name('knockout')->whereNumber('division')->middleware('throttle:admin-write');
        Route::post('/divisions/{division}/next-round', [ChampionshipController::class, 'nextRound'])->name('next-round')->whereNumber('division')->middleware('throttle:admin-write');
        Route::post('/divisions/{division}/final', [ChampionshipController::class, 'final'])->name('final')->whereNumber('division')->middleware('throttle:admin-write');
        Route::put('/contests/{contest}', [ChampionshipController::class, 'contest'])->name('contest')->whereNumber('contest')->middleware('throttle:admin-write');
        Route::put('/performances/{performance}', [ChampionshipController::class, 'performance'])->name('performance')->whereNumber('performance')->middleware('throttle:admin-write');
    });
