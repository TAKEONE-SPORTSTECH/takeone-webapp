<?php

namespace App\Events\OpenMat;

use App\Events\AbstractEventType;
use App\Events\Support\EnrolmentDecision;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventCategory;
use App\Models\EventMatch;
use App\Members\Models\User;

/**
 * Open Mat — a scoreboard for a fight nobody planned.
 *
 * Two people decide to go. One of them opens this on a phone, puts a name in
 * each corner, and taps start. There is no entry list, no draw, no queue, no
 * division, no fee and nothing to sign up for — and the bout is nevertheless
 * scored on the same table, shown on the same wall screens and introduced with
 * the same VS card as a national championship, because it is the same machinery
 * underneath.
 *
 * ── Why it exists beside Sparring ───────────────────────────────────────────
 *
 * Sparring is a COACH'S session: a floor of people who turned up, a queue the
 * coach builds, opened from the club-admin console. It presumes an "us" — the
 * club, its members, its mats. Open Mat presumes nothing: any member of any
 * club anywhere opens one, and a corner may be filled by somebody the platform
 * has never heard of. The two share a scoreboard and share nothing else, so
 * they are two packages rather than one with a mode flag.
 *
 * ── The three ways a corner gets filled ─────────────────────────────────────
 *
 *   · SEARCH — a member the operator finds. Club-mates by name; anyone
 *     discoverable by exact email or phone. You cannot browse the platform
 *     from here (see OpenMatSession::searchOpponents).
 *   · CODE — the mat shows six characters and a QR; the opponent scans it and
 *     takes a corner themselves. The consent path, and the one that works
 *     across clubs and borders.
 *   · GUEST — a typed name. No account, no invitation, nothing created. This
 *     is what lets the scoreboard be used on somebody who is not on TAKEONE at
 *     all, which is the difference between a club tool and one that travels.
 *
 * Delete this directory and its registry line and the feature is gone: the mats
 * become ordinary archived club events, the two open_mat_* tables drop, and
 * nothing else in the product notices.
 */
class OpenMat extends AbstractEventType
{
    /** `club_events.event_type` for an open mat. */
    public const TYPE = 'open_mat';

    /** The mat's own colour — a hot ember, distinct from Sparring's cyan. */
    public const COLOR = '#F97316';

    /**
     * The sports whose scoring table a mat can borrow.
     *
     * Listed only once a sport HAS a mat scoreboard, because that scoreboard is
     * the entire feature. `owns()` refuses a sport that has none, so the type
     * never appears where it would do nothing.
     */
    private const SPORTS = [
        'karate' => \App\Scoreboard\Sports\Karate\HallScreen\CourtDisplayDevice::class,
        'taekwondo' => \App\Scoreboard\Sports\Taekwondo\HallScreen\CourtDisplayDevice::class,
        // Brazilian Jiu-Jitsu calls the same thing by a different name — its
        // fleet is HallScreen\ScreenDevice, not CourtDisplay\CourtDisplayDevice.
        // That naming difference, and nothing else, is what kept BJJ off the mat
        // until now: every other seam a sport needs here (the `{sport}-scoreboard`
        // route pair, the device's own query helpers and present()) it already
        // satisfied. Worth stating out loud, because it is the exact cost
        // CLAUDE.md warns about under "Never name the same thing differently per
        // package".
        'bjj' => \App\Scoreboard\Sports\BrazilianJiuJitsu\HallScreen\ScreenDevice::class,
    ];

    public function __construct(private OpenMatSession $session) {}

    public function key(): string
    {
        return self::TYPE;
    }

    public function label(): string
    {
        return __('event-open_mat::messages.label');
    }

    public function icon(): string
    {
        return 'bi-fire';
    }

    public function color(): string
    {
        return self::COLOR;
    }

    public function owns(ClubEvent $event): bool
    {
        return (string) $event->event_type === self::TYPE
            && isset(self::SPORTS[(string) $event->sport]);
    }

    /** The sports a mat can be opened for. */
    public static function sports(): array
    {
        return array_keys(self::SPORTS);
    }

    /* ---------------- Lifecycle ---------------- */

    /** Running from the moment it is opened; done when it is shut. Nothing between. */
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
     * Nobody enrols in an open mat. Ever.
     *
     * The corner IS the entry, and it is filled from the console or by scanning
     * the mat's code — both of which write a corner, not a registration. An
     * open "join this event" door would be a second way onto the mat with none
     * of the checks the first one makes, so it is closed rather than left
     * inheriting the permissive default.
     */
    public function enrolmentGate(ClubEvent $event, User $user, ?ClubEventRegistration $existing = null): EnrolmentDecision
    {
        return EnrolmentDecision::deny('open_mat_no_enrolment', __('event-open_mat::messages.no_enrolment'));
    }

