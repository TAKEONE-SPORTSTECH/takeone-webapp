<?php

namespace App\Mcp\Tools;

use Ahl\Certificates\Certificates as Engine;
use App\Certificates\Support\CertificatePresenter;
use App\Events\Support\EventCertificates;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('List event certificates')]
#[Description('For an event you manage: its certificate settings (participation / podium, when drafts are made), how many certificates it would issue now, how many divisions are still running, and its certificates (drafts, issued, revoked) with holder, number and what each is for. Certificates are addressed by uuid.')]
class ListEventCertificatesTool extends BaseTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->required()->description('The event\'s uuid.'),
            'status' => $schema->string()->enum(['draft', 'approved', 'revoked'])->description('Only certificates in this state.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);
        if ($user instanceof Response) {
            return $user;
        }
        if (! config('certificates.enabled')) {
            return Response::error('Certificates are not switched on.');
        }

        $v = $request->validate(['event' => 'required|uuid', 'status' => 'nullable|in:draft,approved,revoked']);
        $provider = app(EventCertificates::class);
        $event = $provider->find($v['event']);
        if (! $event || ! $provider->canManage($event, $user)) {
            return Response::error('Event not found.');   // one answer for missing and not-yours
        }

        $presenter = app(CertificatePresenter::class);
        $rows = Engine::model()::query()->forContext($event)->with(['recipient', 'context'])
            ->when($v['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')->limit(500)->get()
            ->map(fn ($c) => $presenter->managed($c, 'en'))->values();

        return Response::json([
            'event' => $event->uuid,
            'settings' => $provider->settings($event),
            'eligible' => $provider->eligible($event),
            'certificates' => $rows,
        ]);
    }
}
