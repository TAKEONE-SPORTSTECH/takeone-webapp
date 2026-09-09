<?php

use App\Translation\Controllers\PublicTranslationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Asking to read a public event in your own language
|--------------------------------------------------------------------------
|
| Open to anybody with the link — a stranger deciding whether to enter a
| competition has no account, and demanding one before they can read the page
| would defeat the entire feature.
|
| ⚠️ Two segments on the status route, on purpose. `GET e/{event}/{section}`
| already exists (the poster's sub-pages) and would swallow a one-segment
| `e/{event}/language`. The prepare route is a POST, which that GET cannot
| match, and the status route carries the locale as a second segment. Changing
| either shape means checking that collision again.
|
| `throttle:translate` is the guard that matters: prepare can start work that
| costs money at an AI provider. See AppServiceProvider for the two ceilings and
| why they are the numbers they are.
*/

Route::middleware('throttle:translate')->group(function () {
    Route::post('e/{event:uuid}/language', [PublicTranslationController::class, 'prepare'])
        ->name('events.public.language.prepare');
});

/*
 * Polled every couple of seconds while a translation is being written, so it
 * gets the roomier public-event limit rather than the translate one: it starts
 * nothing, reads one indexed row, and a visitor watching a spinner must not be
 * rate-limited out of ever seeing it finish.
 */
Route::middleware('throttle:public-event')->group(function () {
    Route::get('e/{event:uuid}/language/{locale}', [PublicTranslationController::class, 'status'])
        ->name('events.public.language.status');
});
