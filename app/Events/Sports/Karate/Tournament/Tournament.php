<?php

namespace App\Events\Sports\Karate\Tournament;

use App\Events\AbstractEventType;
use App\Events\Sports\Karate\Tournament\CourtDisplay\CourtDisplayDevice;
use App\Events\Sports\Karate\Tournament\CourtDisplay\ScreenChannel;
use App\Events\Support\BracketView;
use App\Events\Support\EnrolmentDecision;
use App\Events\Support\Milestone;
use App\Events\Support\SyncsDivisions;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventCategory;
use App\Models\EventMatch;
use App\Models\User;
use App\Sports\Combat\Engine\DrawEngine;
use App\Sports\Combat\Engine\Results;
use App\Sports\Combat\Engine\Scheduler;
use App\Sports\Combat\SportRegistry;
use Illuminate\Validation\Rule;

/**
 * Karate Tournament / Championship — the reference event-type package.
 *
 * Owns the whole vertical for a bracketed, weight-classed, medal-awarding
 * championship: what it stores, what it asks at creation, who may compete and
 * in which division, its weigh-in → draw → running → medals lifecycle, the
 * bracket engine and winner progression, its per-division financials, and its
 * own screens.
 *
 * The sport-agnostic pieces (draw building, day/mat scheduling, timeline) are
 * shared engines this package CALLS; the weight tables and classification come
 * from the Karate combat-sport plug-in. Nothing outside this directory needs
 * to know Karate exists.
 */
class Tournament extends AbstractEventType
{
    use SyncsDivisions;

    private const SPORT = 'karate';

    /** Event types this package presents as. */
    private const TYPES = ['tournament', 'championship'];

    public function __construct(
        private SportRegistry $sports,
        private DrawEngine $draws,
        private Scheduler $scheduler,
        private Results $resultsEngine,
    ) {}

    public function key(): string
    {
        return 'karate_tournament';
    }

    public function label(): string
    {
        return __('event-karate_tournament::messages.type_label');
    }

    public function owns(ClubEvent $event): bool
    {
        return $event->sport === self::SPORT
            && in_array($event->event_type, self::TYPES, true);
    }

    protected function schemaType(): string
    {
        return 'championship';
    }

    public function formSections(): array
    {
        return ['divisions', 'weigh_in', 'mats', 'schedule', 'requirements', 'prize'];
    }

    /** The sport's weight tables, so the organiser can pick the divisions to run. */
    public function formCatalog(): array
    {
        return ['weight_divisions' => $this->sport()->weightDivisions()];
    }

    /* ---------------- Schema & creation input ---------------- */

    public function validationRules(?ClubEvent $event = null): array
    {
        return parent::validationRules($event) + $this->divisionRules() + [
            'sport' => ['required', Rule::in([self::SPORT])],
            'weigh_in_at' => ['nullable', 'date'],
            'courts' => ['nullable', 'integer', 'min:1', 'max:50'],
            'minutes_per_match' => ['nullable', 'integer', 'min:1', 'max:120'],
            'break_start' => ['nullable', 'date_format:H:i', 'after_or_equal:start_time'],
            'break_end' => ['nullable', 'date_format:H:i', 'after:break_start', 'before_or_equal:end_time'],
        ];
    }

    /**
     * A championship has no event-wide level, prize or capacity — those belong
     * to the individual weight divisions, so this package clears them.
     */
    public function columnsFromInput(array $data, ?ClubEvent $event = null): array
    {
        $columns = parent::columnsFromInput($data, $event) + [
            'sport' => self::SPORT,
            'courts' => isset($data['courts']) && $data['courts'] !== '' ? (int) $data['courts'] : null,
            'level' => null,
            'prize' => null,
            'max_capacity' => null,
            'league' => null,
            'agenda' => null,   // the run of show is derived from the real draw
        ];

        // `minutes_per_match` is NOT NULL with a sensible default — only write it
        // when the organiser actually chose a bout length, so an unspecified one
        // falls back to the default instead of violating the constraint.
        if (isset($data['minutes_per_match']) && $data['minutes_per_match'] !== '') {
            $columns['minutes_per_match'] = (int) $data['minutes_per_match'];
        }

        return $columns;
    }

