<?php

namespace App\Mcp\Tools;

use App\Models\Tenant;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Who am I')]
#[Description('Return the identity, roles and accessible clubs of the user this MCP session is acting as. Call this first to learn what the session can see and do.')]
class WhoAmITool extends BaseTool
{
    public function handle(Request $request): Response
    {
        $user = $this->guard($request);

        if ($user instanceof Response) {
            return $user;
        }

        $roles = $user->roles()->get()->map(fn ($role) => [
            'role' => $role->slug,
            'name' => $role->name,
            'tenant_id' => $role->pivot->tenant_id ? (int) $role->pivot->tenant_id : null,
        ])->values();

        $clubIds = $this->accessibleClubIds($user);
        $clubs = Tenant::query()
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->whereIn('id', $clubIds))
            ->get(['id', 'club_name', 'slug'])
            ->map(fn (Tenant $c) => [
                'id' => $c->id,
                'name' => $c->club_name,
                'slug' => $c->slug,
                'can_admin' => $this->canAdminClub($user, $c),
            ])->values();

        return Response::json([
            'id' => $user->id,
            'uuid' => $user->uuid,
            'name' => $user->full_name ?? $user->name,
            'email' => $user->email,
            'is_super_admin' => $user->isSuperAdmin(),
            'roles' => $roles,
            'accessible_clubs_count' => $clubs->count(),
            'accessible_clubs' => $clubs,
            'transport_note' => $request->user() ? 'http (sanctum token)' : 'stdio (configured operator)',
        ]);
    }
}
