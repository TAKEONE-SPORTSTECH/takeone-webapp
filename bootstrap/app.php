<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        // Horizon metrics snapshot — feeds the throughput/runtime graphs in the dashboard
        $schedule->command('horizon:snapshot')->everyFiveMinutes();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->append(\App\Http\Middleware\StructuredLogging::class);
        $middleware->web(append: [
            \App\Http\Middleware\DetectDevice::class,
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\NoStoreAuthenticatedPages::class,
        ]);
        // Exiting impersonation is a safe escape hatch — it only restores the
        // original admin from the session. Exempt it from CSRF so a stale token
        // in a long-lived page never traps an admin inside an impersonated session
        // with a "419 Page Expired". Applies to desktop + mobile alike.
        $middleware->validateCsrfTokens(except: [
            'impersonate/leave',
            // A court display enrolling itself on first boot. This
            // is a device calling in, not a browser posting a form: there is no
            // session and no cookie, so there is no cross-site request to forge
            // — CSRF here only guarantees the call can never succeed. The
            // endpoint is protected by what it grants instead (an unclaimed
            // screen that can render a QR code and nothing else) plus a hard
            // rate limit. Note this cannot be caught by tests: Laravel skips
            // CSRF under phpunit, so it fails only against a real device.
            'court/enroll',
            'karate/court/enroll',
            'bjj/screen/enroll',
            // The media server asking this application whether a broadcast may
            // start, and telling it when one ended. Called by a process on
            // loopback, not a browser: there is no session, no cookie and
            // nothing to forge against, so CSRF here would only guarantee that
            // a legitimate call can never succeed. What guards these instead is
            // the ADDRESS — LiveAuthController refuses anything that is not
            // 127.0.0.1 before it reads a single field, and deliberately does
            // not consult proxy headers, which are attacker-controlled.
            'api/live/auth',
            'api/live/hook',
            // The measurement harness on a phone: a native client with no
            // session and no cookie. Guarded by a key it must present on every
            // request, and non-existent unless that key is configured.
            'api/lab/live',
            'api/lab/telemetry',
            // The same, for the sport-neutral waiting room a browser screen
            // enrols into: a television opening one address, with no session and
            // no cookie to forge against. What it grants is a row that can
            // render its own pairing code and nothing else.
            'screen/enroll',
            // The camera phones. Same reasoning again, and stronger: there is
            // no browser at all here — a Flutter app on a tripod holding a
            // device token, with no session and no cookie for anyone to ride.
            // Each of these reaches exactly one camera's own row (its telemetry
            // beat, or the clip index it just filed), is rate-limited per
            // token, and grants no read of the competition.
            'camera/enroll',
            // The publish credential for a camera that also carries a live
            // feed. Same client, same reasoning: no browser, no session, no
            // cookie — the device token IS the authorisation, and it reaches
            // one stream on the one mat this camera was claimed onto.
            'camera/*/live',
            'camera/*/telemetry',
            'camera/*/clip',
            // DELETE from the same device, for a clip it just removed from its
            // own storage. Same reasoning: a token, no session, nothing to forge.
            'camera/*/clip/*',
            'camera/*/clip/*/upload',
        ]);
        $middleware->alias([
            'no-store'   => \App\Http\Middleware\NoStoreCache::class,
            'role'       => \App\Http\Middleware\CheckRole::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'tenant'     => \App\Http\Middleware\SetCurrentTenant::class,
            'two-factor' => \App\Http\Middleware\RequiresTwoFactor::class,
            'business'   => \App\Http\Middleware\EnsureHasBusiness::class,
            // Override the default `verified` gate so impersonation can bypass it.
            'verified'   => \App\Http\Middleware\EnsureEmailIsVerifiedOrImpersonating::class,
            // Sanctum token-ability gates. Needed so a token minted for one
            // integration (e.g. TAKEONE Play lookups) cannot be replayed against
            // any other token-authenticated surface.
            'abilities'  => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability'    => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Gracefully handle "page expired" (419 CSRF token mismatch) instead of
        // dead-ending on the raw "Page Expired" screen. This happens when a form
        // is submitted from a page whose CSRF token has gone stale (left open, a
        // cached/bookmarked login page, or a session cookie that wasn't present).
        // Bounce the user back so the fresh GET mints a new token and they can
        // simply try again — same philosophy as the 403 handler below.
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your session expired. Please refresh the page and try again.',
                ], 419);
            }

            return redirect()->back(fallback: route('login'))
                ->withInput($request->except(['password', 'password_confirmation', '_token']))
                ->with('error', 'Your session expired — please try again.');
        });

        /*
         * A signed-out visitor to a page inside a public event page is sent to
         * the EVENT's own sign-in, never to /login.
         *
         * `auth` raises this exception, and the framework turns it into a
         * redirect out here — outside the route's middleware — so the seal
         * cannot be applied by SealEventPage and belongs here instead. The
         * intended URL is still remembered, so signing in lands them back on
         * the screen they asked for.
         */
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return null;   // an API caller wants the 401, not a page
            }

            $signIn = \App\Support\SealedRequest::signIn($request);

            return $signIn ? redirect()->guest($signIn) : null;
        });

        // Gracefully handle "forbidden" (403) responses instead of showing the raw
        // "Unauthorized action" page. This happens most often when a long-open page
        // belongs to a previous, higher-privilege session (e.g. super-admin) while
        // the live session is now a different, lower-privilege user — navigating
        // then hits an authorization gate. Send the user somewhere they can access.
        $exceptions->render(function (Throwable $e, \Illuminate\Http\Request $request) {
            $isForbidden = $e instanceof \Illuminate\Auth\Access\AuthorizationException
                || ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                    && $e->getStatusCode() === 403);

            if (! $isForbidden) {
                return null; // not a 403 → let Laravel handle it normally
            }

            // AJAX / API callers get JSON so their code can react (no HTML error page).
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'You are not authorized to perform this action.',
                ], 403);
            }

            // Inside a public event page, "somewhere they can access" is the
            // EVENT, never the platform home. That surface is a standalone app
            // — often installed to a home screen, with no address bar and no
            // back button — so a bounce to `/` is not a redirect, it is the
            // reader losing the thing they opened. See
            // App\Http\Middleware\SealEventPage.
            if ($sealed = \App\Support\SealedRequest::home($request)) {
                return redirect()->to($sealed)->with('error', "You don't have access to that page.");
            }

            // Web navigation: reroute rather than dead-end on a 403 page.
            if ($request->user()) {
                return redirect()->to('/')->with('error', "You don't have access to that page.");
            }

            return redirect()->guest(route('login'))->with('error', 'Please sign in to continue.');
        });

        // Gracefully handle "not found" (404) for signed-in web navigation instead
        // of dead-ending on a 404 page. Covers a missing route AND a bound-model
        // miss (firstOrFail on a record the user can't see — e.g. another member's
        // gated full profile). Bounce back with a toast; the flashed `error` is
        // auto-rendered by the layout. Mirrors the 403 handler above.
        $exceptions->render(function (Throwable $e, \Illuminate\Http\Request $request) {
            $isNotFound = $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException
                || ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                    // 405 is folded in with 404 deliberately: to a reader,
                    // "that address does not answer" is one thing, and inside a
                    // chromeless event page the framework's own error page is a
                    // dead end with no way back.
                    && in_array($e->getStatusCode(), [404, 405], true));

            if (! $isNotFound) {
                return null; // not a 404 → let Laravel handle it normally
            }

            // AJAX / API callers get real JSON 404 so their code can react.
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Not found.'], 404);
            }

            // Same as the 403 above: a miss inside a public event page stays
            // inside it, for guests too — the whole point of that surface is
            // that it has no exit.
            if ($sealed = \App\Support\SealedRequest::home($request)) {
                return redirect()->to($sealed)
                    ->with('error', "That page doesn't exist or is no longer available.");
            }

            // Guests fall through to Laravel's default 404 page — there's no
            // sensible "back" for them and no session to flash a toast into.
            if (! $request->user()) {
                return null;
            }

            // back() resolves to the referer; fall back to home for a direct hit
            // (no referer). The fallback also prevents a redirect loop if the
            // missing URL were somehow its own referer.
            return redirect()->back(fallback: '/')
                ->with('error', "That page doesn't exist or is no longer available.");
        });

        $exceptions->reportable(function (Throwable $e): void {
            if (! app()->bound('sentry')) {
                return;
            }

            \Sentry\configureScope(function (\Sentry\State\Scope $scope): void {
                // Attach the current club (tenant) to every error so you can
                // filter by club in the Sentry dashboard.
                if (app()->bound('current.tenant')) {
                    $tenant = app('current.tenant');
                    $scope->setTag('club.id',   (string) $tenant->id);
                    $scope->setTag('club.slug', $tenant->slug);
                    $scope->setContext('club', [
                        'id'   => $tenant->id,
                        'name' => $tenant->club_name,
                        'slug' => $tenant->slug,
                    ]);
                }
            });
        });
    })->create();
