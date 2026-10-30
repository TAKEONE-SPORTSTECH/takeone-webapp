<?php

/*
| National rankings, under /me.
|
| Registered by App\Support\Modules\ModuleServiceProvider with the `me` prefix,
| the `me.` name prefix and the ['web','auth','verified','two-factor'] stack.
| Read-only; each page answers 404 while RANKINGS_ENABLED is off.
*/

use App\Rankings\Controllers\RankingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/rankings', [RankingsController::class, 'index'])->name('rankings');
    Route::get('/rankings/{federation}', [RankingsController::class, 'federation'])->name('rankings.federation');
    Route::get('/rankings/{federation}/{category}', [RankingsController::class, 'category'])
        ->where('category', '[a-z0-9][a-z0-9:\-]{0,189}')
        ->name('rankings.category');
});
