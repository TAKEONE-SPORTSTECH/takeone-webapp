<?php

namespace App\Models;

use App\Clubs\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * A club standing behind an event.
 *
 * Either one the platform already knows (`tenant_id` set, and then it has been
 * INVITED and has answered) or one it does not (a name, a crest and possibly an
 * Instagram page, written down by the organiser so the competition can name the
 * team it is actually running).
 *
 * @see database/migrations/2026_09_06_130000_create_event_clubs_table.php
 */
class EventClub extends Model
{
    protected $table = 'event_clubs';

    protected $fillable = [
        'event_id', 'tenant_id', 'name', 'logo', 'instagram', 'country',
        'state', 'invited_user_id', 'invited_at', 'responded_at',
        'promoted_tenant_id', 'promoted_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'invited_at' => 'datetime',
            'responded_at' => 'datetime',
            'promoted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $club) {
            if (empty($club->uuid)) {
                $club->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    /** The real club, when this row is one. */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * The athletes this club brought.
     *
     * Only a TEMPORARY club needs this: a real one's athletes name it through
     * `representing_tenant_id`. It is what decides, after the competition,
     * whether this club becomes a real one.
     */
    public function entrants(): BelongsToMany
    {
        return $this->belongsToMany(
            ClubEventRegistration::class,
            'event_club_entrants',
            'event_club_id',
            'registration_id'
        )->withTimestamps();
    }

    /** Written down for this event only, and gone with it unless it competes. */
    public function isTemporary(): bool
    {
        return $this->tenant_id === null && $this->promoted_tenant_id === null;
    }

    /** Has it already become a real club? */
    public function isPromoted(): bool
    {
        return $this->promoted_tenant_id !== null;
    }

    /**
     * Did this club actually take part?
     *
     * At least one athlete on the start list who did not withdraw. That is the
     * line between a club that competed and a name somebody typed — and it is
     * the only thing that turns a temporary club into a real one.
     */
    public function competed(): bool
    {
        return $this->entrants()
            ->where(function ($q) {
                $q->whereNull('club_event_registrations.entry_state')
                    ->orWhereNotIn('club_event_registrations.entry_state', ['withdrawn', 'removed']);
            })
            ->exists();
    }

    /**
     * The Instagram page, or nothing — checked again on the way OUT.
     *
     * `EventClubsController::instagramUrl()` already refuses anything that is
     * not an instagram.com page on the way in, so a stored value should always
     * be canonical. This is the second lock, and it is here rather than in the
     * template because the value's destination is an `href`: a `javascript:`
     * URL reaching one is stored XSS on every screen that draws the club, and a
     * write path added later that forgets the first check would open it
     * silently. A column is trusted because something checked it, not because
     * of where it lives.
     */
    public function instagramLink(): ?string
    {
        $url = (string) $this->instagram;

        return preg_match('#^https://instagram\.com/[A-Za-z0-9._]{1,30}$#', $url) ? $url : null;
    }

    /** Is this a club the platform already holds, or one written down here? */
    public function isOnPlatform(): bool
    {
        return $this->tenant_id !== null;
    }

    /** Has it been asked and not yet answered? */
    public function isAwaiting(): bool
    {
        return $this->state === 'invited';
    }

    /**
     * What a screen needs, and nothing else.
     *
     * The logo goes out as a URL through the one file door, never as a storage
     * path, and no internal id travels except the public uuid.
     *
     * @return array<string, mixed>
     */
    public function present(): array
    {
        $tenant = $this->relationLoaded('tenant') ? $this->tenant : ($this->tenant_id ? $this->tenant : null);

        // A real club's own name and mark win: it is theirs, and it may have
        // changed since somebody typed it here.
        $logo = $tenant?->logo ?: $this->logo;

        return [
            'uuid' => $this->uuid,
            'name' => $tenant?->club_name ?: $this->name,
            'logo' => $logo ? file_url($logo) : null,
            'instagram' => $this->instagramLink(),
            'country' => $tenant?->country ?: $this->country,
            'state' => $this->state,
            'on_platform' => $this->isOnPlatform(),
            'temporary' => $this->isTemporary(),
            'promoted' => $this->isPromoted(),
            'athletes' => $this->relationLoaded('entrants')
                ? $this->entrants->count()
                : $this->entrants()->count(),
            'invited_at' => $this->invited_at?->toIso8601String(),
            'responded_at' => $this->responded_at?->toIso8601String(),
        ];
    }
}
