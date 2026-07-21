<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * Keep `name` in sync with `full_name` so they are always the same.
     * `full_name` is the canonical display field updated by all profile forms.
     * `name` is kept for Laravel internals (notifications, etc.).
     */
    protected static function booted(): void
    {
        static::creating(function (self $user) {
            if (empty($user->uuid)) {
                $user->uuid = (string) \Illuminate\Support\Str::uuid();
            }
            if (empty($user->slug)) {
                $user->slug = self::generateUniqueSlug($user->full_name ?: $user->name ?: 'member');
            }
        });

        static::saving(function (self $user) {
            if ($user->isDirty('full_name') && $user->full_name) {
                $user->name = $user->full_name;
            }
        });

        // Clean up all orphan-prone records when a user is soft-deleted.
        // DB cascades only fire on hard delete; soft delete leaves these behind.
        static::deleting(function (self $user) {
            $id = $user->id;

            // Memberships & subscriptions
            Membership::where('user_id', $id)->delete();
            ClubMemberSubscription::where('user_id', $id)->delete();

            // Instructor record
            ClubInstructor::where('user_id', $id)->delete();

            // Affiliations (cascade skills & media)
            ClubAffiliation::where('member_id', $id)->each(function ($aff) {
                $aff->skillAcquisitions()->delete();
                $aff->affiliationMedia()->delete();
                $aff->delete();
            });

            // Family relationships
            UserRelationship::where('guardian_user_id', $id)
                ->orWhere('dependent_user_id', $id)
                ->delete();

            // Personal data
            HealthRecord::where('user_id', $id)->delete();
            Goal::where('user_id', $id)->delete();
            TournamentEvent::where('user_id', $id)->delete();
            Attendance::where('user_id', $id)->delete();

            // Club interactions
            ClubEventRegistration::where('user_id', $id)->delete();
            ClubReview::where('user_id', $id)->delete();
            InstructorReview::where('reviewer_user_id', $id)->delete();
            ClubTimelinePostLike::where('user_id', $id)->delete();
            ClubTimelinePostComment::where('user_id', $id)->delete();
            ClubMessage::where('sender_id', $id)->orWhere('recipient_id', $id)->delete();

            // Perk collections (as collector or beneficiary)
            PerkCollection::where('collected_by_user_id', $id)
                ->orWhere('collected_for_user_id', $id)
                ->delete();

            // Notifications
            UserNotification::where('user_id', $id)->delete();
            ClubNotification::where('sender_user_id', $id)->delete();

            // Gallery images uploaded by this user
            ClubGalleryImage::where('uploaded_by', $id)->delete();

            // Invoices
            Invoice::where('student_user_id', $id)->orWhere('payer_user_id', $id)->delete();

            // Nullify financial transaction user ref (keep for audit, just remove user link)
            ClubTransaction::where('user_id', $id)->update(['user_id' => null]);

            // Nullify tenant owner (don't delete the club, just unset the owner)
            Tenant::where('owner_user_id', $id)->update(['owner_user_id' => null]);

            // Roles
            \DB::table('user_roles')->where('user_id', $id)->delete();

            // Sessions & tokens
            \DB::table('sessions')->where('user_id', $id)->delete();
            \DB::table('personal_access_tokens')
                ->where('tokenable_type', self::class)
                ->where('tokenable_id', $id)
                ->delete();
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'slug',
        'name',
        'full_name',
        'email',
        'mobile',
        'password',
        'gender',
        'marital_status',
        'birthdate',
        'blood_type',
        'nationality',
        'locale',
        'addresses',
        'documents',
        'emergency_contacts',
        'health_conditions',
        'social_links',
        'media_gallery',
        'profile_picture',
        'profile_picture_is_public',
        'motto',
        'bio',
        'skills',
        'experience_years',
        'is_personal_trainer',
        'is_discoverable',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'birthdate' => 'date',
            'addresses' => 'array',
            'documents' => 'array',
            'emergency_contacts' => 'array',
            'health_conditions' => 'array',
            'social_links' => 'array',
            'media_gallery' => 'array',
            'mobile' => 'array',
            'skills' => 'array',
            'experience_years' => 'integer',
            'is_personal_trainer' => 'boolean',
            'is_discoverable' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Whether the user has fully confirmed 2FA setup.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /**
     * Get the user's age based on birthdate.
     */
    protected function age(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->birthdate) {
                    return null;
                }

                return Carbon::parse($this->birthdate)->age;
            }
        );
    }

    /**
     * Get the user's horoscope based on birthdate.
     */
    protected function horoscope(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->birthdate) {
                    return null;
                }

                $month = $this->birthdate->month;
                $day = $this->birthdate->day;

                if (($month == 3 && $day >= 21) || ($month == 4 && $day <= 19)) {
                    return 'Aries';
                } elseif (($month == 4 && $day >= 20) || ($month == 5 && $day <= 20)) {
                    return 'Taurus';
                } elseif (($month == 5 && $day >= 21) || ($month == 6 && $day <= 20)) {
                    return 'Gemini';
                } elseif (($month == 6 && $day >= 21) || ($month == 7 && $day <= 22)) {
                    return 'Cancer';
                } elseif (($month == 7 && $day >= 23) || ($month == 8 && $day <= 22)) {
                    return 'Leo';
                } elseif (($month == 8 && $day >= 23) || ($month == 9 && $day <= 22)) {
                    return 'Virgo';
                } elseif (($month == 9 && $day >= 23) || ($month == 10 && $day <= 22)) {
                    return 'Libra';
                } elseif (($month == 10 && $day >= 23) || ($month == 11 && $day <= 21)) {
                    return 'Scorpio';
                } elseif (($month == 11 && $day >= 22) || ($month == 12 && $day <= 21)) {
                    return 'Sagittarius';
                } elseif (($month == 12 && $day >= 22) || ($month == 1 && $day <= 19)) {
                    return 'Capricorn';
                } elseif (($month == 1 && $day >= 20) || ($month == 2 && $day <= 18)) {
                    return 'Aquarius';
                } else {
                    return 'Pisces';
                }
            }
        );
    }

    /**
     * Get the user's life stage based on age.
     */
    protected function lifeStage(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->birthdate) {
                    return null;
                }

                $age = Carbon::parse($this->birthdate)->age;

                if ($age >= 0 && $age <= 3) {
                    return 'Toddler';
                } elseif ($age >= 4 && $age <= 12) {
                    return 'Child';
                } elseif ($age >= 13 && $age <= 19) {
                    return 'Teenager';
                } elseif ($age >= 20 && $age <= 59) {
                    return 'Adult';
                } else {
                    return 'Senior';
                }
            }
        );
    }

    /**
     * Get the formatted mobile number.
     */
    protected function mobileFormatted(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->mobile || ! is_array($this->mobile) || empty($this->mobile['code'] ?? '') || empty($this->mobile['number'] ?? '')) {
                    return null;
                }

                return ($this->mobile['code'] ?? '').' '.($this->mobile['number'] ?? '');
            }
        );
    }

    /**
     * Get the club instructor records for the user.
     */
    public function clubInstructors(): HasMany
    {
        return $this->hasMany(ClubInstructor::class);
    }

    /**
     * Registered device tokens for native push (FCM).
     */
    public function pushTokens(): HasMany
    {
        return $this->hasMany(PushToken::class);
    }

    /**
     * Get the dependents for the user.
     */
    public function dependents(): HasMany
    {
        return $this->hasMany(UserRelationship::class, 'guardian_user_id');
    }

    /**
     * Get the guardians for the user.
     */
    public function guardians(): HasMany
    {
        return $this->hasMany(UserRelationship::class, 'dependent_user_id');
    }

    /**
     * Get the clubs owned by the user.
     */
    public function ownedClubs(): HasMany
    {
        return $this->hasMany(Tenant::class, 'owner_user_id');
    }

    /**
     * Get the business (chain) owned by the user. One business per user.
     */
    public function ownedBusiness(): HasOne
    {
        return $this->hasOne(Business::class, 'owner_user_id');
    }

    /**
     * Whether the user owns an approved business (controls the Personal/Business switcher).
     */
    public function hasApprovedBusiness(): bool
    {
        return $this->ownedBusiness()
            ->where('status', Business::STATUS_APPROVED)
            ->exists();
    }

    /**
     * Get the clubs the user is a member of.
     */
    public function memberClubs(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'memberships')
            ->withPivot('status')
            ->withTimestamps();
    }

    /**
     * True when any club the user belongs to has switched off member
     * cross-club discovery (club settings → "block_explore"). Used to hide the
     * Explore entry from the member's navigation. Memoized per request.
     */
    public function isExploreLocked(): bool
    {
        return once(fn () => $this->memberClubs()
            ->get(['tenants.id', 'tenants.settings'])
            ->contains(fn (Tenant $club) => ! empty($club->settings['block_explore'])));
    }

    public function clubMemberSubscriptions(): HasMany
    {
        return $this->hasMany(ClubMemberSubscription::class);
    }

    /**
     * True once at least one club has actually confirmed the member (subscription
     * status = active) — distinct from `memberships.status`, which is set to
     * 'active' the instant a subscription record exists, even a still-pending
     * self-registration. Used to gate club-scoped social features (Find People)
     * until real membership is confirmed.
     */
    public function hasConfirmedClubMembership(): bool
    {
        return once(fn () => $this->clubMemberSubscriptions()->where('status', 'active')->exists());
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    // ===================== Social graph =====================

    public function following(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_follows', 'follower_id', 'followee_id')->withTimestamps();
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_follows', 'followee_id', 'follower_id')->withTimestamps();
    }

    public function blockedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_blocks', 'blocker_id', 'blocked_id')->withTimestamps();
    }

    public function isFollowing(int $userId): bool
    {
        return $this->following()->whereKey($userId)->exists();
    }

    public function hasBlocked(int $userId): bool
    {
        return \App\Models\UserBlock::where('blocker_id', $this->id)->where('blocked_id', $userId)->exists();
    }

    public function isBlockedBy(int $userId): bool
    {
        return \App\Models\UserBlock::where('blocker_id', $userId)->where('blocked_id', $this->id)->exists();
    }

    public function blockedEitherWay(int $userId): bool
    {
        return $this->hasBlocked($userId) || $this->isBlockedBy($userId);
    }

    public function isConnectedTo(int $userId): bool
    {
        return $this->connectionStatusWith($userId) === 'connected';
    }

    /** none | pending_outgoing | pending_incoming | connected */
    public function connectionStatusWith(int $userId): string
    {
        $row = \App\Models\UserConnection::betweenUsers($this->id, $userId)->first();
        if (! $row) {
            return 'none';
        }
        if ($row->status === 'accepted') {
            return 'connected';
        }

        return $row->requester_id === $this->id ? 'pending_outgoing' : 'pending_incoming';
    }

    public function sharesClubWith(User $other): bool
    {
        return $this->memberClubs()->whereIn('tenants.id', $other->memberClubs()->pluck('tenants.id'))->exists();
    }

    /**
     * Visibility rule for another member's wall: not blocked either way AND
     * (own wall | club-mate | following them | connected).
     */
    public function canViewWall(User $owner): bool
    {
        if ($this->id === $owner->id) {
            return true;
        }
        if ($this->blockedEitherWay($owner->id)) {
            return false;
        }

        return $this->sharesClubWith($owner)
            || $this->isFollowing($owner->id);
    }

    /**
     * Messaging consent (Facebook-style): you may DM someone only if you share
     * a club, are accepted connections (a request they approved), or already
     * have a 1:1 thread — and neither of you has blocked the other. This stops
     * unsolicited messages between people who haven't opted into contact.
     */
    public function canMessage(User $other): bool
    {
        if ($this->id === $other->id) {
            return false;
        }
        if ($this->blockedEitherWay($other->id)) {
            return false;
        }

        return $this->sharesClubWith($other)
            || $this->isConnectedTo($other->id)
            // A discoverable member has opted into being found AND contacted.
            || $other->isDiscoverable()
            || \App\Models\Conversation::where('type', 'direct')
                ->whereHas('participants', fn ($q) => $q->where('user_id', $this->id))
                ->whereHas('participants', fn ($q) => $q->where('user_id', $other->id))
                ->exists();
    }

    /** Whether this member opts into people-discovery (search + cold DMs). Default true. */
    public function isDiscoverable(): bool
    {
        return (bool) ($this->is_discoverable ?? true);
    }

    /**
     * Who may open this member's SAFE public profile (people.show): anyone
     * signed in who isn't blocked either way. The public profile deliberately
     * exposes only non-sensitive fields, so it is broadly viewable — private
     * data stays on the family/admin-gated member.show.
     */
    public function canViewPublicProfile(User $viewer): bool
    {
        return $viewer->id === $this->id || ! $viewer->blockedEitherWay($this->id);
    }

    /** A unique, URL-safe slug derived from a display name (e.g. "john-doe", "john-doe-2"). */
    public static function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = \Illuminate\Support\Str::slug($name) ?: 'member';
        $slug = $base;
        $i = 1;
        // withTrashed: the unique index also covers soft-deleted rows.
        while (static::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }

    /** Relationship of THIS user toward $other, shaped for the wall UI. */
    public function relationshipWith(User $other): array
    {
        return [
            'following' => $this->isFollowing($other->id),
            'followsYou' => $other->isFollowing($this->id),
            'blocked' => $this->hasBlocked($other->id),
            'blockedBy' => $this->isBlockedBy($other->id),
            'sharesClub' => $this->sharesClubWith($other),
        ];
    }

    /**
     * Get the invoices where the user is the student.
     */
    public function studentInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'student_user_id');
    }

    /**
     * Get the invoices where the user is the payer.
     */
    public function payerInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'payer_user_id');
    }

    /**
     * Get the health records for the user.
     */
    public function healthRecords(): HasMany
    {
        return $this->hasMany(HealthRecord::class);
    }

    /**
     * Get the most recent health record for the user.
     */
    public function latestHealthRecord(): HasOne
    {
        return $this->hasOne(HealthRecord::class)->latestOfMany('recorded_at');
    }

    /**
     * Get the tournament events for the user.
     */
    public function tournamentEvents(): HasMany
    {
        return $this->hasMany(TournamentEvent::class);
    }

    /**
     * Get the goals for the user.
     */
    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    /**
     * Get the attendance records for the user.
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(Attendance::class, 'member_id');
    }

    /**
     * Get the free-form event-participation log entries for the user.
     */
    public function memberEvents(): HasMany
    {
        return $this->hasMany(MemberEvent::class);
    }

    /**
     * Get the self-managed certifications / qualifications for the user.
     */
    public function certifications(): HasMany
    {
        return $this->hasMany(MemberCertification::class);
    }

    /**
     * Get the self-managed work / coaching history for the user.
     */
    public function workHistory(): HasMany
    {
        return $this->hasMany(MemberWorkHistory::class);
    }

    /**
     * Get the club affiliations for the user.
     */
    public function clubAffiliations(): HasMany
    {
        return $this->hasMany(ClubAffiliation::class, 'member_id');
    }

    /**
     * Get the roles for the user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot('tenant_id')
            ->withTimestamps();
    }

    /**
     * Get the subscriptions for the user.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(ClubMemberSubscription::class);
    }

    /**
     * Get the transactions for the user.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(ClubTransaction::class);
    }

    /**
     * Get the sent messages for the user.
     */
    public function sentMessages(): HasMany
    {
        return $this->hasMany(ClubMessage::class, 'sender_id');
    }

    /**
     * Get the received messages for the user.
     */
    public function receivedMessages(): HasMany
    {
        return $this->hasMany(ClubMessage::class, 'recipient_id');
    }

    /**
     * Get the reviews written by the user.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(ClubReview::class);
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole(string $roleSlug, ?int $tenantId = null): bool
    {
        $query = $this->roles()->where('slug', $roleSlug);

        if ($tenantId !== null) {
            $query->wherePivot('tenant_id', $tenantId);
        }

        return $query->exists();
    }

    /**
     * Check if user has any of the given roles.
     */
    public function hasAnyRole(array $roleSlugs, ?int $tenantId = null): bool
    {
        $query = $this->roles()->whereIn('slug', $roleSlugs);

        if ($tenantId !== null) {
            $query->wherePivot('tenant_id', $tenantId);
        }

        return $query->exists();
    }

    /**
     * Check if user has a specific permission.
     */
    public function hasPermission(string $permissionSlug, ?int $tenantId = null): bool
    {
        $roles = $tenantId !== null
            ? $this->roles()->wherePivot('tenant_id', $tenantId)->get()
            : $this->roles;

        foreach ($roles as $role) {
            if ($role->hasPermission($permissionSlug)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user is super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }

    /**
     * Check if user is club admin for a specific club.
     */
    public function isClubAdmin(?int $tenantId = null): bool
    {
        return $this->hasRole('club-admin', $tenantId);
    }

    /**
     * Check if user is instructor for a specific club.
     */
    public function isInstructor(?int $tenantId = null): bool
    {
        return $this->hasRole('instructor', $tenantId);
    }

    /**
     * Get roles for a specific tenant/club.
     */
    public function getRolesForTenant(?int $tenantId = null)
    {
        if ($tenantId !== null) {
            return $this->roles()->wherePivot('tenant_id', $tenantId)->get();
        }

        return $this->roles;
    }

    /**
     * Assign a role to the user.
     */
    public function assignRole(string $roleSlug, ?int $tenantId = null): void
    {
        $role = Role::where('slug', $roleSlug)->firstOrFail();

        $this->roles()->attach($role->id, ['tenant_id' => $tenantId]);

        // Invalidate all active sessions so the new role takes effect immediately.
        DB::table('sessions')->where('user_id', $this->id)->delete();
    }

    /**
     * Remove a role from the user.
     */
    public function removeRole(string $roleSlug, ?int $tenantId = null): void
    {
        $role = Role::where('slug', $roleSlug)->first();

        if (! $role) {
            return;
        }

        if ($tenantId !== null) {
            $this->roles()->wherePivot('tenant_id', $tenantId)->detach($role->id);
        } else {
            $this->roles()->detach($role->id);
        }

        // Invalidate all active sessions so the removed role takes effect immediately.
        DB::table('sessions')->where('user_id', $this->id)->delete();
    }

    /**
     * Send the email verification notification using the custom WelcomeEmail.
     * Called by Laravel on registration, and manually for resend flows.
     */
    public function sendEmailVerificationNotification()
    {
        Mail::to($this->email)->queue(new \App\Mail\WelcomeEmail($this));
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }

    public function eventRegistrations(): HasMany
    {
        return $this->hasMany(ClubEventRegistration::class);
    }

    public function userNotifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }
}
