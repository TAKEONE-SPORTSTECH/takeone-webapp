<?php

/*
| The club's own admin workspace.
|
| Registered by App\Support\Modules\ModuleServiceProvider under
| admin/club/{club} with the `admin.club.` name prefix and the
| ['web','auth','verified','two-factor','tenant','throttle:admin-write'] stack —
| the same group these routes were declared in when they lived in
| routes/web.php, so every URL, route name and middleware is unchanged.
|
| ClubApiController's own routes are NOT here: they sit under the super-admin
| /admin prefix with a different stack, so they stay in routes/web.php even
| though the controller lives in this module. Same for the two global
| notification routes (mark-read / clear), which are not club-scoped.
*/

use App\Clubs\Controllers\ClubAchievementController;
use App\Clubs\Controllers\ClubActivityController;
use App\Clubs\Controllers\ClubAdminController;
use App\Clubs\Controllers\ClubAnalyticsController;
use App\Clubs\Controllers\ClubEventController;
use App\Clubs\Controllers\ClubFacilityController;
use App\Clubs\Controllers\ClubFinancialController;
use App\Clubs\Controllers\ClubGalleryController;
use App\Clubs\Controllers\ClubInstructorController;
use App\Clubs\Controllers\ClubMemberAdminController;
use App\Clubs\Controllers\ClubMessageController;
use App\Clubs\Controllers\ClubNotificationController;
use App\Clubs\Controllers\ClubPackageController;
use App\Clubs\Controllers\ClubRoleController;
use App\Clubs\Controllers\ClubTimelineController;
use Illuminate\Support\Facades\Route;

// Dashboard & club details
// Bare club-admin root → dashboard, so /admin/club/{club} never dead-ends on a 405
// (only PUT/DELETE live at `/`). Auth/tenant scope still enforced by the group middleware.
Route::get('/', fn ($club) => redirect()->route('admin.club.dashboard', $club))->name('home');
Route::get('/dashboard', [ClubAdminController::class, 'dashboard'])->name('dashboard');
Route::get('/details', [ClubAdminController::class, 'details'])->name('details');
Route::put('/', [ClubAdminController::class, 'update'])->name('update');
Route::put('/settings/whatsapp', [ClubAdminController::class, 'updateWhatsAppSettings'])->name('settings.whatsapp.update');
Route::post('/settings/whatsapp/test', [ClubAdminController::class, 'testWhatsAppConnection'])->name('settings.whatsapp.test');
Route::post('/settings/whatsapp/send-test', [ClubAdminController::class, 'sendTestWhatsAppMessage'])->name('settings.whatsapp.send-test');
Route::delete('/', [ClubAdminController::class, 'destroy'])->name('destroy');
Route::post('/social-links', [ClubAdminController::class, 'storeSocialLink'])->name('social-links.store');
Route::delete('/social-links/{link}', [ClubAdminController::class, 'destroySocialLink'])->name('social-links.destroy');
Route::post('/transfer-ownership', [ClubAdminController::class, 'transferOwnership'])->name('transfer-ownership')->middleware('throttle:admin-write');
Route::post('/create-owner', [ClubAdminController::class, 'createOwner'])->name('create-owner')->middleware('throttle:admin-write');

// Gallery
Route::get('/gallery', [ClubGalleryController::class, 'gallery'])->name('gallery');
Route::post('/gallery/upload', [ClubGalleryController::class, 'uploadGallery'])->name('gallery.upload')->middleware('throttle:uploads');
Route::post('/gallery/reorder', [ClubGalleryController::class, 'reorderGallery'])->name('gallery.reorder');
Route::post('/gallery/youtube', [ClubGalleryController::class, 'saveYoutubeUrl'])->name('gallery.youtube');
Route::delete('/gallery/{image}', [ClubGalleryController::class, 'destroyGalleryImage'])->name('gallery.destroy');

