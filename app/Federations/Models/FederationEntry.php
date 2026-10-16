<?php

namespace App\Federations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** One club's entry into one federation event. */
class FederationEntry extends Model
{
    protected $fillable = ['federation_event_id', 'federation_club_id', 'club_application_id', 'ref', 'teams', 'officials', 'payments', 'log'];

    protected $casts = ['teams' => 'array', 'officials' => 'array', 'payments' => 'array', 'log' => 'array', 'officials_synced' => 'array'];

    protected static function booted(): void
    {
        static::creating(fn (FederationEntry $e) => $e->uuid ??= (string) Str::uuid());
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(FederationEvent::class, 'federation_event_id');
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(FederationClub::class, 'federation_club_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(FederationClubApplication::class, 'club_application_id');
    }

    public function athletes(): HasMany
    {
        return $this->hasMany(FederationEntryAthlete::class);
    }
}
