<?php

/*
| The organiser's own door onto a mat, under /me.
|
| Registered by App\Support\Modules\ModuleServiceProvider with the `me` prefix,
| the `me.` name prefix and the ['web','auth','verified','two-factor'] stack —
| the same group this route was declared in when it lived in routes/web.php, so
| the URL and the name `me.events.court-display.preview` are unchanged.
|
| Only one route, and it is here rather than with the event console because what
| it renders is the BOARD: rehearsing a hall screen in a browser is a question
| about the mat, not about the event around it.
*/

use Illuminate\Support\Facades\Route;

    // Court display — rehearse the hall screen in a browser. The screen
    // itself never calls this: it renders a cached copy of the same view against
    // board state pushed over MQTT. Organiser-only, because the court comes
    // straight off the URL.
    Route::get('/events/{event:uuid}/court/{court}/preview', [\App\Scoreboard\Sports\Taekwondo\Controllers\CourtDisplayController::class, 'preview'])->name('events.court-display.preview')->where('court', '[^/]{1,40}')->middleware('throttle:60,1');
