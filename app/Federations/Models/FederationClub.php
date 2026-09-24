<?php

namespace App\Federations\Models;

use App\Clubs\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One club's affiliation with one federation: asked, accepted or refused. */
class FederationClub extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = ['federation_id', 'tenant_id', 'status', 'decided_at', 'decided_by', 'invite_token', 'officials'];

    protected $casts = [
        'decided_at' => 'datetime',
        'officials' => 'array',
    ];

    protected $hidden = ['invite_token'];

    protected static function booted(): void
    {
        static::creating(fn (FederationClub $c) => $c->uuid ??= (string) \Illuminate\Support\Str::uuid());
    }

    public function federation(): BelongsTo
    {
        return $this->belongsTo(Federation::class);
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
