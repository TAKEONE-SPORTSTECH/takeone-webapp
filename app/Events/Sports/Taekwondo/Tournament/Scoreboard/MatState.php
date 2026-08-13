<?php

namespace App\Events\Sports\Taekwondo\Tournament\Scoreboard;

use App\Models\ClubEvent;
use Illuminate\Support\Facades\Cache;

/**
 * What one Taekwondo mat is showing right now, and the score on it.
 *
 * Deliberately NOT a table, for the same reason as Karate's: a bout's running
 * score is worth nothing once the bout is over — the result is recorded through
 * recordOutcome() like every other result in the product, and everything here
 * is scaffolding that existed for six minutes. A row per point would be a write
 * per keypress on the busiest path in the hall, for data nobody reads after.
 *
 * It cannot be memory-only either: a wall screen reloads (a browser crash, a Pi
 * reboot, a stale JWT) and has to come back showing the bout still happening in
 * front of it. So it lives in the cache — survives a reload, expires on its own,
 * costs no schema.
 *
 * ── Why this is not Karate's MatState with different words ──────────────────
 * A WKF bout is one continuous three minutes with a single score. A WT kyorugi
 * match is a best-of series: each ROUND has its own score and its own gam-jeom
 * count, both reset between rounds, and the match is won by winning rounds, not
 * by the larger total. Two athletes can therefore finish 5–20 down on aggregate
 * and still win 2–1. Everything below follows from that:
 *
 *   · akaScore/aoScore are THIS ROUND, and are cleared at each round break.
 *   · akaRounds/aoRounds are the match, and are the thing that decides it.
 *   · A round can end early — on the point gap, or on the gam-jeom ceiling.
 *   · A level match after the last round goes to a golden round, where the
 *     first point of any kind ends everything.
 *
 * ── The timer ───────────────────────────────────────────────────────────────
 * Stored as `remaining` + `running` + `at`, never as a countdown something has
 * to tick. Every screen computes its own clock from those three, so a screen
 * that joins late is instantly correct, two screens on a mat never drift, and
 * nothing runs server-side between the start and the stop. `at` is the server's
 * clock when the state was written; the client measures elapsed against its OWN
 * clock from when it received it — a wall screen's clock is frequently wrong,
 * and only the delta matters.
 */
class MatState
{
    /** How the mat screen is presenting itself. */
    public const MODE_UPCOMING = 'upcoming';   // the queue — no bout loaded

    public const MODE_VS = 'vs';               // the introduction, before the first round

    public const MODE_SCOREBOARD = 'scoreboard'; // the match itself

    /** Where the match is within its own shape. Drives the control's phase line. */
    public const PHASE_ROUND = 'round';        // a round is on

    public const PHASE_REST = 'rest';          // between rounds

    public const PHASE_GOLDEN = 'golden';      // sudden death, first point wins

    /** A match is minutes long; this only has to outlive a reload. */
    private const TTL_MINUTES = 240;

    /**
     * What a scoring action is worth, and what it is called when it lands.
     *
     * These are the five buttons on the approved control, and the values are
     * WT's: the reward scales with how hard the technique is to land, which is
     * why a turning head kick is worth five punches.
     */
    public const ACTIONS = [
        'punch' => ['value' => 1, 'label' => 'PUNCH'],
        'body' => ['value' => 2, 'label' => 'BODY KICK'],
        'head' => ['value' => 3, 'label' => 'HEAD KICK'],
        'turn_body' => ['value' => 4, 'label' => 'TURNING BODY'],
        'turn_head' => ['value' => 5, 'label' => 'TURNING HEAD'],
    ];

    /**
     * A round ends the moment one athlete leads by this much.
     *
     * The point-gap rule exists so a hopeless round is stopped rather than
     * played out on someone who is being hurt.
     */
    public const POINT_GAP = 12;

    /**
     * Gam-jeom that end the MATCH — the opponent wins by punitive declaration.
     *
     * Each gam-jeom already gives the opponent a point as it is given; this is
     * the separate ceiling on how many one athlete may accumulate. Reaching it
     * is PUN: the match is over there and then, whatever the rounds say.
     *
     * Counted across the whole match, not per round — a ceiling that emptied
     * every round could never be reached in a two-minute round and would make
     * the rule decorative.
     */
    public const GAM_JEOM_LIMIT = 5;

