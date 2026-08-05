<?php

namespace App\Events\Support;

use App\Events\EventTypeRegistry;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventParticipantBan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;

/**
 * Entering athletes into an event — one at a time, or a whole squad at once.
 *
 * Tournaments do not receive entries one athlete at a time: a coach enters
 * fourteen of their club's athletes in a sitting. But a bulk entry must never
 * become a way around the rules that apply to a single one, so EVERY athlete
 * goes through the same checks in the same order as self-entry: eligibility for
 * the event's scope, bans, the enrolment window, capacity, and the owning
 * package's own gate (for a championship, the weight-class routing).
 *
 * Partial success is the normal outcome — 14 offered, 12 entered, 2 refused
 * because they have no weight on file — so the caller always gets a per-athlete
 * verdict rather than one all-or-nothing answer.
 */
class EntryService
{
    public function __construct(private EventTypeRegistry $registry) {}

    /**
     * Enter many athletes on behalf of their club.
     *
     * @param  array<int, int>  $userIds
     * @return array{entered: array<int, array>, rejected: array<int, array>, going: int}
     */
    public function enterMany(ClubEvent $event, User $actor, array $userIds): array
    {
        $entered = [];
        $rejected = [];

        $athletes = User::whereIn('id', array_slice(array_unique($userIds), 0, 200))
            ->with('latestHealthRecord')->get()->keyBy('id');

        foreach ($athletes as $athlete) {
            $verdict = $this->enter($event, $actor, $athlete);

            if ($verdict['ok']) {
                $entered[] = $verdict['row'];
            } else {
                $rejected[] = $verdict['row'];
            }
        }

        return [
            'entered' => $entered,
            'rejected' => $rejected,
            'going' => $event->participantRegistrations()->count(),
        ];
    }

    /**
     * Enter one athlete, applying every rule self-entry applies.
     *
     * @return array{ok: bool, row: array}
     */
    public function enter(ClubEvent $event, User $actor, User $athlete): array
    {
        $name = $athlete->full_name ?? $athlete->name ?? 'Member';
        $reject = fn (string $code, string $message) => [
            'ok' => false,
            'row' => ['user_id' => $athlete->id, 'name' => $name, 'code' => $code, 'message' => $message],
        ];

        // 1. May the actor act for this athlete at all?
        if (! $this->mayEnter($actor, $athlete)) {
            return $reject('not_yours', __('events.entry_not_your_athlete', ['name' => $name]));
        }

        // 2. Does the event's scope reach them? (An athlete from another club
        //    can only be entered into an event open to them.)
        if (! $this->isEligible($event, $athlete)) {
            return $reject('out_of_scope', __('events.entry_out_of_scope', ['name' => $name]));
        }

        // 3. Moderation.
        if ($this->isBanned($event, $athlete->id)) {
            return $reject('banned', __('events.entry_banned', ['name' => $name]));
        }

        // 4. Timing.
        if ($event->hasEnded()) {
            return $reject('ended', __('events.entry_event_ended'));
        }

        $today = now()->startOfDay();
        if ($event->enrollment_starts_at && $today->lt($event->enrollment_starts_at)) {
            return $reject('not_open', __('events.entry_not_open', ['date' => $event->enrollment_starts_at->format('M j')]));
        }
        if ($event->enrollment_ends_at && $today->gt($event->enrollment_ends_at)) {
            return $reject('closed', __('events.entry_closed', ['date' => $event->enrollment_ends_at->format('M j')]));
        }

        $existing = ClubEventRegistration::where('event_id', $event->id)
            ->where('user_id', $athlete->id)->first();

        // 5. Capacity — checked per athlete, so a squad fills the last places in
        //    order rather than all slipping in over the limit together.
        if ($event->max_capacity
            && ! ($existing && $existing->role === 'participant')
            && $event->participantRegistrations()->count() >= $event->max_capacity) {
            return $reject('full', __('events.entry_full'));
        }

        // 6. The owning package's own gate — for a championship this classifies
        //    the athlete into a weight division they actually belong in.
        $decision = $this->registry->for($event)->enrolmentGate($event, $athlete, $existing);
        if (! $decision->allowed) {
            return $reject($decision->code ?? 'not_eligible', $decision->message ?? __('events.entry_not_eligible', ['name' => $name]));
        }

        $fee = $event->participant_fee;
        $paidFee = $fee && ! str_contains(strtolower($fee), 'free');

        $registration = ClubEventRegistration::updateOrCreate(
            ['event_id' => $event->id, 'user_id' => $athlete->id],
            [
                'role' => 'participant',
                'status' => 'joined',
                'paid' => $existing?->paid ?? ! $paidFee,
                'category_id' => $decision->category?->id ?? $existing?->category_id,
                'weight' => $decision->weight ?? $existing?->weight,
                'registered_at' => $existing?->registered_at ?? now(),
                'entered_by' => $actor->id,
            ],
        );

        // The entrant set changed — let the package re-derive its draw.
        $this->registry->for($event)->onEntrantsChanged($event, $decision->category);

        // Tell the athlete they were entered — they did not do this themselves,
        // so they must find out from us and not on the day.
        if (! $existing) {
            $this->notifyEntered($event, $athlete, $actor, $decision->category?->name);
        }

        return [
            'ok' => true,
            'row' => [
                'user_id' => $athlete->id,
                'name' => $name,
                'registration_id' => $registration->id,
                'division' => $decision->category?->name,
                'paid' => (bool) $registration->paid,
            ],
        ];
    }

