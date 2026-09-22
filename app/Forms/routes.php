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

Route::post('/f/{form:uuid}', [FormController::class, 'submit'])
    ->name('forms.submit')
    ->middleware('throttle:20,1');
