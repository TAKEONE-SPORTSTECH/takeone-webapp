<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PlatformController;
use App\Http\Controllers\InstructorReviewController;
use App\Http\Controllers\TrainerController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('clubs.explore');
    }
    return redirect()->route('login');
});

// Authentication routes
Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:register');

// Password reset routes
Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email')->middleware('throttle:password-reset');
Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.update')->middleware('throttle:password-reset');

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

// Public club page - no login required (used for QR code)
Route::get('/mobile/{slug}', [PlatformController::class, 'showPublic'])->name('clubs.show.public');

// Public trainer page - no login required (used for QR code)
Route::get('/t/{user}', [TrainerController::class, 'showPublic'])->name('trainer.show.public');

// Explore routes (accessible to authenticated users)
Route::middleware(['auth'])->group(function () {
    Route::get('/explore', [PlatformController::class, 'index'])->name('clubs.explore');
    Route::get('/clubs/nearby', [PlatformController::class, 'nearby'])->name('clubs.nearby');
    Route::get('/clubs/all', [PlatformController::class, 'all'])->name('clubs.all');
    Route::get('/clubs/{slug}', [PlatformController::class, 'show'])->name('clubs.show');
    Route::get('/clubs/{slug}/packages-json', [PlatformController::class, 'clubPackages'])->name('clubs.packages.json');
    Route::post('/clubs/join', [PlatformController::class, 'joinClub'])->name('clubs.join')->middleware('throttle:join-club');
    Route::post('/clubs/{slug}/events/{event}/join', [PlatformController::class, 'joinEvent'])->name('clubs.events.join')->middleware('throttle:join-club');
    Route::delete('/clubs/{slug}/events/{event}/leave', [PlatformController::class, 'leaveEvent'])->name('clubs.events.leave');
    Route::post('/clubs/{slug}/perks/{perk}/collect', [PlatformController::class, 'collectPerk'])->name('clubs.perks.collect')->middleware('throttle:social');
    Route::post('/clubs/{slug}/timeline/{post}/like', [PlatformController::class, 'toggleLike'])->name('clubs.timeline.like')->middleware('throttle:social');
    Route::post('/clubs/{slug}/timeline/{post}/comments', [PlatformController::class, 'addComment'])->name('clubs.timeline.comment')->middleware('throttle:social');
    Route::delete('/clubs/{slug}/timeline/{post}/comments/{comment}', [PlatformController::class, 'deleteComment'])->name('clubs.timeline.comment.delete');
    Route::get('/trainer/{user}', [TrainerController::class, 'show'])->name('trainer.show');
});

