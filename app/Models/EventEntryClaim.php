<?php

namespace App\Models;

use App\Members\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The link that completes an entry somebody else committed.
 *
 * Sits beside ClubEvent and ClubEventRegistration because it belongs to the
 * same vertical; the SERVICE that issues and consumes it is
 * App\Events\Support\EntryClaim, and nothing outside the Events module should
 * write these rows by hand.
 *
 * The secret half of the token is never stored — only its hash — so a database
 * copy does not hand anybody a working link.
 */
class EventEntryClaim extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'token_hash',
        'registration_id',
        'event_id',
        'user_id',
        'created_by',
        'contact_email',
        'contact_phone',
        'expires_at',
        'claimed_at',
        'claimed_ip',
        'revoked_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'claimed_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /** Never let a token hash reach a JSON response by accident. */
    protected $hidden = ['token_hash'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(ClubEventRegistration::class, 'registration_id');
    }

    /** The person this claim will become. */
    public function athlete(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Live means: not used, not revoked, not expired. Anything else is dead. */
    public function isLive(): bool
    {
        return $this->claimed_at === null
            && $this->revoked_at === null
            && $this->expires_at->isFuture();
    }

    public function state(): string
    {
        if ($this->claimed_at) {
            return 'claimed';
        }
        if ($this->revoked_at) {
            return 'revoked';
        }

        return $this->expires_at->isPast() ? 'expired' : 'live';
    }
}
