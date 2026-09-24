<?php

namespace App\Federations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One federation's Arabic / French wording for one English interface string. */
class FederationString extends Model
{
    protected $fillable = ['federation_id', 'key', 'ar', 'fr', 'updated_by'];

    public function federation(): BelongsTo
    {
        return $this->belongsTo(Federation::class);
    }
}
