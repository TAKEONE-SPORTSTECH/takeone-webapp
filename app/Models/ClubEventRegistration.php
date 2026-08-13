<?php

namespace App\Models;

use App\Traits\DeletesUploadedFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClubEventRegistration extends Model
{
    use DeletesUploadedFiles;

    protected $table = 'club_event_registrations';

    /**
     * Uploads this row owns, purged before the row goes — see the trait.
     * `photo` is the competitor picture an official added at the scoring table
     * for this event's screens.
     */
    protected array $fileUploads = [
        'photo' => 'public',
        // The club crest an official supplied for this event's screens — never
        // the club's own `tenants.logo`, which this must not touch.
        'club_logo' => 'public',
    ];

    protected $fillable = [
        'event_id',
        'user_id',
        'role',
        'status',
        'paid',
        'payment_proof',
        'paid_at',
        'paid_by',
        'category_id',
        'weight',
        // Recorded at the same desk, by the same official, as the weight —
        // see App\Sports\Combat\BeltRank for why this outranks the profile.
        'belt_colour',
        'belt_grade',
        // Added at the desk for this event's screens. Never the member's own
        // profile picture, and never the club's own logo — see the migrations.
        'photo',
        'club_logo',
        'weighed_in_at',
        'weighed_in_by',
        'meta',
        'registered_at',
        'entered_by',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
        'weighed_in_at' => 'datetime',
        'paid_at' => 'datetime',
        'paid' => 'boolean',
        'weight' => 'decimal:2',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class, 'category_id');
    }

    /** The coach/admin who entered this athlete. Null = they entered themselves. */
    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
}
