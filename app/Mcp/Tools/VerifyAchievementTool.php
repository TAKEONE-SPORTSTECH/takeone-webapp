<?php

namespace App\Mcp\Tools;

use App\Models\TournamentEvent;
use App\Services\AchievementVerificationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Verify member record')]
#[Description('Confirm or reject a member self-claimed record that names your club — a tournament medal (type "achievement") or an acquired skill (type "skill"). Authorization mirrors the app: only an admin/owner (or super-admin) of the club the record names may act. Bound by the record\'s public uuid. Confirming marks it verified on the member\'s profile; rejecting records it as not verified.')]
class VerifyAchievementTool extends BaseTool
{
    protected bool $isWrite = true;

    /** @var array<string,class-string> */
    private const TYPES = ['achievement' => TournamentEvent::class, 'skill' => \App\Models\SkillAcquisition::class];

    public function schema(JsonSchema $schema): array
    {
        return [
            'uuid' => $schema->string()->required()
                ->description('The record\'s public uuid (from get_member medals/skills or the member profile).'),
            'type' => $schema->string()->enum(['achievement', 'skill'])
                ->description('What the uuid refers to: "achievement" (tournament medal, default) or "skill".'),
            'decision' => $schema->string()->enum(['confirm', 'reject'])->required()
                ->description('"confirm" to verify the record, "reject" to mark it not verified.'),
            'note' => $schema->string()
                ->description('Optional reason, shared with the member (used on reject).'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);

        if ($user instanceof Response) {
            return $user;
        }

        $service = app(AchievementVerificationService::class);

        $validated = $request->validate([
            'uuid' => 'required|string',
            'type' => 'nullable|in:achievement,skill',
            'decision' => 'required|in:confirm,reject',
            'note' => 'nullable|string|max:500',
        ]);

        $class = self::TYPES[$validated['type'] ?? 'achievement'];
        $claim = $class::where('uuid', $validated['uuid'])
            ->with('clubAffiliation.tenant')
            ->first();

        if (! $claim) {
            return Response::error('Record not found.');
        }

        $tenant = $claim->attestingTenant();
        if (! $tenant || ! $this->canAdminClub($user, $tenant)) {
            // Same message whether the record is missing a club or the user simply
            // can't admin it — don't leak which records name which clubs.
            return Response::error('You are not authorized to verify this record.');
        }

        try {
            if ($validated['decision'] === 'confirm') {
                $service->clubConfirm($claim, $user);
            } else {
                $service->clubReject($claim, $user, $validated['note'] ?? null);
            }
        } catch (AuthorizationException $e) {
            return Response::error($e->getMessage());
        }

        return Response::json([
            'uuid' => $claim->uuid,
            'verification_status' => $claim->verification_status,
            'verification_method' => $claim->verification_method,
            'member_id' => $claim->user_id,
        ]);
    }
}