    public function __construct(
        public string $mode = self::MODE_UPCOMING,
        public ?int $matchId = null,
        /** What the bout IS — printed on both the introduction and the board. */
        public ?string $matchNo = null,
        public ?string $stage = null,
        public ?string $division = null,
        /** "Senior · Kyorugi" — the age grade and discipline, under the weight. */
        public ?string $category = null,
        /** Header text the operator may override on the control page. */
        public ?string $tournament = null,
        public ?string $courtLabel = null,
        /** Announced on the introduction. Empty hides the chip rather than
         *  printing a name nobody appointed. */
        public ?string $referee = null,
        public array $aka = [],
        public array $ao = [],
        /** THIS ROUND's score and gam-jeom. Both reset at every round break. */
        public int $akaScore = 0,
        public int $aoScore = 0,
        public int $akaGam = 0,
        public int $aoGam = 0,
        /** Rounds won — the match itself. */
        public int $akaRounds = 0,
        public int $aoRounds = 0,
        public int $round = 1,
        public int $rounds = 3,
        public string $phase = self::PHASE_ROUND,
        public float $remaining = 120.0,
        public float $duration = 120.0,
        /** The break between rounds, in seconds. */
        public float $restDuration = 60.0,
        public bool $running = false,
        /** The current ROUND is over. The match may or may not be. */
        public bool $finished = false,
        /** The MATCH is over — every round that will be fought has been. */
        public bool $matchOver = false,
        /**
         * Why the round stopped by itself: 'gamjeom' | 'gap' | 'golden', or
         * null when it ran its clock. Purely so the screens can SAY it — a
         * round that ends on a rule the operator did not press needs to
         * explain itself, or it reads as the console having frozen.
         */
        public ?string $endReason = null,
        /**
         * Winner by punitive declaration — set when a competitor reaches the
         * gam-jeom ceiling. Outranks the rounds, because PUN ends the match
         * wherever the series happens to stand.
         */
        public ?string $punWinner = null,
        /** The last point scored, so the screen can shout it. */
        public ?array $lastEvent = null,
        /**
         * What the operator has done, newest first — the control's undo list.
         * Capped, because it is carried in every payload to every screen.
         */
        public array $log = [],
        public ?string $at = null,
    ) {}

    /** How many rounds a side must win to take the match. */
    public function roundsToWin(): int
    {
        return intdiv($this->rounds, 2) + 1;
    }

    /* ---------------- Reading and writing ---------------- */

    public static function key(ClubEvent $event, string $court): string
    {
        return 'taekwondo.mat.'.$event->id.'.'.md5($court);
    }

    public static function load(ClubEvent $event, string $court): self
    {
        $raw = Cache::get(self::key($event, $court));

        return $raw ? self::fromArray($raw) : new self;
    }

    public function save(ClubEvent $event, string $court): self
    {
        $this->at = now()->toIso8601String();
        Cache::put(self::key($event, $court), $this->toArray(), now()->addMinutes(self::TTL_MINUTES));

        return $this;
    }

    public static function forget(ClubEvent $event, string $court): void
    {
        Cache::forget(self::key($event, $court));
    }

    /* ---------------- Derived truths ---------------- */

    /**
     * Who has won the MATCH, or null while it is still live.
     *
     * Rounds decide it, never the aggregate score — see the class note.
     */
    public function matchWinner(): ?string
    {
        // PUN first: five gam-jeom ends the match at 0–0 just as surely as at
        // 1–1, so the rounds cannot be consulted before it.
        if ($this->punWinner !== null) {
            return $this->punWinner;
        }

        $target = $this->roundsToWin();

        if ($this->akaRounds >= $target) {
            return 'aka';
        }

        if ($this->aoRounds >= $target) {
            return 'ao';
        }

        return null;
    }

    /**
     * Who has won the round being fought, or null when it is level.
     *
     * The gam-jeom ceiling outranks the score: an athlete who reaches it loses
     * the round however far ahead they were.
     */
    public function roundWinner(): ?string
    {
        if ($this->akaGam >= self::GAM_JEOM_LIMIT) {
            return 'ao';
        }

        if ($this->aoGam >= self::GAM_JEOM_LIMIT) {
            return 'aka';
        }

        if ($this->akaScore > $this->aoScore) {
            return 'aka';
        }

        if ($this->aoScore > $this->akaScore) {
            return 'ao';
        }

        return null;
    }

    /** True when one side is far enough ahead that the round stops here. */
    public function pointGapReached(): bool
    {
        return abs($this->akaScore - $this->aoScore) >= self::POINT_GAP;
    }

