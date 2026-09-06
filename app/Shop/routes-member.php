<?php

/*
| Shop — the member's side.
|
| Registered by App\Support\Modules\ModuleServiceProvider under the /me prefix
| with the `me.` name prefix and the ['web','auth','verified','two-factor']
| stack — the same group these routes were declared in when they lived in
| routes/web.php, so every URL and route name is unchanged.
*/

use App\Shop\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

// Place an order and see my orders.
Route::get('/orders', [OrderController::class, 'index'])->name('orders');
Route::post('/orders', [OrderController::class, 'store'])->name('orders.store')->middleware('throttle:member-write');
Route::post('/orders/{order}/receive', [OrderController::class, 'receive'])->name('orders.receive')->middleware('throttle:member-write');
