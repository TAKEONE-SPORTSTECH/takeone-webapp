<?php

namespace App\Events\Support;

use App\Events\EventTypeRegistry;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventParticipantBan;
use App\Models\SkillAcquisition;
use App\Clubs\Models\Tenant;
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
    /** Most athletes one roster page returns. Searching reaches the rest. */
    private const ROSTER_LIMIT = 60;

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

        $existing = ClubEventRegistration::where('event_id', $event->id)
            ->where('user_id', $athlete->id)->first();

        // Once the competition is UNDERWAY the entry list is the thing being
        // run: the draw is cut from it, mats are assigned off it, and a name
        // arriving late would have to be squeezed into a bracket already in
        // play. So no new entries after the start — for a club or an
        // individual. Someone already entered is untouched; this only refuses
        // people who are not in yet.
        if (($event->hasStarted() || $event->isOverdueToStart())
            && ! ($existing && $existing->role === 'participant')) {
            return $reject('started', __('events.entry_event_started'));
        }

        $today = now()->startOfDay();
        if ($event->enrollment_starts_at && $today->lt($event->enrollment_starts_at)) {
            return $reject('not_open', __('events.entry_not_open', ['date' => $event->enrollment_starts_at->format('M j')]));
        }
        if ($event->enrollment_ends_at && $today->gt($event->enrollment_ends_at)) {
            return $reject('closed', __('events.entry_closed', ['date' => $event->enrollment_ends_at->format('M j')]));
        }

        // 5. Capacity — checked per athlete, so a squad fills the last places in
        //    order rather than all slipping in over the limit together.
        if ($event->max_capacity
            && ! ($existing && $existing->role === 'participant')
            && $event->participantRegistrations()->count() >= $event->max_capacity) {
            return $reject('full', __('events.entry_full'));
        }

        // 6. The owning package's own gate — for a championship this classifies
        //    the athlete into a weight division they actually belong in.
        //
        //    A DEFERRABLE refusal is admitted here and only here: the club is
        //    entering its own athlete, and the only thing missing is a fact the
        //    weigh-in desk will record on the day. They go in unclassified and
        //    are placed the moment they stand on the scale — see
        //    EventType::classifyEntry(). Self-entry still gets the refusal, so a
        //    member is asked to complete their own profile.
        $decision = $this->registry->for($event)->enrolmentGate($event, $athlete, $existing);
        if (! $decision->allowed && ! $decision->deferrable) {
            return $reject($decision->code ?? 'not_eligible', $decision->message ?? __('events.entry_not_eligible', ['name' => $name]));
        }

        $pendingWeighIn = ! $decision->allowed;

        // Is there anything to collect? The stated amount decides — a display
        // line reading "Free" and one reading "BHD 0" mean the same thing, and
        // "10-15 BHD" no longer quietly means 10.
        $paidFee = EventFee::isPaid($event, 'participant');

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
                // Entered BY a club, FOR that club. A club entry cannot be
                // disowned — the club made it — so any earlier rejection of a
                // self-entry claim is cleared by the club entering them itself.
                'entry_channel' => 'club',
                'representing_tenant_id' => $this->representingClubFor($actor, $athlete)
                    ?? $existing?->representing_tenant_id,
                'club_disowned_at' => null,
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
                // In, but not yet in a division — the desk places them.
                'pending_weigh_in' => $pendingWeighIn,
            ],
        ];
    }

    /**
     * The athletes this actor may enter, with each one's verdict precomputed —
     * so a coach sees who is enterable BEFORE submitting, rather than
     * discovering it in a wall of rejections.
     *
     * Paged and searchable: a club with 300 members would otherwise send 300
     * rows and run 300 enrolment gates to fill one sheet, and the coach would
     * still be scrolling for the one athlete they had in mind.
     *
     * @return array{athletes: array<int, array>, total: int, shown: int}
     */
    public function roster(ClubEvent $event, User $actor, ?string $query = null, int $limit = self::ROSTER_LIMIT): array
    {
        $clubIds = $this->administeredClubIds($actor);
        if (! $clubIds) {
            return ['athletes' => [], 'total' => 0, 'shown' => 0];
        }

        $query = trim((string) $query);

        $base = User::query()
            ->whereHas('memberClubs', fn ($q) => $q->whereIn('tenants.id', $clubIds)->where('memberships.status', 'active'))
            // Name, email or phone — the three things a coach knows about an
            // athlete they cannot see in the list. `mobile` is a {code,number}
            // JSON blob, so LIKE matches the digits inside it, as the people
            // search already does. Scoped to the actor's OWN club members, so
            // this can never become a way to probe the platform's user table.
            ->when($query !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('full_name', 'like', "%{$query}%")
                ->orWhere('name', 'like', "%{$query}%")
                ->orWhere('email', 'like', "%{$query}%")
                ->orWhere('mobile', 'like', "%{$query}%")));

        // A club with hundreds of members must not send hundreds of rows — each
        // one costs an enrolment-gate evaluation. The page is capped and the UI
        // says so; searching is how you reach the rest.
        $total = (clone $base)->count();

        $members = $base
            ->with('latestHealthRecord')
            ->orderBy('full_name')
            ->limit(max(1, min($limit, self::ROSTER_LIMIT)))
            ->get(['id', 'full_name', 'name', 'gender', 'birthdate', 'profile_picture', 'updated_at']);

        $registered = ClubEventRegistration::where('event_id', $event->id)
            ->whereIn('user_id', $members->pluck('id'))
            ->get()->keyBy('user_id');

        $type = $this->registry->for($event);

        // Closed to new entrants — started, over, or past its closing date. Every
        // row that is not already in shows as closed rather than pickable.
        $entries = $this->entriesState($event);

        $athletes = $members->map(function (User $m) use ($event, $type, $registered, $entries) {
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
                // Enterable when the gate allows it — or when its refusal is one
                // weigh-in settles, which is not the coach's problem today.
                'can_enter' => ! $banned && $entries['open'] && ($gate->allowed || $gate->deferrable),
                'pending_weigh_in' => ! $banned && $entries['open'] && ! $gate->allowed && $gate->deferrable,
                'reason' => match (true) {
                    $banned => __('events.entry_banned', ['name' => $m->full_name ?? $m->name]),
                    ! $entries['open'] => $entries['note'],
                    $gate->deferrable => __('events.entry_pending_weigh_in'),
                    default => $gate->message,
                },
            ];
        })->values()->all();

        return ['athletes' => $athletes, 'total' => $total, 'shown' => count($athletes)];
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

    /**
     * Clubs this actor may enter athletes for.
     *
     * Ownership counts implicitly — it is not a role row. Beyond that the answer
     * is the `enter-athletes` PERMISSION, whatever role carries it: a club that
     * wants its head coach doing entries grants it to them, and the coach stops
     * being the one person who knows the squad but cannot enter it. The two old
     * role slugs are still honoured, because the permission migration gave them
     * the grant and a club may since have taken it away from one of them.
     */
    public function administeredClubIds(User $actor): array
    {
        $owned = Tenant::where('owner_user_id', $actor->id)->pluck('id')->map('intval')->all();

        $granted = DB::table('user_roles')
            ->join('role_permission', 'role_permission.role_id', '=', 'user_roles.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
            ->where('user_roles.user_id', $actor->id)
            ->whereNotNull('user_roles.tenant_id')
            ->where('permissions.slug', 'enter-athletes')
            ->pluck('user_roles.tenant_id')->map('intval')->all();

        return array_values(array_unique(array_merge($owned, $granted)));
    }

    /**
     * The club an entry is made ON BEHALF OF.
     *
     * A coach enters athletes for the club they run — so it is the actor's club
     * that the athlete actively belongs to, not whichever club the athlete
     * happens to have joined first. When that is ambiguous or the actor is a
     * super-admin acting for nobody, we fall back to the athlete's single active
     * membership and otherwise record nothing rather than guess.
     */
    public function representingClubFor(User $actor, User $athlete): ?int
    {
        $clubIds = $this->administeredClubIds($actor);

        if ($clubIds) {
            $match = $athlete->memberClubs()->whereIn('tenants.id', $clubIds)
                ->wherePivot('status', 'active')->value('tenants.id');

            if ($match) {
                return (int) $match;
            }
        }

        $active = $athlete->memberClubs()->wherePivot('status', 'active')
            ->pluck('tenants.id');

        return $active->count() === 1 ? (int) $active->first() : null;
    }

    /**
     * Are entries open, and if not, why not — in the words the screen uses.
     *
     * Four things close a competition to new entrants, and they are not the same
     * sentence: it has not opened yet, the closing date has passed, it has
     * started, or it is over. One answer, computed in one place, so the join
     * button, the coach's roster, the entry endpoints and the toast can never
     * disagree about whether a place can still be taken.
     *
     * @return array{open: bool, note: ?string}
     */
    public function entriesState(ClubEvent $event): array
    {
        $today = now()->startOfDay();

        if ($event->hasEnded()) {
            return ['open' => false, 'note' => __('events.entry_event_ended')];
        }
        // Started — or DUE to have started. A competition whose start time is
        // behind it is not taking entries whether or not an organiser has
        // pressed the button yet: the entry list is what the day is being run
        // from, and "nobody pressed start" is an admin detail, not an invitation.
        if ($event->hasStarted() || $event->isOverdueToStart()) {
            return ['open' => false, 'note' => __('events.entry_event_started')];
        }
        if ($event->enrollment_starts_at && $today->lt($event->enrollment_starts_at)) {
            return ['open' => false, 'note' => __('events.entry_not_open', ['date' => $event->enrollment_starts_at->format('M j')])];
        }
        if ($event->enrollment_ends_at && $today->gt($event->enrollment_ends_at)) {
            return ['open' => false, 'note' => __('events.entry_closed', ['date' => $event->enrollment_ends_at->format('M j')])];
        }

        return ['open' => true, 'note' => null];
    }

    /* ---------------- Individual claims on a club ---------------- */

    /**
     * The clubs an athlete may claim to represent — their own active clubs, and
     * nobody else's. Claiming is not approved by anyone, so the ONLY thing
     * standing between a member and a club's name on a federation sheet is this
     * list; it is checked again server-side when the claim is made.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function representableClubs(User $athlete): array
    {
        return $athlete->memberClubs()->wherePivot('status', 'active')
            ->get(['tenants.id', 'tenants.club_name'])
            ->map(fn (Tenant $t) => ['id' => (int) $t->id, 'name' => $t->club_name])
            ->values()->all();
    }

    /**
     * The club to pre-select when this athlete enters an event themselves.
     *
     * Entering as a competitor is entering FOR someone, so the question is asked
     * at the moment of joining — and asking it with nothing chosen makes every
     * entrant do work the platform can do for them. The best guess is where they
     * last practised THIS event's sport: a member of a karate club and a
     * taekwondo club entering a karate open means the karate one, and the club
     * they trained at most recently is the one they still compete for.
     *
     * Falls back to their most recently joined club, then to nothing — an
     * athlete with no club competes unattached, which is a real answer.
     */
    public function defaultRepresentingClub(User $athlete, ClubEvent $event): ?int
    {
        $claimable = collect($this->representableClubs($athlete))->pluck('id')->all();

        if (! $claimable) {
            return null;
        }

        $sport = trim((string) ($event->sport ?? ''));

        if ($sport !== '') {
            // Their history of practising this sport, most recent first. The
            // skill names members type are free text ("Karate", "karate kata"),
            // so this matches loosely on purpose — a miss just falls through to
            // the club they joined last.
            $practised = SkillAcquisition::where('user_id', $athlete->id)
                ->where(fn ($q) => $q->where('skill_name', 'like', "%{$sport}%")
                    ->orWhere('activity_name', 'like', "%{$sport}%"))
                ->with('clubAffiliation:id,tenant_id,club_name')
                ->orderByRaw('COALESCE(end_date, start_date) DESC')
                ->get();

            foreach ($practised as $skill) {
                $affiliation = $skill->clubAffiliation;
                if (! $affiliation) {
                    continue;
                }

                // The affiliation may name a real tenant, or only a club NAME
                // typed by the member — match either against clubs they may
                // actually claim.
                $tenantId = $affiliation->tenant_id
                    ?: Tenant::whereIn('id', $claimable)
                        ->where('club_name', $affiliation->club_name)->value('id');

                if ($tenantId && in_array((int) $tenantId, $claimable, true)) {
                    return (int) $tenantId;
                }
            }
        }

        // No history for this sport — the club they joined most recently.
        $latest = $athlete->memberClubs()->wherePivot('status', 'active')
            ->orderByPivot('created_at', 'desc')->value('tenants.id');

        return $latest ? (int) $latest : $claimable[0];
    }

    /**
     * Self-entries claiming a club this actor has entry authority over.
     *
     * This is what the club gets INSTEAD of an approval step: the claims are
     * visible, and each one can be rejected — but the athlete is entered either
     * way from the moment they press the button.
     *
     * @return array<int, array<string, mixed>>
     */
    public function claims(ClubEvent $event, User $actor): array
    {
        $clubIds = $this->administeredClubIds($actor);
        if (! $clubIds) {
            return [];
        }

        return ClubEventRegistration::where('event_id', $event->id)
            ->where('role', 'participant')
            ->where('entry_channel', 'individual')
            ->whereIn('representing_tenant_id', $clubIds)
            ->with(['user:id,full_name,name,gender,profile_picture,profile_picture_is_public', 'category:id,name', 'representingTenant:id,club_name'])
            // Newest first and capped: a championship with a thousand entrants
            // must not turn one sheet into an unbounded response.
            ->orderByDesc('registered_at')
            ->limit(200)
            ->get()
            ->map(fn (ClubEventRegistration $r) => [
                'user_id' => (int) $r->user_id,
                'name' => $r->user?->full_name ?? $r->user?->name ?? __('events.claim_unknown_athlete'),
                'club' => $r->representingTenant?->club_name,
                'division' => $r->category?->name,
                'disowned' => $r->isDisowned(),
                'at' => $r->registered_at?->format('M j'),
            ])->values()->all();
    }

    /**
     * The club rejects a claim: the athlete keeps their place and competes
     * unattached.
     *
     * Never a removal. Whether someone competes is between them and the
     * organiser; all a club controls is whether its own name is printed beside
     * them.
     */
    public function disown(ClubEvent $event, User $actor, User $athlete): ClubEventRegistration
    {
        $registration = ClubEventRegistration::where('event_id', $event->id)
            ->where('user_id', $athlete->id)
            ->where('entry_channel', 'individual')
            ->whereNotNull('representing_tenant_id')
            ->firstOrFail();

        abort_unless(
            in_array((int) $registration->representing_tenant_id, $this->administeredClubIds($actor), true),
            403,
        );

        $registration->update(['club_disowned_at' => now()]);

        rescue(fn () => UserNotification::notifyUser($athlete->id, 'event', __('events.claim_disowned_title', [
            'club' => $registration->representingTenant?->club_name ?? '',
        ]), [
            'body' => __('events.claim_disowned_body', ['title' => $event->title]),
            'icon' => 'bi-flag',
            'action_url' => route('me.events.show', $event->uuid),
            'actor_id' => $actor->id,
            'tenant_id' => $registration->representing_tenant_id,
            'subject_type' => ClubEvent::class,
            'subject_id' => $event->id,
        ]), null, false);

        return $registration;
    }

    /**
     * Tell a club it is being represented.
     *
     * Sent to everyone who could act on it — the owner and anyone holding the
     * entry grant — because a claim nobody is told about is a claim nobody can
     * reject, which would make the disown right decorative.
     */
    public function notifyClaim(ClubEvent $event, User $athlete, int $tenantId): void
    {
        $recipients = $this->entryAuthorityUserIds($tenantId);
        $name = $athlete->full_name ?? $athlete->name ?? 'A member';

        foreach (array_diff($recipients, [$athlete->id]) as $userId) {
            rescue(fn () => UserNotification::notifyUser($userId, 'event', __('events.claim_notify_title', [
                'name' => $name,
            ]), [
                'body' => __('events.claim_notify_body', ['title' => $event->title]),
                'icon' => 'bi-flag',
                'action_url' => route('me.events.show', $event->uuid),
                'actor_id' => $athlete->id,
                'tenant_id' => $tenantId,
                'subject_type' => ClubEvent::class,
                'subject_id' => $event->id,
            ]), null, false);
        }
    }

    /**
     * Everyone who may enter athletes for a club: its owner, plus every member
     * holding the `enter-athletes` grant there.
     *
     * @return array<int, int>
     */
    public function entryAuthorityUserIds(int $tenantId): array
    {
        $owner = Tenant::whereKey($tenantId)->value('owner_user_id');

        $granted = DB::table('user_roles')
            ->join('role_permission', 'role_permission.role_id', '=', 'user_roles.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
            ->where('user_roles.tenant_id', $tenantId)
            ->where('permissions.slug', 'enter-athletes')
            ->pluck('user_roles.user_id')->map('intval')->all();

        return array_values(array_unique(array_filter(array_merge([(int) $owner], $granted))));
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
