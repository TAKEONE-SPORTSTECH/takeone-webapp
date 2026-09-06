<?php

namespace App\EventLab\Middleware;

use App\EventLab\Services\EventCloner;
use App\Models\ClubEvent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The fence around the copied event flow.
 *
 * `app/EventLab/Controllers/` holds copies of the live event controllers, kept
 * so the flow can be rebuilt without editing the one that is running. A copy is
 * only safe while it cannot act on a real event — and "it is only reachable at
 * /testcode" is not that guarantee, because the uuid in the URL decides which
 * event is loaded, not the prefix.
 *
 * So every copied route passes through here: the event named in the request must
 * be a SANDBOX TWIN, and anything else answers exactly as an unknown uuid does.
 * A real event's uuid pasted into a /testcode address is a 404, whoever is
 * signed in.
 */
class EnsureSandboxEvent
{
    public function handle(Request $request, Closure $next): Response
    {
        $uuid = $request->route('event');

        // A route with no event in it names no event to protect — the sandbox's
        // own index and create screens are the two. Guarding them anyway is what
        // made both 404 for everybody: there is no `{event}` to look up, so the
        // check could only ever fail.
        if ($uuid === null) {
            return $next($request);
        }

        // Route model binding may have resolved it already, or not yet.
        $event = $uuid instanceof ClubEvent
            ? $uuid
            : (is_string($uuid) ? ClubEvent::where('uuid', $uuid)->first() : null);

        abort_unless($event && str_starts_with($event->title, EventCloner::TITLE_PREFIX), 404);

        return $next($request);
    }
}
