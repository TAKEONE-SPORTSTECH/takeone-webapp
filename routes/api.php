<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
|--------------------------------------------------------------------------
| TAKEONE Play — linked match lookup
|--------------------------------------------------------------------------
| Read-only. Every route runs AS the TAKEONE user whose personal token Play
| holds, so authorisation is TAKEONE's, never Play's (VIDEO-INTEGRATION §3.1).
| The `play-integration` ability keeps a token minted for this purpose from
| being usable anywhere else, and vice versa.
*/
Route::middleware(['auth:sanctum', 'ability:play-integration', 'throttle:play-lookup'])
    ->prefix('integration/play')
    ->name('api.play.')
    ->group(function () {
        Route::get('/me', [\App\Http\Controllers\Api\PlayIntegrationController::class, 'me'])->name('me');
        Route::get('/sports', [\App\Http\Controllers\Api\PlayIntegrationController::class, 'sports'])->name('sports');
        Route::get('/people', [\App\Http\Controllers\Api\PlayIntegrationController::class, 'searchPeople'])->name('people.search');
        Route::get('/people/{uuid}', [\App\Http\Controllers\Api\PlayIntegrationController::class, 'person'])
            ->where('uuid', '[0-9a-fA-F-]{36}')
            ->name('people.show');
    });

/*
 * Writes back to TAKEONE — a headshot or a club crest supplied while a match
 * video is filled in lands on the PROFILE, which is what both platforms read
 * from. Separated behind its own `play-write` ability so a token issued for
 * lookups can never rewrite anything, and throttled harder than the reads.
 * Each endpoint re-applies the SAME authorisation the web UI uses; being able
 * to see someone in the picker is not permission to change their picture.
 */
Route::middleware(['auth:sanctum', 'ability:play-write', 'throttle:play-write'])
    ->prefix('integration/play')
    ->name('api.play.')
    ->group(function () {
        Route::post('/people/{uuid}/photo', [\App\Http\Controllers\Api\PlayIntegrationController::class, 'uploadPersonPhoto'])
            ->where('uuid', '[0-9a-fA-F-]{36}')
            ->name('people.photo');
        Route::post('/clubs/{slug}/logo', [\App\Http\Controllers\Api\PlayIntegrationController::class, 'uploadClubLogo'])
            ->where('slug', '[A-Za-z0-9_-]+')
            ->name('clubs.logo');
    });
