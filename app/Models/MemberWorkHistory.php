<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberWorkHistory extends Model
{
    protected $table = 'member_work_history';

    protected $fillable = [
        'user_id',
        'title',
        'organization',
        'employment_type',
        'location',
        'start_date',
        'end_date',
        'description',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** A null end_date means the role is ongoing. */
    public function isCurrent(): bool
    {
        return $this->end_date === null;
    }
}
