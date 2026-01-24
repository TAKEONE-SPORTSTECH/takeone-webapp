<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkillAcquisition extends Model
{
    protected $fillable = [
        'club_affiliation_id',
        'skill_name',
        'icon',
        'duration_months',
        'proficiency_level',
    ];

    protected $casts = [
        'duration_months' => 'integer',
    ];

    /**
     * Get the club affiliation that owns the skill acquisition.
     */
    public function clubAffiliation(): BelongsTo
    {
        return $this->belongsTo(ClubAffiliation::class);
    }

    /**
     * Get formatted duration.
     */
    public function getFormattedDurationAttribute(): string
    {
        $months = $this->duration_months;
        if ($months < 12) {
            return $months . ' month' . ($months > 1 ? 's' : '');
        }

        $years = floor($months / 12);
        $remainingMonths = $months % 12;

        $result = $years . ' year' . ($years > 1 ? 's' : '');
        if ($remainingMonths > 0) {
            $result .= ' ' . $remainingMonths . ' month' . ($remainingMonths > 1 ? 's' : '');
        }

        return $result;
    }

    /**
     * Get proficiency level color for UI.
     */
    public function getProficiencyColorAttribute(): string
    {
        return match($this->proficiency_level) {
            'beginner' => 'text-blue-500',
            'intermediate' => 'text-yellow-500',
            'advanced' => 'text-orange-500',
            'expert' => 'text-red-500',
            default => 'text-gray-500',
        };
    }
}
