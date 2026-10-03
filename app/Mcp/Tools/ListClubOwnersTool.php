<?php

namespace App\Mcp\Tools;

use App\Parties\Motions;
use App\Parties\Relations;
use App\Parties\Roles;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Club owners and shares')]
#[Description('A club\'s owners with their shares (percent), the ownership history, and any open ownership proposal with its vote tally. Requires admin access to the club. Only while the relationships layer (PARTIES_ENABLED) is on.')]
class ListClubOwnersTool extends BaseTool
{
    public function schema(JsonSchema $schema): array
    {
        return ['club' => $schema->string()->required()->description('Club numeric id or slug.')];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);
        if ($user instanceof Response) {
            return $user;
        }
        if (! config('parties.enabled')) {
            return Response::error('Ownership records are not switched on.');
        }

        $club = $this->resolveAccessibleClub($user, (string) $request->get('club'));
        if (! $club || ! $this->canAdminClub($user, $club)) {
            return Response::error('Club not found or you do not have admin access to it.');
        }

        $relations = app(Relations::class);
        $motions = app(Motions::class);
        $name = fn ($r) => ($h = $r->holder()) ? ($h->full_name ?: $h->name) : null;
        $motion = $motions->open($club)->first();

        return Response::json([
            'club' => ['id' => $club->id, 'name' => $club->club_name, 'slug' => $club->slug],
            'owners' => $relations->owners($club)->map(fn ($r) => [
                'user_id' => $r->from_id, 'name' => $name($r), 'share_percent' => $r->share_bp / 100, 'since' => $r->starts_at?->toDateString(),
            ])->values(),
            'history' => $relations->history($club)->where('role', Roles::OWNER)->whereNotNull('ends_at')->map(fn ($r) => [
                'user_id' => $r->from_id, 'name' => $name($r), 'share_percent' => $r->share_bp / 100,
                'from' => $r->starts_at?->toDateString(), 'to' => $r->ends_at?->toDateString(), 'reason' => $r->end_reason,
            ])->values(),
            'open_proposal' => $motion ? [
                'uuid' => $motion->uuid,
                'proposed_by' => $motion->proposed_by,
                'reason' => $motion->reason,
                'proposal' => collect($motion->proposal)->map(fn ($p) => ['user_id' => $p['id'], 'share_percent' => $p['share_bp'] / 100]),
                'tally_percent' => array_map(fn ($bp) => $bp / 100, $motions->tally($motion)),
            ] : null,
        ]);
    }
}
