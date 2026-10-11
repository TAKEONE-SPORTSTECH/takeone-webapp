<?php

namespace App\Events\Models;

use App\Members\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A side exhibition bout — two entrants of an event fought on a mat outside
 * the draw. See the migration: it is never an EventMatch, so nothing that
 * reads the draw, a podium or anybody's record can count it.
 *
 * Numbered per event (EX1, EX2…) so the hall and the organiser can name one.
 */
class EventExhibitionBout extends Model
{
    public const STATUS_RUNNING = 'running';

    public const STATUS_DONE = 'done';

    public const STATUS_ABANDONED = 'abandoned';

    protected $table = 'event_exhibition_bouts';

    protected $fillable = [
        'event_id', 'number', 'court',
        'a_registration_id', 'b_registration_id', 'a_name', 'b_name',
        'a_score', 'b_score', 'winner', 'win_reason', 'win_note',
        'status', 'created_by', 'finished_by', 'finished_at',
    ];

    protected function casts(): array
    {
        return ['finished_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $bout) {
            $bout->uuid ??= (string) Str::uuid();
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    public function aEntry(): BelongsTo
    {
        return $this->belongsTo(ClubEventRegistration::class, 'a_registration_id');
    }

    public function bEntry(): BelongsTo
    {
        return $this->belongsTo(ClubEventRegistration::class, 'b_registration_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** What the hall calls it: EX1, EX2… */
    public function code(): string
    {
        return 'EX'.$this->number;
    }
}
