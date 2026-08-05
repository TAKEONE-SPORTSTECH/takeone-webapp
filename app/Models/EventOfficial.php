<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person appointed to officiate one event — by default, the jury.
 *
 * Scoped to a single event on purpose: officiating a championship says nothing
 * about the next one. See App\Events\Support\EventAccess for what it grants
 * (arranging the draw before the event starts, and nothing else).
 */
class EventOfficial extends Model
{
    /** Arrange the draw before the event starts. */
    public const ROLE_JURY = 'jury';

    /** Record and verify official weights at the weigh-in. */
    public const ROLE_WEIGH_IN = 'weigh_in';

    /** Check proof of payment against the club account and approve it. */
    public const ROLE_PAYMENTS = 'payments';

    /** @return array<int, string> */
    public static function roles(): array
    {
        return [self::ROLE_JURY, self::ROLE_WEIGH_IN, self::ROLE_PAYMENTS];
    }

    protected $fillable = [
        'event_id',
        'user_id',
        'role',
        'assigned_by',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
