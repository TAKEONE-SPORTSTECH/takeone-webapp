<?php

namespace App\Events\League\Models;

use App\Models\ClubEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One match in a league season.
 *
 * `played` is decided by the SCORES, not by the clock: a fixture whose date has
 * passed but whose result nobody has entered is still unplayed, and the table
 * must not count it.
 */
class LeagueFixture extends Model
{
    public const SCHEDULED = 'scheduled';

    public const PLAYED = 'played';

    public const POSTPONED = 'postponed';

    public const CANCELLED = 'cancelled';

    /** Every state a fixture may be in, for validation. */
    public const STATUSES = [self::SCHEDULED, self::PLAYED, self::POSTPONED, self::CANCELLED];

    protected $fillable = [
        'event_id', 'uuid', 'matchday', 'home_team_id', 'away_team_id',
        'kickoff_at', 'venue', 'home_score', 'away_score', 'status', 'recorded_by', 'recorded_at',
    ];

    protected $casts = [
        'kickoff_at' => 'datetime',
        'recorded_at' => 'datetime',
        'home_score' => 'integer',
        'away_score' => 'integer',
        'matchday' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $fixture) {
            $fixture->uuid ??= (string) Str::uuid();
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    public function home(): BelongsTo
    {
        return $this->belongsTo(LeagueTeam::class, 'home_team_id');
    }

    public function away(): BelongsTo
    {
        return $this->belongsTo(LeagueTeam::class, 'away_team_id');
    }

    /** Has a result actually been entered? */
    public function isPlayed(): bool
    {
        return $this->status === self::PLAYED
            && $this->home_score !== null
            && $this->away_score !== null;
    }

    /** The winning team's id, or null for a draw (or an unplayed fixture). */
    public function winnerId(): ?int
    {
        if (! $this->isPlayed() || $this->home_score === $this->away_score) {
            return null;
        }

        return $this->home_score > $this->away_score ? $this->home_team_id : $this->away_team_id;
    }
}
