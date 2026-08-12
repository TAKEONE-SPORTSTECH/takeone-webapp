<?php

namespace App\Mcp\Tools;

use App\Events\EventTypeRegistry;
use App\Events\Support\EventAccess;
use App\Events\Support\RosterPeople;
use App\Models\ClubEvent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

/**
 * Who is competing at an event, and which clubs they came from.
 *
 * The exact payload behind the "Who's joined" screen, built by the same
 * RosterPeople so the two can never drift: an integration asking "which clubs
 * are sending athletes" must get the answer the hall gets.
 *
 * Reading only, and deliberately narrow. No registration ids, no weights, no
 * payment or weigh-in state, no moderation — those belong to the officials'
 * desk and are not exposed here for anyone, organisers included. Spectators are
 * not listed: they did not enter a competition.
 */
#[Title('List event people')]
#[Description('List an event\'s competitors and the clubs behind them — names, divisions, countries and squad sizes. Reading only: no weights, payment status or registration ids. Returns "not found" for an event the acting user cannot see.')]
class ListEventPeopleTool extends BaseTool
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

        // One answer for "no such event" and "not yours" — never confirm that a
        // uuid is real to someone who may not see it.
        if (! $event || ! app(EventAccess::class)->visible($event, $user)) {
            return Response::error('Event not found.');
        }

        $rows = app(EventTypeRegistry::class)->for($event)->rosterRows($event);
        $people = app(RosterPeople::class)->build($rows);

        return Response::json([
            'event' => ['uuid' => $event->uuid, 'title' => $event->title],
            'totals' => [
                'athletes' => count($people['participants']),
                'clubs' => count($people['clubs']),
            ],
            'athletes' => array_map(fn (array $p) => [
                // The public key, so a consumer can link to the profile page.
                'uuid' => $p['uuid'],
                'name' => $p['name'],
                'gender' => $p['gender'],
                'division' => $p['category'],
                'weight_class' => $p['weight_class'],
                'country' => $p['country'],
                'club' => $p['club']['name'] ?? null,
            ], $people['participants']),
            'clubs' => array_map(fn (array $c) => [
                'name' => $c['name'],
                'country' => $c['country'],
                'athletes' => $c['athletes'],
            ], $people['clubs']),
        ]);
    }
}
