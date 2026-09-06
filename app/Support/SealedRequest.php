<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * "Is this request inside the sealed public event surface, and if so, where is
 * home?"
 *
 * A tiny helper with one job, kept OUT of the Events module on purpose: it is
 * called from `bootstrap/app.php`, whose exception handlers run before any
 * route has been matched and must not reach into a module's internals. It reads
 * the URL and nothing else — no models, no database, no container — because the
 * handlers it serves fire on paths where anything heavier could itself fail.
 *
 * @see \App\Http\Middleware\SealEventPage
 * @see \App\Events\Support\PublicEventSkin
 */
class SealedRequest
{
    /**
     * The event page this request belongs to, or null if it is not one.
     *
     * A 403 or a 404 raised anywhere under `/e/{uuid}` has to land back on the
     * event: that surface is a white-label app, frequently installed to a home
     * screen with no browser chrome at all, so the platform's "we sent you
     * somewhere you can access" is the reader losing the page they opened with
     * no way back to it.
     */
    public static function home(Request $request): ?string
    {
        $uuid = self::uuid($request);

        if ($uuid === null) {
            return null;
        }

        // ⚠️ NEVER bounce the event page to itself. A page that redirects to a
        // page that redirects to it is an infinite loop on a wall screen or an
        // installed app that nobody can stop (CLAUDE.md: every redirect chain
        // must terminate). If the failing request IS the event page, the
        // default handling — a real 404 — is the honest answer.
        if (trim($request->path(), '/') === 'e/'.$uuid) {
            return null;
        }

        // And only when that page will actually render. The organiser can turn
        // the public page off at any moment, and after that every address under
        // /e/{uuid} 404s — including the one we would be sending them to.
        if (! self::stillPublic($uuid)) {
            return null;
        }

        // Built by hand rather than with route() — the named route is the right
        // answer, but this runs while an exception is already being rendered and
        // a second failure here would replace a 404 with a 500.
        return url('/e/'.$uuid);
    }

    /**
     * Where a signed-out visitor to a sealed page is sent instead of /login.
     *
     * The event's OWN sign-in: the platform login form in the event's skin,
     * which comes back to the console on success (PublicEventController::signIn)
     * and says nothing about who runs the event on failure.
     */
    public static function signIn(Request $request): ?string
    {
        $uuid = self::uuid($request);

        if ($uuid === null || ! self::stillPublic($uuid)) {
            return null;
        }

        // Already at the sign-in page: let the default answer stand rather than
        // redirect a page to itself.
        if (trim($request->path(), '/') === 'e/'.$uuid.'/manage') {
            return null;
        }

        return url('/e/'.$uuid.'/manage');
    }

    /** The event uuid this path is about, or null if it is not a /e/ path. */
    private static function uuid(Request $request): ?string
    {
        $segments = explode('/', trim($request->path(), '/'));

        if (($segments[0] ?? null) !== 'e') {
            return null;
        }

        $uuid = $segments[1] ?? '';

        // The event page is addressed by uuid and only by uuid. Anything else
        // under /e/ is not this surface, and guessing would be worse than
        // leaving the default behaviour alone.
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid)
            ? $uuid
            : null;
    }

    /**
     * Is the organiser's public-page switch still on?
     *
     * Asks the one rule rather than reading a column, and swallows anything
     * that goes wrong: this runs while an exception is being rendered, so a
     * failure here must degrade to "leave the default handling alone", never
     * turn a 404 into a 500.
     */
    private static function stillPublic(string $uuid): bool
    {
        try {
            $event = \App\Models\ClubEvent::where('uuid', $uuid)->first();

            return $event !== null && app(\App\Events\Support\PublicEvent::class)->isPublic($event);
        } catch (\Throwable) {
            return false;
        }
    }
}
