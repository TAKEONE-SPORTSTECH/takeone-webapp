<?php

namespace App\Events\Sports\Karate\Tournament\Scoreboard;

use App\Models\ClubEvent;
use Illuminate\Support\Facades\Cache;

/**
 * What one mat is showing right now, and the score on it.
 *
 * Deliberately NOT a table. A bout's running score is worth nothing once the
 * bout is over — the result is recorded through recordOutcome() like every other
 * result in the product, and everything here is scaffolding that existed for
 * three minutes. Writing a row per point would be a write per keypress on the
 * busiest path in the hall, for data nobody reads afterwards.
 *
 * But it cannot be memory-only either: a wall screen reloads (a browser crash, a
 * Pi reboot, a stale JWT), and it has to come back showing the bout that is
 * still happening in front of it rather than a blank board. So it lives in the
 * cache — survives a reload, expires on its own, costs no schema.
 *
 * ── The timer ───────────────────────────────────────────────────────────────
 * Stored as `remaining` + `running` + `at`, never as a countdown that something
 * has to tick. Every screen computes its own clock from those three, so:
 *   · a screen that joins late is instantly correct
 *   · two screens on the same mat never drift apart
 *   · nothing has to run server-side between the start and the stop
 * `at` is the server's clock at the moment the state was written, and the client
 * measures elapsed time against its OWN clock from when it received it — a wall
 * screen's clock is frequently wrong, and only the delta matters.
 */
class MatState
{
    /** How the mat screen is presenting itself. */
    public const MODE_UPCOMING = 'upcoming';   // the queue — no bout loaded

    public const MODE_VS = 'vs';               // the introduction, before hajime

    public const MODE_SCOREBOARD = 'scoreboard'; // the bout itself

    /** A bout is minutes long; this only has to outlive a reload. */
    private const TTL_MINUTES = 240;

    /** WKF penalties, in ascending severity. Five is the whole ladder. */
    public const PENALTIES = ['C1', 'C2', 'C3', 'HC', 'H'];

    /** What a point is called when it lands, by value. */
    public const CALLOUTS = [1 => 'YUKO', 2 => 'WAZA-ARI', 3 => 'IPPON'];

    public function __construct(
        public string $mode = self::MODE_UPCOMING,
        public ?int $matchId = null,
        /** What the bout IS — printed on both the introduction and the board. */
        public ?string $matchNo = null,
        public ?string $stage = null,
        public ?string $division = null,
        /** Header text the operator may override on the control page. */
        public ?string $tournament = null,
        public ?string $courtLabel = null,
        /** Announced on the introduction. Empty hides the chip rather than
         *  printing a name nobody appointed. */
        public ?string $referee = null,
        public array $aka = [],
        public array $ao = [],
        public int $akaScore = 0,
        public int $aoScore = 0,
        public int $akaPen = 0,
        public int $aoPen = 0,
        public bool $akaSenshu = false,
        public bool $aoSenshu = false,
        public float $remaining = 180.0,
        public float $duration = 180.0,
        public bool $running = false,
        public bool $finished = false,
        /** The last point scored, so the screen can shout it: ['side'=>, 'n'=>, 'ts'=>]. */
        public ?array $lastEvent = null,
        public ?string $at = null,
    ) {}

    /* ---------------- Reading and writing ---------------- */

    public static function key(ClubEvent $event, string $court): string
    {
        return 'karate.mat.'.$event->id.'.'.md5($court);
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
     * Who is ahead — and senshu is why this is not just a comparison.
     *
     * Senshu is awarded for the first unopposed point, and it decides a bout
     * that ends level. So a screen cannot colour a winner by score alone, and
     * neither can this.
     */
    public function akaLeads(): bool
    {
        return $this->akaScore > $this->aoScore
            || ($this->akaScore === $this->aoScore && $this->akaSenshu);
    }

    public function aoLeads(): bool
    {
        return $this->aoScore > $this->akaScore
            || ($this->akaScore === $this->aoScore && $this->aoSenshu);
    }

    /** 'Hajime' while it runs, 'Yame' when stopped, 'Time' when it is over. */
    public function boutStatus(): string
    {
        return $this->finished ? 'Time' : ($this->running ? 'Hajime' : 'Yame');
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
            'tournament' => $this->tournament,
            'courtLabel' => $this->courtLabel,
            'referee' => $this->referee,
            'aka' => $this->aka,
            'ao' => $this->ao,
            'akaScore' => $this->akaScore,
            'aoScore' => $this->aoScore,
            'akaPen' => $this->akaPen,
            'aoPen' => $this->aoPen,
            'akaSenshu' => $this->akaSenshu,
            'aoSenshu' => $this->aoSenshu,
            'remaining' => $this->remaining,
            'duration' => $this->duration,
            'running' => $this->running,
            'finished' => $this->finished,
            'lastEvent' => $this->lastEvent,
            'at' => $this->at,
            // Sent rather than recomputed on the client: the rule that senshu
            // breaks a tie belongs on this side, so every surface agrees.
            'akaLeads' => $this->akaLeads(),
            'aoLeads' => $this->aoLeads(),
            'boutStatus' => $this->boutStatus(),
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
            tournament: $a['tournament'] ?? null,
            courtLabel: $a['courtLabel'] ?? null,
            referee: $a['referee'] ?? null,
            aka: $a['aka'] ?? [],
            ao: $a['ao'] ?? [],
            akaScore: (int) ($a['akaScore'] ?? 0),
            aoScore: (int) ($a['aoScore'] ?? 0),
            akaPen: (int) ($a['akaPen'] ?? 0),
            aoPen: (int) ($a['aoPen'] ?? 0),
            akaSenshu: (bool) ($a['akaSenshu'] ?? false),
            aoSenshu: (bool) ($a['aoSenshu'] ?? false),
            remaining: (float) ($a['remaining'] ?? 180),
            duration: (float) ($a['duration'] ?? 180),
            running: (bool) ($a['running'] ?? false),
            finished: (bool) ($a['finished'] ?? false),
            lastEvent: $a['lastEvent'] ?? null,
            at: $a['at'] ?? null,
        );
    }
}
