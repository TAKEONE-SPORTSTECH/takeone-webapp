<?php

namespace App\Federations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One division's locked draw and the results recorded on it. */
class FederationDraw extends Model
{
    protected $fillable = ['federation_event_id', 'division', 'slots', 'results', 'updated_by'];

    protected $casts = ['slots' => 'array', 'results' => 'array'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FederationEvent::class, 'federation_event_id');
    }
}
