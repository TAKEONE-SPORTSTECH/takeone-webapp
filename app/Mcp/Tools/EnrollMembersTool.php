<?php

namespace App\Mcp\Tools;

use App\Models\ClubPackage;
use App\Models\Membership;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Batch-enroll members into a package')]
#[Description('Enroll one or more existing, active club members into a package in one call, marking them as already paid (no amount due). Intended for backfilling members who joined before the club used this system. Requires admin access (owner / club-admin / super-admin). Returns which members were enrolled and which were skipped, with a reason.')]
class EnrollMembersTool extends BaseTool
{
    protected bool $isWrite = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'club' => $schema->string()->required()->description('Club numeric id or slug.'),
            'member_ids' => $schema->array()->items($schema->integer())->required()
                ->description('Numeric user ids of the members to enroll. Each must already be an active member of this club.'),
            'package_id' => $schema->integer()->required()
                ->description('Numeric id of the active package to enroll them into.'),
            'start_date' => $schema->string()
                ->description('Optional start date (YYYY-MM-DD) to back-date the enrollment, e.g. when the member actually joined. Defaults to today.'),
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
            return Response::error('Only club admins, owners, or super-admins can batch-enroll members.');
        }

        $validated = $request->validate([
            'member_ids' => 'required|array|min:1',
            'member_ids.*' => 'integer|exists:users,id',
            'package_id' => 'required|integer',
            'start_date' => 'nullable|date',
        ]);

        $package = ClubPackage::where('tenant_id', $club->id)
            ->where('id', $validated['package_id'])
            ->where('is_active', true)
            ->first();

        if (! $package) {
            return Response::error('Package not found or is not active for this club.');
        }

        $startDate = ! empty($validated['start_date']) ? \Carbon\Carbon::parse($validated['start_date']) : null;

        $activeMemberIds = Membership::where('tenant_id', $club->id)
            ->where('status', 'active')
            ->whereIn('user_id', $validated['member_ids'])
            ->pluck('user_id')
            ->all();

        $subscriptions = app(SubscriptionService::class);
        $enrolledIds = [];
        $skipped = [];

        foreach ($validated['member_ids'] as $userId) {
            $member = User::find($userId);
            if (! $member) {
                $skipped[] = ['user_id' => $userId, 'name' => null, 'reason' => 'Member not found.'];

                continue;
            }

            if (! in_array($userId, $activeMemberIds, true)) {
                $skipped[] = ['user_id' => $userId, 'name' => $member->full_name, 'reason' => 'Not an active member of this club.'];

                continue;
            }

            if ($subscriptions->isDuplicate($club->id, $userId, $package->id)) {
                $skipped[] = ['user_id' => $userId, 'name' => $member->full_name, 'reason' => 'Already enrolled in this package.'];

                continue;
            }

            $error = $subscriptions->checkEligibility($package, $member->full_name, $member->age, $member->gender);
            if ($error) {
                $skipped[] = ['user_id' => $userId, 'name' => $member->full_name, 'reason' => $error];

                continue;
            }

            $subscriptions->createActive(
                $club,
                $userId,
                $package,
                "Batch enrollment (MCP): {$member->full_name} — {$package->name}",
                $startDate
            );

            $enrolledIds[] = $userId;
        }

        return Response::json([
            'success' => true,
            'message' => count($enrolledIds).' member(s) enrolled in '.$package->name.'.',
            'enrolled_ids' => $enrolledIds,
            'enrolled_count' => count($enrolledIds),
            'skipped' => $skipped,
            'package' => ['id' => $package->id, 'name' => $package->name],
        ]);
    }
}
