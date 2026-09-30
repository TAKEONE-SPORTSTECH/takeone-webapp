<?php

namespace App\Mcp\Tools;

use App\Certificates\Support\CertificateRealtime;
use App\Events\Support\EventCertificates;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Draft event certificates')]
#[Description('For an event you manage: draft every certificate earned so far — participation for everyone who competed in a finished division, podium for gold, silver and bronze. Idempotent: an entry that already has one is skipped. Drafts reach nobody until approved.')]
class GenerateEventCertificatesTool extends BaseTool
{
    protected bool $isWrite = true;

    public function schema(JsonSchema $schema): array
    {
        return ['event' => $schema->string()->required()->description('The event\'s uuid.')];
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

        $v = $request->validate(['event' => 'required|uuid']);
        $provider = app(EventCertificates::class);
        $event = $provider->find($v['event']);
        if (! $event || ! $provider->canManage($event, $user)) {
            return Response::error('Event not found.');
        }

        $r = $provider->generate($event, $user);
        CertificateRealtime::refresh($provider->audience($event), $user->id);

        return Response::json(['event' => $event->uuid, 'created' => $r['created'], 'divisions_still_running' => $r['pending']]);
    }
}
