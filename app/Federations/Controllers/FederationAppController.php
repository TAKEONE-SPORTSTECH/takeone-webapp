<?php

namespace App\Federations\Controllers;

use App\Federations\Models\Federation;
use App\Federations\Services\FederationApp;
use App\Federations\Services\FederationBilling;
use App\Federations\Services\FederationDesk;
use App\Federations\Services\FederationDirectory;
use App\Federations\Services\FederationDraws;
use App\Federations\Services\FederationMessages;
use App\Federations\Services\FederationStrings;
use App\Federations\Services\FederationEvents;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the federation dashboard prototype, as designed, from
 * app/Federations/resources/app — see FederationApp for what is rewritten and
 * why. Everything is behind config('federations.enabled').
 */
class FederationAppController extends Controller
{
    public const HOME = 'Events Dashboard.dc.html';

    /**
     * Every screen of the dashboard has its own address — /federation/events,
     * /federation/events/<event uuid>/registrations, /federation/settings… —
     * all answered by home(); the bridge (takeone-bridge.js › routes) opens the
     * screen the address names and keeps the address in step as you move.
     * Also the only shape a sign-in may return to (FederationLoginController).
     */
    public const SECTIONS = 'statistics|events|clubs|calendar|athletes|officials|financials|reports|settings';

    public const DEEP_LINK = '#^/federation/('.self::SECTIONS.')(/[A-Za-z0-9-]{1,64}){0,2}/?$#';

    public function __construct(private FederationApp $app, private FederationDirectory $directory, private FederationBilling $billing, private FederationEvents $events, private FederationDesk $desk, private FederationDraws $draws, private FederationStrings $strings, private FederationMessages $messages) {}

    /**
     * /federation — the dashboard itself, at a clean address.
     *
     * Not behind `auth` in the route file: a signed-out visitor goes to the
     * federation's own sign-in (FederationLoginController), which the
     * platform-wide guest redirect cannot know about. The same gates the
     * staff routes carry — signed in, verified, 2FA passed — are applied here.
     */
    public function home(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $this->ensureEnabled();
        $user = $request->user();
        if (! $user) {
            return redirect()->guest(route('federations.login'));
        }
        if ($user->hasTwoFactorEnabled() && ! $request->session()->get('two_factor.verified')) {
            \Illuminate\Support\Facades\Auth::logout();

            return redirect()->route('federations.login');
        }
        if ($user->email && ! $user->hasVerifiedEmail()) {
            return app(\App\Federations\Federations::class)->sealedPage($request, __('federations::messages.sealed_verify_title'), __('federations::messages.sealed_verify_body'), false, false);
        }
        if (! $this->directory->access($user)) {
            // Rendered in place, never redirected: the surface is sealed, and a
            // redirect from here would come straight back.
            return app(\App\Federations\Federations::class)->sealedPage($request, __('federations::messages.sealed_no_access_title'),
                $user->isSuperAdmin() ? __('federations::messages.login_create_first') : __('federations::messages.login_no_access'), false, false);
        }

        return $this->staffPage($request, self::HOME, '/federation/app/'.rawurlencode(self::HOME));
    }

    /**
     * The Draw tab's board: <x-competition-brackets> in a same-origin frame.
     * Carries no draw data — the dashboard hands it the division — and saves
     * nothing; every winner goes back through federations.api.draws.
     */
    public function drawBoard(Request $request): Response
    {
        $this->ensureEnabled();
        abort_unless(config('federations.draw_board') && $this->directory->access($request->user()), 404);

        return response()->view('federations::draw.board', ['canRecord' => true])
            ->header('X-Frame-Options', 'SAMEORIGIN')
            ->header('Cache-Control', 'no-store, private');
    }

    /** The dashboard's file address (old bookmarks) lands on the clean one. */
    public function dashboardFile(): RedirectResponse
    {
        $this->ensureEnabled();

        return redirect()->route('federations.home');
    }

