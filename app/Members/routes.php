<?php

/*
| Member surfaces that are not under /me.
|
| Registered by App\Support\Modules\ModuleServiceProvider inside `web`, so each
| group below still declares the exact middleware it was declared with in
| routes/web.php. Nothing about a URL, a route name or a middleware stack
| changes by living here.
|
| Not here: the instructor-review routes that shared the "unified member" group.
| Those are a Trainers surface and stay in routes/web.php until that module
| moves.
*/

use App\Members\Controllers\ConnectionController;
use App\Members\Controllers\CropperUploadController;
use App\Members\Controllers\FamilyController;
use App\Members\Controllers\InvoiceController;
use App\Members\Controllers\MemberController;
use App\Members\Controllers\MessengerController;
use App\Members\Controllers\MobileAppController;
use App\Members\Controllers\PeopleController;
use App\Members\Controllers\UserPhotoController;
use App\Members\Controllers\UserPostController;
use App\Members\Controllers\WallController;
use Illuminate\Support\Facades\Route;

// Replaces takeone/cropper's own `POST /image-upload` (same URL and name), which
// had no auth or throttle and let the client choose the destination. Module
// routes load after every package has booted, so this one is what answers.
// See docs/uploads.md.
Route::middleware(['auth', 'verified', 'two-factor', 'throttle:uploads'])
    ->post('/image-upload', [CropperUploadController::class, 'store'])
    ->name('image.upload');

// Single-post permalink — unguessable token, members-only (auth-gated).
Route::middleware(['auth', 'verified', 'two-factor'])
    ->get('/p/{post:token}', [App\Members\Controllers\UserPostController::class, 'show'])
    ->name('posts.show');

// Member walls + social graph (follow / connect / block)
Route::middleware(['auth', 'verified', 'two-factor'])->prefix('u')->name('wall.')->group(function () {
    // Backward-compat: legacy /u/{id} links (old notifications/QRs) → member profile.
    Route::get('/{id}', function ($id) {
        $user = \App\Members\Models\User::find($id);
        abort_unless($user, 404);

        return redirect()->route('member.show', $user->uuid);
    })->whereNumber('id')->name('legacy');

    // The social "wall" is retired: /u/{slug} now redirects to the member profile.
    Route::get('/{user:slug}', [App\Members\Controllers\WallController::class, 'show'])->name('show');

    Route::post('/{user:slug}/follow', [App\Members\Controllers\ConnectionController::class, 'follow'])->name('follow')->middleware('throttle:member-write');
    Route::delete('/{user:slug}/follow', [App\Members\Controllers\ConnectionController::class, 'unfollow'])->name('unfollow')->middleware('throttle:member-write');
    Route::post('/{user:slug}/block', [App\Members\Controllers\ConnectionController::class, 'block'])->name('block')->middleware('throttle:member-write');
    Route::delete('/{user:slug}/block', [App\Members\Controllers\ConnectionController::class, 'unblock'])->name('unblock')->middleware('throttle:member-write');
});

