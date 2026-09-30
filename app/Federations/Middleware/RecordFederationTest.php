<?php

namespace App\Federations\Middleware;

use App\Federations\Models\FederationEvent;
use App\Federations\Support\FederationTestMode;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Switches the test-mode recorder on for a request that works on an event in
 * test mode (FederationTestMode). The invitation's doors name their event in
 * the address; a staff action is resolved from what it acts on.
 *
 * Grants nothing: it only decides whether what the controller writes — after
 * its own authorisation — is recorded as test data.
 */
class RecordFederationTest
{
    public function handle(Request $request, Closure $next): Response
    {
        $param = $request->route('event');
        $event = is_string($param) && Str::isUuid($param)
            ? FederationEvent::where('uuid', $param)->first()
            : ($param instanceof FederationEvent ? $param : FederationTestMode::eventForRequest($request));

        if (! FederationTestMode::isOn($event)) {
            return $next($request);
        }

        FederationTestMode::activate($event);
        try {
            return $next($request);
        } finally {
            FederationTestMode::deactivate();
        }
    }
}
