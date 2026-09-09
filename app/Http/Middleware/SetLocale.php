<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active UI locale for every web request.
 *
 * Priority (first match wins):
 *   1. The authenticated user's saved `locale`
 *   2. A `locale` stored in the session (guests / pre-save)
 *   3. The browser's Accept-Language header (limited to supported locales)
 *   4. The app default (config/app.php)
 *
 * Only locales listed in config/locales.php or config/content_locales.php are
 * ever applied — a locale is a key into a directory name and a cache key, so it
 * is matched against a whitelist and never taken as given.
 *
 * ⚠️ The two lists mean two different things:
 *
 *   config/locales.php          the INTERFACE speaks it (lang/<code>/ exists).
 *   config/content_locales.php  an organiser's own WORDS can be read in it
 *                               (App\Translation writes them).
 *
 * Both are applied here, because setting the app locale to a content-only
 * language is exactly right: Laravel falls back to lang/en for the chrome,
 * Carbon renders the dates and month names in that language, and the event's
 * own text comes from the translation store. Refusing them — which this
 * middleware did until 2026-09-08 — meant a visitor could pick Turkish, the
 * translation would be written, and the page would still render in English
 * because the locale never took.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $available = array_keys(config('locales', []) + config('content_locales', []));

        $locale = null;

        /*
         * ⚠️ An EVENT's language outranks everything — inside that event only.
         *
         * A stranger opening a public poster picks a language to read that
         * competition in. That is not a statement about the platform, and it
         * must not become one: before this, choosing Português on a poster
         * re-languaged the whole site for a signed-in member and followed them
         * to every device. See App\Translation\EventLocale.
         *
         * Returns null on every URL that is not `/e/{uuid}`, so nothing else
         * on the platform resolves differently than it always did.
         */
        if ($event = \App\Translation\EventLocale::forRequest($request)) {
            $locale = $event;
        } elseif (($user = $request->user()) && $user->locale) {
            $locale = $user->locale;
        } elseif ($request->session()->has('locale')) {
            $locale = $request->session()->get('locale');
        } elseif (! empty($available)) {
            $locale = $request->getPreferredLanguage($available);
        }

        if ($locale && in_array($locale, $available, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
