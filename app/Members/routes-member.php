<?php

/*
| The member acting on their own behalf, under /me.
|
| Registered by App\Support\Modules\ModuleServiceProvider with the `me` prefix,
| the `me.` name prefix and the ['web','auth','verified','two-factor'] stack —
| the same group these routes were declared in when they lived in
| routes/web.php, so every URL, route name and middleware is unchanged.
|
| What is NOT here, deliberately: /me/events, /me/challenge and the bout-video
| routes. Those are Events, Challenges and Media surfaces that happen to sit
| under the same prefix; they stay in routes/web.php until their own modules
| move, and then they go to those modules — not to this one.
*/

use App\Members\Controllers\FamilyTreeController;
use App\Members\Controllers\LocaleController;
use App\Members\Controllers\MobileAppController;
use App\Members\Controllers\PeopleController;
use App\Members\Controllers\PersonalMobileController;
use App\Members\Controllers\UserPostController;
use Illuminate\Support\Facades\Route;

Route::get('/', [App\Members\Controllers\PersonalMobileController::class, 'home'])->name('home');


// "Get the App / Update" hub (renders in the mobile shell).
Route::get('/app', [App\Members\Controllers\MobileAppController::class, 'page'])->name('app');

// Member-authored personal feed posts (text + images, likes, comments).
Route::post('/posts', [App\Members\Controllers\UserPostController::class, 'store'])->name('posts.store')->middleware('throttle:uploads');
Route::put('/posts/{post}', [App\Members\Controllers\UserPostController::class, 'update'])->name('posts.update')->middleware('throttle:member-write');
Route::delete('/posts/{post}', [App\Members\Controllers\UserPostController::class, 'destroy'])->name('posts.destroy')->middleware('throttle:member-write');
Route::post('/posts/{post}/like', [App\Members\Controllers\UserPostController::class, 'like'])->name('posts.like')->middleware('throttle:member-write');
Route::post('/posts/{post}/vote', [App\Members\Controllers\UserPostController::class, 'vote'])->name('posts.vote')->middleware('throttle:member-write');
Route::post('/posts/{post}/view', [App\Members\Controllers\UserPostController::class, 'view'])->name('posts.view')->middleware('throttle:member-write');
Route::get('/posts/{post}/viewers', [App\Members\Controllers\UserPostController::class, 'viewers'])->name('posts.viewers');
Route::get('/posts/{post}/likers', [App\Members\Controllers\UserPostController::class, 'likers'])->name('posts.likers');
Route::post('/posts/{post}/comment', [App\Members\Controllers\UserPostController::class, 'comment'])->name('posts.comment')->middleware('throttle:member-write');
// Super-admin moderation: hide a post from public view (reversible) or unhide it.
Route::post('/posts/{post}/hide', [App\Members\Controllers\UserPostController::class, 'hide'])->name('posts.hide')->middleware('throttle:member-write');
Route::post('/posts/{post}/unhide', [App\Members\Controllers\UserPostController::class, 'unhide'])->name('posts.unhide')->middleware('throttle:member-write');
Route::get('/schedule', [App\Members\Controllers\PersonalMobileController::class, 'schedule'])->name('schedule');
Route::get('/schedule/data', [App\Members\Controllers\PersonalMobileController::class, 'scheduleData'])->name('schedule.data');
Route::post('/schedule', [App\Members\Controllers\PersonalMobileController::class, 'store'])->name('schedule.store')->middleware('throttle:member-write');
Route::get('/schedule/synced/{token}', [App\Members\Controllers\PersonalMobileController::class, 'scheduleSyncedShow'])->name('schedule.synced');
Route::put('/schedule/synced/{token}', [App\Members\Controllers\PersonalMobileController::class, 'scheduleSyncedUpdate'])->name('schedule.synced.update')->middleware('throttle:admin-write');
// Substitute trainer for a single dated occurrence of a club class.
Route::get('/schedule/synced/{token}/substitutes', [App\Members\Controllers\PersonalMobileController::class, 'substituteSearch'])->name('schedule.substitute.search');
Route::post('/schedule/synced/{token}/substitute', [App\Members\Controllers\PersonalMobileController::class, 'substituteAssign'])->name('schedule.substitute.assign')->middleware('throttle:admin-write');
Route::delete('/schedule/synced/{token}/substitute', [App\Members\Controllers\PersonalMobileController::class, 'substituteRemove'])->name('schedule.substitute.remove')->middleware('throttle:admin-write');
// Mark / unmark attendance for one dated occurrence of a club class.
Route::post('/schedule/synced/{token}/attendance', [App\Members\Controllers\PersonalMobileController::class, 'attendanceToggle'])->name('schedule.attendance.toggle')->middleware('throttle:admin-write');
// Cancel / restore a club class for a date or range (credits enrolled members).
// Trainee engagement: emoji reaction + rate the trainer.
Route::post('/schedule/synced/{token}/react', [App\Members\Controllers\PersonalMobileController::class, 'reactClass'])->name('schedule.react')->middleware('throttle:member-write');
Route::post('/schedule/synced/{token}/rate', [App\Members\Controllers\PersonalMobileController::class, 'rateClassTrainer'])->name('schedule.rate')->middleware('throttle:social');
Route::post('/schedule/synced/{token}/rate-class', [App\Members\Controllers\PersonalMobileController::class, 'rateClass'])->name('schedule.rate.class')->middleware('throttle:social');
Route::delete('/schedule/synced/{token}/rate-class', [App\Members\Controllers\PersonalMobileController::class, 'rateClassDestroy'])->name('schedule.rate.class.destroy')->middleware('throttle:social');
Route::post('/schedule/synced/{token}/cancel', [App\Members\Controllers\PersonalMobileController::class, 'classCancel'])->name('schedule.cancel')->middleware('throttle:admin-write');
Route::delete('/schedule/synced/{token}/cancel', [App\Members\Controllers\PersonalMobileController::class, 'classUncancel'])->name('schedule.uncancel')->middleware('throttle:admin-write');
// Clear a one-off training-program variation for a single dated occurrence (reverts to the recurring plan).
Route::delete('/schedule/synced/{token}/program', [App\Members\Controllers\PersonalMobileController::class, 'programReset'])->name('schedule.program.reset')->middleware('throttle:admin-write');
Route::get('/schedule/{session}', [App\Members\Controllers\PersonalMobileController::class, 'scheduleShow'])->name('schedule.show')->whereNumber('session');
Route::put('/schedule/{session}', [App\Members\Controllers\PersonalMobileController::class, 'update'])->name('schedule.update')->whereNumber('session')->middleware('throttle:member-write');
Route::delete('/schedule/{session}', [App\Members\Controllers\PersonalMobileController::class, 'destroy'])->name('schedule.destroy')->whereNumber('session')->middleware('throttle:member-write');
Route::get('/affiliations', [App\Members\Controllers\PersonalMobileController::class, 'affiliations'])->name('affiliations');
// Family tree (kinship graph) — page + JSON window + writes
Route::get('/family', [App\Members\Controllers\FamilyTreeController::class, 'index'])->name('family');
Route::get('/family/data', [App\Members\Controllers\FamilyTreeController::class, 'data'])->name('family.data');
Route::post('/family/relative', [App\Members\Controllers\FamilyTreeController::class, 'addRelative'])->name('family.relative')->middleware('throttle:member-write');
Route::post('/family/respond', [App\Members\Controllers\FamilyTreeController::class, 'respond'])->name('family.respond')->middleware('throttle:member-write');
Route::delete('/family/relative', [App\Members\Controllers\FamilyTreeController::class, 'removeRelative'])->name('family.relative.remove')->middleware('throttle:member-write');
Route::get('/profile', [App\Members\Controllers\PersonalMobileController::class, 'profile'])->name('profile');
Route::get('/packages', [App\Members\Controllers\PersonalMobileController::class, 'packages'])->name('packages');
Route::get('/progress', [App\Members\Controllers\PersonalMobileController::class, 'progress'])->name('progress');
Route::get('/payments', [App\Members\Controllers\PersonalMobileController::class, 'payments'])->name('payments');

