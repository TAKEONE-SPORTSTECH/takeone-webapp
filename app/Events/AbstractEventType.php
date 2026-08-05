<?php

namespace App\Events;

use App\Events\Contracts\EventType;
use App\Events\Support\BracketView;
use App\Events\Support\EnrolmentDecision;
use App\Events\Support\Milestone;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventCategory;
use App\Models\EventExpense;
use App\Models\User;
use Carbon\Carbon;

/**
 * Shared foundation for event-type packages.
 *
 * Provides the behaviour every type needs identically (core validation shape,
 * the default date-driven lifecycle, fee × paid-count financials, a plain
 * roster) so a package only writes what actually differs. Nothing here is
 * type-specific — anything that branches on a sport or type belongs in the
 * package that owns it, never in this class.
 */
abstract class AbstractEventType implements EventType
{
    public function label(): string
    {
        return config('event_schema.types.'.$this->schemaType().'.label', 'Event');
    }

    /** The config/event_schema.php `types` key this package presents as. */
    protected function schemaType(): string
    {
        return 'class';
    }

    public function icon(): string
    {
        return config('event_schema.types.'.$this->schemaType().'.icon', 'bi-calendar-event');
    }

    public function color(): string
    {
        return config('event_schema.types.'.$this->schemaType().'.color', '#7c3aed');
    }

    public function formSections(): array
    {
        return config('event_schema.types.'.$this->schemaType().'.sections', []);
    }

    public function formCatalog(): array
    {
        return [];
    }

    /* ---------------- Schema & creation input ---------------- */

