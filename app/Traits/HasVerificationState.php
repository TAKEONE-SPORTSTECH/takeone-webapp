<?php

namespace App\Traits;

use App\Models\AchievementVouch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

/**
 * Shared provenance/verification state for any member-authored, club-attestable
 * record (a self-claimed tournament medal, an acquired skill, …). A record is
 * created `self_reported` and only becomes `verified` once a trusted authority
 * attests to it — the awarding club confirming, or credible coach/teammate
 * vouches passing threshold. The verification_* columns are set EXCLUSIVELY by
 * App\Services\AchievementVerificationService — never mass-assigned or trusted
 * from the client.
 *
 * The using model MUST:
 *   - have the columns: uuid, verification_status, verification_method,
 *     verified_by_tenant_id, verified_by_user_id, verified_at, verification_note
 *   - implement attestingTenant() and attestationLabel()
 */
trait HasVerificationState
{
    public const STATUS_SELF_REPORTED = 'self_reported';
    public const STATUS_PENDING = 'pending';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_REJECTED = 'rejected';

    public static function bootHasVerificationState(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->verification_status)) {
                $model->verification_status = self::STATUS_SELF_REPORTED;
            }
        });
    }

    public function initializeHasVerificationState(): void
    {
        $this->casts['verified_at'] = 'datetime';
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** Peer/coach attestations for this record (polymorphic). */
    public function vouches(): MorphMany
    {
        return $this->morphMany(AchievementVouch::class, 'vouchable');
    }

    public function verifiedByTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'verified_by_tenant_id');
    }

    public function verifiedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function isVerified(): bool
    {
        return $this->verification_status === self::STATUS_VERIFIED;
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('verification_status', self::STATUS_VERIFIED);
    }

    public function scopeSelfReported(Builder $query): Builder
    {
        return $query->where('verification_status', self::STATUS_SELF_REPORTED);
    }

    /** The user who owns/authored this claim (notified on decisions). */
    public function attestationOwnerId(): ?int
    {
        return $this->user_id ? (int) $this->user_id : null;
    }

    /** The club that may confirm this claim, or null when none is named. */
    abstract public function attestingTenant(): ?Tenant;

    /** Short human label for notifications/audit (e.g. "Nationals 2019 · Taekwondo"). */
    abstract public function attestationLabel(): string;
}
