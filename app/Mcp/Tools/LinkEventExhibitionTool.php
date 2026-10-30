<?php

namespace App\Mcp\Tools;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventExhibitionBout;
use App\Events\Support\EventAccess;
use App\Events\Support\ExhibitionLink;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

/**
 * Link a finished side exhibition to the draw bout it really was — the same
 * door as the Karate console's Exhibitions tab (App\Events\Support\ExhibitionLink).
 * The exhibition's result is filed into that bout through the event type's own
 * recordOutcome(), so the winner advances exactly as from the scoring table.
 *
 * Organisers only (EventAccess::canManage): a link writes a result into the
 * bracket and can seat an athlete in a later-round slot.
 */
#[Title('Link an exhibition bout to the draw')]
#[Description('For an event you manage: file a finished side exhibition (EX1, EX2…) into the draw bout it really was. The bout must be undecided and either already hold the same two entrants, or be a later-round bout still waiting on earlier results that the bracket would route them into (see link_candidates from list_event_exhibitions). The result is recorded, the winner advances, and the exhibition is marked linked. With attach_only, any bout of the event may be chosen: the exhibition, its footage and score log are attached and the draw is not changed. Refused when the competition has not been started. Others get "Event not found".')]
class LinkEventExhibitionTool extends BaseTool
{
    protected bool $isWrite = true;

    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->required()->description('The event uuid (from list_events).'),
            'exhibition' => $schema->string()->required()->description('The exhibition uuid (from list_event_exhibitions).'),
            'match_id' => $schema->integer()->required()->description('The draw bout to link it to — a match_id from its link_candidates, or any bout of the event with attach_only.'),
            'attach_only' => $schema->boolean()->description('For a bout NOT among link_candidates: attach the exhibition, its footage and score log to that bout without changing the draw (competitors and result stay as they are).'),
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
            'exhibition' => 'required|uuid',
            'match_id' => 'required|integer|min:1',
            'attach_only' => 'sometimes|boolean',
        ]);

        $event = ClubEvent::where('uuid', $v['event'])->first();

        // One answer for "no such event" and "not yours".
        if (! $event || ! app(EventAccess::class)->canManage($event, $user)) {
            return Response::error('Event not found.');
        }

        $ex = EventExhibitionBout::where('event_id', $event->id)->where('uuid', $v['exhibition'])->first();

        if (! $ex) {
            return Response::error('Exhibition not found.');
        }

        try {
            $done = app(ExhibitionLink::class)->link($event, $ex, (int) $v['match_id'], $user, (bool) ($v['attach_only'] ?? false));
        } catch (\RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::json([
            'event' => $event->uuid,
            'exhibition' => [
                'uuid' => $done['exhibition']['uuid'],
                'code' => $done['exhibition']['code'],
                'status' => $done['exhibition']['status'],
                'linked' => $done['exhibition']['linked'],
            ],
            'match' => $done['match'],
        ]);
    }
}
