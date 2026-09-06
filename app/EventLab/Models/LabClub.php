<?php

namespace App\EventLab\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A club as it was named on an entry form, before anybody has decided whether it
 * is a club this platform knows.
 *
 * Three states, and the difference matters when the competition is over:
 *  - `tenant_id` set      → matched to a club that already existed.
 *  - `promoted_tenant_id` → a real club was created FROM this row afterwards.
 *  - neither              → still just a name on a form, which is a perfectly
 *                           good way to finish a competition.
 */
class LabClub extends Model
{
    protected $table = 'lab_clubs';

    protected $fillable = [
        'uuid', 'lab_event_id', 'name', 'country', 'logo',
        'tenant_id', 'promoted_tenant_id', 'promoted_at',
    ];

    protected $casts = [
        'promoted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (LabClub $club) {
            $club->uuid = $club->uuid ?: (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(LabEvent::class, 'lab_event_id');
    }

    public function entrants(): HasMany
    {
        return $this->hasMany(LabEntrant::class, 'lab_club_id');
    }

    /** Has this name been resolved to a real club, by either route? */
    public function isReal(): bool
    {
        return $this->tenant_id !== null || $this->promoted_tenant_id !== null;
    }
}
