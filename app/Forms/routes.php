<?php

use App\Forms\Controllers\FormController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Forms — the one public surface
|--------------------------------------------------------------------------
|
| A form is reached by its LINK, and the link is the protection: /f/{uuid}
| binds on the unguessable key, never on an id somebody could count up from
| (CLAUDE.md, Unpredictable Resource Identifiers). Guest-reachable on purpose —
| a waiver or an intake sheet is often filled in by somebody who has no account
| yet — so the submit side is throttled rather than gated.
|
| Loaded by App\Support\Modules\ModuleServiceProvider inside the `web` stack,
| with exactly the middleware these two lines carried in routes/web.php. A
| module must never quietly opt out of session or CSRF.
*/

Route::get('/f/{form:uuid}', [FormController::class, 'show'])->name('forms.show');

Route::get('/f/{form:uuid}/cover', [FormController::class, 'cover'])
    ->name('forms.cover')
    ->middleware('throttle:120,1');

Route::post('/f/{form:uuid}', [FormController::class, 'submit'])
    ->name('forms.submit')
    ->middleware('throttle:20,1');

/*
| The super-admin's side: every form, one form's answers, charts and proposed
| decisions. Same stack as every other platform-admin page (app/Platform).
*/
Route::middleware(['auth', 'verified', 'two-factor', 'role:super-admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/forms', [\App\Forms\Controllers\FormAdminController::class, 'index'])->name('forms.index');
        Route::get('/forms/new', [\App\Forms\Controllers\FormAdminController::class, 'create'])->name('forms.create');
        Route::post('/forms', [\App\Forms\Controllers\FormAdminController::class, 'store'])->middleware('throttle:admin-write')->name('forms.store');
        Route::get('/forms/{form:uuid}/edit', [\App\Forms\Controllers\FormAdminController::class, 'edit'])->name('forms.edit');
        Route::put('/forms/{form:uuid}', [\App\Forms\Controllers\FormAdminController::class, 'update'])->middleware('throttle:admin-write')->name('forms.update');
        Route::post('/forms/{form:uuid}/duplicate', [\App\Forms\Controllers\FormAdminController::class, 'duplicate'])->middleware('throttle:admin-write')->name('forms.duplicate');
        Route::delete('/forms/{form:uuid}', [\App\Forms\Controllers\FormAdminController::class, 'destroy'])->middleware('throttle:admin-write')->name('forms.destroy');
        Route::get('/forms/{form:uuid}', [\App\Forms\Controllers\FormAdminController::class, 'show'])->middleware('throttle:60,1')->name('forms.show');
        Route::get('/forms/{form:uuid}/export', [\App\Forms\Controllers\FormAdminController::class, 'export'])->middleware('throttle:10,1')->name('forms.export');
        Route::post('/forms/{form:uuid}/cover', [\App\Forms\Controllers\FormAdminController::class, 'cover'])->middleware('throttle:uploads')->name('forms.cover');
        Route::delete('/forms/{form:uuid}/cover', [\App\Forms\Controllers\FormAdminController::class, 'removeCover'])->middleware('throttle:admin-write')->name('forms.cover.remove');
        Route::get('/forms/{form:uuid}/pdf', [\App\Forms\Controllers\FormAdminController::class, 'pdf'])->middleware('throttle:10,1')->name('forms.pdf');
        Route::get('/forms/{form:uuid}/poster', [\App\Forms\Controllers\FormAdminController::class, 'poster'])->middleware('throttle:10,1')->name('forms.poster');
        Route::post('/forms/{form:uuid}/toggle', [\App\Forms\Controllers\FormAdminController::class, 'toggle'])->middleware('throttle:admin-write')->name('forms.toggle');
    });
