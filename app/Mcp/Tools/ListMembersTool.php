<?php

namespace App\Mcp\Tools;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('List club members')]
#[Description('List members of a club the acting user can access, with optional name/email/phone search and pagination. Requires access to the club.')]
class ListMembersTool extends BaseTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'club' => $schema->string()->required()
                ->description('Club numeric id or slug.'),
            'search' => $schema->string()
                ->description('Filter by member name, email or phone (partial match).'),
            'page' => $schema->integer()->min(1)->description('1-based page number.'),
            'per_page' => $schema->integer()->min(1)->description('Results per page (server-capped).'),
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

        $perPage = $this->pageSize($request->get('per_page'));
        $page = max(1, (int) ($request->get('page') ?: 1));
        $search = trim((string) $request->get('search', ''));

        $query = $club->members()
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('full_name', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderBy('full_name');

        $total = (clone $query)->count();

        $members = $query->forPage($page, $perPage)->get()
            ->map(fn (User $m) => [
                'id' => $m->id,
                'uuid' => $m->uuid,
                'name' => $m->full_name ?? $m->name,
                'email' => $m->email,
                'phone' => $m->phone,
                'gender' => $m->gender,
                'membership_status' => $m->pivot->status ?? null,
            ])->values();

        return Response::json([
            'club' => ['id' => $club->id, 'name' => $club->club_name, 'slug' => $club->slug],
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'members' => $members,
        ]);
    }
}
