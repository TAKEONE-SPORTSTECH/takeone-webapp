<?php

namespace App\Mcp\Tools;

use App\Events\Models\ClubEvent;
use App\Events\Support\CategorySystems;
use App\Events\Support\EventAccess;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Set event rule book')]
#[Description('For an event you manage: list the category systems (age groups × weight classes — e.g. WKF, Shotokan, a custom one) and belt systems it may run under, or change them. Changing the category system RE-SORTS everyone already entered (people placed by hand stay put), and is refused once a draw has been made. Omit both keys to only list the choices.')]
class SetEventRulebookTool extends BaseTool
{
    protected bool $isWrite = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->required()->description('The event\'s uuid.'),
            'category_system' => $schema->string()->description('A key from the list, or "" for the sport\'s own.'),
            'belt_system' => $schema->string()->description('A key from the list, or "" for the sport\'s own.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);
        if ($user instanceof Response) {
            return $user;
        }
        $v = $request->validate([
            'event' => 'required|uuid',
            'category_system' => 'nullable|string|max:64',
            'belt_system' => 'nullable|string|max:64',
        ]);
        $event = ClubEvent::where('uuid', $v['event'])->where('is_archived', false)->first();
        if (! $event || ! app(EventAccess::class)->canManage($event, $user)) {
            return Response::error('Event not found.');
        }
        $systems = app(CategorySystems::class);
        $choices = [
            'category_systems' => array_values(array_map(fn ($s) => ['key' => $s->key, 'label' => $s->label, 'custom' => $s->custom], $systems->weightsFor($event))),
            'belt_systems' => array_values(array_map(fn ($s) => ['key' => $s->key, 'label' => $s->label, 'custom' => $s->custom], $systems->beltsFor($event))),
        ];

        if (! array_key_exists('category_system', $request->all()) && ! array_key_exists('belt_system', $request->all())) {
            return Response::json(['event' => $event->uuid, 'category_system' => $event->category_system, 'belt_system' => $event->belt_system] + $choices);
        }

        $r = $systems->change($event,
            array_key_exists('category_system', $request->all()) ? ($v['category_system'] ?? null) : $event->category_system,
            array_key_exists('belt_system', $request->all()) ? ($v['belt_system'] ?? null) : $event->belt_system);
        if (! $r['ok']) {
            return Response::error($r['message'] ?? 'That rule book is not available for this event.');
        }
        $event->refresh();
        \App\Events\Support\EventHost::changed($event);

        return Response::json(['event' => $event->uuid, 'category_system' => $event->category_system, 'belt_system' => $event->belt_system,
            'moved' => $r['moved'] ?? 0, 'created_divisions' => $r['created'] ?? 0, 'unplaced' => $r['unplaced'] ?? 0]);
    }
}
