<?php

namespace App\Scoreboard\Sports\BrazilianJiuJitsu\Mat;

use App\Models\ClubEvent;
use Illuminate\Database\Eloquent\Model;

/**
 * What one Brazilian Jiu-Jitsu mat is showing right now.
 *
 * ── What is NOT here ────────────────────────────────────────────────────────
 * The score. There is no points column, no advantage column and no penalty
 * column on this row, and that is the design rather than an omission: the score
 * is DERIVED by replaying bjj_match_events (see Ledger). A stored counter beside
 * a ledger is a second truth, and when two truths disagree the wrong one wins
 * silently. `tally()` below is the only way anything reads a score.
 *
 * ── What IS here ────────────────────────────────────────────────────────────
 * Everything a replay cannot answer: which match is loaded, what the screen is
 * showing, where the clock stands, what the two corners are announced as, the
 * rules this mat runs, and the result the officials declared.
 *
 * ── A row, not a cache entry ────────────────────────────────────────────────
 * The sibling packages keep this in the cache because their score is scaffolding
 * that existed for three minutes. This one is already writing a ledger row per
 * command, so there is no write to save — and a row survives a cache flush, a
 * queue restart and a long lunch, which a hall in the middle of a competition
 * cares about far more than it cares about one INSERT.
 *
 * ── The clock ───────────────────────────────────────────────────────────────
 * Stored as `remaining` + `running` + `clock_at`, never as a countdown anything
 * has to tick. Every screen computes its own clock from those three, so a screen
 * that joins late is instantly correct, two screens on one mat cannot drift
 * apart, and nothing runs server-side between the start and the stop.
 */
class MatState extends Model
{
    protected $table = 'bjj_mat_states';

    /** How the mat screen is presenting itself. */
    public const MODE_UPCOMING = 'upcoming';   // the queue — nothing loaded

    public const MODE_VS = 'vs';               // the introduction, before the start

    public const MODE_BOARD = 'board';         // the match itself

    /**
     * Where the MATCH stands.
     *
     * WARNING is deliberately absent: it is derived from the clock
     * (`running && remaining <= warning`), so no two screens can disagree about
     * when the final minute started. So is FINISHED-vs-LIVE colouring — the
     * screens read `status` and the clock together, never a flag somebody set.
     */
    public const STATUSES = [
        'idle', 'live', 'paused', 'review', 'medical', 'overtime',
        'submission', 'dq', 'walkover', 'finished',
    ];

    /** How a match ended, when the score is not the whole answer. */
    public const WIN_METHODS = [
        'submission',  // instant — the match is over the moment it is tapped
        'points',      // the score at regulation time
        'decision',    // level at 0:00; the referee decides (IBJJF)
        'dq',          // disqualification — the fourth penalty, or on the spot
        'advantages',  // the advantage limit reached, where a mat sets one
        'walkover',    // the opponent never came to the mat
        'medical',     // stopped by the doctor
        'forfeit',     // withdrawn
    ];

    /**
     * The rules this mat runs, with IBJJF-as-normally-run defaults.
     *
     * They live in the STATE rather than in a console's inputs because three
     * things read them and they must agree: the console that sets them, the
     * engine that enforces them, and any second console on the same mat. A rule
     * kept in a text box on one laptop is a rule the server never knew about.
     */
    public const DEFAULT_RULES = [
        // Seconds left when the board goes to WARNING. The spec's final 60.
        'warning' => 60.0,
        // The fourth penalty disqualifies; the third warns that it will.
        'penalty_limit' => 4,
        'penalty_warn_at' => 3,
        /*
         * How many advantages END the match in that corner's favour.
         *
         * ZERO — no limit — and that is the default on purpose. There is no
         * such rule in jiu-jitsu as it is normally run: advantages break a tie
         * and nothing more, and a mat that suddenly stopped bouts at four of
         * them would be running a competition nobody entered. A mat that wants
         * the cap turns it on and types the number (Settings → Rules), and the
         * rule then lives in the state like every other, so the console, the
         * wall and the engine cannot disagree about it.
         */
        'advantage_limit' => 0,
        // A tie at 0:00 goes to a referee decision rather than to a draw.
        'referee_decision' => true,
        // A buzzer at 0:00, and a stall countdown of ten seconds.
        'time_up_buzzer' => true,
        'stall_seconds' => 10,
    ];

