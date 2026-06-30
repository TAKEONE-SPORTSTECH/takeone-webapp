<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ClubActivity extends Model
{
    use HasFactory, BelongsToTenant, LogsActivity, HasTranslations;

    /** Translatable fields (notes is internal/admin-only and intentionally excluded). */
    protected array $translatable = ['name', 'description'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('club')
            ->logOnly(['name', 'description', 'duration_minutes', 'frequency_per_week'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'club_activities';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'duration_minutes',
        'frequency_per_week',
        'facility_id',
        'schedule',
        'description',
        'picture_url',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'duration_minutes' => 'integer',
        'frequency_per_week' => 'integer',
        'schedule' => 'array',
    ];

    /**
     * Get the club that owns the activity.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the facility where the activity takes place.
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(ClubFacility::class, 'facility_id');
    }

    /**
     * Get the packages that include this activity.
     */
    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(ClubPackage::class, 'club_package_activities', 'activity_id', 'package_id')
                    ->withPivot('instructor_id', 'schedule')
                    ->withTimestamps();
    }

    /**
     * Instructors directly assigned to teach this class (activity).
     */
    public function instructors(): BelongsToMany
    {
        return $this->belongsToMany(ClubInstructor::class, 'club_activity_instructor', 'activity_id', 'instructor_id')
                    ->withTimestamps();
    }

    /**
     * Equipment catalog for this activity (gear members need to practice it).
     */
    public function equipment(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ClubActivityEquipment::class, 'activity_id');
    }
}
