<?php

namespace App\DevBoard\Controllers;

use App\DevBoard\Models\Card;
use App\DevBoard\Models\Relation;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Connecting two notes with a name — Trilium's relations ("depends on", "part
 * of"…), made on the note map or under a note. Both notes are looked up through
 * Card's own scopes, so only notes this person can open right now (their
 * notebook, or the shared folder they are in) can be connected; a uuid from
 * anywhere else is simply not found. One connection per pair of notes: making
 * it again, either way round, renames the one there is.
 */
class TakeNoteRelationController extends Controller
{
    /** Plenty for a notebook; a cap so the map and its endpoint stay cheap. */
    public const MAX = 3000;

    /** Every connection whose two notes are in reach, as the page draws them. */
    public static function all(): array
    {
        if (! config('devboard.relations')) {
            return [];
        }

        return Relation::with(['from:id,uuid', 'to:id,uuid'])->orderBy('id')->limit(self::MAX)->get()
            ->map(fn (Relation $r) => $r->payload())->values()->all();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'uuid'],
            'to' => ['required', 'uuid', 'different:from'],
            'label' => ['nullable', 'string', 'max:40'],
        ]);
        $from = Card::where('uuid', $data['from'])->firstOrFail();
        $to = Card::where('uuid', $data['to'])->firstOrFail();
        $label = self::label($data['label'] ?? null);

        $row = Relation::where(fn ($q) => $q
            ->where(fn ($q) => $q->where('from_card_id', $from->id)->where('to_card_id', $to->id))
            ->orWhere(fn ($q) => $q->where('from_card_id', $to->id)->where('to_card_id', $from->id)))
            ->first();
        if ($row) {
            // already connected: this names it (and points it the way it was drawn this time)
            $row->forceFill(['from_card_id' => $from->id, 'to_card_id' => $to->id, 'label' => $label])->save();
        } else {
            if (Relation::count() >= self::MAX) {
                throw ValidationException::withMessages(['from' => __('devboard::messages.rel_too_many')]);
            }
            $row = Relation::create([
                'notebook_id' => $from->notebook_id,
                'from_card_id' => $from->id,
                'to_card_id' => $to->id,
                'label' => $label,
                'created_by' => $request->user()->id,
            ]);
        }

        return response()->json(['success' => true, 'message' => __('devboard::messages.rel_made'), 'relation' => $row->fresh(['from', 'to'])->payload()]);
    }

    /** Rename a connection, or turn it round. */
    public function update(Request $request, Relation $relation): JsonResponse
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:40'],
            'flip' => ['sometimes', 'boolean'],
        ]);
        if ($request->has('label')) {
            $relation->label = self::label($data['label'] ?? null);
        }
        if (! empty($data['flip'])) {
            [$relation->from_card_id, $relation->to_card_id] = [$relation->to_card_id, $relation->from_card_id];
        }
        $relation->save();

        return response()->json(['success' => true, 'relation' => $relation->fresh(['from', 'to'])->payload()]);
    }

    public function destroy(Relation $relation): JsonResponse
    {
        $relation->delete();

        return response()->json(['success' => true, 'message' => __('devboard::messages.rel_removed'), 'uuid' => $relation->uuid]);
    }

    /** One line of plain words, or nothing. */
    private static function label(?string $label): ?string
    {
        $label = trim(preg_replace('/[\p{C}\s]+/u', ' ', (string) $label) ?? '');

        return $label === '' ? null : mb_substr($label, 0, 40);
    }
}
