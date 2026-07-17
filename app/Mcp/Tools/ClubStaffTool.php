<?php

namespace App\Mcp\Tools;

use App\Models\ClubInstructor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('List club staff')]
#[Description('List a club\'s staff (instructors, secretaries, operators, cleaners, etc.) with their staff type, compensation, and active status. Admin access to the club required (owner / club-admin / super-admin). Read-only — hiring/terminating staff is not exposed over MCP.')]
class ClubStaffTool extends BaseTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'club' => $schema->string()->required()->description('Club numeric id or slug.'),
            'include_inactive' => $schema->boolean()
                ->description('Include terminated/inactive staff. Defaults to false (active roster only).'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);

        if ($user instanceof Response) {
            return $user;
        }

        $club = $this->resolveAccessibleClub($user, (string) $request->get('club'));

        if (! $club) {
            return Response::error('Club not found or you do not have access to it.');
        }

        if (! $this->canAdminClub($user, $club)) {
            return Response::error('Staff details are only available to club admins, owners, or super-admins.');
        }

        $query = ClubInstructor::where('tenant_id', $club->id)->with('user');

        if (! $request->get('include_inactive')) {
            $query->where('is_active', true);
        }

        $staff = $query->orderBy('sort_order')->orderBy('id')->get()->map(function (ClubInstructor $s) {
            return [
                'id' => $s->id,
                'name' => $s->user->full_name ?? $s->user->name ?? null,
                'user_id' => $s->user_id,
                'staff_type' => $s->staff_type,
                'role' => $s->role,
                'is_active' => (bool) $s->is_active,
                'compensation_type' => $s->compensation_type,
                'wage_amount' => $s->wage_amount !== null ? (float) $s->wage_amount : null,
                'wage_period' => $s->wage_period,
                'monthly_wage_cost' => $s->monthlyWageCost(),
            ];
        });

        return Response::json([
            'club' => ['id' => $club->id, 'name' => $club->club_name, 'slug' => $club->slug],
            'currency' => $club->currency,
            'staff' => $staff,
            'count' => $staff->count(),
        ]);
    }
}
