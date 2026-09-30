<?php

namespace App\Federations\Middleware;

use App\Federations\Models\FederationEntry;
use App\Federations\Models\FederationEvent;
use App\Federations\Services\FederationPlatformEntries;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * After a club's entry changes on the federation's side — a club submits or
 * withdraws through the invitation, the dashboard approves, withdraws, enrols
 * by hand or imports — bring the event's platform entries into step
 * (FederationPlatformEntries). Only on a successful write, and never able to
 * fail the request that already succeeded.
 */
class SyncPlatformEntries
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethodSafe() || $response->getStatusCode() >= 400) {
            return $response;
        }

        rescue(function () use ($request) {
            $uuids = array_filter(array_merge(
                [$request->route('event'), $request->input('event')],
                FederationEntry::whereIn('uuid', array_filter(array_merge(
                    [$request->input('entry')],
                    collect((array) $request->input('athletes', []))->pluck('entry')->all(),
                ), 'is_string'))->with('event')->get()->pluck('event.uuid')->all(),
            ), 'is_string');

            foreach (FederationEvent::whereIn('uuid', array_unique($uuids))->get() as $fe) {
                app(FederationPlatformEntries::class)->sync($fe);
            }
        }, null, report: true);

        return $response;
    }
}
