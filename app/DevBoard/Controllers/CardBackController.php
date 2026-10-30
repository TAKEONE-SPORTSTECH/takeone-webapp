<?php

namespace App\DevBoard\Controllers;

use App\DevBoard\Models\Attachment;
use App\DevBoard\Models\Card;
use App\DevBoard\Models\Comment;
use App\DevBoard\Models\Item;
use App\DevBoard\Models\Lane;
use App\DevBoard\Models\Step;
use App\DevBoard\Services\Attachments;
use App\DevBoard\Services\Board;
use App\DevBoard\Services\CardBack;
use App\DevBoard\Services\TakeNote;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The open card (TakeNote's board): its whole detail, converting a note into a
 * card and back, attachments (files and links), comments, checklists and the
 * cover. Behind the member stack and EnsureBoardAccess, like every board route;
 * everything is addressed by uuid, and every child record is looked up WITHIN
 * the card named in the address — a uuid from another card is a 404.
 */
class CardBackController extends Controller
{
    public function __construct(private CardBack $back, private TakeNote $takenote, private Attachments $attachments, private Board $board) {}

    public function show(Card $card): JsonResponse
    {
        return response()->json(['success' => true, 'card' => $this->back->detail($card)]);
    }

    /** A note becomes a card (to a list on the board), or a card goes back to the notebook. */
    public function convert(Request $request, Card $card): JsonResponse
    {
        $data = $request->validate(['to' => ['required', Rule::in(Card::KINDS)], 'lane' => ['nullable', 'uuid']]);
        abort_if($card->kind === $data['to'], 422);
        $lane = isset($data['lane']) ? Lane::where('uuid', $data['lane'])->firstOrFail() : null;

        $card = $this->takenote->convert($card, $request->user(), $data['to'], $lane);

        return response()->json(['success' => true, 'message' => __($data['to'] === 'card' ? 'devboard::messages.cb_now_card' : 'devboard::messages.cb_now_note'), 'note' => $this->takenote->one($card)]);
    }

    /* ── attachments ─────────────────────────────────────────────────── */

    public function attachFile(Request $request, Card $card): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:'.((int) config('devboard.limits.file_mb', 10) * 1024)]]);
        $this->roomFor($card);
        \App\DevBoard\Support\Storage::guard((int) $card->notebook_id, (int) $request->file('file')->getSize()); // the notebook's 1 GB
        $a = $this->attachments->storeFile($card, $request->user(), $request->file('file'));
        if (! $a) {
            return response()->json(['success' => false, 'message' => __('devboard::messages.cb_file_refused')], 422);
        }

        return response()->json(['success' => true, 'attachment' => $this->attachments->payload($a), 'note' => $this->takenote->one($card)]);
    }

    public function attachLink(Request $request, Card $card): JsonResponse
    {
        $data = $request->validate(['url' => ['required', 'string', 'max:1000'], 'name' => ['nullable', 'string', 'max:200']]);
        $this->roomFor($card);
        $a = $this->attachments->storeLink($card, $request->user(), $data['url'], $data['name'] ?? null);
        if (! $a) {
            return response()->json(['success' => false, 'message' => __('devboard::messages.cb_link_refused')], 422);
        }

        return response()->json(['success' => true, 'attachment' => $this->attachments->payload($a), 'note' => $this->takenote->one($card)]);
    }

    public function renameAttachment(Request $request, Card $card, string $attachment): JsonResponse
    {
        $a = $this->attachmentOf($card, $attachment);
        $data = $request->validate(['name' => ['required', 'string', 'max:200']]);
        $a->update(['name' => trim(preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', ' ', $data['name'])) ?: $a->name]);

        return response()->json(['success' => true, 'attachment' => $this->attachments->payload($a)]);
    }

    public function removeAttachment(Request $request, Card $card, string $attachment): JsonResponse
    {
        $a = $this->attachmentOf($card, $attachment);
        if ($card->cover_uuid === $a->uuid) {
            $card->forceFill(['cover_uuid' => null])->save();
        }
        $a->delete(); // the file first (DeletesUploadedFiles), then the row
        $this->board->log($card, $request->user(), 'detached', ['name' => $a->name]);

        return response()->json(['success' => true, 'attachment' => ['uuid' => $a->uuid], 'note' => $this->takenote->one($card->fresh())]);
    }

    /** A file, to save (always as a download; a picture may also be shown inline for its preview). */
    public function download(Request $request, Card $card, string $attachment): StreamedResponse
    {
        $a = $this->attachmentOf($card, $attachment);
        abort_unless($a->kind === 'file' && $a->path && Storage::disk('local')->exists($a->path), 404);
        $inline = $request->boolean('preview') && in_array($a->ext, Attachments::IMAGES, true);

        return Storage::disk('local')->response($a->path, $a->name, [
            'Content-Type' => $inline ? $a->mime : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, max-age=300',
        ], $inline ? 'inline' : 'attachment');
    }

    /* ── comments ────────────────────────────────────────────────────── */

    public function comment(Request $request, Card $card): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.config('devboard.limits.comment', 4000)],
            'mentions' => ['array', 'max:20'], 'mentions.*' => ['integer'], // the @people named in it (kept to the board's people)
        ]);
        $c = $this->back->addComment($card, $request->user(), $data['body']);
        app(\App\DevBoard\Services\CardTools::class)->commented($card, $request->user(), $c, $data['mentions'] ?? []);

        return response()->json(['success' => true, 'comment' => $this->back->comment($c)]);
    }

    public function editComment(Request $request, Card $card, string $comment): JsonResponse
    {
        $c = $this->ownComment($request, $card, $comment);
        $data = $request->validate(['body' => ['required', 'string', 'max:'.config('devboard.limits.comment', 4000)]]);
        $c->update(['body' => trim($data['body'])]);

        return response()->json(['success' => true, 'comment' => $this->back->comment($c)]);
    }

    public function deleteComment(Request $request, Card $card, string $comment): JsonResponse
    {
        $c = $this->ownComment($request, $card, $comment);
        $c->delete();

        return response()->json(['success' => true, 'comment' => ['uuid' => $c->uuid]]);
    }

    /* ── checklists ──────────────────────────────────────────────────── */

    public function addChecklist(Request $request, Card $card): JsonResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:120']]);
        abort_if($card->items()->where('type', 'checklist')->count() >= 10, 422);
        $this->back->addChecklist($card, $request->user(), $data['title']);

        return response()->json(['success' => true, 'checklists' => $this->back->checklists($card)]);
    }

    public function removeChecklist(Card $card, string $list): JsonResponse
    {
        $this->checklistOf($card, $list)->delete();

        return response()->json(['success' => true, 'checklists' => $this->back->checklists($card)]);
    }

    public function addStep(Request $request, Card $card, string $list): JsonResponse
    {
        $data = $request->validate(['text' => ['required', 'string', 'max:500']]);
        abort_unless($this->back->addStep($this->checklistOf($card, $list), $data['text']), 422);

        return response()->json(['success' => true, 'checklists' => $this->back->checklists($card)]);
    }

    public function updateStep(Request $request, Card $card, string $step): JsonResponse
    {
        $data = $request->validate(['text' => ['sometimes', 'string', 'max:500'], 'done' => ['sometimes', 'boolean']]);
        $this->back->updateStep($this->stepOf($card, $step), $request->user(), $data);

        return response()->json(['success' => true, 'checklists' => $this->back->checklists($card)]);
    }

    public function removeStep(Card $card, string $step): JsonResponse
    {
        $this->stepOf($card, $step)->delete();

        return response()->json(['success' => true, 'checklists' => $this->back->checklists($card)]);
    }

    /* ── the cover ───────────────────────────────────────────────────── */

    public function cover(Request $request, Card $card): JsonResponse
    {
        $data = $request->validate(['uuid' => ['nullable', 'uuid']]);
        abort_unless($this->back->setCover($card, $request->user(), $data['uuid'] ?? null), 422);

        return response()->json(['success' => true, 'note' => $this->takenote->one($card->fresh())]);
    }

    /* ── lookups, always inside the card in the address ──────────────── */

    private function attachmentOf(Card $card, string $uuid): Attachment
    {
        return Attachment::where('card_id', $card->id)->where('uuid', $uuid)->firstOrFail();
    }

    private function ownComment(Request $request, Card $card, string $uuid): Comment
    {
        $c = Comment::where('card_id', $card->id)->where('uuid', $uuid)->firstOrFail();
        abort_unless($c->user_id === $request->user()->id, 403); // only its writer edits or removes a comment

        return $c;
    }

    private function checklistOf(Card $card, string $uuid): Item
    {
        return Item::where('card_id', $card->id)->where('type', 'checklist')->where('uuid', $uuid)->firstOrFail();
    }

    private function stepOf(Card $card, string $uuid): Step
    {
        return Step::where('uuid', $uuid)->whereIn('item_id', Item::where('card_id', $card->id)->where('type', 'checklist')->select('id'))->firstOrFail();
    }

    private function roomFor(Card $card): void
    {
        abort_if($card->attachments()->count() >= (int) config('devboard.limits.attachments', 60), 422, __('devboard::messages.cb_too_many'));
    }
}
