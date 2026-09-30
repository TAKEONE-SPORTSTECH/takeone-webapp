<?php

namespace App\Events\Models;

use App\Members\Models\User;
use App\Traits\DeletesUploadedFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * An organiser's paid request to put one event in the /me/events spotlight.
 * Public key: uuid. The rules live in App\Events\Support\EventFeaturing.
 */
class EventFeatureRequest extends Model
{
    use DeletesUploadedFiles;

    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const REVOKED = 'revoked';

    /** The proof of payment is private: served only through the admin controller. */
    protected array $fileUploads = ['proof_path' => 'local'];

    // Nothing is mass-assignable from a request: every field is set by EventFeaturing.
    protected $guarded = ['*'];

    protected $casts = [
        'amount' => 'decimal:3',
        'decided_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (EventFeatureRequest $r) => $r->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function clubEvent(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'club_event_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
