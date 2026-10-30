<?php

namespace App\DevBoard\Models;

use App\DevBoard\Models\Concerns\HasBoardUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A named connection between two notes (TakeNoteRelationController). Every
 * query — one fetched by its own uuid included — sees only the connections
 * whose BOTH notes this person can see now: their notebook, inside a shared
 * folder only that folder's notes, never a note in the bin.
 */
class Relation extends Model
{
    use HasBoardUuid;

    protected $table = 'devboard_relations';

    protected $fillable = ['uuid', 'notebook_id', 'from_card_id', 'to_card_id', 'label', 'created_by'];

    protected static function booted(): void
    {
        static::addGlobalScope('visible', function (Builder $q) {
            // Card's own scopes (notebook, shared-folder fence, bin) decide which notes are in reach
            $q->whereIn('devboard_relations.from_card_id', Card::query()->select('devboard_cards.id'))
                ->whereIn('devboard_relations.to_card_id', Card::query()->select('devboard_cards.id'));
        });
    }

    public function from(): BelongsTo
    {
        return $this->belongsTo(Card::class, 'from_card_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(Card::class, 'to_card_id');
    }

    /** What the page draws: the two notes by uuid, and the name of the connection. */
    public function payload(): array
    {
        return ['uuid' => $this->uuid, 'from' => $this->from?->uuid, 'to' => $this->to?->uuid, 'label' => $this->label];
    }
}
