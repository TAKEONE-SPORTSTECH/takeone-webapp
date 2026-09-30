<?php

namespace App\Ranks\Models;

use App\Clubs\Models\ClubActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A club activity's link to its sport package and style. */
class ActivityPackage extends Model
{
    protected $table = 'activity_packages';

    protected $fillable = ['activity_id', 'sport', 'style', 'matched_by'];

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->uuid ??= (string) \Illuminate\Support\Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(ClubActivity::class, 'activity_id');
    }
}
