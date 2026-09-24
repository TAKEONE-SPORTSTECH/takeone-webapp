<?php

use App\Ai\Controllers\AiComposeController;
use App\Ai\Controllers\AiProviderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AI — one door for any signed-in member, one room for the super-admin
|--------------------------------------------------------------------------
|
| ⚠️ Both middleware stacks below are reproduced EXACTLY as they stood in
| routes/web.php. A module must never quietly opt out of session, CSRF, auth
| or a throttle by moving house — and the admin half was inside a group, so
| the group comes with it rather than being flattened into six repeats.
*/

/*
 * "Write with AI" for rich-text fields.
 *
 * Any authenticated member, because it GENERATES and nothing else: it reads no
 * record and writes none. The copilot ceiling is what stops it being a way to
 * spend somebody else's provider budget.
 */
Route::post('/ai/compose', [AiComposeController::class, 'compose'])
    ->middleware(['auth', 'verified', 'throttle:copilot'])
    ->name('ai.compose');

/*
 * The provider room. Keys are written here and encrypted at rest, so this is
 * super-admin only and two-factor is part of the stack, not an extra.
 */
Route::middleware(['auth', 'verified', 'two-factor', 'role:super-admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/ai', [AiProviderController::class, 'index'])->name('ai.index');

        // Coach with every module's skills (App\Ai\Copilot\SkillCopilot), beside the
        // club-only /copilot/* endpoints until config('copilot.skills') cuts over.
        Route::post('/copilot/skills/message', [\App\Ai\Controllers\CopilotSkillController::class, 'message'])->name('copilot.skills.message')->middleware('throttle:copilot');
        Route::post('/copilot/skills/apply', [\App\Ai\Controllers\CopilotSkillController::class, 'apply'])->name('copilot.skills.apply')->middleware('throttle:copilot');

        Route::post('/ai/providers', [AiProviderController::class, 'store'])
            ->name('ai.store')->middleware('throttle:admin-write');

        Route::put('/ai/providers/{provider}', [AiProviderController::class, 'update'])
            ->name('ai.update')->middleware('throttle:admin-write');

        Route::delete('/ai/providers/{provider}', [AiProviderController::class, 'destroy'])
            ->name('ai.destroy')->middleware('throttle:admin-write');

        Route::post('/ai/providers/{provider}/test', [AiProviderController::class, 'test'])
            ->name('ai.test')->middleware('throttle:admin-write');

        /*
         * Which model writes the event translations — Claude, a local Ollama,
         * or anything else configured above. Separate from the Copilot's
         * default on purpose; see AiProviderController::translationPrimary().
         */
        Route::post('/ai/translation-primary', [AiProviderController::class, 'translationPrimary'])
            ->name('ai.translation-primary')->middleware('throttle:admin-write');
    });
