<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signed-in HTML pages are per-user and per-locale: they must never be replayed
 * from a browser cache. Two bugs came from this:
 *
 *  - Exiting impersonation restored the admin server-side, but Back replayed the
 *    impersonated page, so the admin still looked like the member.
 *  - Switching language re-rendered the current page, but Back replayed pages
 *    rendered in the old locale — English strings, `dir="ltr"`.
 *
 * Both are the same thing: the browser answers Back from its own cache and
 * Laravel is never asked. A `pageshow`/`persisted` listener only catches the
 * bfcache half; the ordinary HTTP disk cache restores without firing it.
 * `no-store` forbids both, so Back always re-requests.
 *
 * Scoped to authenticated HTML so public/cacheable pages and JSON are untouched.
 */
class NoStoreAuthenticatedPages
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! Auth::check() || $request->expectsJson()) {
            return $response;
        }

        $contentType = $response->headers->get('Content-Type', '');

        // Redirects carry no body but sit in history too, so cover them.
        $isHtml = $contentType === '' || str_contains($contentType, 'text/html');

        if ($isHtml || $response->isRedirection()) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }
}
