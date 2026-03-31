<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequiresTwoFactor
{
    /**
     * If the authenticated user has 2FA enabled, check that they have
     * already passed the 2FA challenge this session.
     *
     * The challenge flag 'two_factor.verified' is set in the session by
     * TwoFactorController::verifyChallenge() after a successful code check.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->hasTwoFactorEnabled() && !$request->session()->get('two_factor.verified')) {
            // Store intended destination so we can redirect after challenge.
            if (!$request->expectsJson()) {
                $request->session()->put('url.intended', $request->url());
            }

            Auth::logout();

            return redirect()->route('login')
                ->with('error', 'Please log in and complete two-factor authentication.');
        }

        return $next($request);
    }
}
