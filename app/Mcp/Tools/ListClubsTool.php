<?php

namespace App\Mcp\Tools;

use App\Models\Tenant;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('List clubs')]
#[Description('List the clubs (tenants) the acting user can access, with optional name/slug search. Super-admins see every club; other users see clubs they own, administer, or belong to.')]
class ListClubsTool extends BaseTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()
                ->description('Filter by club name or slug (case-insensitive, partial match).'),
            'page' => $schema->integer()->min(1)
                ->description('1-based page number. Defaults to 1.'),
            'per_page' => $schema->integer()->min(1)
                ->description('Results per page. Capped by the server (default 25).'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);

        if ($user instanceof Response) {
            return $user;
        }

        $perPage = $this->pageSize($request->get('per_page'));
        $page = max(1, (int) ($request->get('page') ?: 1));
        $search = trim((string) $request->get('search', ''));

        $query = $this->accessibleClubsQuery($user)
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('club_name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->orderBy('club_name');

        $total = (clone $query)->count();

        $clubs = $query->forPage($page, $perPage)->get()
            ->map(fn (Tenant $c) => [
                'id' => $c->id,
                'name' => $c->club_name,
                'slug' => $c->slug,
                'country' => $c->country,
                'currency' => $c->currency,
                'members_count' => $c->members()->count(),
                'can_admin' => $this->canAdminClub($user, $c),
            ])->values();

        return Response::json([
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'clubs' => $clubs,
        ]);
    }
}
