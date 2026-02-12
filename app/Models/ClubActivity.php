<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ClubActivity extends Model
{
    use HasFactory;

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
}
