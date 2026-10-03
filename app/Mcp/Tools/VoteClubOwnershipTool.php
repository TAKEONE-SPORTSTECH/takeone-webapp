<?php

namespace App\Mcp\Tools;

use App\Parties\Kinds;
use App\Parties\Models\PartyMotion;
use App\Parties\Motions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Vote on a club ownership proposal')]
#[Description('Vote for or against an open ownership proposal of a club. Only the club\'s owners may vote; their vote weighs their share. More than 50% for passes it; 50% or more against rejects it. Only while PARTIES_ENABLED is on.')]
class VoteClubOwnershipTool extends BaseTool
{
    protected bool $isWrite = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'club' => $schema->string()->required()->description('Club numeric id or slug.'),
            'proposal_uuid' => $schema->string()->required()->description('The open proposal\'s uuid (from list_club_owners).'),
            'approve' => $schema->boolean()->required()->description('true = for, false = against.'),
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
        $motion = PartyMotion::where('uuid', (string) $request->get('proposal_uuid'))->first();
        if (! $club || ! $motion || $motion->to_type !== Kinds::CLUB || (int) $motion->to_id !== (int) $club->id) {
            return Response::error('Proposal not found.');
        }

        try {
            $motion = app(Motions::class)->vote($motion, $user, (bool) $request->get('approve'));
        } catch (\DomainException $e) {
            return Response::error($e->getMessage());
        }

        return Response::json(['success' => true, 'status' => $motion->status, 'tally_percent' => array_map(fn ($bp) => $bp / 100, app(Motions::class)->tally($motion))]);
    }
}
