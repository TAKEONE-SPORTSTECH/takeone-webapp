<?php

namespace App\Events\Contracts;

use App\Events\Support\EnrolmentDecision;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventCategory;
use App\Models\User;

/**
 * An event type as a self-contained package (CLAUDE.md → "Events Are
 * Self-Contained Packages").
 *
 * One implementation owns the ENTIRE vertical for one kind of event: what it
 * stores, what it asks for at creation, who may enrol and under what gate, its
 * lifecycle, its domain engine, its outputs, its financials and its screens.
 *
 * Controllers resolve a package from the registry and delegate. A controller,
 * shared service or shared view must never test `$event->sport` or
 * `$event->event_type` to decide behaviour — that decision belongs here.
 *
 * Add a type: create app/Events/Sports/<Sport>/<Type>/ (a sport folder holds
 * every event type that sport runs), implement this contract — usually by
 * extending AbstractEventType — register it in config/event_types.php, and ship
 * its views, migrations, MCP coverage and tests. No shared-code edit.
 */
interface EventType
{
    /* ---------------- Identity ---------------- */

    /** Stable package key, e.g. "taekwondo_tournament". */
    public function key(): string;

    /** Human label for the type picker, e.g. "Taekwondo Tournament". */
    public function label(): string;

    /**
     * Does this package own the given event? The registry asks each registered
     * package in order and the first match wins, so a package must answer
     * narrowly (its own event_type AND its own sport).
     */
    public function owns(ClubEvent $event): bool;

    /* ---------------- Schema & creation input ---------------- */

    /**
     * Validation rules for the type-specific half of the create/edit payload.
     * Merged on top of the shared core rules (title, dates, fees, …).
     *
     * @return array<string, mixed>
     */
    public function validationRules(?ClubEvent $event = null): array;

    /**
     * Which form sections this type's create/edit screen renders, in order.
     *
     * @return array<int, string>
     */
    public function formSections(): array;

    /**
     * Reference data this type's create/edit screen needs — weight tables, belt
     * ladders, fixture templates. Exposed through the registry so the creation
     * screen never has to name a specific type to look its catalogue up.
     *
     * @return array<string, mixed>
     */
    public function formCatalog(): array;

    /**
     * Map validated input onto the type-specific ClubEvent column values.
     * Return only the columns this package owns; the caller merges.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function columnsFromInput(array $data, ?ClubEvent $event = null): array;

    /**
     * Persist everything that lives OUTSIDE club_events — divisions, fixtures,
     * test items — after the event row itself is saved.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveRelatedData(ClubEvent $event, array $data): void;

    /* ---------------- Enrolment ---------------- */

    /** May this member enter as a COMPETITOR, and in which division? */
    public function enrolmentGate(ClubEvent $event, User $user, ?ClubEventRegistration $existing = null): EnrolmentDecision;

    /**
     * The entrant set changed (join, removal, moderation). Let the package
     * re-derive whatever depends on it — a provisional draw, a fixture list.
     */
    public function onEntrantsChanged(ClubEvent $event, ?EventCategory $category = null): void;

    /* ---------------- Lifecycle ---------------- */

    /**
     * The event's current stage in THIS type's state machine, e.g.
     * draft · enrolling · weigh_in · drawn · running · completed · cancelled.
     */
    public function stage(ClubEvent $event): string;

    /** Is a transition to $stage legal right now? Enforced server-side. */
    public function canTransitionTo(ClubEvent $event, string $stage): bool;

    /** Every stage this type defines, in lifecycle order. */
    public function stages(): array;

    /* ---------------- Engine ---------------- */

    /**
     * Record the outcome of one unit of competition (a bout, a fixture, a test)
     * and propagate it — advancing winners, updating a table, awarding a grade.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed> what changed, for the in-place UI patch
     */
    public function recordOutcome(ClubEvent $event, int $unitId, array $payload): array;

    /**
     * Run a manager action this type defines — generating a draw, closing
     * weigh-in, publishing a table. Lets a package add its own run-day controls
     * without adding a route or a controller branch per type.
     *
     * Must reject an unknown action and must enforce its own preconditions
     * (the caller has already checked that the actor may manage the event).
     *
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, message: string, data?: array}
     */
    public function performAction(ClubEvent $event, string $action, array $payload = []): array;

