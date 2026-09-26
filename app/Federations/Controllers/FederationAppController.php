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
            // What the page OFFERS; the endpoint decides (FederationEvents::delete).
            'canDeleteEvents' => (bool) ($access['manager'] ?? false),
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
                'eventDelete' => \Illuminate\Support\Facades\Route::has('federations.api.events.delete') ? route('federations.api.events.delete') : null,
                'uploads' => route('federations.api.uploads'),
                'entryConfirm' => route('federations.api.entries.confirm'),
                'transferReply' => route('federations.api.transfers.reply'),
                'transferDecide' => route('federations.api.transfers.decide'),
                'regsWithdraw' => route('federations.api.regs.withdraw'),
                'regsApprove' => route('federations.api.regs.approve'),
                'draws' => route('federations.api.draws'),
                'strings' => route('federations.api.strings'),
                'messages' => route('federations.api.messages'),
                'messagesRead' => route('federations.api.messages.read'),
                // Guarded: a stale route cache must never take the dashboard down.
                'statistics' => \Illuminate\Support\Facades\Route::has('federations.api.statistics') ? route('federations.api.statistics') : null,
                'clubs' => \Illuminate\Support\Facades\Route::has('federations.api.clubs') ? route('federations.api.clubs') : null,
                'clubSearch' => \Illuminate\Support\Facades\Route::has('federations.api.clubs.search') ? route('federations.api.clubs.search') : null,
                'clubAdd' => \Illuminate\Support\Facades\Route::has('federations.api.clubs.add') ? route('federations.api.clubs.add') : null,
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
            'fedstore' => $this->desk->store($access),
            'draws' => $this->draws->payload($access),
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
    public function publicPage(Request $request, string $file): Response
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

        return $this->html($this->app->page($file, [
            'csrf' => csrf_token(),
            'mode' => 'public',
            'favicon' => \App\Federations\Support\FederationBrand::favicon($event->federation),
            'event' => $event->uuid,
            'store' => $invitation->store($event),
            'urls' => [
                'code' => route('federations.invite.code', $event->uuid),
                'verify' => route('federations.invite.verify', $event->uuid),
                'lookup' => route('federations.invite.lookup', $event->uuid),
                'submit' => route('federations.invite.submit', $event->uuid),
                'withdraw' => route('federations.invite.withdraw', $event->uuid),
            ],
        ]));
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
