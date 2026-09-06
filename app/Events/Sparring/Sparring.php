<?php

namespace App\Events\Sparring;

use App\Events\AbstractEventType;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventCategory;
use App\Models\EventMatch;
use App\Members\Models\User;

/**
 * Sparring — the scoreboard for a session nobody planned.
 *
 * A coach at a club says "let's fight" in the middle of training. There is no
 * draw, no entry list, no weight class and no medal, but there is very much a
 * mat, two people on it, and a hall that wants to see the score. This package
 * is that: the club's own scoreboard, opened in one tap and closed the same
 * evening.
 *
 * ── Why it is not a tournament ──────────────────────────────────────────────
 *
 * A championship derives everything from its draw: who fights whom, what
 * happens to a winner, when a division has a podium. Sparring derives nothing.
 * A bout exists because a coach paired two people, and its result is a fact
 * about that bout and nothing else — so this package implements recordOutcome()
 * itself, and deliberately returns no bracket at all.
 *
 * ── Why it is cross-sport, when the tournaments are not ─────────────────────
 *
 * The two Tournament packages are separate because their RULES differ — senshu,
 * gam-jeom, how a bout is won. Sparring adds no rules of its own: it borrows
 * the sport's scoring table wholesale, which is exactly the point of using it
 * for training. What differs per sport is only which fleet a screen joins and
 * which console URL to open, so this is one package with a sport map rather
 * than two packages that would differ by four lines.
 *
 * Delete this directory and its registry line and the feature is gone — the
 * sessions become ordinary archived club events, and nothing else notices.
 */
class Sparring extends AbstractEventType
{
    /** `club_events.event_type` for a session. */
    public const TYPE = 'sparring';

    /**
     * The sports whose scoring table a session can borrow.
     *
     * A sport is listed here only once it HAS a mat scoreboard, because the
     * session's entire value is that scoreboard. Sparring for a sport with no
     * scoreboard would be an event that does nothing, so `owns()` refuses to
     * claim one and the type never appears for it.
     */
    private const SPORTS = [
        'karate' => \App\Scoreboard\Sports\Karate\HallScreen\CourtDisplayDevice::class,
        'taekwondo' => \App\Scoreboard\Sports\Taekwondo\HallScreen\CourtDisplayDevice::class,
    ];

    public function __construct(private SparringSession $session) {}

    public function key(): string
    {
        return self::TYPE;
    }

    public function label(): string
    {
        return __('event-sparring::messages.label');
    }

    public function icon(): string
    {
        return 'bi-lightning-charge';
    }

    public function color(): string
    {
        return '#0EA5E9';
    }

    public function owns(ClubEvent $event): bool
    {
        return (string) $event->event_type === self::TYPE
            && isset(self::SPORTS[(string) $event->sport]);
    }

    /** The sports a club may open a session for. */
    public static function sports(): array
    {
        return array_keys(self::SPORTS);
    }

    /* ---------------- Lifecycle ---------------- */

    /**
     * Two states and nothing in between. A session is running from the moment
     * it is opened — there is no enrolment to wait for and no draw to lock —
     * and it is over when the coach closes it.
     */
    public function stages(): array
    {
        return ['running', 'done'];
    }

    public function stage(ClubEvent $event): string
    {
        return $event->is_archived || $event->status === 'completed' ? 'done' : 'running';
    }

    public function canTransitionTo(ClubEvent $event, string $stage): bool
    {
        return $stage === 'done' && $this->stage($event) === 'running';
    }

    /* ---------------- Enrolment ---------------- */

    /**
     * A member of the club may put themselves on the floor; anyone else may not.
     *
     * The coach adding people from the console is the normal path, but a member
     * who opens the session on their phone should be able to say "I'm in" — and
     * a session is internal, so eligibility has already narrowed this to the
     * club. Everything else about entry (a fee, a weight, a division) does not
     * exist here.
     */
    public function classifyEntry(ClubEvent $event, ClubEventRegistration $registration): ?EventCategory
    {
        return $this->session->division($event);
    }

    /* ---------------- The console's own actions ---------------- */

    /**
     * Every write the console can make.
     *
     * Declared here because PersonalEventController::performAction refuses any
     * action a package does not currently offer — deny by default, so a stale
     * page cannot post something this session has moved past. The console
     * renders its own controls rather than these rows, but the vocabulary is
     * one list either way.
     */
    public function availableActions(ClubEvent $event): array
    {
        if ($this->stage($event) === 'done') {
            return [];
        }

        return [
            ['action' => 'add_entrants', 'label' => __('event-sparring::messages.action_add_entrants'), 'icon' => 'bi-person-plus'],
            ['action' => 'remove_entrant', 'label' => __('event-sparring::messages.action_remove_entrant'), 'icon' => 'bi-person-dash'],
            ['action' => 'queue_bout', 'label' => __('event-sparring::messages.action_queue_bout'), 'icon' => 'bi-plus-circle'],
            ['action' => 'unqueue_bout', 'label' => __('event-sparring::messages.action_unqueue_bout'), 'icon' => 'bi-x-circle'],
            ['action' => 'set_mats', 'label' => __('event-sparring::messages.action_set_mats'), 'icon' => 'bi-grid-1x2'],
            ['action' => 'close_session', 'label' => __('event-sparring::messages.action_close'), 'icon' => 'bi-stop-circle'],
        ];
    }

