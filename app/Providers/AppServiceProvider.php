<?php

namespace App\Providers;

use App\Models\Tenant;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
        $this->pruneMissingViewPaths();
    }

    /**
     * Drop registered view-namespace paths that don't exist on disk.
     *
     * `view:cache` walks every hint and throws DirectoryNotFoundException on a
     * missing one. takeone/cropper registers `src/resources/views`, but ships its
     * views at `resources/views` — the published copy under
     * resources/views/vendor/takeone is what resolves at runtime.
     */
    private function pruneMissingViewPaths(): void
    {
        $this->app->booted(function () {
            $finder = $this->app['view']->getFinder();

            foreach ($finder->getHints() as $namespace => $paths) {
                $existing = array_values(array_filter($paths, 'is_dir'));

                if (count($existing) !== count($paths) && $existing !== []) {
                    $finder->replaceNamespace($namespace, $existing);
                }
            }
        });
    }

    private function configureRateLimiters(): void
    {
        // Login: dual-key — 5 per minute keyed by email+IP (targeted attack),
        // and 10 per minute keyed by IP alone (spray attack).
        RateLimiter::for('login', function (Request $request) {
            return [
                Limit::perMinute(5)->by($request->input('email').'|'.$request->ip()),
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

        // A screen asking about ITSELF — its board, its own status.
        //
        // Keyed by the TOKEN, deliberately, not by the address. A venue is one
        // NAT'd IP: every screen in the building, every Pi and every television,
        // arrives from the same address. Under a per-IP limit one misbehaving
        // screen — a stale token, a tab left open on an old page — spends the
        // whole venue's allowance and every OTHER screen in the hall starts
        // getting 429s in the middle of a competition. Which is exactly what
        // happened. A screen may now only starve itself.
        RateLimiter::for('screen-token', function (Request $request) {
            return Limit::perMinute(60)->by((string) $request->route('token'));
        });

        // A hall screen enrolling itself. Unauthenticated by necessity — a fresh
        // Pi has no credential and no keyboard — and what it gets back grants no
        // access to any data, only the right to show a pairing code until an
        // organiser claims it. But it does write a row, so it is capped.
        //
        // 30 an hour per address, raised from 5. Five was right while a Pi was
        // the only thing that enrolled, ONCE, ever. Now `/court/new` lets any
        // browser become a screen, and a venue has three per mat — a hall with
        // three mats sets up nine, every one of them from the building's single
        // address, on the morning of the competition. Five would have stopped
        // that halfway through. Thirty still makes bulk row creation pointless.
        RateLimiter::for('court-enroll', function (Request $request) {
            return Limit::perHour(30)->by($request->ip());
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
            return Limit::perMinute(6)->by($request->user()?->email.'|'.$request->ip());
        });

        // Member data writes (health records, tournaments, affiliations, goals, family):
        // 30 per minute per user — prevents scripted data flooding.
        RateLimiter::for('member-write', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // Arranging a tournament draw: one save per drop, and an organiser
        // laying out a 32-competitor bracket makes many in a row — so it gets
        // its own bucket rather than exhausting member-write. Still bounded:
        // this is a cheap write, but not a free one.
        RateLimiter::for('bracket-arrange', function (Request $request) {
            return Limit::perMinute(90)->by($request->user()?->id ?: $request->ip());
        });

        // Sensitive admin operations (messaging, notifications, ownership transfer):
        // 60 per minute per user — generous for normal admin use, blocks abuse.
        RateLimiter::for('admin-write', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Copilot ("Coach") chat + confirm: bursty conversational use gets its own
        // bucket so a chat session can't exhaust the shared admin-write limit.
        RateLimiter::for('copilot', function (Request $request) {
            return Limit::perMinute(40)->by($request->user()?->id ?: $request->ip());
        });

        // Backup restore: 3 per hour per user — extremely destructive operation.
        RateLimiter::for('backup', function (Request $request) {
            return Limit::perHour(3)->by($request->user()?->id ?: $request->ip());
        });

        // Catastrophic, rarely-used platform wipe — keep this very tight.
        RateLimiter::for('reset-baseline', function (Request $request) {
            return Limit::perHour(2)->by($request->user()?->id ?: $request->ip());
        });
    }
}
