<?php

namespace App\Printables\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Until the designer is switched on, none of its addresses exist. */
class EnsurePrintablesEnabled
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(config('printables.enabled'), 404);

        return $next($request);
    }
}
