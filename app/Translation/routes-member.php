<?php

use App\Translation\Controllers\EventTranslationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| The organiser reviewing what the machine wrote
|--------------------------------------------------------------------------
|
| Under /me with the `me.` prefix, so these land as `me.events.translations.*`
| and sit beside the event console's own routes. Every one of them re-checks
| EventAccess::canManage inside the controller — the /me stack proves who you
| are, never what you may manage.
|
| Bound as a plain string rather than a model, because PersonalEventController
| binds events the same way and the sealed event mirror (SealEventPage) resolves
| whatever the route holds. Route model binding by `{event:uuid}` here would
| work too; not doing it keeps this file free of an assumption about how the
| events module binds.
|
| ⚠️ THE PARAMETER MUST BE NAMED `{event}`. It was `{uuid}` until
| 2026-09-09, and that one word was the whole of a four-symptom bug:
| App\Events\Support\SealedEventRoutes mirrors this space into the sealed event
| app by matching the URI prefix `me/events/{event}` literally, so these four
| routes were the only ones of 116 that were never mirrored — while
| SealEventPage::rewriteLinks() still rewrote the page's URL into the sealed
| space. The organiser's Languages dialog therefore fetched
| `/e/{uuid}/admin/translations`, got a 404, showed "Action failed", then
| claimed nobody had ever read the event in another language (twenty-three were
| stored) and rendered its "Written in …" label blank, because that comes from
| the same dead endpoint. SealedEventRoutes now refuses to boot on a route in
| this space that uses any other parameter name, so it cannot happen again.
*/

Route::get('events/{event}/translations', [EventTranslationController::class, 'index'])
    ->name('events.translations');

Route::middleware('throttle:member-write')->group(function () {
    Route::put('events/{event}/translations', [EventTranslationController::class, 'update'])
        ->name('events.translations.update');

    // Starting work at a paid provider, so it keeps the translate ceiling on
    // top of the member-write one — an organiser cannot spend the account's
    // budget by holding down a button either.
    Route::post('events/{event}/translations', [EventTranslationController::class, 'retranslate'])
        ->middleware('throttle:translate')
        ->name('events.translations.retranslate');

    // Which languages the poster OFFERS. A setting, not a translation: it
    // hides languages without touching a word of them, so it carries no
    // translate ceiling — nothing here can start work at a provider.
    Route::put('events/{event}/translations/offered', [EventTranslationController::class, 'offered'])
        ->name('events.translations.offered');

    Route::delete('events/{event}/translations', [EventTranslationController::class, 'destroy'])
        ->name('events.translations.destroy');
});
