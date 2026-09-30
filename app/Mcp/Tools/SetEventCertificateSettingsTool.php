<?php

namespace App\Mcp\Tools;

use App\Events\Support\EventCertificates;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Set event certificate settings')]
#[Description('For an event you manage: whether it issues participation and/or podium certificates, and when drafts are made — "division" (as each division finishes), "event" (when the event is completed) or "manual" (only when asked).')]
class SetEventCertificateSettingsTool extends BaseTool
{
    protected bool $isWrite = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->required()->description('The event\'s uuid.'),
            'participation' => $schema->boolean(),
            'podium' => $schema->boolean(),
            'trigger' => $schema->string()->enum(['division', 'event', 'manual']),
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

        $v = $request->validate([
            'event' => 'required|uuid',
            'participation' => 'sometimes|boolean',
            'podium' => 'sometimes|boolean',
            'trigger' => 'sometimes|in:division,event,manual',
        ]);
        $provider = app(EventCertificates::class);
        $event = $provider->find($v['event']);
        if (! $event || ! $provider->canManage($event, $user)) {
            return Response::error('Event not found.');
        }

        return Response::json(['event' => $event->uuid, 'settings' => $provider->saveSettings($event, $v)]);
    }
}
