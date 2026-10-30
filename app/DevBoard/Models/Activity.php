<?php

namespace App\DevBoard\Models;

use App\DevBoard\Models\Concerns\InNotebookThroughCard;
use App\Members\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** "You moved this to Doing" — the card's own history line. Append-only. */
class Activity extends Model
{
    use InNotebookThroughCard;

    public const UPDATED_AT = null;

    protected $table = 'devboard_activity';

    protected $fillable = ['card_id', 'user_id', 'action', 'meta'];

    protected $casts = ['meta' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
