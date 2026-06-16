<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;

/**
 * Drop-in replacement for Laravel's `verified` middleware that skips the
 * email-verification gate while a super-admin is impersonating a user.
 *
 * Imported / unverified accounts would otherwise bounce the impersonator to
 * the verification screen on every protected route. The admin has already
 * authenticated, so it's safe to bypass verification for the duration of the
 * impersonation session only.
 */
class EnsureEmailIsVerifiedOrImpersonating extends EnsureEmailIsVerified
{
    public function handle($request, Closure $next, $redirectToRoute = null)
    {
        if ($request->session()->has('impersonate.original_id')) {
            return $next($request);
        }

        return parent::handle($request, $next, $redirectToRoute);
    }
}
