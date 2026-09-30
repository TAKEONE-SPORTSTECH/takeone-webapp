<?php

namespace App\Mcp\Tools;

use App\Events\Models\EventFeatureRequest;
use App\Events\Support\EventFeaturing;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Decide an event featuring request')]
#[Description('Super-admin only. Approve a pending featuring request (the event becomes the spotlight on the members\' Events page until it ends), reject it, or revoke an approved one. Reject and revoke need a reason, which is sent to the organiser. Bound by the request\'s uuid (from list_event_feature_requests).')]
class DecideEventFeatureRequestTool extends BaseTool
{
    protected bool $isWrite = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'uuid' => $schema->string()->required()->description('The featuring request\'s uuid.'),
            'decision' => $schema->string()->enum(['approve', 'reject', 'revoke'])->required(),
            'reason' => $schema->string()->description('Required for reject and revoke; shown to the organiser.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);
        if ($user instanceof Response) {
            return $user;
        }
        if (! $user->hasRole('super-admin')) {
            return Response::error('Only a super-admin can decide featuring requests.');
        }

        $v = $request->validate([
            'uuid' => 'required|uuid',
            'decision' => 'required|in:approve,reject,revoke',
            'reason' => 'required_unless:decision,approve|nullable|string|max:500',
        ]);

        $r = EventFeatureRequest::where('uuid', $v['uuid'])->first();
        if (! $r) {
            return Response::error('Request not found.');
        }

        $out = match ($v['decision']) {
            'approve' => EventFeaturing::approve($user, $r),
            'reject' => EventFeaturing::reject($user, $r, (string) $v['reason']),
            'revoke' => EventFeaturing::revoke($user, $r, (string) $v['reason']),
        };

        return $out['ok']
            ? Response::json(['message' => $out['message'], 'request' => EventFeaturing::row($r->fresh())])
            : Response::error($out['message']);
    }
}
