<?php

use Illuminate\Support\Facades\Route;
use Takeone\Realtime\Http\Controllers\RealtimePluginController;

Route::get('/', [RealtimePluginController::class, 'index'])->name('index');
Route::put('/', [RealtimePluginController::class, 'update'])->name('update');
Route::post('/test', [RealtimePluginController::class, 'test'])->name('test');
