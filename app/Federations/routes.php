<?php

use App\Federations\Controllers\FederationAccountController;
use App\Federations\Controllers\FederationAdminController;
use App\Federations\Controllers\FederationApiController;
use App\Federations\Controllers\FederationAppController;
use App\Federations\Controllers\FederationInviteController;
use App\Federations\Controllers\FederationLoginController;
use App\Federations\Middleware\PulseFederation;
use App\Federations\Middleware\SealFederation;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Federations
|--------------------------------------------------------------------------
| The dashboard is the designed prototype, served as-is from
| app/Federations/resources/app (FederationApp). Its files keep their own
| names — the runtime fetches siblings by filename — so the pages live at
| /federation/app/<file>. Behind config('federations.enabled'); every
| request is re-authorised by the controller.
*/

// SealFederation, outermost: nothing under /federation redirects out of it.
Route::prefix('federation')->name('federations.')->middleware(SealFederation::class)->group(function () {

    // The staff member's own account, in the federation's shell (sign-out,
    // forgotten password, 2FA) — the platform's pages would leave the surface.
    Route::post('/logout', [FederationAccountController::class, 'logout'])->middleware('auth')->name('logout');
    Route::middleware(['throttle:federation-page'])->group(function () {
        Route::get('/password/forgot', [FederationAccountController::class, 'forgot'])->middleware('no-store')->name('password.request');
        Route::get('/password/reset/{token}', [FederationAccountController::class, 'resetForm'])->middleware('no-store')->name('password.reset');
    });
    Route::post('/password/forgot', [FederationAccountController::class, 'sendLink'])->middleware('throttle:password-reset')->name('password.email');
    Route::post('/password/reset', [FederationAccountController::class, 'reset'])->middleware('throttle:password-reset')->name('password.update');
    Route::middleware(['auth', 'verified', 'two-factor', 'throttle:federation-page'])->group(function () {
        Route::get('/security', [FederationAccountController::class, 'security'])->middleware('no-store')->name('security');
    });
    Route::middleware(['auth', 'verified', 'two-factor', 'throttle:admin-write'])->prefix('security')->name('security.')->group(function () {
        Route::post('/setup', [FederationAccountController::class, 'setup'])->name('setup');
        Route::post('/confirm', [FederationAccountController::class, 'confirm'])->middleware('throttle:6,1')->name('confirm');
        Route::post('/codes', [FederationAccountController::class, 'codes'])->middleware('throttle:6,1')->name('codes');
        Route::post('/disable', [FederationAccountController::class, 'disable'])->middleware('throttle:6,1')->name('disable');
    });


    // The federation's own door (the designed sign-in; the platform's auth behind it).
    Route::middleware(['throttle:federation-page'])->group(function () {
        Route::get('/login', [FederationLoginController::class, 'show'])->middleware('no-store')->name('login');
        Route::get('/login/verify', [FederationLoginController::class, 'verify'])->middleware('no-store')->name('login.verify');
    });
    Route::post('/login', [FederationLoginController::class, 'signIn'])->middleware('throttle:login')->name('login.submit');

    // Home checks sign-in itself, so a signed-out visitor lands on the
    // federation's door rather than the platform's.
    Route::middleware(['throttle:federation-page'])->get('/', [FederationAppController::class, 'home'])->name('home');
    // Every screen's own address: the same page, opened where the address says.
    Route::middleware(['throttle:federation-page'])->get('/{section}/{rest?}', [FederationAppController::class, 'home'])
        ->where('section', FederationAppController::SECTIONS)->where('rest', '[A-Za-z0-9/-]{0,140}')
        ->name('section');
    Route::middleware(['throttle:federation-page'])->get('/app/Events Dashboard.dc.html', [FederationAppController::class, 'dashboardFile'])
        ->name('app.dashboard');

    // Staff: signed in, verified, 2FA-cleared.
    Route::middleware(['auth', 'verified', 'two-factor', 'throttle:federation-page'])->group(function () {

        // Literal addresses: the runtime fetches a sibling page by its file
        // name, so the names must stay exactly as the prototype wrote them.
        Route::get('/app/Karate Statistics.dc.html', [FederationAppController::class, 'staffPage'])
            ->defaults('file', 'Karate Statistics.dc.html')
            ->name('app.statistics');
    });

    // Public: the invitation a club opens from a link, and the static files
    // (code, fonts, flags) the pages load. None of it carries data.
    Route::middleware(['throttle:federation-page'])->group(function () {
        Route::get('/app/Club Invitation.dc.html', [FederationAppController::class, 'publicPage'])
            ->defaults('file', 'Club Invitation.dc.html')
            ->name('app.invitation');

        Route::get('/app/flags/{code}.png', [FederationAppController::class, 'flag'])
            ->where('code', '[A-Za-z-]{2,7}')
            ->name('app.flag');
    });

    Route::middleware(['throttle:federation-assets'])->get('/app/{file}', [FederationAppController::class, 'asset'])
        ->where('file', '[A-Za-z0-9._/-]+')
        ->name('app.asset');

    // The dashboard's writes (forwarded by takeone-bridge.js). JSON, CSRF,
    // throttled as writes.
    // PulseFederation: once a write succeeds, every open dashboard in the
    // federation's tree re-reads (live updates, no refresh).
    Route::middleware(['auth', 'verified', 'two-factor', 'throttle:admin-write', PulseFederation::class])
        ->prefix('api')->name('api.')->group(function () {
            Route::post('/orgs', [FederationApiController::class, 'orgs'])->name('orgs');
            Route::post('/users', [FederationApiController::class, 'users'])->name('users');
            Route::post('/region', [FederationApiController::class, 'region'])->name('region');
            Route::post('/billing', [FederationApiController::class, 'billing'])->name('billing');
            Route::post('/events', [FederationApiController::class, 'event'])->name('events');
            Route::post('/uploads', [FederationApiController::class, 'upload'])->middleware('throttle:uploads')->name('uploads');
            Route::post('/entries/confirm', [FederationApiController::class, 'entryConfirm'])->name('entries.confirm');
            Route::post('/transfers/reply', [FederationApiController::class, 'transferReply'])->name('transfers.reply');
            Route::post('/transfers/decide', [FederationApiController::class, 'transferDecide'])->name('transfers.decide');
            Route::post('/regs/withdraw', [FederationApiController::class, 'regsWithdraw'])->name('regs.withdraw');
            Route::post('/regs/approve', [FederationApiController::class, 'regsApprove'])->name('regs.approve');
            Route::post('/draws', [FederationApiController::class, 'draw'])->name('draws');
            Route::post('/strings', [FederationApiController::class, 'strings'])->name('strings');
            Route::post('/messages', [FederationApiController::class, 'messages'])->name('messages');
            Route::post('/clubs', [FederationApiController::class, 'clubAdd'])->name('clubs.add');
        });

    Route::middleware(['auth', 'verified', 'two-factor', 'throttle:federation-page'])
        ->get('/api/messages', [FederationApiController::class, 'messages'])->name('api.messages.read');
    Route::middleware(['auth', 'verified', 'two-factor', 'throttle:federation-read'])
        ->get('/api/state', [FederationAppController::class, 'state'])->name('api.state');
    Route::middleware(['auth', 'verified', 'two-factor', 'throttle:federation-read'])
        ->get('/api/statistics', [FederationApiController::class, 'statistics'])->name('api.statistics');
    Route::middleware(['auth', 'verified', 'two-factor', 'throttle:federation-read'])
        ->get('/api/clubs', [FederationApiController::class, 'clubList'])->name('api.clubs');
    Route::middleware(['auth', 'verified', 'two-factor', 'throttle:federation-lookup'])
        ->get('/api/clubs/search', [FederationApiController::class, 'clubSearch'])->name('api.clubs.search');

    Route::middleware(['throttle:federation-read'])->group(function () {
        Route::get('/logo/{federation:uuid}', [FederationApiController::class, 'logo'])->name('logo');
        Route::get('/event-media/{event:uuid}/{kind}', [FederationApiController::class, 'eventMedia'])
            ->where('kind', 'logo|art-p|art-l')->name('event-media');
        Route::get('/invite-qr/{event:uuid}.{format}', [FederationApiController::class, 'inviteQr'])
            ->where('event', '[0-9a-fA-F-]{36}')->where('format', 'svg|png')->name('invite-qr');
        Route::get('/event-doc/{document}', [FederationApiController::class, 'eventDocument'])
            ->whereUuid('document')->name('event-doc');
    });

    // The club invitation's doors — public, session-bound, throttled hard.
    Route::prefix('invite/{event}')->whereUuid('event')->name('invite.')->group(function () {
        Route::post('/code', [FederationInviteController::class, 'code'])->middleware('throttle:federation-code')->name('code');
        Route::post('/verify', [FederationInviteController::class, 'verify'])->middleware('throttle:federation-verify')->name('verify');
        Route::get('/lookup', [FederationInviteController::class, 'lookup'])->middleware('throttle:federation-lookup')->name('lookup');
        Route::post('/submit', [FederationInviteController::class, 'submit'])->middleware(['throttle:federation-submit', PulseFederation::class])->name('submit');
        Route::post('/withdraw', [FederationInviteController::class, 'withdraw'])->middleware(['throttle:federation-withdraw', PulseFederation::class])->name('withdraw');
    });
    Route::middleware('throttle:federation-read')->group(function () {
        Route::get('/invite-club-logo/{application}', [FederationInviteController::class, 'clubLogo'])->whereUuid('application')->name('invite.club-logo');
        // `{clubKey}`, never `{club}`: the platform binds every `{club}` to a Tenant.
        Route::get('/invite-official/{clubKey}/{slot}', [FederationInviteController::class, 'official'])
            ->where('clubKey', 'f[ca]-[0-9a-f-]{36}')->where('slot', 'mgr|ref1|ref2')->name('invite.official');
    });

    // The first slice's per-federation address now lands on the dashboard.
    Route::middleware(['auth', 'verified', 'two-factor', 'throttle:federation-page'])
        ->get('/{federation:uuid}', [FederationAppController::class, 'legacy'])
        ->name('dashboard');
});

