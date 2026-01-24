<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TournamentEvent extends Model
{
    protected $fillable = [
        'user_id',
        'club_affiliation_id',
        'title',
        'type',
        'sport',
        'date',
        'time',
        'location',
        'participants_count',
    ];

    protected $casts = [
        'date' => 'date',
        'time' => 'datetime:H:i',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clubAffiliation(): BelongsTo
    {
        return $this->belongsTo(ClubAffiliation::class);
    }

    public function performanceResults(): HasMany
    {
        return $this->hasMany(PerformanceResult::class);
    }

    public function notesMedia(): HasMany
    {
        return $this->hasMany(NotesMedia::class);
    }
}
