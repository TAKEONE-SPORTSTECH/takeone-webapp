<?php

namespace App\Events\Support;

use App\Members\Models\User;
use App\Members\Models\UserNotification;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventWithdrawalRequest;
use App\Events\EventTypeRegistry;
use Illuminate\Support\Str;

/**
 * An athlete asks to come out of a competition.
 *
 * WHY A REQUEST
 * -------------
 * `PersonalEventController::cancel()` has always refused: *"Registration is
 * final — no self-cancel once you've joined."* That is defensible on run-day
 * and indefensible three weeks out, when somebody has broken a finger. But the
 * fix is not a delete button, because a draw is CUT from the entry list: a
 * competitor removing themselves an hour before the first bout silently re-cuts
 * a bracket the organiser has already printed, and after the first bout it
 * would corrupt a running competition.
 *
 * So the athlete asks and the organiser answers. Nobody leaves a bracket
 * without the person running it knowing, and the athlete gets an answer instead
 * of a phone number.
 *
 * The shape is `PublicEntry` pointing the other way — a state machine in its
 * own table so `club_event_registrations` only ever holds people who are
 * actually competing — and it borrows that class's hardest-won lesson: a
 * settled request is REOPENED, never duplicated, because a second insert
 * against the unique index meets the athlete as a bare 500. That happened in
 * production; it does not need to happen twice.
 */
class Withdrawal
{
    public function __construct(
        private EventAccess $access,
        private EventTypeRegistry $registry,
        private EntryService $entries,
        private AudienceResolver $audience,
    ) {}

    /* ==================== The athlete's side ==================== */

    /**
     * Raise (or re-raise) a request to withdraw.
     *
     * @return array{ok: bool, message: string, request?: array}
     */
    public function request(ClubEvent $event, User $athlete, ?string $reason = null): array
    {
        $registration = $this->registrationFor($event, $athlete);

        if (! $registration) {
            return ['ok' => false, 'message' => __('events.withdraw_not_entered')];
        }

        if ($event->hasEnded()) {
            return ['ok' => false, 'message' => __('events.withdraw_event_over')];
        }

        $existing = EventWithdrawalRequest::where('event_id', $event->id)
            ->where('user_id', $athlete->id)
            ->first();

        // Idempotent: a double tap, a back button or a reload must not queue
        // the same person twice, and the honest answer is the one they have.
        if ($existing && $existing->isPending()) {
            return [
                'ok' => true,
                'message' => __('events.withdraw_already_pending'),
                'request' => $this->present($existing),
            ];
        }

        $reason = $reason === null ? null : mb_substr(trim($reason), 0, 300);

        if ($existing) {
            // Reopened, not duplicated — the unique index would refuse a second
            // row, and an athlete whose first request was refused may have a
            // new reason a week later.
            $existing->forceFill([
                'registration_id' => (int) $registration->id,
                'state' => 'pending',
                'reason' => $reason,
                'response' => null,
                'decided_by' => null,
                'decided_at' => null,
            ])->save();

            $request = $existing->fresh();
        } else {
            $request = EventWithdrawalRequest::create([
                'uuid' => (string) Str::uuid(),
                'event_id' => $event->id,
                'user_id' => $athlete->id,
                'registration_id' => (int) $registration->id,
                'state' => 'pending',
                'reason' => $reason,
            ]);
        }

        $this->notifyManagers($event, $request, $athlete);
        $this->announce($event, $request);

        return [
            'ok' => true,
            'message' => __('events.withdraw_received'),
            'request' => $this->present($request),
        ];
    }

    /**
     * The athlete changes their mind before anyone has answered.
     *
     * @return array{ok: bool, message: string, request?: array}
     */
    public function cancel(ClubEvent $event, User $athlete): array
    {
        $request = EventWithdrawalRequest::where('event_id', $event->id)
            ->where('user_id', $athlete->id)
            ->first();

        if (! $request || ! $request->isPending()) {
            return ['ok' => false, 'message' => __('events.withdraw_nothing_to_cancel')];
        }

        $request->forceFill(['state' => 'cancelled', 'decided_at' => now()])->save();

        $this->announce($event, $request->fresh());

        return [
            'ok' => true,
            'message' => __('events.withdraw_cancelled'),
            'request' => $this->present($request->fresh()),
        ];
    }

