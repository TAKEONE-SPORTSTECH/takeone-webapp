<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class StructuredLogging
{
    /**
     * Attach a per-request correlation ID and common context to every log
     * entry made during this request, then expose the correlation ID in the
     * response header so it can be matched against log entries.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::uuid();

        Log::shareContext([
            'request_id' => $requestId,
            'ip'         => $request->ip(),
            'method'     => $request->method(),
            'url'        => $request->fullUrl(),
            'route'      => $request->route()?->getName(),
            'user_agent' => $request->userAgent(),
            'user_id'    => Auth::id(),
        ]);

        $response = $next($request);

        // Expose the request ID in the response so it can be correlated with
        // log entries in Sentry / Datadog / log files during debugging.
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }
}
