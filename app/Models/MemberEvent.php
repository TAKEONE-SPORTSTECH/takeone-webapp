<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberEvent extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'event_date',
        'location',
        'role',
        'result',
        'notes',
    ];

    protected $casts = [
        'event_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
