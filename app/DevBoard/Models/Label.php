<?php

namespace App\DevBoard\Models;

use App\DevBoard\Models\Concerns\HasBoardUuid;
use App\DevBoard\Models\Concerns\InNotebook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A label of the board (Trello's): a colour, and a name if it has one. A card
 * carries any number of them.
 */
class Label extends Model
{
    use HasBoardUuid, InNotebook;

    /** Trello's label colours, light to dark. */
    public const COLORS = [
        '#4bce97', '#f5cd47', '#fea362', '#f87168', '#9f8fef', '#579dff', '#6cc3e0', '#94c748', '#e774bb', '#8590a2',
        '#1f845a', '#946f00', '#c25100', '#c9372c', '#6e5dc6', '#0c66e4', '#227d9b', '#5b7f24', '#ae4787', '#626f86',
    ];

    protected $table = 'devboard_labels';

    protected $fillable = ['uuid', 'name', 'color', 'position'];

    protected $casts = ['position' => 'float'];

    public function cards(): BelongsToMany
    {
        return $this->belongsToMany(Card::class, 'devboard_card_label');
    }
}
