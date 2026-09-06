<?php

namespace App\Models;

use App\Clubs\Models\Tenant;
use App\Members\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A request to compete, made by somebody who followed a public link.
 *
 * Phase C of Documentation/EVENTS-PUBLIC-ENTRY.md, Door C. It is NOT an entry:
 * until an organiser accepts it, it counts toward nothing — not the entrant
 * count, not capacity, not the money — and it lives here rather than in
 * `club_event_registrations` for exactly that reason (see the migration).
 *
 * The rules live in App\Events\Support\PublicEntry. Nothing outside the Events
 * module should write these rows by hand.
 */
class EventPublicEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'event_id',
        'user_id',
        'birthdate',
        'gender',
        'weight',
        'belt_colour',
        // The priced extras they ticked, as option UUIDs. Held until somebody
        // accepts the request, because that is when the entry is priced.
        'fee_options',
        'representing_tenant_id',
        // The club named by an entrant whose club is not on this platform. Set
        // only when `representing_tenant_id` is not — see PublicEntry::resolveClub().
        'club_name',
        'state',
        'decided_by',
        'decided_at',
        'registration_id',
        'ip',
    ];

    protected $casts = [
        'fee_options' => 'array',
        'birthdate' => 'date',
        'weight' => 'decimal:2',
        'decided_at' => 'datetime',
    ];

    /** An IP is audit, not something a view ever needs. */
    protected $hidden = ['ip'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    /** The person asking. A real account from the moment they submitted. */
    public function athlete(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function representingTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'representing_tenant_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(ClubEventRegistration::class, 'registration_id');
    }

    public function isPending(): bool
    {
        return $this->state === 'pending';
    }
}
