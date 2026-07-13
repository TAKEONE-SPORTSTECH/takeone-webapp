<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A "spouse/partner" edge between two persons.
 *
 * Stored normalised: person_low_id always holds the smaller person id and
 * person_high_id the larger, so (A,B) and (B,A) are the same row and the
 * unique index genuinely prevents duplicate unions regardless of who added it.
 * Always create/look these up through KinshipService so normalisation holds.
 */
class PersonUnion extends Model
{
    protected $fillable = [
        'person_low_id',
        'person_high_id',
        'status',
        'state',
        'started_on',
        'ended_on',
        'created_by_user_id',
        'confirmed_by_user_id',
        'confirmed_at',
    ];

    protected $casts = [
        'started_on' => 'date',
        'ended_on' => 'date',
        'confirmed_at' => 'datetime',
    ];

    public function personLow(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_low_id');
    }

    public function personHigh(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_high_id');
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    /** True while the couple is still together (not divorced/widowed). */
    public function isCurrent(): bool
    {
        return ! in_array($this->state, ['divorced', 'widowed'], true);
    }

    /** Return the id of the partner opposite the given person. */
    public function partnerIdOf(int $personId): ?int
    {
        if ($this->person_low_id === $personId) {
            return $this->person_high_id;
        }
        if ($this->person_high_id === $personId) {
            return $this->person_low_id;
        }

        return null;
    }
}
