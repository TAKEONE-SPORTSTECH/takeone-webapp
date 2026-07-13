<?php

namespace App\Mcp\Tools;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Get member')]
#[Description('Fetch a single member profile by uuid or numeric id. Authorization mirrors the app: allowed only for super-admins, the member themselves, a confirmed guardian, or a club-admin of a club the member belongs to.')]
class GetMemberTool extends BaseTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'member' => $schema->string()->required()
                ->description('Member uuid (preferred) or numeric id.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);

        if ($user instanceof Response) {
            return $user;
        }

        $ref = (string) $request->get('member');

        $member = User::query()
            ->where('uuid', $ref)
            ->orWhere('id', is_numeric($ref) ? (int) $ref : 0)
            ->first();

        if (! $member) {
            return Response::error('Member not found.');
        }

        if (! $this->canViewMember($user, $member)) {
            return Response::error('You are not authorized to view this member.');
        }

        return Response::json([
            'id' => $member->id,
            'uuid' => $member->uuid,
            'slug' => $member->slug,
            'name' => $member->full_name ?? $member->name,
            'email' => $member->email,
            'phone' => $member->phone,
            'gender' => $member->gender,
            'birthdate' => optional($member->birthdate)->toDateString(),
            'age' => $member->age,
            'blood_type' => $member->blood_type,
            'marital_status' => $member->marital_status,
            'motto' => $member->motto,
            'is_personal_trainer' => (bool) $member->is_personal_trainer,
            'clubs' => $member->memberClubs()->get(['tenants.id', 'club_name', 'slug'])
                ->map(fn ($c) => [
                    'club_id' => $c->id,
                    'club_name' => $c->club_name,
                    'slug' => $c->slug,
                    'status' => $c->pivot->status ?? null,
                ])->values(),
            'active_subscriptions' => $member->subscriptions()->where('status', 'active')->count(),
        ]);
    }
}
