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

// Market item creators — PREVIEW of the reusable product/category form
// components (form UI only, no DB yet). Drop the components into club admin /
// seller area / personal mobile when wiring the real backend.
Route::middleware(['auth', 'verified'])->get('/market/forms-preview', function () {
    return view('market.forms-preview');
})->name('market.forms-preview');

// Personal (member) mobile experience — shared mobile shell
Route::middleware(['auth', 'verified', 'two-factor'])->prefix('me')->name('me.')->group(function () {
    Route::get('/',          [App\Http\Controllers\PersonalMobileController::class, 'home'])->name('home');

    // Member-authored personal feed posts (text + images, likes, comments).
    Route::post('/posts',                   [App\Http\Controllers\UserPostController::class, 'store'])->name('posts.store')->middleware('throttle:uploads');
    Route::put('/posts/{post}',             [App\Http\Controllers\UserPostController::class, 'update'])->name('posts.update')->middleware('throttle:member-write');
    Route::delete('/posts/{post}',          [App\Http\Controllers\UserPostController::class, 'destroy'])->name('posts.destroy')->middleware('throttle:member-write');
    Route::post('/posts/{post}/like',       [App\Http\Controllers\UserPostController::class, 'like'])->name('posts.like')->middleware('throttle:member-write');
    Route::post('/posts/{post}/vote',       [App\Http\Controllers\UserPostController::class, 'vote'])->name('posts.vote')->middleware('throttle:member-write');
    Route::post('/posts/{post}/view',       [App\Http\Controllers\UserPostController::class, 'view'])->name('posts.view')->middleware('throttle:member-write');
    Route::get('/posts/{post}/viewers',     [App\Http\Controllers\UserPostController::class, 'viewers'])->name('posts.viewers');
    Route::get('/posts/{post}/likers',      [App\Http\Controllers\UserPostController::class, 'likers'])->name('posts.likers');
    Route::post('/posts/{post}/comment',    [App\Http\Controllers\UserPostController::class, 'comment'])->name('posts.comment')->middleware('throttle:member-write');
    // Member stories (24h) shown in the feed's stories row.
    Route::post('/stories',                 [App\Http\Controllers\UserStoryController::class, 'store'])->name('stories.store')->middleware('throttle:uploads');
    Route::delete('/stories/{story}',       [App\Http\Controllers\UserStoryController::class, 'destroy'])->name('stories.destroy')->middleware('throttle:member-write');
    Route::get('/schedule',  [App\Http\Controllers\PersonalMobileController::class, 'schedule'])->name('schedule');
    Route::get('/schedule/data', [App\Http\Controllers\PersonalMobileController::class, 'scheduleData'])->name('schedule.data');
    Route::post('/schedule', [App\Http\Controllers\PersonalMobileController::class, 'store'])->name('schedule.store')->middleware('throttle:member-write');
    Route::get('/schedule/synced/{token}', [App\Http\Controllers\PersonalMobileController::class, 'scheduleSyncedShow'])->name('schedule.synced');
    Route::put('/schedule/synced/{token}', [App\Http\Controllers\PersonalMobileController::class, 'scheduleSyncedUpdate'])->name('schedule.synced.update')->middleware('throttle:admin-write');
    // Substitute trainer for a single dated occurrence of a club class.
    Route::get('/schedule/synced/{token}/substitutes', [App\Http\Controllers\PersonalMobileController::class, 'substituteSearch'])->name('schedule.substitute.search');
    Route::post('/schedule/synced/{token}/substitute', [App\Http\Controllers\PersonalMobileController::class, 'substituteAssign'])->name('schedule.substitute.assign')->middleware('throttle:admin-write');
    Route::delete('/schedule/synced/{token}/substitute', [App\Http\Controllers\PersonalMobileController::class, 'substituteRemove'])->name('schedule.substitute.remove')->middleware('throttle:admin-write');
    // Mark / unmark attendance for one dated occurrence of a club class.
    Route::post('/schedule/synced/{token}/attendance', [App\Http\Controllers\PersonalMobileController::class, 'attendanceToggle'])->name('schedule.attendance.toggle')->middleware('throttle:admin-write');
    // Cancel / restore a club class for a date or range (credits enrolled members).
    // Trainee engagement: emoji reaction + rate the trainer.
    Route::post('/schedule/synced/{token}/react', [App\Http\Controllers\PersonalMobileController::class, 'reactClass'])->name('schedule.react')->middleware('throttle:member-write');
    Route::post('/schedule/synced/{token}/rate', [App\Http\Controllers\PersonalMobileController::class, 'rateClassTrainer'])->name('schedule.rate')->middleware('throttle:social');
    Route::post('/schedule/synced/{token}/rate-class', [App\Http\Controllers\PersonalMobileController::class, 'rateClass'])->name('schedule.rate.class')->middleware('throttle:social');
    Route::delete('/schedule/synced/{token}/rate-class', [App\Http\Controllers\PersonalMobileController::class, 'rateClassDestroy'])->name('schedule.rate.class.destroy')->middleware('throttle:social');
    Route::post('/schedule/synced/{token}/cancel', [App\Http\Controllers\PersonalMobileController::class, 'classCancel'])->name('schedule.cancel')->middleware('throttle:admin-write');
    Route::delete('/schedule/synced/{token}/cancel', [App\Http\Controllers\PersonalMobileController::class, 'classUncancel'])->name('schedule.uncancel')->middleware('throttle:admin-write');
    Route::get('/schedule/{session}', [App\Http\Controllers\PersonalMobileController::class, 'scheduleShow'])->name('schedule.show')->whereNumber('session');
    Route::put('/schedule/{session}', [App\Http\Controllers\PersonalMobileController::class, 'update'])->name('schedule.update')->whereNumber('session')->middleware('throttle:member-write');
    Route::delete('/schedule/{session}', [App\Http\Controllers\PersonalMobileController::class, 'destroy'])->name('schedule.destroy')->whereNumber('session')->middleware('throttle:member-write');
    Route::get('/affiliations', [App\Http\Controllers\PersonalMobileController::class, 'affiliations'])->name('affiliations');
    Route::get('/profile',   [App\Http\Controllers\PersonalMobileController::class, 'profile'])->name('profile');
    Route::get('/packages',  [App\Http\Controllers\PersonalMobileController::class, 'packages'])->name('packages');
    Route::get('/progress',  [App\Http\Controllers\PersonalMobileController::class, 'progress'])->name('progress');
    Route::get('/payments',  [App\Http\Controllers\PersonalMobileController::class, 'payments'])->name('payments');
    // Events — real, DB-backed (club_events).
    Route::get('/events',                  [App\Http\Controllers\PersonalEventController::class, 'index'])->name('events');
    Route::get('/events/create',           [App\Http\Controllers\PersonalEventController::class, 'create'])->name('events.create');
    Route::post('/events',                 [App\Http\Controllers\PersonalEventController::class, 'store'])->name('events.store')->middleware('throttle:member-write');
    Route::get('/events/{event:uuid}',          [App\Http\Controllers\PersonalEventController::class, 'show'])->name('events.show');
    Route::get('/events/{event:uuid}/edit',     [App\Http\Controllers\PersonalEventController::class, 'edit'])->name('events.edit');
    Route::put('/events/{event:uuid}',          [App\Http\Controllers\PersonalEventController::class, 'update'])->name('events.update')->middleware('throttle:member-write');
    Route::patch('/events/{event:uuid}/cancel', [App\Http\Controllers\PersonalEventController::class, 'cancelEvent'])->name('events.cancel-event')->middleware('throttle:member-write');
    Route::put('/events/{event:uuid}/results',  [App\Http\Controllers\PersonalEventController::class, 'setResults'])->name('events.results')->middleware('throttle:member-write');
    Route::delete('/events/{event:uuid}',       [App\Http\Controllers\PersonalEventController::class, 'destroy'])->name('events.destroy')->middleware('throttle:member-write');
    Route::get('/events/{event:uuid}/brackets', [App\Http\Controllers\PersonalEventController::class, 'bracket'])->name('events.bracket');
    Route::post('/events/{event:uuid}/generate-draw', [App\Http\Controllers\PersonalEventController::class, 'generateDraw'])->name('events.generate-draw')->middleware('throttle:member-write');
    Route::post('/events/{event:uuid}/expenses', [App\Http\Controllers\PersonalEventController::class, 'addExpense'])->name('events.expenses.add')->middleware('throttle:member-write');
    Route::delete('/events/{event:uuid}/expenses/{expense}', [App\Http\Controllers\PersonalEventController::class, 'deleteExpense'])->name('events.expenses.delete')->whereNumber('expense')->middleware('throttle:member-write');
    Route::put('/events/{event:uuid}/categories/{category}', [App\Http\Controllers\PersonalEventController::class, 'saveCategory'])->name('events.category.save')->middleware('throttle:member-write');
    Route::post('/events/{event:uuid}/register',  [App\Http\Controllers\PersonalEventController::class, 'register'])->name('events.register')->middleware('throttle:member-write');
    Route::post('/events/{event:uuid}/ticket',    [App\Http\Controllers\PersonalEventController::class, 'ticket'])->name('events.ticket')->middleware('throttle:member-write');
    Route::delete('/events/{event:uuid}/register', [App\Http\Controllers\PersonalEventController::class, 'cancel'])->name('events.cancel')->middleware('throttle:member-write');
    // Owner moderation of registrations (remove / block this event / blacklist club-wide).
    Route::post('/events/{event:uuid}/participants/{user}/moderate', [App\Http\Controllers\PersonalEventController::class, 'moderateParticipant'])->name('events.participant.moderate')->whereNumber('user')->middleware('throttle:member-write');
    Route::delete('/events/{event:uuid}/bans/{user}', [App\Http\Controllers\PersonalEventController::class, 'liftBan'])->name('events.ban.lift')->whereNumber('user')->middleware('throttle:member-write');
    Route::get('/market',    [App\Http\Controllers\PersonalMobileController::class, 'market'])->name('market');
    Route::get('/market/{product}', [App\Http\Controllers\PersonalMobileController::class, 'marketShow'])->name('market.show')->whereNumber('product');
    // Shop orders (member side): place an order + see my orders.
    Route::get('/orders',    [App\Http\Controllers\OrderController::class, 'index'])->name('orders');
    Route::post('/orders',   [App\Http\Controllers\OrderController::class, 'store'])->name('orders.store')->middleware('throttle:member-write');
    Route::post('/orders/{order}/receive', [App\Http\Controllers\OrderController::class, 'receive'])->name('orders.receive')->middleware('throttle:member-write');
    // Challenges & 1v1 duels (real, DB-backed).
    Route::get('/challenge',                       [App\Http\Controllers\ChallengeController::class, 'index'])->name('challenge');
    Route::get('/challenge/create',                [App\Http\Controllers\ChallengeController::class, 'create'])->name('challenge.create');
    Route::post('/challenge/duels',                [App\Http\Controllers\ChallengeController::class, 'store'])->name('challenge.store')->middleware('throttle:member-write');
    Route::get('/challenge/history',               [App\Http\Controllers\ChallengeController::class, 'history'])->name('challenge.history');
    Route::get('/challenge/duel/{duel}',           [App\Http\Controllers\ChallengeController::class, 'duel'])->name('challenge.duel')->whereNumber('duel');
    Route::post('/challenge/duel/{duel}/accept',   [App\Http\Controllers\ChallengeController::class, 'accept'])->name('challenge.duel.accept')->middleware('throttle:member-write');
    Route::post('/challenge/duel/{duel}/decline',  [App\Http\Controllers\ChallengeController::class, 'decline'])->name('challenge.duel.decline')->middleware('throttle:member-write');
    Route::post('/challenge/duel/{duel}/cancel',   [App\Http\Controllers\ChallengeController::class, 'cancel'])->name('challenge.duel.cancel')->middleware('throttle:member-write');
    Route::post('/challenge/duel/{duel}/report',   [App\Http\Controllers\ChallengeController::class, 'report'])->name('challenge.duel.report')->middleware('throttle:member-write');
    Route::post('/challenge/duel/{duel}/confirm',  [App\Http\Controllers\ChallengeController::class, 'confirm'])->name('challenge.duel.confirm')->middleware('throttle:member-write');
    Route::post('/challenge/duel/{duel}/dispute',  [App\Http\Controllers\ChallengeController::class, 'dispute'])->name('challenge.duel.dispute')->middleware('throttle:member-write');
    Route::get('/challenge/{challenge}',           [App\Http\Controllers\ChallengeController::class, 'show'])->name('challenge.show')->whereNumber('challenge');
    Route::post('/challenge/{challenge}/join',     [App\Http\Controllers\ChallengeController::class, 'join'])->name('challenge.join')->middleware('throttle:member-write');
    Route::post('/challenge/{challenge}/leave',    [App\Http\Controllers\ChallengeController::class, 'leave'])->name('challenge.leave')->middleware('throttle:member-write');
    Route::post('/challenge/{challenge}/progress', [App\Http\Controllers\ChallengeController::class, 'progress'])->name('challenge.progress')->middleware('throttle:member-write');
    Route::get('/settings',  [App\Http\Controllers\PersonalMobileController::class, 'settings'])->name('settings');

    // Switch the active UI language (persists to user + session; client reloads).
    Route::put('/locale',    [App\Http\Controllers\LocaleController::class, 'update'])->name('locale.update');
});