    /**
     * The line the control prints above the clock.
     *
     * A round that ended on a rule says WHICH rule. Without it the console sat
     * on "Round 1" after five gam-jeom had closed the round, so the operator
     * had no way to tell the difference between a stopped round and a stuck
     * page.
     */
    public function phaseLabel(): string
    {
        return match (true) {
            $this->matchOver => match ($this->endReason) {
                'golden' => 'Match over · golden point',
                'gamjeom' => 'Match over · 5 gam-jeom (PUN)',
                'gap' => 'Match over · point gap',
                default => 'Match over',
            },
            $this->phase === self::PHASE_GOLDEN => 'Golden round',
            $this->phase === self::PHASE_REST => match ($this->endReason) {
                'gamjeom' => 'Rest · round on 5 gam-jeom',
                'gap' => 'Rest · round on point gap',
                default => 'Rest',
            },
            default => 'Round '.$this->round,
        };
    }

    /* ---------------- Serialisation ---------------- */

    public function toArray(): array
    {
        return [
            'mode' => $this->mode,
            'matchId' => $this->matchId,
            'matchNo' => $this->matchNo,
            'stage' => $this->stage,
            'division' => $this->division,
            'category' => $this->category,
            'tournament' => $this->tournament,
            'courtLabel' => $this->courtLabel,
            'referee' => $this->referee,
            'aka' => $this->aka,
            'ao' => $this->ao,
            'akaScore' => $this->akaScore,
            'aoScore' => $this->aoScore,
            'akaGam' => $this->akaGam,
            'aoGam' => $this->aoGam,
            'akaRounds' => $this->akaRounds,
            'aoRounds' => $this->aoRounds,
            'round' => $this->round,
            'rounds' => $this->rounds,
            'phase' => $this->phase,
            'remaining' => $this->remaining,
            'duration' => $this->duration,
            'restDuration' => $this->restDuration,
            'running' => $this->running,
            'finished' => $this->finished,
            'matchOver' => $this->matchOver,
            'endReason' => $this->endReason,
            'punWinner' => $this->punWinner,
            'lastEvent' => $this->lastEvent,
            'log' => $this->log,
            'at' => $this->at,
            // Sent rather than recomputed on the client. The rules that rounds
            // beat aggregate score, and that the gam-jeom ceiling beats both,
            // belong on this side — so every surface agrees about who is
            // winning without any of them owning a copy of the rulebook.
            'roundWinner' => $this->roundWinner(),
            'matchWinner' => $this->matchWinner(),
            'roundsToWin' => $this->roundsToWin(),
            'phaseLabel' => $this->phaseLabel(),
        ];
    }

    public static function fromArray(array $a): self
    {
        return new self(
            mode: $a['mode'] ?? self::MODE_UPCOMING,
            matchId: $a['matchId'] ?? null,
            matchNo: $a['matchNo'] ?? null,
            stage: $a['stage'] ?? null,
            division: $a['division'] ?? null,
            category: $a['category'] ?? null,
            tournament: $a['tournament'] ?? null,
            courtLabel: $a['courtLabel'] ?? null,
            referee: $a['referee'] ?? null,
            aka: $a['aka'] ?? [],
            ao: $a['ao'] ?? [],
            akaScore: (int) ($a['akaScore'] ?? 0),
            aoScore: (int) ($a['aoScore'] ?? 0),
            akaGam: (int) ($a['akaGam'] ?? 0),
            aoGam: (int) ($a['aoGam'] ?? 0),
            akaRounds: (int) ($a['akaRounds'] ?? 0),
            aoRounds: (int) ($a['aoRounds'] ?? 0),
            round: max(1, (int) ($a['round'] ?? 1)),
            rounds: max(1, (int) ($a['rounds'] ?? 3)),
            phase: $a['phase'] ?? self::PHASE_ROUND,
            remaining: (float) ($a['remaining'] ?? 120),
            duration: (float) ($a['duration'] ?? 120),
            restDuration: (float) ($a['restDuration'] ?? 60),
            running: (bool) ($a['running'] ?? false),
            finished: (bool) ($a['finished'] ?? false),
            matchOver: (bool) ($a['matchOver'] ?? false),
            endReason: $a['endReason'] ?? null,
            punWinner: $a['punWinner'] ?? null,
            lastEvent: $a['lastEvent'] ?? null,
            log: $a['log'] ?? [],
            at: $a['at'] ?? null,
        );
    }
}
