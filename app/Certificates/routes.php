<?php

use App\Certificates\Controllers\VerifyController;
use App\Certificates\Middleware\EnsureCertificatesEnabled;
use Illuminate\Support\Facades\Route;

/*
| The one public door: what the QR on a printed certificate opens.
| Keyed by the 32-character random token, never by id or number, and throttled
| so the token space cannot be walked (CLAUDE.md → Anti-Enumeration).
*/
Route::get('/verify/{token}', VerifyController::class)
    ->where('token', '[A-Za-z0-9]{32}')
    ->middleware([EnsureCertificatesEnabled::class, 'throttle:30,1'])
    ->name('certificates.verify');

// The QR on an official's badge (App\Events\Support\EventBadges): the same rules.
Route::get('/verify/badge/{token}', \App\Certificates\Controllers\BadgeVerifyController::class)
    ->where('token', '[A-Za-z0-9]{32}')
    ->middleware([EnsureCertificatesEnabled::class, 'throttle:30,1'])
    ->name('badges.verify');
