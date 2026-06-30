<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DuelWitness extends Model
{
    protected $table = 'duel_witnesses';

    protected $fillable = ['duel_id', 'user_id', 'status', 'name', 'rating', 'comment', 'added_by'];

    protected $casts = ['rating' => 'integer'];

    public function duel(): BelongsTo
    {
        return $this->belongsTo(Duel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