// Platform Admin routes (Super Admin only)
Route::middleware(['auth', 'verified', 'role:super-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', function () {
        return redirect()->route('admin.platform.clubs');
    })->name('platform.index');

    // All Clubs Management
    Route::get('/clubs', [App\Http\Controllers\Admin\PlatformController::class, 'clubs'])->name('platform.clubs');
    Route::get('/clubs/create', [App\Http\Controllers\Admin\PlatformController::class, 'createClub'])->name('platform.clubs.create');
    Route::post('/clubs', [App\Http\Controllers\Admin\ClubApiController::class, 'store'])->name('platform.clubs.store');
    Route::get('/clubs/{club}/edit', [App\Http\Controllers\Admin\PlatformController::class, 'editClub'])->name('platform.clubs.edit');
    Route::put('/clubs/{club}', [App\Http\Controllers\Admin\ClubApiController::class, 'update'])->name('platform.clubs.update');
    Route::delete('/clubs/{club}', [App\Http\Controllers\Admin\PlatformController::class, 'destroyClub'])->name('platform.clubs.destroy');
    Route::post('/clubs/{club}/upload-logo', [App\Http\Controllers\Admin\PlatformController::class, 'uploadClubLogo'])->name('platform.clubs.upload-logo')->middleware('throttle:uploads');
    Route::post('/clubs/{club}/upload-cover', [App\Http\Controllers\Admin\PlatformController::class, 'uploadClubCover'])->name('platform.clubs.upload-cover')->middleware('throttle:uploads');

    // Club API endpoints for modal
    Route::get('/api/users', [App\Http\Controllers\Admin\ClubApiController::class, 'getUsers']);
    Route::get('/api/clubs/{id}', [App\Http\Controllers\Admin\ClubApiController::class, 'getClub']);
    Route::post('/api/clubs/check-slug', [App\Http\Controllers\Admin\ClubApiController::class, 'checkSlug']);

    // All Members Management
    Route::get('/members', [App\Http\Controllers\Admin\PlatformController::class, 'members'])->name('platform.members');
    Route::post('/members', [App\Http\Controllers\Admin\PlatformController::class, 'storeMember'])->name('platform.members.store');
    Route::get('/members/{id}', [App\Http\Controllers\Admin\PlatformController::class, 'showMember'])->name('platform.members.show');
    Route::get('/members/{id}/edit', [App\Http\Controllers\Admin\PlatformController::class, 'editMember'])->name('platform.members.edit');
    Route::put('/members/{id}', [App\Http\Controllers\Admin\PlatformController::class, 'updateMember'])->name('platform.members.update');
    Route::delete('/members/{id}', [App\Http\Controllers\Admin\PlatformController::class, 'destroyMember'])->name('platform.members.destroy');
    Route::post('/members/{id}/upload-picture', [App\Http\Controllers\Admin\PlatformController::class, 'uploadMemberPicture'])->name('platform.members.upload-picture')->middleware('throttle:uploads');
    Route::post('/members/{id}/health', [App\Http\Controllers\Admin\PlatformController::class, 'storeMemberHealth'])->name('platform.members.store-health');
    Route::put('/members/{id}/health/{recordId}', [App\Http\Controllers\Admin\PlatformController::class, 'updateMemberHealth'])->name('platform.members.update-health');
    Route::post('/members/{id}/tournament', [App\Http\Controllers\Admin\PlatformController::class, 'storeMemberTournament'])->name('platform.members.store-tournament');

    // Database Backup & Restore
    Route::get('/backup', [App\Http\Controllers\Admin\PlatformController::class, 'backup'])->name('platform.backup');
    Route::get('/backup/download', [App\Http\Controllers\Admin\PlatformController::class, 'downloadBackup'])->name('platform.backup.download');
    Route::post('/backup/restore', [App\Http\Controllers\Admin\PlatformController::class, 'restoreBackup'])->name('platform.backup.restore');
    Route::get('/backup/export-users', [App\Http\Controllers\Admin\PlatformController::class, 'exportAuthUsers'])->name('platform.backup.export-users');
});