    protected $fillable = [
        'event_id', 'court', 'mode', 'status', 'match_id', 'match_no', 'stage',
        'division', 'tournament', 'court_label', 'referee', 'ruleset',
        'blue', 'white', 'remaining', 'duration', 'running', 'clock_at',
        'winner', 'win_method', 'win_note', 'awaiting_decision', 'celebration_closed',
        'stall_side', 'stall_until', 'last_event', 'rules', 'theme',
    ];

    protected $casts = [
        'blue' => 'array',
        'white' => 'array',
        'last_event' => 'array',
        'rules' => 'array',
        'running' => 'boolean',
        'awaiting_decision' => 'boolean',
        'celebration_closed' => 'boolean',
        'remaining' => 'float',
        'duration' => 'float',
        'clock_at' => 'datetime',
        'stall_until' => 'datetime',
    ];

    /* ---------------- Reading and writing ---------------- */

    /**
     * This mat's state, creating a cold one from the event's saved settings if
     * the mat has never been used.
     *
     * A cold mat is the first match of the day, and it must come back with the
     * rules an official set in the morning rather than with the factory
     * defaults — so the event's own `scoreboard_settings` seed it.
     */
    /**
     * The window after which an untouched mat is treated as clear.
     *
     * The same 240 minutes Karate and Taekwondo use as their cache TTL — one
     * number, one behaviour, three sports. Longer than any competition day's
     * gap between matches on a live mat (the clock alone updates the row), and
     * short enough that yesterday is never on the wall this morning.
     */
    public const IDLE_AFTER_MINUTES = 240;

    /** Has this mat gone quiet long enough to count as clear? */
    public function isStale(): bool
    {
        return $this->updated_at !== null
            && $this->updated_at->lt(now()->subMinutes(self::IDLE_AFTER_MINUTES));
    }

    public static function forMat(ClubEvent $event, string $court): self
    {
        $state = static::query()
            ->where('event_id', $event->id)
            ->where('court', $court)
            ->first();

        // A mat nobody has touched for hours is CLEAR, not still showing what
        // was last on it.
        //
        // Karate and Taekwondo get this for free: their state is a cache entry
        // with a 240-minute TTL, so an abandoned mat returns to idle on its own
        // overnight. This package keeps state in a TABLE — deliberately, because
        // a jiu-jitsu score is contested and must be replayable — and a table
        // does not expire. The consequence was that a scoreboard paired in the
        // morning opened straight onto the previous evening's introduction, with
        // a match that was already decided: a wall showing a fixture that ended
        // hours ago, and no way to clear it from the floor.
        //
        // The ROW is kept — it is the officiating record and the ledger hangs
        // off it. Only its claim to be what is happening NOW expires, on the
        // same clock as the siblings, so all three sports behave alike.
        if ($state && ! $state->isStale()) {
            return $state;
        }

        $saved = (array) ($event->scoreboard_settings ?? []);
        $rules = array_intersect_key($saved, self::DEFAULT_RULES) + self::DEFAULT_RULES;
        $duration = isset($saved['duration']) ? max(30.0, (float) $saved['duration']) : 300.0;

        $cold = [
            'event_id' => $event->id,
            'court' => $court,
            'mode' => self::MODE_UPCOMING,
            'status' => 'idle',
            'duration' => $duration,
            'remaining' => $duration,
            'rules' => $rules,
            'theme' => (string) ($saved['theme'] ?? 'arena') === 'venue' ? 'venue' : 'arena',
        ];

        // ⚠️ A STALE mat still HAS a row, and (event_id, court) is unique. So the
        // cold state has to be that row emptied out, never a second one: handing
        // back `new static(...)` here meant the next command the console sent
        // tried to INSERT alongside the row it was meant to replace, and every
        // press on that mat failed with a constraint violation until somebody
        // deleted it by hand. Fill the existing model instead — the row, its id
        // and the ledger hanging off it all survive; only its claim to be what
        // is happening NOW is cleared.
        if ($state) {
            return $state->forceFill($cold + [
                'match_id' => null,
                'match_no' => null,
                'stage' => null,
                'division' => null,
                'tournament' => null,
                'court_label' => null,
                'referee' => null,
                'ruleset' => null,
                'blue' => null,
                'white' => null,
                'running' => false,
                'clock_at' => null,
                'winner' => null,
                'win_method' => null,
                'win_note' => null,
                'awaiting_decision' => false,
                'celebration_closed' => false,
                'stall_side' => null,
                'stall_until' => null,
                'last_event' => null,
            ]);
        }

        return new static($cold);
    }

