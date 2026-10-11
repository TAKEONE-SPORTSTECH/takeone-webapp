<?php

namespace App\Mcp\Tools;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventPhoto;
use App\Events\Support\EventAccess;
use App\Events\Support\EventPhotos;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

/**
 * The photographs in an event's gallery.
 *
 * Metadata and addresses only — the bytes are served by the web route, which
 * re-runs the same visibility check per request. Never a storage path.
 */
#[Title('List event photos')]
#[Description('List the photographs in an event\'s gallery — size, upload time and the image URLs (thumbnail and full). Visible to anyone the event reaches; returns "not found" for an event the acting user cannot see. Empty while the photo gallery is switched off.')]
class ListEventPhotosTool extends BaseTool
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
        if (! $event || ! app(EventAccess::class)->visible($event, $user)) {
            return Response::error('Event not found.');
        }

        $photos = EventPhotos::enabled()
            ? $event->photos()->limit(1000)->get()->map(fn (EventPhoto $p) => [
                'uuid' => $p->uuid,
                'width' => $p->width,
                'height' => $p->height,
                'caption' => $p->caption,
                'uploaded_at' => $p->created_at?->toIso8601String(),
                'thumb_url' => route('events.photos.show', ['event' => $event->uuid, 'photo' => $p->uuid, 'size' => 'thumb']),
                'full_url' => route('events.photos.show', ['event' => $event->uuid, 'photo' => $p->uuid, 'size' => 'full']),
            ])->values()
            : collect();

        return Response::json([
            'event' => ['uuid' => $event->uuid, 'title' => $event->title],
            'enabled' => EventPhotos::enabled(),
            'total' => $photos->count(),
            'photos' => $photos,
        ]);
    }
}