// Facilities
Route::get('/facilities', [ClubFacilityController::class, 'facilities'])->name('facilities');
Route::post('/facilities', [ClubFacilityController::class, 'storeFacility'])->name('facilities.store');
Route::get('/facilities/{facility}', [ClubFacilityController::class, 'getFacility'])->name('facilities.show');
Route::put('/facilities/{facility}', [ClubFacilityController::class, 'updateFacility'])->name('facilities.update');
Route::delete('/facilities/{facility}', [ClubFacilityController::class, 'destroyFacility'])->name('facilities.destroy');
Route::post('/facilities/{facility}/toggle', [ClubFacilityController::class, 'toggleFacility'])->name('facilities.toggle');
Route::post('/facilities/{facility}/upload-image', [ClubFacilityController::class, 'uploadFacilityImage'])->name('facilities.upload-image')->middleware('throttle:uploads');

// Instructors
Route::get('/instructors', [ClubInstructorController::class, 'instructors'])->name('instructors');
Route::post('/instructors/reorder', [ClubInstructorController::class, 'reorderInstructors'])->name('instructors.reorder')->middleware('throttle:admin-write');
Route::post('/instructors', [ClubInstructorController::class, 'storeInstructor'])->name('instructors.store');
Route::post('/instructors/{instructor}/upload-photo', [ClubInstructorController::class, 'uploadInstructorPhoto'])->name('instructors.upload-photo')->middleware('throttle:uploads');
Route::put('/instructors/{instructor}', [ClubInstructorController::class, 'updateInstructor'])->name('instructors.update');
Route::delete('/instructors/{instructor}', [ClubInstructorController::class, 'destroyInstructor'])->name('instructors.destroy');
Route::get('/instructors/{instructor}/termination-preview', [ClubInstructorController::class, 'terminationPreview'])->name('instructors.termination-preview');
Route::get('/instructors-prefill/{user}', [ClubInstructorController::class, 'instructorPrefill'])->name('instructors.prefill')->middleware('throttle:60,1');

// Activities
Route::get('/activities', [ClubActivityController::class, 'activities'])->name('activities');
Route::get('/activities/library', [ClubActivityController::class, 'activityLibrary'])->name('activities.library');
Route::post('/activities', [ClubActivityController::class, 'storeActivity'])->name('activities.store');
Route::put('/activities/{activity}', [ClubActivityController::class, 'updateActivity'])->name('activities.update');
Route::delete('/activities/{activity}', [ClubActivityController::class, 'destroyActivity'])->name('activities.destroy');

// Activity equipment catalog (gear required to practice the activity)
Route::get('/activities/{activity}/equipment', [ClubActivityController::class, 'equipment'])->name('activities.equipment');
Route::post('/activities/{activity}/equipment', [ClubActivityController::class, 'storeEquipment'])->name('activities.equipment.store')->middleware('throttle:admin-write');
Route::put('/activities/{activity}/equipment/{equipment}', [ClubActivityController::class, 'updateEquipment'])->name('activities.equipment.update')->middleware('throttle:admin-write');
Route::delete('/activities/{activity}/equipment/{equipment}', [ClubActivityController::class, 'destroyEquipment'])->name('activities.equipment.destroy')->middleware('throttle:admin-write');

// Events
Route::get('/events', [ClubEventController::class, 'events'])->name('events');
// Sparring — the club's own scoreboard for training. Two routes and no
// more: the launcher, and the one tap that opens a session. Everything
// afterwards is the session's console under /me/events, because a session
// IS an event (see App\Events\Sparring\Sparring).
Route::get('/sparring', [\App\Events\Sparring\SparringLauncherController::class, 'index'])->name('sparring');
Route::post('/sparring', [\App\Events\Sparring\SparringLauncherController::class, 'store'])->name('sparring.store')->middleware('throttle:admin-write');
Route::post('/events', [ClubEventController::class, 'storeEvent'])->name('events.store');
Route::put('/events/{event}', [ClubEventController::class, 'updateEvent'])->name('events.update');
Route::delete('/events/{event}', [ClubEventController::class, 'destroyEvent'])->name('events.destroy');
Route::patch('/events/{event}/archive', [ClubEventController::class, 'archiveEvent'])->name('events.archive');
Route::get('/events/{event}/participants', [ClubEventController::class, 'participants'])->name('events.participants');
Route::get('/events/{event}/participants/{registration}/proof', [ClubEventController::class, 'participantProof'])->name('events.participants.proof');
Route::post('/events/{event}/participants/{registration}/paid', [ClubEventController::class, 'markParticipantPaid'])->name('events.participants.paid')->middleware('throttle:admin-write');
Route::delete('/events/{event}/participants/{registration}', [ClubEventController::class, 'removeParticipant'])->name('events.participants.remove')->middleware('throttle:admin-write');