    public function saveRelatedData(ClubEvent $event, array $data): void
    {
        $this->syncDivisions($event, $data['divisions'] ?? []);

        // Day edits (mats, timings, phase days) re-flow the running order
        // immediately — but only while the draw is still open.
        if (! $event->hasStarted()) {
            $this->scheduler->scheduleAndNumber($event->fresh());
        }
    }

    /* ---------------- Enrolment ---------------- */

    public function enrolmentGate(ClubEvent $event, User $user, ?ClubEventRegistration $existing = null): EnrolmentDecision
    {
        return $this->enrolment()->gate($event, $user, $existing);
    }

    /**
     * Place an entrant the club entered before anyone knew their weight.
     *
     * The official weight from the scale is what classifies them — the same
     * routing self-entry uses, run again with a number nobody had at entry time.
     * An entry that ALREADY has a division is left alone: re-cutting a drawn
     * competitor into another division on a weigh-in is an organiser's decision,
     * not a side effect of the desk.
     */
    public function classifyEntry(ClubEvent $event, ClubEventRegistration $registration): ?EventCategory
    {
        if ($registration->category_id || ! $registration->user || ! $registration->weight) {
            return null;
        }

        $division = $this->enrolment()->resolveDivision($event, $registration->user, (float) $registration->weight);

        if ($division) {
            $registration->update(['category_id' => $division->id]);
        }

        return $division;
    }

    /**
     * Keep the provisional draw in step with the entrant set. Before the event
     * starts the bracket is rebuilt as competitors join or are removed; once it
     * starts the paid-and-weighed-in draw is locked and never re-cut.
     */
    public function onEntrantsChanged(ClubEvent $event, ?EventCategory $category = null): void
    {
        if ($event->hasStarted() || $event->hasEnded()) {
            $this->draws->ensure($event);   // locks the final draw once, then no-ops

            return;
        }

        if (! $category) {
            $this->draws->ensure($event);

            return;
        }

        // A hand-arranged draw is never re-cut: the newcomer waits on the bench
        // for the organiser to place, and anyone who withdrew is lifted out of
        // their slot.
        if ($category->draw_state === 'manual') {
            $this->arrangement()->syncEntrants($category);
            $this->scheduler->scheduleAndNumber($event->fresh());
            $this->broadcast($event, ['action' => 'entrants', 'division' => $category->id]);

            return;
        }

        if ($category->registrations()->where('role', 'participant')->count() >= 2) {
            $this->draws->build($event, $category, paidOnly: false);
        } else {
            $category->matches()->delete();
            $category->update(['draw_state' => null, 'draw_count' => 0]);
        }

        $this->scheduler->scheduleAndNumber($event->fresh());

        // Only the entrant-change path broadcasts. The no-category call above is
        // the bracket page bringing itself up to date on every view — publishing
        // there would spam the whole championship on each page load.
        $this->broadcast($event, ['action' => 'entrants', 'division' => $category->id]);
    }

    /* ---------------- Lifecycle ---------------- */

    public function stages(): array
    {
        return ['draft', 'enrolling', 'weigh_in', 'drawn', 'running', 'completed', 'cancelled'];
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
        if ($event->weigh_in_at && now()->gte($event->weigh_in_at)) {
            return 'weigh_in';
        }
        if ($event->categories()->where('draw_state', 'final')->exists()) {
            return 'drawn';
        }
        if ($event->enrollment_starts_at && now()->startOfDay()->lt($event->enrollment_starts_at)) {
            return 'draft';
        }

        return 'enrolling';
    }

