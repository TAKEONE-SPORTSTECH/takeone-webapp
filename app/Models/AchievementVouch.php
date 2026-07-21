<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A peer/coach attestation for a member's self-claimed, club-attestable record
 * (a tournament medal or an acquired skill — see the polymorphic `vouchable`).
 * `weight` is derived server-side by AchievementVerificationService::credibilityWeight()
 * and is intentionally NOT fillable — a voucher can never set their own credibility.
 */
class AchievementVouch extends Model
{
    public const STANCE_VOUCH = 'vouch';
    public const STANCE_DISPUTE = 'dispute';

    protected $fillable = [
        'vouchable_type',
        'vouchable_id',
        'voucher_user_id',
        'stance',
        'relationship',
        'note',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
    ];

    public function vouchable(): MorphTo
    {
        return $this->morphTo();
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voucher_user_id');
    }
}
