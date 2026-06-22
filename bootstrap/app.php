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