// Messenger — platform-wide direct messages (Facebook-style). Specific paths
// are registered before the {conversation} binding so they aren't swallowed.
Route::middleware(['auth', 'verified', 'two-factor'])->group(function () {
    Route::get('/messages', [App\Members\Controllers\MessengerController::class, 'index'])->name('messages.index');
    Route::get('/messages/conversations', [App\Members\Controllers\MessengerController::class, 'conversations'])->name('messages.conversations');
    Route::get('/messages/unread-count', [App\Members\Controllers\MessengerController::class, 'unreadCount'])->name('messages.unread');
    Route::get('/messages/search-users', [App\Members\Controllers\MessengerController::class, 'searchUsers'])->name('messages.search-users');
    Route::get('/messages/link-preview', [App\Members\Controllers\MessengerController::class, 'linkPreview'])->name('messages.link-preview')->middleware('throttle:60,1');
    Route::post('/messages/start/{user}', [App\Members\Controllers\MessengerController::class, 'start'])->name('messages.start')->middleware('throttle:member-write');
    Route::get('/messages/{conversation}', [App\Members\Controllers\MessengerController::class, 'show'])->name('messages.show');
    Route::get('/messages/{conversation}/thread', [App\Members\Controllers\MessengerController::class, 'thread'])->name('messages.thread');
    Route::post('/messages/{conversation}/send', [App\Members\Controllers\MessengerController::class, 'send'])->name('messages.send')->middleware('throttle:member-write');
    Route::patch('/messages/{conversation}/messages/{message}', [App\Members\Controllers\MessengerController::class, 'editMessage'])->name('messages.edit')->middleware('throttle:member-write');
    Route::delete('/messages/{conversation}/messages/{message}', [App\Members\Controllers\MessengerController::class, 'deleteMessage'])->name('messages.delete')->middleware('throttle:member-write');
    Route::post('/messages/{conversation}/messages/{message}/hide', [App\Members\Controllers\MessengerController::class, 'deleteMessageForMe'])->name('messages.hide')->middleware('throttle:member-write');
    Route::post('/messages/{conversation}/attachments', [App\Members\Controllers\MessengerController::class, 'uploadFile'])->name('messages.upload')->middleware('throttle:uploads');
    Route::get('/messages/{conversation}/attachments/{message}', [App\Members\Controllers\MessengerController::class, 'serveAttachment'])->name('messages.attachment');
    Route::post('/messages/{conversation}/read', [App\Members\Controllers\MessengerController::class, 'read'])->name('messages.read');
    Route::delete('/messages/{conversation}', [App\Members\Controllers\MessengerController::class, 'deleteConversation'])->name('messages.delete-conversation')->middleware('throttle:member-write');
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
    Route::delete('/member/{id}/profile-picture', [MemberController::class, 'removeProfilePicture'])->name('member.remove-picture')->middleware('throttle:member-write');
    Route::put('/member/{id}/profile-picture/visibility', [MemberController::class, 'updateProfilePictureVisibility'])->name('member.picture-visibility')->middleware('throttle:member-write');

    // A profile holds several pictures; {photo} is a uuid and is always resolved
    // inside {id}'s own photos, so another profile's uuid resolves to nothing.
    Route::post('/member/{id}/photos', [App\Members\Controllers\UserPhotoController::class, 'store'])->name('member.photos.store')->middleware('throttle:uploads');
    Route::put('/member/{id}/photos/{photo}/avatar', [App\Members\Controllers\UserPhotoController::class, 'setAvatar'])->name('member.photos.avatar')->middleware('throttle:member-write');
    Route::delete('/member/{id}/photos/{photo}', [App\Members\Controllers\UserPhotoController::class, 'destroy'])->name('member.photos.destroy')->middleware('throttle:member-write');
    Route::post('/member/{id}/upload-document', [MemberController::class, 'uploadDocument'])->name('member.upload-document')->middleware('throttle:uploads');
    // Identity documents are on the PRIVATE disk, so reaching one goes through
    // the controller, which re-checks who is asking. There is deliberately no
    // /storage/ link for these any more.
    Route::get('/member/{id}/document', [MemberController::class, 'downloadDocument'])->name('member.download-document')->middleware('throttle:60,1');
    Route::delete('/member/{id}/document', [MemberController::class, 'deleteDocument'])->name('member.delete-document')->middleware('throttle:member-write');
    Route::post('/member/{id}/reset-password', [MemberController::class, 'resetPassword'])->name('member.reset-password')->middleware('throttle:member-write');
    Route::post('/member/{id}/regenerate-password', [MemberController::class, 'regeneratePassword'])->name('member.regenerate-password')->middleware('throttle:member-write');
    Route::post('/member/{id}/health', [MemberController::class, 'storeHealth'])->name('member.store-health')->middleware('throttle:member-write');
    Route::put('/member/{id}/health/{recordId}', [MemberController::class, 'updateHealth'])->name('member.update-health')->middleware('throttle:member-write');
    Route::post('/member/{id}/tournament', [MemberController::class, 'storeTournament'])->name('member.store-tournament')->middleware('throttle:member-write');
    Route::put('/member/{id}/tournament/{uuid}', [MemberController::class, 'updateTournament'])->name('member.tournament.update')->middleware('throttle:member-write');
    Route::delete('/member/{id}/tournament/{uuid}', [MemberController::class, 'destroyTournament'])->name('member.tournament.destroy')->middleware('throttle:member-write');
    Route::post('/member/{id}/tournament/{uuid}/request-verification', [MemberController::class, 'requestTournamentVerification'])->name('member.tournament.request-verification')->middleware('throttle:member-write');
    Route::get('/member/{id}/tournament/{uuid}/evidence', [MemberController::class, 'tournamentEvidence'])->name('member.tournament.evidence');
    Route::post('/member/{id}/goal', [MemberController::class, 'storeGoal'])->name('member.store-goal')->middleware('throttle:member-write');
    Route::post('/member/{id}/attendance', [MemberController::class, 'storeAttendance'])->name('member.store-attendance')->middleware('throttle:member-write');
    Route::post('/member/{id}/event-log', [MemberController::class, 'storeMemberEvent'])->name('member.store-event')->middleware('throttle:member-write');
    Route::put('/member/goal/{goalId}', [MemberController::class, 'updateGoal'])->name('member.update-goal')->middleware('throttle:member-write');

    // Certifications (member-owned, self-managed)
    Route::post('/member/{id}/certification', [MemberController::class, 'storeCertification'])->name('member.store-certification')->middleware('throttle:member-write');
    Route::put('/member/certification/{certificationId}', [MemberController::class, 'updateCertification'])->name('member.update-certification')->whereNumber('certificationId')->middleware('throttle:member-write');
    Route::delete('/member/certification/{certificationId}', [MemberController::class, 'destroyCertification'])->name('member.destroy-certification')->whereNumber('certificationId')->middleware('throttle:member-write');

    // Work history (member-owned, self-managed)
    Route::post('/member/{id}/work-history', [MemberController::class, 'storeWorkHistory'])->name('member.store-work')->middleware('throttle:member-write');
    Route::put('/member/work-history/{workId}', [MemberController::class, 'updateWorkHistory'])->name('member.update-work')->whereNumber('workId')->middleware('throttle:member-write');
    Route::delete('/member/work-history/{workId}', [MemberController::class, 'destroyWorkHistory'])->name('member.destroy-work')->whereNumber('workId')->middleware('throttle:member-write');

    // Affiliation routes
    Route::post('/member/{id}/affiliations', [MemberController::class, 'storeAffiliation'])->name('member.store-affiliation')->middleware('throttle:member-write');
    Route::put('/member/{id}/affiliations/{affiliationId}', [MemberController::class, 'updateAffiliation'])->name('member.update-affiliation')->middleware('throttle:member-write');
    Route::delete('/member/{id}/affiliations/{affiliationId}', [MemberController::class, 'destroyAffiliation'])->name('member.destroy-affiliation')->middleware('throttle:member-write');
    Route::get('/member/{id}/affiliations/{affiliationId}/activities', [MemberController::class, 'affiliationActivities'])->name('member.affiliation-activities')->middleware('throttle:60,1');
    Route::get('/member/{id}/instructor-search', [MemberController::class, 'instructorSearch'])->name('member.instructor-search')->middleware('throttle:60,1');
    Route::post('/member/{id}/affiliations/{affiliationId}/instructors', [MemberController::class, 'storeAffiliationInstructor'])->name('member.store-affiliation-instructor')->middleware('throttle:member-write');
    Route::delete('/member/{id}/affiliations/{affiliationId}/instructors', [MemberController::class, 'destroyAffiliationInstructor'])->name('member.destroy-affiliation-instructor')->middleware('throttle:member-write');
    Route::post('/member/{id}/affiliations/{affiliationId}/skills', [MemberController::class, 'storeAffiliationSkill'])->name('member.store-affiliation-skill')->middleware('throttle:member-write');
    Route::post('/member/{id}/affiliations/{affiliationId}/skills/{uuid}/request-verification', [MemberController::class, 'requestSkillVerification'])->name('member.skill.request-verification')->middleware('throttle:member-write');
    Route::delete('/member/{id}/affiliations/{affiliationId}/skills/{skillId}', [MemberController::class, 'destroyAffiliationSkill'])->name('member.destroy-affiliation-skill')->middleware('throttle:member-write');
    Route::post('/member/{id}/affiliations/{affiliationId}/media/upload-image', [MemberController::class, 'uploadAffiliationMediaImage'])->name('member.affiliation-media.upload-image')->middleware('throttle:uploads');
    Route::post('/member/{id}/affiliations/{affiliationId}/media', [MemberController::class, 'storeAffiliationMedia'])->name('member.store-affiliation-media')->middleware('throttle:uploads');
    Route::delete('/member/{id}/affiliations/{affiliationId}/media/{mediaId}', [MemberController::class, 'destroyAffiliationMedia'])->name('member.destroy-affiliation-media')->middleware('throttle:member-write');
    Route::post('/member/{id}/affiliations/{uuid}/request-verification', [MemberController::class, 'requestAffiliationVerification'])->name('member.affiliation.request-verification')->middleware('throttle:member-write');
    Route::post('/member/{id}/work-history/{uuid}/request-verification', [MemberController::class, 'requestWorkVerification'])->name('member.work.request-verification')->middleware('throttle:member-write');

    // Keep old family routes for backward compatibility (redirect to new routes)
    Route::get('/family/create', function () {
        return redirect()->route('members.create');
    })->name('family.create');
    Route::post('/family', [FamilyController::class, 'store'])->name('family.store')->middleware('throttle:member-write');
    Route::post('/family/lookup', [FamilyController::class, 'lookup'])->name('family.lookup')->middleware('throttle:member-write');
    Route::get('/family/search-existing', [FamilyController::class, 'searchExisting'])->name('family.search-existing');
    Route::post('/family/link-existing', [FamilyController::class, 'linkExisting'])->name('family.link-existing')->middleware('throttle:member-write');
    Route::get('/family/{id}', function ($id) {
        $user = \App\Members\Models\User::findOrFail($id);

        return redirect()->route('member.show', $user->uuid);
    })->name('family.show');
    Route::get('/family/{id}/edit', function ($id) {
        return redirect()->route('member.edit', $id);
    })->name('family.edit');
    Route::put('/family/{id}', [MemberController::class, 'update'])->name('family.update')->middleware('throttle:member-write');
    Route::post('/family/{id}/health', [MemberController::class, 'storeHealth'])->name('family.store-health')->middleware('throttle:member-write');
    Route::put('/family/{id}/health/{recordId}', [MemberController::class, 'updateHealth'])->name('family.update-health')->middleware('throttle:member-write');
    Route::put('/family/goal/{goalId}', [MemberController::class, 'updateGoal'])->name('family.update-goal')->middleware('throttle:member-write');
    Route::post('/family/{id}/goal', [MemberController::class, 'storeGoal'])->name('family.store-goal')->middleware('throttle:member-write');
    Route::post('/family/{id}/attendance', [MemberController::class, 'storeAttendance'])->name('family.store-attendance')->middleware('throttle:member-write');
    Route::post('/family/{id}/event-log', [MemberController::class, 'storeMemberEvent'])->name('family.store-event')->middleware('throttle:member-write');
    Route::post('/family/{id}/certification', [MemberController::class, 'storeCertification'])->name('family.store-certification')->middleware('throttle:member-write');
    Route::post('/family/{id}/work-history', [MemberController::class, 'storeWorkHistory'])->name('family.store-work')->middleware('throttle:member-write');
    Route::post('/family/{id}/tournament', [MemberController::class, 'storeTournament'])->name('family.store-tournament')->middleware('throttle:member-write');
    Route::put('/family/{id}/tournament/{uuid}', [MemberController::class, 'updateTournament'])->name('family.tournament.update')->middleware('throttle:member-write');
    Route::delete('/family/{id}/tournament/{uuid}', [MemberController::class, 'destroyTournament'])->name('family.tournament.destroy')->middleware('throttle:member-write');
    Route::post('/family/{id}/tournament/{uuid}/request-verification', [MemberController::class, 'requestTournamentVerification'])->name('family.tournament.request-verification')->middleware('throttle:member-write');

    // Peer/coach attestation for a member's self-claimed record (achievement | skill), bound by uuid.
    Route::post('/attestations/{type}/{uuid}/vouch', [App\Members\Controllers\AchievementVouchController::class, 'vouch'])->whereIn('type', ['achievement', 'skill', 'affiliation', 'work'])->name('attestations.vouch')->middleware('throttle:member-write');
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
});

