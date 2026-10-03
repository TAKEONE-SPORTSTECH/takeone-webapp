<?php

namespace App\Mcp\Tools;

use App\Members\Models\User;
use App\Parties\Motions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Propose a change of club ownership')]
#[Description('Set who owns a club and each share (percent, must total 100). Only an owner (or a super-admin) may propose. The majority owner\'s change applies at once; anyone else\'s goes to a vote of the owners weighted by share — more than 50% passes. Only while PARTIES_ENABLED is on.')]
class ProposeClubOwnershipTool extends BaseTool
{
    protected bool $isWrite = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'club' => $schema->string()->required()->description('Club numeric id or slug.'),
            'owners' => $schema->array()->required()->description('List of {user_id, share_percent}; shares must total exactly 100.'),
            'reason' => $schema->string()->description('Optional reason, kept with the record.'),
        ];
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

        $set = [];
        foreach ((array) $request->get('owners') as $row) {
            $person = isset($row['user_id']) ? User::find((int) $row['user_id']) : null;
            $pct = (float) ($row['share_percent'] ?? 0);
            if (! $person || $pct <= 0 || $pct > 100) {
                return Response::error('Every owner needs an existing user_id and a share_percent between 0 and 100.');
            }
            $set[] = [$person, (int) round($pct * 100)];
        }

        try {
            $motion = app(Motions::class)->propose($club, $user, $set, $request->get('reason') ? (string) $request->get('reason') : null);
        } catch (\DomainException) {
            return Response::error('Only an owner of the club (or a super-admin) may propose a change of ownership.');
        } catch (\InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        }

        return Response::json(['success' => true, 'status' => $motion->status, 'proposal_uuid' => $motion->uuid]);
    }
}