    /** Old per-federation address: land on the same dashboard. */
    public function legacy(Request $request, Federation $federation): RedirectResponse
    {
        $this->ensureEnabled();
        $access = $this->staffAccess($request);
        $inScope = collect($this->directory->subtree($access['root']))->contains(fn ($f) => $f->is($federation));
        abort_unless($inScope, 404);

        return redirect()->route('federations.home');
    }

    /** A staff page — signed in, and running a federation. */
    public function staffPage(Request $request, string $file, ?string $base = null): Response
    {
        $this->ensureEnabled();
        abort_unless($this->app->isStaffPage($file), 404);

        $access = $this->staffAccess($request);
        $user = $request->user();
        $root = $access['root'];

        return $this->html($this->app->page($file, [
            'csrf' => csrf_token(),
            'mode' => 'staff',
            'favicon' => \App\Federations\Support\FederationBrand::favicon($root),
            'federation' => ['uuid' => $root->uuid, 'name' => $root->name],
            // The platform's one country list — the enroll dialog's nationality
            // picker is the invitation's, fed the same way (see inviteBoot()).
            'dial' => $this->countryList(),
            // What the page OFFERS; the endpoint decides (FederationEvents::delete).
            'canDeleteEvents' => (bool) ($access['manager'] ?? false),
            // Test mode (FederationTestMode): offered to managers; the endpoint decides.
            'canTestMode' => (bool) ($access['manager'] ?? false),
            'testEvents' => $this->events->testEventIds($access),
            // Paid featuring on the members' Events page (takeone-featuring.js): offered to managers.
            'canFeature' => (bool) ($access['manager'] ?? false),
            'featureStates' => ($access['manager'] ?? false) ? $this->events->featureStates($access) : [],
            // The rule book per event (takeone-rulebook.js): offered to managers.
            'canRuleBook' => (bool) ($access['manager'] ?? false),
            'ruleBooks' => ($access['manager'] ?? false) ? $this->events->ruleBooks($access) : [],
            'featureFee' => class_exists(\App\Events\Support\EventFeaturing::class) ? \App\Events\Support\EventFeaturing::fee() : null,
            'user' => [
                'name' => $user->full_name ?: $user->name,
                'email' => strtolower((string) $user->email),
                'twoFactor' => $user->hasTwoFactorEnabled(),
            ],
            ...$this->live($access, $user),
            'takeoneBank' => (function () {
                $b = (array) config('federations.takeone_bank');
                $iban = preg_replace('/\s+/', '', (string) ($b['iban'] ?? ''));

                return ['beneficiary' => (string) ($b['beneficiary'] ?? ''), 'bank' => $b['bank'] ?? null,
                    'iban' => $iban !== '' ? trim(chunk_split(strtoupper($iban), 4, ' ')) : null, 'swift' => $b['swift'] ?? null];
            })(),
            'urls' => [
                'state' => \Illuminate\Support\Facades\Route::has('federations.api.state') ? route('federations.api.state') : null,
                'logout' => route('federations.logout'),
                'security' => $this->securityUrl(),
                'orgs' => route('federations.api.orgs'),
                'users' => route('federations.api.users'),
                'region' => route('federations.api.region'),
                'billing' => route('federations.api.billing'),
                'events' => route('federations.api.events'),
                // One event editor (events.federation_hosts): "Plan new event" opens
                // the shared editor with this federation chosen. Null keeps the modal.
                'eventCreate' => config('events.federation_hosts') && config('federations.platform_events') && ($access['manager'] ?? false)
                    ? route('me.events.create', ['host' => 'federation:'.$access['root']->uuid]) : null,
                'testMode' => \Illuminate\Support\Facades\Route::has('federations.api.events.test-mode') ? route('federations.api.events.test-mode') : null,
                'ruleBook' => \Illuminate\Support\Facades\Route::has('federations.api.events.rulebook') ? route('federations.api.events.rulebook') : null,
                'feature' => \Illuminate\Support\Facades\Route::has('federations.api.events.feature') ? route('federations.api.events.feature') : null,
                'eventDelete' => \Illuminate\Support\Facades\Route::has('federations.api.events.delete') ? route('federations.api.events.delete') : null,
                'uploads' => route('federations.api.uploads'),
                'entryConfirm' => route('federations.api.entries.confirm'),
                'transferReply' => route('federations.api.transfers.reply'),
                'transferDecide' => route('federations.api.transfers.decide'),
                'regsWithdraw' => route('federations.api.regs.withdraw'),
                'regsApprove' => route('federations.api.regs.approve'),
                'regsEnrol' => config('federations.manual_enrolment') && \Illuminate\Support\Facades\Route::has('federations.api.regs.enrol') ? route('federations.api.regs.enrol') : null,
                'regsImport' => config('federations.manual_enrolment') && \Illuminate\Support\Facades\Route::has('federations.api.regs.import') ? [
                    'template' => route('federations.api.regs.template'),
                    'preview' => route('federations.api.regs.import.preview'),
                    'import' => route('federations.api.regs.import'),
                ] : null,
                'draws' => route('federations.api.draws'),
                // Guarded: a stale route cache must never take the dashboard down.
                'schedule' => \Illuminate\Support\Facades\Route::has('federations.api.schedule') ? route('federations.api.schedule') : null,
                'strings' => route('federations.api.strings'),
                'messages' => route('federations.api.messages'),
                'messagesRead' => route('federations.api.messages.read'),
                // Guarded: a stale route cache must never take the dashboard down.
                'statistics' => \Illuminate\Support\Facades\Route::has('federations.api.statistics') ? route('federations.api.statistics') : null,
                'mapLink' => \Illuminate\Support\Facades\Route::has('federations.api.map-link') ? route('federations.api.map-link') : null,
                'clubs' => \Illuminate\Support\Facades\Route::has('federations.api.clubs') ? route('federations.api.clubs') : null,
                'clubSearch' => \Illuminate\Support\Facades\Route::has('federations.api.clubs.search') ? route('federations.api.clubs.search') : null,
                'clubAdd' => \Illuminate\Support\Facades\Route::has('federations.api.clubs.add') ? route('federations.api.clubs.add') : null,
                'drawBoard' => config('federations.draw_board') && \Illuminate\Support\Facades\Route::has('federations.draw-board') ? route('federations.draw-board') : null,
                // Edit / unlink / decide: only for a manager, and only with the feature switched on.
                'clubManage' => ($access['manager'] ?? false) && config('federations.club_management') && \Illuminate\Support\Facades\Route::has('federations.api.clubs.decide') ? [
                    'detail' => route('federations.api.clubs.detail'),
                    'update' => route('federations.api.clubs.update'),
                    'unlink' => route('federations.api.clubs.unlink'),
                    'decide' => route('federations.api.clubs.decide'),
                    'delete' => \Illuminate\Support\Facades\Route::has('federations.api.clubs.delete') ? route('federations.api.clubs.delete') : null,
                    'mergePreview' => \Illuminate\Support\Facades\Route::has('federations.api.athletes.merge-preview') ? route('federations.api.athletes.merge-preview') : null,
                    'merge' => \Illuminate\Support\Facades\Route::has('federations.api.athletes.merge') ? route('federations.api.athletes.merge') : null,
                    'issueId' => \Illuminate\Support\Facades\Route::has('federations.api.athletes.issue-id') ? route('federations.api.athletes.issue-id') : null,
                    'deleteAthletes' => \Illuminate\Support\Facades\Route::has('federations.api.athletes.delete') ? route('federations.api.athletes.delete') : null,
                    'exportAthletes' => \Illuminate\Support\Facades\Route::has('federations.api.athletes.export') ? route('federations.api.athletes.export') : null,
                    'importAthletes' => \Illuminate\Support\Facades\Route::has('federations.api.athletes.import') ? route('federations.api.athletes.import') : null,
                ] : null,
            ],
        ], $base,
            // Live updates: the platform's own MQTT client, and nothing else of the shell.
            // ⚠️ The client starts only on a page carrying `rt-user` (realtime.js
            // boot()) — without it the script loads and never connects, which is
            // how every live signal here was silently lost.
            '<meta name="rt-user" content="'.(int) $user->id.'">'."\n"
            .app(\Illuminate\Foundation\Vite::class)(['resources/js/realtime-only.js'])->toHtml()));
    }

