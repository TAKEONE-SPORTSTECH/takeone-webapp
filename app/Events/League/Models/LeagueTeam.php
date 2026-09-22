<?php

namespace App\Events\League\Models;

use App\Clubs\Models\Tenant;
use App\Events\Models\ClubEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** One side in a league: a name, optionally a club of ours behind it. */
class LeagueTeam extends Model
{
    protected $fillable = [
        'event_id', 'uuid', 'name', 'short_name', 'tenant_id', 'logo', 'country', 'withdrawn_at', 'sort_order',
    ];

    protected $casts = ['withdrawn_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (self $team) {
            $team->uuid ??= (string) Str::uuid();
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function homeFixtures(): HasMany
    {
        return $this->hasMany(LeagueFixture::class, 'home_team_id');
    }

    public function awayFixtures(): HasMany
    {
        return $this->hasMany(LeagueFixture::class, 'away_team_id');
    }

    public function isPlaying(): bool
    {
        return $this->withdrawn_at === null;
    }
}
