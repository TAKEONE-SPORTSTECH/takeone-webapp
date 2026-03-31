<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Attendance;
use App\Models\ClubAffiliation;
use App\Models\ClubEventRegistration;
use App\Models\ClubGalleryImage;
use App\Models\ClubInstructor;
use App\Models\ClubMemberSubscription;
use App\Models\ClubMessage;
use App\Models\ClubNotification;
use App\Models\ClubReview;
use App\Models\ClubTimelinePostComment;
use App\Models\ClubTimelinePostLike;
use App\Models\ClubTransaction;
use App\Models\Goal;
use App\Models\HealthRecord;
use App\Models\InstructorReview;
use App\Models\Invoice;
use App\Models\Membership;
use App\Models\TournamentEvent;
use App\Models\PerkCollection;
use App\Models\UserNotification;
use App\Models\UserRelationship;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Carbon\Carbon;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Keep `name` in sync with `full_name` so they are always the same.
     * `full_name` is the canonical display field updated by all profile forms.
     * `name` is kept for Laravel internals (notifications, etc.).
     */
    protected static function booted(): void
    {
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
        'addresses',
        'social_links',
        'media_gallery',
        'profile_picture',
        'profile_picture_is_public',
        'motto',
        'bio',
        'skills',
        'experience_years',
        'is_personal_trainer',
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
            'social_links' => 'array',
            'media_gallery' => 'array',
            'mobile' => 'array',
            'skills' => 'array',
            'experience_years' => 'integer',
            'is_personal_trainer' => 'boolean',
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
                if (!$this->birthdate) {
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
                if (!$this->birthdate) {
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
                if (!$this->birthdate) {
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
                if (!$this->mobile || !is_array($this->mobile) || empty($this->mobile['code'] ?? '') || empty($this->mobile['number'] ?? '')) {
                    return null;
                }
                return ($this->mobile['code'] ?? '') . ' ' . ($this->mobile['number'] ?? '');
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
     * Get the clubs the user is a member of.
     */
    public function memberClubs(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'memberships')
                    ->withPivot('status')
                    ->withTimestamps();
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

        if (!$role) {
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
