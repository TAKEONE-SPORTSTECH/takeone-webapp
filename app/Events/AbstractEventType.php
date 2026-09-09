<?php

namespace App\Events;

use App\Support\Cldr;
use App\Events\Contracts\EventType;
use App\Events\Support\BracketView;
use App\Events\Support\EnrolmentDecision;
use App\Events\Support\EventFee;
use App\Events\Support\Milestone;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventCategory;
use App\Models\EventExpense;
use App\Members\Models\User;
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

    /**
     * The list columns every type keeps on the event row.
     *
     * ⚠️ ABSENT IS NOT THE SAME AS BLANK (CLAUDE.md, "Who Fills The Form
     * Decides"). A key the request never sent is left OUT of the returned
     * columns, so an update leaves it alone; a key sent EMPTY is a deliberate
     * clear and is written as one. `$data['x'] ?? []` treated the two the
     * same, which meant any caller posting a partial payload — an older form,
     * an integration, a screen that renders only some sections — silently
     * erased the requirements, tags and run-of-show of an event it never
     * meant to touch (found while chasing a lost end date, 2026-09-04).
     *
     * On CREATE nothing is lost either way: a column nobody sent is null.
     */
    public function columnsFromInput(array $data, ?ClubEvent $event = null): array
    {
        $columns = [];

        if (array_key_exists('agenda', $data)) {
            $columns['agenda'] = $this->cleanAgenda($data['agenda'] ?? []);
        }

        if (array_key_exists('requirements', $data)) {
            $columns['requirements'] = $this->cleanList($data['requirements'] ?? []);
        }

        if (array_key_exists('tags', $data)) {
            $columns['tags'] = $this->cleanTags($data['tags'] ?? []);
        }

        if (array_key_exists('phases', $data)) {
            $columns['phases'] = $this->cleanPhases($data['phases'] ?? []);
        }

        return $columns;
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

    /** A type with no divisions has nowhere to place anyone. */
    public function classifyEntry(ClubEvent $event, ClubEventRegistration $registration): ?EventCategory
    {
        return null;
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
                    'date' => $event->date ? Cldr::shortDate($event->date) : '',
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
                    'date' => Cldr::shortDate($event->enrollment_ends_at),
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
            ->with(['user:id,full_name,name,gender', 'category:id,name,weight_class', 'representingTenant:id,country'])
            ->latest('registered_at')->get()
            ->map(fn ($r) => [
                'id' => $r->user?->id,
                'name' => $r->user?->full_name ?? $r->user?->name ?? 'Member',
                'gender' => $r->user?->gender,
                'category' => $r->category?->name,
                'weight_class' => $r->category?->weight_class,
                'meta' => $r->meta ?: ($r->category?->name ?? ($r->paid ? 'Registered' : 'Pending payment')),
                // The ENTRY's own id and photo, so the roster can show a face
                // for someone with no account and let an organiser add one. Kept
                // separate from the user's picture: this belongs to the entry.
                'registration' => $r->id,
                'registration_photo' => $r->photo,
                // The three things an organiser checks off before a competitor
                // can be drawn. The flag is the country of the CLUB they compete
                // for — see ClubEventRegistration::countryCode().
                'country' => $r->countryCode(),
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
        // The stated BASE price, not a number scraped out of the display line.
        // Still reported as the headline figure an organiser recognises, but it
        // is no longer what revenue is derived from — see below.
        $pFee = EventFee::amount($event, 'participant') ?? 0.0;
        $sFee = $event->spectator_enabled ? (EventFee::amount($event, 'spectator') ?? 0.0) : 0.0;

        $paidP = $event->registrations()->where('role', 'participant')->where('paid', true)->count();
        $paidS = $event->registrations()->where('role', 'spectator')->where('paid', true)->count();

        /*
         * Revenue is SUMMED from what each entry was actually charged, not
         * multiplied out from today's price.
         *
         * `paid_count × current_fee` was wrong before multi-pricing existed —
         * an organiser correcting a price silently restated every entry ever
         * taken, and the event's profit moved underneath them. With options and
         * a late penalty it is not merely imprecise, it is inexpressible: two
         * entrants in the same event legitimately pay different amounts.
         *
         * `event_registration_fee_lines` is that record. Entries taken BEFORE
         * lines existed have none, so they are still counted at the event's base
         * fee — reporting them as free would be a worse answer than the one the
         * platform gave at the time. Hence two terms per role rather than one.
         */
        [$pRev, $pEst] = self::chargedRevenue($event, 'participant');
        [$sRev, $sEst] = $event->spectator_enabled
            ? self::chargedRevenue($event, 'spectator')
            : [0.0, 0];

        $money = self::moneySources($event);

        $expenses = $event->expenses()->latest('id')->get(['id', 'label', 'amount'])
            ->map(fn (EventExpense $x) => ['id' => $x->id, 'label' => $x->label, 'amount' => (float) $x->amount])->all();
        $expTotal = array_sum(array_column($expenses, 'amount'));

        return [
            'currency' => EventFee::currency($event),
            'participant_fee' => $pFee,
            'paid_participants' => $paidP,
            'participant_revenue' => $pRev,
            'spectator_enabled' => (bool) $event->spectator_enabled,
            'spectator_fee' => $sFee,
            'paid_spectators' => $paidS,
            'spectator_revenue' => $sRev,
            'revenue' => $pRev + $sRev,
            // How much of that revenue is an ESTIMATE — paid entries with no
            // recorded amount, valued at the list price. A total that includes
            // guesses has to be able to say how many.
            'estimated_entries' => $pEst + $sEst,
            'list_price' => EventFee::listPrice($event, 'participant'),

            // WHERE the money came from, and what it would be if everybody
            // paid. See moneySources().
            'sources' => $money['sources'],
            'expected_revenue' => $money['expected'],
            'expected_outstanding' => round($money['expected'] - ($pRev + $sRev), 3),
            'unpaid_entries' => $money['unpaid'],
            'expenses' => $expenses,
            'expenses_total' => $expTotal,
            'profit' => ($pRev + $sRev) - $expTotal,
            'breakdown' => [],
        ];
    }

    /**
     * What a role actually brought in, and how much of it is an estimate:
     * frozen fee lines where they exist, the event's own list price where they
     * do not.
     *
     * Two queries whatever the size of the entry list — never a loop over
     * registrations.
     *
     * @return array{0: float, 1: int}  [revenue, entries valued by estimate]
     */
    private static function chargedRevenue(ClubEvent $event, string $role): array
    {
        $paidIds = $event->registrations()
            ->where('role', $role)
            ->where('paid', true)
            ->pluck('club_event_registrations.id');

        if ($paidIds->isEmpty()) {
            return [0.0, 0];
        }

        /*
         * ⚠️ The fallback for an entry with no fee line is the LIST PRICE, not
         * the base fee.
         *
         * It used to be the base column, which is exactly 0 on any event that
         * prices itself through options — so an organiser who had taken
         * eighteen payments was shown the revenue of one (reported 2026-09-06).
         * `EventFee::listPrice` answers with the base where there is one and
         * the cheapest option where there is not.
         *
         * The entries that need it are the ones taken at the desk: an official
         * ticks "paid" on the entry list and nothing writes a line, because the
         * amount was never quoted. Reporting those as free was the worse of two
         * imperfect answers.
         */
        $charged = EventFee::chargedForMany($event, $paidIds, $role);

        return [
            round(array_sum($charged['amounts']), 3),
            count($charged['estimated']),
        ];
    }

    /**
     * Where the money came from, and what it would be if everyone paid.
     *
     * TWO questions an organiser asks about the same table, so they are
     * answered from one pass:
     *
     *   · **Sources.** "Gi entries brought BHD 30, Gi + No-Gi brought BHD 45."
     *     A total tells nobody which of the things they are selling is selling.
     *     The rows come straight from the frozen fee lines, grouped by the
     *     option they name, so a price change afterwards never restates them —
     *     and a late-entry penalty appears as its own row, because that is a
     *     different kind of income from an entry fee.
     *
     *   · **Forecast.** What the event takes if every entrant on the list pays.
     *     Same arithmetic, over ALL entries instead of the paid ones, so the
     *     difference between the two is exactly what is outstanding.
     *
     * Entries with NO fee line are their own row, marked as an estimate: they
     * were ticked paid at a desk where no amount was ever quoted, so all this
     * can honestly say is how many and what the list price is.
     *
     * Two queries, whatever the size of the entry list.
     *
     * @return array{sources: array<int, array<string, mixed>>, expected: float, unpaid: int}
     */
    private static function moneySources(ClubEvent $event): array
    {
        $entries = $event->registrations()
            ->whereIn('role', ['participant', 'spectator'])
            ->get(['club_event_registrations.id', 'role', 'paid']);

        if ($entries->isEmpty()) {
            return ['sources' => [], 'expected' => 0.0, 'unpaid' => 0];
        }

        $paidIds = $entries->where('paid', true)->pluck('id')->all();

        $lines = \App\Models\EventRegistrationFeeLine::whereIn('registration_id', $entries->pluck('id'))
            ->get(['registration_id', 'fee_option_id', 'kind', 'label', 'amount']);

        $isPaid = array_fill_keys($paidIds, true);
        $lined = $lines->pluck('registration_id')->unique()->all();

        $sources = [];

        foreach ($lines->groupBy(fn ($l) => $l->kind.':'.($l->fee_option_id ?? 0).':'.$l->label) as $group) {
            $first = $group->first();

            // A zero-amount line is a recorded free entry, not a source of
            // money — it is what makes "recorded free" different from "no
            // record at all", and it does not belong in this table.
            if ((float) $group->sum('amount') <= 0) {
                continue;
            }

            $paidRows = $group->filter(fn ($l) => isset($isPaid[$l->registration_id]));

            $sources[] = [
                'label' => (string) $first->label,
                'kind' => (string) $first->kind,
                'count' => $paidRows->count(),
                'revenue' => round((float) $paidRows->sum('amount'), 3),
                'expected' => round((float) $group->sum('amount'), 3),
                'estimated' => false,
            ];
        }

        // Everything the record does not cover, valued at the list price.
        $unrecorded = $entries->reject(fn ($r) => in_array($r->id, $lined, true));

        foreach (['participant', 'spectator'] as $role) {
            $rows = $unrecorded->where('role', $role);
            $price = EventFee::listPrice($event, $role);

            if ($rows->isEmpty() || $price <= 0) {
                continue;
            }

            $sources[] = [
                'label' => __('personal.event_show_money_unrecorded'),
                'kind' => 'unrecorded',
                'count' => $rows->where('paid', true)->count(),
                'revenue' => round($rows->where('paid', true)->count() * $price, 3),
                'expected' => round($rows->count() * $price, 3),
                'estimated' => true,
            ];
        }

        // Biggest first: the question is which of these is carrying the event.
        usort($sources, fn ($a, $b) => $b['expected'] <=> $a['expected']);

        return [
            'sources' => $sources,
            'expected' => round(array_sum(array_column($sources, 'expected')), 3),
            'unpaid' => $entries->where('paid', false)->count(),
        ];
    }

    /**
     * @deprecated Prices are columns now — use EventFee::amount($event, $role).
     *             Kept so a package still calling this keeps working; it can
     *             only ever guess, which is why nothing here calls it.
     */
    protected function feeAmount(?string $fee): float
    {
        return EventFee::parse($fee) ?? 0.0;
    }

    /* ---------------- Display ---------------- */

    public function views(): array
    {
        return [];
    }

    /**
     * The platform's frame stays, unless a type asks for it to go.
     *
     * FALSE is the safe default and the shipped behaviour: an event renders
     * inside the member shell, with the top bar, the drawer and the bottom
     * tabs it has always had. A package that overrides this to true is saying
     * its competitions are their own app, organiser-branded (see the contract).
     */
    public function brandedSurface(): bool
    {
        return false;
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
     * The hall screens this event drives, or null when the type has none.
     *
     * A belt test has one room and no wall board; a championship runs several
     * mats, each with a screen showing that mat's queue. So the console
     * asks the type rather than assuming — a type that returns null simply has
     * no screens section, with no branching in the shared controller or view.
     *
     * Shape: ['mats' => string[], 'screens' => array<int, array>] — the mats
     * this event actually runs on (so an organiser pairs to a real board), and
     * the screens already paired to it.
     *
     * @return array{mats: array<int, string>, screens: array<int, array<string, mixed>>}|null
     */
    public function hallScreens(ClubEvent $event): ?array
    {
        return null;
    }

    /**
     * A panel this package contributes to the SPORT'S SCORING TABLE, or null.
     *
     * The scoring console belongs to the sport, not to the event type, and it
     * is deliberately a fixed broadcast document rather than a page that grows
     * features. But some types need one thing on it that only they can supply,
     * and the alternative is worse: Open Mat's operator had to leave the
     * scoreboard, go back to a console to change the two names, and come back —
     * for every pair, all evening.
     *
     * So a package may hand the table one modal of its own. Returning null (the
     * default, and what every championship returns) renders nothing at all and
     * leaves that console byte-identical to what it is today.
     *
     *   ['view' => 'event-<key>::mat-panel', 'label' => 'Next pair', 'data' => [...]]
     *
     * The view is included INSIDE that console's document, so it must be written
     * in the console's own idiom — vanilla JS, its dark broadcast styling, no
     * design-system classes and no Alpine, none of which exist on that page.
     *
     * @return array{view: string, label: string, data: array<string, mixed>}|null
     */
    public function matPanel(ClubEvent $event, string $court, User $viewer): ?array
    {
        return null;
    }

    /** No wall screens by default, so nothing to reload. */
    public function reloadHallScreens(ClubEvent $event): void
    {
        //
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
    public function bracketView(ClubEvent $event, ?User $viewer = null): array
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
                'country' => $r->countryCode() ?: '',
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
