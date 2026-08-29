<?php

namespace App\Mcp\Tools;

use App\Events\Support\EventAccess;
use App\Media\VideoLibrary;
use App\Models\ClubEvent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

/**
 * Everything filmed at an event, grouped by division.
 *
 * Metadata and links only — the bytes are served by the media routes, which run
 * their own authorisation on the playlist and on every segment. Nothing here
 * hands out a storage path.
 */
#[Title('List event videos')]
#[Description('List the bouts of an event that were filmed, grouped by division (weight class). Returns each bout, its competitors, how many camera angles exist and the URL to watch it. Visible to anyone the event reaches; returns "not found" for an event the acting user cannot see.')]
class ListEventVideosTool extends BaseTool
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
        // uuid is real to somebody who may not see it.
        if (! $event || ! app(EventAccess::class)->visible($event, $user)) {
            return Response::error('Event not found.');
        }

        $divisions = collect(app(VideoLibrary::class)->forEvent($event))
            ->map(fn (array $d) => [
                'division' => $d['title'],
                'category' => $d['subtitle'],
                'filmed' => $d['count'],
                'bouts' => collect($d['bouts'])->map(fn (array $b) => [
                    'match_no' => $b['match_no'],
                    'round' => $b['round'],
                    'court' => $b['court'],
                    'red' => $b['arena']['red']['name'] ?? null,
                    'blue' => $b['arena']['blue']['name'] ?? null,
                    'score' => $b['score'],
                    'angles' => $b['angles'],
                    'duration_seconds' => $b['duration'],
                    // Needs an authenticated browser session; listed so an
                    // integration can hand a person the link.
                    'watch_url' => $b['url'],
                ])->values(),
            ])
            ->values();

        return Response::json([
            'event' => ['uuid' => $event->uuid, 'title' => $event->title],
            'filmed_bouts' => (int) $divisions->sum('filmed'),
            'divisions' => $divisions,
        ]);
    }
}
