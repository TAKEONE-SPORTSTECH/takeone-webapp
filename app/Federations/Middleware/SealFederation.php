<?php

namespace App\Federations\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the federation surface sealed: a redirect that would take a visitor
 * out of /federation — the platform's sign-in, its email-verification notice,
 * its home page after a refused action — is turned into the federation's own
 * page. Runs outermost on every federation route, so it also sees what the
 * auth / verified / two-factor middleware decided.
 *
 * Only this host is touched; only paths outside /federation; never JSON.
 */
class SealFederation
{
    /**
     * A redirect the federation's OWN code sends out of /federation on
     * purpose — a manager's gear on a club invitation opening the event's
     * console (InvitePageController::settings). Set by us, stripped here; a
     * redirect without it is re-aimed exactly as before.
     */
    public const LEAVE = 'X-Federation-Leave';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response instanceof RedirectResponse || $request->expectsJson()) {
            return $response;
        }
        if ($response->headers->has(self::LEAVE)) {
            $response->headers->remove(self::LEAVE);

            return $response;
        }
        $target = $response->getTargetUrl();
        $host = parse_url($target, PHP_URL_HOST);
        $path = '/'.ltrim((string) parse_url($target, PHP_URL_PATH), '/');

        if (($host && $host !== $request->getHost()) || str_starts_with($path, '/federation')) {
            return $response;
        }

        // Flashed messages travel with the new target: it is the same response, re-aimed.
        $response->setTargetUrl($request->user() ? route('federations.home') : route('federations.login'));

        return $response;
    }
}
