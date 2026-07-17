<?php

namespace App\Mcp\Tools;

use App\Models\ClubMemberSubscription;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Search people')]
#[Description('Search for discoverable members who share a club-owner-confirmed membership with the acting user (never platform-wide — mirrors the web "Find People" scoping). Returns only SAFE public fields (name, slug, uuid) of members who have not opted out of discovery — never health, billing, contacts or family data.')]
class SearchPeopleTool extends BaseTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required()
                ->description('Name, email or phone fragment to search for (min 2 characters).'),
            'limit' => $schema->integer()->min(1)
                ->description('Max results (server-capped).'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);

        if ($user instanceof Response) {
            return $user;
        }

        $q = trim((string) $request->get('query', ''));

        if (mb_strlen($q) < 2) {
            return Response::error('Search query must be at least 2 characters.');
        }

        // Club-scoped, same as the web feature: until a club actually confirms the
        // acting user's own membership, there is no club-mate pool to search.
        $clubMateIds = ClubMemberSubscription::confirmedClubMateIds($user->id);

        if ($clubMateIds->isEmpty()) {
            return Response::json(['query' => $q, 'count' => 0, 'people' => []]);
        }

        $limit = $this->pageSize($request->get('limit'));

        $people = User::query()
            ->where('is_discoverable', true)
            ->whereIn('id', $clubMateIds)
            ->where('id', '!=', $user->id)
            ->where(function ($sub) use ($q) {
                $sub->where('full_name', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            })
            ->orderBy('full_name')
            ->limit($limit)
            ->get(['id', 'uuid', 'slug', 'full_name', 'name', 'gender'])
            ->map(fn (User $u) => [
                'uuid' => $u->uuid,
                'slug' => $u->slug,
                'name' => $u->full_name ?? $u->name,
                'gender' => $u->gender,
                'public_profile_path' => "/people/{$u->uuid}",
            ])->values();

        return Response::json([
            'query' => $q,
            'count' => $people->count(),
            'people' => $people,
        ]);
    }
}
