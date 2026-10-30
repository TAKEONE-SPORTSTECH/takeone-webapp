<?php

namespace App\DevBoard\Middleware;

use App\DevBoard\Support\Notebooks;
use Closure;
use Illuminate\Http\Request;

/**
 * TakeNote's stand-in for the platform's `verified` middleware — and ONLY
 * TakeNote's (the dev board's member routes, routes-takenote.php).
 *
 * A friend the owner handed an invite link to may write notes before they have
 * confirmed their email (owner, 28 Oct 2026: "let them register straight away
 * with the link"): the link itself, from the owner's own hand, is the proof.
 * For everybody else this is the platform's `verified` middleware, untouched —
 * it is resolved and called as it is.
 *
 * Nothing else on the platform is opened by this: every other route still has
 * the platform's own `verified` in its stack.
 */
class VerifiedOrLinkFriend
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && ! $user->hasVerifiedEmail() && Notebooks::joinedByLink($user)) {
            return $next($request);
        }

        $verified = app('router')->getMiddleware()['verified'] ?? \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class;

        return app($verified)->handle($request, $next);
    }
}