    /** The draw is final from the first bout; medals are final once the event ends. */
    public function canTransitionTo(ClubEvent $event, string $stage): bool
    {
        if (! in_array($stage, $this->stages(), true)) {
            return false;
        }

        return match ($stage) {
            'cancelled' => $this->stage($event) !== 'completed',
            'drawn' => ! $event->hasStarted(),
            default => false,
        };
    }

    /* ---------------- Engine ---------------- */

    /**
     * Record a bout result. The winner is carried into their next bout
     * automatically and the division's medals are awarded when the final lands.
     *
     * @param  int  $unitId  event_matches.id
     */
    public function recordOutcome(ClubEvent $event, int $unitId, array $payload): array
    {
        $match = EventMatch::with('category')->where('event_id', $event->id)->findOrFail($unitId);

        // A finished championship is a historical record.
        abort_if($event->hasEnded(), 422, __('event-karate_tournament::messages.ended_locked'));

        $result = $this->advancement()->record($match, $payload);

        // The queue behind this bout just moved up: tell whoever the result
        // concerned, then call anyone who has come within range on this mat.
        $calls = app(CallNotifier::class);
        if ($match->winner) {
            $calls->pushResult($event, $match->refresh());
        }
        $calls->pushDue($event, $match->court);

        // And the walls — EVERY mat, not just this one. The winner of this bout
        // advances into a next-round slot, and the scheduler is free to put that
        // bout on another mat: that mat's queue just changed too, and scoping the
        // push to $match->court would leave it announcing a bout with a corner
        // that is no longer "to be decided". Rebuilding three or four boards is
        // cheaper than one wrong wall.
        ScreenChannel::notifyCourt($event, null);

        // Everyone following this championship sees the bout land — and the
        // athletes it just advanced — without refreshing.
        $this->broadcast($event, [
            'action' => 'outcome',
            'division' => $match->category_id,
            'match' => $result['match'],
            'advanced' => $result['advanced'],
        ]);

        if ($result['division_complete']) {
            $this->broadcast($event, [
                'action' => 'podium',
                'division' => $match->category_id,
                'podium' => $result['podium'],
            ]);
        }

        return $result;
    }

    public function performAction(ClubEvent $event, string $action, array $payload = []): array
    {
        return match ($action) {
            'generate_draw' => $this->generateDraw($event),
            'arrange_draw' => $this->arrangeDraw($event, $payload),
            'clear_draw' => $this->clearDraw($event, $payload),
            'end_next_bout' => $this->endNextBout($event),
            default => ['success' => false, 'message' => __('events.action_unsupported')],
        };
    }

    public function availableActions(ClubEvent $event): array
    {
        $actions = [];

        if (! $event->hasStarted() && $event->categories()->exists()) {
            $actions[] = [
                'action' => 'generate_draw',
                'label' => __('event-karate_tournament::messages.action_generate_draw'),
                'icon' => 'bi-diagram-3',
            ];

            // Arranging is offered as an action rather than a route of its own,
            // so the "may this run?" check stays in one place: an action the
            // package does not currently offer can never be performed.
            $actions[] = [
                'action' => 'arrange_draw',
                'label' => __('event-karate_tournament::messages.action_arrange_draw'),
                'icon' => 'bi-arrows-move',
            ];

            $actions[] = [
                'action' => 'clear_draw',
                'label' => __('event-karate_tournament::messages.action_clear_draw'),
                'icon' => 'bi-eraser',
            ];
        }

        // A way to make the hall board move without twenty people and a mat.
        //
        // Ending a bout is otherwise only reachable by POSTing to the outcome
        // endpoint by hand — there is no run-day scorer yet — which makes the
        // court display impossible to demonstrate or rehearse. This ends the
        // next queued bout with a plausible score, which is exactly what the
        // real thing will do when it exists.
        //
        // NOT in production. It invents a result and writes it to a real draw:
        // fine on a rehearsal event, never something to leave one tap away from
        // an organiser during a live competition.
        if (! app()->environment('production') && $this->nextBout($event)) {
            $actions[] = [
                'action' => 'end_next_bout',
                'label' => __('event-karate_tournament::messages.action_end_next_bout'),
                'icon' => 'bi-flag-fill',
            ];
        }

        return $actions;
    }