    /**
     * Write the rules back to the EVENT, so they outlive this mat and this
     * session. Every mat on an event shares them — they are the competition's
     * rules, not one table's preference, and two mats running different
     * penalty limits at one championship is a bug rather than a feature.
     */
    public function persistSettings(ClubEvent $event): void
    {
        $settings = ($this->rules ?? self::DEFAULT_RULES) + [
            'duration' => $this->duration,
            'theme' => $this->theme,
        ];

        if (((array) $event->scoreboard_settings) !== $settings) {
            $event->forceFill(['scoreboard_settings' => $settings])->saveQuietly();
        }
    }

    /* ---------------- Derived truths ---------------- */

    /** One rule, with the default behind it — an absent key is never a false. */
    public function rule(string $key): mixed
    {
        return ($this->rules ?? [])[$key] ?? (self::DEFAULT_RULES[$key] ?? null);
    }

    /** The score, replayed. There is no other way to ask. */
    public function tally(): Tally
    {
        return app(Ledger::class)->tally($this->eventModel(), $this->court, $this->match_id);
    }

    /** The match is over — however it ended. */
    public function isFinished(): bool
    {
        return in_array($this->status, ['submission', 'dq', 'walkover', 'finished'], true);
    }

    /**
     * Who won, and it is NOT simply who is ahead.
     *
     * A declared result outranks the score: a submission ends the match however
     * the points stand, a disqualification hands it to the other corner, a
     * walkover is won with no score at all. Everything downstream — the colour
     * on the board, the celebration, the result that goes into the bracket —
     * reads this one method, so the override belongs here and nowhere else.
     *
     * @return 'blue'|'white'|null
     */
    public function winnerSide(?Tally $tally = null): ?string
    {
        if (in_array($this->winner, ['blue', 'white'], true)) {
            return $this->winner;
        }

        return ($tally ?? $this->tally())->leader();
    }

    /**
     * Is the board in its final-minute WARNING?
     *
     * Derived, never stored — see the note on STATUSES.
     */
    public function isWarning(float $remaining): bool
    {
        return $this->status === 'live'
            && $remaining > 0
            && $remaining <= (float) $this->rule('warning');
    }

    /* ---------------- Serialisation ---------------- */

