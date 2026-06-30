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
        ]);
        // Exiting impersonation is a safe escape hatch — it only restores the
        // original admin from the session. Exempt it from CSRF so a stale token
        // in a long-lived page never traps an admin inside an impersonated session
        // with a "419 Page Expired". Applies to desktop + mobile alike.
        $middleware->validateCsrfTokens(except: [
            'impersonate/leave',
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

            // Web navigation: reroute rather than dead-end on a 403 page.
            if ($request->user()) {
                return redirect()->to('/')->with('error', "You don't have access to that page.");
            }

            return redirect()->guest(route('login'))->with('error', 'Please sign in to continue.');
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
