<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();
        $tenantId = $this->resolveTenantId($request);

        // Check if user has the required permission
        if ($user->hasPermission($permission, $tenantId)) {
            return $next($request);
        }

        // If user doesn't have required permission, abort with 403
        abort(403, 'Unauthorized action.');
    }

    /**
     * Resolve the tenant id from the route, handling a bound Tenant model,
     * a numeric id, or a slug param. Returns null when the route is not
     * tenant-scoped.
     *
     * NOTE: this replaces a precedence bug — `$a ?? $b ? c : d` parses as
     * `($a ?? $b) ? c : d`, which always looked the tenant up by slug and
     * returned null under a {club}/{tenant} model binding.
     */
    private function resolveTenantId(Request $request): ?int
    {
        $param = $request->route('tenant') ?? $request->route('club');

        if ($param instanceof \App\Models\Tenant) {
            return $param->id;
        }
        if (is_numeric($param)) {
            return (int) $param;
        }
        if ($slug = $request->route('slug')) {
            return \App\Models\Tenant::where('slug', $slug)->value('id');
        }

        return null;
    }
}
