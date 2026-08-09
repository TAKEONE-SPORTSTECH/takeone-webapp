<?php

namespace App\Mcp\Tools;

use App\Events\Support\EventAccess;
use App\Models\ClubEvent;
use App\Models\EventChecklistItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

/**
 * Is this event ready to begin, and has it begun?
 *
 * The run-day checklist is organiser and official material — it is the
 * organiser's preparation notes and it names the people who signed each item
 * off. So this tool answers for the people who staff the event and nobody
 * else, mirroring exactly what the event screen shows: canOfficiate sees the
 * list, everyone else is told the event does not exist to them in this sense.
 */
#[Title('Get event readiness')]
#[Description('The run-day checklist for an event and whether it has started: each item, whether it is cleared and by whom, how many are outstanding, and whether the organiser started with items still open. Restricted to the event organiser and its appointed officials.')]
class GetEventReadinessTool extends BaseTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->required()
                ->description('The event uuid (from list_events).'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);

        if ($user instanceof Response) {
            return $user;
        }

        $uuid = trim((string) $request->get('event', ''));
        $event = $uuid !== '' ? ClubEvent::where('uuid', $uuid)->first() : null;

        $access = app(EventAccess::class);

        // One answer for "no such event", "not yours" and "not your job" — a
        // competitor probing uuids learns nothing either way.
        if (! $event || ! $access->visible($event, $user) || ! $access->canOfficiate($event, $user)) {
            return Response::error('Event not found.');
        }

        $items = $event->checklistItems()->with('checker:id,full_name,name')->get();

        return Response::json([
            'event' => ['uuid' => $event->uuid, 'title' => $event->title],
            'started' => $event->hasStarted(),
            'started_at' => $event->started_at?->toIso8601String(),
            'started_with_checks_outstanding' => (bool) $event->start_overridden,
            'scheduled_start' => $event->scheduledStart()?->toIso8601String(),
            'overdue_to_start' => $event->isOverdueToStart(),
            'outstanding' => $items->whereNull('checked_at')->count(),
            'ready_to_start' => $items->whereNull('checked_at')->isEmpty(),
            'checklist' => $items->map(fn (EventChecklistItem $i) => [
                'uuid' => $i->uuid,
                'label' => $i->label,
                'checked' => $i->isChecked(),
                'checked_at' => $i->checked_at?->toIso8601String(),
                'checked_by' => $i->checked_by
                    ? ($i->checker?->full_name ?? $i->checker?->name)
                    : null,
            ])->values(),
        ]);
    }
}
