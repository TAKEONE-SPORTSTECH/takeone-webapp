<?php

namespace App\Providers;

use App\Models\Tenant;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Horizon\Horizon;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Horizon dashboard — super-admin only
        Horizon::auth(function (Request $request) {
            return $request->user()?->hasRole('super-admin') ?? false;
        });

        // Allow {club} route parameter to be resolved by slug OR numeric ID
        Route::bind('club', function ($value) {
            return is_numeric($value)
                ? Tenant::findOrFail($value)
                : Tenant::where('slug', $value)->firstOrFail();
        });

        $this->configureRateLimiters();
    }

    private function configureRateLimiters(): void
    {
        // Login: dual-key — 5 per minute keyed by email+IP (targeted attack),
        // and 10 per minute keyed by IP alone (spray attack).
        RateLimiter::for('login', function (Request $request) {
            return [
                Limit::perMinute(5)->by($request->input('email') . '|' . $request->ip()),
                Limit::perMinute(10)->by($request->ip()),
            ];
        });

        // Registration: 3 accounts per minute per IP.
        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        // Password reset requests: 3 per minute per IP.
        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        // Club join / subscription creation: 5 per minute per authenticated user.
        RateLimiter::for('join-club', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });

        // File uploads (gallery, profile pictures, facility images, etc.):
        // 20 per minute per user — generous enough for normal use, blocks DoS.
        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        // Walk-in registration (admin action — creates users + subscriptions in bulk):
        // 10 per minute per user.
        RateLimiter::for('walk-in', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        // Social interactions (likes, comments, perk collection, reviews):
        // 30 per minute per user.
        RateLimiter::for('social', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // Email verification resend: 6 per minute keyed by email+IP.
        RateLimiter::for('verification', function (Request $request) {
            return Limit::perMinute(6)->by($request->user()?->email . '|' . $request->ip());
        });

        // Member data writes (health records, tournaments, affiliations, goals, family):
        // 30 per minute per user — prevents scripted data flooding.
        RateLimiter::for('member-write', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // Sensitive admin operations (messaging, notifications, ownership transfer):
        // 60 per minute per user — generous for normal admin use, blocks abuse.
        RateLimiter::for('admin-write', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Backup restore: 3 per hour per user — extremely destructive operation.
        RateLimiter::for('backup', function (Request $request) {
            return Limit::perHour(3)->by($request->user()?->id ?: $request->ip());
        });
    }
}
