<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClubAffiliation extends Model
{
    protected $fillable = [
        'member_id',
        'club_name',
        'logo',
        'start_date',
        'end_date',
        'location',
        'coaches',
        'description',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'coaches' => 'array',
    ];

    /**
     * Get the member that owns the affiliation.
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    /**
     * Get the skills acquired during this affiliation.
     */
    public function skillAcquisitions(): HasMany
    {
        return $this->hasMany(SkillAcquisition::class);
    }

    /**
     * Get the media associated with this affiliation.
     */
    public function affiliationMedia(): HasMany
    {
        return $this->hasMany(AffiliationMedia::class);
    }

    /**
     * Get the duration of the affiliation in months.
     */
    public function getDurationInMonthsAttribute(): int
    {
        $endDate = $this->end_date ?? now();
        return $this->start_date->diffInMonths($endDate);
    }

    /**
     * Get formatted date range.
     */
    public function getDateRangeAttribute(): string
    {
        $start = $this->start_date->format('M Y');
        $end = $this->end_date ? $this->end_date->format('M Y') : 'Present';
        return $start . ' – ' . $end;
    }

    /**
     * Get detailed formatted duration (years, months, days).
     */
    public function getFormattedDurationAttribute(): string
    {
        $endDate = $this->end_date ?? now();
        $diff = $this->start_date->diff($endDate);

        $parts = [];

        if ($diff->y > 0) {
            $parts[] = $diff->y . ' year' . ($diff->y > 1 ? 's' : '');
        }
        if ($diff->m > 0) {
            $parts[] = $diff->m . ' month' . ($diff->m > 1 ? 's' : '');
        }
        if ($diff->d > 0) {
            $parts[] = $diff->d . ' day' . ($diff->d > 1 ? 's' : '');
        }

        return implode(' ', $parts) ?: 'Same day';
    }
}
