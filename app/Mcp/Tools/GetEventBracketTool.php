<?php

namespace App\Mcp\Tools;

use App\Events\EventTypeRegistry;
use App\Events\Support\EventAccess;
use App\Models\ClubEvent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Read an event bracket')]
#[Description('Read the knockout draw of an event: every division, its rounds, each bout with both competitors, their seeds and scores, who won, the mat and time, plus the podium once a division is decided. Also lists entrants who are not currently placed in the draw. Works for any bracketed event type (a taekwondo championship today; any sport that runs a knockout). Read-only, and limited to events the acting user can see in the app.')]
class GetEventBracketTool extends BaseTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->required()
                ->description('The event uuid (the unpredictable public id used in its URL).'),
            'division' => $schema->string()
                ->description('Optional division name to return on its own (e.g. "-68 kg"). Omit for every division.'),
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
            'division' => 'nullable|string|max:80',
        ]);

        $event = ClubEvent::where('uuid', $validated['event'])->with('tenant')->first();

        // Same visibility rule as the web screen — and the same answer whether
        // the event is missing or merely out of reach, so this cannot be used
        // to discover which uuids exist.
        if (! $event || ! app(EventAccess::class)->visible($event, $user)) {
            return Response::error('Event not found.');
        }

        // A draw the organiser has withheld is withheld here too. The MCP must
        // never show more than the acting user could reach in the UI, and an
        // integration reading a bracket the athletes cannot see would be
        // exactly that.
        if (! app(EventAccess::class)->drawVisible($event, $user)) {
            return Response::json([
                'event' => ['uuid' => $event->uuid, 'title' => $event->title],
                'bracketed' => true,
                'published' => false,
                'message' => 'The draw for this event has not been published yet.',
            ]);
        }

        $type = app(EventTypeRegistry::class)->for($event);
        $divisions = $type->bracketView($event, $user);

        if ($divisions === []) {
            return Response::json([
                'event' => ['uuid' => $event->uuid, 'title' => $event->title],
                'bracketed' => false,
                'message' => 'This event does not run a knockout bracket.',
            ]);
        }

        if (! empty($validated['division'])) {
            $needle = mb_strtolower($validated['division']);
            $divisions = array_values(array_filter(
                $divisions,
                fn (array $d) => mb_strtolower($d['name']) === $needle,
            ));

            if ($divisions === []) {
                return Response::error('No division by that name in this event.');
            }
        }

        return Response::json([
            'event' => [
                'uuid' => $event->uuid,
                'title' => $event->title,
                'club' => $event->tenant?->club_name,
                'date' => $event->date?->toDateString(),
                'stage' => $type->stage($event),
                'draw_locked' => $event->hasStarted() || $event->hasEnded(),
            ],
            'bracketed' => true,
            'divisions' => $divisions,
        ]);
    }
}
