<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;

class WallController extends Controller
{
    /**
     * The standalone social "wall" page has been retired. `/u/{slug}` now
     * redirects to the SAFE public profile (people.show) so existing links, QR
     * codes and follow notifications resolve for ANY viewer — the old target
     * (member.show) is family/admin-gated and 404s for a stranger.
     */
    public function show(User $user): RedirectResponse
    {
        return redirect()->route('people.show', $user->uuid);
    }
}
