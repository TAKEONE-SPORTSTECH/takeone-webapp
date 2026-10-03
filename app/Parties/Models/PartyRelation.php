<?php

namespace App\Parties\Models;

use App\Parties\Kinds;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One dated relationship: `from` holds `role` in `to` (PLAN.md §3.2).
 *
 * Never deleted — ended. Written only through App\Parties\Relations, which
 * owns the invariants (ownership shares sum to 100%, an owned party always has
 * an owner, a transfer is atomic).
 *
 * @property string $uuid
 * @property string $from_type
 * @property int $from_id
 * @property string $to_type
 * @property int $to_id
 * @property string $role
 * @property ?int $share_bp
 * @property ?\Illuminate\Support\Carbon $starts_at
 * @property ?\Illuminate\Support\Carbon $ends_at
 */
class PartyRelation extends Model
{
    protected $table = 'party_relations';

    protected $fillable = [
        'from_type', 'from_id', 'to_type', 'to_id', 'role', 'share_bp', 'title',
        'starts_at', 'ends_at', 'end_reason', 'granted_by', 'ended_by', 'source', 'meta',
    ];

    protected $casts = [
        'from_id' => 'integer',
        'to_id' => 'integer',
        'share_bp' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $r) {
            $r->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** Active at a moment (default now): started, and not yet ended. */
    public function scopeActive(Builder $q, $at = null): Builder
    {
        $at ??= now();

        return $q->where('starts_at', '<=', $at)
            ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>', $at));
    }

    public function scopeIn(Builder $q, Model $party): Builder
    {
        return $q->where('to_type', Kinds::keyOf($party))->where('to_id', $party->getKey());
    }

    public function scopeHeldBy(Builder $q, Model $party): Builder
    {
        return $q->where('from_type', Kinds::keyOf($party))->where('from_id', $party->getKey());
    }

    public function isActive(): bool
    {
        return $this->ends_at === null || $this->ends_at->isFuture();
    }

    /** The holder, resolved (soft-deleted ones included — history outlives them). */
    public function holder(): ?Model
    {
        return Kinds::find($this->from_type, $this->from_id);
    }

    /** The party the role is held in. */
    public function party(): ?Model
    {
        return Kinds::find($this->to_type, $this->to_id);
    }
}