    /**
     * Everything on a staff dashboard that changes: the events, the desk's
     * lists, the draws, who may be messaged, and the prototype's own storage
     * keys. The page is handed it on load, and asks for it again (state())
     * whenever a FederationPulse says something in its federation changed.
     */
    private function live(array $access, $user): array
    {
        $root = $access['root'];

        return [
            'events' => $this->events->payload($access),
            'fedstore' => $this->desk->store($access, $user),
            'draws' => $this->draws->payload($access),
            'schedules' => app(\App\Federations\Services\FederationSchedule::class)->payload($access),
            'messageClubs' => $this->messages->clubMap($access),
            // The prototype's own storage keys, filled from the server. The
            // bridge writes them before the page reads them.
            'store' => [
                'fed-orgs' => $this->directory->orgs($root),
                'fed-users' => $this->directory->users($root, $user, $access),
                'fed-region' => $this->directory->region($root),
                'takeone_club_regs' => $this->desk->regs($access),
                'takeone_strings' => $this->strings->table($access),
                'fed-messages' => $this->messages->threads($access),
            ] + $this->billing->payloads($root),
        ];
    }

    /** The live half of a staff page, re-read after a FederationPulse — the viewer's own scope only. */
    public function state(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->ensureEnabled();
        $access = $this->staffAccess($request);

        return response()->json(['success' => true] + $this->live($access, $request->user()), 200, ['Cache-Control' => 'no-store, private']);
    }

