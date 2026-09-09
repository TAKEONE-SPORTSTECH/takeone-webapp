<?php

namespace App\Members\Models;

use Illuminate\Contracts\Translation\HasLocalePreference;
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
use App\Shop\Models\Order;
use App\Shop\Models\PerkCollection;
use App\Clubs\Models\Tenant;
use App\Clubs\Models\ClubAffiliation;
use App\Clubs\Models\ClubGalleryImage;
use App\Clubs\Models\ClubInstructor;
use App\Clubs\Models\ClubMessage;
use App\Clubs\Models\ClubNotification;
use App\Clubs\Models\ClubReview;
use App\Clubs\Models\ClubTransaction;
use App\Models\Business;
use App\Models\ClubEventRegistration;
use App\Models\ClubMemberSubscription;
use App\Models\ClubTimelinePostComment;
use App\Models\ClubTimelinePostLike;
use App\Models\EventOfficial;
use App\Trainers\Models\InstructorReview;
use App\Models\Invoice;

class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\Members\Models\UserFactory> */
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

            /* `phone_key` is DERIVED from `mobile` and must never drift from
               it — it is what signing in by telephone number looks up, so a
               stale key is an account nobody can reach. Kept here rather than
               at each write site because `mobile` is written from the profile
               modal, the public entry door, the member importer and the admin
               screens, and one of them would eventually forget. Not fillable
               for the same reason: nothing outside this line may set it. */
            if ($user->isDirty('mobile')) {
                $user->phone_key = self::normalisePhone($user->mobile);
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
            // getMorphClass(), not self::class — Sanctum stores the morph alias
            // (see App\Support\MorphMap), so matching on the class name would
            // leave a deleted user's API tokens behind as orphaned rows.
            \DB::table('personal_access_tokens')
                ->where('tokenable_type', (new self)->getMorphClass())
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
        'height_cm',
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
        // Entered by somebody else and never signed into — see the migration.
        'is_unclaimed',
        'notify_event_announcements',
        'notify_event_reminders',
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
            'height_cm' => 'integer',
            'experience_years' => 'integer',
            'is_personal_trainer' => 'boolean',
            'is_discoverable' => 'boolean',
            'is_unclaimed' => 'boolean',
            'notify_event_announcements' => 'boolean',
            'notify_event_reminders' => 'boolean',
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

                /*
                 * ⚠️ Lang keys, not literals.
                 *
                 * These were five hardcoded English words, which put them
                 * beyond BOTH translators: `__()` never saw them, so no lang
                 * file could carry them, and they are not organiser content, so
                 * App\Translation could not either. A Chinese reader was shown
                 * "Adult" on a page that was otherwise Chinese, and there was no
                 * key anywhere to fix it with. `lang/en/platform.php` already
                 * had the words — nothing had ever been wired to them.
                 *
                 * This is a DISPLAY value. Nothing compares or stores it; the
                 * age itself is the datum.
                 */
                return match (true) {
                    $age <= 3 => __('platform.age_toddler'),
                    $age <= 12 => __('platform.age_child'),
                    $age <= 19 => __('platform.age_teenager'),
                    $age <= 59 => __('platform.age_adult'),
                    default => __('platform.age_senior'),
                };
            }
        );
    }

    /**
     * A telephone number reduced to the one form we can match on.
     *
     * Digits only, country code first, leading zeros gone — so `+973 3774
     * 3277`, `00973 37743277` and `973-3774-3277` all become `97337743277`.
     * A local number typed with no country code keeps its own digits, which is
     * why two people in different countries could in principle collide; the
     * password and the "who is competing?" step settle that, and a phone was
     * never a unique identity here anyway.
     *
     * THE one place this rule lives. The stored `phone_key` column, the
     * sign-in lookup and the public entry door all call this, so a change here
     * changes all three together.
     *
     * @param  array|string|null  $mobile  the `{code, number}` array, or its JSON
     */
    public static function normalisePhone($mobile): ?string
    {
        if (is_string($mobile)) {
            $decoded = json_decode($mobile, true);
            $mobile = is_array($decoded) ? $decoded : ['code' => '', 'number' => $mobile];
        }

        if (! is_array($mobile)) {
            return null;
        }

        $code = ltrim(preg_replace('/\D/', '', (string) ($mobile['code'] ?? '')), '0');
        $number = ltrim(preg_replace('/\D/', '', (string) ($mobile['number'] ?? '')), '0');

        if ($number === '') {
            return null;
        }

        return mb_substr($code.$number, 0, 32);
    }

    /**
     * Normalise whatever somebody typed into a sign-in box.
     *
     * Same rule, one difference: a pasted number arrives as one string rather
     * than a `{code, number}` pair, and a leading `+` or `00` is the country
     * code announcing itself.
     */
    public static function normalisePhoneInput(?string $typed): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $typed);

        if ($digits === '') {
            return null;
        }

        // `00973…` is the same as `+973…`.
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        return mb_substr(ltrim($digits, '0'), 0, 32);
    }

    /**
     * Everybody whose telephone number is this one. Never assumes it is one person.
     *
     * Matches three ways, because people do not type their number the way it
     * was stored and refusing them would be indistinguishable from a wrong
     * password:
     *
     *   exact        — typed and stored agree (`97339990003`)
     *   typed local  — they typed `39990003`, we hold `97339990003`
     *   typed global — they typed `+973 39990003`, we hold only `39990003`
     *
     * The two loose matches need SIX digits before they apply, so a handful of
     * digits cannot sweep up the database. Ambiguity is safe here in a way it
     * would not be elsewhere: several matches do not pick one, they ask WHO
     * after the password has been checked against each.
     */
    public function scopeWithPhone($query, ?string $typed)
    {
        $key = self::normalisePhoneInput($typed);

        if ($key === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($q) use ($key) {
            $q->where('phone_key', $key);

            if (mb_strlen($key) >= 6) {
                // They typed the local number; we hold it with a country code.
                $q->orWhere('phone_key', 'like', '%'.$key);

                // They typed the country code; we hold only the local number.
                $q->orWhereRaw("length(phone_key) >= 6 AND ? LIKE '%' || phone_key", [$key]);
            }
        });
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
        return \App\Members\Models\UserBlock::where('blocker_id', $this->id)->where('blocked_id', $userId)->exists();
    }

    public function isBlockedBy(int $userId): bool
    {
        return \App\Members\Models\UserBlock::where('blocker_id', $userId)->where('blocked_id', $this->id)->exists();
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
        $row = \App\Members\Models\UserConnection::betweenUsers($this->id, $userId)->first();
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
            || \App\Members\Models\Conversation::where('type', 'direct')
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
    public function canViewPublicProfile(?User $viewer): bool
    {
        // Anonymous visitor: a deliberately narrower rule than for a member.
        //
        // A signed-in viewer may see anyone who has not blocked them. The open
        // internet may see only a member who has opted IN to being found
        // (is_discoverable) and who is not a minor. Discoverability is consent to
        // be found; a child's name, photo and club do not become public on the
        // strength of a link someone shared.
        if ($viewer === null) {
            return (bool) $this->is_discoverable && ! $this->isMinor();
        }

        return $viewer->id === $this->id || ! $viewer->blockedEitherWay($this->id);
    }

    /** Under 18 on the birthdate we hold. Unknown birthdate is NOT treated as a minor. */
    public function isMinor(): bool
    {
        return $this->birthdate !== null
            && \Carbon\Carbon::parse($this->birthdate)->age < 18;
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
     * Skills the user has acquired, with the club and dates behind each.
     *
     * Declared so callers can eager-load them: App\Sports\Combat\BeltRank reads
     * proficiency_level as its last resort for an athlete's rank, and an
     * officials' desk resolves that for every entrant in the event at once.
     */
    public function skillAcquisitions(): HasMany
    {
        return $this->hasMany(SkillAcquisition::class);
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

    /**
     * Does this person enter OTHER people into the platform as part of a job?
     *
     * True for a super admin, anyone who owns or administers a club, and anyone
     * appointed to officiate an event — the four groups that routinely create a
     * player from incomplete information: a name on a federation list, a paper
     * weigh-in sheet, a walk-in at the door.
     *
     * Used to decide how strict a person form is (PersonFieldRules), never to
     * decide what someone may DO — every authorisation check stays exactly where
     * it was. Widening this grants no access; it only stops the platform
     * demanding a birthdate from someone who does not have one.
     */
    public function entersPeopleOnBehalfOfOthers(): bool
    {
        if ($this->hasRole('super-admin')) {
            return true;
        }

        // Owns a club outright.
        if (Tenant::where('owner_user_id', $this->id)->exists()) {
            return true;
        }

        // Administers one. Any tenant: whether they may touch a given member is
        // an authorisation question, already answered before a form is reached.
        if ($this->roles()->where('slug', 'club-admin')->exists()) {
            return true;
        }

        // Appointed to officiate an event — organiser, jury, referee, any of them.
        return EventOfficial::where('user_id', $this->id)->exists();
    }

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
     * Roles this user holds PLATFORM-WIDE — the pivot carries no tenant, so the
     * grant is not about one club. In practice this is how super-admin is held.
     *
     * Kept separate from the tenant-scoped checks on purpose: those answer
     * "does this user run THIS club", and must keep answering it strictly.
     */
    public function hasPlatformRole(array $roleSlugs): bool
    {
        return $this->roles()->whereIn('slug', $roleSlugs)->wherePivotNull('tenant_id')->exists();
    }

    /**
     * A permission carried by a platform-wide role. Same reasoning as above —
     * never widened to "any role in any tenant", which would let an admin of
     * one club walk through another club's gate.
     */
    public function hasPlatformPermission(string $permissionSlug): bool
    {
        foreach ($this->roles()->wherePivotNull('tenant_id')->get() as $role) {
            if ($role->hasPermission($permissionSlug)) {
                return true;
            }
        }

        return false;
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
        Mail::to($this)->queue(new \App\Mail\WelcomeEmail($this));
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

    /** The pictures on this profile — the avatar is the one whose path is in profile_picture. */
    public function photos(): HasMany
    {
        return $this->hasMany(UserPhoto::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * The language to render anything sent TO this person in.
     *
     * ⚠️ This is what makes mail obey a locale at all.
     *
     * Every email on this platform is QUEUED, and a queue worker has no
     * request: no `Accept-Language`, no session, no `SetLocale` middleware. So
     * a queued mailable rendered whatever `config('app.locale')` said — English
     * — however carefully the member had set their language in /me/settings.
     * The member changed a setting, the platform agreed, and their receipts
     * kept arriving in English.
     *
     * Laravel reads this contract when a mailable is addressed to the MODEL:
     *
     *     Mail::to($user)          ← honours it
     *     Mail::to($user->email)   ← cannot; a string has no preference
     *
     * which is why the send sites pass the model. Returns null for a member who
     * has never chosen, and Laravel then uses the application default — the
     * same behaviour as before, for everybody who never expressed a preference.
     */
    public function preferredLocale(): ?string
    {
        return $this->locale ?: null;
    }
}
