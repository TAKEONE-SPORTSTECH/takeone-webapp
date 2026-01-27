<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClubPackage extends Model
{
    use HasFactory;

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
                    ->withPivot('instructor_id')
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
