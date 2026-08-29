<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One coach's note against a moment of a bout. See the migration for why it
 * hangs off the bout rather than off a video file.
 */
class BoutCoachNote extends Model
{
    use HasUuids;

    protected $fillable = [
        'event_id', 'match_id', 'media_file_id', 'user_id', 'coach_name',
        'emoji', 'note', 'start_seconds', 'end_seconds', 'position_x', 'position_y',
    ];

    protected $casts = [
        'start_seconds' => 'float',
        'end_seconds' => 'float',
        'position_x' => 'float',
        'position_y' => 'float',
    ];

    /** The emoji a coach may pick. Anything else is refused, not sanitised. */
    public const EMOJI = ['🔥', '🤔', '😄', '💪', '⚠️', '👀', '📝'];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(EventMatch::class, 'match_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The note as the player consumes it.
     *
     * Only what the panel draws. The author's user id is deliberately absent —
     * the displayed name is `coach_name`, and leaking who typed it tells a
     * viewer something about the club's staffing that the note does not.
     */
    public function present(): array
    {
        return [
            'uuid' => $this->uuid,
            'start' => (float) $this->start_seconds,
            'end' => $this->end_seconds !== null ? (float) $this->end_seconds : null,
            'note' => (string) $this->note,
            'coach' => (string) $this->coach_name,
            'emoji' => (string) $this->emoji,
            'x' => $this->position_x !== null ? (float) $this->position_x : null,
            'y' => $this->position_y !== null ? (float) $this->position_y : null,
        ];
    }
}
