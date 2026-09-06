<?php

namespace App\EventLab\Controllers;

use App\Http\Controllers\Controller;

use App\Events\Support\EventAccess;
use App\Models\ClubEvent;
use App\Models\EventDocument;
use App\Support\DocumentUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Documents attached to an event — rulebooks, entry forms, schedules.
 *
 * Two different authorization rules, deliberately kept apart:
 *   • upload / delete — only whoever may manage the event.
 *   • download        — anyone the event's scope reaches, re-checked per request.
 *
 * Files live on the private disk, so the ONLY way to them is download(), which
 * runs that check every time. Nothing is web-reachable by path, and the stored
 * filename is random, so a leaked path is neither guessable nor useful.
 */
/*
 * COPY — this file is `app/Http/Controllers/EventDocumentController.php`, copied verbatim into the
 * sandbox on 2026-09-05 and then rewritten ONLY where it had to be:
 *
 *   - the namespace, and an explicit import of the base Controller it used to
 *     inherit by being a neighbour of it;
 *   - `view('personal.…')` / `view('entry.…')` now name the sandbox's own copies
 *     of those templates (`eventlab::…`);
 *   - `route('me.events.…')` / `route('events.public…')` now name the sandbox's
 *     own routes, so a copied screen links to other copied screens.
 *
 * Nothing else was touched, so `diff` against the original still reads clean.
 * The original is untouched and still serves every real event; this copy is
 * reachable only under /testcode and only for a SANDBOX twin.
 */
class EventDocumentController extends Controller
{
    public function __construct(private EventAccess $access) {}

    /** Attach a document. Organiser only. */
    public function store(Request $request, ClubEvent $event, DocumentUpload $uploads)
    {
        $me = Auth::user();
        abort_if($event->is_archived, 404);
        abort_unless($this->access->canManage($event, $me), 403);

        // A body larger than PHP's post_max_size is discarded before Laravel runs:
        // $_POST and $_FILES arrive EMPTY, so plain validation would answer with a
        // misleading "the document field is required". Detect that and say the
        // real thing instead.
        if ($this->postWasTruncated($request)) {
            return response()->json([
                'success' => false,
                'message' => __('personal.event_docs_too_large'),
            ], 413);
        }

        $data = $request->validate([
            // Framework-level guard rails; the real check is the byte inspection below.
            'document' => ['required', 'file', 'max:'.(int) (DocumentUpload::MAX_BYTES / 1024)],
            'title' => ['required', 'string', 'max:120'],
        ]);

        // Folder is built by the app from the event's public id — never from input.
        $stored = $uploads->store($data['document'], 'events/'.$event->uuid.'/documents');

        if ($stored === null) {
            return response()->json([
                'success' => false,
                'message' => __('personal.event_docs_rejected'),
            ], 422);
        }

        $doc = EventDocument::create([
            'event_id' => $event->id,
            'title' => $data['title'],
            'path' => $stored['path'],
            'mime' => $stored['mime'],
            'extension' => $stored['extension'],
            'size' => $stored['size'],
            'uploaded_by' => $me->id,
            'sort_order' => (int) EventDocument::where('event_id', $event->id)->max('sort_order') + 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('personal.event_docs_added'),
            'document' => $this->present($doc, $event),
        ]);
    }

    /** Download a document. Anyone who can see the event. */
    public function download(ClubEvent $event, EventDocument $document)
    {
        $me = Auth::user();
        abort_if($event->is_archived, 404);
        abort_unless($this->access->visible($event, $me), 403);

        // Never let a document from one event be fetched through another's URL.
        abort_unless($document->event_id === $event->id, 404);
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download($document->path, $document->downloadName());
    }

    /** Remove a document. Organiser only. Files go before the row (model trait). */
    public function destroy(ClubEvent $event, EventDocument $document)
    {
        $me = Auth::user();
        abort_unless($this->access->canManage($event, $me), 403);
        abort_unless($document->event_id === $event->id, 404);

        $uuid = $document->uuid;
        $document->delete();

        return response()->json([
            'success' => true,
            'message' => __('personal.event_docs_removed'),
            'uuid' => $uuid,
        ]);
    }

    /**
     * True when PHP dropped the request body for exceeding post_max_size.
     *
     * The signature is a non-zero Content-Length with nothing parsed out of it.
     * Checked before validation so the caller hears "too large" rather than
     * "the document field is required".
     */
    private function postWasTruncated(Request $request): bool
    {
        $length = (int) $request->server('CONTENT_LENGTH', 0);

        if ($length <= 0 || $request->all() !== [] || $request->allFiles() !== []) {
            return false;
        }

        return $length > $this->iniBytes('post_max_size');
    }

    /** Parse a php.ini shorthand size ("40M", "1G") into bytes. 0 = unlimited. */
    private function iniBytes(string $key): int
    {
        $raw = trim((string) ini_get($key));

        if ($raw === '' || $raw === '-1' || $raw === '0') {
            return PHP_INT_MAX;   // unlimited — never report a truncation
        }

        $unit = strtolower(substr($raw, -1));
        $value = (int) $raw;

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }

    /** The shape the UI and the MCP both read. Only fields a viewer may see. */
    public function present(EventDocument $doc, ClubEvent $event): array
    {
        return [
            'uuid' => $doc->uuid,
            'title' => $doc->title,
            'extension' => $doc->extension,
            'size' => $doc->size,
            'size_label' => $doc->readableSize(),
            'icon' => $doc->icon(),
            'url' => route('testcode.me.events.documents.download', [$event->uuid, $doc->uuid]),
        ];
    }
}
