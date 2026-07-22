<?php

namespace App\Models;

use App\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Tenant extends Model
{
    use HasFactory, HasTranslations, LogsActivity, SoftDeletes {
        HasTranslations::setTranslation as protected baseSetTranslation;
    }

    /** Fields a club admin can provide an Arabic (or other-locale) version of. */
    protected array $translatable = ['club_name', 'slogan', 'description', 'address', 'registration_terms', 'registration_requirements'];

    /** Translatable fields that hold rich HTML and must be sanitised on write. */
    protected array $richHtmlFields = ['registration_terms', 'registration_requirements'];

    // ── Rich-text sanitisation ─────────────────────────────────────────────
    // The registration terms/requirements accept admin-authored HTML and are
    // later rendered to registrants, so every write path is sanitised here:
    // English base columns via mutators, other locales via setTranslation().

    public function setRegistrationTermsAttribute($value): void
    {
        $this->attributes['registration_terms'] = \App\Support\HtmlSanitizer::clean($value);
    }

    public function setRegistrationRequirementsAttribute($value): void
    {
        $this->attributes['registration_requirements'] = \App\Support\HtmlSanitizer::clean($value);
    }

    public function setTranslation(string $field, string $locale, ?string $value): static
    {
        if (in_array($field, $this->richHtmlFields, true)) {
            $value = \App\Support\HtmlSanitizer::clean($value);
        }

        return $this->baseSetTranslation($field, $locale, $value);
    }

    /**
     * Clean up all orphan-prone records when a tenant is soft-deleted.
     * DB cascades only fire on hard delete; soft delete leaves these behind.
     */
    protected static function booted(): void
    {
        // Auto-link a newly created club to its owner's approved chain, if any.
        static::creating(function (self $tenant) {
            if (empty($tenant->business_id) && ! empty($tenant->owner_user_id)) {
                $businessId = Business::where('owner_user_id', $tenant->owner_user_id)
                    ->where('status', Business::STATUS_APPROVED)
                    ->value('id');
                if ($businessId) {
                    $tenant->business_id = $businessId;
                }
            }
        });

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
            PerkCollection::where('tenant_id', $id)->delete();
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
        // 'business_id' is intentionally NOT mass-assignable — it is set
        // server-side by the creating() hook and Business::approve() only,
        // preventing a crafted request from linking a club into another chain.
        'club_name',
        'slug',
        'logo',
        'slogan',
        'description',
        'registration_terms',
        'registration_requirements',
        'require_email_verification',
        'enrollment_fee',
        'registration_fee',
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
        'registration_splash_image',
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
        'registration_fee' => 'decimal:2',
        'vat_percentage' => 'decimal:2',
        'phone' => 'array',
        'settings' => 'array',
        'require_email_verification' => 'boolean',
        'is_test_mode' => 'boolean',
    ];

    private const TEST_MODE_CACHE_PREFIX = 'tenant:test_mode:';

    /**
     * Whether this club is currently in Test Mode. Cached forever so every
     * financial-record write doesn't hit the DB; the cache is invalidated in
     * ClubFinancialController::switchMode() whenever the mode changes.
     */
    public static function isTestMode(int $id): bool
    {
        return Cache::rememberForever(
            self::TEST_MODE_CACHE_PREFIX.$id,
            fn () => (bool) (static::where('id', $id)->value('is_test_mode') ?? true)
        );
    }

    public static function forgetTestModeCache(int $id): void
    {
        Cache::forget(self::TEST_MODE_CACHE_PREFIX.$id);
    }

    /**
     * Get the owner user that owns the tenant.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * Get the business (chain) this club belongs to, if any.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    /**
     * User ids that manage this club and should be told about club-level events
     * (new member registrations, payments, etc.): the owner plus anyone holding
     * a staff/admin role scoped to this tenant. Tenant-scoped and de-duplicated.
     */
    public function staffUserIds(): array
    {
        $ids = \DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.tenant_id', $this->id)
            ->whereIn('roles.slug', ['club-admin', 'moderator', 'staff'])
            ->pluck('user_roles.user_id')
            ->all();

        if (! empty($this->owner_user_id)) {
            $ids[] = (int) $this->owner_user_id;
        }

        return array_values(array_unique(array_map('intval', $ids)));
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

    public function products(): HasMany
    {
        return $this->hasMany(ClubProduct::class);
    }

    public function productCategories(): HasMany
    {
        return $this->hasMany(ClubProductCategory::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
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
        return $this->hasMany(ClubAchievement::class)->orderBy('sort_order')->orderByDesc('id');
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
