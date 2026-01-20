<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;

Route::get('/', function () {
    return view('welcome');
});

// Authentication routes
Route::get('/login', [AuthenticatedSessionController::class, 'create'])
                ->name('login');

Route::post('/login', [AuthenticatedSessionController::class, 'store']);

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
                ->name('logout');

Route::get('/register', [RegisteredUserController::class, 'create'])
                ->name('register');

Route::post('/register', [RegisteredUserController::class, 'store']);

// Password reset routes
Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])
                ->name('password.request');

Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
                ->name('password.email');

Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])
                ->name('password.reset');

Route::post('/reset-password', [NewPasswordController::class, 'store'])
                ->name('password.update');

// Email verification routes
Route::get('/email/verify', function () {
    return view('auth.verify-email');
})->middleware('auth')->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', function (Request $request) {
    $request->user()->markEmailAsVerified();
    return redirect('/')->with('verified', true);
})->middleware(['auth', 'signed'])->name('verification.verify');

Route::post('/email/verification-notification', function (Request $request) {
    $request->user()->sendEmailVerificationNotification();
    return back()->with('resent', true);
})->middleware(['auth', 'throttle:6,1'])->name('verification.send');

// Family routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/profile', [FamilyController::class, 'profile'])->name('profile.show');
    Route::get('/family', [FamilyController::class, 'dashboard'])->name('family.dashboard');
    Route::get('/family/create', [FamilyController::class, 'create'])->name('family.create');
    Route::post('/family', [FamilyController::class, 'store'])->name('family.store');
    Route::get('/family/{id}', [FamilyController::class, 'show'])->name('family.show');
    Route::get('/family/{id}/edit', [FamilyController::class, 'edit'])->name('family.edit');
    Route::put('/family/{id}', [FamilyController::class, 'update'])->name('family.update');
    Route::delete('/family/{id}', [FamilyController::class, 'destroy'])->name('family.destroy');

    // Invoice routes
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/{id}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('/invoices/{id}/pay', [InvoiceController::class, 'pay'])->name('invoices.pay');
    Route::get('/invoices/pay-all', [InvoiceController::class, 'payAll'])->name('invoices.pay-all');
});
