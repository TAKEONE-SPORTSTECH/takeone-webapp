<?php

use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| v1 — the TAKEONE integration surface
|--------------------------------------------------------------------------
|
| Entirely additive and entirely separate from the web routes. takeone reaches
| this platform ONLY through here, as a service client holding a Sanctum token
| with explicit abilities — never as a person, never through a session, never
| through the annotation UI's own routes.
|
| Deny by default: `auth:sanctum` establishes WHO is calling, and an `abilities`
| middleware on each route establishes WHAT that token may do. A token with no
| matching ability is refused even though it authenticated.
|
| Throttled on its own limiter so the integration can never exhaust the budget
| that human traffic shares, and vice versa.
|
| Design: Documentation/VIDEO-INTEGRATION.md §6.5 (this repo's counterpart lives
| in the takeone repo).
|
*/
Route::prefix('v1')
    ->name('api.v1.')
    ->middleware(['auth:sanctum', 'throttle:integration'])
    ->group(function () {
        // Liveness + identity. Requires a valid token, no particular ability:
        // its purpose is to let the caller discover which abilities it holds.
        Route::get('/health', [HealthController::class, 'show'])->name('health');

        /*
         * The competition truth for one match, pushed by takeone.
         *
         * Requires the `match:write` ability explicitly: authenticating as the
         * service client is not the same as being allowed to write, and a token
         * minted for reading must be refused here even though it is valid.
         */
        Route::put('/matches/{video}', [\App\Http\Controllers\Api\V1\MatchController::class, 'update'])
            ->middleware('abilities:match:write')
            ->name('matches.update');

        /*
         * Ingest — a match video arriving from a matside camera, in chunks.
         *
         * Its own ability (`video:write`), separate from `match:write`: writing a
         * bout's competition truth onto a video somebody already uploaded, and
         * creating new media on this platform, are different powers. A token
         * minted for the first must not gain the second by implication.
         *
         * Chunked because `post_max_size` here is 512M and a five-minute bout at
         * 60fps is larger than that — and because the far end is a phone on a
         * competition hall's wifi, where an upload that cannot resume never
         * finishes. Chunk bodies are raw octet-stream, read from the request.
         *
         * Only `match` videos can be created here; see UploadController and this
         * repo's RULE #3.
         */
        /*
         * The bout's timeline — the markers on the video's bar.
         *
         * `match:write`, like the match record it belongs to: both are takeone
         * telling this platform what happened in a bout it owns. Separate from
         * MatchController because a bout has one match record and one timeline
         * PER VIDEO — two cameras filming the same bout start at different
         * moments, so their markers sit at different seconds.
         */
        Route::post('/matches/{video}/timeline', [\App\Http\Controllers\Api\V1\TimelineController::class, 'update'])
            ->middleware('abilities:match:write')
            ->name('matches.timeline');

        Route::post('/uploads', [\App\Http\Controllers\Api\V1\UploadController::class, 'begin'])
            ->middleware('abilities:video:write')
            ->name('uploads.begin');
        Route::patch('/uploads/{upload}', [\App\Http\Controllers\Api\V1\UploadController::class, 'chunk'])
            ->middleware('abilities:video:write')
            ->name('uploads.chunk');
        Route::post('/uploads/{upload}/complete', [\App\Http\Controllers\Api\V1\UploadController::class, 'complete'])
            ->middleware('abilities:video:write')
            ->name('uploads.complete');
        Route::delete('/uploads/{upload}', [\App\Http\Controllers\Api\V1\UploadController::class, 'cancel'])
            ->middleware('abilities:video:write')
            ->name('uploads.cancel');
    });
