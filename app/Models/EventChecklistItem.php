<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One thing that must be true before an event can begin.
 *
 * The organiser writes the list; any appointed official can clear an item.
 * Clearing records who and when — an unsigned checklist is a to-do list, a
 * signed one is a record of who said the hall was ready.
 */
class EventChecklistItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'label',
        'sort_order',
        'checked_at',
        'checked_by',
        'uuid',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'checked_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $item) {
            if (empty($item->uuid)) {
                $item->uuid = (string) Str::uuid();
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function isChecked(): bool
    {
        return $this->checked_at !== null;
    }
}
