<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'owner_user_id',
        'club_name',
        'slug',
        'logo',
        'slogan',
        'description',
        'enrollment_fee',
        'commercial_reg_number',
        'vat_reg_number',
        'vat_percentage',
        'email',
        'phone',
        'currency',
        'timezone',
        'country',
        'address',
        'favicon',
        'cover_image',
        'owner_name',
        'owner_email',
        'gps_lat',
        'gps_long',
        'settings',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'gps_lat' => 'decimal:7',
        'gps_long' => 'decimal:7',
        'enrollment_fee' => 'decimal:2',
        'vat_percentage' => 'decimal:2',
        'phone' => 'array',
        'settings' => 'array',
    ];

    /**
     * Get the owner user that owns the tenant.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * Get the members for the tenant.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'memberships')
                    ->withPivot('status')
                    ->withTimestamps();
    }

    /**
     * Get the invoices for the tenant.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get the facilities for the club.
     */
    public function facilities(): HasMany
    {
        return $this->hasMany(ClubFacility::class);
    }

    /**
     * Get the instructors for the club.
     */
    public function instructors(): HasMany
    {
        return $this->hasMany(ClubInstructor::class);
    }

    /**
     * Get the activities for the club.
     */
    public function activities(): HasMany
    {
        return $this->hasMany(ClubActivity::class);
    }

    /**
     * Get the packages for the club.
     */
    public function packages(): HasMany
    {
        return $this->hasMany(ClubPackage::class);
    }

    /**
     * Get the subscriptions for the club.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(ClubMemberSubscription::class);
    }

    /**
     * Get the transactions for the club.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(ClubTransaction::class);
    }

    /**
     * Get the gallery images for the club.
     */
    public function galleryImages(): HasMany
    {
        return $this->hasMany(ClubGalleryImage::class);
    }

    /**
     * Get the social links for the club.
     */
    public function socialLinks(): HasMany
    {
        return $this->hasMany(ClubSocialLink::class);
    }

    /**
     * Get the bank accounts for the club.
     */
    public function bankAccounts(): HasMany
    {
        return $this->hasMany(ClubBankAccount::class);
    }

    /**
     * Get the messages for the club.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ClubMessage::class);
    }

    /**
     * Get the reviews for the club.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(ClubReview::class);
    }

    /**
     * Get the memberships for the club.
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Get approved reviews for the club.
     */
    public function approvedReviews(): HasMany
    {
        return $this->hasMany(ClubReview::class)->where('is_approved', true);
    }

    /**
     * Get the average rating for the club.
     */
    public function getAverageRatingAttribute(): float
    {
        return $this->approvedReviews()->avg('rating') ?? 0;
    }

    /**
     * Get active members count.
     */
    public function getActiveMembersCountAttribute(): int
    {
        return $this->subscriptions()->where('status', 'active')->distinct('user_id')->count('user_id');
    }

    /**
     * Get the club URL.
     */
    public function getUrlAttribute(): string
    {
        return url("/club/{$this->slug}");
    }
}
