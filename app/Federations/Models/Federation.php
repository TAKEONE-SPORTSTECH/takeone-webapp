<?php

namespace App\Federations\Models;

use App\Clubs\Models\Tenant;
use App\Members\Models\User;
use App\Support\Palette;
use App\Traits\DeletesUploadedFiles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A governing body. Clubs affiliate with it through `federation_clubs`;
 * it never owns them. Routed by `uuid`, never by id.
 */
class Federation extends Model
{
    use DeletesUploadedFiles, SoftDeletes;

    /** Files this row owns — purged before a force-delete (Delete Files Before Records). */
    protected array $fileUploads = ['logo' => 'public'];

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const PAYER_SELF = 'self';

    public const PAYER_PARENT = 'parent';

    /** A federation tree is at most three levels deep. */
    public const MAX_DEPTH = 3;

    protected $fillable = [
        'owner_user_id', 'parent_id', 'payer', 'name', 'short_name', 'country', 'timezone', 'currency',
        'sport', 'sports', 'color', 'logo', 'logo_shape',
        'status', 'approved_at', 'approved_by', 'suspended_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'suspended_at' => 'datetime',
        'sports' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Federation $f) {
            $f->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function profile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(FederationProfile::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(FederationDocument::class)->latest('id');
    }

    public function staff(): HasMany
    {
        return $this->hasMany(FederationStaff::class);
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /** Every affiliation row — pending, approved and refused. */
    public function clubLinks(): HasMany
    {
        return $this->hasMany(FederationClub::class);
    }

    /** The clubs the federation has ACCEPTED. */
    public function clubs(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'federation_clubs', 'federation_id', 'tenant_id')
            ->wherePivot('status', FederationClub::STATUS_APPROVED)
            ->withPivot('status', 'decided_at')
            ->withTimestamps();
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * May this user run the dashboard? The owner of an approved federation,
     * or a super-admin. Deny by default.
     */
    public function isManagedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $this->isApproved() && (int) $this->owner_user_id === (int) $user->id;
    }

    /** The band colour, safe to drop into a `style` attribute. */
    public function bandColor(): string
    {
        return Palette::safe($this->color);
    }
}