/*
 * The safe public profile, open to anyone.
 *
 * Deliberately narrower for a guest than for a member: a signed-in viewer may
 * see any profile they are not blocked by, but an ANONYMOUS visitor only sees a
 * member who is discoverable and is not a minor. Discoverability is the member's
 * own opt-in to being found, and a minor's name, photo and club do not go to the
 * open internet on the strength of a shared link. Anything else 404s — the same
 * answer a non-existent uuid gets, so the difference discloses nothing.
 */
Route::get('/people/{uuid}', [App\Members\Controllers\PeopleController::class, 'show'])
    ->name('people.show')->middleware('throttle:60,1');

/*
 * The same profile, INSIDE a sealed public event page.
 *
 * The event surface at /e/{uuid} may never hand a reader to the platform (see
 * App\Events\Middleware\SealEventPage), so tapping a name on an entry list or an
 * officiating sheet has to open that person here rather than at /people/{uuid}.
 * Same controller, same authorization, same answer for everything else.
 *
 * Declared in THIS module because it points at this module's own controller —
 * the Events module cannot name it without reaching into private internals.
 *
 * ⚠️ It goes through `showInEvent`, not `show`. Controller arguments are handed
 * over in ROUTE ORDER (`array_values` in Laravel's dispatcher), so on a URI
 * that carries the event first, `show(Request, string $uuid)` received the
 * EVENT's uuid and every name on the list 404'd. The wrapper types the event, so
 * the scalar that follows is the person's.
 */
