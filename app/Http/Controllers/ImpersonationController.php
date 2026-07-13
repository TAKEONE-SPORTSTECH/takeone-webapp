<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    /**
     * Start impersonating a user. Super-admin only (also enforced by the
     * route's `role:super-admin` middleware — checked here as defense in depth).
     */
    public function start(User $user)
    {
        $admin = Auth::user();

        if (! $admin || ! $admin->isSuperAdmin()) {
            abort(403);
        }

        // No nested impersonation — must exit the current one first.
        if (session()->has('impersonate.original_id')) {
            return redirect()->back()->with('error', 'You are already impersonating someone. Exit first.');
        }

        if ($user->id === $admin->id) {
            return redirect()->back()->with('error', 'You cannot impersonate yourself.');
        }

        // Never let an admin step into another administrator's account.
        if ($user->isSuperAdmin()) {
            return redirect()->back()->with('error', 'You cannot impersonate another administrator.');
        }

        Auth::login($user);

        // Remember who to return to, and carry over the admin's verified 2FA
        // state so the impersonated session isn't challenged.
        session(['impersonate.original_id' => $admin->id]);
        session()->put('two_factor.verified', true);

        // Land on the impersonated user's own wall / home feed.
        return redirect()->route('me.home')
            ->with('success', 'You are now viewing the platform as '.($user->full_name ?? $user->name).'.');
    }

    /**
     * Stop impersonating and return to the original admin account.
     */
    public function stop()
    {
        $originalId = session()->pull('impersonate.original_id');

        if (! $originalId) {
            return redirect()->route('clubs.explore');
        }

        $admin = User::find($originalId);

        if (! $admin) {
            Auth::logout();

            return redirect()->route('login');
        }

        Auth::login($admin);
        session()->put('two_factor.verified', true);

        return redirect()->route('admin.platform.index')
            ->with('success', 'Returned to your admin account.');
    }
}
