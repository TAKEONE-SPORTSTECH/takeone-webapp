<?php

namespace App\Clubs\Middleware;

use App\Clubs\Models\Business;
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
        $user = Auth::user();
        $business = $user?->ownedBusiness;

        // A super-admin may LOOK at any approved business (?b={slug}), read
        // only: this applies to GET requests alone, so every write in this
        // area still acts on the business the user actually owns.
        if ($user?->isSuperAdmin() && $request->isMethod('GET')) {
            $picked = $request->query('b');
            if (is_string($picked) && $picked !== '') {
                $business = Business::approved()->where('slug', $picked)->first();
                if (! $business) {
                    return redirect()->route('business.home');
                }
            } elseif (! $business || ! $business->isApproved()) {
                return redirect()->route('business.home');
            }
        }

        if (! $business || ! $business->isApproved()) {
            return redirect()->route('business.setup');
        }

        // Make the resolved business available to controllers without re-querying.
        $request->attributes->set('business', $business);

        return $next($request);
    }
}
