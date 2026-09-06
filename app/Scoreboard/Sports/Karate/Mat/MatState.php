<?php

namespace App\Scoreboard\Sports\Karate\Mat;

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
 * screen reboot, a stale JWT), and it has to come back showing the bout that is
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
        /**
         * The official has tucked the celebration away.
         *
         * Lives in the SHARED state rather than in the console's own head, so
         * closing it on the scoring table closes it on the wall too. A screen is
         * not a second opinion about the bout; if the table has stopped
         * celebrating, a board still throwing confetti at the hall is wrong.
         *
         * It says nothing about the RESULT: the bout is still finished and still
         * unfiled. Only the confetti is gone.
         */
        public bool $celebrationClosed = false,
        /**
         * A winner the official DECLARED, overriding the score.
         *
         * 'aka', 'ao' or null. A karate bout does not always go to the higher
         * score: hansoku and shikkaku hand it to the other side however the
         * points stand, kiken and a medical retirement hand it over with no
         * points at all. So who won is a decision the table can make, and the
         * scoreboard has to be able to say so — including "won with fewer
         * points", which is otherwise indistinguishable from a mistake.
         */
        public ?string $winner = null,
        /** Why, from a known vocabulary — see Scoring::WIN_REASONS. */
        public ?string $winReason = null,
        /** The official's own words, when the code alone does not say enough. */
        public ?string $winNote = null,
        /** The last point scored, so the screen can shout it: ['side'=>, 'n'=>, 'ts'=>]. */
        public ?array $lastEvent = null,
        public ?string $at = null,

        /* ---------------- The rules this mat is running ----------------
         *
         * They live in the MAT STATE, not in the console's inputs, because
         * three things read them and they have to agree: the console that sets
         * them, the engine that enforces them, and any second console on the
         * same mat. A rule kept in a text box on one laptop is a rule the
         * server never knew about — which is how a bout ends at eight points
         * on one screen and runs on to the bell on the other.
         *
         * Defaults are WKF as it is normally run, so a mat nobody configures
         * behaves exactly as it did before these existed.
         */

        /** Senshu counts at all — the first unopposed point breaks a tie. */
        public bool $senshuRule = true,
        /** …and it is awarded automatically to whoever scores first. */
        public bool $autoSenshu = true,
        /** The fifth penalty (H) ends the bout, the opponent wins. */
        public bool $winByPenalties = true,
        /** Atoshi baraku: the clock flashes and the alarm sounds near the end. */
        public bool $atoshiWarn = true,
        /** A buzzer at 0:00. */
        public bool $timeUpBuzzer = true,
        /** The point-gap rule is armed. */
        public bool $gapOn = true,
        /** How big the gap has to be. WKF: 8. */
        public int $gap = 8,
        /** Seconds left when the warning starts. WKF atoshi baraku: 15. */
        public float $warning = 15.0,

        /**
         * The bout ended by itself and nobody has said how it ended yet.
         *
         * A gap, a hansoku or the bell stop the clock on their own, and the
         * result they imply is almost always the right one — but "almost" is
         * the problem: WKF ends bouts on things software cannot see, and a
         * console that celebrates the moment the clock hits zero has announced
         * a winner to the hall before any official agreed to one.
         *
         * So an automatic ending parks HERE instead: the bout is finished, the
         * wall holds its celebration, and the scoring table is asked how it
         * ended. Confirming on the score or declaring someone clears this and
         * the celebration runs.
         */
        public bool $awaitingDecision = false,
    ) {}

    /* ---------------- Reading and writing ---------------- */

    public static function key(ClubEvent $event, string $court): string
    {
        return 'karate.mat.'.$event->id.'.'.md5($court);
    }

    /** The settings that belong to the EVENT rather than to one bout. */
    public const SETTINGS = [
        'senshuRule', 'autoSenshu', 'winByPenalties', 'atoshiWarn',
        'timeUpBuzzer', 'gapOn', 'gap', 'warning', 'duration',
    ];

    public static function load(ClubEvent $event, string $court): self
    {
        $raw = Cache::get(self::key($event, $court));

        // A live mat, mid-competition.
        if ($raw) {
            return self::fromArray($raw);
        }

        // A cold one — first bout of the day, or the cache entry aged out
        // between sessions. The SCORE is rightly gone; the RULES are not, so
        // they come back off the event. Without this an official set the gap
        // and the bout length in the morning and found them at their defaults
        // after a long lunch.
        $state = new self;

        foreach ((array) $event->scoreboard_settings as $k => $v) {
            if (in_array($k, self::SETTINGS, true)) {
                $state->$k = is_bool($state->$k) ? (bool) $v : $v;
            }
        }

        // The clock starts where the bout length says it does.
        $state->remaining = $state->duration;

        return $state;
    }

    /**
     * Write the rules back to the event, so they outlive this cache entry.
     *
     * Every mat on an event shares them: they are the competition's rules, not
     * one table's preference, and two mats running different gap rules at the
     * same championship is a bug rather than a feature.
     */
    public function persistSettings(ClubEvent $event): void
    {
        $settings = [];

        foreach (self::SETTINGS as $k) {
            $settings[$k] = $this->$k;
        }

        if (((array) $event->scoreboard_settings) !== $settings) {
            $event->forceFill(['scoreboard_settings' => $settings])->saveQuietly();
        }
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
        // A declared winner outranks the score, and outranks senshu with it.
        // Everything downstream — the colour on the board, the celebration, the
        // result that goes into the bracket — reads these two methods, so the
        // override belongs HERE and nowhere else. Putting it anywhere further
        // down would leave the wall and the record disagreeing.
        if ($this->winner !== null) {
            return $this->winner === 'aka';
        }

        return $this->akaScore > $this->aoScore
            || ($this->akaScore === $this->aoScore && $this->akaSenshu);
    }

    public function aoLeads(): bool
    {
        if ($this->winner !== null) {
            return $this->winner === 'ao';
        }

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
            // Announced, not stored: a corner's country goes out as the full
            // name every screen prints, whatever shape it was kept in. A club's
            // `country` column holds an ISO code because the club's public URL
            // is built from it — 'BH' on a wall screen reads as a fault.
            'aka' => $this->announced($this->aka),
            'ao' => $this->announced($this->ao),
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
            'celebrationClosed' => $this->celebrationClosed,
            'winner' => $this->winner,
            'winReason' => $this->winReason,
            'winNote' => $this->winNote,
            'lastEvent' => $this->lastEvent,
            'at' => $this->at,
            // Sent rather than recomputed on the client: the rule that senshu
            // breaks a tie belongs on this side, so every surface agrees.
            'akaLeads' => $this->akaLeads(),
            'aoLeads' => $this->aoLeads(),
            'boutStatus' => $this->boutStatus(),
            // The rules, so every console on this mat draws the same switches
            // and the wall can flash the clock at the same moment.
            'senshuRule' => $this->senshuRule,
            'autoSenshu' => $this->autoSenshu,
            'winByPenalties' => $this->winByPenalties,
            'atoshiWarn' => $this->atoshiWarn,
            'timeUpBuzzer' => $this->timeUpBuzzer,
            'gapOn' => $this->gapOn,
            'gap' => $this->gap,
            'warning' => $this->warning,
            'awaitingDecision' => $this->awaitingDecision,
        ];
    }

    /**
     * One corner, as a screen should read it.
     *
     * Only the country is touched, and only its presentation: the flag stays
     * the code (it is a filename), and a name we cannot place is passed through
     * as it was given rather than blanked.
     */
    private function announced(array $corner): array
    {
        if (($corner['country'] ?? '') !== '') {
            $corner['country'] = \App\Support\Countries::label($corner['country']);
        }

        return $corner;
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
            celebrationClosed: (bool) ($a['celebrationClosed'] ?? false),
            winner: in_array($a['winner'] ?? null, ['aka', 'ao'], true) ? $a['winner'] : null,
            winReason: isset($a['winReason']) ? (string) $a['winReason'] : null,
            winNote: isset($a['winNote']) ? (string) $a['winNote'] : null,
            lastEvent: $a['lastEvent'] ?? null,
            senshuRule: (bool) ($a['senshuRule'] ?? true),
            autoSenshu: (bool) ($a['autoSenshu'] ?? true),
            winByPenalties: (bool) ($a['winByPenalties'] ?? true),
            atoshiWarn: (bool) ($a['atoshiWarn'] ?? true),
            timeUpBuzzer: (bool) ($a['timeUpBuzzer'] ?? true),
            gapOn: (bool) ($a['gapOn'] ?? true),
            awaitingDecision: (bool) ($a['awaitingDecision'] ?? false),
            gap: (int) ($a['gap'] ?? 8),
            warning: (float) ($a['warning'] ?? 15),
            at: $a['at'] ?? null,
        );
    }
}
