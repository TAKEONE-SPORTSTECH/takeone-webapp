<?php

namespace App\Duplicates\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One suspected duplicate the watch found. See the migration for the shape.
 */
class DuplicateFinding extends Model
{
    public const KIND_PERSON = 'person';

    public const KIND_CLUB = 'club';

    public const KIND_EVENT_CLUB = 'event_club';

    public const KIND_ENTRY = 'entry';

    public const OPEN = 'open';

    public const FIXED = 'fixed';

    public const DISMISSED = 'dismissed';

    public const GONE = 'gone';

    protected $table = 'duplicate_findings';

    protected $fillable = [
        'kind', 'signature', 'a_id', 'b_id', 'event_id', 'reasons', 'strength', 'status',
        'fix', 'first_seen_at', 'last_seen_at', 'resolved_at', 'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'reasons' => 'array',
            'fix' => 'array',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $f) {
            $f->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }

    public function isCertain(): bool
    {
        return $this->strength === 'certain';
    }
}
