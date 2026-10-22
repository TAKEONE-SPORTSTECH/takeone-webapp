<?php

namespace App\Mcp\Tools;

use App\Events\Models\ClubEvent;
use App\Events\Support\EventAccess;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('One division per person')]
#[Description('Switch an event\'s "one division per person" rule on or off (App\\Events\\Support\\OneDivisionRule). On: nobody can be placed in a second division\'s bracket — arranging, admitting, copying and the draw desk all refuse it. Off (the default): a person may stand in several. The same switch as the federation Draw tab and the draw desk. Turning it on also returns `conflicts`: everybody already standing in two divisions, with the division each one fits. Turning it on also returns `conflicts`: everybody ALREADY standing in two divisions, with the division each one fits. Only the event\'s organiser may change it.')]
class SetEventOneDivisionTool extends BaseTool
{
    protected bool $isWrite = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->required()->description('The event uuid.'),
            'on' => $schema->boolean()->required()->description('true = one division per person; false = several allowed.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);

        if ($user instanceof Response) {
            return $user;
        }

        $validated = $request->validate(['event' => 'required|string', 'on' => 'required|boolean']);

        $event = ClubEvent::where('uuid', $validated['event'])->first();

        if (! $event || ! app(EventAccess::class)->visible($event, $user)) {
            return Response::error('Event not found.');
        }

        if (! app(EventAccess::class)->canManage($event, $user)) {
            return Response::error('Only the event\'s organiser can change this rule.');
        }

        $event->update(['one_division_per_person' => (bool) $validated['on']]);

        return Response::json([
            'one_division_per_person' => (bool) $event->fresh()->one_division_per_person,
            // Anybody ALREADY in two divisions (the switch only refuses new placements).
            'conflicts' => $validated['on'] ? \App\Events\Support\OneDivisionRule::conflicts($event) : [],
        ]);
    }
}
