<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $table = 'members_attendance';

    protected $fillable = [
        'member_id',
        'session_type',
        'trainer_name',
        'session_datetime',
        'status',
        'notes',
    ];

    protected $casts = [
        'session_datetime' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }
}
