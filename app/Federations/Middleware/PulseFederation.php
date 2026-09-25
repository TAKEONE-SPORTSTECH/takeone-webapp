<?php

namespace App\Federations\Middleware;

use App\Federations\Models\Federation;
use App\Federations\Support\FederationPulse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * After a federation write succeeds, tell every open dashboard in that
 * federation's tree to re-read (FederationPulse). On the write routes only —
 * one place, so a new endpoint cannot forget to be live.
 *
 * Which federation: the event a club invitation route names, else the body a
 * super-admin route names, else the one the signed-in staff member works in.
 */
class PulseFederation
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethodSafe() || $response->getStatusCode() >= 400) {
            return $response;
        }

        $route = $request->route();
        $event = $route?->parameter('event');
        $federation = $route?->parameter('federation');

        if ($event) {
            FederationPulse::forEvent($event);
        } elseif ($federation instanceof Federation) {
            FederationPulse::federation($federation);
        } elseif ($route?->parameter('uuid')) {
            FederationPulse::federation(Federation::withTrashed()->where('uuid', $route->parameter('uuid'))->first());
        } else {
            FederationPulse::forUser($request->user());
        }

        return $response;
    }
}