    /** The registration rows this package creates itself all land in one division. */
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
     * page cannot post something this mat has moved past.
     */
    public function availableActions(ClubEvent $event): array
    {
        if ($this->stage($event) === 'done') {
            return [];
        }

        return [
            ['action' => 'add_member', 'label' => __('event-open_mat::messages.action_add_member'), 'icon' => 'bi-person-plus'],
            ['action' => 'add_guest', 'label' => __('event-open_mat::messages.action_add_guest'), 'icon' => 'bi-person-plus'],
            ['action' => 'remove_person', 'label' => __('event-open_mat::messages.action_remove_person'), 'icon' => 'bi-person-dash'],
            ['action' => 'assign_corner', 'label' => __('event-open_mat::messages.action_assign_corner'), 'icon' => 'bi-box-arrow-in-right'],
            ['action' => 'place_member', 'label' => __('event-open_mat::messages.action_place_member'), 'icon' => 'bi-person-check'],
            ['action' => 'place_guest', 'label' => __('event-open_mat::messages.action_place_guest'), 'icon' => 'bi-person-plus'],
            ['action' => 'clear_corner', 'label' => __('event-open_mat::messages.action_clear_corner'), 'icon' => 'bi-x-circle'],
            ['action' => 'swap_corners', 'label' => __('event-open_mat::messages.action_swap'), 'icon' => 'bi-arrow-left-right'],
            ['action' => 'start_bout', 'label' => __('event-open_mat::messages.action_start_bout'), 'icon' => 'bi-play-circle'],
            ['action' => 'set_mats', 'label' => __('event-open_mat::messages.action_set_mats'), 'icon' => 'bi-grid-1x2'],
            ['action' => 'set_sport', 'label' => __('event-open_mat::messages.action_set_sport'), 'icon' => 'bi-shuffle'],
            ['action' => 'new_code', 'label' => __('event-open_mat::messages.action_new_code'), 'icon' => 'bi-arrow-repeat'],
            ['action' => 'close_mat', 'label' => __('event-open_mat::messages.action_close'), 'icon' => 'bi-stop-circle'],
        ];
    }

