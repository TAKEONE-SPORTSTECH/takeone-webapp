<?php

namespace App\Mcp\Tools;

use App\Events\EventTypeRegistry;
use App\Events\Models\ClubEvent;
use App\Events\Models\EventCategory;
use App\Events\Support\EventAccess;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Shuffle a division\'s draw')]
#[Description('Re-draw one division fairly — the Shuffle button on the draw board: the highest belts are seeded apart (1 and 2 in opposite halves), teammates from the same club are kept apart as long as possible, and everyone else is placed at random. Replaces any hand arrangement in that division. Only the event\'s organiser may do it, only before the event starts, and never on a division whose bouts have results. Read the draw afterwards with get_event_bracket.')]
class ShuffleEventDivisionTool extends BaseTool
{
    protected bool $isWrite = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->required()
                ->description('The event uuid.'),
            'division' => $schema->string()->required()
                ->description('The division name to shuffle (e.g. "Cadet Kumite Male -52 kg").'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);

        if ($user instanceof Response) {
            return $user;
        }

        $validated = $request->validate([
            'event' => 'required|string',
            'division' => 'required|string|max:80',
        ]);

        $event = ClubEvent::where('uuid', $validated['event'])->first();

        if (! $event || ! app(EventAccess::class)->visible($event, $user)) {
            return Response::error('Event not found.');
        }

        if (! app(EventAccess::class)->canManage($event, $user)) {
            return Response::error('Only the event\'s organiser can shuffle its draw.');
        }

        $category = EventCategory::where('event_id', $event->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($validated['division'])])
            ->first();

        if (! $category) {
            return Response::error('No division by that name in this event.');
        }

        // The package that owns the event type decides whether shuffling is on
        // offer — the same deny-by-default the web endpoint applies.
        $type = app(EventTypeRegistry::class)->for($event);

        if (! in_array('shuffle_draw', collect($type->availableActions($event))->pluck('action')->all(), true)) {
            return Response::error('This event\'s draw cannot be shuffled right now.');
        }

        $result = $type->performAction($event, 'shuffle_draw', ['category_id' => $category->id]);

        if (! ($result['success'] ?? false)) {
            return Response::error($result['message'] ?? 'Could not shuffle the draw.');
        }

        return Response::json([
            'shuffled' => true,
            'message' => $result['message'],
            'division' => $result['data']['division'] ?? null,
        ]);
    }
}
