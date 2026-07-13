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

class ClubInstructor extends Model
{
    use BelongsToTenant, HasFactory, HasTranslations, LogsActivity;

    /** Only the club-specific role is translatable; person name/bio live on User. */
    protected array $translatable = ['role'];

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
        'sort_order',
        'compensation_type',
        'wage_amount',
        'wage_period',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'rating' => 'decimal:2',
        'wage_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public const COMPENSATION_VOLUNTEER = 'volunteer';

    public const COMPENSATION_PAID = 'paid';

    public function isPaid(): bool
    {
        return $this->compensation_type === self::COMPENSATION_PAID && $this->wage_amount > 0;
    }

    /**
     * Best-effort monthly cost for this instructor. Only a monthly wage maps to a concrete
     * recurring figure; session/hourly rates depend on usage so they return null here.
     */
    public function monthlyWageCost(): ?float
    {
        if (! $this->isPaid() || $this->wage_period !== 'monthly') {
            return null;
        }

        return (float) $this->wage_amount;
    }

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
     * Classes (activities) this instructor teaches — direct assignment.
     */
    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(ClubActivity::class, 'club_activity_instructor', 'instructor_id', 'activity_id')
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
