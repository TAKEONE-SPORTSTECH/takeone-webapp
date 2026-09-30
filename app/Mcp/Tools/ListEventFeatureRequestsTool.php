<?php

namespace App\Mcp\Tools;

use App\Events\Models\EventFeatureRequest;
use App\Events\Support\EventFeaturing;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('List event featuring requests')]
#[Description('Super-admin only. The paid requests to put an event in the spotlight on the members\' Events page: pending (waiting for a decision), approved (featured), rejected or revoked. Also returns the current flat featuring fee (null = featuring closed). Newest first, up to 100.')]
class ListEventFeatureRequestsTool extends BaseTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(['pending', 'approved', 'rejected', 'revoked'])
                ->description('Only requests in this state. Omit for all.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);
        if ($user instanceof Response) {
            return $user;
        }
        if (! $user->hasRole('super-admin')) {
            return Response::error('Only a super-admin can review featuring requests.');
        }

        $v = $request->validate(['status' => 'nullable|in:pending,approved,rejected,revoked']);

        $rows = EventFeatureRequest::with(['requester:id,name,full_name', 'decider:id,name,full_name', 'clubEvent.tenant:id,club_name'])
            ->when($v['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->latest('id')->limit(100)->get()
            ->map(fn (EventFeatureRequest $r) => EventFeaturing::row($r))->values()->all();

        return Response::json(['fee' => EventFeaturing::fee(), 'requests' => $rows]);
    }
}
