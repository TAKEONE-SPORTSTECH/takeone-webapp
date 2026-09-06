<?php

namespace App\Mcp\Tools;

use App\Events\Support\EventAccess;
use App\Models\ClubEvent;
use App\Models\EventDocument;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

/**
 * Documents attached to an event (rulebook, entry form, schedule).
 *
 * Metadata only — the bytes are served by the web download route, which runs
 * this same visibility check per request. Listing never exposes a storage path.
 */
#[Title('List event documents')]
#[Description('List the documents attached to an event — title, file type, size and download URL. Visible to anyone the event reaches; returns "not found" for an event the acting user cannot see.')]
class ListEventDocumentsTool extends BaseTool
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

        $documents = $event->documents()->get()->map(fn (EventDocument $d) => [
            'uuid' => $d->uuid,
            'title' => $d->title,
            'type' => $d->extension,
            'mime' => $d->mime,
            'size_bytes' => $d->size,
            'size' => $d->readableSize(),
            'uploaded_at' => $d->created_at?->toIso8601String(),
            // Requires an authenticated browser session; listed so an integration
            // can hand a person the link.
            'download_url' => route('me.events.documents.download', [$event->uuid, $d->uuid]),
        ])->values();

        return Response::json([
            'event' => ['uuid' => $event->uuid, 'title' => $event->title],
            'total' => $documents->count(),
            'documents' => $documents,
        ]);
    }
}
