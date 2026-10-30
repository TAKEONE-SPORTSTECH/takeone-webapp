<?php

namespace App\DevBoard\Controllers;

use App\DevBoard\Models\Asset;
use App\DevBoard\Models\Card;
use App\DevBoard\Models\Item;
use App\DevBoard\Services\Board;
use App\DevBoard\Services\NoteHtml;
use App\DevBoard\Services\Notes;
use App\DevBoard\Services\Clips;
use App\DevBoard\Services\Ink;
use App\DevBoard\Services\Pictures;
use App\DevBoard\Support\VideoLink;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * A card's notes and the pictures inside them. Behind EnsureBoardAccess like
 * every board route; a note is always resolved THROUGH its card, so a note
 * uuid from one card can never be read or written via another.
 */
class NoteController extends Controller
{
    public function __construct(private Notes $notes, private Board $board) {}

    public function index(Card $card): JsonResponse
    {
        return response()->json(['success' => true, 'notes' => $this->notes->list($card)]);
    }

    public function store(Request $request, Card $card): JsonResponse
    {
        $data = $request->validate(['html' => ['nullable', 'string', 'max:'.config('devboard.limits.note_html')]]);

        $note = $this->notes->create($card, $request->user(), $data['html'] ?? null);

        return $this->answer($card, $this->notes->payload($note->fresh()));
    }

    public function update(Request $request, Card $card, string $note): JsonResponse
    {
        $item = $this->note($card, $note);
        $data = $request->validate([
            'version' => ['required', 'integer'],
            'html' => ['nullable', 'string', 'max:'.config('devboard.limits.note_html')],
        ]);

        if ($data['version'] !== $item->version) {
            return response()->json([
                'success' => false,
                'message' => __('devboard::messages.note_conflict'),
                'note' => $this->notes->payload($item),
            ], 409);
        }

        $item = $this->notes->update($item, $request->user(), $data['html'] ?? null);

        return $this->answer($card, $this->notes->payload($item->fresh()));
    }

    public function move(Request $request, Card $card, string $note): JsonResponse
    {
        $item = $this->note($card, $note);
        $data = $request->validate(['above' => ['nullable', 'uuid'], 'below' => ['nullable', 'uuid']]);

        $above = isset($data['above']) ? $this->note($card, $data['above'], $item) : null;
        $below = isset($data['below']) ? $this->note($card, $data['below'], $item) : null;

        return $this->answer($card, $this->notes->payload($this->notes->move($item, $above, $below)));
    }

    public function destroy(Request $request, Card $card, string $note): JsonResponse
    {
        $item = $this->note($card, $note);
        $item->delete();
        $this->board->log($card, $request->user(), 'note_deleted');

        return $this->answer($card, ['uuid' => $item->uuid], __('devboard::messages.note_binned'));
    }

    public function restore(Request $request, Card $card, string $note): JsonResponse
    {
        $item = $card->items()->onlyTrashed()->where('type', 'note')->where('uuid', $note)->firstOrFail();
        $item->restore();
        $this->board->log($card, $request->user(), 'note_restored');

        return $this->answer($card, $this->notes->payload($item->fresh()), __('devboard::messages.restored'));
    }

    public function upload(Request $request, Card $card, Pictures $pictures): JsonResponse
    {
        $request->validate(['image' => ['required', 'file', 'max:'.(config('devboard.limits.image_mb') * 1024)]]);

        \App\DevBoard\Support\Storage::guard((int) $card->notebook_id, (int) $request->file('image')->getSize()); // the notebook's 1 GB
        $asset = $pictures->store($card, $request->user(), $request->file('image'));
        if (! $asset) {
            return response()->json(['success' => false, 'message' => __('devboard::messages.bad_image')], 422);
        }

        return response()->json(['success' => true, 'asset' => $this->notes->assetPayload($asset)]);
    }

    /** A voice note or a short video, from the phone's recorder or camera. */
    public function uploadMedia(Request $request, Card $card, Clips $clips): JsonResponse
    {
        $request->validate([
            'kind' => ['required', 'in:audio,video'],
            'media' => ['required', 'file', 'max:'.(config('devboard.limits.media_mb') * 1024)],
        ]);

        \App\DevBoard\Support\Storage::guard((int) $card->notebook_id, (int) $request->file('media')->getSize());
        $asset = $clips->store($card, $request->user(), $request->file('media'), $request->input('kind'));
        if (! $asset || $asset->status === 'failed') {
            return response()->json(['success' => false, 'message' => __('devboard::messages.bad_media')], 422);
        }

        return response()->json(['success' => true, 'asset' => $this->notes->assetPayload($asset)]);
    }