// Club Admin routes (Club owners and admins)
Route::middleware(['auth', 'verified', 'tenant'])->prefix('admin/club/{club}')->name('admin.club.')->group(function () {
    // Dashboard & club details
    Route::get('/dashboard', [App\Http\Controllers\Admin\ClubAdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/details', [App\Http\Controllers\Admin\ClubAdminController::class, 'details'])->name('details');
    Route::put('/', [App\Http\Controllers\Admin\ClubAdminController::class, 'update'])->name('update');
    Route::delete('/', [App\Http\Controllers\Admin\ClubAdminController::class, 'destroy'])->name('destroy');
    Route::post('/social-links', [App\Http\Controllers\Admin\ClubAdminController::class, 'storeSocialLink'])->name('social-links.store');
    Route::delete('/social-links/{link}', [App\Http\Controllers\Admin\ClubAdminController::class, 'destroySocialLink'])->name('social-links.destroy');
    Route::post('/transfer-ownership', [App\Http\Controllers\Admin\ClubAdminController::class, 'transferOwnership'])->name('transfer-ownership');
    Route::post('/create-owner', [App\Http\Controllers\Admin\ClubAdminController::class, 'createOwner'])->name('create-owner');

    // Gallery
    Route::get('/gallery', [App\Http\Controllers\Admin\ClubGalleryController::class, 'gallery'])->name('gallery');
    Route::post('/gallery/upload', [App\Http\Controllers\Admin\ClubGalleryController::class, 'uploadGallery'])->name('gallery.upload')->middleware('throttle:uploads');
    Route::post('/gallery/reorder', [App\Http\Controllers\Admin\ClubGalleryController::class, 'reorderGallery'])->name('gallery.reorder');
    Route::post('/gallery/youtube', [App\Http\Controllers\Admin\ClubGalleryController::class, 'saveYoutubeUrl'])->name('gallery.youtube');
    Route::delete('/gallery/{image}', [App\Http\Controllers\Admin\ClubGalleryController::class, 'destroyGalleryImage'])->name('gallery.destroy');

    // Facilities
    Route::get('/facilities', [App\Http\Controllers\Admin\ClubFacilityController::class, 'facilities'])->name('facilities');
    Route::post('/facilities', [App\Http\Controllers\Admin\ClubFacilityController::class, 'storeFacility'])->name('facilities.store');
    Route::get('/facilities/{facility}', [App\Http\Controllers\Admin\ClubFacilityController::class, 'getFacility'])->name('facilities.show');
    Route::put('/facilities/{facility}', [App\Http\Controllers\Admin\ClubFacilityController::class, 'updateFacility'])->name('facilities.update');
    Route::delete('/facilities/{facility}', [App\Http\Controllers\Admin\ClubFacilityController::class, 'destroyFacility'])->name('facilities.destroy');
    Route::post('/facilities/{facility}/upload-image', [App\Http\Controllers\Admin\ClubFacilityController::class, 'uploadFacilityImage'])->name('facilities.upload-image')->middleware('throttle:uploads');

    // Instructors
    Route::get('/instructors', [App\Http\Controllers\Admin\ClubInstructorController::class, 'instructors'])->name('instructors');
    Route::post('/instructors', [App\Http\Controllers\Admin\ClubInstructorController::class, 'storeInstructor'])->name('instructors.store');
    Route::post('/instructors/{instructor}/upload-photo', [App\Http\Controllers\Admin\ClubInstructorController::class, 'uploadInstructorPhoto'])->name('instructors.upload-photo')->middleware('throttle:uploads');
    Route::put('/instructors/{instructor}', [App\Http\Controllers\Admin\ClubInstructorController::class, 'updateInstructor'])->name('instructors.update');
    Route::delete('/instructors/{instructor}', [App\Http\Controllers\Admin\ClubInstructorController::class, 'destroyInstructor'])->name('instructors.destroy');

    // Activities
    Route::get('/activities', [App\Http\Controllers\Admin\ClubActivityController::class, 'activities'])->name('activities');
    Route::post('/activities', [App\Http\Controllers\Admin\ClubActivityController::class, 'storeActivity'])->name('activities.store');
    Route::put('/activities/{activity}', [App\Http\Controllers\Admin\ClubActivityController::class, 'updateActivity'])->name('activities.update');
    Route::delete('/activities/{activity}', [App\Http\Controllers\Admin\ClubActivityController::class, 'destroyActivity'])->name('activities.destroy');

    // Events
    Route::get('/events', [App\Http\Controllers\Admin\ClubEventController::class, 'events'])->name('events');
    Route::post('/events', [App\Http\Controllers\Admin\ClubEventController::class, 'storeEvent'])->name('events.store');
    Route::put('/events/{event}', [App\Http\Controllers\Admin\ClubEventController::class, 'updateEvent'])->name('events.update');
    Route::delete('/events/{event}', [App\Http\Controllers\Admin\ClubEventController::class, 'destroyEvent'])->name('events.destroy');
    Route::patch('/events/{event}/archive', [App\Http\Controllers\Admin\ClubEventController::class, 'archiveEvent'])->name('events.archive');

    // Timeline
    Route::get('/timeline', [App\Http\Controllers\Admin\ClubTimelineController::class, 'timeline'])->name('timeline');
    Route::post('/timeline', [App\Http\Controllers\Admin\ClubTimelineController::class, 'storeTimelinePost'])->name('timeline.store');
    Route::put('/timeline/{post}', [App\Http\Controllers\Admin\ClubTimelineController::class, 'updateTimelinePost'])->name('timeline.update');
    Route::delete('/timeline/{post}', [App\Http\Controllers\Admin\ClubTimelineController::class, 'destroyTimelinePost'])->name('timeline.destroy');

    // Perks
    Route::get('/perks', [App\Http\Controllers\Admin\ClubPerkController::class, 'perks'])->name('perks');
    Route::post('/perks', [App\Http\Controllers\Admin\ClubPerkController::class, 'storePerk'])->name('perks.store');
    Route::put('/perks/{perk}', [App\Http\Controllers\Admin\ClubPerkController::class, 'updatePerk'])->name('perks.update');
    Route::delete('/perks/{perk}', [App\Http\Controllers\Admin\ClubPerkController::class, 'destroyPerk'])->name('perks.destroy');

    // Achievements
    Route::get('/achievements', [App\Http\Controllers\Admin\ClubAchievementController::class, 'achievements'])->name('achievements');
    Route::post('/achievements', [App\Http\Controllers\Admin\ClubAchievementController::class, 'storeAchievement'])->name('achievements.store');
    Route::put('/achievements/{achievement}', [App\Http\Controllers\Admin\ClubAchievementController::class, 'updateAchievement'])->name('achievements.update');
    Route::delete('/achievements/{achievement}', [App\Http\Controllers\Admin\ClubAchievementController::class, 'destroyAchievement'])->name('achievements.destroy');

    // Packages
    Route::get('/packages', [App\Http\Controllers\Admin\ClubPackageController::class, 'packages'])->name('packages');
    Route::post('/packages', [App\Http\Controllers\Admin\ClubPackageController::class, 'storePackage'])->name('packages.store');
    Route::put('/packages/{package}', [App\Http\Controllers\Admin\ClubPackageController::class, 'updatePackage'])->name('packages.update');
    Route::delete('/packages/{package}', [App\Http\Controllers\Admin\ClubPackageController::class, 'destroyPackage'])->name('packages.destroy');

    // Members
    Route::get('/members', [App\Http\Controllers\Admin\ClubMemberAdminController::class, 'members'])->name('members');
    Route::post('/members', [App\Http\Controllers\Admin\ClubMemberAdminController::class, 'storeMember'])->name('members.store');
    Route::post('/members/walk-in', [App\Http\Controllers\Admin\ClubMemberAdminController::class, 'walkInRegistration'])->name('members.walk-in')->middleware('throttle:walk-in');
    Route::get('/members/search', [App\Http\Controllers\Admin\ClubMemberAdminController::class, 'searchUsers'])->name('members.search');
    Route::post('/subscriptions/{subscription}/approve-payment', [App\Http\Controllers\Admin\ClubMemberAdminController::class, 'approvePayment'])->name('subscriptions.approve-payment');
    Route::get('/subscriptions/{subscription}/payment-proof', [App\Http\Controllers\Admin\ClubMemberAdminController::class, 'servePaymentProof'])->name('subscriptions.payment-proof');

    // Roles
    Route::get('/roles', [App\Http\Controllers\Admin\ClubRoleController::class, 'roles'])->name('roles');
    Route::post('/roles', [App\Http\Controllers\Admin\ClubRoleController::class, 'storeRole'])->name('roles.store');
    Route::delete('/roles', [App\Http\Controllers\Admin\ClubRoleController::class, 'destroyRole'])->name('roles.destroy');

    // Financials
    Route::get('/financials', [App\Http\Controllers\Admin\ClubFinancialController::class, 'financials'])->name('financials');
    Route::post('/financials/income', [App\Http\Controllers\Admin\ClubFinancialController::class, 'storeIncome'])->name('financials.income');
    Route::post('/financials/expense', [App\Http\Controllers\Admin\ClubFinancialController::class, 'storeExpense'])->name('financials.expense');
    Route::put('/financials/{transaction}', [App\Http\Controllers\Admin\ClubFinancialController::class, 'updateTransaction'])->name('financials.update');
    Route::delete('/financials/{transaction}', [App\Http\Controllers\Admin\ClubFinancialController::class, 'destroyTransaction'])->name('financials.destroy');

    // Messages
    Route::get('/messages', [App\Http\Controllers\Admin\ClubMessageController::class, 'messages'])->name('messages');
    Route::post('/messages/send', [App\Http\Controllers\Admin\ClubMessageController::class, 'sendMessage'])->name('messages.send');

    // Analytics
    Route::get('/analytics', [App\Http\Controllers\Admin\ClubAnalyticsController::class, 'analytics'])->name('analytics');
});

// Unified Member routes
Route::middleware(['auth', 'verified'])->group(function () {
    // Redirect old /profile route to /member/{id}
    Route::get('/profile', function () {
        return redirect()->route('member.show', Auth::id());
    });

    // Redirect old routes to /family/members
    Route::get('/family', function () {
        return redirect()->route('members.index');
    });
    Route::get('/members', function () {
        return redirect()->route('members.index');
    });

    // Members listing (family dashboard)
    Route::get('/family/members', [MemberController::class, 'index'])->name('members.index');
    Route::get('/family/members/create', [MemberController::class, 'create'])->name('members.create');
    Route::post('/members', [MemberController::class, 'store'])->name('members.store');

    // Individual member routes
    Route::get('/member/{id}', [MemberController::class, 'show'])->name('member.show');
    Route::get('/member/{id}/edit', [MemberController::class, 'edit'])->name('member.edit');
    Route::put('/member/{id}', [MemberController::class, 'update'])->name('member.update');
    Route::delete('/member/{id}/confirm-delete', [MemberController::class, 'confirmDelete'])->name('member.confirm-delete');
    Route::delete('/member/{id}', [MemberController::class, 'destroy'])->name('member.destroy');
    Route::post('/member/{id}/upload-picture', [MemberController::class, 'uploadPicture'])->name('member.upload-picture')->middleware('throttle:uploads');
    Route::post('/member/{id}/health', [MemberController::class, 'storeHealth'])->name('member.store-health');
    Route::put('/member/{id}/health/{recordId}', [MemberController::class, 'updateHealth'])->name('member.update-health');
    Route::post('/member/{id}/tournament', [MemberController::class, 'storeTournament'])->name('member.store-tournament');
    Route::put('/member/goal/{goalId}', [MemberController::class, 'updateGoal'])->name('member.update-goal');

    // Keep old family routes for backward compatibility (redirect to new routes)
    Route::get('/family/create', function () {
        return redirect()->route('members.create');
    })->name('family.create');
    Route::post('/family', [FamilyController::class, 'store'])->name('family.store');
    Route::get('/family/{id}', function ($id) {
        return redirect()->route('member.show', $id);
    })->name('family.show');
    Route::get('/family/{id}/edit', function ($id) {
        return redirect()->route('member.edit', $id);
    })->name('family.edit');
    Route::put('/family/{id}', [MemberController::class, 'update'])->name('family.update');
    Route::post('/family/{id}/health', [MemberController::class, 'storeHealth'])->name('family.store-health');
    Route::put('/family/{id}/health/{recordId}', [MemberController::class, 'updateHealth'])->name('family.update-health');
    Route::put('/family/goal/{goalId}', [MemberController::class, 'updateGoal'])->name('family.update-goal');
    Route::post('/family/{id}/tournament', [MemberController::class, 'storeTournament'])->name('family.store-tournament');
    Route::post('/family/{id}/upload-picture', [MemberController::class, 'uploadPicture'])->name('family.upload-picture')->middleware('throttle:uploads');
    Route::delete('/family/{id}', [MemberController::class, 'destroy'])->name('family.destroy');
    Route::get('/family/dashboard', function () {
        return redirect()->route('members.index');
    });

    // Bills routes
    Route::get('/bills', [InvoiceController::class, 'index'])->name('bills.index');
    Route::get('/bills/{id}', [InvoiceController::class, 'show'])->name('bills.show');
    Route::get('/bills/{id}/receipt', [InvoiceController::class, 'receipt'])->name('bills.receipt');
    Route::get('/bills/{id}/pay', [InvoiceController::class, 'pay'])->name('bills.pay');
    Route::get('/bills/pay-all', [InvoiceController::class, 'payAll'])->name('bills.pay-all');

    // Instructor Review routes
    Route::get('/instructor/{instructorId}/reviews', [InstructorReviewController::class, 'index'])->name('instructor.reviews.index');
    Route::post('/instructor/{instructorId}/reviews', [InstructorReviewController::class, 'store'])->name('instructor.reviews.store')->middleware('throttle:social');
    Route::put('/instructor/reviews/{reviewId}', [InstructorReviewController::class, 'update'])->name('instructor.reviews.update');
});
