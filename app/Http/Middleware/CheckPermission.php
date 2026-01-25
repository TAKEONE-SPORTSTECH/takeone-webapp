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
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();
        $tenantId = $request->route('tenant') ?? $request->route('slug')
            ? \App\Models\Tenant::where('slug', $request->route('slug'))->value('id')
            : null;

        // Check if user has the required permission
        if ($user->hasPermission($permission, $tenantId)) {
            return $next($request);
        }

        // If user doesn't have required permission, abort with 403
        abort(403, 'Unauthorized action.');
    }
}
