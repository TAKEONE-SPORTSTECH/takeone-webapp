<?php

namespace App\DevBoard\Controllers;

use App\DevBoard\Models\Card;
use App\DevBoard\Models\Folder;
use App\DevBoard\Services\Folders;
use App\DevBoard\Models\Lane;
use App\DevBoard\Services\Board;
use App\DevBoard\Services\NoteHtml;
use App\DevBoard\Services\TakeNote;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * TakeNote's data: the notebook list, one note, and writing a note's page.
 * Behind the member stack and EnsureBoardAccess, like every board route; a
 * note is addressed by its card's uuid only. Pictures go through the board's
 * own picture door (me.margin.pictures.*), which already scopes them to a card.
 */
class TakeNoteController extends Controller
{
    public function __construct(private TakeNote $takenote, private Board $board, private Folders $folders) {}

    public function data(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'me' => $this->board->person($request->user()),
            'people' => $this->board->people(),
            'lanes' => $this->takenote->lanes(),
            'notes' => $this->takenote->list(),
            'cards' => $this->takenote->cards(), // the action-plan board: cards only, never notes
            'folders' => $this->folders->all(),
            'labels' => app(\App\DevBoard\Services\CardTools::class)->labels(),
            // named connections between notes (the map's labelled lines), when switched on
            'relations' => config('devboard.relations') ? \App\DevBoard\Controllers\TakeNoteRelationController::all() : null,
            // folders shared with this person, the one open now (if any), and how full their OWN notebook is
            'sharing' => \App\DevBoard\Support\Notebooks::sharingOn(),
            'shared' => \App\DevBoard\Controllers\TakeNoteShareController::sharedWith($request->user()),
            'space' => $this->space(),
            'storage' => ($own = \App\DevBoard\Support\Notebooks::for($request->user())) ? \App\DevBoard\Support\Storage::summary($own->id) : null,
            // the room in the notebook being worked in (the owner's, inside a shared folder): checked before an upload starts
            'room' => \App\DevBoard\Support\Notebooks::currentId() ? \App\DevBoard\Support\Storage::summary(\App\DevBoard\Support\Notebooks::currentId()) : null,
        ]);
    }

    /** The shared folder this request works in, as the page shows it: its name and whose it is. */
    private function space(): ?array
    {
        $share = \App\DevBoard\Support\Notebooks::share();
        if (! $share) {
            return null;
        }
        $folder = Folder::whereKey($share->folder_id)->first(['uuid', 'name', 'color']);
        $owner = \App\DevBoard\Models\Notebook::find($share->notebook_id)?->owner;

        return ['uuid' => $share->uuid, 'folder' => $folder?->uuid, 'name' => $folder?->name, 'color' => $folder?->color,
            'owner' => $owner ? \App\DevBoard\Support\FriendInvites::first($owner) : null];
    }

    public function show(Card $card): JsonResponse
    {
        // a note's attached files and links come with it (cards read theirs from the card back)
        $card->loadMissing('attachments.author:id,full_name');
        $files = app(\App\DevBoard\Services\Attachments::class);

        return response()->json(['success' => true, 'note' => $this->takenote->open($card) + [
            'attachment_list' => $card->attachments->map(fn ($a) => $files->payload($a))->values()->all(),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'html' => ['nullable', 'string', 'max:'.config('devboard.limits.note_html')],
            'lane' => ['nullable', 'uuid'], // "Add a card" in a list: the note starts at the bottom of that list
        ]);
        $lane = isset($data['lane']) ? Lane::where('uuid', $data['lane'])->firstOrFail() : null;

        $card = $this->takenote->create($request->user(), (string) ($data['html'] ?? ''), $lane);

        return response()->json(['success' => true, 'message' => __('devboard::messages.note_saved'), 'note' => $this->takenote->open($card)]);
    }

    public function update(Request $request, Card $card): JsonResponse
    {
        $data = $request->validate([
            'version' => ['required', 'integer', 'min:0'],
            'html' => ['nullable', 'string', 'max:'.config('devboard.limits.note_html')],
        ]);

        if (! $this->takenote->write($card, $request->user(), $data['version'], (string) ($data['html'] ?? ''))) {
            return response()->json([
                'success' => false,
                'message' => __('devboard::messages.note_conflict'),
                'note' => $this->takenote->open($card->fresh()),
            ], 409);
        }

        return response()->json(['success' => true, 'message' => __('devboard::messages.note_saved'), 'note' => $this->takenote->open($card->fresh())]);
    }

    /**
     * Put a note on the board: its lane and place in it, who picks it up, and
     * by when. Only the fields sent change; `assignee: null` / `due: null`
     * clear them. The person must be one of the board's people.
     */
    public function place(Request $request, Card $card): JsonResponse
    {
        abort_if($card->kind !== 'card', 422); // a note is not on the board: it is converted into a card first
        $people = collect($this->board->people())->pluck('id')->all();
        $data = $request->validate([
            'lane' => ['nullable', 'uuid'],
            'above' => ['nullable', 'uuid'],
            'below' => ['nullable', 'uuid'],
            'assignee' => ['sometimes', 'nullable', 'integer', Rule::in($people)],
            'due' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after:2000-01-01', 'before:2100-01-01'],
            'priority' => ['sometimes', Rule::in(Card::PRIORITIES)],
        ]);

        $lane = isset($data['lane']) ? Lane::where('uuid', $data['lane'])->firstOrFail() : null;
        $within = $lane ?? $card->lane;
        $neighbour = function (?string $uuid) use ($within, $card) {
            return $uuid ? Card::where('uuid', $uuid)->where('lane_id', $within->id)->whereKeyNot($card->id)->firstOrFail() : null;
        };
        $above = $neighbour($data['above'] ?? null);
        $below = $neighbour($data['below'] ?? null);
        if (! $lane && ($above || $below)) {
            $lane = $card->lane;
        }

        $fields = array_intersect_key($data, array_flip(['assignee', 'due', 'priority']));
        $card = $this->takenote->place($card, $request->user(), $lane, $above, $below, $fields);

        return response()->json(['success' => true, 'message' => __('devboard::messages.saved'), 'note' => $this->takenote->one($card)]);
    }

    /**
     * Bin a note (the card; Undo restores it). `discard` is the quiet path for
     * a note that was started and left empty: it removes the card for good,
     * but only when it is the caller's own, blank, unanswered and fresh — so
     * nothing anybody wrote can be lost through it.
     */
    public function destroy(Request $request, Card $card): JsonResponse
    {
        if ($request->boolean('discard')) {
            $page = $this->takenote->page($card);
            $blank = NoteHtml::parts($page?->body)[0] === '' && ! NoteHtml::assets($page?->body);
            $abort = $card->created_by !== $request->user()->id || ! $blank
                || $card->comments()->exists() || $card->items()->where('type', '!=', 'note')->exists()
                || $card->created_at->lt(now()->subDay());
            abort_if($abort, 422);

            $card->forceDelete();

            return response()->json(['success' => true, 'note' => ['uuid' => $card->uuid]]);
        }

        $card->delete();
        $this->board->log($card, $request->user(), 'deleted');

        return response()->json(['success' => true, 'message' => __('devboard::messages.note_binned'), 'note' => ['uuid' => $card->uuid]]);
    }

    /* ── folders ─────────────────────────────────────────────────────── */

    public function storeFolder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'parent' => ['nullable', 'uuid'],
            'color' => ['nullable', Rule::in(Folders::COLORS)],
        ]);
        abort_if(Folder::count() >= 300, 422);
        $parent = isset($data['parent']) ? Folder::where('uuid', $data['parent'])->firstOrFail() : null;
        $folder = $this->folders->create($request->user(), $data['name'], $parent, $data['color'] ?? null);
        abort_unless($folder, 422, __('devboard::messages.fo_too_deep'));

        return response()->json(['success' => true, 'folder' => $this->folders->payload($folder), 'folders' => $this->folders->all()]);
    }

    public function updateFolder(Request $request, Folder $folder): JsonResponse
    {
        abort_if((int) (\App\DevBoard\Support\Notebooks::share()?->folder_id ?? 0) === (int) $folder->id, 403); // the shared folder itself stays the owner's
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:80'],
            'color' => ['sometimes', Rule::in(Folders::COLORS)],
            'parent' => ['sometimes', 'nullable', 'uuid'],
            'position' => ['sometimes', 'numeric'],
        ]);
        if (array_key_exists('parent', $data)) {
            $data['parent'] = $data['parent'] ? Folder::where('uuid', $data['parent'])->firstOrFail() : null;
        }
        abort_unless($this->folders->update($folder, $data), 422, __('devboard::messages.fo_cant_move'));

        return response()->json(['success' => true, 'folders' => $this->folders->all()]);
    }

    public function destroyFolder(Folder $folder): JsonResponse
    {
        abort_if((int) (\App\DevBoard\Support\Notebooks::share()?->folder_id ?? 0) === (int) $folder->id, 403); // the shared folder itself stays the owner's
        $this->folders->delete($folder);

        return response()->json(['success' => true, 'folders' => $this->folders->all(), 'notes' => $this->takenote->list()]);
    }

    /** A note into a folder, or out of all of them. */
    public function file(Request $request, Card $card): JsonResponse
    {
        abort_if($card->kind !== 'note' && ! $card->in_notebook, 422);
        $data = $request->validate(['folder' => ['nullable', 'uuid']]);
        $folder = isset($data['folder']) ? Folder::where('uuid', $data['folder'])->firstOrFail() : null;
        $this->folders->file($card, $folder);

        return response()->json(['success' => true, 'note' => $this->takenote->one($card->fresh())]);
    }

    /** Pin a note to the top of the notebook, or take it down. Not an edit: the note keeps its place in time. */
    public function pin(Request $request, Card $card): JsonResponse
    {
        abort_if($card->kind !== 'note' && ! $card->in_notebook, 422);
        $data = $request->validate(['pinned' => ['required', 'boolean']]);
        $card->timestamps = false;
        $card->forceFill(['pinned_at' => $data['pinned'] ? now() : null])->save();

        return response()->json(['success' => true, 'note' => $this->takenote->one($card->fresh())]);
    }

    public function restore(Request $request, string $uuid): JsonResponse
    {
        $card = Card::onlyTrashed()->where('uuid', $uuid)->firstOrFail();
        $card->restore();
        $this->board->log($card, $request->user(), 'restored');

        return response()->json(['success' => true, 'message' => __('devboard::messages.restored'), 'note' => $this->takenote->open($card->fresh())]);
    }
}
