<?php

namespace App\Mcp\Tools;

use App\Events\Models\ClubEvent;
use App\Events\Support\EntryChannels;
use App\Events\Support\EventAccess;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Set how athletes enter an event')]
#[Description('For an event you manage: read or set how athletes may enter — "individual" (athletes enter themselves and are billed for their own entry), "club" (clubs enter squads, billed to the club), or both. Only NEW entries are affected; nobody already entered loses their place, and the people running the event can always add anyone. Omit `channels` to only read the current setting. Not available for an event hosted by a federation (its clubs enter through its invitation).')]
class SetEventEntryChannelsTool extends BaseTool
{
    protected bool $isWrite = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->required()->description('The event\'s uuid.'),
            'channels' => $schema->array()->description('Any of "individual", "club" — at least one. Omit to read.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);
        if ($user instanceof Response) {
            return $user;
        }
        $v = $request->validate(['event' => 'required|uuid', 'channels' => 'sometimes|array|min:1', 'channels.*' => 'string|in:'.implode(',', EntryChannels::ALL)]);

        $event = ClubEvent::where('uuid', $v['event'])->where('is_archived', false)->first();
        if (! $event || ! app(EventAccess::class)->canManage($event, $user)) {
            return Response::error('Event not found.');
        }
        $channels = app(EntryChannels::class);

        if (array_key_exists('channels', $v)) {
            if ($event->host_type) {
                return Response::error('This event\'s host takes its athletes from clubs through its own invitation; its entry channels are not set here.');
            }
            $channels->set($event, $v['channels']);
            $event->refresh();
        }

        return Response::json(['event' => $event->uuid, 'channels' => $channels->allowed($event), 'chosen' => $channels->chosen($event)]);
    }
}