// Moved from /e/{uuid}/admin/person/{uuid} on 2026-09-27, when /e/ went: the
// event's pages all live under /me/events/{uuid} now, dressed in the event's
// skin by BrandEventPage rather than the retired seal. Same name, controller
// and authorization (PeopleController::show asks canViewPublicProfile()).
Route::get('/me/events/{event:uuid}/person/{uuid}', [App\Members\Controllers\PeopleController::class, 'showInEvent'])
    ->name('sealed.people.show')
    ->middleware(['web', App\Events\Middleware\BrandEventPage::class, 'throttle:60,1'])
    ->whereUuid('event');

// Public version manifest polled by the installed Android app to detect updates.
// Unauthenticated by design: the installed app asks before anybody signs in.
Route::get('/app/manifest.json', [MobileAppController::class, 'manifest'])->name('app.manifest');

/*
| Switch the UI language, for somebody who has no account.
|
| `me.locale.update` already does this but sits behind auth + verified +
| two-factor, and the first place a visitor is offered a language is the PUBLIC
| event cover — before any of that. Same controller, because the behaviour must
| not fork: it validates against config/locales.php, writes the session, and
| updates the user's saved locale only when there IS a user.
|
| PUT, not GET: it changes state, so it keeps CSRF and stays off a link
| (CLAUDE.md → CSRF / unsafe state changes). Throttled because it is an
| unauthenticated write.
|
| 120/min, raised from 30 on 2026-09-05. 30 was reached by ordinary use — a
| bilingual reader comparing two versions of an event page flips back and forth,
| and the toggle is on the cover of every public page, so the minute is shared
| across all of them. Hitting it hands a visitor a raw 429 in place of the page
| they were reading, which is a far worse outcome than the thing being guarded
| against: this writes one key to a session and, for a signed-in member, one
| column on their own row. It is a preference, not an expensive operation.
| Still capped, because it is an unauthenticated write and every one of those
| has a ceiling here.
*/
Route::put('/locale', [App\Members\Controllers\LocaleController::class, 'update'])
    ->name('locale.set')->middleware('throttle:120,1');


