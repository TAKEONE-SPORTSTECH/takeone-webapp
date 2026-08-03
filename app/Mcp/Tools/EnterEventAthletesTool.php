<?php

namespace App\Mcp\Tools;

use App\Events\Support\EntryService;
use App\Models\ClubEvent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Enter athletes into an event')]
#[Description('Enter one or more of your club\'s athletes into an event (a coach entering their squad). Every athlete passes the same checks as entering themselves — the event must be open to their club, they must not be barred, entries must be open, there must be room, and for a weight-classed championship they must classify into a division the event is actually running. Partial success is normal: the response reports who was entered and who was refused, with a reason for each. Omit athlete_ids to preview the roster without entering anyone.')]
class EnterEventAthletesTool extends BaseTool
{
    protected bool $isWrite = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->required()
                ->description('The event uuid (the unpredictable public id used in its URL).'),
            'athlete_ids' => $schema->array()->items($schema->integer())
                ->description('Numeric user ids to enter. Each must be an active member of a club you own or administer. Omit to just list the roster and each athlete\'s eligibility.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $entries = app(EntryService::class);

        $user = $this->guard($request);

        if ($user instanceof Response) {
            return $user;
        }

        $validated = $request->validate([
            'event' => 'required|string',
            'athlete_ids' => 'nullable|array|max:200',
            'athlete_ids.*' => 'integer',
        ]);

        $event = ClubEvent::where('uuid', $validated['event'])->with('tenant')->first();

        if (! $event || $event->is_archived) {
            return Response::error('Event not found.');
        }

        // Same bar as the web endpoint: you must run a club to enter anyone.
        if ($entries->administeredClubIds($user) === [] && ! $user->isSuperAdmin()) {
            return Response::error('Only a club owner or club admin can enter athletes into an event.');
        }

        // No ids → answer "who could I enter?" without changing anything.
        if (empty($validated['athlete_ids'])) {
            return Response::json([
                'event' => ['uuid' => $event->uuid, 'title' => $event->title, 'date' => $event->date?->toDateString()],
                'roster' => $entries->roster($event, $user),
            ]);
        }

        $result = $entries->enterMany($event, $user, $validated['athlete_ids']);

        return Response::json([
            'event' => ['uuid' => $event->uuid, 'title' => $event->title],
            'entered' => $result['entered'],
            'rejected' => $result['rejected'],
            'participants_total' => $result['going'],
        ]);
    }
}
