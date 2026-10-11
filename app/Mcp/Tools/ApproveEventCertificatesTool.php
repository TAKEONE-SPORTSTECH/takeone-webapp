<?php

namespace App\Mcp\Tools;

use Ahl\Certificates\Certificates as Engine;
use Ahl\Certificates\CertificateService;
use App\Certificates\Support\CertificateRealtime;
use App\Events\Support\EventCertificates;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Approve event certificates')]
#[Description('For an event you manage: approve draft certificates — the uuids given, or the next batch of drafts when none are given. Up to 15 per call (each approval tells the holder; the PDF is drawn and stored the first time it is opened); the answer says how many drafts remain, so call again until none do.')]
class ApproveEventCertificatesTool extends BaseTool
{
    protected bool $isWrite = true;

    public const BATCH = 15;

    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->required()->description('The event\'s uuid.'),
            'uuids' => $schema->array()->items($schema->string())->description('Draft certificate uuids (max 15). Omit to approve the next batch.'),
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

        $v = $request->validate(['event' => 'required|uuid', 'uuids' => 'sometimes|array|max:'.self::BATCH, 'uuids.*' => 'uuid']);
        $provider = app(EventCertificates::class);
        $event = $provider->find($v['event']);
        if (! $event || ! $provider->canManage($event, $user)) {
            return Response::error('Event not found.');
        }

        $service = app(CertificateService::class);
        $approved = 0;
        $drafts = Engine::model()::query()->forContext($event)->where('status', 'draft')->orderBy('id')
            ->when(! empty($v['uuids']), fn ($q) => $q->whereIn('uuid', $v['uuids']))
            ->limit(self::BATCH)->get();
        foreach ($drafts as $c) {
            if (Gate::forUser($user)->allows('approve', $c)) {
                $service->approve($c, $user);
                CertificateRealtime::refresh([$c->recipient_id]);
                $approved++;
            }
        }
        CertificateRealtime::refresh($provider->audience($event), $user->id);

        return Response::json([
            'event' => $event->uuid,
            'approved' => $approved,
            'remaining' => Engine::model()::query()->forContext($event)->where('status', 'draft')->count(),
        ]);
    }
}
