<?php

namespace App\Mcp\Tools;

use App\Duplicates\Models\DuplicateFinding;
use App\Duplicates\Support\DuplicateFixer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Resolve duplicate finding')]
#[Description('Decide one duplicate finding: "dismiss" (not a duplicate — a person pair is recorded as two different people and never flagged again) or "fix" (a CERTAIN finding is fixed exactly as the watch would; a LIKELY typed event club is linked to the one platform club named). Likely people are merged on the members screen, not here. Super-admin only.')]
class ResolveDuplicateFindingTool extends BaseTool
{
    protected bool $isWrite = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'finding' => $schema->string()->required()->description('Finding id (uuid) from list_duplicate_findings.'),
            'action' => $schema->string()->enum(['dismiss', 'fix'])->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);
        if ($user instanceof Response) {
            return $user;
        }
        if (! $user->isSuperAdmin()) {
            return Response::error('Only a platform super-admin may resolve duplicate findings.');
        }

        $v = $request->validate([
            'finding' => 'required|uuid',
            'action' => 'required|in:dismiss,fix',
        ]);
        $f = DuplicateFinding::where('uuid', $v['finding'])->first();
        if (! $f || ! $f->isOpen()) {
            return Response::error('No open finding with that id.');
        }

        if ($v['action'] === 'dismiss') {
            app(DuplicateFixer::class)->dismiss($f, $user);

            return Response::json(['success' => true, 'message' => 'Dismissed — it will not be flagged again.', 'status' => $f->status]);
        }

        $r = app(DuplicateFixer::class)->fix($f, $user, confirmedByPerson: true);

        return $r['ok']
            ? Response::json(['success' => true, 'message' => 'Fixed ('.$r['message'].').', 'status' => $f->fresh()->status, 'fix' => $f->fresh()->fix])
            : Response::error('Not fixed: '.$r['message']);
    }
}