    /**
     * End the next queued bout, so the hall board can be watched moving.
     *
     * Goes through recordOutcome rather than writing the row itself: the point
     * is to exercise the real path — the winner advances, the podium closes when
     * a division finishes, the athletes are called, the screens redraw. A
     * shortcut here would demonstrate nothing.
     *
     * Re-checks the environment. availableActions decides what to OFFER; this
     * decides what may HAPPEN, and a request can arrive without the button.
     */
    private function endNextBout(ClubEvent $event): array
    {
        abort_if(app()->environment('production'), 403);

        $bout = $this->nextBout($event);

        if (! $bout) {
            return ['success' => false, 'message' => __('event-karate_tournament::messages.end_next_bout_none')];
        }

        // Red or blue, decided here rather than always 'a', so a rehearsal draw
        // does not fill one side of the bracket.
        $winner = random_int(0, 1) === 1 ? 'a' : 'b';
        $winning = random_int(8, 20);
        $losing = random_int(0, $winning - 1);

        $this->recordOutcome($event, $bout->id, [
            'winner' => $winner,
            'a_score' => (string) ($winner === 'a' ? $winning : $losing),
            'b_score' => (string) ($winner === 'b' ? $winning : $losing),
            'status' => 'done',
        ]);

        $bout->refresh();

        return [
            'success' => true,
            'message' => __('event-karate_tournament::messages.end_next_bout_done', [
                'winner' => ($winner === 'a' ? $bout->a_name : $bout->b_name) ?: '—',
                'court' => $bout->court ?: '—',
            ]),
        ];
    }

    /**
     * The bout this test action should end, or null when nothing is endable.
     *
     * Prefers a mat somebody is watching. The whole point of the button is to
     * see a hall screen move, and the running order's own next bout is often on
     * a mat with no screen paired to it — press, nothing happens, and the
     * feature looks broken when it is working exactly as scoped.
     */
    private function nextBout(ClubEvent $event): ?EventMatch
    {
        // A bout waiting on a feeder has nobody to declare the winner of.
        $endable = $this->runningOrder()->upcomingBouts($event)
            ->filter(fn (EventMatch $m) => $m->a_competitor_id && $m->b_competitor_id);

        $watched = CourtDisplayDevice::where('event_id', $event->id)
            ->whereNull('revoked_at')
            ->whereNotNull('court')
            ->pluck('court')
            ->all();

        return $endable->first(fn (EventMatch $m) => in_array($m->court, $watched, true))
            ?? $endable->first();
    }

    /**
     * Carry every first-round bye into the next round.
     *
     * A field that is not a power of two is padded with byes, and the draw
     * engine writes those straight to the table as already won — winner set,
     * status done — because nobody fights them. But writing a winner is not the
     * same as ADVANCING one: propagate() is what puts a name into the next
     * round's slot, and it only ever runs from recordOutcome(), which a bye
     * never reaches.
     *
     * So without this a bracket keeps permanent holes. The semi-final fed by a
     * bye never gets its second competitor, so it can never be played, so the
     * division never completes and its podium is never awarded. Any entry list
     * that is not exactly 4, 8 or 16 hits it.
     *
     * Idempotent: propagate() writes the same name into the same slot however
     * many times a draw is re-cut.
     */
    private function advanceByes(EventCategory $category): void
    {
        $byes = $category->matches()
            ->whereNotNull('winner')
            ->where(fn ($q) => $q->whereNull('a_competitor_id')->orWhereNull('b_competitor_id'))
            ->get();

        foreach ($byes as $bye) {
            $this->advancement()->propagate($category, $bye);
        }
    }

