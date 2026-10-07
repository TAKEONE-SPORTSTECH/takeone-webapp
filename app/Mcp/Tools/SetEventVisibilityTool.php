<?php

namespace App\Mcp\Tools;

use App\Events\Models\ClubEvent;
use App\Events\Support\EventAccess;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Set whether an event is listed or hidden')]
#[Description('For an event you manage: read or set its visibility. "listed" — it appears on Explore, the club page and to the federation, and sends announcements. "hidden" — it appears in no list anywhere and sends no announcements; anybody you give its link to can open it and register. Organisers and people already entered still see it in their own lists. Omit `visibility` to only read it.')]
class SetEventVisibilityTool extends BaseTool
{
    protected bool $isWrite = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->required()->description('The event\'s uuid.'),
            'visibility' => $schema->string()->enum([ClubEvent::LISTED, ClubEvent::HIDDEN])->description('"listed" or "hidden". Omit to read.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);
        if ($user instanceof Response) {
            return $user;
        }
        $v = $request->validate(['event' => 'required|uuid', 'visibility' => 'sometimes|in:'.ClubEvent::LISTED.','.ClubEvent::HIDDEN]);

        // One answer for missing and not-yours.
        $event = ClubEvent::where('uuid', $v['event'])->where('is_archived', false)->first();
        if (! $event || ! app(EventAccess::class)->canManage($event, $user)) {
            return Response::error('Event not found.');
        }

        if (array_key_exists('visibility', $v)) {
            $event->update(['visibility' => $v['visibility']]);
        }

        return Response::json([
            'event' => $event->uuid,
            'visibility' => $event->visibility ?: ClubEvent::LISTED,
            'link' => route('me.events.show', ['event' => $event->uuid]),
        ]);
    }
}
