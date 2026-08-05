<?php

namespace App\Mcp\Tools;

use App\Events\EventTypeRegistry;
use App\Events\Support\EventAccess;
use App\Models\ClubEvent;
use App\Models\EventCategory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Arrange an event bracket')]
#[Description('Move one competitor within a division\'s first round, or in and out of the draw — the same hand-arranging an organiser does by dragging on the bracket screen. Only the event\'s organiser may do it, and only before the event starts: once the first bout is due the draw is final and this refuses. Later rounds are never arrangeable (they are derived from the earlier ones). Read the draw first with get_event_bracket to get the match ids.')]
class ArrangeEventBracketTool extends BaseTool
{
    protected bool $isWrite = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->required()
                ->description('The event uuid.'),
            'division' => $schema->string()->required()
                ->description('The division name the move happens in (e.g. "-68 kg").'),
            'from_match_id' => $schema->integer()
                ->description('Match id the competitor is currently in. Omit when taking someone off the entrants list instead.'),
            'from_side' => $schema->string()
                ->description('"a" or "b" — which corner of from_match_id. Required with from_match_id.'),
            'from_competitor_id' => $schema->integer()
                ->description('Registration id of an entrant NOT currently in the draw, to place them into it. Use instead of from_match_id/from_side.'),
            'to_match_id' => $schema->integer()
                ->description('Match id to move them into. Omit to take them out of the draw and back onto the entrants list.'),
            'to_side' => $schema->string()
                ->description('"a" or "b" — which corner of to_match_id. Required with to_match_id.'),
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
            'from_match_id' => 'nullable|integer',
            'from_side' => 'nullable|in:a,b',
            'from_competitor_id' => 'nullable|integer',
            'to_match_id' => 'nullable|integer',
            'to_side' => 'nullable|in:a,b',
        ]);

        $event = ClubEvent::where('uuid', $validated['event'])->with('tenant')->first();

        if (! $event || ! app(EventAccess::class)->visible($event, $user)) {
            return Response::error('Event not found.');
        }

        // Arranging is running the event, not watching it.
        if (! app(EventAccess::class)->canManage($event, $user)) {
            return Response::error('Only the event\'s organiser can arrange its draw.');
        }

        $category = EventCategory::where('event_id', $event->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($validated['division'])])
            ->first();

        if (! $category) {
            return Response::error('No division by that name in this event.');
        }

        $from = ! empty($validated['from_match_id'])
            ? ['type' => 'slot', 'match_id' => $validated['from_match_id'], 'side' => $validated['from_side'] ?? null]
            : ['type' => 'bench', 'competitor_id' => $validated['from_competitor_id'] ?? null];

        $to = ! empty($validated['to_match_id'])
            ? ['type' => 'slot', 'match_id' => $validated['to_match_id'], 'side' => $validated['to_side'] ?? null]
            : ['type' => 'bench'];

        // Every rule about what a legal arrangement IS belongs to the package
        // that owns the event type — this tool only carries the request to it,
        // exactly as the web endpoint does.
        $type = app(EventTypeRegistry::class)->for($event);

        $offered = collect($type->availableActions($event))->pluck('action')->all();
        if (! in_array('arrange_draw', $offered, true)) {
            return Response::error('This event\'s draw cannot be arranged right now.');
        }

        $result = $type->performAction($event, 'arrange_draw', [
            'category_id' => $category->id,
            'from' => $from,
            'to' => $to,
        ]);

        if (! ($result['success'] ?? false)) {
            return Response::error($result['message'] ?? 'Could not arrange the draw.');
        }

        return Response::json([
            'moved' => true,
            'message' => $result['message'],
            'division' => $result['data']['division'] ?? null,
        ]);
    }
}
