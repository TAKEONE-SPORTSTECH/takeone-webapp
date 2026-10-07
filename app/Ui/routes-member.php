<?php

use App\Ui\Controllers\AskController;
use App\Ui\Controllers\ManageController;
use App\Ui\Controllers\PreviewController;
use Illuminate\Support\Facades\Route;

/*
| /me/new-ui/on and /me/new-ui/off — the super-admin's own preview switch.
| POST, so a link elsewhere cannot flip it (CSRF applies).
*/
Route::get('/new-ui', [PreviewController::class, 'show'])->name('new-ui')->middleware('throttle:30,1');
Route::post('/new-ui/on', [PreviewController::class, 'on'])->name('new-ui.on')->middleware('throttle:30,1');
Route::post('/new-ui/off', [PreviewController::class, 'off'])->name('new-ui.off')->middleware('throttle:30,1');

/* /me/manage — the Manage side of Play/Manage (new UI only; 404 otherwise). */
Route::get('/manage', [ManageController::class, 'show'])->name('manage')->middleware('throttle:60,1');

/* /me/ask — Ask TAKEONE (new UI only). The POST is the expensive one: a model call. */
Route::get('/ask', [AskController::class, 'show'])->name('ask')->middleware('throttle:60,1');
Route::post('/ask', [AskController::class, 'message'])->name('ask.message')->middleware('throttle:15,1');
