<?php

namespace App\Parties\Models;

use App\Parties\Kinds;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A proposed change of ownership, put to the owners (App\Parties\Motions).
 *
 * @property string $uuid
 * @property string $status open|passed|rejected|withdrawn|superseded
 * @property array $proposal [{type, id, share_bp}, …]
 */
class PartyMotion extends Model
{
    protected $table = 'party_motions';

    protected $fillable = ['to_type', 'to_id', 'kind', 'proposal', 'reason', 'proposed_by', 'status', 'decided_at'];

    protected $casts = ['to_id' => 'integer', 'proposed_by' => 'integer', 'proposal' => 'array', 'decided_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function votes(): HasMany
    {
        return $this->hasMany(PartyMotionVote::class, 'motion_id');
    }

    public function party(): ?Model
    {
        return Kinds::find($this->to_type, $this->to_id);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
