<?php

namespace App\DevBoard\Services;

use App\DevBoard\Models\Asset;
use App\DevBoard\Models\Card;
use App\DevBoard\Models\Item;
use App\Members\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * A card's notes: formatted text with pictures in it. Ordered by the same
 * fractional key as cards, so dragging a note writes one row.
 */
class Notes
{
    private const GAP = 1024.0;

    public function __construct(private Board $board) {}

    /** @return array<int,array> the card's live notes, in order */
    public function list(Card $card): array
    {
        $notes = $card->items()->where('type', 'note')->with(['author:id,full_name'])->get();
        $editors = User::whereIn('id', $notes->pluck('updated_by')->filter()->unique())->get(['id', 'full_name'])->keyBy('id');

        return $notes->map(fn (Item $n) => $this->payload($n, $editors->get($n->updated_by)))->values()->all();
    }

    public function payload(Item $note, ?User $editor = null): array
    {
        $note->loadMissing('author:id,full_name');
        $editor ??= $note->updated_by ? User::find($note->updated_by, ['id', 'full_name']) : null;

        [$title, $excerpt] = NoteHtml::parts($note->body);

        return [
            'uuid' => $note->uuid,
            'html' => $note->body,
            'title' => $title,
            'excerpt' => $excerpt,
            'assets' => NoteHtml::pictures($note->body),
            'media' => $this->media($note->body),
            'position' => $note->position,
            'version' => $note->version,
            'author' => $this->person($note->author),
            'editor' => $this->person($editor),
            'created_at' => $note->created_at?->toIso8601String(),
            'updated_at' => $note->updated_at?->toIso8601String(),
        ];
    }

    public function create(Card $card, User $by, ?string $html): Item
    {
        $first = $card->items()->where('type', 'note')->min('position');

        $note = $card->items()->create([
            'type' => 'note',
            'body' => NoteHtml::clean($html, $card),
            'position' => $first === null ? self::GAP : $first - self::GAP,
            'created_by' => $by->id,
            'updated_by' => $by->id,
            'version' => 1,
        ]);
        $this->board->log($card, $by, 'note_added');

        return $note;
    }

    public function update(Item $note, User $by, ?string $html): Item
    {
        $body = NoteHtml::clean($html, $note->card);
        if ($body !== $note->body) {
            $note->forceFill(['body' => $body, 'updated_by' => $by->id, 'version' => $note->version + 1])->save();
            $this->board->log($note->card, $by, 'note_edited');
        }

        return $note;
    }

    /** Put $note between $above and $below (either may be null). */
    public function move(Item $note, ?Item $above, ?Item $below): Item
    {
        return DB::transaction(function () use ($note, $above, $below) {
            $a = $above?->position;
            $b = $below?->position;

            if ($a !== null && $b !== null && ($b - $a) < 1e-6) {
                $i = 1;
                Item::withTrashed()->where('card_id', $note->card_id)->whereKeyNot($note->id)->orderBy('position')->pluck('id')
                    ->each(function ($id) use (&$i) { Item::withTrashed()->whereKey($id)->update(['position' => $i++ * self::GAP]); });
                $a = $above->fresh()->position;
                $b = $below->fresh()->position;
            }

            $note->forceFill(['position' => match (true) {
                $a === null && $b === null => self::GAP,
                $a === null => $b - self::GAP,
                $b === null => $a + self::GAP,
                default => ($a + $b) / 2,
            }])->save();

            return $note;
        });
    }

    public function assetPayload(Asset $asset): array
    {
        return ['uuid' => $asset->uuid, 'width' => $asset->width, 'height' => $asset->height] + Clips::describe($asset);
    }

    /** What the editor needs to draw the voice notes and videos in a note, by uuid. */
    public function media(?string $html): array
    {
        $kinds = NoteHtml::kinds($html);
        if (! $kinds['audio'] && ! $kinds['video']) {
            return [];
        }

        return Asset::whereIn('uuid', NoteHtml::assets($html))->whereIn('kind', ['audio', 'video'])->get()
            ->mapWithKeys(fn (Asset $a) => [$a->uuid => Clips::describe($a)])->all();
    }

    private function person(?User $u): ?array
    {
        if (! $u) {
            return null;
        }

        return ['id' => $u->id, 'name' => strtok((string) $u->full_name, ' ') ?: (string) $u->full_name, 'ink' => $this->board->inkFor($u->id)];
    }
}
