<?php

namespace App\Providers;

use App\Clubs\Models\Tenant;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
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
        /*
         * Aliases for polymorphic types, so a `*_type` column never holds a PHP
         * namespace. See App\Support\MorphMap for why this matters before any
         * model is moved between folders.
         */
        Relation::morphMap(\App\Support\MorphMap::map());

        /*
         * The event management screens, mirrored INSIDE the public event page.
         *
         * A shared competition link is a standalone app: nothing inside it may
         * hand the reader to the platform behind it — including the organiser
         * who signs in to run the event. The mirror re-serves every screen
         * under /e/{uuid}/admin/… through the event's own shell.
         *
         * Hung on the `Routing` event — dispatched immediately before the router
         * matches — rather than on `booted()`. The routes being cloned are
         * declared across several module route files, and ModuleServiceProvider
         * loads those in a `booted()` callback of its OWN: two callbacks in one
         * queue is a race, and it lost it in php-fpm while winning it under the
         * console kernel, so the mirror existed in every test and in none of the
         * browser's requests. The `Routing` event has no such ambiguity: by the
         * time it fires, every route file has been loaded.
         */
        Event::listen(
            \Illuminate\Routing\Events\Routing::class,
            fn () => \App\Events\Support\SealedEventRoutes::register(),
        );

        /*
         * Keep the profile's Affiliations and Tournaments tabs in step with what
         * the club actually did. Those tabs read the member's self-reported log;
         * `memberships` and `club_event_registrations` are the authoritative
         * records, and without this a member could be enrolled and have competed
         * while both tabs sat empty.
         *
         * Both observers are created-only, idempotent and best-effort — joining a
         * club or entering an event must never fail because a profile row could
         * not be written. See App\Support\ProfileHistorySync.
         */
        \App\Members\Models\Membership::observe(\App\Observers\MembershipObserver::class);
        \App\Models\ClubEventRegistration::observe(\App\Observers\ClubEventRegistrationObserver::class);

        // A new avatar joins the profile's own pictures, whichever path set it.
        \App\Members\Models\User::observe(\App\Observers\UserObserver::class);

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

        /*
         * The PUBLIC ENTRY door — the page a competitor with no account opens
         * to enter a competition, and the club search inside it.
         *
         * The same NAT lesson as `screen-token` below, on the surface where it
         * costs the most. A venue is ONE address: the whole hall is on the
         * house wifi, and a coach opening this for eight athletes, plus every
         * competitor checking the page on their own phone, all arrive from that
         * one IP. At 30 a minute the allowance was spent in seconds and the
         * next person to tap "enter this competition" — someone who has never
         * used the platform and has nothing to retry with — got a bare 429.
         * The front door is the one page that must not refuse.
         *
         * A page read is cheap and writes nothing, so it is generous. There is
         * no per-user key available here by definition: whoever is asking has
         * no account yet, which is the entire point of the door.
         */
        RateLimiter::for('public-entry', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        /*
         * Committing an entry. This one WRITES — it creates a person and an
         * entry — so it stays far tighter than the page around it, but it is
         * lifted off 5 for the same NAT reason: five entries a minute for an
         * entire venue meant a club registering its squad locked out everybody
         * else in the building. Twenty still bounds a scripted abuser hard,
         * and the real protections are elsewhere: validation, one entry per
         * person per event, and a competitor who must supply what only they
         * know.
         */
        RateLimiter::for('public-entry-write', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        // A screen asking about ITSELF — its board, its own status.
        //
        // Keyed by the TOKEN, deliberately, not by the address. A venue is one
        // NAT'd IP: every screen in the building, every board and every television,
        // arrives from the same address. Under a per-IP limit one misbehaving
        // screen — a stale token, a tab left open on an old page — spends the
        // whole venue's allowance and every OTHER screen in the hall starts
        // getting 429s in the middle of a competition. Which is exactly what
        // happened. A screen may now only starve itself.
        RateLimiter::for('screen-token', function (Request $request) {
            return Limit::perMinute(60)->by((string) $request->route('token'));
        });

        // A hall screen enrolling itself. Unauthenticated by necessity — a fresh
        // screen has no credential and no keyboard — and what it gets back grants no
        // access to any data, only the right to show a pairing code until an
        // organiser claims it. But it does write a row, so it is capped.
        //
        // 30 an hour per address, raised from 5. Five was right while a screen was
        // the only thing that enrolled, ONCE, ever. Now `/court/new` lets any
        // browser become a screen, and a venue has three per mat — a hall with
        // three mats sets up nine, every one of them from the building's single
        // address, on the morning of the competition. Five would have stopped
        // that halfway through. Thirty still makes bulk row creation pointless.
        // Raised from 30 to 120. Thirty was sized for a hall setting up once,
        // and it is the wrong shape for how this is actually used: a venue is
        // one NAT address, every screen in the building enrols through it, and
        // setting up is iterative — a screen is paired to the wrong mat, a
        // television is moved, somebody presses Try again. Running out looked
        // exactly like a hang, because the page showed the same spinner either
        // way (now fixed, but the limit was still too tight).
        //
        // What it grants is unchanged and still worth almost nothing: a row
        // that can render its own pairing code and nothing else, useless until
        // an authenticated organiser adopts it. Bulk creation remains pointless.
        RateLimiter::for('court-enroll', function (Request $request) {
            /*
             * 600/hour per IP, not 120.
             *
             * A COMPETITION VENUE IS ONE IP. Every television, tablet and phone
             * in the hall shares the venue's NAT address, and setting up is not
             * one visit each: a screen is opened, carried to a wall, reopened
             * because somebody power-cycled it, the camera page is opened on
             * three phones, an organiser reloads to get a fresh code. Eight
             * screens and a bad morning reaches 120 easily — and what a screen
             * gets at 121 is a 429 HTML page with NO CODE ON IT, which on a wall
             * display with no keyboard is indistinguishable from the platform
             * being down.
             *
             * What this protects is cheap: a `pending_screens` row is a few
             * dozen bytes and holds nothing until an authenticated organiser
             * claims it. 600/hour still bounds a scripted flood to something
             * trivial, while putting the limit far above anything a real hall
             * can produce. The abuse this guards against was never volume; it
             * was somebody minting codes hoping to collide with one an organiser
             * is about to type, and THAT is bounded by the 29^6 code space and
             * by the claim requiring a session, not by this number.
             */
            return Limit::perHour(600)->by($request->ip());
        });

        // Handing a bare television its own app. Unauthenticated by necessity,
        // for the same reason enrolling is: the machine asking has no keyboard,
        // no account and — this being the point — no app yet. It is the one
        // thing a screen needs BEFORE it can be a screen.
        //
        // Capped because it is the most expensive open response on the platform:
        // ~44 MB streamed per call.
        //
        // Six an hour was the first guess and it was wrong for the same reason
        // court-enroll's five was: a venue is ONE NAT address, and kitting out a
        // wall is iterative. Six screens is six downloads before anybody has
        // retried anything, there are now two builds to fetch, and running out
        // looks exactly like a broken download button on a machine with no way
        // to read an error. Twenty leaves room for a hall plus its mistakes and
        // is still a pointless way to spend the box's bandwidth.
        //
        // The artifact carries no secret — it is a kiosk browser pointed at one
        // host — so what a caller gains by fetching it is a copy of something we
        // hand out on purpose.
        /*
         * A camera pushing a bout's video up in 8MB chunks.
         *
         * Its own limiter because the shape is unlike every other camera call:
         * one clip is hundreds of requests in a few minutes, where a telemetry
         * beat is one every twenty seconds. Keyed on the device token, so four
         * cameras on a mat each get their own budget and one uploading phone
         * cannot throttle the fleet's heartbeats.
         *
         * 600/minute is ~80 MB/s of chunks — far beyond any hall's wifi, which
         * is the point: the limiter is there to stop a loop, not to shape
         * traffic the network already shapes.
         */
        RateLimiter::for('camera-upload', function (Request $request) {
            return Limit::perMinute(600)->by((string) $request->route('token'));
        });

        RateLimiter::for('screen-app', function (Request $request) {
            return Limit::perHour(20)->by($request->ip());
        });

        // Watching a bout. One minute of HLS is ten segment requests, and a
        // person scrubbing through a five-minute fight legitimately fires
        // hundreds — so this is generous by design. It exists to stop a script
        // walking the library, not to ration playback, and it counts per VIEWER
        // rather than per address so a hall behind one NAT is not one budget.
        RateLimiter::for('media-read', function (Request $request) {
            return Limit::perMinute(1200)->by((string) ($request->user()?->id ?: $request->ip()));
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
