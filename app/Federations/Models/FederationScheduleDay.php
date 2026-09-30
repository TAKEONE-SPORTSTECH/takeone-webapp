<?php

namespace App\Federations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** One day of an event's schedule. Its number is its place in the order. Public key: uuid. */
class FederationScheduleDay extends Model
{
    protected $fillable = ['federation_event_id', 'position', 'title', 'updated_by'];

    protected $casts = ['position' => 'integer'];

    protected static function booted(): void
    {
        static::creating(fn (FederationScheduleDay $d) => $d->uuid ??= (string) Str::uuid());
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(FederationEvent::class, 'federation_event_id');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(FederationScheduleSlot::class)->orderBy('starts_at')->orderBy('id');
    }
}
