<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ClubInstructor extends Model
{
    use HasFactory, BelongsToTenant, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('club')
            ->logOnly(['user_id', 'role', 'rating'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'club_instructors';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'role',
        'rating',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'rating' => 'decimal:2',
    ];

    /**
     * Get the club that owns the instructor.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the user associated with the instructor.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the packages that include this instructor.
     */
    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(ClubPackage::class, 'club_package_activities', 'instructor_id', 'package_id')
                    ->withTimestamps();
    }

    /**
     * Get the reviews for the instructor.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(InstructorReview::class, 'instructor_id');
    }

    /**
     * Get average rating from reviews.
     */
    public function getAverageRatingAttribute(): float
    {
        return $this->reviews()->avg('rating') ?? 0;
    }

    /**
     * Get total number of reviews.
     */
    public function getReviewsCountAttribute(): int
    {
        return $this->reviews()->count();
    }
}
