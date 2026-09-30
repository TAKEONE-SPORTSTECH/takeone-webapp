<?php

namespace App\Events\Models;

use App\Clubs\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A pair or a team entered in an event — several people competing as one.
 *
 * Each person keeps their own ClubEventRegistration (fee, weigh-in, photo,
 * entry state); a side only groups those registrations under a name. Built and
 * changed through App\Events\Support\Competition\EventSides, which enforces
 * what the event's competition profile allows. The uuid is the only
 * identifier that leaves the server.
 */
class EventSide extends Model
{
    public const KIND_PAIR = 'pair';
    public const KIND_TEAM = 'team';

    protected $table = 'event_sides';

    protected $fillable = [
        'uuid', 'event_id', 'category_id', 'kind', 'name', 'short_name',
        'tenant_id', 'seed', 'withdrawn_at', 'created_by',
    ];

    protected function casts(): array
    {
        return ['withdrawn_at' => 'datetime', 'seed' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $side) {
            $side->uuid ??= (string) Str::uuid();
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class, 'category_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(EventSideMember::class, 'side_id')->orderBy('sort_order')->orderBy('id');
    }

    /** The people who compete (players and the captain), not the bench or the coach. */
    public function players(): HasMany
    {
        return $this->members()->whereIn('role', EventSideMember::COMPETING);
    }

    /** What to call it: its own name, or a pair's two surnames. */
    public function displayName(): string
    {
        if (filled($this->name)) {
            return (string) $this->name;
        }

        $names = $this->players()->with('registration.user:id,full_name,name')->get()
            ->map(fn (EventSideMember $m) => $m->registration?->user?->full_name ?: $m->registration?->user?->name)
            ->filter()
            ->map(fn (string $n) => Str::of($n)->trim()->explode(' ')->last())
            ->values();

        return $names->isNotEmpty() ? $names->implode(' / ') : __('events::events.side_unnamed');
    }
}
