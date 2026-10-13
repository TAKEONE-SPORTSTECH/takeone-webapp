<?php

namespace App\Events\Sports\BrazilianJiuJitsu\Tournament;

use App\Events\AbstractEventType;
use App\Scoreboard\Sports\BrazilianJiuJitsu\HallScreen\ScreenDevice;
use App\Scoreboard\Sports\BrazilianJiuJitsu\HallScreen\ScreenChannel;
use App\Events\Support\BracketView;
use App\Events\Support\EnrolmentDecision;
use App\Events\Support\Milestone;
use App\Events\Support\SyncsDivisions;
use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Members\Models\User;
use App\Sports\Combat\Engine\DrawEngine;
use App\Sports\Combat\Engine\Results;
use App\Sports\Combat\Engine\Scheduler;
use App\Sports\Combat\SportRegistry;
use Illuminate\Validation\Rule;

/**
 * Brazilian Jiu-Jitsu Tournament / Championship — a self-contained package.
 *
 * Owns the whole vertical for a bracketed, weight-classed, medal-awarding
 * jiu-jitsu championship: what it stores, what it asks at creation, who may
 * compete and in which division, its weigh-in → draw → running → medals
 * lifecycle, the bracket engine and winner progression, its per-division
 * financials, its scoring table, its wall boards and its livestream overlay.
 *
 * The sport-agnostic pieces (draw building, day/mat scheduling, results) are
 * shared engines this package CALLS; the weight tables and classification come
 * from the BrazilianJiuJitsu combat-sport plug-in one directory up. Nothing
 * outside app/Events/Sports/BrazilianJiuJitsu/ needs to know this sport exists.
 *
 * ── What is different here, and why ─────────────────────────────────────────
 * The scoring table is not a counter. A jiu-jitsu score is three separate
 * things (points, advantages, penalties) that must never be summed, and it is
 * contested at the table often enough that a mutable total is not good enough:
 * every scoring act is an append-only ledger row and the score is DERIVED by
 * replaying them. See Scoreboard/Ledger.
 */
class Tournament extends AbstractEventType implements \App\Events\Contracts\AwardsDivisions
{
    use SyncsDivisions;

    private const SPORT = 'bjj';

    /**
     * Event types this package presents as.
     *
     * `league` is here because a jiu-jitsu competition run as POOLS is still a
     * jiu-jitsu competition — same entrants, same weigh-in, same mats, same
     * medals — and only the shape of the play changes, which is a property of
     * each weight class (`event_categories.format`), not of the event. An
     * organiser who types "league" gets this package with its classes cut as
     * round robins, never the team-and-fixture-list league.
     */
    private const TYPES = ['tournament', 'championship', 'league'];

    public function __construct(
        private SportRegistry $sports,
        private DrawEngine $draws,
        private Scheduler $scheduler,
        private Results $resultsEngine,
    ) {}

    public function key(): string
    {
        return 'bjj_tournament';
    }

