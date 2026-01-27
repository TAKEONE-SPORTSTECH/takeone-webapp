<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\InstructorReviewController;
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
Route::middleware(['auth'])->group(function () {
    Route::get('/explore', [ClubController::class, 'index'])->name('clubs.explore');
    Route::get('/clubs/nearby', [ClubController::class, 'nearby'])->name('clubs.nearby');
    Route::get('/clubs/all', [ClubController::class, 'all'])->name('clubs.all');
});

// Platform Admin routes (Super Admin only)
Route::middleware(['auth', 'verified', 'role:super-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', function () {
        return redirect()->route('admin.platform.clubs');
    })->name('platform.index');

    // All Clubs Management
    Route::get('/clubs', [App\Http\Controllers\Admin\PlatformController::class, 'clubs'])->name('platform.clubs');
    Route::get('/clubs/create', [App\Http\Controllers\Admin\PlatformController::class, 'createClub'])->name('platform.clubs.create');
    Route::post('/clubs', [App\Http\Controllers\Admin\PlatformController::class, 'storeClub'])->name('platform.clubs.store');
    Route::get('/clubs/{club}/edit', [App\Http\Controllers\Admin\PlatformController::class, 'editClub'])->name('platform.clubs.edit');
    Route::put('/clubs/{club}', [App\Http\Controllers\Admin\PlatformController::class, 'updateClub'])->name('platform.clubs.update');
    Route::delete('/clubs/{club}', [App\Http\Controllers\Admin\PlatformController::class, 'destroyClub'])->name('platform.clubs.destroy');
    Route::post('/clubs/{club}/upload-logo', [App\Http\Controllers\Admin\PlatformController::class, 'uploadClubLogo'])->name('platform.clubs.upload-logo');
    Route::post('/clubs/{club}/upload-cover', [App\Http\Controllers\Admin\PlatformController::class, 'uploadClubCover'])->name('platform.clubs.upload-cover');

    // All Members Management
    Route::get('/members', [App\Http\Controllers\Admin\PlatformController::class, 'members'])->name('platform.members');

    // Database Backup & Restore
    Route::get('/backup', [App\Http\Controllers\Admin\PlatformController::class, 'backup'])->name('platform.backup');
    Route::get('/backup/download', [App\Http\Controllers\Admin\PlatformController::class, 'downloadBackup'])->name('platform.backup.download');
    Route::post('/backup/restore', [App\Http\Controllers\Admin\PlatformController::class, 'restoreBackup'])->name('platform.backup.restore');
    Route::get('/backup/export-users', [App\Http\Controllers\Admin\PlatformController::class, 'exportAuthUsers'])->name('platform.backup.export-users');
});

// Club Admin routes (Club owners and admins)
Route::middleware(['auth', 'verified'])->prefix('admin/club/{club}')->name('admin.club.')->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\Admin\ClubAdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/details', [App\Http\Controllers\Admin\ClubAdminController::class, 'details'])->name('details');
    Route::get('/gallery', [App\Http\Controllers\Admin\ClubAdminController::class, 'gallery'])->name('gallery');
    Route::post('/gallery/upload', [App\Http\Controllers\Admin\ClubAdminController::class, 'uploadGallery'])->name('gallery.upload');
    Route::get('/facilities', [App\Http\Controllers\Admin\ClubAdminController::class, 'facilities'])->name('facilities');
    Route::post('/facilities', [App\Http\Controllers\Admin\ClubAdminController::class, 'storeFacility'])->name('facilities.store');
    Route::post('/facilities/{facility}/upload-image', [App\Http\Controllers\Admin\ClubAdminController::class, 'uploadFacilityImage'])->name('facilities.upload-image');
    Route::get('/instructors', [App\Http\Controllers\Admin\ClubAdminController::class, 'instructors'])->name('instructors');
    Route::post('/instructors', [App\Http\Controllers\Admin\ClubAdminController::class, 'storeInstructor'])->name('instructors.store');
    Route::post('/instructors/{instructor}/upload-photo', [App\Http\Controllers\Admin\ClubAdminController::class, 'uploadInstructorPhoto'])->name('instructors.upload-photo');
    Route::get('/activities', [App\Http\Controllers\Admin\ClubAdminController::class, 'activities'])->name('activities');
    Route::post('/activities', [App\Http\Controllers\Admin\ClubAdminController::class, 'storeActivity'])->name('activities.store');
    Route::get('/packages', [App\Http\Controllers\Admin\ClubAdminController::class, 'packages'])->name('packages');
    Route::post('/packages', [App\Http\Controllers\Admin\ClubAdminController::class, 'storePackage'])->name('packages.store');
    Route::get('/members', [App\Http\Controllers\Admin\ClubAdminController::class, 'members'])->name('members');
    Route::post('/members', [App\Http\Controllers\Admin\ClubAdminController::class, 'storeMember'])->name('members.store');
    Route::get('/roles', [App\Http\Controllers\Admin\ClubAdminController::class, 'roles'])->name('roles');
    Route::post('/roles', [App\Http\Controllers\Admin\ClubAdminController::class, 'storeRole'])->name('roles.store');
    Route::get('/financials', [App\Http\Controllers\Admin\ClubAdminController::class, 'financials'])->name('financials');
    Route::post('/financials/income', [App\Http\Controllers\Admin\ClubAdminController::class, 'storeIncome'])->name('financials.income');
    Route::post('/financials/expense', [App\Http\Controllers\Admin\ClubAdminController::class, 'storeExpense'])->name('financials.expense');
    Route::get('/messages', [App\Http\Controllers\Admin\ClubAdminController::class, 'messages'])->name('messages');
    Route::post('/messages/send', [App\Http\Controllers\Admin\ClubAdminController::class, 'sendMessage'])->name('messages.send');
    Route::get('/analytics', [App\Http\Controllers\Admin\ClubAdminController::class, 'analytics'])->name('analytics');
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
    Route::post('/family/{id}/health', [FamilyController::class, 'storeHealth'])->name('family.store-health');
    Route::put('/family/{id}/health/{recordId}', [FamilyController::class, 'updateHealth'])->name('family.update-health');
    Route::put('/family/goal/{goalId}', [FamilyController::class, 'updateGoal'])->name('family.update-goal');
    Route::post('/family/{id}/tournament', [FamilyController::class, 'storeTournament'])->name('family.store-tournament');
    Route::post('/family/{id}/upload-picture', [FamilyController::class, 'uploadFamilyMemberPicture'])->name('family.upload-picture');
    Route::delete('/family/{id}', [FamilyController::class, 'destroy'])->name('family.destroy');

    // Bills routes
    Route::get('/bills', [InvoiceController::class, 'index'])->name('bills.index');
    Route::get('/bills/{id}', [InvoiceController::class, 'show'])->name('bills.show');
    Route::get('/bills/{id}/receipt', [InvoiceController::class, 'receipt'])->name('bills.receipt');
    Route::get('/bills/{id}/pay', [InvoiceController::class, 'pay'])->name('bills.pay');
    Route::get('/bills/pay-all', [InvoiceController::class, 'payAll'])->name('bills.pay-all');

    // Instructor Review routes
    Route::get('/instructor/{instructorId}/reviews', [InstructorReviewController::class, 'index'])->name('instructor.reviews.index');
    Route::post('/instructor/{instructorId}/reviews', [InstructorReviewController::class, 'store'])->name('instructor.reviews.store');
    Route::put('/instructor/reviews/{reviewId}', [InstructorReviewController::class, 'update'])->name('instructor.reviews.update');
});
