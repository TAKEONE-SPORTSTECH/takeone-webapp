<?php

namespace App\DevBoard\Middleware;

use App\DevBoard\Support\Notebooks;
use Closure;
use Illuminate\Http\Request;

/**
 * Every board route sits behind this. Not-found rather than forbidden: the
 * board is private, and its existence is nobody else's business.
 */
class EnsureBoardAccess
{
    public function handle(Request $request, Closure $next)
    {
        // whose notebook this request works in; every board query is scoped to it (Support\Notebooks)
        // — their own, or a folder somebody shared with them, fenced to it (Notebooks::resolve)
        [$notebook, $fence, $share] = Notebooks::resolve($request->user(), $request);
        Notebooks::set($notebook, $fence, $share);
        app()->terminating(fn () => Notebooks::set(null)); // never carried into whatever runs next in this process
        abort_unless($notebook !== null, 404);

        return $next($request);
    }
}
