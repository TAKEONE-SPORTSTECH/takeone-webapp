<?php

namespace App\Traits;

use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;

trait HandlesClubAuthorization
{
    private function authorizeClub(Tenant $club): void
    {
        $user = Auth::user();
        if ($user->isSuperAdmin()) return;
        if ($club->owner_user_id === $user->id) return;
        if ($user->isClubAdmin($club->id)) return;
        abort(403, 'Unauthorized access to this club.');
    }
}
