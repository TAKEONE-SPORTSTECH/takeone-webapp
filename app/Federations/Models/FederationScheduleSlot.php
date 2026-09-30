<?php

namespace App\Federations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** One session on a schedule day: a time, what happens, where. Public key: uuid. */
class FederationScheduleSlot extends Model
{
    protected $fillable = ['federation_schedule_day_id', 'starts_at', 'ends_at', 'title', 'location', 'updated_by'];

    protected static function booted(): void
    {
        static::creating(fn (FederationScheduleSlot $s) => $s->uuid ??= (string) Str::uuid());
    }

    public function day(): BelongsTo
    {
        return $this->belongsTo(FederationScheduleDay::class, 'federation_schedule_day_id');
    }
}
