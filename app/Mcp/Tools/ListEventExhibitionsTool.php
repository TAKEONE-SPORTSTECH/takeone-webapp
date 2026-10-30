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
#[Description('List the side exhibition bouts fought at an event outside the draw — number (EX1…), mat, the two entrants, scores, winner and status (running, done, abandoned, linked). A linked exhibition was filed into the draw bout named by linked_match_id. For an organiser each finished exhibition also carries link_candidates: the undecided draw bouts it could be linked to with link_event_exhibition (kind "exact" = same pair already in the undecided bout, "recorded" = the bout of the same pair already has a result — linkable only when agrees is true, which writes the real score from the exhibition without changing the winner, "seat" = a later-round bout still waiting on earlier results). Only for users who may score the event; others get "Event not found".')]
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
                // Once linked it IS that draw bout (App\Events\Support\ExhibitionLink).
                'linked_match_id' => $b->linked_match_id,
                'linked_at' => $b->linked_at?->toIso8601String(),
            ])->values();

        // The draw bouts each finished exhibition could be linked to — only for
        // whoever may make the link (organisers), and the same answer the
        // console's Exhibitions tab gives.
        if (app(EventAccess::class)->canManage($event, $user)) {
            $fits = collect(app(\App\Events\Support\ExhibitionLink::class)->overview($event)['exhibitions'])
                ->mapWithKeys(fn ($x) => [$x['uuid'] => collect($x['candidates'])->map(fn ($c) => [
                    'match_id' => $c['id'], 'number' => $c['number'], 'round' => $c['round'],
                    'division' => $c['division'], 'mat' => $c['court'], 'kind' => $c['kind'],
                    'a' => $c['a'], 'b' => $c['b'],
                ] + ($c['kind'] === 'recorded' ? ['agrees' => $c['agrees'], 'recorded' => $c['recorded']] : []))->values()->all()]);
            $bouts = $bouts->map(fn ($b) => $b + ['link_candidates' => $fits[$b['uuid']] ?? []]);
        }

        return Response::json([
            'event' => ['uuid' => $event->uuid, 'title' => $event->title],
            'total' => $bouts->count(),
            'exhibitions' => $bouts,
        ]);
    }
}