// The member's own footage — bouts, duels, clips they shot. Self-only:
// there is no route to anybody else's, by design.
Route::get('/videos', [App\Members\Controllers\PersonalMobileController::class, 'videos'])->name('videos');
Route::get('/videos/data', [App\Members\Controllers\PersonalMobileController::class, 'videosData'])
    ->name('videos.data')->middleware('throttle:media-read');
// Settle an outstanding subscription bill by uploading proof of payment.
Route::post('/payments/{subscription}/settle', [App\Members\Controllers\PersonalMobileController::class, 'settlePayment'])->name('payments.settle')->middleware('throttle:uploads');

// The club shop, seen from the member side. Shop territory living on a
// Members controller: it follows PersonalMobileController::market() into
// App\Shop when that method moves, not before.
Route::get('/market', [App\Members\Controllers\PersonalMobileController::class, 'market'])->name('market');
Route::get('/market/{product}', [App\Members\Controllers\PersonalMobileController::class, 'marketShow'])->name('market.show')->whereNumber('product');

Route::get('/settings', [App\Members\Controllers\PersonalMobileController::class, 'settings'])->name('settings');

// People discovery — search directory (public profile lives at /people/{uuid}).
Route::get('/people', [App\Members\Controllers\PeopleController::class, 'index'])->name('people');
Route::get('/people/search', [App\Members\Controllers\PeopleController::class, 'search'])->name('people.search');
Route::put('/discoverable', [App\Members\Controllers\PersonalMobileController::class, 'updateDiscoverable'])->name('discoverable.update')->middleware('throttle:member-write');
// Mark an app section/feed-tab as seen (clears its unseen red-dot).
Route::post('/seen', [App\Members\Controllers\PersonalMobileController::class, 'markSectionSeen'])->name('seen');

// Switch the active UI language (persists to user + session; client reloads).
Route::put('/locale', [App\Members\Controllers\LocaleController::class, 'update'])->name('locale.update');
