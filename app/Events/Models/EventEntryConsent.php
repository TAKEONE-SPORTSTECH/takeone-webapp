<?php

namespace App\Events\Models;

use App\Members\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One acceptance of an event's rules (App\Events\Support\EntryConsent).
 * Written by the server only — never mass-assigned from a request.
 */
class EventEntryConsent extends Model
{
    protected $guarded = ['*'];

    protected $casts = [
        'statements' => 'array',
        'accepted_at' => 'datetime',
        'subject_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $c) => $c->uuid ??= (string) Str::uuid());
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