    /**
     * Apply one.
     *
     * Each returns the part of the console that changed, so the page patches in
     * place (No Page Reload). A refusal the operator can act on — "that is the
     * same person", "both corners first" — comes back as a message and a 422,
     * never as a fault.
     */
    public function performAction(ClubEvent $event, string $action, array $payload = []): array
    {
        if ($this->stage($event) === 'done') {
            return ['success' => false, 'message' => __('event-open_mat::messages.mat_closed')];
        }

        $actor = \Illuminate\Support\Facades\Auth::user();

        if (! $actor) {
            return ['success' => false, 'message' => __('events.action_unsupported')];
        }

        $court = (string) ($payload['mat'] ?? '');

        try {
            return match ($action) {
                // ── The floor: add somebody, then stand them in a corner ──
                'add_member' => $this->floorResult($event, $court, __('event-open_mat::messages.person_added'),
                    fn () => $this->session->addMember($event, (int) ($payload['user_id'] ?? 0), $actor)),

                'add_guest' => $this->floorResult($event, $court, __('event-open_mat::messages.person_added'),
                    fn () => $this->session->addGuest($event, (string) ($payload['name'] ?? ''), $payload['country'] ?? null, $actor)),

                'remove_person' => $this->floorResult($event, $court, __('event-open_mat::messages.person_removed'),
                    fn () => $this->session->removePerson($event, (int) ($payload['person_id'] ?? 0))),

                'assign_corner' => $this->cornerResult($event, $court, __('event-open_mat::messages.corner_set'),
                    $this->session->assign($event, $court, (string) ($payload['side'] ?? ''), (int) ($payload['person_id'] ?? 0))),

                'place_member' => $this->cornerResult($event, $court, __('event-open_mat::messages.corner_set'),
                    $this->session->placeMember($event, $court, (string) ($payload['side'] ?? ''), (int) ($payload['user_id'] ?? 0), $actor)),

                'place_guest' => $this->cornerResult($event, $court, __('event-open_mat::messages.corner_set'),
                    $this->session->placeGuest($event, $court, (string) ($payload['side'] ?? ''), (string) ($payload['name'] ?? ''), $payload['country'] ?? null, $actor)),

                'clear_corner' => $this->cornerResult($event, $court, __('event-open_mat::messages.corner_cleared'),
                    $this->session->clearCorner($event, $court, (string) ($payload['side'] ?? ''))),

                'swap_corners' => $this->cornerResult($event, $court, __('event-open_mat::messages.corners_swapped'),
                    $this->session->swapCorners($event, $court)),

                'start_bout' => $this->startBout($event, $court),
                'set_mats' => $this->setMats($event, (int) ($payload['mats'] ?? 1)),
                'set_sport' => $this->setSport($event, (string) ($payload['sport'] ?? '')),
                'new_code' => $this->newCode($event, $court),
                'close_mat' => $this->closeMat($event),

                default => ['success' => false, 'message' => __('events.action_unsupported')],
            };
        } catch (\RuntimeException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function cornerResult(ClubEvent $event, string $court, string $message, array $corners): array
    {
        $this->nudge($event, $court);

        return [
            'success' => true,
            'message' => $message,
            'mat' => $court,
            'corners' => $corners,
            // The floor rides along with every corner change: standing somebody
            // up moves them ON it, and the panel draws both from one reply.
            'floor' => $this->session->floor($event)->all(),
        ];
    }

    /** A change to the floor rather than to a corner. Same shape either way. */
    private function floorResult(ClubEvent $event, string $court, string $message, callable $do): array
    {
        $do();

        $this->nudge($event, $court ?: null);

        return [
            'success' => true,
            'message' => $message,
            'mat' => $court,
            'floor' => $this->session->floor($event)->all(),
            'corners' => $court ? $this->session->corners($event, $court) : null,
        ];
    }

    /**
     * Start the fight, and hand the mat straight to the scoring table.
     *
     * The console does not score anything itself — the sport's own table does,
     * and it is the same table a championship uses. So this returns the bout it
     * created AND the address of that table with the bout named, which is what
     * turns "two names" into "a scoreboard" in one tap.
     */
    private function startBout(ClubEvent $event, string $court): array
    {
        $match = $this->session->startBout($event, $court);

        $this->nudge($event, $court);

        return [
            'success' => true,
            'message' => __('event-open_mat::messages.bout_started'),
            'mat' => $court,
            'match_id' => $match->id,
            // The console posts `load` to the sport's OWN scoring endpoint with
            // this id before it navigates, so the table opens with the bout
            // already on the mat rather than with a queue to tap. Going through
            // the real endpoint rather than reaching into Scoring from here is
            // what keeps this package from touching the sport's code at all:
            // the same validation, the same screen pushes, the same authority.
            'command_url' => $this->commandUrl($event),
            'control_url' => $this->controlUrl($event, $court),
            'bouts' => $this->session->bouts($event, $court)->all(),
            'floor' => $this->session->floor($event)->all(),
            'corners' => $this->session->corners($event, $court),
        ];
    }

    private function setMats(ClubEvent $event, int $mats): array
    {
        $this->session->setMats($event, $mats);

        return [
            'success' => true,
            'message' => __('event-open_mat::messages.mats_saved'),
            'mats' => $this->session->mats($event),
        ];
    }

    /**
     * Switch which sport's rules this mat runs.
     *
     * This is the ONE question the front door does not ask, moved to where it
     * can be answered in a tap by somebody already looking at the mat. Only
     * while the mat has fought nothing: the sport decides the scoring table,
     * the corner names and which screen fleet a paired display belongs to, so
     * changing it under bouts that were fought under other rules would make
     * those results mean something they did not mean.
     */
    private function setSport(ClubEvent $event, string $sport): array
    {
        if (! isset(self::SPORTS[$sport])) {
            return ['success' => false, 'message' => __('event-open_mat::messages.unknown_sport')];
        }

        if ($sport === (string) $event->sport) {
            return ['success' => true, 'message' => '', 'sport' => $sport];
        }

        if (EventMatch::where('event_id', $event->id)->exists()) {
            return ['success' => false, 'message' => __('event-open_mat::messages.sport_locked')];
        }

        $event->sport = $sport;
        $event->save();

        return [
            'success' => true,
            'message' => __('event-open_mat::messages.sport_saved'),
            'sport' => $sport,
            // The scoring table lives at a per-sport address, so it moves too.
            'control_urls' => collect($this->session->mats($event))
                ->mapWithKeys(fn (string $m) => [$m => $this->controlUrl($event, $m)])->all(),
            'command_url' => $this->commandUrl($event),
        ];
    }

    /**
     * Burn the current join code and print a new one.
     *
     * The code is what lets a stranger onto the mat, so the operator needs a
     * way to retire one that has been photographed, shouted across a hall, or
     * left on a screen after the person it was for has gone.
     */
    private function newCode(ClubEvent $event, string $court): array
    {
        // The session owns the code's lifetime now that it is stored rather than
        // cached — including invalidating the old one's reverse lookup, which
        // this method used to do by reaching into a cache key by hand.
        return [
            'success' => true,
            'message' => __('event-open_mat::messages.code_renewed'),
            'mat' => $court,
            'code' => $this->session->rotateCode($event, $court),
        ];
    }

    private function closeMat(ClubEvent $event): array
    {
        $this->session->close($event);

        return ['success' => true, 'message' => __('event-open_mat::messages.mat_ended'), 'closed' => true];
    }

    /* ---------------- Results ---------------- */

    /**
     * A bout is over.
     *
     * Called by the sport's own Scoring::commit through the registry — which is
     * why the scoreboard needed no change to serve an open mat: it hands the
     * result to whichever package owns the event, and for a mat that is this.
     *
     * Two things happen and no more. The bout row is written, and the result is
     * filed onto both members' CASUAL record — a record kept deliberately apart
     * from medals and tournament placings, because a training win is a real
     * thing that happened and is not a competitive result. Nothing advances;
     * there is no next round.
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

        // The casual record. Best-effort in the sense that it must never stop a
        // mat: a record row that did not write is a missing line on a profile,
        // and a throw here would strand a bout that has already been fought.
        rescue(fn () => $this->session->fileResult($event, $match, $payload), null, false);

        $this->nudge($event, $match->court);

        return ['bouts' => $this->session->bouts($event, $match->court)->all()];
    }

    /** What was fought today. The mat's only output. */
    public function results(ClubEvent $event): array
    {
        return $this->session->bouts($event)->filter(fn (array $b) => $b['done'])->values()->all();
    }

    public function allowsManualResults(): bool
    {
        return true;
    }

    /**
     * No bracket, ever — the same reason Sparring has none. These bouts have no
     * rounds and feed nothing, and a tree drawn over them would invite somebody
     * to read a knockout that does not exist.
     */
    public function bracketView(ClubEvent $event, ?User $viewer = null): array
    {
        return [];
    }

    /* ---------------- Screens ---------------- */

    /**
     * The mats, and the wall screens on them.
     *
     * Mats come from the mat itself, never from its bouts: a screen is paired
     * to Mat 2 before anybody has stood on Mat 2, and a mat that only existed
     * once a bout was queued would leave somebody pairing to nothing.
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

    /* ---------------- The panel on the scoring table ---------------- */

    /**
     * "Next pair", on the scoreboard itself.
     *
     * This is the fix for the one thing that made the feature unusable in
     * practice. Every other event type knows its bouts in advance, so its
     * operator taps them off a running order and never leaves the table. An
     * open mat has no running order by definition — and the first cut made the
     * operator navigate back to a console, re-find two people and come back,
     * for every pair, all evening.
     *
     * So the mat brings its floor to the table: everyone who is here, sorted by
     * who has fought least, two taps to set the corners, one to start — and the
     * page never changes. Adding somebody new (a member by search, or a
     * stranger by name) happens right here too, because at an open mat the next
     * person to walk up was not on anybody's list a minute ago.
     *
     * Rendered only for a signed-in operator. A scoring table paired by DEVICE
     * TOKEN has no user behind it, and the writes this panel makes are all
     * member-authorised endpoints — so rather than open a token-authorised way
     * to put arbitrary names on a mat, that door simply does not get the panel.
     */
    public function matPanel(ClubEvent $event, string $court, User $viewer): ?array
    {
        if ($this->stage($event) === 'done') {
            return null;
        }

        return [
            'view' => 'event-open_mat::mat-panel',
            'label' => __('event-open_mat::messages.panel_button'),
            'data' => [
                'omCourt' => $court,
                'omFloor' => $this->session->floor($event)->all(),
                'omCorners' => $this->session->corners($event, $court),
                'omCode' => $this->session->joinCode($event, $court),
                'omJoinUrl' => route('openmat.join', ['code' => $this->session->joinCode($event, $court)]),
                'omActionBase' => \Illuminate\Support\Str::beforeLast(route('me.events.action', [$event->uuid, 'x'], false), 'x'),
                'omSearchUrl' => route('me.events.openmat.search', $event->uuid, false),
                'omStateUrl' => route('me.events.openmat', $event->uuid, false),
                'omMe' => $this->session->opponentRow($viewer->loadMissing('memberClubs:id,club_name,country'), $viewer),
            ],
        ];
    }

    /* ---------------- Display ---------------- */

    /** The console is this package's own; every other screen stays shared. */
    public function views(): array
    {
        return [
            'manage' => [
                'mobile' => 'event-open_mat::manage.mobile',
                'desktop' => 'event-open_mat::manage.desktop',
            ],
        ];
    }

    public function viewData(ClubEvent $event, User $viewer): array
    {
        $mats = $this->session->mats($event);
        $closed = $this->stage($event) === 'done';

        return parent::viewData($event, $viewer) + [
            'openMat' => [
                'mats' => $mats,
                'max_mats' => OpenMatSession::MAX_MATS,
                'closed' => $closed,
                'sport' => (string) $event->sport,
                // The viewer themselves, ready to drop into a corner without
                // searching. The person holding the console is usually one of
                // the two fighters; making them type their own name for that is
                // the kind of small stupidity that stops a feature being used.
                'me' => $this->session->opponentRow($viewer->loadMissing('memberClubs:id,club_name,country'), $viewer),
                'sports' => \App\Events\OpenMat\OpenMatController::sportOptions(),
                // A mat that has fought nothing may still change its rules.
                'sport_locked' => EventMatch::where('event_id', $event->id)->exists(),
                'corners' => collect($mats)->mapWithKeys(fn (string $m) => [$m => $this->session->corners($event, $m)])->all(),
                'floor' => $this->session->floor($event)->all(),
                'codes' => $closed ? [] : collect($mats)->mapWithKeys(fn (string $m) => [$m => $this->session->joinCode($event, $m)])->all(),
                'join_base' => rtrim(route('openmat.join', ['code' => 'CODE']), 'CODE'),
                'bouts' => $this->session->bouts($event)->all(),
                'control_urls' => collect($mats)->mapWithKeys(fn (string $m) => [$m => $this->controlUrl($event, $m)])->all(),
                'command_url' => $this->commandUrl($event),
                'live' => collect($mats)->mapWithKeys(fn (string $m) => [$m => $this->liveBoutId($event, $m)])->all(),
            ],
        ];
    }

    /** The bout currently on a mat, if any — what "Resume" points at. */
    private function liveBoutId(ClubEvent $event, string $court): ?int
    {
        return EventMatch::where('event_id', $event->id)
            ->where('court', $court)
            ->whereNull('winner')
            ->where('status', '!=', 'done')
            ->value('id');
    }

    /** The sport's own scoring table for this mat. */
    private function controlUrl(ClubEvent $event, string $court): ?string
    {
        $sport = (string) $event->sport;

        if (! isset(self::SPORTS[$sport])) {
            return null;
        }

        return route($sport.'-scoreboard.control', ['event' => $event->uuid, 'mat' => $court]);
    }

    /** Where the console posts scoring commands — the sport's own endpoint. */
    private function commandUrl(ClubEvent $event): ?string
    {
        $sport = (string) $event->sport;

        return isset(self::SPORTS[$sport])
            ? route($sport.'-scoreboard.command', $event->uuid)
            : null;
    }

    /**
     * Tell the consoles a corner changed, from outside this class.
     *
     * The join page is not an action on the mat — it is somebody else's phone —
     * so it cannot go through performAction, and `broadcast()` is protected for
     * good reason. This is the one seam.
     */
    public function notifyConsoles(ClubEvent $event, ?string $court = null): void
    {
        $this->nudge($event, $court);
    }

    /**
     * Tell the mat's screens and any other console that something moved.
     *
     * A refresh signal rather than a payload: what each holder of the console
     * may see differs, so they re-fetch. Best-effort, like every push in the
     * app — a screen that missed it re-reads when it next polls.
     */
    private function nudge(ClubEvent $event, ?string $court = null): void
    {
        $channel = match ((string) $event->sport) {
            'karate' => \App\Scoreboard\Sports\Karate\HallScreen\ScreenChannel::class,
            'taekwondo' => \App\Scoreboard\Sports\Taekwondo\HallScreen\ScreenChannel::class,
            default => null,
        };

        if ($channel) {
            rescue(fn () => $channel::notifyCourt($event, $court ?: null), null, false);
        }

        $this->broadcast($event, ['action' => 'open_mat']);
    }
}
