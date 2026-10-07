<?php

namespace App\Events\Models;

use App\Members\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

/**
 * An event's tablet PIN — see the migration for what it is for.
 *
 * ── The rules it enforces ─────────────────────────────────────────────────
 *  · Only the HASH is kept; the PIN itself never touches the database.
 *  · Five wrong tries locks it for fifteen minutes, for every tablet at once.
 *    Ten thousand four-digit PINs are not many, and a hall is full of people
 *    with time on their hands between bouts.
 *  · A right PIN is only worth anything while its OWNER still runs the event:
 *    `owner()` is re-checked against EventAccess on every use, so an
 *    organiser removed from the event takes their PIN's power with them.
 */
class EventDrawPin extends Model
{
    public const MAX_ATTEMPTS = 5;

    public const LOCK_MINUTES = 15;

    protected $table = 'event_draw_pins';

    protected $fillable = ['event_id', 'user_id', 'pin_hash'];

    protected $hidden = ['pin_hash'];

    protected function casts(): array
    {
        return [
            'locked_until' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** 4 to 8 digits — what a keypad on a scoring table can be asked for. */
    public static function acceptable(string $pin): bool
    {
        return (bool) preg_match('/^\d{4,8}$/', $pin);
    }

    public static function setFor(ClubEvent $event, User $owner, string $pin): self
    {
        $row = static::firstOrNew(['event_id' => $event->id]);
        $row->user_id = $owner->id;
        $row->pin_hash = Hash::make($pin);
        $row->failed_attempts = 0;
        $row->locked_until = null;
        $row->save();

        return $row;
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    /**
     * Try a PIN. True only for the right one on an unlocked row; a wrong one
     * counts towards the lock-out, a right one clears the count.
     */
    public function attempt(string $pin): bool
    {
        if ($this->isLocked()) {
            return false;
        }

        if (static::acceptable($pin) && Hash::check($pin, $this->pin_hash)) {
            $this->forceFill(['failed_attempts' => 0, 'locked_until' => null, 'last_used_at' => now()])->save();

            return true;
        }

        $fails = $this->failed_attempts + 1;
        $this->forceFill([
            'failed_attempts' => $fails >= self::MAX_ATTEMPTS ? 0 : $fails,
            'locked_until' => $fails >= self::MAX_ATTEMPTS ? now()->addMinutes(self::LOCK_MINUTES) : $this->locked_until,
        ])->save();

        return false;
    }
}