    /**
     * Move one competitor within a division's first round, or in and out of it.
     * Returns the division's fresh bracket so the screen patches in place.
     */
    private function arrangeDraw(ClubEvent $event, array $payload): array
    {
        $result = $this->arrangement()->move($event, $payload);

        if (! $result['ok']) {
            return ['success' => false, 'message' => $result['message']];
        }

        $this->scheduler->scheduleAndNumber($event->fresh());

        return $this->arrangedResponse($event, $result['category'], __('event-karate_tournament::messages.draw_arranged'));
    }

    /** Empty a division's draw onto the bench so it can be built by hand. */
    private function clearDraw(ClubEvent $event, array $payload): array
    {
        $category = $event->categories()->find($payload['category_id'] ?? null);

        if (! $category) {
            return ['success' => false, 'message' => __('events.division_not_found')];
        }

        $result = $this->arrangement()->clear($event, $category);

        if (! $result['ok']) {
            return ['success' => false, 'message' => $result['message']];
        }

        $this->scheduler->scheduleAndNumber($event->fresh());

        return $this->arrangedResponse($event, $result['category'], __('event-karate_tournament::messages.draw_cleared'));
    }

    /**
     * One arranged division, pushed to everyone watching. The bracket renders
     * differently per viewer (their own bouts are marked, only organisers see
     * the bench), so the broadcast is a refresh signal — the division's data
     * comes back only to the operator who moved it, for their in-place patch.
     */
    private function arrangedResponse(ClubEvent $event, EventCategory $category, string $message): array
    {
        $this->broadcast($event, ['action' => 'draw', 'division' => $category->id]);

        return [
            'success' => true,
            'message' => $message,
            'data' => ['division' => (new BracketView)->division(
                $category->load(['matches', 'registrations.user:id,full_name,name,profile_picture,profile_picture_is_public,updated_at'])
            )],
        ];
    }


    /**
     * (Re)cut every division's provisional bracket and re-flow the running
     * order. Once the first bout is due the draw is final — a championship can
     * never be re-drawn out from under the competitors who turned up for it.
     */
    private function generateDraw(ClubEvent $event): array
    {
        if ($event->hasStarted()) {
            return ['success' => false, 'message' => __('event-karate_tournament::messages.draw_final')];
        }

        foreach ($event->categories()->get() as $category) {
            if ($category->registrations()->where('role', 'participant')->count() >= 1) {
                $this->draws->build($event, $category, paidOnly: false);
                $this->advanceByes($category);
            }
        }

        $plan = $this->scheduler->scheduleAndNumber($event);

        // A re-cut draw changes everyone's opponent, mat and bout number, and it
        // renders differently for each viewer (their own bouts are highlighted)
        // — so send a refresh signal rather than a per-user payload.
        $this->broadcast($event, ['action' => 'draw']);

        $mats = collect($plan)
            ->map(fn ($p, $day) => __('event-karate_tournament::messages.day_mats', ['day' => $day, 'count' => $p['courts']]))
            ->implode(' · ');

        return [
            'success' => true,
            'message' => __('event-karate_tournament::messages.draw_generated').($mats ? ' · '.$mats : ''),
            'data' => ['plan' => $plan],
        ];
    }

    /**
     * A championship adds two milestones the generic schedule has no concept
     * of: the official weigh-in, and the draw being published (the moment every
     * competitor learns their opponent, mat and bout number).
     */
    public function notificationSchedule(ClubEvent $event): array
    {
        $milestones = parent::notificationSchedule($event);

        if ($event->weigh_in_at) {
            $milestones[] = new Milestone(
                key: 'weigh_in',
                title: __('event-karate_tournament::messages.notify_weigh_in_title', ['title' => $event->title]),
                body: __('event-karate_tournament::messages.notify_weigh_in_body', [
                    'time' => $event->weigh_in_at->format('g:i A'),
                    'place' => $event->location ?: ($event->tenant?->club_name ?? ''),
                ]),
                at: $event->weigh_in_at->copy()->subDay()->setTime(
                    (int) config('event_notifications.event_day_reminder_hour', 7), 0,
                ),
                audience: Milestone::AUDIENCE_PARTICIPANTS,
                icon: 'bi-clipboard-data',
            );
        }

        if ($event->categories()->where('draw_state', 'final')->exists()) {
            $milestones[] = new Milestone(
                key: 'draw_published',
                title: __('event-karate_tournament::messages.notify_draw_title', ['title' => $event->title]),
                body: __('event-karate_tournament::messages.notify_draw_body'),
                at: null,   // the moment the final draw is locked
                audience: Milestone::AUDIENCE_PARTICIPANTS,
                icon: 'bi-diagram-3',
            );
        }

        return $milestones;
    }