// Timeline
Route::get('/timeline', [ClubTimelineController::class, 'timeline'])->name('timeline');
Route::post('/timeline', [ClubTimelineController::class, 'storeTimelinePost'])->name('timeline.store');
Route::put('/timeline/{post}', [ClubTimelineController::class, 'updateTimelinePost'])->name('timeline.update');
Route::delete('/timeline/{post}', [ClubTimelineController::class, 'destroyTimelinePost'])->name('timeline.destroy');

// Achievements
Route::get('/achievements', [ClubAchievementController::class, 'achievements'])->name('achievements');
Route::post('/achievements', [ClubAchievementController::class, 'storeAchievement'])->name('achievements.store');
Route::put('/achievements/{achievement}', [ClubAchievementController::class, 'updateAchievement'])->name('achievements.update');
Route::delete('/achievements/{achievement}', [ClubAchievementController::class, 'destroyAchievement'])->name('achievements.destroy');
// Member self-claimed achievement verification queue (club attests claims naming this club).
Route::get('/achievements/verifications', [ClubAchievementController::class, 'verifications'])->name('achievements.verifications');
Route::post('/achievements/verifications/{type}/{uuid}/confirm', [ClubAchievementController::class, 'confirmVerification'])->whereIn('type', ['achievement', 'skill', 'affiliation', 'work'])->name('achievements.verifications.confirm')->middleware('throttle:admin-write');
Route::post('/achievements/verifications/{type}/{uuid}/reject', [ClubAchievementController::class, 'rejectVerification'])->whereIn('type', ['achievement', 'skill', 'affiliation', 'work'])->name('achievements.verifications.reject')->middleware('throttle:admin-write');

// Packages
Route::get('/packages', [ClubPackageController::class, 'packages'])->name('packages');
Route::post('/packages', [ClubPackageController::class, 'storePackage'])->name('packages.store');
Route::put('/packages/{package}', [ClubPackageController::class, 'updatePackage'])->name('packages.update');
Route::delete('/packages/{package}', [ClubPackageController::class, 'destroyPackage'])->name('packages.destroy');

// Members
Route::get('/members', [ClubMemberAdminController::class, 'members'])->name('members');
Route::post('/members', [ClubMemberAdminController::class, 'storeMember'])->name('members.store');
Route::post('/members/walk-in', [ClubMemberAdminController::class, 'walkInRegistration'])->name('members.walk-in')->middleware('throttle:walk-in');
Route::get('/members/search', [ClubMemberAdminController::class, 'searchUsers'])->name('members.search');
Route::post('/members/resolve-qr', [ClubMemberAdminController::class, 'resolveQr'])->name('members.resolve-qr');
Route::get('/members/cards', [ClubMemberAdminController::class, 'membersCards'])->name('members.cards');
Route::get('/members/{user}/popup', [ClubMemberAdminController::class, 'memberPopup'])->name('members.popup');
Route::get('/members/popup-demo', [ClubMemberAdminController::class, 'memberPopupDemo'])->name('members.popup-demo');
Route::get('/members/{user}/enroll-packages', [ClubMemberAdminController::class, 'enrollPackages'])->name('members.enroll-packages');
Route::post('/members/{user}/enroll', [ClubMemberAdminController::class, 'enrollMember'])->name('members.enroll');
Route::post('/members/enroll-batch', [ClubMemberAdminController::class, 'enrollBatch'])->name('members.enroll-batch')->middleware('throttle:admin-write');
Route::delete('/members/{user}/remove', [ClubMemberAdminController::class, 'removeMember'])->name('members.remove')->middleware('throttle:admin-write');
Route::post('/members/{user}/verify-email', [ClubMemberAdminController::class, 'verifyMemberEmail'])->name('members.verify-email')->middleware('throttle:admin-write');
Route::get('/members/import-template', [ClubMemberAdminController::class, 'importTemplate'])->name('members.import-template');
Route::post('/members/import', [ClubMemberAdminController::class, 'importMembers'])->name('members.import')->middleware('throttle:admin-write');
Route::post('/subscriptions/{subscription}/approve-payment', [ClubMemberAdminController::class, 'approvePayment'])->name('subscriptions.approve-payment');
Route::get('/subscriptions/{subscription}/payment-proof', [ClubMemberAdminController::class, 'servePaymentProof'])->name('subscriptions.payment-proof');
Route::post('/subscriptions/{subscription}/refund', [ClubMemberAdminController::class, 'refundPayment'])->name('subscriptions.refund');
Route::get('/subscriptions/{subscription}/refund-proof', [ClubMemberAdminController::class, 'serveRefundProof'])->name('subscriptions.refund-proof');

