<?php

namespace App\Clubs\Middleware;

use App\Clubs\Support\ClubLock;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The server half of club mode (see App\Clubs\Support\ClubLock): a manager
 * locked into their club who lands on any other full page is sent back to
 * the club instead.
 *
 * Only a successful GET that renders a WHOLE HTML document is re-aimed —
 * JSON, files, media, HTML fragments, redirects and errors pass untouched, so
 * the endpoints the club's own pages call keep working. It checks the
 * response rather than guessing from headers because the shell navigators
 * fetch full pages over XHR too. The redirect target is always a page the
 * lock permits, so the chain terminates.
 *
 * A no-op for everyone who has not come in through /club.
 */
class KeepInClub
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! ClubLock::active()
            || ! $request->isMethod('GET')
            || $response->getStatusCode() !== 200
            || ! str_contains((string) $response->headers->get('Content-Type'), 'text/html')
            || ! $this->isWholeDocument($response)
            || ClubLock::permits($request)) {
            return $response;
        }

        return redirect()->to(ClubLock::homeUrl($request));
    }

    private function isWholeDocument(Response $response): bool
    {
        $content = $response->getContent();
        if (! is_string($content)) {
            return false; // streamed / binary
        }
        $head = strtolower(ltrim(substr($content, 0, 512)));

        return str_starts_with($head, '<!doctype html') || str_starts_with($head, '<html');
    }
}