    /**
     * Apply one of them.
     *
     * Each returns the part of the console that changed, so the page patches in
     * place (No Page Reload). A refusal the coach can act on — "they are
     * already queued", "that is the same person" — comes back as a message and
     * a 422, never as a fault.
     */
    public function performAction(ClubEvent $event, string $action, array $payload = []): array
    {
        if ($this->stage($event) === 'done') {
            return ['success' => false, 'message' => __('event-sparring::messages.session_closed')];
        }

        try {
            return match ($action) {
                'add_entrants' => [
                    'success' => true,
                    'message' => __('event-sparring::messages.entrants_added'),
                    'entrants' => $this->session->addEntrants($event, (array) ($payload['user_ids'] ?? []))->all(),
                ],
                'remove_entrant' => $this->removeEntrant($event, (int) ($payload['registration_id'] ?? 0)),
                'queue_bout' => $this->queueBout($event, $payload),
                'unqueue_bout' => $this->unqueueBout($event, (int) ($payload['match_id'] ?? 0)),
                'set_mats' => $this->setMats($event, (int) ($payload['mats'] ?? 1)),
                'close_session' => $this->closeSession($event),
                default => ['success' => false, 'message' => __('events.action_unsupported')],
            };
        } catch (\RuntimeException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function removeEntrant(ClubEvent $event, int $registrationId): array
    {
        $this->session->removeEntrant($event, $registrationId);

        return [
            'success' => true,
            'message' => __('event-sparring::messages.entrant_removed'),
            'entrants' => $this->session->entrants($event)->all(),
        ];
    }

    private function queueBout(ClubEvent $event, array $payload): array
    {
        $this->session->queueBout(
            $event,
            (int) ($payload['aka'] ?? 0),
            (int) ($payload['ao'] ?? 0),
            (string) ($payload['mat'] ?? ''),
        );

        $this->nudgeMats($event, (string) ($payload['mat'] ?? ''));

        return [
            'success' => true,
            'message' => __('event-sparring::messages.bout_queued'),
            'bouts' => $this->session->bouts($event)->all(),
            'entrants' => $this->session->entrants($event)->all(),
            'ready_mats' => $this->session->readyMats($event),
        ];
    }

    private function unqueueBout(ClubEvent $event, int $matchId): array
    {
        $this->session->unqueue($event, $matchId);

        $this->nudgeMats($event);

        return [
            'success' => true,
            'message' => __('event-sparring::messages.bout_removed'),
            'bouts' => $this->session->bouts($event)->all(),
            'ready_mats' => $this->session->readyMats($event),
        ];
    }

    private function setMats(ClubEvent $event, int $mats): array
    {
        $this->session->setMats($event, $mats);

        return [
            'success' => true,
            'message' => __('event-sparring::messages.mats_saved'),
            'mats' => $this->session->mats($event),
            'ready_mats' => $this->session->readyMats($event),
        ];
    }

    private function closeSession(ClubEvent $event): array
    {
        $this->session->close($event);

        return [
            'success' => true,
            'message' => __('event-sparring::messages.session_ended'),
            'closed' => true,
        ];
    }

    /**
     * Tell the mats' own screens that the running order moved.
     *
     * The queue board on a wall is built from these bouts, so adding or
     * dropping one has to reach it — the same nudge the scoring table sends
     * when it loads or clears a bout. Best-effort, like every push in the app:
     * a screen that missed it re-fetches when it next polls.
     */
    private function nudgeMats(ClubEvent $event, ?string $mat = null): void
    {
        $channel = match ((string) $event->sport) {
            'karate' => \App\Scoreboard\Sports\Karate\HallScreen\ScreenChannel::class,
            'taekwondo' => \App\Scoreboard\Sports\Taekwondo\HallScreen\ScreenChannel::class,
            default => null,
        };

        if ($channel) {
            rescue(fn () => $channel::notifyCourt($event, $mat ?: null), null, false);
        }

        // And the consoles other coaches are holding. A refresh signal rather
        // than a payload: what each of them may see differs, so they re-fetch.
        $this->broadcast($event, ['action' => 'sparring']);
    }

    /* ---------------- Results ---------------- */

    /**
     * A bout is over.
     *
     * Called by the sport's own Scoring::commit through the registry, which is
     * why the scoreboard needed no change to serve a session: it hands the
     * result to whichever package owns the event, and for a session that is
     * this method. It writes the bout and stops. Nothing advances — there is no
     * next round — and nothing is written to either member's competitive record,
     * because a training bout is not a competitive result and putting it beside
     * real medals would devalue both.
     */
    public function recordOutcome(ClubEvent $event, int $unitId, array $payload): array
    {
        $match = EventMatch::where('event_id', $event->id)->find($unitId);

        if (! $match) {
            return [];
        }

        $winner = in_array($payload['winner'] ?? null, ['a', 'b'], true) ? $payload['winner'] : null;

        $match->fill([
            'a_score' => (string) ($payload['a_score'] ?? $match->a_score),
            'b_score' => (string) ($payload['b_score'] ?? $match->b_score),
            'winner' => $winner,
            'win_reason' => $payload['win_reason'] ?? null,
            'win_note' => $payload['win_note'] ?? null,
            'status' => $payload['status'] ?? 'done',
        ])->save();

        $this->nudgeMats($event, $match->court);

        return ['bouts' => $this->session->bouts($event)->all()];
    }

    /** What was fought today. The session's only output. */
    public function results(ClubEvent $event): array
    {
        return $this->session->bouts($event)->filter(fn (array $b) => $b['done'])->values()->all();
    }

    public function allowsManualResults(): bool
    {
        return true;
    }

    /**
     * No bracket, ever.
     *
     * The default would happily draw one from the bouts, because any type whose
     * divisions hold bouts gets a bracket for free. Here that would be a lie:
     * these bouts have no rounds and feed nothing, and a tree drawn over them
     * would invite somebody to read a knockout that does not exist.
     */
    public function bracketView(ClubEvent $event, ?User $viewer = null): array
    {
        return [];
    }

    /* ---------------- Screens ---------------- */

    /**
     * The mats, and the screens on them.
     *
     * Mats come from the session itself, NOT from its bouts — the coach pairs a
     * screen to Mat 2 before anybody has been put on Mat 2, and a mat that only
     * exists once a bout is queued would leave them pairing to nothing.
     */
    public function hallScreens(ClubEvent $event): ?array
    {
        $device = self::SPORTS[(string) $event->sport] ?? null;

        if (! $device) {
            return null;
        }

        return [
            'mats' => $this->session->mats($event),
            'screens' => $device::where('event_id', $event->id)
                ->whereNull('revoked_at')
                ->orderBy('court')->orderBy('id')
                ->get()
                ->map(fn ($d) => $d->present())
                ->all(),
        ];
    }

    /* ---------------- Display ---------------- */

    /** The console is this package's own; every other screen stays shared. */
    public function views(): array
    {
        return [
            'manage' => [
                'mobile' => 'event-sparring::manage.mobile',
                'desktop' => 'event-sparring::manage.desktop',
            ],
        ];
    }

    public function viewData(ClubEvent $event, User $viewer): array
    {
        return parent::viewData($event, $viewer) + [
            'sparring' => [
                'mats' => $this->session->mats($event),
                'entrants' => $this->session->entrants($event)->all(),
                'bouts' => $this->session->bouts($event)->all(),
                'club_members' => $this->clubMembers($event),
                'control_urls' => $this->controlUrls($event),
                // Which of those addresses will actually open — see readyMats().
                'ready_mats' => $this->session->readyMats($event),
                'closed' => $this->stage($event) === 'done',
                'max_mats' => SparringSession::MAX_MATS,
            ],
        ];
    }

    /**
     * The club's members, for the "who is here" picker.
     *
     * The host club's only — the same boundary the engine enforces on the way
     * in. A picker that could search the platform would turn a training console
     * into a member directory.
     */
    private function clubMembers(ClubEvent $event): array
    {
        return User::whereHas('memberClubs', fn ($q) => $q->whereKey($event->tenant_id))
            ->orderBy('full_name')
            ->limit(500)
            ->get(['id', 'full_name', 'name', 'gender', 'profile_picture', 'profile_picture_is_public'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->full_name ?: $u->name,
                'gender' => $u->gender,
                'photo' => ($u->profile_picture && $u->profile_picture_is_public)
                    ? file_url($u->profile_picture)
                    : null,
            ])->all();
    }

    /** One scoring-table address per mat, in the sport's own console. */
    private function controlUrls(ClubEvent $event): array
    {
        $sport = (string) $event->sport;

        if (! isset(self::SPORTS[$sport])) {
            return [];
        }

        $route = $sport.'-scoreboard.control';

        return collect($this->session->mats($event))
            ->mapWithKeys(fn (string $mat) => [$mat => route($route, ['event' => $event->uuid, 'mat' => $mat])])
            ->all();
    }
}
