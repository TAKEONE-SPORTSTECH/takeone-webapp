<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;

Route::get('/', function () {
    // If user is authenticated, show explore page
    if (Auth::check()) {
        return redirect()->route('clubs.explore');
    }
    // Otherwise redirect to login
    return redirect()->route('login');
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

Route::get('/email/verify/{id}/{hash}', function (Request $request, $id, $hash) {
    $user = \App\Models\User::findOrFail($id);

    if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
        abort(403, 'Invalid verification link.');
    }

    if ($user->hasVerifiedEmail()) {
        return redirect('/login')->with('status', 'Email already verified.');
    }

    $user->markEmailAsVerified();

    return redirect('/login')->with('status', 'Email verified successfully. You can now log in.');
})->middleware(['signed'])->name('verification.verify');

Route::post('/email/verification-notification', function (Request $request) {
    $request->user()->sendEmailVerificationNotification();
    return back()->with('resent', true);
})->middleware(['auth', 'throttle:6,1'])->name('verification.send');

// Explore routes (accessible to authenticated users)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/explore', [ClubController::class, 'index'])->name('clubs.explore');
    Route::get('/clubs/nearby', [ClubController::class, 'nearby'])->name('clubs.nearby');
    Route::get('/clubs/all', [ClubController::class, 'all'])->name('clubs.all');
});

// Family routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/profile', [FamilyController::class, 'profile'])->name('profile.show');
    Route::get('/profile/edit', [FamilyController::class, 'editProfile'])->name('profile.edit');
    Route::put('/profile', [FamilyController::class, 'updateProfile'])->name('profile.update');
    Route::post('/profile/upload-picture', [FamilyController::class, 'uploadProfilePicture'])->name('profile.upload-picture');
    Route::get('/family', [FamilyController::class, 'dashboard'])->name('family.dashboard');
    Route::get('/family/create', [FamilyController::class, 'create'])->name('family.create');
    Route::post('/family', [FamilyController::class, 'store'])->name('family.store');
    Route::get('/family/{id}', [FamilyController::class, 'show'])->name('family.show');
    Route::get('/family/{id}/edit', [FamilyController::class, 'edit'])->name('family.edit');
    Route::put('/family/{id}', [FamilyController::class, 'update'])->name('family.update');
    Route::post('/family/{id}/upload-picture', [FamilyController::class, 'uploadFamilyMemberPicture'])->name('family.upload-picture');
    Route::delete('/family/{id}', [FamilyController::class, 'destroy'])->name('family.destroy');

    // Bills routes
    Route::get('/bills', [InvoiceController::class, 'index'])->name('bills.index');
    Route::get('/bills/{id}', [InvoiceController::class, 'show'])->name('bills.show');
    Route::get('/bills/{id}/receipt', [InvoiceController::class, 'receipt'])->name('bills.receipt');
    Route::get('/bills/{id}/pay', [InvoiceController::class, 'pay'])->name('bills.pay');
    Route::get('/bills/pay-all', [InvoiceController::class, 'payAll'])->name('bills.pay-all');
});