/*
|--------------------------------------------------------------------------
| Sign-in, two-factor, registration, password reset, e-mail verification
|--------------------------------------------------------------------------
| Moved verbatim from routes/web.php — every URI, name and middleware is the
| one it had there.
*/

// Two-Factor Authentication challenge (no auth required — user is between login and session)
Route::get('/two-factor-challenge', [\App\Members\Controllers\TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
Route::post('/two-factor-challenge', [\App\Members\Controllers\TwoFactorController::class, 'verifyChallenge'])->name('two-factor.verify')->middleware('throttle:6,1');

// Security Settings (requires full auth + 2FA if enabled)
Route::middleware(['auth', 'verified', 'two-factor'])->group(function () {
    Route::get('/security', [\App\Members\Controllers\TwoFactorController::class, 'show'])->name('security.show');
    Route::post('/security/two-factor/setup', [\App\Members\Controllers\TwoFactorController::class, 'setup'])->name('security.2fa.setup');
    Route::get('/security/two-factor/setup', [\App\Members\Controllers\TwoFactorController::class, 'setup'])->name('security.2fa.setup.get');
    Route::post('/security/two-factor/confirm', [\App\Members\Controllers\TwoFactorController::class, 'confirm'])->name('security.2fa.confirm');
    Route::post('/security/two-factor/disable', [\App\Members\Controllers\TwoFactorController::class, 'disable'])->name('security.2fa.disable');
    Route::post('/security/two-factor/recovery-codes', [\App\Members\Controllers\TwoFactorController::class, 'regenerateRecoveryCodes'])->name('security.2fa.recovery-codes');
    Route::post('/security/password', [\App\Members\Controllers\TwoFactorController::class, 'changePassword'])->name('security.password.change')->middleware('throttle:6,1');
});

Route::get('/login', [\App\Members\Auth\AuthenticatedSessionController::class, 'create'])->name('login')->middleware('no-store');
Route::post('/login', [\App\Members\Auth\AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
/*
| "Who is signing in?" — the second half of a telephone sign-in.
|
| A telephone belongs to a household. When one number and one password answer
| for more than one person (a parent and a child, the ordinary case at a
| junior competition), the password is already proven and only the identity is
| open. Guarded by a SERVER-held shortlist in the session, not by anything the
| caller sends, and throttled like the sign-in it continues.
*/
Route::get('/login/choose', [\App\Members\Auth\AuthenticatedSessionController::class, 'choose'])
    ->name('login.choose')->middleware(['no-store', 'throttle:login']);
Route::post('/login/choose', [\App\Members\Auth\AuthenticatedSessionController::class, 'chose'])
    ->name('login.chose')->middleware('throttle:login');

Route::post('/logout', [\App\Members\Auth\AuthenticatedSessionController::class, 'destroy'])->name('logout');

// Passwordless "magic link" login — request a one-time signed link by email,
// then click it to sign in. The verify route is signature-protected.
Route::post('/login/link', [\App\Members\Auth\MagicLinkController::class, 'send'])
    ->name('login.magic')->middleware('throttle:login');
Route::get('/login/link/{user}', [\App\Members\Auth\MagicLinkController::class, 'login'])
    ->name('login.magic.verify')->middleware('signed', 'throttle:6,1');
// The public form renderer moved to app/Forms/routes.php with its module.

Route::get('/register', [\App\Members\Auth\RegisteredUserController::class, 'create'])->name('register')->middleware('no-store');
Route::post('/register', [\App\Members\Auth\RegisteredUserController::class, 'store'])->middleware('throttle:register');
Route::get('/register/wizard/packages', [\App\Members\Auth\WizardRegistrationController::class, 'packages'])->name('register.wizard.packages')->middleware('throttle:60,1');
Route::post('/register/wizard/upload-temp', [\App\Members\Auth\WizardRegistrationController::class, 'uploadTemp'])->name('register.wizard.upload')->middleware('throttle:uploads');
Route::post('/register/wizard/lookup', [\App\Members\Auth\WizardRegistrationController::class, 'lookup'])->name('register.wizard.lookup')->middleware('throttle:10,1');
Route::post('/register/wizard/verify-otp', [\App\Members\Auth\WizardRegistrationController::class, 'verifyOtp'])->name('register.wizard.verify')->middleware('throttle:15,1');
Route::post('/register/wizard/submit', [\App\Members\Auth\WizardRegistrationController::class, 'submit'])->name('register.wizard.submit')->middleware('throttle:register');
// Dedicated club-registration URL (distinct from platform /register). The 2-3
// letter country constraint keeps it from clashing with /register/wizard/*.
Route::get('/register/{country}/{slug}', [\App\Members\Auth\RegisteredUserController::class, 'createForClub'])
    ->name('register.club')->where('country', '[a-z]{2,3}')->middleware('no-store');

// Password reset routes
Route::get('/forgot-password', [\App\Members\Auth\PasswordResetLinkController::class, 'create'])->name('password.request')->middleware('no-store');
Route::post('/forgot-password', [\App\Members\Auth\PasswordResetLinkController::class, 'store'])->name('password.email')->middleware('throttle:password-reset');
Route::get('/reset-password/{token}', [\App\Members\Auth\NewPasswordController::class, 'create'])->name('password.reset')->middleware('no-store');
Route::post('/reset-password', [\App\Members\Auth\NewPasswordController::class, 'store'])->name('password.update')->middleware('throttle:password-reset');

// Email verification routes
Route::get('/email/verify', function (\Illuminate\Http\Request $request) {
    if ($request->user()->hasVerifiedEmail()) {
        $intended = session()->pull('url.intended', \App\Support\Landing::url($request));
        // Just confirmed by the emailed link: it said where to go (TakeNote's sign-up sends people back
        // to TakeNote), and that must win over the default landing — on another device there is no
        // url.intended. Only ever an address on this site.
        $fromLink = session('verified') ? (string) session('verified_link_intended') : '';
        if ($fromLink !== '' && str_starts_with($fromLink, rtrim(url('/'), '/').'/')) {
            $intended = $fromLink;
        }
        session()->forget('club.context');

        return redirect($intended);
    }

    return view('members::auth.verify-email');
})->middleware('auth')->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', function (\Illuminate\Http\Request $request, $id, $hash) {
    $user = \App\Members\Models\User::findOrFail($id);

    if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
        abort(403, 'Invalid verification link.');
    }

    $intended = $request->query('intended') ?: session()->pull('url.intended', \App\Support\Landing::url($request));
    session()->forget('club.context');

    // Log the user in and set the 2FA session flag so all middleware passes.
    if (! auth()->check()) {
        \Illuminate\Support\Facades\Auth::login($user);
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
        ->with('verified_intended', $intended)
        ->with('verified_link_intended', (string) $request->query('intended')); // only what the signed link itself named
})->middleware(['signed'])->name('verification.verify');

Route::post('/email/verification-notification', function (\Illuminate\Http\Request $request) {
    $request->user()->sendEmailVerificationNotification();

    return back()->with('resent', true);
})->middleware(['auth', 'throttle:verification'])->name('verification.send');

// Public resend — for users who are not yet logged in (e.g. walk-in registrations).
Route::post('/email/resend-verification', function (\Illuminate\Http\Request $request) {
    $request->validate(['email' => 'required|email']);
    $user = \App\Members\Models\User::where('email', $request->email)->first();
    if ($user && ! $user->hasVerifiedEmail()) {
        $user->sendEmailVerificationNotification();
    }

    // Always return the same message to avoid email enumeration.
    return redirect()->route('login')
        ->with('info', 'If that email exists and is unverified, a new verification link has been sent.');
})->middleware('throttle:verification')->name('verification.resend.public');
