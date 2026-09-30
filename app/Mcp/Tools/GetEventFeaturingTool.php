<?php

namespace App\Mcp\Tools;

use App\Events\Models\ClubEvent;
use App\Events\Support\EventAccess;
use App\Events\Support\EventFeaturing;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Get event featuring')]
#[Description('For an event you manage: whether it is featured on the members\' Events page, whether a paid request is waiting, the reason if one was rejected, and the current fee. Sending the proof of payment is done in the event console (it needs a file upload).')]
class GetEventFeaturingTool extends BaseTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->required()->description('The event\'s uuid.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);
        if ($user instanceof Response) {
            return $user;
        }

        $v = $request->validate(['event' => 'required|uuid']);
        $event = ClubEvent::where('uuid', $v['event'])->where('is_archived', false)->first();

        // One answer for missing and not-yours.
        if (! $event || ! app(EventAccess::class)->canManage($event, $user)) {
            return Response::error('Event not found.');
        }

        return Response::json(['event' => $event->uuid, 'featuring' => EventFeaturing::state($event)]);
    }
}