    /** The club invitation — public: a club opens it from a link. */
    public function publicPage(Request $request, string $file): \Symfony\Component\HttpFoundation\Response
    {
        $this->ensureEnabled();
        abort_unless($this->app->isPublicPage($file), 404);

        // Every invitation is to ONE event: /…/Club Invitation.dc.html?event=<uuid>.
        $invitation = app(\App\Federations\Services\FederationInvitation::class);
        $event = $invitation->event((string) $request->query('event', ''));
        abort_unless($event, 404);

        // A club's personal link proves the club; the session remembers it.
        if ($request->query('club')) {
            $invitation->bindToken($event, (string) $request->query('club'));
        }
        // A signed-in owner of exactly one of the federation's clubs needs no code.
        $invitation->bindOwner($event, $request->user());

        // The invitation in the event page's design, when switched on
        // (federations.invite_v2). It is handed the SAME boot the prototype
        // gets, so both pages run on one store and one set of doors.
        // A super-admin may PREVIEW it before it is switched on for everyone:
        // `?preview=v2` (or `?preview=off`) sets it for their session only.
        if ($request->user()?->hasRole('super-admin') && in_array($request->query('preview'), ['v2', 'off'], true)) {
            $request->session()->put('fedinv.preview', $request->query('preview') === 'v2');
        }
        // ONE address per event (owner, 2026-10-04): with the shared club-entry
        // screens on, the invitation forwards to the event's own page, carrying
        // an invitation pass (EventInvitePass) so a club with no account can read
        // it and register. The club's proof bound above rides along in the
        // session. A 302 adds nothing to the history, so Back is not trapped.
        if (config('events.club_entry') && InvitePageController::enabled($request)
            && ($platform = app(\App\Federations\Services\FederationPlatformEvent::class)->enabled()
                ? app(\App\Federations\Services\FederationPlatformEvent::class)->of($event) : null)
            && ! $platform->is_archived) {
            // A link reader (WhatsApp, Instagram…) keeps no session, so the pass
            // would not survive the forward and it would unfurl the login page.
            // It gets the event's preview tags here instead (ShareCard).
            if (\App\Events\Support\ShareCard::isLinkReader($request)) {
                return response()->view('events::entry.share-preview', [
                    'share' => app(\App\Events\Support\ShareCard::class)->meta($platform),
                    'url' => $request->fullUrl(),
                    'host' => $platform->host()->name,
                ])->header('Cache-Control', 'public, max-age=600');
            }
            \App\Events\Support\EventInvitePass::grant($request, $platform);

            // Leaving /federation on purpose — SealFederation would otherwise
            // re-aim it at the federation's sign-in.
            return redirect()->to(route('me.events.show', ['event' => $platform->uuid]).'#cover')
                ->header(\App\Federations\Middleware\SealFederation::LEAVE, '1');
        }

        if (InvitePageController::enabled($request)) {
            return app(InvitePageController::class)->show($request, $event, $this->inviteBoot($event));
        }

        return $this->html($this->app->page($file, $this->inviteBoot($event)));
    }

