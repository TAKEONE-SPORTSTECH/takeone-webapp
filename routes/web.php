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
use App\Http\Controllers\TwoFactorController;

Route::get('/', function (Request $request) {
    if (Auth::check()) {
        return redirect(\App\Support\Landing::url($request));
    }
    return redirect()->route('login');
});

// Two-Factor Authentication challenge (no auth required — user is between login and session)
Route::get('/two-factor-challenge', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
Route::post('/two-factor-challenge', [TwoFactorController::class, 'verifyChallenge'])->name('two-factor.verify')->middleware('throttle:6,1');

// Security Settings (requires full auth + 2FA if enabled)
Route::middleware(['auth', 'verified', 'two-factor'])->group(function () {
    Route::get('/security', [TwoFactorController::class, 'show'])->name('security.show');
    Route::post('/security/two-factor/setup', [TwoFactorController::class, 'setup'])->name('security.2fa.setup');
    Route::get('/security/two-factor/setup', [TwoFactorController::class, 'setup'])->name('security.2fa.setup.get');
    Route::post('/security/two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('security.2fa.confirm');
    Route::post('/security/two-factor/disable', [TwoFactorController::class, 'disable'])->name('security.2fa.disable');
    Route::post('/security/two-factor/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])->name('security.2fa.recovery-codes');
    Route::post('/security/password', [TwoFactorController::class, 'changePassword'])->name('security.password.change')->middleware('throttle:6,1');

    // Business (Chain) — create & manage your own chain
    Route::get('/business/create', [App\Http\Controllers\BusinessController::class, 'setup'])->name('business.setup');
    Route::post('/business', [App\Http\Controllers\BusinessController::class, 'store'])->name('business.store')->middleware('throttle:6,1');

    // Switch between Personal and Business view modes (Facebook-style)
    Route::post('/switch-view', [App\Http\Controllers\BusinessController::class, 'switchView'])->name('view.switch');
});

// Business (Chain) dashboard — requires an APPROVED business
Route::middleware(['auth', 'verified', 'two-factor', 'business'])->prefix('business')->name('business.')->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\BusinessDashboardController::class, 'index'])->name('dashboard');
});

// Personal (member) mobile experience — shared mobile shell
Route::middleware(['auth', 'verified', 'two-factor'])->prefix('me')->name('me.')->group(function () {
    Route::get('/',          [App\Http\Controllers\PersonalMobileController::class, 'home'])->name('home');
    Route::get('/schedule',  [App\Http\Controllers\PersonalMobileController::class, 'schedule'])->name('schedule');
    Route::get('/profile',   [App\Http\Controllers\PersonalMobileController::class, 'profile'])->name('profile');
    Route::get('/packages',  [App\Http\Controllers\PersonalMobileController::class, 'packages'])->name('packages');
    Route::get('/progress',  [App\Http\Controllers\PersonalMobileController::class, 'progress'])->name('progress');
    Route::get('/payments',  [App\Http\Controllers\PersonalMobileController::class, 'payments'])->name('payments');
    Route::get('/community', [App\Http\Controllers\PersonalMobileController::class, 'community'])->name('community');
    Route::get('/events',    [App\Http\Controllers\PersonalMobileController::class, 'events'])->name('events');
    Route::get('/settings',  [App\Http\Controllers\PersonalMobileController::class, 'settings'])->name('settings');
});

// Authentication routes
Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login')->middleware('no-store');
Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
Route::get('/register', [RegisteredUserController::class, 'create'])->name('register')->middleware('no-store');
Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:register');
Route::get('/register/wizard/packages', [App\Http\Controllers\Auth\WizardRegistrationController::class, 'packages'])->name('register.wizard.packages')->middleware('throttle:60,1');
Route::post('/register/wizard/upload-temp', [App\Http\Controllers\Auth\WizardRegistrationController::class, 'uploadTemp'])->name('register.wizard.upload')->middleware('throttle:uploads');
Route::post('/register/wizard/submit', [App\Http\Controllers\Auth\WizardRegistrationController::class, 'submit'])->name('register.wizard.submit')->middleware('throttle:register');

