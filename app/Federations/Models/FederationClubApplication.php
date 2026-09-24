<?php

namespace App\Federations\Models;

use App\Clubs\Models\Tenant;
use App\Traits\DeletesUploadedFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A club that is not on the platform, applying through an invitation link.
 * Stays an application — nothing on the club side — until the federation
 * approves it.
 */
class FederationClubApplication extends Model
{
    use DeletesUploadedFiles;

    public const STATUS_PENDING = 'Pending';

    public const STATUS_APPROVED = 'Approved';

    public const STATUS_REJECTED = 'Rejected';

    protected array $fileUploads = ['logo' => 'public'];

    protected $fillable = [
        'federation_id', 'federation_event_id', 'name', 'coach', 'email', 'phone', 'address', 'logo', 'logo_shape',
        'officials', 'invite_token', 'status', 'tenant_id', 'federation_club_id', 'decided_by', 'decided_at',
    ];

    protected $casts = ['officials' => 'array', 'decided_at' => 'datetime'];

    protected $hidden = ['invite_token'];

    protected static function booted(): void
    {
        static::creating(fn (FederationClubApplication $a) => $a->uuid ??= (string) Str::uuid());
    }

    public function federation(): BelongsTo
    {
        return $this->belongsTo(Federation::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