    /** What the club invitation boots with — its store, its doors, the country list. Public for FederationClubEntryDesk. */
    public function inviteBoot(\App\Federations\Models\FederationEvent $event): array
    {
        $invitation = app(\App\Federations\Services\FederationInvitation::class);

        return [
            'csrf' => csrf_token(),
            'mode' => 'public',
            'favicon' => \App\Federations\Support\FederationBrand::favicon($event->federation),
            'event' => $event->uuid,
            // Test mode: the page says so, so a real club never mistakes it for the real thing.
            'testMode' => $event->test_mode_at !== null,
            'store' => $invitation->store($event),
            // The platform's one country list (App\Support\Countries), for the phone picker.
            // Same list, with the alpha-3 code the page keys nationality by.
            'dial' => $this->countryList(),
            'urls' => [
                'code' => route('federations.invite.code', $event->uuid),
                'verify' => route('federations.invite.verify', $event->uuid),
                'choose' => \Illuminate\Support\Facades\Route::has('federations.invite.choose') ? route('federations.invite.choose', $event->uuid) : null,
                'lookup' => route('federations.invite.lookup', $event->uuid),
                'clubs' => route('federations.invite.clubs', $event->uuid),
                'checkClub' => route('federations.invite.check-club', $event->uuid),
                'submit' => route('federations.invite.submit', $event->uuid),
                'withdraw' => route('federations.invite.withdraw', $event->uuid),
            ],
        ];
    }

    /** App\Support\Countries as the prototype pages read it: alpha-2, alpha-3, name, dial code. */
    private function countryList(): array
    {
        return collect(\App\Support\Countries::dialCodes())
            ->map(fn ($c) => ['i2' => strtoupper($c['flag']), 'i3' => \App\Support\Countries::iso3($c['flag']), 'name' => $c['name'], 'code' => $c['code']])
            ->filter(fn ($c) => $c['i3'] !== 'UNK')->values()->all();
    }

    /** Code, fonts and images the pages load. Holds no data. */
    public function asset(string $file): Response
    {
        $this->ensureEnabled();
        $path = $this->app->assetPath($file);
        abort_unless($path, 404);

        return response($this->app->asset($path), 200, [
            'Content-Type' => $this->app->contentType($path),
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * The prototype draws flags from flagcdn.com/w40/{iso2}.png. We hold the
     * same flags locally (public/images/flags) — Self-Host Everything.
     */
    public function flag(string $code): BinaryFileResponse
    {
        $this->ensureEnabled();
        $code = strtolower($code);
        $path = public_path('images/flags/'.(preg_match('/^[a-z]{2}(-[a-z]{2,3})?$/', $code) ? $code : 'xx').'.svg');
        if (! is_file($path)) {
            $path = public_path('images/flags/xx.svg');
        }

        return response()->file($path, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * What this user runs. Deny by default; a user who runs nothing gets the
     * same 404 as a missing page.
     *
     * @return array{root: Federation, manager: bool, sports: array, role: string}
     */
    private function staffAccess(Request $request): array
    {
        $access = $this->directory->access($request->user());
        abort_unless($access, 404);

        return $access;
    }

    private function securityUrl(): string
    {
        return route('federations.security');
    }

    private function html(string $body): Response
    {
        return response($body, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'same-origin',
        ]);
    }

    private function ensureEnabled(): void
    {
        abort_unless(config('federations.enabled'), 404);
    }
}