    /**
     * The mat, as every screen reads it.
     *
     * ONE shape for the wall, the overlay and the console, so a second code
     * path can never disagree with the first. The score in it is the replay's
     * answer, computed here rather than trusted from anywhere.
     *
     * @return array<string, mixed>
     */
    public function present(?Tally $tally = null): array
    {
        $tally ??= $this->tally();
        $remaining = $this->settledRemaining();

        return [
            'mode' => $this->mode,
            'status' => $this->status,
            'matchId' => $this->match_id,
            'matchNo' => $this->match_no,
            'stage' => $this->stage,
            'division' => $this->division,
            'tournament' => $this->tournament,
            'courtLabel' => $this->court_label,
            'referee' => $this->referee,
            'ruleset' => $this->ruleset,

            // Announced, not stored: a corner's country goes out as the full
            // name every screen prints, whatever shape it was kept in. A club's
            // `country` column holds an ISO code because the club's public URL
            // is built from it, and 'BH' on a wall screen reads as a fault.
            'blue' => $this->announced((array) $this->blue),
            'white' => $this->announced((array) $this->white),

            // The three counters, separately. Each ladder also moves the
            // points line since 2026-09-12, but it is still ITS OWN number on
            // every screen — the reader is never handed one total. See Tally.
            'score' => $tally->toArray(),

            'remaining' => round($remaining, 1),
            'duration' => round((float) $this->duration, 1),
            'running' => (bool) $this->running,
            // The server's clock at the moment this was written. The client
            // measures elapsed time against its OWN clock from when it received
            // it — a wall screen's clock is frequently wrong, and only the delta
            // matters.
            'at' => now()->toIso8601String(),
            'warning' => $this->isWarning($remaining),
            'finished' => $this->isFinished(),

            'winner' => $this->winnerSide($tally),
            'declared' => in_array($this->winner, ['blue', 'white'], true),
            'winMethod' => $this->win_method,
            'winNote' => $this->win_note,
            'awaitingDecision' => (bool) $this->awaiting_decision,
            'celebrationClosed' => (bool) $this->celebration_closed,

            'lastEvent' => $this->last_event,
            'rules' => ($this->rules ?? []) + self::DEFAULT_RULES,
            'theme' => $this->theme === 'venue' ? 'venue' : 'arena',

            /*
             * The stalling count — ON the board since 2026-09-12.
             *
             * ⚠️ This was deliberately absent until then, and the reason was a
             * real one: a hall that can see a count running knows a penalty is
             * coming before the referee has decided to give one, and a crowd
             * that starts counting down at a man is pressure the referee did
             * not ask for. The organiser asked for it anyway, on the grounds
             * that the count is part of the officiating the hall is entitled
             * to see, and that is their call to make.
             *
             * Sent as a REMAINDER, never as a deadline, for the same reason
             * `remaining` above is: a wall screen's clock is frequently wrong,
             * and only the delta from arrival matters.
             *
             * `side` is 'blue', 'white' or 'both'. Null whenever no count is
             * running, so a board that has never been told about stalling
             * simply draws nothing.
             */
            'stall' => $this->stall_side && $this->stall_until?->isFuture() ? [
                'side' => $this->stall_side,
                'seconds' => round(max(0, $this->stall_until->getTimestampMs() - now()->getTimestampMs()) / 1000, 2),
            ] : null,
        ];
    }

    /**
     * `remaining` brought up to now for a clock that is running.
     *
     * Read-only — the stored value is settled by the engine before it acts, and
     * this is only so a screen fetching the state mid-match is handed the truth
     * rather than the last written number.
     */
    public function settledRemaining(): float
    {
        if (! $this->running || ! $this->clock_at) {
            return max(0.0, (float) $this->remaining);
        }

        $elapsed = max(0, now()->diffInMilliseconds($this->clock_at, absolute: true) / 1000);

        return max(0.0, round((float) $this->remaining - $elapsed, 1));
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

    public function event(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    /**
     * The event this mat belongs to, loaded once.
     *
     * The engine always has the event in hand and passes it; this is the path
     * for a state read on its own (a screen re-fetching after a reconnect),
     * where making the caller find the event first would only invite it to find
     * the wrong one.
     */
    public function eventModel(): ClubEvent
    {
        return $this->event()->getResults() ?? ClubEvent::findOrFail($this->event_id);
    }
}
