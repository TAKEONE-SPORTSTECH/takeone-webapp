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

/**
 * The hall screens driving an event's mats, and whether they are alive.
 *
 * Venue hardware is organiser material: which boards exist, which mat each one
 * shows, and — the question worth asking remotely — whether any of them has
 * gone dark before the hall fills. Restricted to whoever manages the event, the
 * same rule the console enforces.
 *
 * Read-only on purpose. Pairing a screen means reading a code off a wall in the
 * room, so it is not an action an integration can meaningfully take; and a write
 * tool here would turn a public code into a remotely exploitable one.
 */
#[Title('List court screens')]
#[Description("The hall screens paired to an event: which mat each shows, whether it has reported in recently, and when it was last seen — plus the mats this event runs on. Restricted to the event's organiser.")]
class ListCourtScreensTool extends BaseTool
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

        // One answer for "no such event" and "not yours", so a stranger probing
        // uuids cannot tell which competitions exist.
        if (! $event || ! app(EventAccess::class)->canManage($event, $user)) {
            return Response::error('Event not found.');
        }

        // The type answers, exactly as the console asks it — an event type with
        // no wall boards reports none rather than being special-cased here.
        $screens = app(EventTypeRegistry::class)->for($event)->hallScreens($event);

        if ($screens === null) {
            return Response::json([
                'event' => ['uuid' => $event->uuid, 'title' => $event->title],
                'supported' => false,
                'mats' => [],
                'screens' => [],
            ]);
        }

        return Response::json([
            'event' => ['uuid' => $event->uuid, 'title' => $event->title],
            'supported' => true,
            'mats' => $screens['mats'],
            'paired' => count($screens['screens']),
            'dark' => count(array_filter($screens['screens'], fn (array $s) => ! $s['live'])),
            'screens' => $screens['screens'],
        ]);
    }
}