    /* ---------------- Outputs ---------------- */

    /** Medals per weight class, derived from the brackets — never hand-entered. */
    public function results(ClubEvent $event): array
    {
        return $this->resultsEngine->podium($event);
    }

    /** Registration closes → weigh-in & draw → day-by-day running order. */
    public function timeline(ClubEvent $event): array
    {
        return $event->categories()->exists()
            ? $this->resultsEngine->timeline($event)
            : ($event->phases ?: []);
    }

    public function rosterRows(ClubEvent $event): array
    {
        return (new Roster)->rows($event);
    }

    /**
     * The venue board — addressed by PLACE, not person. Carries only what a
     * hall screen shows: mat, bout number, round, and the two names.
     */
    public function board(ClubEvent $event, ?string $court = null): array
    {
        $order = $this->runningOrder();

        return $court ? [$order->matBoard($event, $court)] : $order->venueBoard($event);
    }

    /** The athlete's countdown: mat, bout number, bouts ahead, rough wait. */
    public function nextUp(ClubEvent $event, User $athlete): ?array
    {
        $entry = ClubEventRegistration::where('event_id', $event->id)
            ->where('user_id', $athlete->id)
            ->where('role', 'participant')
            ->first();

        return $entry ? $this->runningOrder()->nextFor($event, $entry) : null;
    }

    /**
     * Every competitor's countdown, soonest first — the coach's single screen.
     *
     * @param  array<int, int>  $userIds
     */
    public function squadNextUp(ClubEvent $event, array $userIds): array
    {
        $order = $this->runningOrder();

        return ClubEventRegistration::where('event_id', $event->id)
            ->where('role', 'participant')
            ->whereIn('user_id', $userIds)
            ->with('user:id,full_name,name,profile_picture,updated_at', 'category:id,name')
            ->get()
            ->map(fn ($entry) => [
                'user_id' => $entry->user_id,
                'name' => $entry->user?->full_name ?? $entry->user?->name ?? 'Athlete',
                'division' => $entry->category?->name,
                'next' => $order->nextFor($event, $entry),
            ])
            // Still competing first, soonest bout at the top; finished last.
            ->sortBy(fn ($row) => $row['next']['bouts_ahead'] ?? PHP_INT_MAX)
            ->values()->all();
    }

    /**
     * Medals come from the brackets. Letting an organiser also type a podium in
     * by hand would allow a published result that contradicts the bouts the
     * competitors actually fought.
     */
    public function allowsManualResults(): bool
    {
        return false;
    }

    /* ---------------- Financials ---------------- */

    /** Championship P&L, plus entries-per-division so the organiser sees where the fees came from. */
    public function finance(ClubEvent $event): array
    {
        $finance = parent::finance($event);
        $fee = $finance['participant_fee'];

        $finance['breakdown'] = $event->categories()->withCount([
            'registrations as entries_count' => fn ($q) => $q->where('role', 'participant'),
            'registrations as paid_count' => fn ($q) => $q->where('role', 'participant')->where('paid', true),
        ])->orderBy('sort_order')->get()
            ->map(fn (EventCategory $c) => [
                'division' => $c->name,
                'entries' => (int) $c->entries_count,
                'paid' => (int) $c->paid_count,
                'revenue' => (int) $c->paid_count * $fee,
            ])->values()->all();

        return $finance;
    }