    /**
     * The athletes this actor may enter, with each one's verdict precomputed —
     * so a coach sees who is enterable BEFORE submitting, rather than
     * discovering it in a wall of rejections.
     *
     * @return array<int, array>
     */
    public function roster(ClubEvent $event, User $actor): array
    {
        $clubIds = $this->administeredClubIds($actor);
        if (! $clubIds) {
            return [];
        }

        $members = User::query()
            ->whereHas('memberClubs', fn ($q) => $q->whereIn('tenants.id', $clubIds)->where('memberships.status', 'active'))
            ->with('latestHealthRecord')
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'name', 'gender', 'birthdate', 'profile_picture', 'updated_at']);

        $registered = ClubEventRegistration::where('event_id', $event->id)
            ->whereIn('user_id', $members->pluck('id'))
            ->get()->keyBy('user_id');

        $type = $this->registry->for($event);

        return $members->map(function (User $m) use ($event, $type, $registered) {
            $existing = $registered->get($m->id);
            $gate = $type->enrolmentGate($event, $m, $existing);
            $banned = $this->isBanned($event, $m->id);

            return [
                'id' => $m->id,
                'name' => $m->full_name ?? $m->name ?? 'Member',
                'gender' => $m->gender,
                'entered' => (bool) $existing && $existing->role === 'participant',
                'entered_by_them' => $existing && $existing->entered_by === null,
                'division' => $existing?->category?->name ?? $gate->category?->name,
                'weight' => $m->latestHealthRecord?->weight ? (float) $m->latestHealthRecord->weight : null,
                'can_enter' => ! $banned && $gate->allowed,
                'reason' => $banned ? __('events.entry_banned', ['name' => $m->full_name ?? $m->name]) : $gate->message,
            ];
        })->values()->all();
    }

    /* ---------------- Authorization ---------------- */

    /**
     * An actor may enter an athlete only when they run a club that athlete
     * actively belongs to. A coach cannot enter someone else's athletes, and an
     * ordinary member cannot enter anyone but themselves (they use self-entry).
     */
    public function mayEnter(User $actor, User $athlete): bool
    {
        if ($actor->id === $athlete->id) {
            return true;
        }
        if ($actor->isSuperAdmin()) {
            return true;
        }

        $clubIds = $this->administeredClubIds($actor);

        return $clubIds !== []
            && $athlete->memberClubs()
                ->whereIn('tenants.id', $clubIds)
                ->wherePivot('status', 'active')
                ->exists();
    }

    /** Clubs this actor owns or administers. */
    public function administeredClubIds(User $actor): array
    {
        $owned = Tenant::where('owner_user_id', $actor->id)->pluck('id')->map('intval')->all();

        $administered = DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $actor->id)
            ->whereNotNull('user_roles.tenant_id')
            ->whereIn('roles.slug', ['club-admin', 'owner'])
            ->pluck('user_roles.tenant_id')->map('intval')->all();

        return array_values(array_unique(array_merge($owned, $administered)));
    }

    /* ---------------- Shared guards ---------------- */

    private function isEligible(ClubEvent $event, User $athlete): bool
    {
        if ($athlete->memberClubs()->whereKey($event->tenant_id)->exists()) {
            return true;
        }

        return match ($event->scope ?? 'internal') {
            'inter_club', 'worldwide' => true,
            'nationwide', 'regional' => (bool) $event->tenant?->country
                && $athlete->memberClubs()->where('tenants.country', $event->tenant->country)->exists(),
            default => false,
        };
    }

    private function isBanned(ClubEvent $event, int $userId): bool
    {
        return EventParticipantBan::where('user_id', $userId)
            ->where(function ($q) use ($event) {
                $q->where(fn ($w) => $w->where('scope', 'event')->where('event_id', $event->id))
                    ->orWhere(fn ($w) => $w->where('scope', 'club')->where('tenant_id', $event->tenant_id));
            })->exists();
    }

    private function notifyEntered(ClubEvent $event, User $athlete, User $actor, ?string $division): void
    {
        rescue(fn () => UserNotification::notifyUser($athlete->id, 'event', __('events.entry_notify_title', [
            'title' => $event->title,
        ]), [
            'body' => $division
                ? __('events.entry_notify_body_division', ['club' => $event->tenant?->club_name ?? '', 'division' => $division])
                : __('events.entry_notify_body', ['club' => $event->tenant?->club_name ?? '']),
            'icon' => 'bi-person-check',
            'action_url' => route('me.events.show', $event->uuid),
            'actor_id' => $actor->id,
            'tenant_id' => $event->tenant_id,
            'subject_type' => ClubEvent::class,
            'subject_id' => $event->id,
        ]), null, false);
    }
}