// Password reset routes
Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request')->middleware('no-store');
Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email')->middleware('throttle:password-reset');
Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset')->middleware('no-store');
Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.update')->middleware('throttle:password-reset');

// Email verification routes
Route::get('/email/verify', function (Request $request) {
    if ($request->user()->hasVerifiedEmail()) {
        $intended = session()->pull('url.intended', \App\Support\Landing::url($request));
        session()->forget('club.context');
        return redirect($intended);
    }
    return view('auth.verify-email');
})->middleware('auth')->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', function (Request $request, $id, $hash) {
    $user = \App\Models\User::findOrFail($id);

    if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
        abort(403, 'Invalid verification link.');
    }

    $intended = $request->query('intended') ?: session()->pull('url.intended', \App\Support\Landing::url($request));
    session()->forget('club.context');

    // Log the user in and set the 2FA session flag so all middleware passes.
    if (!auth()->check()) {
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('two_factor.verified', true);
    }

    if ($user->hasVerifiedEmail()) {
        return redirect($intended)->with('success', 'Your email is already verified.');
    }

    $user->markEmailAsVerified();

    // Redirect to the verify-email page with verified=true so the success
    // message shows in the same auth card the user is familiar with.
    // The page will auto-redirect them to the app after 3 seconds.
    return redirect()->route('verification.notice')
        ->with('verified', true)
        ->with('verified_intended', $intended);
})->middleware(['signed'])->name('verification.verify');

Route::post('/email/verification-notification', function (Request $request) {
    $request->user()->sendEmailVerificationNotification();
    return back()->with('resent', true);
})->middleware(['auth', 'throttle:verification'])->name('verification.send');

// Public resend — for users who are not yet logged in (e.g. walk-in registrations).
Route::post('/email/resend-verification', function (Request $request) {
    $request->validate(['email' => 'required|email']);
    $user = \App\Models\User::where('email', $request->email)->first();
    if ($user && !$user->hasVerifiedEmail()) {
        $user->sendEmailVerificationNotification();
    }
    // Always return the same message to avoid email enumeration.
    return redirect()->route('login')
        ->with('info', 'If that email exists and is unverified, a new verification link has been sent.');
})->middleware('throttle:verification')->name('verification.resend.public');

// Public club page - no login required (used for QR code)
Route::get('/mobile/{country}/{slug}', [PlatformController::class, 'showPublic'])->name('clubs.show.public');

// Public trainer page - no login required (used for QR code)
Route::get('/t/{user}', [TrainerController::class, 'showPublic'])->name('trainer.show.public');

