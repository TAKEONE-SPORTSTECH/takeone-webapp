<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ClubPackage extends Model
{
    use BelongsToTenant, HasFactory, HasTranslations, LogsActivity;

    /** Fields a club admin can provide an Arabic (or other-locale) version of. */
    protected array $translatable = ['name', 'description'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('club')
            ->logOnly(['name', 'price', 'duration_months', 'type', 'gender', 'age_min', 'age_max', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'club_packages';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'cover_image',
        'type',
        'age_min',
        'age_max',
        'gender',
        'price',
        'registration_fee',
        'duration_months',
        'session_count',
        'description',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'age_min' => 'integer',
        'age_max' => 'integer',
        'price' => 'decimal:2',
        'registration_fee' => 'decimal:2',
        'duration_months' => 'integer',
        'session_count' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get the club that owns the package.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the activities included in the package.
     */
    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(ClubActivity::class, 'club_package_activities', 'package_id', 'activity_id')
            ->withPivot('instructor_id', 'schedule')
            ->withTimestamps();
    }

    /**
     * Get the subscriptions for the package.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(ClubMemberSubscription::class, 'package_id');
    }

    /**
     * Get active subscriptions for the package.
     */
    public function activeSubscriptions(): HasMany
    {
        return $this->hasMany(ClubMemberSubscription::class, 'package_id')
            ->where('status', 'active');
    }

    /**
     * Get the package activities (with instructors).
     */
    public function packageActivities(): HasMany
    {
        return $this->hasMany(ClubPackageActivity::class, 'package_id');
    }
}
