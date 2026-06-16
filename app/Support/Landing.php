<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Resolves where to send a user on entry (root visit / after login).
 * Mobile users land inside the mobile app shell; desktop keeps Explore.
 */
class Landing
{
    public static function url(Request $request): string
    {
        $user = Auth::user();
        if (!$user) {
            return route('login');
        }

        if ($request->attributes->get('is_mobile')) {
            // Respect the last chosen view; default to the personal shell.
            if (session('view_mode') === 'business' && $user->hasApprovedBusiness()) {
                return route('business.dashboard');
            }
            return route('me.home');
        }

        return route('clubs.explore');
    }
}
