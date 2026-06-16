<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marks a response as non-cacheable so browsers never restore it from the
 * back/forward cache (bfcache).
 *
 * Applied to the GET auth pages (login, register, password reset): mobile
 * Safari/Chrome aggressively bfcache these forms, and a restored page carries a
 * CSRF token that no longer matches the current session — the user then submits
 * and gets a 419 "Page Expired". `no-store` is the directive that actually
 * disables bfcache, forcing a fresh page (and fresh token) on every visit.
 */
class NoStoreCache
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
