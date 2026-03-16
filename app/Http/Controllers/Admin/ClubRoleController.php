<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Models\ClubMemberSubscription;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Traits\HandlesClubAuthorization;

class ClubRoleController extends Controller
{
    use HandlesClubAuthorization;

    public function roles(Tenant $club)
    {
        $this->authorizeClub($club);
        $clubId  = $club->id;

        $members = ClubMemberSubscription::where('tenant_id', $clubId)
            ->with('user')
            ->get()
            ->unique('user_id');

        $availableRoles = Role::whereIn('slug', ['club-admin', 'instructor', 'staff', 'moderator'])->get();

        if ($availableRoles->isEmpty()) {
            $availableRoles = collect([
                (object) ['slug' => 'club-admin', 'name' => 'Club Admin',  'description' => 'Full access to all club settings, members, and financials'],
                (object) ['slug' => 'instructor',  'name' => 'Instructor', 'description' => 'Can manage activities, view members, and track attendance'],
                (object) ['slug' => 'staff',       'name' => 'Staff',      'description' => 'Limited access to member check-in and basic operations'],
            ]);
        }

        return view('admin.club.roles.index', compact('club', 'members', 'availableRoles'));
    }

    public function storeRole(RoleRequest $request, Tenant $club)
    {
        $this->authorizeClub($club);

        $user = User::findOrFail($request->user_id);
        $user->assignRole($request->role, $club->id);

        return back()->with('success', 'Role assigned successfully.');
    }

    public function destroyRole(RoleRequest $request, Tenant $club)
    {
        $this->authorizeClub($club);

        $user = User::findOrFail($request->user_id);
        $user->removeRole($request->role, $club->id);

        return back()->with('success', 'Role removed successfully.');
    }
}
