<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per ownership transfer of a Business (chain) — the audit trail.
 */
class BusinessOwnershipLog extends Model
{
    protected $fillable = [
        'business_id',
        'from_user_id',
        'to_user_id',
        'changed_by',
        'clubs_reassigned',
        'clubs_reassigned_count',
        'note',
    ];

    protected $casts = [
        'clubs_reassigned' => 'boolean',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
