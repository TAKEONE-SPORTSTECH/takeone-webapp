<?php

namespace App\Events\Support\Competition;

use App\Events\EventTypeRegistry;
use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Events\Models\EventSide;
use App\Events\Models\EventSideMember;
use App\Events\Support\AudienceResolver;
use App\Members\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pairs and teams — the ONE place a side is built, changed and checked.
 *
 * What a side may be is the event's own answer (its CompetitionProfile):
 *   · an event whose entrants are individuals has no sides at all;
 *   · a pair is exactly two players;
 *   · a team respects the profile's squad size, where the sport sets one.
 *
 * Every member is somebody already entered in THIS event (their own
 * registration keeps their fee, weigh-in and photo), and a person plays for
 * one side per division. Authorization is the caller's (the controller asks
 * EventAccess::canManage); every rule about the data is here, so the web, the
 * MCP and anything later obey the same ones.
 *
 * Writes nudge the event's organisers over realtime with a refresh signal
 * (`{action: 'sides'}` on `events`) — each screen re-fetches what it may see.
 */
class EventSides
{
    public function __construct(
        private EventTypeRegistry $types,
        private AudienceResolver $audience,
    ) {}

    /** The side kind this event takes, or null when its entrants are individuals. */
    public function kindFor(ClubEvent $event): ?string
    {
        return match ($this->profile($event)->entrant) {
            Entrant::Pair => EventSide::KIND_PAIR,
            Entrant::Team => EventSide::KIND_TEAM,
            default => null,
        };
    }

    /** @return list<array<string, mixed>> */
    public function list(ClubEvent $event): array
    {
        return $event->hasMany(EventSide::class, 'event_id')
            ->with(['members.registration.user:id,uuid,full_name,name', 'category:id,name'])
            ->orderByRaw('withdrawn_at is not null')->orderBy('category_id')->orderBy('seed')->orderBy('id')
            ->get()
            ->map(fn (EventSide $s) => $this->present($s))
            ->all();
    }

    public function create(ClubEvent $event, User $actor, array $data): EventSide
    {
        $kind = $this->kindFor($event);

        if ($kind === null) {
            throw ValidationException::withMessages(['kind' => __('events::events.side_not_for_this_event')]);
        }

        $category = $this->categoryOf($event, $data['category_id'] ?? null);

        $side = EventSide::create([
            'event_id' => $event->id,
            'category_id' => $category?->id,
            'kind' => $kind,
            'name' => $this->clean($data['name'] ?? null, 80),
            'short_name' => $this->clean($data['short_name'] ?? null, 12),
            'tenant_id' => $data['tenant_id'] ?? null,
            'seed' => isset($data['seed']) ? max(1, (int) $data['seed']) : null,
            'created_by' => $actor->id,
        ]);

        $this->announce($event);

        return $side;
    }

    public function update(EventSide $side, array $data): EventSide
    {
        $event = $side->event;

        if (array_key_exists('category_id', $data)) {
            $category = $this->categoryOf($event, $data['category_id']);

            // Moving divisions must not put a player on two sides in one division.
            foreach ($side->members as $member) {
                $this->assertFreeInDivision($side, $member->registration, $category?->id);
            }

            $side->category_id = $category?->id;
        }

        foreach (['name' => 80, 'short_name' => 12] as $field => $max) {
            if (array_key_exists($field, $data)) {
                $side->{$field} = $this->clean($data[$field], $max);
            }
        }

        if (array_key_exists('seed', $data)) {
            $side->seed = $data['seed'] === null ? null : max(1, (int) $data['seed']);
        }

        if (array_key_exists('withdrawn', $data)) {
            $side->withdrawn_at = $data['withdrawn'] ? ($side->withdrawn_at ?? now()) : null;
        }

        $side->save();
        $this->announce($event);

        return $side->fresh();
    }

    public function delete(EventSide $side): void
    {
        $event = $side->event;
        $side->delete();   // members go with it (cascade); registrations are untouched
        $this->announce($event);
    }

