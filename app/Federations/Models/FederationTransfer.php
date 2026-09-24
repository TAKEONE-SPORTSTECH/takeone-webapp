<?php

namespace App\Federations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A club asking to take over an athlete registered with another club. The
 * federation decides; approving moves only the federation record.
 */
class FederationTransfer extends Model
{
    protected $fillable = [
        'federation_id', 'federation_athlete_id', 'from_club_id', 'from_application_id', 'to_club_id', 'to_application_id',
        'requested_by', 'status', 'old_club', 'due_at', 'decided_at', 'decided_by',
    ];

    protected $casts = ['due_at' => 'datetime', 'decided_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(fn (FederationTransfer $t) => $t->uuid ??= (string) Str::uuid());
    }

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(FederationAthlete::class, 'federation_athlete_id');
    }
}