// Explore routes (accessible to authenticated users)
Route::middleware(['auth', 'two-factor'])->group(function () {
    Route::get('/explore', [PlatformController::class, 'index'])->name('clubs.explore');

    // Stop impersonating — available to the (impersonated) session, restores the admin.
    Route::post('/impersonate/leave', [App\Http\Controllers\ImpersonationController::class, 'stop'])->name('impersonate.leave');
    Route::get('/clubs/nearby', [PlatformController::class, 'nearby'])->name('clubs.nearby');
    Route::get('/clubs/all', [PlatformController::class, 'all'])->name('clubs.all');
    Route::get('/trainer/{user}', [TrainerController::class, 'show'])->name('trainer.show');

    // Country-prefixed club routes
    Route::prefix('{country}')->where(['country' => '[a-z]{2,3}'])->group(function () {
        Route::get('/clubs/{slug}', [PlatformController::class, 'show'])->name('clubs.show');
        Route::get('/clubs/{slug}/packages-json', [PlatformController::class, 'clubPackages'])->name('clubs.packages.json');
        Route::post('/clubs/join', [PlatformController::class, 'joinClub'])->name('clubs.join')->middleware('verified', 'throttle:join-club');
        Route::post('/clubs/{slug}/events/{event}/join', [PlatformController::class, 'joinEvent'])->name('clubs.events.join')->middleware('verified', 'throttle:join-club');
        Route::delete('/clubs/{slug}/events/{event}/leave', [PlatformController::class, 'leaveEvent'])->name('clubs.events.leave')->middleware('verified', 'throttle:social');
        Route::post('/clubs/{slug}/perks/{perk}/collect', [PlatformController::class, 'collectPerk'])->name('clubs.perks.collect')->middleware('verified', 'throttle:social');
        Route::post('/clubs/{slug}/timeline/{post}/like', [PlatformController::class, 'toggleLike'])->name('clubs.timeline.like')->middleware('verified', 'throttle:social');
        Route::post('/clubs/{slug}/timeline/{post}/comments', [PlatformController::class, 'addComment'])->name('clubs.timeline.comment')->middleware('verified', 'throttle:social');
        Route::delete('/clubs/{slug}/timeline/{post}/comments/{comment}', [PlatformController::class, 'deleteComment'])->name('clubs.timeline.comment.delete')->middleware('verified', 'throttle:social');
    });
});

