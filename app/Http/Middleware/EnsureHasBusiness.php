<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the Business (chain) area: only users who own an APPROVED business
 * may enter. Everyone else is bounced to the business setup/creation page.
 */
class EnsureHasBusiness
{
    public function handle(Request $request, Closure $next): Response
    {
        $business = Auth::user()?->ownedBusiness;

        if (!$business || !$business->isApproved()) {
            return redirect()->route('business.setup');
        }

        // Make the resolved business available to controllers without re-querying.
        $request->attributes->set('business', $business);

        return $next($request);
    }
}
