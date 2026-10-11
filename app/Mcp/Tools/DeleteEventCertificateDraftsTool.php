<?php

namespace App\Mcp\Tools;

use Ahl\Certificates\Certificates as Engine;
use Ahl\Certificates\CertificateService;
use App\Certificates\Support\CertificateRealtime;
use App\Events\Support\EventCertificates;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Delete event certificate drafts')]
#[Description('For an event you manage: delete draft certificates — the uuids given, or every draft when none are given (200 per call; the answer says how many remain). Only drafts: an issued certificate is revoked, never deleted. Generating again re-drafts whatever is still earned.')]
class DeleteEventCertificateDraftsTool extends BaseTool
{
    protected bool $isWrite = true;

    public const BATCH = 200;

    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->required()->description('The event\'s uuid.'),
            'uuids' => $schema->array()->items($schema->string())->description('Draft certificate uuids (max 200). Omit to delete every draft.'),
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
        $deleted = 0;
        $drafts = Engine::model()::query()->forContext($event)->where('status', 'draft')->orderBy('id')
            ->when(! empty($v['uuids']), fn ($q) => $q->whereIn('uuid', $v['uuids']))
            ->limit(self::BATCH)->get();
        foreach ($drafts as $c) {
            if (Gate::forUser($user)->allows('delete', $c)) {
                $service->delete($c);
                $deleted++;
            }
        }
        Log::info('certificates.drafts_deleted', ['event' => $event->uuid, 'by' => $user->id, 'count' => $deleted, 'via' => 'mcp']);
        CertificateRealtime::refresh($provider->audience($event), $user->id);

        return Response::json([
            'event' => $event->uuid,
            'deleted' => $deleted,
            'remaining' => Engine::model()::query()->forContext($event)->where('status', 'draft')->count(),
        ]);
    }
}