// Single-post permalink — unguessable token, members-only (auth-gated).
Route::middleware(['auth', 'verified', 'two-factor'])
    ->get('/p/{post:token}', [App\Http\Controllers\UserPostController::class, 'show'])
    ->name('posts.show');

// Member walls + social graph (follow / connect / block)
Route::middleware(['auth', 'verified', 'two-factor'])->prefix('u')->name('wall.')->group(function () {
    // Backward-compat: legacy /u/{id} links (e.g. older notifications generated
    // before walls moved to slugs) redirect to the canonical slug URL.
    Route::get('/{id}', function ($id) {
        $user = \App\Models\User::find($id);
        abort_unless($user, 404);
        return redirect()->route('wall.show', $user);
    })->whereNumber('id')->name('legacy');

    // Bound by slug ({user:slug}) so walls aren't reachable by guessing numeric IDs.
    Route::get('/{user:slug}', [App\Http\Controllers\WallController::class, 'show'])->name('show');

    Route::post('/{user:slug}/follow',   [App\Http\Controllers\ConnectionController::class, 'follow'])->name('follow')->middleware('throttle:member-write');
    Route::delete('/{user:slug}/follow', [App\Http\Controllers\ConnectionController::class, 'unfollow'])->name('unfollow')->middleware('throttle:member-write');
    Route::post('/{user:slug}/block',    [App\Http\Controllers\ConnectionController::class, 'block'])->name('block')->middleware('throttle:member-write');
    Route::delete('/{user:slug}/block',  [App\Http\Controllers\ConnectionController::class, 'unblock'])->name('unblock')->middleware('throttle:member-write');
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

// Printable QR posters (club page, club registration, member profile, event).
Route::middleware(['auth', 'verified', 'two-factor'])->prefix('qr')->name('qr.')->group(function () {
    Route::get('/club/{club}/page',     [App\Http\Controllers\QrController::class, 'clubPoster'])->name('club.page');
    Route::get('/club/{club}/register', [App\Http\Controllers\QrController::class, 'clubRegisterPoster'])->name('club.register');
    Route::get('/member/{user}',        [App\Http\Controllers\QrController::class, 'memberPoster'])->name('member');
    Route::get('/member/{user}/svg',    [App\Http\Controllers\QrController::class, 'memberSvg'])->name('member.svg');
    Route::get('/event/{event:uuid}',   [App\Http\Controllers\QrController::class, 'eventPoster'])->name('event');
});

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
    Route::get('/', [App\Http\Controllers\Admin\PlatformController::class, 'home'])->name('platform.index');

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
    // Shop — club store: products held in stock or dropshipped.
    Route::get('/shop', [App\Http\Controllers\Admin\ClubShopController::class, 'shop'])->name('shop');
    Route::post('/shop/products', [App\Http\Controllers\Admin\ClubShopController::class, 'storeProduct'])->name('shop.products.store');
    Route::put('/shop/products/{product}', [App\Http\Controllers\Admin\ClubShopController::class, 'updateProduct'])->name('shop.products.update');
    Route::delete('/shop/products/{product}', [App\Http\Controllers\Admin\ClubShopController::class, 'destroyProduct'])->name('shop.products.destroy');
    Route::post('/shop/categories', [App\Http\Controllers\Admin\ClubShopController::class, 'storeCategory'])->name('shop.categories.store');
    // Incoming shop orders the club fulfils.
    Route::get('/orders', [App\Http\Controllers\Admin\ClubOrderController::class, 'index'])->name('orders');
    Route::patch('/orders/{order}/status', [App\Http\Controllers\Admin\ClubOrderController::class, 'updateStatus'])->name('orders.status');
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
    Route::post('/instructors/reorder', [App\Http\Controllers\Admin\ClubInstructorController::class, 'reorderInstructors'])->name('instructors.reorder')->middleware('throttle:admin-write');
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
    Route::post('/members/resolve-qr', [App\Http\Controllers\Admin\ClubMemberAdminController::class, 'resolveQr'])->name('members.resolve-qr');
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
Route::middleware(['auth', 'verified', 'two-factor'])->delete('/notifications', [App\Http\Controllers\Admin\ClubNotificationController::class, 'clearAll'])->name('notifications.clear');

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
    Route::post('/member/{id}/regenerate-password', [MemberController::class, 'regeneratePassword'])->name('member.regenerate-password')->middleware('throttle:member-write');
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
