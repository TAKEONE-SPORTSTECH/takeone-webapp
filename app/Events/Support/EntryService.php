<?php

namespace App\Events\Support;

use App\Support\Cldr;
use App\Events\EventTypeRegistry;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventParticipantBan;
use App\Members\Models\SkillAcquisition;
use App\Clubs\Models\Tenant;
use App\Members\Models\User;
use App\Members\Models\UserNotification;
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
     * `$optionsByUser` is keyed BY ATHLETE, not by squad, because a coach
     * entering fourteen people is making fourteen separate purchases: one wants
     * Gi and No-Gi, the next only Gi, the third neither. A single list for the
     * whole sheet would bill them all the same and be wrong for most of them.
     * An athlete missing from the map simply ticked nothing, which is why the
     * default is an empty array and every existing caller keeps its meaning.
     *
     * @param  array<int, int>  $userIds
     * @param  array<int|string, array<int, string>>  $optionsByUser  user id => option uuids
     * @return array{entered: array<int, array>, rejected: array<int, array>, going: int}
     */
    public function enterMany(ClubEvent $event, User $actor, array $userIds, array $optionsByUser = []): array
    {
        $entered = [];
        $rejected = [];

        $athletes = User::whereIn('id', array_slice(array_unique($userIds), 0, 200))
            ->with('latestHealthRecord')->get()->keyBy('id');

        foreach ($athletes as $athlete) {
            $chosen = $optionsByUser[$athlete->id] ?? $optionsByUser[(string) $athlete->id] ?? [];

            $verdict = $this->enter($event, $actor, $athlete, is_array($chosen) ? $chosen : []);

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
     * ── Entering a NAMED division ──────────────────────────────────────────
     * `$categoryId` is how an athlete comes to hold more than one entry in one
     * event. Jiu-jitsu is the case that forced it: Gi and No-Gi are separate
     * divisions, drawn separately, and an athlete routinely enters both — the
     * fee side already sold it that way ("Gi", "No-Gi", "Gi + No-Gi") while
     * the entry side allowed one row per event, so somebody who had PAID for
     * both could be drawn in only one.
     *
     * Passing null keeps the original behaviour EXACTLY: the athlete's single
     * existing entry is found and updated, and the package's own gate decides
     * the division. Every caller that predates this passes nothing and is
     * unaffected.
     *
     * Naming a division changes only WHICH entry is resolved: theirs in THAT
     * division, or a new one. It grants nothing — every rule above still runs,
     * in the same order, against the same athlete.
     *
     * @param  array<int, string>  $optionKeys  fee-option UUIDs the coach ticked for THIS athlete
     * @param  int|null  $categoryId  the division to enter them into, or null to let the gate decide
     * @return array{ok: bool, row: array}
     */
    public function enter(ClubEvent $event, User $actor, User $athlete, array $optionKeys = [], ?int $categoryId = null): array
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

        // The division being entered, if the caller named one — and only if it
        // belongs to THIS event. A category id from anywhere else is ignored
        // rather than trusted; nothing here takes an id on faith.
        $target = $categoryId
            ? $event->categories()->whereKey($categoryId)->first()
            : null;

        if ($categoryId && ! $target) {
            return $reject('no_division', __('events.division_not_found'));
        }

        // A heading is a title in the list, not a division. Refused by name
        // rather than left to fail oddly further down.
        if ($target?->isHeading()) {
            return $reject('is_heading', __('events.division_is_heading'));
        }

        // THEIR entry in the division being entered — or, when no division was
        // named, their one entry in this event, which is what every caller
        // before this asked for.
        $existing = ClubEventRegistration::where('event_id', $event->id)
            ->where('user_id', $athlete->id)
            ->when($target, fn ($q) => $q->where('category_id', $target->id))
            ->first();

        // Do they already stand in this event at all, in any division? This is
        // a different question from $existing and it decides the MONEY: a
        // second division is a second entry, but it is not a second purchase.
        // The event sells "Gi + No-Gi" as one option, so the fee lines stay on
        // the entry that carries the purchase and the sibling carries none.
        $alreadyInEvent = $existing
            ?: ClubEventRegistration::where('event_id', $event->id)
                ->where('user_id', $athlete->id)
                ->first();

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
            return $reject('not_open', __('events.entry_not_open', ['date' => Cldr::shortDate($event->enrollment_starts_at)]));
        }
        if ($event->enrollment_ends_at && $today->gt($event->enrollment_ends_at)) {
            return $reject('closed', __('events.entry_closed', ['date' => Cldr::shortDate($event->enrollment_ends_at)]));
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

        // A named division OUTRANKS the gate's classification, because naming
        // one is an organiser placing a competitor by hand — the same authority
        // that arranges a draw. The gate still runs: its refusals, its weight
        // and belt rules and its deferral all stand, and only the division it
        // would have chosen is replaced.
        if ($target) {
            $decision = $decision->intoCategory($target);
        }

        if (! $decision->allowed && ! $decision->deferrable) {
            return $reject($decision->code ?? 'not_eligible', $decision->message ?? __('events.entry_not_eligible', ['name' => $name]));
        }

        $pendingWeighIn = ! $decision->allowed;

        // Is there anything to collect? The stated amount decides — a display
        // line reading "Free" and one reading "BHD 0" mean the same thing, and
        // "10-15 BHD" no longer quietly means 10.
        $paidFee = EventFee::isPaid($event, 'participant');

        // Keyed by DIVISION as well as athlete since 2026-09-08, which is what
        // lets the second entry be created instead of overwriting the first.
        $registration = ClubEventRegistration::updateOrCreate(
            $target
                ? ['event_id' => $event->id, 'user_id' => $athlete->id, 'category_id' => $target->id]
                : ['event_id' => $event->id, 'user_id' => $athlete->id],
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

        // Freeze what this entry COSTS, as a set of lines on the entry itself.
        //
        // Priced from the event's own rows — the ticked uuids are looked up and
        // anything that does not resolve is dropped, so a stale form or a forged
        // key cannot invent a discount. `paid` above is untouched on purpose:
        // the lines say what was charged, `paid` says whether anybody has handed
        // the money over, and those have always been two questions.
        //
        // Only for an entry that is NEW, or one whose options the coach is
        // explicitly restating. Re-running a squad sheet with nothing ticked
        // must not quietly wipe an option somebody already agreed to pay for,
        // and re-pricing an old entry at today's clock would hand it a late
        // penalty it never incurred — hence the entry's own moment as `$at`.
        if ((! $alreadyInEvent && ! $existing) || $optionKeys !== []) {
            EventFee::commit($registration, EventFee::quote(
                $event,
                'participant',
                $optionKeys,
                $existing?->registered_at ?? $registration->registered_at,
            ));
        }

        // The entrant set changed — let the package re-derive its draw.
        $this->registry->for($event)->onEntrantsChanged($event, $decision->category);

        // Tell the athlete they were entered — they did not do this themselves,
        // so they must find out from us and not on the day.
        if (! $alreadyInEvent) {
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
                // What this entry was actually charged, read back from its own
                // frozen lines — so a coach who ticked two options sees the two
                // options, not the event's headline price.
                'charged' => EventFee::charged($registration, $event),
                'currency' => EventFee::currency($event),
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
            return ['open' => false, 'note' => __('events.entry_not_open', ['date' => Cldr::shortDate($event->enrollment_starts_at)])];
        }
        if ($event->enrollment_ends_at && $today->gt($event->enrollment_ends_at)) {
            return ['open' => false, 'note' => __('events.entry_closed', ['date' => Cldr::shortDate($event->enrollment_ends_at)])];
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
                'at' => $r->registered_at ? Cldr::shortDate($r->registered_at) : null,
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
            'subject_type' => (new ClubEvent)->getMorphClass(),
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
                'subject_type' => (new ClubEvent)->getMorphClass(),
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

    /**
     * Is this person barred from this event?
     *
     * Public because the public door (PublicEntry) has to ask the SAME
     * question — a block that only the coach's path honoured would be no
     * block at all. One rule, one place (Shared Stays Shared).
     */
    public function isBanned(ClubEvent $event, int $userId): bool
    {
        return EventParticipantBan::where('user_id', $userId)
            ->where(function ($q) use ($event) {
                $q->where(fn ($w) => $w->where('scope', 'event')->where('event_id', $event->id))
                    ->orWhere(fn ($w) => $w->where('scope', 'club')->where('tenant_id', $event->tenant_id));
            })->exists();
    }

    /**
     * Take athletes OUT of an event — the inverse of enterMany().
     *
     * Here rather than in the controller for the same reason enter() is here:
     * an entry list is a set of rules, and the rules for leaving it are as real
     * as the rules for joining. A controller that deleted rows directly would
     * be a second entry system with none of them.
     *
     * Takes REGISTRATION ids, not user ids, and every one of them is looked up
     * scoped to this event — an id from another competition finds nothing and is
     * reported as such, so this endpoint cannot be used to reach across events.
     *
     * Partial success is the normal outcome, exactly as it is for entering: an
     * organiser sweeps six no-shows off the list and one of them turns out to
     * have already fought.
     *
     * @param  array<int, int>  $registrationIds
     * @return array{removed: array<int, array>, rejected: array<int, array>, going: int}
     */
    public function removeMany(ClubEvent $event, User $actor, array $registrationIds): array
    {
        $removed = [];
        $rejected = [];

        $rows = ClubEventRegistration::where('event_id', $event->id)
            ->whereIn('id', array_slice(array_unique($registrationIds), 0, 200))
            ->with('user:id,full_name,name')
            ->get();

        foreach ($rows as $row) {
            $verdict = $this->remove($event, $actor, $row);

            if ($verdict['ok']) {
                $removed[] = $verdict['row'];
            } else {
                $rejected[] = $verdict['row'];
            }
        }

        return [
            'removed' => $removed,
            'rejected' => $rejected,
            'going' => $event->participantRegistrations()->count(),
        ];
    }

    /**
     * Take ONE athlete out.
     *
     * Two refusals, and both are about the competition rather than the person:
     *
     *   · The event is UNDERWAY. From the first bout the entry list is the thing
     *     being run — the draw was cut from it and the mats are assigned off it
     *     — so a name cannot be lifted out of it without the bracket around it
     *     ceasing to mean anything. This mirrors enter(), which refuses a new
     *     name from the same moment for the same reason. A no-show on the day is
     *     a walkover recorded on the bout, not a deletion.
     *   · They have already FOUGHT. Belt and braces for the case above: an event
     *     whose start was never pressed can still have a result recorded, and a
     *     bout with a winner is a fact about the competition that outlives
     *     somebody's place in it.
     *
     * The proof of payment is deleted with the row. `photo` and `club_logo` go
     * automatically (ClubEventRegistration uses DeletesUploadedFiles, and this
     * deletes model by model so the hook actually fires); `payment_proof` is not
     * declared on that trait, so it is purged HERE rather than by widening the
     * model's behaviour for every other delete path in the platform.
     *
     * MONEY IS NOT TOUCHED. Any club transaction the entry created stays in the
     * books: refunding is a decision with an amount attached, it belongs to
     * whoever keeps the accounts, and a delete button is not the place to make
     * it. The caller says so in the confirmation.
     *
     * @return array{ok: bool, row: array}
     */
    public function remove(ClubEvent $event, User $actor, ClubEventRegistration $registration, bool $notify = true): array
    {
        $name = $registration->user?->full_name
            ?: ($registration->user?->name ?: __('shared.unknown'));

        $reject = fn (string $code, string $message) => [
            'ok' => false,
            'row' => ['registration_id' => $registration->id, 'name' => $name, 'code' => $code, 'message' => $message],
        ];

        if ($event->hasStarted() || $event->isOverdueToStart()) {
            return $reject('started', __('events.entry_remove_started', ['name' => $name]));
        }

        if ($this->hasFought($event, $registration)) {
            return $reject('has_result', __('events.entry_remove_has_result', ['name' => $name]));
        }

        // Held for after the delete: the division decides which draw has to be
        // re-cut, and the row is gone by then.
        $category = $registration->category;
        $athleteId = $registration->user_id;
        $proof = $registration->payment_proof;

        DB::transaction(function () use ($registration) {
            // Files BEFORE the row, while the paths are still readable.
            // `photo`/`club_logo` are the trait's; this one is not declared
            // there, so it is done by hand.
            if ($registration->payment_proof) {
                rescue(fn () => \Illuminate\Support\Facades\Storage::disk('local')
                    ->delete($registration->payment_proof), null, false);
            }

            $registration->delete();
        });

        // The entrant set changed — the package re-cuts an automatic draw, and
        // lifts them out of their slot in a hand-arranged one.
        $this->registry->for($event)->onEntrantsChanged($event, $category);

        // They did not do this themselves, so they must hear it from us rather
        // than by turning up. Skipped for an entry nobody has claimed: there is
        // an account behind it, but no person reading it yet.
        // `$notify` is false only when the CALLER is telling them something
        // truer. A granted withdrawal removes the entry through this same
        // method, and "an organiser removed you from the event" is the wrong
        // sentence for a departure the athlete asked for — App\Events\Support\
        // Withdrawal sends "your withdrawal was granted" instead. Defaults to
        // true, so every existing caller is unchanged.
        if ($notify && $athleteId && $registration->entry_state !== 'unclaimed') {
            $this->notifyRemoved($event, $athleteId, $actor);
        }

        return [
            'ok' => true,
            'row' => [
                'registration_id' => $registration->id,
                'user_id' => $athleteId,
                'name' => $name,
                'division' => $category?->name,
                'had_proof' => (bool) $proof,
            ],
        ];
    }

    /**
     * Has this entry already been in a decided bout?
     *
     * Asks the MATCH table directly rather than the owning package, because the
     * question is the same for every sport that draws a bracket and a type that
     * draws none has no rows here to find.
     */
    private function hasFought(ClubEvent $event, ClubEventRegistration $registration): bool
    {
        return \App\Models\EventMatch::where('event_id', $event->id)
            ->where(fn ($q) => $q
                ->where('a_competitor_id', $registration->id)
                ->orWhere('b_competitor_id', $registration->id))
            ->where(fn ($q) => $q->whereNotNull('winner')->orWhere('status', 'done'))
            ->exists();
    }

    /** Tell somebody their entry was withdrawn, and by whose club. */
    private function notifyRemoved(ClubEvent $event, int $athleteId, User $actor): void
    {
        rescue(fn () => UserNotification::notifyUser($athleteId, 'event', __('events.entry_removed_notify_title', [
            'title' => $event->title,
        ]), [
            'body' => __('events.entry_removed_notify_body', ['club' => $event->tenant?->club_name ?? '']),
            'icon' => 'bi-person-dash',
            'action_url' => route('me.events.show', $event->uuid),
            'actor_id' => $actor->id,
            'tenant_id' => $event->tenant_id,
            'subject_type' => (new ClubEvent)->getMorphClass(),
            'subject_id' => $event->id,
        ]), null, false);
    }

    private function notifyEntered(ClubEvent $event, User $athlete, User $actor, ?string $division): void
    {
        /*
         * Somebody else put them in — a coach entering a squad, an organiser
         * working off a paper list. They may not have asked to be entered and
         * may not know they have been, so the email matters MORE here than on a
         * self-entry, not less. `EntryMail` declines quietly when there is no
         * inbox, which is the common case for a paper entry.
         */
        app(EntryMail::class)->send(
            $event,
            $athlete,
            \App\Mail\EventEntryEmail::ENTERED,
            $division,
            \App\Models\ClubEventRegistration::where('event_id', $event->id)
                ->where('user_id', $athlete->id)->first(),
        );

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
            'subject_type' => (new ClubEvent)->getMorphClass(),
            'subject_id' => $event->id,
        ]), null, false);
    }
}
