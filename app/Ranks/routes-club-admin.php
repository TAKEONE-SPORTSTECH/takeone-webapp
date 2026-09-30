<?php

/*
| Ranks & grading — under admin/club/{club}, names admin.club.ranks*.
| Every ranked activity is addressed by its link's uuid, never an id. Scoped
| binding is off on purpose: the controller checks the link belongs to {club}
| itself, and answers not-found for one that does not.
*/

use App\Ranks\Controllers\RanksController;
use Illuminate\Support\Facades\Route;

Route::get('/ranks', [RanksController::class, 'index'])->name('ranks');
Route::post('/ranks/{link:uuid}/members/{member:uuid}/rank', [RanksController::class, 'setRank'])->name('ranks.set')->middleware('throttle:admin-write')->withoutScopedBindings();
Route::get('/ranks/{link:uuid}/members/{member:uuid}/history', [RanksController::class, 'history'])->name('ranks.history')->middleware('throttle:60,1')->withoutScopedBindings();
Route::put('/ranks/{link:uuid}/package', [RanksController::class, 'choosePackage'])->name('ranks.package')->middleware('throttle:admin-write')->withoutScopedBindings();
Route::put('/ranks/{link:uuid}/requirements', [RanksController::class, 'saveRequirements'])->name('ranks.requirements')->middleware('throttle:admin-write')->withoutScopedBindings();
Route::post('/ranks/{link:uuid}/gradings', [RanksController::class, 'createGrading'])->name('ranks.gradings.store')->middleware('throttle:admin-write')->withoutScopedBindings();
Route::get('/ranks/{link:uuid}/gradings/{grading:uuid}', [RanksController::class, 'showGrading'])->name('ranks.gradings.show')->middleware('throttle:60,1')->withoutScopedBindings();
Route::put('/ranks/{link:uuid}/gradings/{grading:uuid}', [RanksController::class, 'recordGrading'])->name('ranks.gradings.record')->middleware('throttle:admin-write')->withoutScopedBindings();
Route::delete('/ranks/{link:uuid}/gradings/{grading:uuid}', [RanksController::class, 'deleteGrading'])->name('ranks.gradings.delete')->middleware('throttle:admin-write')->withoutScopedBindings();
