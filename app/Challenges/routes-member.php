<?php

/*
| Member-to-member competition, under /me.
|
| Registered by App\Support\Modules\ModuleServiceProvider with the `me` prefix,
| the `me.` name prefix and the ['web','auth','verified','two-factor'] stack —
| the same group these routes were declared in when they lived in
| routes/web.php, so every URL, route name and middleware is unchanged.
*/

use App\Challenges\Controllers\ChallengeController;
use Illuminate\Support\Facades\Route;

// Challenges & 1v1 duels (real, DB-backed).
Route::get('/challenge', [ChallengeController::class, 'index'])->name('challenge');
Route::get('/challenge/create', [ChallengeController::class, 'create'])->name('challenge.create');
Route::post('/challenge/duels', [ChallengeController::class, 'store'])->name('challenge.store')->middleware('throttle:member-write');
Route::get('/challenge/history', [ChallengeController::class, 'history'])->name('challenge.history');
Route::get('/challenge/duel/{duel}', [ChallengeController::class, 'duel'])->name('challenge.duel')->whereNumber('duel');
Route::post('/challenge/duel/{duel}/accept', [ChallengeController::class, 'accept'])->name('challenge.duel.accept')->middleware('throttle:member-write');
Route::post('/challenge/duel/{duel}/decline', [ChallengeController::class, 'decline'])->name('challenge.duel.decline')->middleware('throttle:member-write');
Route::post('/challenge/duel/{duel}/cancel', [ChallengeController::class, 'cancel'])->name('challenge.duel.cancel')->middleware('throttle:member-write');
Route::post('/challenge/duel/{duel}/report', [ChallengeController::class, 'report'])->name('challenge.duel.report')->middleware('throttle:member-write');
Route::post('/challenge/duel/{duel}/confirm', [ChallengeController::class, 'confirm'])->name('challenge.duel.confirm')->middleware('throttle:member-write');
Route::post('/challenge/duel/{duel}/dispute', [ChallengeController::class, 'dispute'])->name('challenge.duel.dispute')->middleware('throttle:member-write');
Route::put('/challenge/duel/{duel}', [ChallengeController::class, 'updateDuel'])->name('challenge.duel.update')->middleware('throttle:member-write');
Route::delete('/challenge/duel/{duel}', [ChallengeController::class, 'destroyDuel'])->name('challenge.duel.destroy')->middleware('throttle:member-write');
Route::post('/challenge/duel/{duel}/media', [ChallengeController::class, 'addMedia'])->name('challenge.duel.media.add')->middleware('throttle:uploads');
Route::delete('/challenge/duel/{duel}/media/{media}', [ChallengeController::class, 'deleteMedia'])->name('challenge.duel.media.delete')->whereNumber('media')->middleware('throttle:member-write');
Route::get('/challenge/duel/{duel}/witness-search', [ChallengeController::class, 'searchWitnesses'])->name('challenge.duel.witness.search')->middleware('throttle:60,1');
Route::post('/challenge/duel/{duel}/witnesses', [ChallengeController::class, 'addWitness'])->name('challenge.duel.witness.add')->middleware('throttle:member-write');
Route::patch('/challenge/duel/{duel}/witnesses/{witness}/respond', [ChallengeController::class, 'respondWitness'])->name('challenge.duel.witness.respond')->whereNumber('witness')->middleware('throttle:member-write');
Route::patch('/challenge/duel/{duel}/witnesses/{witness}/feedback', [ChallengeController::class, 'witnessFeedback'])->name('challenge.duel.witness.feedback')->whereNumber('witness')->middleware('throttle:member-write');
Route::delete('/challenge/duel/{duel}/witnesses/{witness}', [ChallengeController::class, 'deleteWitness'])->name('challenge.duel.witness.delete')->whereNumber('witness')->middleware('throttle:member-write');
Route::get('/challenge/{challenge}', [ChallengeController::class, 'show'])->name('challenge.show')->whereNumber('challenge');
Route::post('/challenge/{challenge}/join', [ChallengeController::class, 'join'])->name('challenge.join')->middleware('throttle:member-write');
Route::post('/challenge/{challenge}/leave', [ChallengeController::class, 'leave'])->name('challenge.leave')->middleware('throttle:member-write');
Route::post('/challenge/{challenge}/progress', [ChallengeController::class, 'progress'])->name('challenge.progress')->middleware('throttle:member-write');