    /**
     * A drawing: the picture the phone rendered, plus its strokes so it can be
     * changed later. A marked-up photo names its original (`base`), which must
     * be a picture of THIS card.
     */
    public function ink(Request $request, Card $card, Ink $ink): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'file', 'max:'.(config('devboard.limits.image_mb') * 1024)],
            'kind' => ['required', 'in:sketch,markup'],
            'w' => ['required', 'integer', 'min:1', 'max:4000'],
            'h' => ['required', 'integer', 'min:1', 'max:4000'],
            'strokes' => ['required', 'string', 'max:'.config('devboard.limits.strokes_bytes')],
            'base' => ['nullable', 'uuid'],
        ]);

        \App\DevBoard\Support\Storage::guard((int) $card->notebook_id, (int) $request->file('image')->getSize());
        $strokes = Ink::clean(json_decode($data['strokes'], true), $data['w'], $data['h']);
        $base = null;
        if (! empty($data['base'])) {
            $base = $card->assets()->where('uuid', $data['base'])->where('kind', 'image')->first();
        }
        if ($strokes === null || ($data['kind'] === 'markup' && ! $base) || ($data['kind'] === 'sketch' && ! empty($data['base']))) {
            return response()->json(['success' => false, 'message' => __('devboard::messages.bad_drawing')], 422);
        }

        $asset = $ink->store($card, $request->user(), $request->file('image'), [
            'kind' => $data['kind'], 'w' => $data['w'], 'h' => $data['h'], 'base' => $base?->uuid, 'strokes' => $strokes,
        ]);
        if (! $asset) {
            return response()->json(['success' => false, 'message' => __('devboard::messages.bad_image')], 422);
        }

        return response()->json(['success' => true, 'asset' => $this->notes->assetPayload($asset)]);
    }

    /**
     * What it takes to open a picture for drawing on: its strokes if it is a
     * drawing, and the original to draw over if it is a marked-up photo.
     */
    public function inkShow(Asset $asset): JsonResponse
    {
        abort_unless($asset->kind === 'image', 404);
        $ink = $asset->meta['ink'] ?? null;

        return response()->json([
            'success' => true,
            'ink' => $ink ? ['kind' => $ink['kind'], 'w' => $ink['w'], 'h' => $ink['h'], 'base' => $ink['base'] ?? null, 'strokes' => $ink['strokes']]
                : ['kind' => 'markup', 'w' => $asset->width, 'h' => $asset->height, 'base' => $asset->uuid, 'strokes' => []],
        ]);
    }

    /** Where a video being prepared stands. */
    public function mediaStatus(Asset $asset): JsonResponse
    {
        abort_unless(in_array($asset->kind, ['audio', 'video'], true), 404);

        return response()->json(['success' => true, 'asset' => $this->notes->assetPayload($asset)]);
    }

    /**
     * Markdown (pasted, or read from a .md file on the phone) as a note's own
     * formatting, ready to put in at the caret. Converts only — nothing is
     * stored; the note is cleaned again when it saves.
     */
    public function markdown(Request $request): JsonResponse
    {
        $text = (string) $request->validate(['text' => ['required', 'string', 'max:'.NoteHtml::MARKDOWN_MAX]])['text'];

        return response()->json(['success' => true, 'html' => NoteHtml::fromMarkdown($text) ?? '']);
    }

    /**
     * Which TikTok video a short share link stands for, so a pasted one shows
     * as the video. Only ever follows TikTok (VideoLink); answers null for
     * anything else, and remembers each answer for a month.
     */
    public function videoLink(Request $request): JsonResponse
    {
        $url = (string) $request->validate(['url' => ['required', 'string', 'max:2000']])['url'];
        $embed = VideoLink::isTikTok($url)
            ? (Cache::remember('devboard:videolink:'.sha1($url), 60 * 60 * 24 * 30, fn () => VideoLink::resolve($url) ?? '') ?: null)
            : null;

        return response()->json(['success' => true, 'embed' => $embed]);
    }

    /**
     * A voice note or a video (or, with ?poster=1, a video's first frame), for
     * a board member only. Served as a file so phones can seek (Range).
     */
    public function media(Request $request, Asset $asset)
    {
        abort_unless(in_array($asset->kind, ['audio', 'video'], true) && $asset->status === 'ready', 404);

        $poster = $request->boolean('poster');
        $path = $poster ? $asset->preview_path : $asset->path;
        $disk = Storage::disk('local');
        abort_unless($path && $disk->exists($path), 404);

        return response()->file($disk->path($path), [
            'Content-Type' => $poster ? 'image/webp' : $asset->mime,
            'Cache-Control' => 'private, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
        ]);
    }

    /** A picture, for a board member only. Immutable: a uuid names one file forever. */
    public function asset(Request $request, Asset $asset)
    {
        abort_unless($asset->kind === 'image', 404);

        $path = $request->query('size') === 'preview' && $asset->preview_path ? $asset->preview_path : $asset->path;
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
        ]);
    }

    private function answer(Card $card, array $note, ?string $message = null): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message ?? __('devboard::messages.saved'),
            'note' => $note,
            'card' => $this->board->cardPayload($this->board->fresh($card)),
        ]);
    }

    private function note(Card $card, string $uuid, ?Item $not = null): Item
    {
        return $card->items()->where('type', 'note')->where('uuid', $uuid)
            ->when($not, fn ($q) => $q->whereKeyNot($not->id))
            ->firstOrFail();
    }
}
