<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * `throttle:N,M` — with a counter PER ROUTE, as every line that writes it assumes.
 *
 * Laravel's inline throttle keys its counter by the user (or, signed out, the
 * domain + IP) and NOTHING about the route. Every inline-throttled route in the
 * application therefore shared one bucket per visitor, and each route's own
 * limit was tested against the SUM of all of them: `throttle:6,1` on the draw
 * PDF answered "Too many requests" on the first click because the draw page
 * had just made a handful of `throttle:60,1` requests. The same bug had been
 * patched by hand three times (named limiters `public-event`, `public-entry`,
 * `screen-token` in AppServiceProvider) before this fixed it at the source.
 *
 * Now the key is the visitor + the route + the limit itself:
 *   · the route, so one door can only ever use up its own allowance;
 *   · the limit, so a route carrying two inline throttles (a group's and its
 *     own) keeps two counters instead of counting every request twice.
 *
 * Named limiters (`throttle:uploads`) are untouched — they key themselves.
 * Every limit is exactly as strict per route as it reads; it simply stops
 * being charged for traffic on other routes.
 */
class ThrottleRequestsPerRoute extends ThrottleRequests
{
    public function handle($request, Closure $next, $maxAttempts = 60, $decayMinutes = 1, $prefix = '')
    {
        // A named limiter: Laravel's own path, unchanged.
        if (is_string($maxAttempts) && func_num_args() === 3 && $this->limiter->limiter($maxAttempts) !== null) {
            return parent::handle($request, $next, $maxAttempts);
        }

        $route = $request->route();
        $door = $route
            ? ($route->getName() ?: implode(',', $route->methods()).' '.$route->uri())
            : $request->method().' '.$request->path();

        return parent::handle(
            $request, $next, $maxAttempts, $decayMinutes,
            $prefix.'route:'.sha1($door.'|'.$maxAttempts.','.$decayMinutes).'|',
        );
    }
}
