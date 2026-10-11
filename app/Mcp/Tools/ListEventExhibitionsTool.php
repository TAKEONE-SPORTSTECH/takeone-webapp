<?php

namespace App\Mcp\Tools;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventExhibitionBout;
use App\Events\Support\EventAccess;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

/**
 * An event's side exhibition bouts — two entrants put on a mat outside the
 * draw from the scoring console's Arcade tab. Never part of the draw, the
 * results or anyone's record; listed so the organiser has what was fought.
 *
 * Same audience as the console that creates them: whoever may score the event.
 */
#[Title('List event exhibition bouts')]
#[Description('List the side exhibition bouts fought at an event outside the draw — number (EX1…), mat, the two entrants, scores, winner and status (running, done, abandoned). Exhibitions never affect the draw or any record. Only for users who may score the event; others get "Event not found".')]
class ListEventExhibitionsTool extends BaseTool
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

        // One answer for "no such event" and "not yours".
        if (! $event || ! app(EventAccess::class)->canScore($event, $user)) {
            return Response::error('Event not found.');
        }

        $bouts = EventExhibitionBout::where('event_id', $event->id)
            ->orderBy('number')
            ->limit(500)
            ->get()
            ->map(fn (EventExhibitionBout $b) => [
                'uuid' => $b->uuid,
                'code' => $b->code(),
                'mat' => $b->court,
                'a' => ['name' => $b->a_name, 'score' => $b->a_score],
                'b' => ['name' => $b->b_name, 'score' => $b->b_score],
                'winner' => $b->winner,
                'win_reason' => $b->win_reason,
                'win_note' => $b->win_note,
                'status' => $b->status,
                'started_at' => $b->created_at?->toIso8601String(),
                'finished_at' => $b->finished_at?->toIso8601String(),
            ])->values();

        return Response::json([
            'event' => ['uuid' => $event->uuid, 'title' => $event->title],
            'total' => $bouts->count(),
            'exhibitions' => $bouts,
        ]);
    }
}
