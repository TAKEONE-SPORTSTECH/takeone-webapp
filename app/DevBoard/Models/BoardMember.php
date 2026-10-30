<?php

namespace App\DevBoard\Models;

use App\Members\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A person allowed onto the board, and the ink colour their work is drawn in. */
class BoardMember extends Model
{
    protected $table = 'devboard_members';

    protected $fillable = ['user_id', 'ink', 'added_by'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