    /** Their outstanding request for this event, if they have one. */
    public function pendingFor(ClubEvent $event, User $athlete): ?EventWithdrawalRequest
    {
        return EventWithdrawalRequest::where('event_id', $event->id)
            ->where('user_id', $athlete->id)
            ->where('state', 'pending')
            ->first();
    }

    /* ==================== The organiser's side ==================== */

    /**
     * The queue: everybody waiting on this organiser, newest first.
     *
     * @return array<int, array>
     */
    public function queue(ClubEvent $event, User $actor): array
    {
        if (! $this->access->canManage($event, $actor)) {
            return [];
        }

        return EventWithdrawalRequest::with('athlete:id,uuid,full_name,name,profile_picture,gender')
            ->where('event_id', $event->id)
            ->where('state', 'pending')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn (EventWithdrawalRequest $r) => $this->present($r))
            ->values()->all();
    }

    /**
     * Grant it — this is the moment they leave the competition.
     *
     * The removal goes through `EntryService::remove()` rather than deleting
     * the row here, so every rule about leaving an entry list still applies:
     * it refuses once the competition has started, and refuses outright for an
     * entry that has already fought a decided bout. An organiser who overrides
     * that does it on the entrants screen, deliberately, knowing what it costs.
     *
     * @return array{ok: bool, message: string, request?: array}
     */
    public function grant(EventWithdrawalRequest $request, User $actor, ?string $response = null): array
    {
        $event = $request->event;

        if (! $event || ! $this->access->canManage($event, $actor)) {
            return ['ok' => false, 'message' => __('events.withdraw_not_yours')];
        }

        if (! $request->isPending()) {
            return ['ok' => false, 'message' => __('events.withdraw_already_decided')];
        }

        $registration = ClubEventRegistration::where('event_id', $event->id)
            ->where('user_id', $request->user_id)
            ->first();

        // Already gone — an organiser removed them by hand while this sat in
        // the queue. Settle the request rather than failing: the athlete asked
        // to be out and they are out.
        if (! $registration) {
            return $this->settle($request, $actor, 'granted', $response, true);
        }

        // `remove()` owns every rule about LEAVING an entry list — it refuses
        // once the competition has started, refuses an entry that has already
        // fought a decided bout, deletes the payment proof before the row, and
        // re-cuts the vacated division itself. So this asks it rather than
        // deleting anything here. `notify: false` because it would otherwise
        // tell the athlete an organiser removed them, and `settle()` below
        // sends the true sentence instead.
        $removal = $this->entries->remove($event, $actor, $registration, notify: false);

        if (($removal['ok'] ?? false) !== true) {
            return [
                'ok' => false,
                'message' => $removal['row']['message'] ?? __('events.withdraw_cannot_remove'),
            ];
        }

        return $this->settle($request, $actor, 'granted', $response, true);
    }

    /**
     * Refuse it — they stay in the competition.
     *
     * @return array{ok: bool, message: string, request?: array}
     */
    public function refuse(EventWithdrawalRequest $request, User $actor, ?string $response = null): array
    {
        $event = $request->event;

        if (! $event || ! $this->access->canManage($event, $actor)) {
            return ['ok' => false, 'message' => __('events.withdraw_not_yours')];
        }

        if (! $request->isPending()) {
            return ['ok' => false, 'message' => __('events.withdraw_already_decided')];
        }

        return $this->settle($request, $actor, 'refused', $response, false);
    }

    /** Write the decision, tell the athlete, nudge every console. */
    private function settle(EventWithdrawalRequest $request, User $actor, string $state, ?string $response, bool $granted): array
    {
        $request->forceFill([
            'state' => $state,
            'response' => $response === null ? null : mb_substr(trim($response), 0, 300),
            'decided_by' => (int) $actor->id,
            'decided_at' => now(),
        ])->save();

        $request->refresh();

        $this->notifyAthlete($request, $granted);
        $this->announce($request->event, $request);

        return [
            'ok' => true,
            'message' => $granted
                ? __('events.withdraw_granted', ['name' => $request->athlete?->full_name ?? ''])
                : __('events.withdraw_refused', ['name' => $request->athlete?->full_name ?? '']),
            'request' => $this->present($request),
        ];
    }

    /* ==================== Telling people ==================== */

    /**
     * Every manager hears about a new request — not only the creator.
     *
     * `PublicEntry::notifyOrganiser` notifies `created_by` alone, which is how
     * a co-organiser or a host-club owner ends up never seeing a queue they are
     * responsible for. This uses the shared `managers()` audience instead.
     */
    private function notifyManagers(ClubEvent $event, EventWithdrawalRequest $request, User $athlete): void
    {
        $name = $athlete->full_name ?: $athlete->name;

        foreach ($this->audience->managers($event) as $managerId) {
            rescue(fn () => UserNotification::notifyUser($managerId, 'event', __('events.withdraw_notify_title', [
                'name' => $name,
            ]), [
                'body' => __('events.withdraw_notify_body', ['title' => $event->title]),
                'icon' => 'bi-box-arrow-left',
                'action_url' => route('events.public', $event->uuid),
                'subject_type' => (new ClubEvent)->getMorphClass(),
                'subject_id' => $event->id,
                'tenant_id' => $event->tenant_id,
            ]), null, false);
        }
    }

    /** They asked; they are told either way, without having to check back. */
    private function notifyAthlete(EventWithdrawalRequest $request, bool $granted): void
    {
        $event = $request->event;

        rescue(fn () => UserNotification::notifyUser((int) $request->user_id, 'event', $granted
            ? __('events.withdraw_athlete_granted_title', ['title' => $event->title])
            : __('events.withdraw_athlete_refused_title', ['title' => $event->title]), [
                'body' => $request->response ?: ($granted
                    ? __('events.withdraw_athlete_granted_body')
                    : __('events.withdraw_athlete_refused_body')),
                'icon' => $granted ? 'bi-box-arrow-left' : 'bi-x-circle',
                // The event's OWN page, never the platform member shell — a
                // deep link that breaks the seal throws the athlete out of the
                // app the link belongs to.
                'action_url' => route('events.public', $event->uuid),
                'subject_type' => (new ClubEvent)->getMorphClass(),
                'subject_id' => $event->id,
                'tenant_id' => $event->tenant_id,
            ]), null, false);
    }

    /** A refresh signal: the queue and the athlete's panel render differently. */
    private function announce(ClubEvent $event, EventWithdrawalRequest $request): void
    {
        $payload = [
            'action' => 'withdrawal',
            'event' => $event->uuid,
            'state' => $request->state,
            'athlete' => (int) $request->user_id,
        ];

        $watchers = $this->audience->entryWatchers($event, (int) $request->user_id);

        rescue(fn () => \Realtime()->publishMany(array_map(
            fn (int $id) => ['topic' => \Realtime()->userTopic($id, 'events'), 'payload' => $payload],
            $watchers,
        )), null, false);
    }

    /* ==================== Reading it back ==================== */

    private function registrationFor(ClubEvent $event, User $athlete): ?ClubEventRegistration
    {
        return ClubEventRegistration::where('event_id', $event->id)
            ->where('user_id', $athlete->id)
            ->where('role', 'participant')
            ->first();
    }

    /** @return array<string, mixed> */
    public function present(EventWithdrawalRequest $request): array
    {
        $athlete = $request->athlete;

        return [
            'uuid' => $request->uuid,
            'state' => $request->state,
            'reason' => $request->reason,
            'response' => $request->response,
            'asked_at' => $request->created_at?->toIso8601String(),
            'decided_at' => $request->decided_at?->toIso8601String(),
            'athlete' => $athlete ? [
                'uuid' => $athlete->uuid,
                'name' => $athlete->full_name ?: $athlete->name,
                'photo' => $athlete->profile_picture ? file_url($athlete->profile_picture) : null,
                'gender' => $athlete->gender,
            ] : null,
        ];
    }
}
