<?php

/*
| TakeNote, the app: the page a phone opens, its manifest and its service
| worker — all under /me/takenote/ so the app's scope is that folder alone.
| Plain `web`: the page answers signed out with TakeNote's own sign-in, and
| carries no notes (TakeNoteAppController explains). The notes themselves are
| in routes-takenote.php, behind the member stack (loaded below).
*/

use App\DevBoard\Controllers\TakeNoteAppController;
use App\DevBoard\Controllers\TakeNoteJoinController;
use App\DevBoard\Middleware\TakeNoteLocale;
use App\DevBoard\Middleware\VerifiedOrLinkFriend;
use Illuminate\Support\Facades\Route;

// The board's member pages (Margin, TakeNote's notes, the desk): the member stack, with TakeNote's
// VerifiedOrLinkFriend in place of `verified` — see routes-takenote.php.
Route::middleware(['auth', VerifiedOrLinkFriend::class, 'two-factor'])->prefix('me')->name('me.')
    ->group(__DIR__.'/routes-takenote.php');

if (! config('devboard.enabled') || ! config('devboard.takenote')) {
    return;
}

// An invite link: whoever opens it first gets a notebook of their own — signing up right there if they
// have no account — and goes straight in (TakeNoteJoinController). Behind the friends flag.
if (config('devboard.friends')) {
    Route::middleware(['throttle:30,1', TakeNoteLocale::class])->group(function () {
        Route::get('/me/takenote/join/{token}', [TakeNoteJoinController::class, 'show'])->name('takenote.join')->where('token', '[A-Za-z0-9]{40}');
        Route::post('/me/takenote/join/{token}', [TakeNoteJoinController::class, 'store'])->name('takenote.join.store')->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:10,1');
        // TakeNote's own sign-up (from its sign-in door): the confirmation email brings them back to TakeNote
        Route::get('/me/takenote/signup', [TakeNoteJoinController::class, 'signupForm'])->name('takenote.signup');
        Route::post('/me/takenote/signup', [TakeNoteJoinController::class, 'signup'])->name('takenote.signup.store')->middleware('throttle:10,1');
        Route::post('/me/takenote/signup/resend', [TakeNoteJoinController::class, 'resend'])->name('takenote.signup.resend')->middleware('throttle:verification');
    });
}

Route::middleware(['throttle:60,1', TakeNoteLocale::class])->group(function () {
    Route::redirect('/takenote', '/me/takenote')->name('takenote');
    Route::get('/me/takenote', [TakeNoteAppController::class, 'app'])->name('takenote.app');
    // signing out lands back on TakeNote's own sign-in, not TAKEONE's (TakeNoteJoinController::signOut)
    Route::post('/me/takenote/sign-out', [TakeNoteJoinController::class, 'signOut'])->name('takenote.signout');
    Route::get('/me/takenote/manifest.webmanifest', [TakeNoteAppController::class, 'manifest'])->name('takenote.manifest');
    Route::get('/me/takenote/sw.js', [TakeNoteAppController::class, 'worker'])->name('takenote.worker');
});
