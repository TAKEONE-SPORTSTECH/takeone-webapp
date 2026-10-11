<?php

namespace App\Mcp\Tools;

use App\Certificates\Support\CertificateBundles;
use App\Events\Support\EventCertificates;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Event certificates as one PDF')]
#[Description('For an event you manage: every issued certificate of one design (participation or podium) as ONE PDF, a page each — the file a print shop is sent. With build=true it starts building in the background (or finds it built); the answer says idle / building (done of total) / ready, with the size and the address the event console downloads it from once ready. One file per design, because a PDF has one page shape.')]
class EventCertificateBundleTool extends BaseTool
{
    protected bool $isWrite = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->required()->description('The event\'s uuid.'),
            'type' => $schema->string()->required()->description('participation or podium.'),
            'build' => $schema->boolean()->description('Start building it if it is not built or building yet.'),
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
        $v = $request->validate(['event' => 'required|uuid', 'type' => 'required|string|max:32', 'build' => 'sometimes|boolean']);
        $provider = app(EventCertificates::class);
        $event = $provider->find($v['event']);
        if (! $event || ! $provider->canManage($event, $user)) {
            return Response::error('Event not found.');
        }
        if (! in_array($v['type'], $provider->types(), true)) {
            return Response::error('Unknown certificate type.');
        }

        $bundles = app(CertificateBundles::class);
        $state = ($v['build'] ?? false) ? $bundles->start($provider, $event, $v['type'], $user->id) : $bundles->state($provider, $event, $v['type']);
        if ($v['build'] ?? false) {
            Log::info('certificates.bundle_requested', ['event' => $event->uuid, 'type' => $v['type'], 'by' => $user->id, 'via' => 'mcp']);
        }

        return Response::json(['event' => $event->uuid, 'type' => $v['type']] + $state + [
            'download_url' => $state['state'] === 'ready' ? route('me.certificates.bundle.download', ['context' => $provider->alias(), 'key' => $event->uuid, 'type' => $v['type']]) : null,
        ]);
    }
}
