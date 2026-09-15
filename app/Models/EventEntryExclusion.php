<?php

namespace App\Models;

use App\Members\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Why somebody is not on this competition's list any more.
 *
 * An entry that is removed is DELETED — money, weigh-in signature, fee lines
 * and all — which is right: a competition list is who is competing. But it
 * leaves the athlete with nothing to read. They open the event on the morning
 * of the weigh-in, see the ordinary "enter this event" screen, and phone
 * somebody.
 *
 * So the removal keeps one sentence behind: what was missing, and when the
 * deadline took it. It holds no personal data beyond the link itself, and the
 * reason is a machine key so the athlete reads it in their own language.
 *
 * It is deliberately NOT a ban. A ban says "you may not"; this says "you were
 * not ready in time" — and the organiser can still re-enter them, which
 * clears it.
 */
class EventEntryExclusion extends Model
{
    public const UNPAID = 'unpaid';

    public const NO_WEIGH_IN = 'no_weigh_in';

    public const BOTH = 'unpaid_and_no_weigh_in';

    protected $fillable = ['event_id', 'user_id', 'reason', 'decided_by', 'excluded_at'];

    protected $casts = ['excluded_at' => 'datetime'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** The reason, in the reader's own language. */
    public function sentence(): string
    {
        return __('events.entry_deadline_reason_'.$this->reason);
    }

    /** Which of the two gates this entry failed — the key the sweep writes. */
    public static function reasonFor(bool $unpaid, bool $unweighed): string
    {
        return match (true) {
            $unpaid && $unweighed => self::BOTH,
            $unpaid => self::UNPAID,
            default => self::NO_WEIGH_IN,
        };
    }
}
