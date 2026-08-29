<?php

namespace App\Events\OpenMat;

use App\Models\ClubEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The six characters printed on one mat.
 *
 * Its own row rather than a cache entry, because the cache here is the FILE
 * driver: a code that lived only in the cache was voided by any deploy, in the
 * middle of a session, with a failure message deliberately indistinguishable
 * from a typo. See the migration for the whole argument.
 *
 * The alphabet has no O/0 and no I/1: this is read off a screen across a room
 * and typed by somebody in a hurry.
 */
class OpenMatCode extends Model
{
    protected $table = 'open_mat_codes';

    protected $fillable = ['event_id', 'court', 'code', 'rotated_at'];

    protected $casts = ['rotated_at' => 'datetime'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }
}
