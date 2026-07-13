<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Get club')]
#[Description('Fetch full details for a single club (tenant) by numeric id or slug, including counts of members, packages, activities, instructors and events. Returns an error if the club does not exist or the user cannot access it.')]
class GetClubTool extends BaseTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'club' => $schema->string()->required()
                ->description('Club numeric id or slug (e.g. "12" or "demo-taekwondo-club").'),
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

        return Response::json([
            'id' => $club->id,
            'name' => $club->club_name,
            'slug' => $club->slug,
            'slogan' => $club->slogan,
            'description' => $club->description,
            'email' => $club->email,
            'phone' => $club->phone,
            'country' => $club->country,
            'address' => $club->address,
            'currency' => $club->currency,
            'timezone' => $club->timezone,
            'registration_fee' => $club->registration_fee,
            'owner' => $club->owner ? [
                'id' => $club->owner->id,
                'name' => $club->owner->full_name ?? $club->owner->name,
            ] : null,
            'counts' => [
                'members' => $club->members()->count(),
                'packages' => $club->packages()->count(),
                'activities' => $club->activities()->count(),
                'instructors' => $club->instructors()->count(),
                'events' => $club->events()->count(),
                'active_subscriptions' => $club->subscriptions()->where('status', 'active')->count(),
            ],
            'can_admin' => $this->canAdminClub($user, $club),
        ]);
    }
}
