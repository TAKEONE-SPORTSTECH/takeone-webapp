<?php

namespace App\EventLab\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * A person on a start list. NOT a platform member.
 *
 * This is the whole point of the sandbox. Today an entry has to name a `users`
 * row, so every double registration, every misspelt name and every athlete who
 * never turned up leaves a permanent account behind. Here they leave a row on
 * one event's start list, and the platform's member list never hears about it
 * unless somebody decides, afterwards, that it should.
 *
 * `user_id` is therefore null for everybody during the competition, and is
 * filled in only by promotion. `duplicate_of_id` records a reviewer's judgement
 * that two rows are the same human — it does not delete the loser, because what
 * was typed at the desk is evidence of what happened.
 */
class LabEntrant extends Model
{
    protected $table = 'lab_entrants';

    protected $fillable = [
        'uuid', 'lab_event_id', 'full_name', 'birthdate', 'gender',
        'phone_code', 'phone', 'email', 'nationality', 'photo',
        'belt_colour', 'belt_grade', 'lab_club_id', 'tenant_id',
        'status', 'duplicate_of_id', 'user_id', 'promoted_at', 'created_by',
    ];

    protected $casts = [
        'birthdate' => 'date',
        'promoted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (LabEntrant $entrant) {
            $entrant->uuid = $entrant->uuid ?: (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(LabEvent::class, 'lab_event_id');
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(LabClub::class, 'lab_club_id');
    }

    public function entry(): HasOne
    {
        return $this->hasOne(LabEntry::class, 'lab_entrant_id');
    }

    /** The row a reviewer said this one duplicates. */
    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(LabEntrant::class, 'duplicate_of_id');
    }

    /** Has this person been made a platform member? */
    public function isPromoted(): bool
    {
        return $this->user_id !== null;
    }

    /**
     * The club name to print beside them, whichever kind of club it is.
     *
     * Unattached is a real answer, not a missing one — plenty of people enter
     * as themselves.
     */
    public function clubName(): ?string
    {
        return $this->club?->name;
    }
}
