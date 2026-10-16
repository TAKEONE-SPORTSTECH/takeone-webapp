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

#[Title('Move or copy a competitor into another division')]
#[Description('Take a competitor from one division\'s draw into ANOTHER division — the split-view drag on the bracket screen. mode "move" takes their entry out of the old division (its corner empties) and into the new one; it is refused for somebody who has already fought in the old division. mode "copy" keeps them where they are and makes a SECOND entry in the new division; charge=true writes the event\'s entry price on it as unpaid, charge=false records it as a free entry. Optionally drop them into a first-round corner of the new draw (to_match_id + to_side), otherwise they go onto its entrants list. Organiser only. Read both draws first with get_event_bracket for the ids.')]
class TransferEventCompetitorTool extends BaseTool
{
    protected bool $isWrite = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->required()->description('The event uuid.'),
            'from_division' => $schema->string()->required()->description('The division they are in now (its name).'),
            'competitor_id' => $schema->integer()->required()->description('Their registration id in from_division.'),
            'to_division' => $schema->string()->required()->description('The division to move or copy them into (its name).'),
            'mode' => $schema->string()->required()->description('"move" or "copy".'),
            'charge' => $schema->boolean()->description('Copy only: true = charge the entry price (unpaid), false = free entry.'),
            'to_match_id' => $schema->integer()->description('Optional first-round match id in to_division to place them in.'),
            'to_side' => $schema->string()->description('"a" or "b" — required with to_match_id.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);

        if ($user instanceof Response) {
            return $user;
        }

        if (! config('events.draw_transfer')) {
            return Response::error('Moving competitors between divisions is not switched on.');
        }

        $validated = $request->validate([
            'event' => 'required|string',
            'from_division' => 'required|string|max:120',
            'competitor_id' => 'required|integer',
            'to_division' => 'required|string|max:120',
            'mode' => 'required|in:move,copy',
            'charge' => 'nullable|boolean',
            'to_match_id' => 'nullable|integer',
            'to_side' => 'nullable|in:a,b',
        ]);

        $event = ClubEvent::where('uuid', $validated['event'])->with('tenant')->first();

        if (! $event || ! app(EventAccess::class)->visible($event, $user)) {
            return Response::error('Event not found.');
        }

        // Same line as the web door's COPY (it can make a charge), and the
        // arrange tool's: running the event, not watching it.
        if (! app(EventAccess::class)->canManage($event, $user)) {
            return Response::error('Only the event\'s organiser can move competitors between divisions.');
        }

        $division = fn (string $name) => EventCategory::where('event_id', $event->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

        $from = $division($validated['from_division']);
        $to = $division($validated['to_division']);

        if (! $from || ! $to) {
            return Response::error('No division by that name in this event.');
        }

        $type = app(EventTypeRegistry::class)->for($event);

        if (! in_array('transfer_draw', collect($type->availableActions($event))->pluck('action')->all(), true)) {
            return Response::error('This event\'s draw cannot be changed right now.');
        }

        // One division per person — the same refusal the web door gives.
        if (! empty($validated['to_match_id'])) {
            $entry = $event->registrations()->where('id', (int) $validated['competitor_id'])->first();
            if ($refused = \App\Events\Support\OneDivisionRule::refusal($event, $entry, $to->id, moving: $validated['mode'] === 'move')) {
                return Response::error($refused);
            }
        }

        $result = $type->performAction($event, 'transfer_draw', [
            'from_category_id' => $from->id,
            'competitor_id' => $validated['competitor_id'],
            'to_category_id' => $to->id,
            'to' => ! empty($validated['to_match_id'])
                ? ['type' => 'slot', 'match_id' => $validated['to_match_id'], 'side' => $validated['to_side'] ?? null]
                : ['type' => 'bench'],
            'mode' => $validated['mode'],
            'charge' => (bool) ($validated['charge'] ?? false),
        ]);

        if (! ($result['success'] ?? false)) {
            return Response::error($result['message'] ?? 'Could not move the competitor.');
        }

        return Response::json([
            'done' => true,
            'message' => $result['message'],
            'division' => $result['data']['division'] ?? null,
            'source' => $result['data']['source'] ?? null,
        ]);
    }
}
