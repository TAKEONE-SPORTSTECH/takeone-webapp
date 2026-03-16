<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;

class SetCurrentTenant
{
    /**
     * Bind the resolved {club} route parameter into the service container so
     * that TenantScope can automatically scope queries to the current tenant.
     *
     * SubstituteBindings runs before route-group middleware, so {club} is
     * already a Tenant instance by the time this middleware executes.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $club = $request->route('club');

        if ($club instanceof Tenant) {
            app()->instance('current.tenant', $club);
        }

        return $next($request);
    }
}
