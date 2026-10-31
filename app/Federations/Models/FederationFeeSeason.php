<?php

namespace App\Federations\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One season of a federation's yearly fees: when it runs, what a club pays to
 * be recognised for it, and how long recognition lasts past its end (grace).
 * The athlete licence is an option a federation may switch on per season.
 */
class FederationFeeSeason extends Model
{
    public const PAYER_CLUB = 'club';
    public const PAYER_ATHLETE = 'athlete';

    protected $fillable = [
        'federation_id', 'name', 'starts_on', 'ends_on', 'due_on', 'grace_days',
        'club_fee', 'currency', 'athlete_licence', 'athlete_fee', 'athlete_payer', 'created_by',
    ];

    protected $casts = [
        'starts_on' => 'immutable_date',
        'ends_on' => 'immutable_date',
        'due_on' => 'immutable_date',
        'grace_days' => 'integer',
        'club_fee' => 'decimal:3',
        'athlete_fee' => 'decimal:3',
        'athlete_licence' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $s) => $s->uuid ??= (string) Str::uuid());
    }

    public function federation(): BelongsTo
    {
        return $this->belongsTo(Federation::class);
    }

    public function fees(): HasMany
    {
        return $this->hasMany(FederationFee::class, 'season_id');
    }

    /** The last day a fee paid for this season still recognises the club. */
    public function recognisedUntil(): CarbonImmutable
    {
        return $this->ends_on->addDays(max(0, (int) $this->grace_days));
    }

    public function covers(CarbonImmutable $day): bool
    {
        return $day->betweenIncluded($this->starts_on, $this->recognisedUntil());
    }
}