// Platform Admin routes (Super Admin only)
Route::middleware(['auth', 'verified', 'two-factor', 'role:super-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', function () {
        return redirect()->route('admin.platform.clubs');
    })->name('platform.index');

    // Start impersonating a user (super-admin only — enforced by group middleware).
    Route::post('/impersonate/{user}', [App\Http\Controllers\ImpersonationController::class, 'start'])->name('impersonate.start')->middleware('throttle:admin-write');

    // All Clubs Management
    Route::get('/clubs', [App\Http\Controllers\Admin\PlatformController::class, 'clubs'])->name('platform.clubs');
    Route::get('/clubs/create', [App\Http\Controllers\Admin\PlatformController::class, 'createClub'])->name('platform.clubs.create');
    Route::post('/clubs', [App\Http\Controllers\Admin\ClubApiController::class, 'store'])->name('platform.clubs.store')->middleware('throttle:admin-write');
    Route::get('/clubs/{club}/edit', [App\Http\Controllers\Admin\PlatformController::class, 'editClub'])->name('platform.clubs.edit');
    Route::put('/clubs/{club}', [App\Http\Controllers\Admin\ClubApiController::class, 'update'])->name('platform.clubs.update')->middleware('throttle:admin-write');
    Route::delete('/clubs/{club}', [App\Http\Controllers\Admin\PlatformController::class, 'destroyClub'])->name('platform.clubs.destroy')->middleware('throttle:admin-write');
    Route::post('/clubs/{club}/upload-logo', [App\Http\Controllers\Admin\PlatformController::class, 'uploadClubLogo'])->name('platform.clubs.upload-logo')->middleware('throttle:uploads');
    Route::post('/clubs/{club}/upload-cover', [App\Http\Controllers\Admin\PlatformController::class, 'uploadClubCover'])->name('platform.clubs.upload-cover')->middleware('throttle:uploads');

    // Club API endpoints for modal
    Route::get('/api/users', [App\Http\Controllers\Admin\ClubApiController::class, 'getUsers']);
    Route::get('/api/clubs/{id}', [App\Http\Controllers\Admin\ClubApiController::class, 'getClub']);
    Route::post('/api/clubs/check-slug', [App\Http\Controllers\Admin\ClubApiController::class, 'checkSlug'])->middleware('throttle:admin-write');

    // All Members Management
    Route::get('/members', [App\Http\Controllers\Admin\PlatformController::class, 'members'])->name('platform.members');
    Route::post('/members', [App\Http\Controllers\Admin\PlatformController::class, 'storeMember'])->name('platform.members.store')->middleware('throttle:admin-write');
    Route::get('/members/{id}', [App\Http\Controllers\Admin\PlatformController::class, 'showMember'])->name('platform.members.show');
    Route::get('/members/{id}/edit', [App\Http\Controllers\Admin\PlatformController::class, 'editMember'])->name('platform.members.edit');
    Route::put('/members/{id}', [App\Http\Controllers\Admin\PlatformController::class, 'updateMember'])->name('platform.members.update')->middleware('throttle:admin-write');
    Route::delete('/members/{id}', [App\Http\Controllers\Admin\PlatformController::class, 'destroyMember'])->name('platform.members.destroy')->middleware('throttle:admin-write');
    Route::post('/members/{id}/upload-picture', [App\Http\Controllers\Admin\PlatformController::class, 'uploadMemberPicture'])->name('platform.members.upload-picture')->middleware('throttle:uploads');
    Route::post('/members/{id}/health', [App\Http\Controllers\Admin\PlatformController::class, 'storeMemberHealth'])->name('platform.members.store-health')->middleware('throttle:admin-write');
    Route::put('/members/{id}/health/{recordId}', [App\Http\Controllers\Admin\PlatformController::class, 'updateMemberHealth'])->name('platform.members.update-health')->middleware('throttle:admin-write');
    Route::post('/members/{id}/tournament', [App\Http\Controllers\Admin\PlatformController::class, 'storeMemberTournament'])->name('platform.members.store-tournament')->middleware('throttle:admin-write');
    Route::get('/members/{user}/popup', [App\Http\Controllers\Admin\PlatformController::class, 'memberPopup'])->name('platform.members.popup');
    Route::get('/members/{user}/enroll-data', [App\Http\Controllers\Admin\PlatformController::class, 'memberEnrollData'])->name('platform.members.enroll-data');

    // Database Backup & Restore
    Route::get('/backup', [App\Http\Controllers\Admin\PlatformController::class, 'backup'])->name('platform.backup');
    Route::get('/backup/download', [App\Http\Controllers\Admin\PlatformController::class, 'downloadBackup'])->name('platform.backup.download');
    Route::post('/backup/restore', [App\Http\Controllers\Admin\PlatformController::class, 'restoreBackup'])->name('platform.backup.restore')->middleware('throttle:backup');
    Route::get('/backup/export-users', [App\Http\Controllers\Admin\PlatformController::class, 'exportAuthUsers'])->name('platform.backup.export-users');

    // Audit Log
    Route::get('/audit-log', [App\Http\Controllers\Admin\PlatformController::class, 'auditLog'])->name('platform.audit-log');

    // Business (Chain) approvals
    Route::get('/businesses', [App\Http\Controllers\Admin\BusinessApprovalController::class, 'index'])->name('platform.businesses');
    Route::post('/businesses/{business}/approve', [App\Http\Controllers\Admin\BusinessApprovalController::class, 'approve'])->name('platform.businesses.approve')->middleware('throttle:admin-write');
    Route::post('/businesses/{business}/reject', [App\Http\Controllers\Admin\BusinessApprovalController::class, 'reject'])->name('platform.businesses.reject')->middleware('throttle:admin-write');
    Route::put('/businesses/{business}', [App\Http\Controllers\Admin\BusinessApprovalController::class, 'update'])->name('platform.businesses.update')->middleware('throttle:admin-write');
    Route::get('/businesses/{business}/history', [App\Http\Controllers\Admin\BusinessApprovalController::class, 'history'])->name('platform.businesses.history');
    Route::get('/businesses/{business}/clubs', [App\Http\Controllers\Admin\BusinessApprovalController::class, 'clubs'])->name('platform.businesses.clubs');
    Route::post('/businesses/{business}/clubs/attach', [App\Http\Controllers\Admin\BusinessApprovalController::class, 'attachClub'])->name('platform.businesses.clubs.attach')->middleware('throttle:admin-write');
    Route::post('/businesses/{business}/clubs/detach', [App\Http\Controllers\Admin\BusinessApprovalController::class, 'detachClub'])->name('platform.businesses.clubs.detach')->middleware('throttle:admin-write');
    Route::post('/businesses/{business}/transfer-owner', [App\Http\Controllers\Admin\BusinessApprovalController::class, 'transferOwner'])->name('platform.businesses.transfer-owner')->middleware('throttle:admin-write');
    Route::delete('/businesses/{business}', [App\Http\Controllers\Admin\BusinessApprovalController::class, 'destroy'])->name('platform.businesses.destroy')->middleware('throttle:admin-write');
});

