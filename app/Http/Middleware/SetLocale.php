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
 * Only locales listed in config/locales.php are ever applied.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $available = array_keys(config('locales', []));

        $locale = null;

        if (($user = $request->user()) && $user->locale) {
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