    public function label(): string
    {
        return __('event-bjj_tournament::messages.type_label');
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

    /**
     * The sport's weight tables, so the organiser can pick the divisions to run
     * — plus the BELT ladder, which the sibling sports have no equivalent of.
     *
     * A jiu-jitsu division is belt × age × weight, and the belt is the part an
     * organiser picks first. It is offered as reference data rather than as a
     * column, because a division's name already carries it and the draw engine
     * has no opinion about grading.
     */
    public function formCatalog(): array
    {
        return [
            'weight_divisions' => $this->sport()->weightDivisions('gi'),
            /* The no-gi table, which is its own table and not the gi one with
               a couple of kilos taken off — an athlete weighs in without a gi,
               so the limits and the ladder both differ. */
            'weight_divisions_nogi' => $this->sport()->weightDivisions('nogi'),
            // What each band MEANS in years, so a division built from the table
            // carries the same ages registration will place people by.
            'age_bands' => \App\Events\Sports\BrazilianJiuJitsu\BrazilianJiuJitsu::ageBands(),
            'belts' => array_map(
                fn (string $belt) => ['value' => $belt, 'label' => __('sport-brazilianjiujitsu::messages.belt_'.$belt)],
                \App\Events\Sports\BrazilianJiuJitsu\BrazilianJiuJitsu::BELTS,
            ),
        ];
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
        return $this->weighInPlacement($event, $registration)['division'];
    }

    /**
     * The same placement, with the WORD for what happened — so the weigh-in
     * desk can say "placed in" or "moved to" rather than reporting a move as a
     * plain recording.
     *
     * The rule itself is shared (Enrolment::placeOnWeighIn): every bracketed
     * sport places an athlete by the scale the same way, and only the medal
     * conventions and the scoring differ between them.
     *
     * @return array{division: ?EventCategory, action: ?string, from: ?EventCategory}
     */
    public function weighInPlacement(ClubEvent $event, ClubEventRegistration $registration): array
    {
        return $this->enrolment()->placeOnWeighIn($event, $registration);
    }

    /**
     * Keep the provisional draw in step with the entrant set. Before the event
     * starts the bracket is rebuilt as competitors join or are removed; once it
     * starts the paid-and-weighed-in draw is locked and never re-cut.
     */
    public function onEntrantsChanged(ClubEvent $event, ?EventCategory $category = null): void
    {
        // A heading holds nobody and is never drawn (EventCategory::isHeading).
        if ($category?->isHeading()) {
            return;
        }

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
        if ($event->weigh_in_at && now()->gte($event->inZone($event->weigh_in_at))) {
            return 'weigh_in';
        }
        if ($event->categories()->where('draw_state', 'final')->exists()) {
            return 'drawn';
        }
        // Same day boundary as the entry gate itself (EntryWindow): an event
        // must not read as 'draft' on a console while its entry door is open,
        // or the other way round.
        if (\App\Events\Support\EntryWindow::hasNotOpened($event)) {
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
        /*
         * ⚠️ A FINISHED CHAMPIONSHIP IS STILL CORRECTABLE (2026-09-20, at the
         * owner's instruction).
         *
         * This used to refuse outright — "a finished championship is a
         * historical record" — and that reading is wrong in the one case that
         * matters: the record is wrong. A winner entered against the wrong
         * corner on the day is found when somebody reads the sheet afterwards,
         * and refusing the correction does not protect the history, it
         * preserves the mistake in it.
         *
         * Nothing else changed. This door is me.events.outcome, which asks for
         * canManage on every call; the change is appended to the officiating
         * log exactly as any other; and Advancement re-cuts whatever followed
         * the bout, which is what correcting a result means. The board offers
         * it only behind its padlock.
         */

        $result = $this->advancement()->record($match, $payload);

        // The shared engine refuses an impossible instruction (a winner with
        // nobody in that corner) rather than reading it as "clear this bout".
        // Nothing is notified, nothing is called: nothing happened.
        if (($result['ok'] ?? true) === false) {
            return $result;
        }

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
            'grow_draw' => $this->growDraw($event, $payload),
            'admit_late_entry' => $this->admitLateEntry($event, $payload),
            'clear_draw' => $this->clearDraw($event, $payload),
            'end_next_bout' => $this->endNextBout($event),
            default => ['success' => false, 'message' => __('events::events.action_unsupported')],
        };
    }

    public function availableActions(ClubEvent $event): array
    {
        $actions = [];

        $hasDivisions = $event->categories()->exists();

        if (! $event->hasStarted() && $hasDivisions) {
            $actions[] = [
                'action' => 'generate_draw',
                'label' => __('event-bjj_tournament::messages.action_generate_draw'),
                'icon' => 'bi-diagram-3',
            ];

            $actions[] = [
                'action' => 'clear_draw',
                'label' => __('event-bjj_tournament::messages.action_clear_draw'),
                'icon' => 'bi-eraser',
            ];
        }

        /*
         * Arranging is offered as an action rather than a route of its own, so
         * the "may this run?" check stays in one place: an action the package
         * does not currently offer can never be performed.
         *
         * ⚠️ It OUTLIVES THE START, unlike the two above (2026-09-18, at the
         * user's instruction). Generating and clearing re-cut a division from
         * scratch and must never run once people are fighting on it. Arranging
         * after the start is a different act: Arrangement::move() narrows it to
         * one shape — somebody off the bench into an EMPTY first-round corner —
         * which adds a missing athlete and cannot disturb a drawn one.
         *
         * Withdrawing it entirely is what left nine enrolled athletes with no
         * way onto a board on the morning of 2026-09-18: the organiser could
         * see them on the bench, and the only control that could place them had
         * been taken off the screen. The narrow move is safe; having no move at
         * all was not.
         */
        /*
         * ⚠️ AND IT OUTLIVES THE END, not only the start (2026-09-20, at the
         * owner's instruction: "the unlock can work even after the competition
         * is over as I need it").
         *
         * A championship does not stop needing corrections when the last bout
         * finishes. A result filed against the wrong corner, an athlete left
         * off a board, a name spelled wrong on a medal list — all of it is
         * found in the days AFTER, when somebody reads the sheet properly, and
         * a board that seals itself at the final whistle is a board that cannot
         * be put right.
         *
         * Nothing underneath was relaxed to allow this. Arrangement::move()
         * applies exactly the same rules it always did, including the one thing
         * it still refuses outright — the bout currently loaded on a mat — and
         * every call re-authorises. This only decides whether the verb is
         * OFFERED, and it is now offered for as long as the organiser holds the
         * event.
         */
        if ($hasDivisions) {
            $actions[] = [
                'action' => 'arrange_draw',
                'label' => __('event-bjj_tournament::messages.action_arrange_draw'),
                'icon' => 'bi-arrows-move',
            ];

            /*
             * Growing a division to the next bracket size, so a late entrant
             * has somewhere to stand.
             *
             * Offered alongside Arrange and for the same reason it outlives the
             * start: a bracket whose corners are all occupied has no empty slot
             * to drag anybody into, and a competitor who turns up entered and
             * eligible cannot be told the shape of a power of two is the reason
             * they are not fighting. It refuses a division that has been
             * fought — see growDraw() — so it can add room without ever
             * spending a result.
             */
            $actions[] = [
                'action' => 'grow_draw',
                'label' => __('event-bjj_tournament::messages.action_grow_draw'),
                'icon' => 'bi-arrows-angle-expand',
            ];

            /*
             * Admitting somebody to a division that is ALREADY BEING FOUGHT.
             *
             * The other three all re-cut or rearrange a board that nothing has
             * happened on yet. This one is for the desk at 10am: an entered,
             * eligible competitor turns up for a division three bouts deep, and
             * the answer has to be "yes" without spending a result. It refuses
             * nothing on the grounds of the event having started — see
             * App\Events\Support\Tournament\LateEntry, which protects the
             * results themselves rather than the clock.
             */
            $actions[] = [
                'action' => 'admit_late_entry',
                'label' => __('event-bjj_tournament::messages.action_admit_late_entry'),
                'icon' => 'bi-person-plus',
            ];
        }

        /*
         * "End the next bout" is NOT OFFERED on the console (asked for on
         * 2026-09-04).
         *
         * It exists to make the hall board move without twenty people and a
         * mat, by inventing a plausible score for the next queued bout — a
         * rehearsal tool. On the console it read as an ordinary run-day verb
         * one tap from an organiser, and what it actually does is write a
         * fabricated result into a real draw.
         *
         * The HANDLER stays (see endNextBout below, and the `end_next_bout`
         * case in performAction): it is still the way to rehearse a hall
         * screen, it re-checks the environment itself, and deleting it would
         * take the only way to exercise the run-day path with it. What is gone
         * is the BUTTON.
         */

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
            return ['success' => false, 'message' => __('event-bjj_tournament::messages.end_next_bout_none')];
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
            'message' => __('event-bjj_tournament::messages.end_next_bout_done', [
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

        $watched = ScreenDevice::where('event_id', $event->id)
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

        return $this->arrangedResponse($event, $result['category'], __('event-bjj_tournament::messages.draw_arranged'));
    }

    /** Empty a division's draw onto the bench so it can be built by hand. */
    private function clearDraw(ClubEvent $event, array $payload): array
    {
        $category = $event->categories()->find($payload['category_id'] ?? null);

        if (! $category) {
            return ['success' => false, 'message' => __('events::events.division_not_found')];
        }

        $result = $this->arrangement()->clear($event, $category);

        if (! $result['ok']) {
            return ['success' => false, 'message' => $result['message']];
        }

        $this->scheduler->scheduleAndNumber($event->fresh());

        return $this->arrangedResponse($event, $result['category'], __('event-bjj_tournament::messages.draw_cleared'));
    }

    /**
     * One arranged division, pushed to everyone watching. The bracket renders
     * differently per viewer (their own bouts are marked, only organisers see
     * the bench), so the broadcast is a refresh signal — the division's data
     * comes back only to the operator who moved it, for their in-place patch.
     */
    /**
     * Re-lay one division at the size its entrant list now needs.
     *
     * ── What this is for ───────────────────────────────────────────────────
     *
     * A knockout bracket is a power of two. A division drawn for eight holds
     * eight, and the ninth entrant — the late one, the one found in the wrong
     * weight class, the one nobody enrolled until the morning — has nowhere to
     * be put. Arrange cannot help: there is no empty corner to drag them into.
     * Before this, the only answer was to re-draw the whole event before it
     * started, which is no answer at all once it has.
     *
     * So this rebuilds ONE division at whatever size its current entrants
     * require, byes filling the remainder, and leaves everybody free to be
     * rearranged afterwards.
     *
     * ── What it refuses, and why that is the whole safety of it ────────────
     *
     * `build()` deletes the division's bouts and lays new ones. That is
     * acceptable for a bracket nobody has fought on and unforgivable for one
     * they have, so a division holding any decided contest is refused outright
     * rather than partially preserved — half a re-laid bracket is worse than
     * none. A bye does not count as a contest: it is an artefact of the shape
     * we are about to change.
     *
     * A bout loaded on a mat is refused too, even with no result yet, because
     * the mat scores against the row it loaded and this deletes rows.
     *
     * Entrants are taken WITHOUT the paid-and-weighed filter. Mid-competition
     * the point is to seat everybody who is entered; dropping the unverified
     * here would re-create the disappearance this whole day has been spent
     * undoing.
     */
    private function growDraw(ClubEvent $event, array $payload): array
    {
        $category = $event->categories()->find($payload['category_id'] ?? null);

        if (! $category || $category->isHeading()) {
            return ['success' => false, 'message' => __('events::events.division_not_found')];
        }

        if (($category->format ?: EventCategory::FORMAT_KNOCKOUT) === EventCategory::FORMAT_ROUND_ROBIN) {
            return ['success' => false, 'message' => __('event-bjj_tournament::messages.grow_not_a_bracket')];
        }

        $fought = $category->matches()
            ->whereNotNull('winner')
            ->whereNotNull('a_name')
            ->whereNotNull('b_name')
            ->exists();

        if ($fought) {
            return ['success' => false, 'message' => __('event-bjj_tournament::messages.grow_already_fought')];
        }

        $onMats = \App\Scoreboard\Fleet::matchesOnMats($event);

        if ($onMats !== [] && $category->matches()->whereIn('id', $onMats)->exists()) {
            return ['success' => false, 'message' => __('event-bjj_tournament::messages.grow_bout_on_mat')];
        }

        $entrants = $category->registrations()->where('role', 'participant')->count();

        if ($entrants < 2) {
            return ['success' => false, 'message' => __('event-bjj_tournament::messages.grow_needs_two')];
        }

        $this->draws->build($event, $category, paidOnly: false);
        $this->advanceByes($category);
        $this->scheduler->scheduleAndNumber($event->fresh());

        return $this->arrangedResponse(
            $event,
            $category->fresh(),
            __('event-bjj_tournament::messages.draw_grown', ['count' => $entrants])
        );
    }

    /**
     * Put a late entrant onto a division's board, whatever state it is in.
     *
     * The division may be mid-competition; that is the whole point. What it may
     * not do is cost anybody a result, and the engine — not this method — is
     * what guarantees that. Here we only resolve WHO and WHERE, including the
     * common case where the athlete is entered in the championship but sitting
     * in no division at all (or in the wrong one, which is the same repair).
     */
    private function admitLateEntry(ClubEvent $event, array $payload): array
    {
        $category = $event->categories()->find($payload['category_id'] ?? null);

        if (! $category || $category->isHeading()) {
            return ['success' => false, 'message' => __('events::events.division_not_found')];
        }

        $entry = $event->registrations()
            ->where('id', (int) ($payload['competitor_id'] ?? 0))
            ->where('role', 'participant')
            ->with('user:id,full_name,name')
            ->first();

        if (! $entry) {
            return ['success' => false, 'message' => __('events::events.late_entry_not_an_entrant')];
        }

        /*
         * "He says he is supposed to be in this division."
         *
         * ⚠️ A PERSON IS ONE; THEIR ENTRIES ARE MANY — one per division, which
         * is ordinary (Gi and No-Gi in the same championship) and is enforced by
         * a unique key on (event, user, division). So the entry the desk picked
         * is not necessarily the entry that belongs here: this athlete may
         * ALREADY hold one in this division, sitting off the board. Moving the
         * picked row on top of it violates that key and used to be a 500.
         *
         * So the entry for THIS division wins if there is one, and the picked
         * row is left exactly where it is. Only when there is no such entry is
         * the picked one moved — which is the repair for somebody entered in
         * the championship and placed in no division at all.
         */
        $mine = $event->registrations()
            ->where('role', 'participant')
            ->where('user_id', $entry->user_id)
            ->where('category_id', $category->id)
            ->with('user:id,full_name,name')
            ->first();

        if ($mine) {
            $entry = $mine;
        } else {
            $from = $entry->category;

            try {
                $entry->category_id = $category->id;
                $entry->save();
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                // Somebody else moved them while this request was in flight.
                return ['success' => false, 'message' => __('events::events.late_entry_already_drawn')];
            }

            if ($from) {
                rescue(fn () => $this->arrangement()->syncEntrants($from->fresh()), null, false);
            }
        }

        $result = $this->lateEntry()->admit($event, $category, $entry->fresh(['user']));

        if (! ($result['ok'] ?? false)) {
            return ['success' => false, 'message' => $result['message'] ?? __('events::events.action_unsupported')];
        }

        $this->scheduler->scheduleAndNumber($event->fresh());

        $name = $entry->user?->full_name ?: $entry->user?->name ?: __('events::events.athlete');

        $message = match (true) {
            $result['how'] === 'grown' => __('events::events.late_entry_grown', ['name' => $name, 'opponent' => $result['opponent']]),
            filled($result['opponent'] ?? null) => __('events::events.late_entry_placed', ['name' => $name, 'opponent' => $result['opponent']]),
            default => __('events::events.late_entry_placed_bye', ['name' => $name]),
        };

        return $this->arrangedResponse($event, $category->fresh(), $message);
    }

    private function arrangedResponse(ClubEvent $event, EventCategory $category, string $message): array
    {
        $this->broadcast($event, ['action' => 'draw', 'division' => $category->id]);

        /*
         * And the WALL. broadcast() reaches USER topics — an athlete's phone,
         * an organiser's console — and a hall screen is not a user: it has no
         * session and nothing in that scheme fits it, so it heard none of this.
         *
         * Every path into this method changes who is standing in a first-round
         * slot: arranging, clearing, growing a division, admitting a late
         * entry. The scheduler has just re-numbered and re-mat'd the bouts
         * above, so a competitor moved between brackets is now queued on a
         * different board — and both the board they left and the board they
         * joined were showing the old draw until somebody power-cycled a
         * television.
         *
         * Fleet is the scoreboard module's one public door (CLAUDE.md → "The
         * Platform Is a Set of Modules"), and it is silent and best-effort: an
         * arrangement must never fail because a screen is unreachable.
         */
        rescue(fn () => app(\App\Scoreboard\Fleet::class)->boardsChanged($event), null, false);

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
    /**
     * Has anything in this division actually been contested?
     *
     * `contested` (EventMatch) is the authority: two corners filled AND an
     * outcome. A BYE carries `done` and a winner with nobody to beat, and a
     * ladder of byes is scaffolding — clearing that is the whole point here.
     */
    private function hasBeenFought(EventCategory $category): bool
    {
        return $category->matches()->contested()->exists();
    }

    private function generateDraw(ClubEvent $event): array
    {
        if ($event->hasStarted()) {
            return ['success' => false, 'message' => __('event-bjj_tournament::messages.draw_final')];
        }

        foreach ($event->categories()->get() as $category) {
            $entrants = $category->registrations()->where('role', 'participant')->count();

            if ($entrants >= 2) {
                $this->draws->build($event, $category, paidOnly: false);
                $this->advanceByes($category);

                continue;
            }

            /*
             * Nothing to draw here — and that is the case this loop used to
             * walk past.
             *
             * It only ever asked for `>= 1`, so a division that had emptied
             * since the last cut (entrants moved to another group, or the
             * group released) was SKIPPED, and the bracket from that earlier
             * cut stayed on the board: a full ladder of bouts with nobody in
             * them (reported 2026-09-06 — a division showing seven empty bouts
             * and no entrants).
             *
             * So take the stale ladder away. Never one that was fought: a
             * result is a record of something that happened, and an odd state
             * on a board is a far smaller problem than a deleted bout.
             */
            if (! $this->hasBeenFought($category)) {
                $category->matches()->delete();
                $category->update(['draw_state' => 'provisional', 'draw_count' => $entrants]);
            }
        }

        $plan = $this->scheduler->scheduleAndNumber($event);

        // A re-cut draw changes everyone's opponent, mat and bout number, and it
        // renders differently for each viewer (their own bouts are highlighted)
        // — so send a refresh signal rather than a per-user payload.
        $this->broadcast($event, ['action' => 'draw']);

        $mats = collect($plan)
            ->map(fn ($p, $day) => __('event-bjj_tournament::messages.day_mats', ['day' => $day, 'count' => $p['courts']]))
            ->implode(' · ');

        return [
            'success' => true,
            'message' => __('event-bjj_tournament::messages.draw_generated').($mats ? ' · '.$mats : ''),
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
                title: __('event-bjj_tournament::messages.notify_weigh_in_title', ['title' => $event->title]),
                body: __('event-bjj_tournament::messages.notify_weigh_in_body', [
                    'time' => $event->weigh_in_at->format('g:i A'),
                    'place' => $event->location ?: $event->host()->name,
                ]),
                at: $event->inZone($event->weigh_in_at)->subDay()->setTime(
                    (int) config('event_notifications.event_day_reminder_hour', 7), 0,
                ),
                audience: Milestone::AUDIENCE_PARTICIPANTS,
                icon: 'bi-clipboard-data',
            );
        }

        if ($event->categories()->where('draw_state', 'final')->exists()) {
            $milestones[] = new Milestone(
                key: 'draw_published',
                title: __('event-bjj_tournament::messages.notify_draw_title', ['title' => $event->title]),
                body: __('event-bjj_tournament::messages.notify_draw_body'),
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

        /*
         * Per division, from what each entry was actually charged.
         *
         * It used to be `paid_count × the base fee`, which reads as zero on any
         * event whose price lives in fee options — every division showed
         * "3 paid · BHD 0" while the event had taken real money (2026-09-06).
         * `EventFee::chargedForMany` is the same source the event total uses,
         * so the divisions always add up to it.
         */
        /*
         * ONE entry per PERSON, exactly as the event total counts them.
         *
         * An athlete pays once and holds one entry per division, so summing
         * every paid entry booked the same fee into two divisions — and the
         * breakdown then added up to a total the event had never taken.
         * EventFee::billing picks the entry the fee is recorded on, which is
         * the one they registered through; its division is where the money
         * lands. The other division still shows the entrant in its `paid`
         * count — they HAVE paid — it just does not bank the fee twice.
         */
        $billed = \App\Events\Support\EventFee::billing($event, 'participant')
            ->where('paid', true)
            ->pluck('registration_id');

        $paid = \App\Events\Models\ClubEventRegistration::whereIn('id', $billed)
            ->get(['id', 'category_id']);

        $charged = \App\Events\Support\EventFee::chargedForMany($event, $paid->pluck('id'), 'participant')['amounts'];

        $byDivision = $paid->groupBy('category_id')
            ->map(fn ($rows) => $rows->sum(fn ($r) => $charged[$r->id] ?? 0.0));

        $rows = $event->categories()
            // A heading holds nobody, so it is not a division earning nothing —
            // it is not a division at all, and a row of zeroes per heading made
            // the breakdown look like a fault.
            ->where('is_heading', false)
            ->withCount([
            'registrations as entries_count' => fn ($q) => $q->where('role', 'participant'),
            'registrations as paid_count' => fn ($q) => $q->where('role', 'participant')->where('paid', true),
        ])->orderBy('sort_order')->get()
            ->map(fn (EventCategory $c) => [
                'division' => $c->name,
                'entries' => (int) $c->entries_count,
                'paid' => (int) $c->paid_count,
                'revenue' => round((float) ($byDivision[$c->id] ?? 0.0), 3),
            ])->values()->all();

        /*
         * Entrants nobody has placed yet get a row of their own.
         *
         * Without it the divisions silently failed to add up to the event's
         * revenue — ten paid entrants with no division carried a hundred dinars
         * that appeared in the total and in no line of the table.
         */
        $unplaced = \App\Events\Models\ClubEventRegistration::where('event_id', $event->id)
            ->where('role', 'participant')
            ->whereNull('category_id')
            ->get(['id', 'paid']);

        if ($unplaced->isNotEmpty()) {
            $rows[] = [
                'division' => __('events::events.entry_no_division_yet'),
                'entries' => $unplaced->count(),
                'paid' => $unplaced->where('paid', true)->count(),
                'revenue' => round($unplaced->where('paid', true)->sum(fn ($r) => $charged[$r->id] ?? 0.0), 3),
            ];
        }

        $finance['breakdown'] = $rows;

        return $finance;
    }

    /* ---------------- Display ---------------- */

    /**
     * Screens this package ships. Still empty: the championship currently
     * renders through the shared event screens, driven entirely by the data
     * this package returns from viewData()/rosterRows()/results(). Phase 2 of
     * the migration moves those screens in here as
     * resources/views/events/types/bjj_tournament/{mobile,desktop}/ and
     * they take over automatically — the caller resolves a declared view only
     * when it exists, so a package may ship one device at a time.
     */
    /**
     * The classes run as POOLS, published beside the draw.
     *
     * An event with both shapes shows both doors: the draw for the classes that
     * are bracketed, the tables for the classes that are not. The reading is
     * shared — a pool table is the same object in every sport.
     */
    public function publicView(ClubEvent $event): array
    {
        return app(\App\Events\Support\Tournament\PublicPools::class)->view($event);
    }

    public function views(): array
    {
        return [];
    }

    /**
     * A championship is its own app, in the organiser's name.
     *
     * The person who was sent this competition — and the coach entering a
     * squad, and the organiser running the mats — reads Brazilian Jiu-Jitsu on the
     * club's mark in the event's colour, with no platform chrome around it.
     * `/me/events/{uuid}` therefore wears the same skin `/e/{uuid}` does:
     * one surface, whichever door was used to reach it.
     */
    public function brandedSurface(): bool
    {
        return true;
    }

    public function viewData(ClubEvent $event, User $viewer): array
    {
        $registration = ClubEventRegistration::where('event_id', $event->id)
            ->where('user_id', $viewer->id)->first();

        $gate = $this->enrolmentGate($event, $viewer, $registration);

        return parent::viewData($event, $viewer) + [
            'division_label' => __('sport-brazilianjiujitsu::messages.division_label'),
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
     * pairing a screen picks a board that actually has bouts on it — the mistake
     * this prevents (a screen pointed at "Mat 3" when the draw only made two)
     * only shows itself on competition morning, in front of a hall.
     */
    /**
     * Every board on every mat, told to start again.
     *
     * A screen caches what it fetched on load — the event's sounds above all —
     * and the live mat state it receives afterwards says nothing about those.
     * So when the files behind them change, the only honest recovery is the one
     * an official already has a button for: reload.
     */
    public function reloadHallScreens(ClubEvent $event): void
    {
        ScreenChannel::notifyCourt($event, null, ['action' => 'reload']);
    }

    public function hallScreens(ClubEvent $event): ?array
    {
        return [
            // From the start, not only once the draw has put bouts on them.
            'mats' => \App\Events\Support\EventMats::of($event),
            'screens' => ScreenDevice::where('event_id', $event->id)
                ->whereNull('revoked_at')
                ->orderBy('court')->orderBy('id')
                ->get()
                ->map(fn (ScreenDevice $d) => $d->present())
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
            'date' => $event->date ? $event->date->copy()->addDays(max(0, $day - 1))->format('D j M') : '',
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

    /**
     * Award one division again, on request — App\Events\Contracts\AwardsDivisions.
     *
     * Shared code changes things a podium depends on (the play-off for bronze
     * being switched on or off) and cannot construct this package's own
     * Advancement to settle the consequence. This is the door it knocks on.
     */
    public function awardDivision(\App\Events\Models\EventCategory $category): array
    {
        return $this->advancement()->awardIfComplete($category);
    }

    private function advancement(): Advancement
    {
        return new Advancement($this->sport());
    }

    private function arrangement(): Arrangement
    {
        return new Arrangement($this->advancement());
    }

    private function lateEntry(): \App\Events\Support\Tournament\LateEntry
    {
        return new \App\Events\Support\Tournament\LateEntry($this->advancement());
    }

    /** A bracketed, medal-awarding combat championship — see CompetitionProfile. */
    public function profile(?ClubEvent $event = null): \App\Events\Support\Competition\CompetitionProfile
    {
        return \App\Events\Support\Competition\CompetitionProfile::combatChampionship($this->sport());
    }

    private function sport(): \App\Sports\Combat\CombatSport
    {
        return $this->sports->get(self::SPORT)
            ?? throw new \RuntimeException('The Brazilian Jiu-Jitsu combat-sport plug-in is not registered in config/combat.php.');
    }
}
