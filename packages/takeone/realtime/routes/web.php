<?php

use Illuminate\Support\Facades\Route;
use Takeone\Realtime\Http\Controllers\RealtimeTokenController;

// Browser fetches its short-lived MQTT credentials here.
Route::get('/realtime/token', RealtimeTokenController::class)->name('realtime.token');