    public function validationRules(?ClubEvent $event = null): array
    {
        return [
            'gps_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'gps_long' => ['nullable', 'numeric', 'between:-180,180'],
            'location_url' => ['nullable', 'url:http,https', 'max:500'],
            'agenda' => ['nullable', 'array', 'max:40'],
            'agenda.*.t' => ['nullable', 'date'],
            'agenda.*.d' => ['nullable', 'string', 'max:200'],
            'requirements' => ['nullable', 'array', 'max:30'],
            'requirements.*' => ['nullable', 'string', 'max:200'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['nullable', 'string', 'max:40'],
            'phases' => ['nullable', 'array', 'max:20'],
            'phases.*.label' => ['nullable', 'string', 'max:60'],
            'phases.*.date' => ['nullable', 'date'],
            'phases.*.note' => ['nullable', 'string', 'max:160'],
        ];
    }

    public function columnsFromInput(array $data, ?ClubEvent $event = null): array
    {
        return [
            'agenda' => $this->cleanAgenda($data['agenda'] ?? []),
            'requirements' => $this->cleanList($data['requirements'] ?? []),
            'tags' => $this->cleanTags($data['tags'] ?? []),
            'phases' => $this->cleanPhases($data['phases'] ?? []),
        ];
    }

    public function saveRelatedData(ClubEvent $event, array $data): void
    {
        // Types with no side tables store everything on the event row.
    }

    /* ---------------- Enrolment ---------------- */

    public function enrolmentGate(ClubEvent $event, User $user, ?ClubEventRegistration $existing = null): EnrolmentDecision
    {
        return EnrolmentDecision::allow();
    }

    public function onEntrantsChanged(ClubEvent $event, ?EventCategory $category = null): void
    {
        // No derived state by default.
    }

    /* ---------------- Lifecycle ---------------- */

    public function stages(): array
    {
        return ['draft', 'enrolling', 'running', 'completed', 'cancelled'];
    }

    public function stage(ClubEvent $event): string
    {
        if ($event->status === 'cancelled') {
            return 'cancelled';
        }
        if ($event->hasEnded()) {
            return 'completed';
        }
        if ($event->hasStarted()) {
            return 'running';
        }
        if ($event->enrollment_starts_at && now()->startOfDay()->lt($event->enrollment_starts_at)) {
            return 'draft';
        }

        return 'enrolling';
    }

    public function canTransitionTo(ClubEvent $event, string $stage): bool
    {
        if (! in_array($stage, $this->stages(), true)) {
            return false;
        }
        // Cancelling is always available while the event is not finished;
        // everything else is derived from the calendar, never set by hand.
        if ($stage === 'cancelled') {
            return $this->stage($event) !== 'completed';
        }

        return false;
    }

    /* ---------------- Engine ---------------- */

    public function recordOutcome(ClubEvent $event, int $unitId, array $payload): array
    {
        return [];
    }

    public function performAction(ClubEvent $event, string $action, array $payload = []): array
    {
        return ['success' => false, 'message' => __('events.action_unsupported')];
    }

    public function availableActions(ClubEvent $event): array
    {
        return [];
    }

    /* ---------------- Realtime ---------------- */

    /**
     * Default audience: everyone registered for the event (competitors and
     * spectators alike) plus whoever created it. A type that can narrow this —
     * to one division, one examinee — should override.
     */
    public function audienceFor(ClubEvent $event, string $reason = 'update'): array
    {
        $ids = $event->registrations()->pluck('user_id')->all();

        if ($event->created_by) {
            $ids[] = $event->created_by;
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * Best-effort MQTT fan-out for this event. Never throws — a broker outage
     * must not fail the write that triggered it.
     *
     * @param  array<string, mixed>  $payload  always carries `action` + `event`
     */
    protected function broadcast(ClubEvent $event, array $payload, ?array $audience = null): void
    {
        $audience ??= $this->audienceFor($event, $payload['action'] ?? 'update');
        if (! $audience) {
            return;
        }

        $body = $payload + ['event' => $event->uuid];

        rescue(function () use ($audience, $body) {
            \Realtime()->publishMany(array_map(
                fn (int $id) => ['topic' => \Realtime()->userTopic($id, 'events'), 'payload' => $body],
                $audience,
            ));
        }, null, false);
    }

    /**
     * Milestones every event has, whatever its type: it is announced, enrolment
     * opens and closes, and the morning of the day itself people are reminded
     * where to be. A type ADDS to these (weigh-in, draw) rather than replacing
     * them — call parent::notificationSchedule() and merge.
     *
     * @return array<int, Milestone>
     */
    public function notificationSchedule(ClubEvent $event): array
    {
        $where = $event->location ?: ($event->tenant?->club_name ?? '');
        $when = $event->start_time ? Carbon::parse($event->start_time)->format('g:i A') : '';
        $leadDays = (int) config('event_notifications.enrolment_closing_lead_days', 1);

        $milestones = [
            new Milestone(
                key: 'created',
                title: __('events.notify_created_title', ['title' => $event->title]),
                body: __('events.notify_created_body', [
                    'club' => $event->tenant?->club_name ?? '',
                    'date' => $event->date?->format('M j') ?? '',
                ]),
                at: null,                                   // fires on creation
                audience: Milestone::AUDIENCE_SCOPE,
                icon: 'bi-megaphone',
            ),
        ];

        if ($event->enrollment_starts_at) {
            $milestones[] = new Milestone(
                key: 'enrolment_opens',
                title: __('events.notify_enrolment_open_title', ['title' => $event->title]),
                body: __('events.notify_enrolment_open_body'),
                at: $event->enrollment_starts_at->copy()->startOfDay(),
                audience: Milestone::AUDIENCE_SCOPE,
                icon: 'bi-door-open',
            );
        }

        if ($event->enrollment_ends_at) {
            $milestones[] = new Milestone(
                key: 'enrolment_closing',
                title: __('events.notify_enrolment_closing_title', ['title' => $event->title]),
                body: __('events.notify_enrolment_closing_body', [
                    'date' => $event->enrollment_ends_at->format('M j'),
                ]),
                at: $event->enrollment_ends_at->copy()->subDays($leadDays)->startOfDay(),
                audience: Milestone::AUDIENCE_SCOPE,
                icon: 'bi-hourglass-split',
            );

            $milestones[] = new Milestone(
                key: 'enrolment_closed',
                title: __('events.notify_enrolment_closed_title', ['title' => $event->title]),
                body: __('events.notify_enrolment_closed_body'),
                at: $event->enrollment_ends_at->copy()->endOfDay(),
                audience: Milestone::AUDIENCE_REGISTRANTS,
                icon: 'bi-door-closed',
            );
        }

        $milestones[] = new Milestone(
            key: 'event_day',
            title: __('events.notify_event_day_title', ['title' => $event->title]),
            body: __('events.notify_event_day_body', ['time' => $when, 'place' => $where]),
            at: $this->localMorningOfEvent($event),
            audience: Milestone::AUDIENCE_REGISTRANTS,
            icon: 'bi-alarm',
        );

        return $milestones;
    }

    /**
     * Early morning of the event day, in the HOST CLUB's timezone — a 7am
     * reminder must be 7am where the event is, not on the server.
     */
    protected function localMorningOfEvent(ClubEvent $event): ?Carbon
    {
        if (! $event->date) {
            return null;
        }

        $tz = $event->tenant?->timezone ?: config('app.timezone');
        $hour = (int) config('event_notifications.event_day_reminder_hour', 7);

        return Carbon::parse($event->date->toDateString(), $tz)->setTime($hour, 0)->utc();
    }

    /* ---------------- Outputs ---------------- */

    public function results(ClubEvent $event): array
    {
        return array_values($event->results ?? []);
    }

    public function allowsManualResults(): bool
    {
        return true;
    }

    public function timeline(ClubEvent $event): array
    {
        return $event->phases ?: [];
    }

    /** Types with no running order have nothing to count down to. */
    public function nextUp(ClubEvent $event, User $athlete): ?array
    {
        return null;
    }

    public function rosterRows(ClubEvent $event): array
    {
        return $event->participantRegistrations()
            ->with(['user:id,full_name,name,gender', 'category:id,name,weight_class'])
            ->latest('registered_at')->get()
            ->map(fn ($r) => [
                'id' => $r->user?->id,
                'name' => $r->user?->full_name ?? $r->user?->name ?? 'Member',
                'gender' => $r->user?->gender,
                'category' => $r->category?->name,
                'weight_class' => $r->category?->weight_class,
                'meta' => $r->meta ?: ($r->category?->name ?? ($r->paid ? 'Registered' : 'Pending payment')),
                // The three things an organiser checks off before a competitor
                // can be drawn. `meta` is the entry's country, the same field
                // BracketView reads to fly a flag.
                'country' => $r->meta ?: null,
                'enrolled' => $r->status === 'joined',
                // Claimed vs verified. Money and weight are both things a
                // competitor asserts and an official confirms; the roster shows
                // the assertion in amber and the confirmation in green.
                'paid' => (bool) $r->paid,
                'paid_verified' => $r->paid && $r->paid_by !== null,
                'weighed' => $r->weighed_in_at !== null,
                'weighed_verified' => $r->weighed_in_at !== null && $r->weighed_in_by !== null,
            ])->values()->all();
    }

    /* ---------------- Financials ---------------- */

    public function finance(ClubEvent $event): array
    {
        $pFee = $this->feeAmount($event->participant_fee);
        $sFee = $event->spectator_enabled ? $this->feeAmount($event->spectator_fee) : 0.0;

        $paidP = $event->registrations()->where('role', 'participant')->where('paid', true)->count();
        $paidS = $event->registrations()->where('role', 'spectator')->where('paid', true)->count();
        $pRev = $paidP * $pFee;
        $sRev = $paidS * $sFee;

        $expenses = $event->expenses()->latest('id')->get(['id', 'label', 'amount'])
            ->map(fn (EventExpense $x) => ['id' => $x->id, 'label' => $x->label, 'amount' => (float) $x->amount])->all();
        $expTotal = array_sum(array_column($expenses, 'amount'));

        return [
            'currency' => $event->tenant?->currency ?: 'BHD',
            'participant_fee' => $pFee,
            'paid_participants' => $paidP,
            'participant_revenue' => $pRev,
            'spectator_enabled' => (bool) $event->spectator_enabled,
            'spectator_fee' => $sFee,
            'paid_spectators' => $paidS,
            'spectator_revenue' => $sRev,
            'revenue' => $pRev + $sRev,
            'expenses' => $expenses,
            'expenses_total' => $expTotal,
            'profit' => ($pRev + $sRev) - $expTotal,
            'breakdown' => [],
        ];
    }

    /** First numeric value in a fee string ("BHD 10" → 10.0). */
    protected function feeAmount(?string $fee): float
    {
        return ($fee && preg_match('/[\d.]+/', $fee, $m)) ? (float) $m[0] : 0.0;
    }

    /* ---------------- Display ---------------- */

    public function views(): array
    {
        return [];
    }

    public function viewData(ClubEvent $event, User $viewer): array
    {
        return [
            'type_key' => $this->key(),
            'stage' => $this->stage($event),
            'manual_results' => $this->allowsManualResults(),
        ];
    }

    /**
     * Default run screen: each division with its entrants, its matches grouped
     * into rounds, and its podium. No day/mat scheduling — a type that schedules
     * its play across days and courts overrides this to add it.
     */
    public function runData(ClubEvent $event, User $viewer): array
    {
        $divisions = [];

        foreach ($event->categories()->with(['matches', 'registrations.user:id,full_name,name'])->get() as $c) {
            $divisions[$c->id] = $this->divisionView($event, $c, $viewer);
        }

        return ['categories' => $divisions];
    }

    /**
     * Default bracket view: any type whose divisions hold knockout bouts gets a
     * bracket for free, in the one shared shape. A type with no bouts at all
     * (a belt test, a league table) yields nothing and the screen offers no
     * bracket rather than an empty one.
     */
    public function bracketView(ClubEvent $event, User $viewer): array
    {
        if (! $event->categories()->whereHas('matches')->exists()) {
            return [];
        }

        return (new BracketView)->divisions($event);
    }

    /** One division's run-screen view model. Types extend this via matchView(). */
    protected function divisionView(ClubEvent $event, EventCategory $c, User $viewer): array
    {
        $rounds = [];
        $flat = [];

        foreach ($c->matches as $m) {
            $rounds[$m->round] ??= ['name' => $m->round, 'matches' => []];
            $rounds[$m->round]['matches'][] = $this->matchView($event, $c, $m);
            $flat[] = [
                'round' => $m->round, 'court' => $m->court ?? '', 'time' => $m->scheduled_time ?? '',
                'status' => $m->status, 'winner' => $m->winner ?? '',
                'a_name' => $m->a_name ?? '', 'a_seed' => $m->a_seed, 'a_score' => $m->a_score ?? '',
                'b_name' => $m->b_name ?? '', 'b_seed' => $m->b_seed, 'b_score' => $m->b_score ?? '',
            ];
        }

        $joined = $c->registrations->count();

        return [
            'key' => 'c'.$c->id,
            'id' => $c->id,
            'name' => $c->name,
            'class' => $c->weight_class ?? '',
            'cap' => $c->capacity,                       // null = no cap
            'joined' => $joined,
            'open' => $c->capacity ? max(0, $c->capacity - $joined) : null,
            'status' => $c->status,
            'draw_state' => $c->draw_state,
            'provisional' => $c->draw_state === 'provisional',
            'unpaid_count' => $c->registrations->where('role', 'participant')
                ->filter(fn ($r) => ! $r->paid || $r->weight === null)->count(),
            'note' => $c->note ?? '',
            'rounds' => array_values($rounds),
            'matches_flat' => $flat,
            'podium' => $c->podium ?? [],
            'roster' => $c->registrations->map(fn ($r) => [
                'name' => $r->user?->full_name ?? $r->user?->name ?? 'Athlete',
                'country' => $r->meta ?: '',
            ])->all(),
            'roster_names' => $c->registrations
                ->map(fn ($r) => $r->user?->full_name ?? $r->user?->name ?? 'Athlete')->values()->all(),
            'mine' => $c->registrations->contains('user_id', $viewer->id),
        ];
    }

    /** One match, as the run screen shows it. */
    protected function matchView(ClubEvent $event, EventCategory $c, $m): array
    {
        return [
            'id' => $m->id,
            'no' => $m->match_no,
            'phase' => $m->phase,
            'date' => '',
            'code' => null,
            'court' => $m->court ?? '',
            'time' => $m->scheduled_time ?? '',
            'status' => $m->status,
            'winner' => $m->winner,
            'a' => ['name' => $m->a_name, 'competitor_id' => $m->a_competitor_id, 'country' => $m->a_country, 'seed' => $m->a_seed, 'score' => $m->a_score ?? '–', 'provisional' => (bool) $m->a_provisional],
            'b' => ['name' => $m->b_name, 'competitor_id' => $m->b_competitor_id, 'country' => $m->b_country, 'seed' => $m->b_seed, 'score' => $m->b_score ?? '–', 'provisional' => (bool) $m->b_provisional],
        ];
    }

    /* ---------------- Input normalisers ---------------- */

    /** Run-of-show entries, kept in chronological order. */
    protected function cleanAgenda(array $rows): ?array
    {
        $out = collect($rows)
            ->map(fn ($r) => [
                't' => ! empty($r['t']) ? Carbon::parse($r['t'])->format('Y-m-d H:i') : '',
                'd' => trim((string) ($r['d'] ?? '')),
            ])
            ->filter(fn ($r) => $r['t'] !== '' || $r['d'] !== '')
            ->sortBy('t')->values()->all();

        return $out ?: null;
    }

    protected function cleanList(array $rows): ?array
    {
        $out = collect($rows)->map(fn ($r) => trim((string) $r))->filter()->values()->all();

        return $out ?: null;
    }

    protected function cleanTags(array $rows): ?array
    {
        $out = collect($rows)->map(fn ($t) => ltrim(trim((string) $t), '#'))->filter()->values()->all();

        return $out ?: null;
    }

    /** Milestones. Status is NOT stored — it is derived from the date at display time. */
    protected function cleanPhases(array $rows): ?array
    {
        $out = collect($rows)
            ->map(fn ($p) => [
                'label' => trim((string) ($p['label'] ?? '')),
                'date' => ! empty($p['date']) ? Carbon::parse($p['date'])->toDateString() : null,
                'note' => trim((string) ($p['note'] ?? '')),
                'icon' => 'bi-flag',
            ])
            ->filter(fn ($p) => $p['label'] !== '')->values()->all();

        return $out ?: null;
    }
}
