<?php

namespace App\Ranks\Models;

use App\Clubs\Models\ClubActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** A belt test held for one ranked activity. */
class Grading extends Model
{
    protected $fillable = ['activity_id', 'title', 'held_on', 'status', 'created_by', 'finished_at'];

    protected $casts = ['held_on' => 'date', 'finished_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(fn (self $g) => $g->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string { return 'uuid'; }

    public function activity(): BelongsTo { return $this->belongsTo(ClubActivity::class, 'activity_id'); }

    public function candidates(): HasMany { return $this->hasMany(GradingCandidate::class); }
}