    /* ---------------- Display ---------------- */

    /**
     * Screens this package ships. Still empty: the championship currently
     * renders through the shared event screens, driven entirely by the data
     * this package returns from viewData()/rosterRows()/results(). Phase 2 of
     * the migration moves those screens in here as
     * resources/views/events/types/karate_tournament/{mobile,desktop}/ and
     * they take over automatically — the caller resolves a declared view only
     * when it exists, so a package may ship one device at a time.
     */
    public function views(): array
    {
        return [];
    }

    public function viewData(ClubEvent $event, User $viewer): array
    {
        $registration = ClubEventRegistration::where('event_id', $event->id)
            ->where('user_id', $viewer->id)->first();

        $gate = $this->enrolmentGate($event, $viewer, $registration);

        return parent::viewData($event, $viewer) + [
            'division_label' => __('sport-karate::messages.division_label'),
            'weigh_in_at' => $event->weigh_in_at,
            'my_weight' => $registration?->weight ?: $this->enrolment()->declaredWeight($viewer),
            'my_division' => $registration?->category?->name,
            'can_compete' => $gate->allowed,
            'gate_code' => $gate->code,
            'gate_reason' => $gate->message,
            'offer_spectator' => $gate->offerSpectator,
        ];
    }

    /**
     * A championship runs several mats at once, each with its own wall screen.
     *
     * The mats come from the draw rather than from a setting, so an organiser
     * pairing a Pi picks a board that actually has bouts on it — the mistake
     * this prevents (a screen pointed at "Mat 3" when the draw only made two)
     * only shows itself on competition morning, in front of a hall.
     */
    public function hallScreens(ClubEvent $event): ?array
    {
        return [
            'mats' => EventMatch::where('event_id', $event->id)
                ->whereNotNull('court')->distinct()->orderBy('court')->pluck('court')->all(),
            'screens' => CourtDisplayDevice::where('event_id', $event->id)
                ->whereNull('revoked_at')
                ->orderBy('court')->orderBy('id')
                ->get()
                ->map(fn (CourtDisplayDevice $d) => $d->present())
                ->all(),
        ];
    }

    /**
     * A championship schedules its bouts across days and mats, so each bout
     * also carries the day it falls on and its mat/bout code (Mat 1, bout 4 →
     * "1-04" — unique per day, and what the callers announce.)
     */
    protected function matchView(ClubEvent $event, EventCategory $c, $m): array
    {
        // The scheduler resolves this once and stores it on the bout; fall back
        // to re-deriving it for a draw that predates that column.
        $day = $m->day ?: $this->scheduler->phaseDay($c, $m->phase ?: 'preliminary');
        $courtNo = ($m->court && preg_match('/(\d+)/', $m->court, $cm)) ? (int) $cm[1] : null;

        return array_replace(parent::matchView($event, $c, $m), [
            'date' => $event->date ? $event->date->copy()->addDays(max(0, $day - 1))->format('D, M j') : '',
            'code' => ($courtNo && $m->match_no)
                ? $courtNo.'-'.str_pad((string) $m->match_no, 2, '0', STR_PAD_LEFT)
                : null,
        ]);
    }

    /* ---------------- Collaborators ---------------- */

    private function runningOrder(): RunningOrder
    {
        return app(RunningOrder::class);
    }

    private function enrolment(): Enrolment
    {
        return new Enrolment($this->sport());
    }

    private function advancement(): Advancement
    {
        return new Advancement($this->sport());
    }

    private function arrangement(): Arrangement
    {
        return new Arrangement($this->advancement());
    }

    private function sport(): \App\Sports\Combat\CombatSport
    {
        return $this->sports->get(self::SPORT)
            ?? throw new \RuntimeException('Karate combat-sport plug-in is not registered in config/combat.php.');
    }
}
