<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Models\ClubAchievement;
use App\Models\ClubActivity;
use App\Models\ClubAffiliation;
use App\Models\ClubBankAccount;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\ClubFacility;
use App\Models\ClubGalleryImage;
use App\Models\ClubInstructor;
use App\Models\ClubMemberSubscription;
use App\Models\ClubMessage;
use App\Models\ClubNotification;
use App\Models\ClubPackage;
use App\Models\ClubPerk;
use App\Models\ClubReview;
use App\Models\ClubSocialLink;
use App\Models\ClubTimelinePost;
use App\Models\ClubTimelinePostComment;
use App\Models\ClubTimelinePostLike;
use App\Models\ClubTransaction;
use App\Models\Invoice;
use App\Models\Membership;
use App\Models\UserNotification;

class Tenant extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    /**
     * Clean up all orphan-prone records when a tenant is soft-deleted.
     * DB cascades only fire on hard delete; soft delete leaves these behind.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $tenant) {
            $id = $tenant->id;

            // Memberships & subscriptions
            Membership::where('tenant_id', $id)->delete();
            ClubMemberSubscription::where('tenant_id', $id)->delete();

            // Instructor records
            ClubInstructor::where('tenant_id', $id)->delete();

            // Affiliations (cascade skills & media)
            ClubAffiliation::where('tenant_id', $id)->each(function ($aff) {
                $aff->skillAcquisitions()->delete();
                $aff->affiliationMedia()->delete();
                $aff->delete();
            });

            // Club structure
            ClubActivity::where('tenant_id', $id)->delete();
            ClubFacility::where('tenant_id', $id)->delete();

            // Packages + their pivot data
            ClubPackage::where('tenant_id', $id)->each(function ($pkg) {
                $pkg->activities()->detach();
                $pkg->delete();
            });

            // Financial records
            ClubTransaction::where('tenant_id', $id)->delete();
            Invoice::where('tenant_id', $id)->delete();
            ClubBankAccount::where('tenant_id', $id)->delete();

            // Media & branding
            ClubGalleryImage::where('tenant_id', $id)->delete();
            ClubSocialLink::where('tenant_id', $id)->delete();

            // Communication
            ClubMessage::where('tenant_id', $id)->delete();

            // Events (cascade registrations)
            ClubEvent::where('tenant_id', $id)->each(function ($event) {
                ClubEventRegistration::where('event_id', $event->id)->delete();
                $event->delete();
            });

            // Timeline (cascade likes & comments)
            ClubTimelinePost::where('tenant_id', $id)->each(function ($post) {
                ClubTimelinePostLike::where('post_id', $post->id)->delete();
                ClubTimelinePostComment::where('post_id', $post->id)->delete();
                $post->delete();
            });

            // Reviews
            ClubReview::where('tenant_id', $id)->delete();

            // Content
            ClubPerk::where('tenant_id', $id)->delete();
            ClubAchievement::where('tenant_id', $id)->delete();

            // Notifications (cascade user notifications)
            ClubNotification::where('tenant_id', $id)->each(function ($notif) {
                UserNotification::where('club_notification_id', $notif->id)->delete();
                $notif->delete();
            });
            UserNotification::where('tenant_id', $id)->delete();

            // Role assignments for this club
            DB::table('user_roles')->where('tenant_id', $id)->delete();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('club')
            ->logOnly(['club_name', 'slug', 'status', 'enrollment_fee', 'vat_percentage', 'owner_user_id', 'email'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

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
        'youtube_url',
        'owner_name',
        'owner_email',
        'gps_lat',
        'gps_long',
        'maps_url',
        'settings',
        'established_date',
        'status',
        'public_profile_enabled',
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
        return $this->hasMany(ClubGalleryImage::class)->orderBy('display_order')->orderBy('id');
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
     * Get the events for the club.
     */
    public function events(): HasMany
    {
        return $this->hasMany(ClubEvent::class)->orderBy('date')->orderBy('start_time');
    }

    /**
     * Get the timeline posts for the club.
     */
    public function timelinePosts(): HasMany
    {
        return $this->hasMany(ClubTimelinePost::class)->orderBy('posted_at', 'desc');
    }

    /**
     * Get the exclusive perks for the club.
     */
    public function perks(): HasMany
    {
        return $this->hasMany(ClubPerk::class)->orderBy('sort_order')->orderBy('id');
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(ClubAchievement::class)->orderBy('sort_order')->orderBy('id');
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
     * Get the club URL with country prefix.
     */
    public function getUrlAttribute(): string
    {
        $country = strtolower($this->country ?? 'bh');
        return route('clubs.show', ['country' => $country, 'slug' => $this->slug]);
    }

    /**
     * Get the lowercase country code.
     */
    public function getCountryCodeAttribute(): string
    {
        return strtolower($this->country ?? 'bh');
    }
}