/*
| The super-admin's Federations screens, inside the platform admin: the list
| and the record form. The exact stack the platform admin carries (auth,
| verified, two-factor, super-admin); writes throttled as admin writes.
*/
Route::middleware(['auth', 'verified', 'two-factor', 'role:super-admin'])
    ->prefix('admin/federations')->name('admin.federations.')->group(function () {
        Route::get('/', [FederationAdminController::class, 'index'])->name('index');
        Route::get('/create', [FederationAdminController::class, 'create'])->name('create');
        Route::get('/people', [FederationAdminController::class, 'people'])->middleware('throttle:federation-read')->name('people');
        Route::post('/', [FederationAdminController::class, 'store'])->middleware(['throttle:admin-write', 'throttle:uploads', PulseFederation::class])->name('store');
        Route::get('/{federation:uuid}/edit', [FederationAdminController::class, 'edit'])->name('edit');
        Route::put('/{federation:uuid}', [FederationAdminController::class, 'update'])->middleware(['throttle:admin-write', 'throttle:uploads', PulseFederation::class])->name('update');
        // Delete hides it (soft delete — records and files kept); restore brings it back.
        Route::delete('/{federation:uuid}', [FederationAdminController::class, 'destroy'])->middleware(['throttle:admin-write', PulseFederation::class])->name('destroy');
        Route::post('/{uuid}/restore', [FederationAdminController::class, 'restore'])->middleware(['throttle:admin-write', PulseFederation::class])->whereUuid('uuid')->name('restore');
        Route::get('/{federation:uuid}/documents/{document:uuid}', [FederationAdminController::class, 'document'])->name('document');
        Route::delete('/{federation:uuid}/documents/{document:uuid}', [FederationAdminController::class, 'destroyDocument'])->middleware(['throttle:admin-write', PulseFederation::class])->name('document.destroy');
    });