    public function addMember(EventSide $side, ClubEventRegistration $registration, string $role = 'player', ?int $number = null): EventSideMember
    {
        $event = $side->event;

        if ((int) $registration->event_id !== (int) $event->id || $registration->role !== 'participant') {
            throw ValidationException::withMessages(['registration' => __('events::events.side_member_not_entered')]);
        }

        if (! in_array($role, EventSideMember::ROLES, true)) {
            throw ValidationException::withMessages(['role' => __('events::events.side_role_invalid')]);
        }

        if ($side->members()->where('registration_id', $registration->id)->exists()) {
            throw ValidationException::withMessages(['registration' => __('events::events.side_member_already')]);
        }

        $this->assertFreeInDivision($side, $registration, $side->category_id);

        if (in_array($role, EventSideMember::COMPETING, true)) {
            $max = $this->maxPlayers($side);

            if ($max !== null && $side->players()->count() >= $max) {
                throw ValidationException::withMessages(['registration' => trans_choice('events::events.side_full', $max, ['n' => $max])]);
            }
        }

        $member = DB::transaction(fn () => $side->members()->create([
            'registration_id' => $registration->id,
            'role' => $role,
            'number' => $number,
            'sort_order' => (int) $side->members()->max('sort_order') + 1,
        ]));

        $this->announce($event);

        return $member;
    }

    public function removeMember(EventSide $side, EventSideMember $member): void
    {
        abort_unless((int) $member->side_id === (int) $side->id, 404);

        $member->delete();
        $this->announce($side->event);
    }

    /**
     * Is the side ready to compete? A pair needs both players; a team at
     * least the profile's minimum. Returned, never enforced on save — a side
     * is put together over time.
     */
    public function complete(EventSide $side): bool
    {
        $players = $side->players()->count();
        [$min] = $this->size($side);

        return $players >= $min;
    }

    /** @return array<string, mixed> */
    public function present(EventSide $side): array
    {
        [$min, $max] = $this->size($side);
        $members = $side->members;

        return [
            'uuid' => $side->uuid,
            'kind' => $side->kind,
            'name' => $side->name,
            'display_name' => $side->displayName(),
            'short_name' => $side->short_name,
            'category_id' => $side->category_id,
            'category' => $side->category?->name,
            'seed' => $side->seed,
            'withdrawn' => $side->withdrawn_at !== null,
            'players' => $members->whereIn('role', EventSideMember::COMPETING)->count(),
            'size' => ['min' => $min, 'max' => $max],
            'complete' => $members->whereIn('role', EventSideMember::COMPETING)->count() >= $min,
            'members' => $members->map(fn (EventSideMember $m) => [
                'id' => $m->id,
                'registration_id' => $m->registration_id,
                'person' => $m->registration?->user?->uuid,
                'name' => $m->registration?->user?->full_name ?: $m->registration?->user?->name,
                'role' => $m->role,
                'number' => $m->number,
            ])->values()->all(),
        ];
    }

    /* ---------------- internals ---------------- */

    private function profile(ClubEvent $event): CompetitionProfile
    {
        return $this->types->for($event)->profile($event);
    }

    /** @return array{0: int, 1: ?int} the fewest and most players a side may field */
    private function size(EventSide $side): array
    {
        if ($side->kind === EventSide::KIND_PAIR) {
            return [2, 2];
        }

        $size = $this->profile($side->event)->teamSize;

        return [max(1, (int) ($size['min'] ?? 1)), isset($size['max']) ? (int) $size['max'] : null];
    }

    private function maxPlayers(EventSide $side): ?int
    {
        return $this->size($side)[1];
    }

    private function categoryOf(ClubEvent $event, mixed $id): ?EventCategory
    {
        if ($id === null || $id === '') {
            return null;
        }

        $category = EventCategory::where('event_id', $event->id)->find((int) $id);

        if (! $category || $category->isHeading()) {
            throw ValidationException::withMessages(['category_id' => __('events::events.side_division_invalid')]);
        }

        return $category;
    }

    /** One side per person per division (per event while the side has no division yet). */
    private function assertFreeInDivision(EventSide $side, ?ClubEventRegistration $registration, ?int $categoryId): void
    {
        if (! $registration) {
            return;
        }

        $taken = EventSideMember::query()
            ->whereHas('side', fn ($q) => $q->where('event_id', $side->event_id)
                ->where('id', '!=', $side->id)
                ->whereNull('withdrawn_at')
                ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId)))
            ->whereHas('registration', fn ($q) => $q->where('user_id', $registration->user_id))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['registration' => __('events::events.side_member_elsewhere')]);
        }
    }

    private function clean(mixed $value, int $max): ?string
    {
        $value = trim(strip_tags((string) $value));

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    /** A refresh signal to the people running the event — never the data itself. */
    private function announce(ClubEvent $event): void
    {
        $ids = $this->audience->managers($event);

        rescue(fn () => \Realtime()->publishMany(array_map(
            fn (int $id) => ['topic' => \Realtime()->userTopic($id, 'events'), 'payload' => ['action' => 'sides', 'event' => $event->uuid]],
            $ids,
        )), null, false);
    }
}
