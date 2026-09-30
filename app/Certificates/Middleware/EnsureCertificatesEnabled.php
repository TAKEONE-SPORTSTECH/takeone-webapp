<?php

namespace App\Certificates\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Until the feature is switched on, none of its addresses exist. */
class EnsureCertificatesEnabled
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(config('certificates.enabled'), 404);

        return $next($request);
    }
}