    /**
     * Manager actions this type currently offers, for rendering its run-day
     * controls: [['action' => 'generate_draw', 'label' => …, 'icon' => …], …].
     *
     * @return array<int, array<string, mixed>>
     */
    public function availableActions(ClubEvent $event): array;

    /* ---------------- Realtime ---------------- */

    /**
     * Everyone who must be told live when this event changes — user ids.
     *
     * The audience is type-specific: a bout result concerns the division's
     * competitors, the coaches and anyone watching that mat; a belt-test grade
     * concerns the examinee and their guardian. The controller cannot know
     * that, so the package answers it.
     *
     * Packages MUST fan out over MQTT from inside their own write paths
     * (`recordOutcome`, `performAction`, `onEntrantsChanged`) — see CLAUDE.md
     * → "Realtime / MQTT — Always Instant". Publishing is best-effort; the DB
     * stays the source of truth.
     *
     * @param  string  $reason  what changed, so a type can narrow the audience
     * @return array<int, int>
     */
    public function audienceFor(ClubEvent $event, string $reason = 'update'): array;

    /**
     * The points in this event's life at which people are told something —
     * announcement on creation, enrolment opening and closing, a run-day
     * reminder, whatever this type actually has.
     *
     * Type-specific by nature: a championship has a weigh-in and a draw, a belt
     * test has neither, a league has fixture-day reminders instead. Declaring a
     * milestone does not send it; the shared runner decides when each is due,
     * resolves its audience and delivers exactly once.
     *
     * @return array<int, \App\Events\Support\Milestone>
     */
    public function notificationSchedule(ClubEvent $event): array;

    /* ---------------- Outputs ---------------- */

    /**
     * Final standings / medals / grades, derived from the engine's own data.
     *
     * @return array<int, mixed>
     */
    public function results(ClubEvent $event): array;

    /**
     * May a manager type the winners in by hand?
     *
     * False for types that DERIVE their result from their own engine (a
     * bracket, a league table) — for those, hand-entry would let a manager
     * publish a podium that contradicts the recorded matches, so the write
     * path must refuse it server-side.
     */
    public function allowsManualResults(): bool;

    /**
     * Public lifecycle timeline entries (label + date + note + icon).
     *
     * @return array<int, mixed>
     */
    public function timeline(ClubEvent $event): array;

    /**
     * "When am I on, and where?" for one competitor — the only question an
     * athlete has all day. Null when this type has no running order, or when
     * they have nothing left to do.
     *
     * @return array<string, mixed>|null
     */
    public function nextUp(ClubEvent $event, User $athlete): ?array;

    /**
     * Rows for the entrant roster, presented the way THIS type reads them.
     *
     * @return array<int, mixed>
     */
    public function rosterRows(ClubEvent $event): array;

    /* ---------------- Financials ---------------- */

    /**
     * Revenue − expenses for this event, computed the way this type charges.
     *
     * @return array<string, mixed>
     */
    public function finance(ClubEvent $event): array;

    /* ---------------- Display ---------------- */

    /**
     * View names this package ships, keyed by screen and device:
     * ['create' => [...], 'show' => ['mobile' => …, 'desktop' => …], 'run' => …]
     * A null/absent entry means "fall back to the shared generic screen".
     *
     * @return array<string, mixed>
     */
    public function views(): array;

    /**
     * Extra view data this type's screens need beyond the shared event payload.
     *
     * @return array<string, mixed>
     */
    public function viewData(ClubEvent $event, User $viewer): array;

    /**
     * View data for the RUN screen — the bracket, the score sheet, the fixture
     * list. How a division's play is laid out (rounds, mats, bout numbers,
     * which day each phase falls on) is the type's own business, so it builds
     * this itself rather than a shared mapper guessing at it.
     *
     * @return array<string, mixed>
     */
    public function runData(ClubEvent $event, User $viewer): array;

    /**
     * The type's divisions as BRACKETS, for the zoomable bracket screen.
     *
     * A knockout draw is not one sport's idea — a taekwondo weight class, a
     * karate kumite pool and a padel cup are the same picture in different
     * words — so the SHAPE is shared (App\Events\Support\BracketView) while
     * each package decides what fills it. A type that runs no brackets (a belt
     * test, a league) returns an empty array and the screen never offers one.
     *
     * @return array<int, array<string, mixed>> one entry per division
     */
    public function bracketView(ClubEvent $event, User $viewer): array;
}
