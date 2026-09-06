<?php

namespace App\Models;

use App\Traits\DeletesUploadedFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Clubs\Models\Tenant;
use App\Members\Models\User;

class ClubEventRegistration extends Model
{
    use DeletesUploadedFiles;

    protected $table = 'club_event_registrations';

    /**
     * Uploads this row owns, purged before the row goes — see the trait.
     *
     * `photo` is NOT declared here, deliberately. It holds one of two things:
     * a competitor picture an official took for this event (ours to delete) or
     * a reference to the athlete's own `users.profile_picture` (theirs, and
     * settled that way on purpose by App\Events\Support\PublicEntry). The trait
     * cannot tell them apart, so it purged member profile pictures whenever an
     * entry was removed. `App\Events\Support\EntryPhoto` asks first; the
     * `deleting` hook below routes through it.
     */
    protected array $fileUploads = [
        // The club crest an official supplied for this event's screens — never
        // the club's own `tenants.logo`, which this must not touch.
        'club_logo' => 'public',
    ];

    protected static function booted(): void
    {
        static::deleting(function (self $registration) {
            \App\Events\Support\EntryPhoto::discard($registration->photo);
        });
    }

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
        // How they got in, and who they compete FOR — see the migration.
        'entry_channel',
        // Whether the entry is finished. A coach may commit one from a NAME
        // alone; the athlete completes it through a claim link.
        'entry_state',
        'representing_tenant_id',
        'club_disowned_at',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
        'weighed_in_at' => 'datetime',
        'paid_at' => 'datetime',
        'club_disowned_at' => 'datetime',
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

    /** The club this athlete competes FOR — not necessarily the host. */
    public function representingTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'representing_tenant_id');
    }

    /** The claim was rejected by the club — the athlete competes unattached. */
    public function isDisowned(): bool
    {
        return $this->club_disowned_at !== null;
    }

    /**
     * The flag this competitor flies — the COUNTRY OF THE CLUB they compete for.
     *
     * Not their nationality. A competitor at a club event represents a club, and
     * a club sits in a country: a Jordanian passport holder fighting for a
     * Bahraini club is on the sheet as Bahrain, which is what the draw, the
     * scoreboard and the hall screens all print. Their own nationality stays a
     * fact about them, on their profile, where it belongs.
     *
     * `meta` — the free-text country an official could type at the desk — is
     * only the fallback for an entry with no club behind it (unattached, or
     * disowned). Eager-load `representingTenant` where this is read in a loop.
     */
    /**
     * The club a screen, a draw sheet or a roster prints beside this competitor.
     *
     * When the entry has an opinion — a claimed club, or a claim the club
     * rejected — that opinion wins, including when it says nobody: an unattached
     * competitor must not be quietly re-badged with a club they merely train at.
     * Only an entry that never recorded one falls back to their membership.
     *
     * Requires `representingTenant` (and, for the fallback, `user.memberClubs`).
     */
    public function competingClub(): ?Tenant
    {
        if ($this->representing_tenant_id !== null || $this->isDisowned()) {
            return $this->representedTenantId() ? $this->representingTenant : null;
        }

        return $this->user?->memberClubs->first();
    }

    public function countryCode(): ?string
    {
        if ($this->representedTenantId()) {
            return $this->representingTenant?->country ?: ($this->meta ?: null);
        }

        /*
         * Nobody to compete for — so the athlete's own nationality, added
         * 2026-09-05 at the user's request.
         *
         * The rule that the flag on a competition sheet is the CLUB's rather
         * than the passport's is unchanged and still comes first: an athlete
         * representing a Bahraini club is on the sheet as Bahrain whatever
         * their nationality. This is only the case where there IS no club —
         * where the old answer was no flag at all, which told a reader nothing
         * when their nationality was on file all along.
         */
        return $this->meta ?: ($this->user?->nationality ?: null);
    }

    /**
     * The club that may actually be printed beside this athlete's name.
     *
     * A disowned claim resolves to nobody: the athlete keeps their place and
     * competes unattached, which is the whole point of disowning being
     * moderation rather than a gate.
     */
    public function representedTenantId(): ?int
    {
        return $this->isDisowned() ? null : $this->representing_tenant_id;
    }
}
