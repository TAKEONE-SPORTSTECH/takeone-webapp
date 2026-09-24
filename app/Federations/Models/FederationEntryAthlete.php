<?php

namespace App\Federations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One athlete on a club's entry: weight, participation types, and where they stand. */
class FederationEntryAthlete extends Model
{
    protected $fillable = ['federation_entry_id', 'federation_athlete_id', 'weight', 'types', 'status', 'approval', 'late', 'chargeable', 'withdrawn_at'];

    protected $casts = ['types' => 'array', 'late' => 'boolean', 'chargeable' => 'boolean', 'withdrawn_at' => 'datetime', 'weight' => 'float'];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(FederationEntry::class, 'federation_entry_id');
    }

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(FederationAthlete::class, 'federation_athlete_id');
    }
}
