<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerkCollection extends Model
{
    protected $fillable = [
        'perk_id',
        'tenant_id',
        'collected_by_user_id',
        'collected_for_user_id',
    ];

    public function perk(): BelongsTo
    {
        return $this->belongsTo(ClubPerk::class, 'perk_id');
    }

    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by_user_id');
    }

    public function collectedFor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_for_user_id');
    }
}