// Roles
Route::get('/roles', [ClubRoleController::class, 'roles'])->name('roles');
Route::post('/roles', [ClubRoleController::class, 'storeRole'])->name('roles.store');
Route::delete('/roles', [ClubRoleController::class, 'destroyRole'])->name('roles.destroy');
// Per-member access: read current effective permissions + save a standard role or custom permission set.
Route::get('/roles/member/{user}/permissions', [ClubRoleController::class, 'memberPermissions'])->name('roles.member.permissions');
Route::post('/roles/member/permissions', [ClubRoleController::class, 'storeMemberPermissions'])->name('roles.member.permissions.store')->middleware('throttle:admin-write');
Route::post('/roles/definitions', [ClubRoleController::class, 'createRole'])->name('roles.def.store');
Route::put('/roles/definitions/{role}', [ClubRoleController::class, 'updateRole'])->name('roles.def.update');
Route::delete('/roles/definitions/{role}', [ClubRoleController::class, 'deleteRole'])->name('roles.def.destroy');

// Financials
Route::get('/financials', [ClubFinancialController::class, 'financials'])->name('financials');
Route::post('/financials/income', [ClubFinancialController::class, 'storeIncome'])->name('financials.income');
Route::post('/financials/expense', [ClubFinancialController::class, 'storeExpense'])->name('financials.expense');
Route::put('/financials/{transaction}', [ClubFinancialController::class, 'updateTransaction'])->name('financials.update');
Route::delete('/financials/{transaction}', [ClubFinancialController::class, 'destroyTransaction'])->name('financials.destroy');
Route::post('/financials/recurring', [ClubFinancialController::class, 'storeRecurringExpense'])->name('financials.recurring.store');
Route::put('/financials/recurring/{recurringExpense}', [ClubFinancialController::class, 'updateRecurringExpense'])->name('financials.recurring.update');
Route::delete('/financials/recurring/{recurringExpense}', [ClubFinancialController::class, 'destroyRecurringExpense'])->name('financials.recurring.destroy');
Route::patch('/financials/recurring/{recurringExpense}/toggle', [ClubFinancialController::class, 'toggleRecurringExpense'])->name('financials.recurring.toggle');
Route::get('/financials/test-data', [ClubFinancialController::class, 'testData'])->name('financials.test-data');
Route::post('/financials/mode', [ClubFinancialController::class, 'switchMode'])->name('financials.mode')->middleware('throttle:admin-write');

// Messages
Route::get('/messages', [ClubMessageController::class, 'messages'])->name('messages');
Route::get('/messages/thread/{user}', [ClubMessageController::class, 'conversation'])->name('messages.thread');
Route::post('/messages/send', [ClubMessageController::class, 'sendMessage'])->name('messages.send')->middleware('throttle:admin-write');

// Analytics
Route::get('/analytics', [ClubAnalyticsController::class, 'analytics'])->name('analytics');

// Notifications
Route::get('/notifications', [ClubNotificationController::class, 'index'])->name('notifications');
Route::post('/notifications', [ClubNotificationController::class, 'store'])->name('notifications.store')->middleware('throttle:admin-write');
