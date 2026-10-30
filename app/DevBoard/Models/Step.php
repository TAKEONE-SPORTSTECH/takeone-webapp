<?php

namespace App\DevBoard\Models;

use App\DevBoard\Models\Concerns\HasBoardUuid;
use App\DevBoard\Models\Concerns\InNotebookThroughCard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One step of a checklist item. `pin_no` ties it to a numbered pin on a picture. */
class Step extends Model
{
    use HasBoardUuid, InNotebookThroughCard;

    /** a step hangs under an item (a checklist), which hangs under a card */
    public string $notebookThrough = 'item';

    protected $table = 'devboard_steps';

    protected $fillable = [
        'uuid', 'item_id', 'text', 'done_at', 'done_by', 'assignee_id', 'due_on', 'pin_no', 'position',
    ];

    protected $casts = [
        'position' => 'float',
        'done_at' => 'datetime',
        'due_on' => 'date',
        'pin_no' => 'integer',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
