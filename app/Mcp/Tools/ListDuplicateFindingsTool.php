<?php

namespace App\Mcp\Tools;

use App\Duplicates\Models\DuplicateFinding;
use App\Duplicates\Support\FindingPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('List duplicate findings')]
#[Description('What the duplicate watch found: people, clubs, typed event clubs and spare entries that may exist twice, with why and how sure (certain = the watch may fix it itself; likely = a person decides). Super-admin only — a finding names records across every club.')]
class ListDuplicateFindingsTool extends BaseTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(['open', 'fixed', 'dismissed', 'gone'])->description('Default: open.'),
            'kind' => $schema->string()->enum(['person', 'club', 'event_club', 'entry'])->description('Only this kind.'),
            'limit' => $schema->integer()->description('1–200, default 50.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);
        if ($user instanceof Response) {
            return $user;
        }
        if (! $user->isSuperAdmin()) {
            return Response::error('Only a platform super-admin may read duplicate findings.');
        }

        $v = $request->validate([
            'status' => 'nullable|in:open,fixed,dismissed,gone',
            'kind' => 'nullable|in:person,club,event_club,entry',
            'limit' => 'nullable|integer|min:1|max:200',
        ]);

        $rows = DuplicateFinding::where('status', $v['status'] ?? 'open')
            ->when($v['kind'] ?? null, fn ($q, $k) => $q->where('kind', $k))
            ->orderByRaw("case strength when 'certain' then 0 else 1 end")->orderBy('id')
            ->limit((int) ($v['limit'] ?? 50))->get();
        $presenter = app(FindingPresenter::class);

        return Response::json([
            'success' => true,
            'count' => $rows->count(),
            'findings' => $rows->map(fn (DuplicateFinding $f) => $presenter->present($f))->values(),
        ]);
    }
}
