<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One named, priced thing an event sells.
 *
 * "Gi", "No-Gi", "Event T-shirt", "Banquet seat", "Ringside ticket". An entrant
 * ticks any number of them and pays the event's base fee plus what they ticked.
 *
 * The base fee stays on the event (`participant_fee_amount` /
 * `spectator_fee_amount`), so an event with no options behaves exactly as it did
 * before these existed — which is the whole reason the roughly nine hundred
 * places that read a fee did not have to change.
 */
class EventFeeOption extends Model
{
    use HasFactory;

    protected $table = 'event_fee_options';

    protected $fillable = ['uuid', 'event_id', 'role', 'label', 'amount', 'is_active', 'sort'];

    protected $casts = [
        'amount' => 'decimal:3',
        'is_active' => 'boolean',
        'sort' => 'integer',
    ];

    /** The roles that may carry options — the same two the event's own fees use. */
    public const ROLES = ['participant', 'spectator'];

    protected static function booted(): void
    {
        // A public key on every row, without every write path remembering to
        // make one. This id travels to the browser and comes back on an entry,
        // so it may never be the autoincrement (Unpredictable Resource
        // Identifiers).
        static::creating(function (self $option) {
            $option->uuid ??= (string) Str::uuid();
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    /** Ordered as the organiser arranged them, with a stable tiebreak. */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort')->orderBy('id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForRole($query, string $role)
    {
        return $query->where('role', $role);
    }
}
