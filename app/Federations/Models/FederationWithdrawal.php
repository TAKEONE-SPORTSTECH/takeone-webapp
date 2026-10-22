<?php

namespace App\Federations\Models;

use App\Members\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** One withdrawal action on a federation event: who, when, why, and which athletes. */
class FederationWithdrawal extends Model
{
    protected $fillable = ['federation_event_id', 'user_id', 'scope', 'reason', 'count', 'athletes', 'ip'];

    protected $casts = ['athletes' => 'array', 'count' => 'integer'];

    protected static function booted(): void
    {
        static::creating(fn (self $w) => $w->uuid ??= (string) Str::uuid());
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(FederationEvent::class, 'federation_event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(FederationEntryAthlete::class, 'withdrawal_id');
    }
}
