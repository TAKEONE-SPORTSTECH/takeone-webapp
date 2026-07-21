<?php

namespace App\Models;

use App\Traits\DeletesUploadedFiles;
use App\Traits\HasVerificationState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TournamentEvent extends Model
{
    use DeletesUploadedFiles;
    use HasVerificationState;

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

    /**
     * Evidence lives on the private local disk; purge it before the row is removed.
     * verification_status / verification_method / verified_* are set ONLY by
     * App\Services\AchievementVerificationService — deliberately never fillable.
     */
    protected array $fileUploads = [
        'evidence_path' => 'local',
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

    /** The club that may confirm this claim (the named affiliation's platform club). */
    public function attestingTenant(): ?Tenant
    {
        return $this->clubAffiliation?->tenant;
    }

    public function attestationLabel(): string
    {
        return trim(($this->title ?? '').' · '.($this->sport ?? ''), ' ·');
    }
}