// Club Admin routes (Club owners and admins)
Route::middleware(['auth', 'verified', 'two-factor', 'tenant', 'throttle:admin-write'])->prefix('admin/club/{club}')->name('admin.club.')->group(function () {
    // Dashboard & club details
    Route::get('/dashboard', [App\Http\Controllers\Admin\ClubAdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/details', [App\Http\Controllers\Admin\ClubAdminController::class, 'details'])->name('details');
    Route::put('/', [App\Http\Controllers\Admin\ClubAdminController::class, 'update'])->name('update');
    Route::delete('/', [App\Http\Controllers\Admin\ClubAdminController::class, 'destroy'])->name('destroy');
    Route::post('/social-links', [App\Http\Controllers\Admin\ClubAdminController::class, 'storeSocialLink'])->name('social-links.store');
    Route::delete('/social-links/{link}', [App\Http\Controllers\Admin\ClubAdminController::class, 'destroySocialLink'])->name('social-links.destroy');
    Route::post('/transfer-ownership', [App\Http\Controllers\Admin\ClubAdminController::class, 'transferOwnership'])->name('transfer-ownership')->middleware('throttle:admin-write');
    Route::post('/create-owner', [App\Http\Controllers\Admin\ClubAdminController::class, 'createOwner'])->name('create-owner')->middleware('throttle:admin-write');

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
    Route::get('/members/cards', [App\Http\Controllers\Admin\ClubMemberAdminController::class, 'membersCards'])->name('members.cards');
    Route::get('/members/{user}/popup', [App\Http\Controllers\Admin\ClubMemberAdminController::class, 'memberPopup'])->name('members.popup');
    Route::get('/members/popup-demo', [App\Http\Controllers\Admin\ClubMemberAdminController::class, 'memberPopupDemo'])->name('members.popup-demo');
    Route::get('/members/{user}/enroll-packages', [App\Http\Controllers\Admin\ClubMemberAdminController::class, 'enrollPackages'])->name('members.enroll-packages');
    Route::post('/members/{user}/enroll', [App\Http\Controllers\Admin\ClubMemberAdminController::class, 'enrollMember'])->name('members.enroll');
    Route::delete('/members/{user}/remove', [App\Http\Controllers\Admin\ClubMemberAdminController::class, 'removeMember'])->name('members.remove')->middleware('throttle:admin-write');
    Route::get('/members/import-template', [App\Http\Controllers\Admin\ClubMemberAdminController::class, 'importTemplate'])->name('members.import-template');
    Route::post('/members/import', [App\Http\Controllers\Admin\ClubMemberAdminController::class, 'importMembers'])->name('members.import')->middleware('throttle:admin-write');
    Route::post('/subscriptions/{subscription}/approve-payment', [App\Http\Controllers\Admin\ClubMemberAdminController::class, 'approvePayment'])->name('subscriptions.approve-payment');
    Route::get('/subscriptions/{subscription}/payment-proof', [App\Http\Controllers\Admin\ClubMemberAdminController::class, 'servePaymentProof'])->name('subscriptions.payment-proof');
    Route::post('/subscriptions/{subscription}/refund', [App\Http\Controllers\Admin\ClubMemberAdminController::class, 'refundPayment'])->name('subscriptions.refund');
    Route::get('/subscriptions/{subscription}/refund-proof', [App\Http\Controllers\Admin\ClubMemberAdminController::class, 'serveRefundProof'])->name('subscriptions.refund-proof');

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
    Route::post('/financials/recurring', [App\Http\Controllers\Admin\ClubFinancialController::class, 'storeRecurringExpense'])->name('financials.recurring.store');
    Route::put('/financials/recurring/{recurringExpense}', [App\Http\Controllers\Admin\ClubFinancialController::class, 'updateRecurringExpense'])->name('financials.recurring.update');
    Route::delete('/financials/recurring/{recurringExpense}', [App\Http\Controllers\Admin\ClubFinancialController::class, 'destroyRecurringExpense'])->name('financials.recurring.destroy');
    Route::patch('/financials/recurring/{recurringExpense}/toggle', [App\Http\Controllers\Admin\ClubFinancialController::class, 'toggleRecurringExpense'])->name('financials.recurring.toggle');

    // Messages
    Route::get('/messages', [App\Http\Controllers\Admin\ClubMessageController::class, 'messages'])->name('messages');
    Route::get('/messages/thread/{user}', [App\Http\Controllers\Admin\ClubMessageController::class, 'conversation'])->name('messages.thread');
    Route::post('/messages/send', [App\Http\Controllers\Admin\ClubMessageController::class, 'sendMessage'])->name('messages.send')->middleware('throttle:admin-write');

    // Analytics
    Route::get('/analytics', [App\Http\Controllers\Admin\ClubAnalyticsController::class, 'analytics'])->name('analytics');

    // Notifications
    Route::get('/notifications', [App\Http\Controllers\Admin\ClubNotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications', [App\Http\Controllers\Admin\ClubNotificationController::class, 'store'])->name('notifications.store')->middleware('throttle:admin-write');
});

// Mark notification as read (global — not club-scoped)
Route::middleware(['auth', 'verified', 'two-factor'])->post('/notifications/mark-read', [App\Http\Controllers\Admin\ClubNotificationController::class, 'markRead'])->name('notifications.mark-read');

// Messenger — platform-wide direct messages (Facebook-style). Specific paths
// are registered before the {conversation} binding so they aren't swallowed.
Route::middleware(['auth', 'verified', 'two-factor'])->group(function () {
    Route::get('/messages', [App\Http\Controllers\MessengerController::class, 'index'])->name('messages.index');
    Route::get('/messages/conversations', [App\Http\Controllers\MessengerController::class, 'conversations'])->name('messages.conversations');
    Route::get('/messages/unread-count', [App\Http\Controllers\MessengerController::class, 'unreadCount'])->name('messages.unread');
    Route::get('/messages/search-users', [App\Http\Controllers\MessengerController::class, 'searchUsers'])->name('messages.search-users');
    Route::get('/messages/link-preview', [App\Http\Controllers\MessengerController::class, 'linkPreview'])->name('messages.link-preview')->middleware('throttle:60,1');
    Route::post('/messages/start/{user}', [App\Http\Controllers\MessengerController::class, 'start'])->name('messages.start')->middleware('throttle:member-write');
    Route::get('/messages/{conversation}', [App\Http\Controllers\MessengerController::class, 'show'])->name('messages.show');
    Route::get('/messages/{conversation}/thread', [App\Http\Controllers\MessengerController::class, 'thread'])->name('messages.thread');
    Route::post('/messages/{conversation}/send', [App\Http\Controllers\MessengerController::class, 'send'])->name('messages.send')->middleware('throttle:member-write');
    Route::patch('/messages/{conversation}/messages/{message}', [App\Http\Controllers\MessengerController::class, 'editMessage'])->name('messages.edit')->middleware('throttle:member-write');
    Route::delete('/messages/{conversation}/messages/{message}', [App\Http\Controllers\MessengerController::class, 'deleteMessage'])->name('messages.delete')->middleware('throttle:member-write');
    Route::post('/messages/{conversation}/messages/{message}/hide', [App\Http\Controllers\MessengerController::class, 'deleteMessageForMe'])->name('messages.hide')->middleware('throttle:member-write');
    Route::post('/messages/{conversation}/attachments', [App\Http\Controllers\MessengerController::class, 'uploadFile'])->name('messages.upload')->middleware('throttle:uploads');
    Route::get('/messages/{conversation}/attachments/{message}', [App\Http\Controllers\MessengerController::class, 'serveAttachment'])->name('messages.attachment');
    Route::post('/messages/{conversation}/read', [App\Http\Controllers\MessengerController::class, 'read'])->name('messages.read');
    Route::delete('/messages/{conversation}', [App\Http\Controllers\MessengerController::class, 'deleteConversation'])->name('messages.delete-conversation')->middleware('throttle:member-write');

    // ── Club room chat (group chat per club) ──
    Route::get('/club-chat', [App\Http\Controllers\ClubChatController::class, 'index'])->name('club-chat');
    Route::get('/club-chat/{club}', [App\Http\Controllers\ClubChatController::class, 'room'])->name('club-chat.room');
    Route::get('/club-chat/{club}/thread', [App\Http\Controllers\ClubChatController::class, 'thread'])->name('club-chat.thread');
    Route::get('/club-chat/{club}/members', [App\Http\Controllers\ClubChatController::class, 'members'])->name('club-chat.members');
    Route::post('/club-chat/{club}/mute', [App\Http\Controllers\ClubChatController::class, 'mute'])->name('club-chat.mute')->middleware('throttle:member-write');
    Route::post('/club-chat/{club}/leave', [App\Http\Controllers\ClubChatController::class, 'leave'])->name('club-chat.leave')->middleware('throttle:member-write');
    Route::post('/club-chat/{club}/kick/{user}', [App\Http\Controllers\ClubChatController::class, 'kick'])->name('club-chat.kick')->middleware('throttle:admin-write');
    Route::post('/club-chat/{club}/block/{user}', [App\Http\Controllers\ClubChatController::class, 'block'])->name('club-chat.block')->middleware('throttle:admin-write');
    Route::post('/club-chat/{club}/unblock/{user}', [App\Http\Controllers\ClubChatController::class, 'unblock'])->name('club-chat.unblock')->middleware('throttle:admin-write');
});

// Unified Member routes
Route::middleware(['auth', 'verified', 'two-factor'])->group(function () {
    // Redirect old /profile route to /member/{uuid}
    Route::get('/profile', function () {
        return redirect()->route('member.show', Auth::user()->uuid);
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
    Route::post('/members', [MemberController::class, 'store'])->name('members.store')->middleware('throttle:member-write');

    // Individual member routes
    Route::get('/member/{uuid}', [MemberController::class, 'show'])->name('member.show');
    Route::get('/member/{id}/edit', [MemberController::class, 'edit'])->name('member.edit');
    Route::put('/member/{id}', [MemberController::class, 'update'])->name('member.update')->middleware('throttle:member-write');
    Route::delete('/member/{id}/confirm-delete', [MemberController::class, 'confirmDelete'])->name('member.confirm-delete');
    Route::delete('/member/{id}', [MemberController::class, 'destroy'])->name('member.destroy')->middleware('throttle:member-write');
    Route::post('/member/{id}/upload-picture', [MemberController::class, 'uploadPicture'])->name('member.upload-picture')->middleware('throttle:uploads');
    Route::post('/member/{id}/upload-document', [MemberController::class, 'uploadDocument'])->name('member.upload-document')->middleware('throttle:uploads');
    Route::delete('/member/{id}/document', [MemberController::class, 'deleteDocument'])->name('member.delete-document')->middleware('throttle:member-write');
    Route::post('/member/{id}/reset-password', [MemberController::class, 'resetPassword'])->name('member.reset-password')->middleware('throttle:member-write');
    Route::post('/member/{id}/health', [MemberController::class, 'storeHealth'])->name('member.store-health')->middleware('throttle:member-write');
    Route::put('/member/{id}/health/{recordId}', [MemberController::class, 'updateHealth'])->name('member.update-health')->middleware('throttle:member-write');
    Route::post('/member/{id}/tournament', [MemberController::class, 'storeTournament'])->name('member.store-tournament')->middleware('throttle:member-write');
    Route::put('/member/goal/{goalId}', [MemberController::class, 'updateGoal'])->name('member.update-goal')->middleware('throttle:member-write');

    // Affiliation routes
    Route::post('/member/{id}/affiliations', [MemberController::class, 'storeAffiliation'])->name('member.store-affiliation')->middleware('throttle:member-write');
    Route::put('/member/{id}/affiliations/{affiliationId}', [MemberController::class, 'updateAffiliation'])->name('member.update-affiliation')->middleware('throttle:member-write');
    Route::delete('/member/{id}/affiliations/{affiliationId}', [MemberController::class, 'destroyAffiliation'])->name('member.destroy-affiliation')->middleware('throttle:member-write');
    Route::post('/member/{id}/affiliations/{affiliationId}/skills', [MemberController::class, 'storeAffiliationSkill'])->name('member.store-affiliation-skill')->middleware('throttle:member-write');
    Route::delete('/member/{id}/affiliations/{affiliationId}/skills/{skillId}', [MemberController::class, 'destroyAffiliationSkill'])->name('member.destroy-affiliation-skill')->middleware('throttle:member-write');
    Route::post('/member/{id}/affiliations/{affiliationId}/media', [MemberController::class, 'storeAffiliationMedia'])->name('member.store-affiliation-media')->middleware('throttle:uploads');
    Route::delete('/member/{id}/affiliations/{affiliationId}/media/{mediaId}', [MemberController::class, 'destroyAffiliationMedia'])->name('member.destroy-affiliation-media')->middleware('throttle:member-write');

    // Keep old family routes for backward compatibility (redirect to new routes)
    Route::get('/family/create', function () {
        return redirect()->route('members.create');
    })->name('family.create');
    Route::post('/family', [FamilyController::class, 'store'])->name('family.store')->middleware('throttle:member-write');
    Route::get('/family/{id}', function ($id) {
        $user = \App\Models\User::findOrFail($id);
        return redirect()->route('member.show', $user->uuid);
    })->name('family.show');
    Route::get('/family/{id}/edit', function ($id) {
        return redirect()->route('member.edit', $id);
    })->name('family.edit');
    Route::put('/family/{id}', [MemberController::class, 'update'])->name('family.update')->middleware('throttle:member-write');
    Route::post('/family/{id}/health', [MemberController::class, 'storeHealth'])->name('family.store-health')->middleware('throttle:member-write');
    Route::put('/family/{id}/health/{recordId}', [MemberController::class, 'updateHealth'])->name('family.update-health')->middleware('throttle:member-write');
    Route::put('/family/goal/{goalId}', [MemberController::class, 'updateGoal'])->name('family.update-goal')->middleware('throttle:member-write');
    Route::post('/family/{id}/tournament', [MemberController::class, 'storeTournament'])->name('family.store-tournament')->middleware('throttle:member-write');
    Route::post('/family/{id}/upload-picture', [MemberController::class, 'uploadPicture'])->name('family.upload-picture')->middleware('throttle:uploads');
    Route::delete('/family/{id}', [MemberController::class, 'destroy'])->name('family.destroy')->middleware('throttle:member-write');
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
    Route::put('/instructor/reviews/{reviewId}', [InstructorReviewController::class, 'update'])->name('instructor.reviews.update')->middleware('throttle:social');
});
