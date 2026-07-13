<?php

namespace App\Mcp\Tools;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Notify member')]
#[Description('Send an in-app notification to a member. The notification is stored AND pushed live over MQTT with an optional deep-link. Only allowed to super-admins or a club-admin of a club the recipient belongs to.')]
class NotifyMemberTool extends BaseTool
{
    protected bool $isWrite = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'member' => $schema->string()->required()->description('Recipient member uuid or numeric id.'),
            'title' => $schema->string()->required()->description('Notification title (short).'),
            'body' => $schema->string()->description('Optional notification body text.'),
            'action_url' => $schema->string()->description('Optional in-app deep-link path (e.g. "/me/payments").'),
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
            return Response::error('Recipient member not found.');
        }

        // Reuse the same access rule as viewing a member: super-admin, guardian,
        // or a club-admin of a club the member belongs to. (Self-notify is a no-op.)
        if (! $this->canViewMember($user, $member)) {
            return Response::error('You are not authorized to notify this member.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'body' => 'nullable|string|max:1000',
            'action_url' => 'nullable|string|max:500',
        ]);

        $notification = UserNotification::notifyUser(
            $member->id,
            'mcp',
            $validated['title'],
            [
                'actor_id' => $user->id,
                'body' => $validated['body'] ?? null,
                'action_url' => $validated['action_url'] ?? null,
                'icon' => 'bi-bell',
            ],
        );

        if (! $notification) {
            return Response::error('Notification was not sent (you cannot notify yourself about your own action).');
        }

        return Response::json([
            'success' => true,
            'message' => 'Notification sent and pushed live.',
            'notification_id' => $notification->id,
            'recipient' => $member->full_name ?? $member->name,
        ]);
    }
}
