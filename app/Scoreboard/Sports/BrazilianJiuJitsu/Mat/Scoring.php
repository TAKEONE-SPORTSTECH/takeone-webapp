<?php

namespace App\Scoreboard\Sports\BrazilianJiuJitsu\Mat;

use App\Events\EventTypeRegistry;
use App\Events\Sports\BrazilianJiuJitsu\Tournament\RunningOrder;
use App\Events\Support\Cameras\CameraFleet;
use App\Events\Support\MatchEventLog;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventMatch;
use App\Models\EventOfficial;
use App\Members\Models\User;
use App\Sports\Combat\BeltRank;
use Illuminate\Support\Facades\DB;

/**
 * Applies what an official does at the table to the state of a jiu-jitsu mat.
 *
 * Every rule lives here rather than in the console's JavaScript, for one reason:
 * the console is not the only thing that can be wrong. A second official opening
 * the page, a laptop reconnecting mid-match, a wall screen catching up after a
 * dropped socket — all of them must arrive at the SAME score, and they only do
 * if it is computed in one place and pushed out.
 *
 * The console therefore sends INTENTIONS ("blue passed the guard"), never
 * results ("blue now has five"). It does not even send the value: what a guard
 * pass is worth is decided by Ledger::POINT_SOURCES on this side, so a client
 * that has fallen behind — or anything else POSTing at the endpoint — cannot
 * mint a five-point mount.
 *
 * ── Audit, not erase ────────────────────────────────────────────────────────
 * Nothing takes a point off. A correction APPENDS a reversal naming the row it
 * undoes, with a reason the operator had to type, and the score is re-derived
 * by replaying what survives. See Ledger.
 */
class Scoring
{
    /**
     * The SHARED result vocabulary that `event_matches.win_reason` holds.
     *
     * Not this package's own words — those are MatState::WIN_METHODS, which is
     * what the ledger and the screens speak. This is the smaller, older list the
     * whole platform reads off a finished match row (the podium, the profile,
     * the export), and Advancement whitelists against it before writing. A
     * jiu-jitsu submission has no entry here because the shared column cannot
     * express one; outcomeReason() maps each method into the kind of ending
     * this list CAN express, and the exact method stays in the package ledger
     * where it means something.
     */
    public const WIN_REASONS = [
        'points', 'hansoku', 'shikkaku', 'kiken', 'medical', 'no_show', 'other',
    ];

    /** Every command an official can issue. Anything else is rejected. */
    public const COMMANDS = [
        'load',       // put a match on the screen (mode → vs)
        'start',      // the referee starts it — also leaves the VS introduction
        'pause',      // stop the clock
        'resume',     // start it again without leaving the board
        'board',      // end the introduction, show the scoreboard, clock untouched
        'intro',      // put the introduction back — the other half of 'board'
        'point',      // {side, source} — the VALUE comes from the source, here
                      //   …or {side, value}, priced against Ledger::POINT_VALUES
        'deduct',     // {side, ladder, value} — take something back: reverse the
                      //   matching entry, or (points only) append a correction
        'advantage',  // {side}
        'penalty',    // {side, source}
        'reverse',    // {ledger_id, reason} — append a reversal, never a delete
        'time',       // {remaining} — the official corrects the clock
        'duration',   // {seconds} — the match length
        'review',     // referee review: the clock freezes, the wall says so
        'medical',    // medical stoppage — neutral surface, no dedicated hue
        'overtime',   // into overtime, with its own clock
        'stall',      // {side, phase: start|cancel|apply|award, points} — the referee's own countdown
        'end',        // {winner, method, note} — declare how it ended
        'decision',   // {winner} — the referee decision a level match needs
        'reset',      // back to a fresh match, same competitors
        'clear',      // take the match off the screen entirely
        'commit',     // WRITE the result, advance the bracket, call the next match
        'dismiss',    // put the celebration away — on the table AND the wall
        'celebrate',  // bring it back
        'resync',     // tell every screen on this mat to reload itself
        'bell',       // the clock reached zero — settle it and see what that means
        'corner',     // {side, name, club, country, flag} — fix what is announced
        'meta',       // {tournament, division, matchNo, courtLabel, stage, referee, ruleset}
        'rules',      // {warning, penalty_limit, penalty_warn_at, referee_decision, …}
        'theme',      // {theme: arena|venue} — dark hall, or a bright one
    ];

    /**
     * Commands that only mean something with a match on the mat.
     *
     * `load` and `clear` change WHICH match is there; `duration`, `meta`,
     * `rules` and `theme` prepare the mat before one arrives. Those are the six
     * that may run on an empty mat.
     */
    private const NEEDS_MATCH = [
        'start', 'pause', 'resume', 'point', 'advantage', 'penalty', 'reverse', 'deduct',
        'time', 'review', 'medical', 'overtime', 'stall', 'end', 'decision',
        'reset', 'commit', 'board', 'intro', 'bell',
    ];

    /**
     * Commands that may not touch a match that is already over.
     *
     * A finished match is a decision the officials have made. Correcting one is
     * `reverse` (which appends, and is allowed), or `reset` (which starts it
     * again, deliberately) — not another point quietly landing on a result the
     * hall has already been shown.
     */
    private const REFUSED_WHEN_FINISHED = ['point', 'advantage', 'penalty', 'start', 'resume', 'stall'];

    /**
     * Commands that put a result on the RECORD, and are refused until the
     * competition day has been started.
     *
     * ── Why this exists ────────────────────────────────────────────────────
     *
     * A mat is set up and rehearsed days before an event: an official loads a
     * bout, runs the clock, presses a few points, learns the instrument. All of
     * that is wanted. What must not happen is a rehearsal reaching the bracket
     * — and until 2026-09-10 nothing stopped it. The Victory BJJ Championship
     * had 509 officiating rows against 52 of its bouts a week before its date,
     * from exactly this. Nothing had been committed, by luck rather than by
     * design: one press of Record on a finished test bout would have advanced a
     * competitor in a draw nobody had drawn yet.
     *
     * ── What it does and does not stop ─────────────────────────────────────
     *
     * Scoring, the clock, the wall board, the introduction, penalties, the
     * stalling count — all still work with the event unstarted, because that is
     * the rehearsal. What is refused is the three acts that OUTLIVE the mat:
     *
     *   commit    writes the winner into the bracket and advances the draw
     *   end       declares how a bout finished, which is what commit files
     *   decision  the referee's ruling on a level bout, the same
     *
     * The rehearsal is still cleared with `reset`, which is allowed — it is how
     * a mat is put back after a test.
     *
     * The gate is `started_at`: an organiser pressing Start on the event's own
     * console. Not the date, and not the start TIME — a competition that begins
     * late is still not under way, and one that begins early is. That is a
     * person's decision and it already has a button.
     */
    private const NEEDS_EVENT_STARTED = ['end', 'decision', 'commit'];

    /**
     * Endings that NAME somebody, and therefore cannot be filed without one.
     *
     * `points` is the only method that may arrive with no winner attached: it
     * means "award it on the score", and the score already says who. Every
     * other method is an officials' declaration ABOUT a person — this athlete
     * submitted, that one was disqualified, the doctor stopped it for one of
     * them — and a declaration with nobody in it is not a result.
     *
     * Refused rather than quietly downgraded. An `end` carrying a method and no
     * winner used to fall through to the branch below, which cleared the method
     * to 'points' and awarded the bout on the score: a submission filed into the
     * bracket as a points decision, with nothing anywhere saying so. The console
     * makes this hard to do (see the End modal) and this makes it impossible.
     */
    private const METHODS_NEEDING_WINNER = ['submission', 'decision', 'dq', 'walkover', 'medical', 'forfeit', 'advantages'];

    public function __construct(private BeltRank $belts, private Ledger $ledger) {}

    /**
     * Who is at the table for THIS request.
     *
     * Set once by apply() and read by every row it writes, so an operator is
     * never smuggled in through a payload field a client could set. Null only
     * where the platform genuinely has no user — which the endpoints do not
     * allow: a paired scoring table resolves to the organiser who paired it.
     */
    private ?User $operator = null;

    /**
     * Apply one command and hand back the new state.
     *
     * @param  array<string, mixed>  $payload
     */
    public function apply(ClubEvent $event, string $court, string $command, array $payload = [], ?User $operator = null): MatState
    {
        abort_unless(in_array($command, self::COMMANDS, true), 422);

        $this->operator = $operator;

        $state = MatState::forMat($event, $court);
        $state->setRelation('event', $event);

        // Nothing that acts on a MATCH may run when no match is on the mat.
        // The console should not offer these and does not, but the console is a
        // convenience and the endpoint is the contract: a second laptop, a stale
        // tab or a replayed request must all be refused here.
        if (in_array($command, self::NEEDS_MATCH, true) && ! $state->match_id) {
            throw new \RuntimeException(__('scoreboard::bjj_messages.no_match_loaded'));
        }

        if (in_array($command, self::REFUSED_WHEN_FINISHED, true) && $state->isFinished()) {
            throw new \RuntimeException(__('scoreboard::bjj_messages.match_over'));
        }

        // Nothing reaches the RECORD before the day has been started. See
        // NEEDS_EVENT_STARTED — the mat is fully usable for rehearsal, but a
        // rehearsal cannot file a result.
        if (in_array($command, self::NEEDS_EVENT_STARTED, true) && ! $event->hasStarted()) {
            throw new \RuntimeException(__('scoreboard::bjj_messages.event_not_started'));
        }

        // The clock is stored as "remaining as of a moment"; before anything
        // else touches it, bring it up to now. Otherwise a pause five seconds
        // after a start would record the time as though nothing had elapsed.
        $this->settleClock($state);

        // What the ledger will record for this command, filled in by whichever
        // branch runs. A command that is not a scoring act leaves it null and
        // still gets a context row — the log is the whole match, not only its
        // points.
        $entry = null;

        match ($command) {
            'load' => $this->load($event, $court, $state, (int) ($payload['match_id'] ?? 0), $payload),
            'start' => $this->start($state),
            'pause' => $this->pause($state, 'paused'),
            'resume' => $this->resume($state),
            'board' => $state->mode = MatState::MODE_BOARD,
            'intro' => $this->intro($state),
            'point' => $entry = $this->point($state, $payload),
            'deduct' => $entry = $this->deduct($event, $court, $state, $payload),
            'advantage' => $entry = $this->advantage($state, $payload),
            'penalty' => $entry = $this->penalty($state, $payload),
            'reverse' => $entry = $this->reverse($event, $court, $state, $payload),
            'time' => $this->time($state, $payload),
            'duration' => $this->duration($state, $payload, $event),
            'review' => $this->pause($state, 'review'),
            'medical' => $this->pause($state, 'medical'),
            'overtime' => $this->overtime($state, $payload),
            'stall' => $entry = $this->stall($state, $payload),
            'end' => $this->declareEnd($state, $payload),
            'decision' => $this->decision($state, $payload),
            'reset' => $this->reset($state),
            'clear' => $this->clear($state),
            'commit' => $this->commit($event, $court, $state),
            // Neither of these touches the match: they decide whether the hall
            // is still being shown a celebration for a result that is already
            // decided and not yet filed.
            'dismiss' => $state->celebration_closed = true,
            'celebrate' => $state->celebration_closed = false,
            // A no-op on purpose. It changes nothing and saves the state
            // unchanged; what makes it useful is the reload the controller
            // publishes afterwards, which is the one recovery a screen with no
            // keyboard has.
            'resync' => null,
            /*
             * Also a no-op — and that is the whole point of it.
             *
             * The clock is stored as "remaining as of a moment", and it is
             * settled at the top of THIS method before any command runs. So a
             * match whose time has expired is not actually over on the server
             * until something asks: settleClock() is what calls autoEnd(), and
             * nothing was asking. The buzzer sounds in the hall (the board
             * plays it off its own local clock) while the state still says the
             * match is live, and the table only found out when it next pressed
             * something.
             *
             * A console whose own clock reaches zero sends this once. It carries
             * no opinion about the result — it just lets the engine notice the
             * bell, which either finishes the match or raises a referee
             * decision, exactly as it would have done later. Idempotent, so a
             * second console at the same mat sending it too costs nothing.
             */
            'bell' => null,
            'corner' => $this->cornerEdit($state, $payload),
            'meta' => $this->meta($state, $payload),
            'rules' => $this->rules($state, $payload, $event),
            'theme' => $this->theme($state, $payload, $event),
        };

        // The clock rides on the state, so it must be written before the row
        // that cites it.
        $state->clock_at = now();
        $state->save();

        // Context row for everything that was not itself a scoring act. The
        // scoring branches above have already appended theirs, because they had
        // to know the row's id to hand it back for an UNDO.
        if ($entry === null && $this->worthRecording($command)) {
            $this->ledger->append(
                event: $event,
                court: $court,
                action: $command,
                matchId: $state->match_id,
                operatorId: $this->operator?->id,
                clockRemaining: $state->match_id ? round((float) $state->remaining, 2) : null,
                clockDuration: $state->match_id ? round((float) $state->duration, 2) : null,
                payload: $this->publicPayload($payload),
            );
        }

        $tally = $this->ledger->tally($event, $court, $state->match_id);

        // …and the same command in the platform's SHARED officiating timeline,
        // which things OUTSIDE this package read: the bout video's highlights
        // bar and the officiating sheet are both derived from it. Deliberate
        // duplication with two different jobs — the package ledger is the
        // authority a score is replayed from, this is the platform-wide
        // witness. It cannot throw by design, so it can never stop a mat.
        MatchEventLog::record(
            event: $event,
            court: $court,
            sport: 'bjj',
            command: $command,
            payload: $this->publicPayload($payload),
            matchId: $state->match_id,
            // Points only. Advantages and penalties are NOT points and must
            // stay three numbers on every screen — see Tally.
            scoreA: $tally->bluePoints,
            scoreB: $tally->whitePoints,
            clockRemaining: $state->match_id ? round((float) $state->remaining, 2) : null,
            clockDuration: $state->match_id ? round((float) $state->duration, 2) : null,
        );

        // The cameras on this mat, if any, are told the same thing the hall is:
        // a match was loaded, started, or is over. It cannot throw; a mat must
        // never stop because a phone did.
        CameraFleet::observe(
            event: $event,
            court: $court,
            command: $this->cameraCommand($command),
            matchId: $state->match_id,
            bout: [
                'number' => $state->match_no,
                'stage' => $state->stage,
                // The fleet's vocabulary is red/blue because every other combat
                // sport has a red corner. Jiu-jitsu's white corner travels in
                // the 'red' slot; nothing downstream reads it as a colour.
                'red' => $state->white['name'] ?? null,
                'blue' => $state->blue['name'] ?? null,
            ],
        );

        return $state;
    }

    /* ---------------- The clock ---------------- */

    /** Charge elapsed time against a running clock, and stop it at zero. */
    private function settleClock(MatState $state): void
    {
        if (! $state->running || ! $state->clock_at) {
            return;
        }

        $elapsed = max(0, now()->diffInMilliseconds($state->clock_at, absolute: true) / 1000);
        $state->remaining = round(max(0, (float) $state->remaining - $elapsed), 1);

        if ($state->remaining <= 0) {
            $state->running = false;

            // The bell is an automatic ending: it stops the match, and then the
            // table is asked how it ended. Guarded so settling the clock twice
            // does not re-ask a question already answered.
            if (! $state->isFinished() && ! $state->awaiting_decision) {
                $this->autoEnd($state);
            }
        }
    }

    /**
     * The match ended on its own — the bell, or the top of the penalty ladder.
     *
     * It stops there. The result is not announced to the hall and the
     * celebration does not run until an official at the table says how it
     * ended, because the score is not always who won: a disqualification hands
     * it the other way, and a level match on the bell is not a result at all —
     * IBJJF sends that one to a referee decision, and the wall holds on PAUSED
     * until somebody enters it.
     */
    private function autoEnd(MatState $state, ?string $winner = null, ?string $method = null): void
    {
        $tally = $this->ledger->tally($state->eventModel(), $state->court, $state->match_id);
        $level = $winner === null && $tally->leader() === null;

        $state->running = false;
        $state->awaiting_decision = true;
        // Held: no confetti until an official has agreed there is a result.
        $state->celebration_closed = true;
        $state->last_event = null;

        /*
         * A level match at 0:00 is not finished, it is WAITING. The public
         * screen holds PAUSED, and the console surfaces REFEREE DECISION.
         *
         * ⚠️ The method is used as the STATUS only when it is also a status.
         * Three of them are both words — `submission`, `dq`, `walkover` — and
         * this line quietly relied on that: `advantages` is a method and not a
         * status, so writing it here left a match that isFinished() did not
         * recognise, the wall with no status word for it, and the Match result
         * card that opens off `finished` never opening. Everything else lands
         * on the general word, which is what it means.
         */
        $ending = $method ?: 'finished';
        $state->status = $level && $state->rule('referee_decision')
            ? 'paused'
            : (in_array($ending, MatState::STATUSES, true) ? $ending : 'finished');

        if ($winner !== null) {
            $state->winner = $winner;
            $state->win_method = $method ?: 'dq';
        }

        // A match that ended from the introduction — an opponent who never came
        // — must take the introduction down, or the celebration paints
        // underneath it and the hall sees two athletes about to fight a match
        // that is already over.
        if ($state->mode === MatState::MODE_VS) {
            $state->mode = MatState::MODE_BOARD;
        }
    }

    /* ---------------- Commands ---------------- */

    /**
     * Put a match on the mat.
     *
     * Everything the screens announce is assembled HERE, once, from the draw
     * and the athletes' records — the console never types a competitor's name,
     * club, flag or belt, so what the hall sees cannot disagree with what the
     * system holds.
     */
    private function load(ClubEvent $event, string $court, MatState $state, int $matchId, array $payload): void
    {
        $match = EventMatch::where('event_id', $event->id)->with('category:id,name,weight_class')->find($matchId);

        if (! $match) {
            throw new \RuntimeException(__('scoreboard::bjj_messages.match_not_found'));
        }

        // Keyed by REGISTRATION id, not user id: a_competitor_id/b_competitor_id
        // name the entry in THIS event, which is what carries the weigh-in
        // weight and the belt presented.
        $registrations = ClubEventRegistration::where('event_id', $event->id)
            ->whereIn('id', array_filter([$match->a_competitor_id, $match->b_competitor_id]))
            ->with(['user:id,full_name,name,gender,birthdate,nationality,height_cm,profile_picture,profile_picture_is_public',
                'user.skillAcquisitions:id,user_id,proficiency_level,start_date',
                'user.memberClubs:id,club_name,logo,country',
                // The club they compete FOR, which is what the screens print.
                'representingTenant:id,club_name,logo,country'])
            ->get()->keyBy('id');

        // The clubs the organiser WROTE DOWN for this event (`event_clubs`) —
        // teams the platform has never met, which is most of a real
        // competition. Without them the introduction named a club for the few
        // entrants attached to a tenant and nobody else.
        $written = \App\Events\Support\EntryClub::forMany($event, $registrations->keys()->all());

        $state->mode = MatState::MODE_VS;
        $state->status = 'idle';
        $state->match_id = $match->id;
        $state->match_no = $match->match_no !== null ? (string) $match->match_no : null;
        $state->stage = (string) ($match->phase ?: $match->round ?: '');
        $state->division = (string) ($match->category?->weight_class ?: $match->category?->name ?: '');
        // Whoever is actually appointed. Nobody appointed hides the chip — a
        // hall screen naming a referee who was never assigned is worse than one
        // that names none.
        $state->referee = $this->officiatingName($event);
        // Blue on the LEFT is side 'a', white on the RIGHT is side 'b' — the
        // same order on the console and on the wall, always.
        $state->blue = $this->corner($match, 'a', $registrations, $written);
        $state->white = $this->corner($match, 'b', $registrations, $written);

        // A fresh match: nothing carries over — except the clock LENGTH, which
        // is a setting rather than something the last match did.
        $state->duration = match (true) {
            array_key_exists('seconds', $payload) => max(30.0, round((float) $payload['seconds'])),
            array_key_exists('minutes', $payload) => max(30.0, (float) $payload['minutes'] * 60),
            default => max(30.0, (float) ($state->duration ?: 300)),
        };
        $state->remaining = $state->duration;
        $state->running = false;
        $state->winner = null;
        $state->win_method = null;
        $state->win_note = null;
        $state->awaiting_decision = false;
        $state->celebration_closed = false;
        $state->stall_side = null;
        $state->stall_until = null;
        $state->last_event = null;

        // The SCORE is not reset, because there is nothing to reset: it is
        // derived from the ledger rows of THIS match, and a match that has just
        // walked on has none.
    }

    /**
     * The appointed referee, as the introduction announces them.
     *
     * Abbreviated to an initial and a surname ("S. Petrov"), which is both the
     * convention on a competition screen and what the layout is sized for.
     * Nobody appointed returns null and the chip is hidden.
     */
    private function officiatingName(ClubEvent $event): ?string
    {
        $full = $event->officials()
            ->where('role', EventOfficial::ROLE_JURY)
            ->with('user:id,full_name,name')
            ->first()?->user?->full_name;

        if (! $full) {
            return null;
        }

        $parts = preg_split('/\s+/', trim($full), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return count($parts) < 2
            ? ($parts[0] ?? null)
            : mb_substr($parts[0], 0, 1).'. '.end($parts);
    }

    /**
     * One corner, as the screens announce it.
     *
     * A competitor's photo obeys profile_picture_is_public — a hall screen is a
     * publication, and a member who has not made their picture public does not
     * get their face projected onto a wall.
     */
    private function corner(EventMatch $match, string $side, $registrations, array $written = []): array
    {
        $id = $match->{$side.'_competitor_id'};
        $reg = $id ? $registrations->get($id) : null;
        $user = $reg?->user;
        $club = $reg?->competingClub();
        $belt = $user ? $this->belts->for($user, $reg) : null;

        // A tenant wins when the entry names one — it is the stronger claim and
        // it carries a country and a page. Otherwise the club the organiser
        // wrote down stands in, name and crest alike.
        $named = $club
            ? ['name' => $club->club_name, 'logo' => $club->logo ? file_url($club->logo) : null, 'country' => $club->country]
            : ($id ? ($written[$id] ?? null) : null);

        return [
            'name' => $match->{$side.'_name'} ?: ($user?->full_name ?? $user?->name ?? ''),
            'club' => $named['name'] ?? '',
            // The club's country, never the person's passport — they are here
            // as their club, and that is what the hall is told. But when there
            // IS no club, no flag told a reader nothing while the athlete's
            // nationality was on file all along, so countryCode() is the one
            // place that whole rule lives (and the override an official typed
            // at this table still wins over both).
            'country' => ($named['country'] ?? null) ?: ($match->{$side.'_country'} ?: ($reg?->countryCode() ?: '')),
            'flag' => strtolower((string) ($match->{$side.'_country'} ?: ($named['country'] ?? null) ?: ($reg?->countryCode() ?: ''))) ?: null,
            'logo' => $named['logo'] ?? null,
            // The event's OWN photo wins, then the member's profile picture if
            // they published it. The first was uploaded by an organiser FOR this
            // competition; the second is somebody's private picture and keeps
            // its gate.
            'photo' => $reg?->photo
                ? file_url($reg->photo)
                : (($user?->profile_picture && $user->profile_picture_is_public)
                    ? file_url($user->profile_picture)
                    : null),
            'fallback' => \App\Support\Avatar::placeholder($user?->gender),
            'belt' => $belt['label'] ?? null,
            'age' => $user?->birthdate ? $user->birthdate->age : null,
            'height' => $user?->height_cm ?: null,
            'weight' => $reg?->weight ? (float) $reg->weight : null,
        ];
    }

    private function start(MatState $state): void
    {
        if ($state->remaining <= 0) {
            return;
        }

        // Starting is also what ends the introduction — the VS screen animates
        // itself out and the scoreboard is already behind it.
        $state->mode = MatState::MODE_BOARD;
        $state->status = 'live';
        $state->running = true;
    }

    /** Out of a pause, a review or a medical stoppage, back to live. */
    private function resume(MatState $state): void
    {
        if ($state->remaining <= 0) {
            return;
        }

        $state->mode = MatState::MODE_BOARD;
        $state->status = $state->status === 'overtime' ? 'overtime' : 'live';
        $state->running = true;
        // A resumed match is no longer waiting to be explained.
        $state->awaiting_decision = false;
    }

    /**
     * Stop the clock, and say WHY — a pause, a referee review, a medical
     * stoppage. All three freeze the same clock and differ only in what the
     * hall is told, which is why they are one method rather than three.
     */
    private function pause(MatState $state, string $status): void
    {
        $state->running = false;
        $state->status = $status;
        $state->last_event = null;
        // The referee's private countdown does not survive a stoppage.
        $state->stall_side = null;
        $state->stall_until = null;
    }

    /**
     * Back to the introduction.
     *
     * Refused while the clock is RUNNING, and that refusal is the point: the
     * introduction covers the score, and a match in progress whose scoreboard
     * has been replaced by two portraits is a hall that cannot see what is
     * happening. Stop the clock first, deliberately.
     */
    private function intro(MatState $state): void
    {
        if ($state->running) {
            throw new \RuntimeException(__('scoreboard::bjj_messages.intro_running'));
        }

        $state->mode = MatState::MODE_VS;
    }

    /**
     * A point, worth what the server says it is worth. Two ways in, one price
     * list, and neither of them is the browser's arithmetic.
     *
     *  · {source}  an ACTION — takedown, guard pass, mount. Priced from
     *              Ledger::POINT_SOURCES, and the word reaches the record and
     *              the wall board's callout. This is what the hall board, an
     *              MCP tool and the React console still send.
     *  · {value}   an AMOUNT — 2, 3 or 4, checked against Ledger::POINT_VALUES.
     *              What the scoring table sends since its grid stopped naming
     *              actions (see POINT_VALUES for why). The row then carries no
     *              source, and the log reads "+2" rather than "TAKEDOWN".
     *
     * `source` is tried FIRST and wins, so every existing caller is untouched:
     * a payload carrying a valid action is priced exactly as it always was.
     */
    private function point(MatState $state, array $payload): MatchEvent
    {
        $side = $this->side($payload);

        if (! $side) {
            throw new \RuntimeException(__('scoreboard::bjj_messages.unknown_side'));
        }

        $source = (string) ($payload['source'] ?? '');

        if (isset(Ledger::POINT_SOURCES[$source])) {
            $value = Ledger::POINT_SOURCES[$source];
        } else {
            $value = (int) ($payload['value'] ?? 0);
            $source = null;

            if (! in_array($value, Ledger::POINT_VALUES, true)) {
                throw new \RuntimeException(__('scoreboard::bjj_messages.unknown_point'));
            }
        }

        $state->last_event = ['kind' => 'point', 'side' => $side, 'value' => $value,
            'source' => $source, 'ts' => (int) (microtime(true) * 1000)];

        return $this->record($state, 'point', $side, $value, $source, $payload);
    }

    /** The three things a corner can be given, and therefore can have taken back. */
    private const LADDERS = ['point', 'advantage', 'penalty'];

    /**
     * The stalling count's third side: neither man is working.
     *
     * A value of `stall_side`, beside 'blue' and 'white' — not a second count.
     * Applying it gives a stalling penalty to each corner; awarding points out
     * of it is refused, because there is nobody it would go to.
     */
    public const STALL_BOTH = 'both';

    /**
     * Take something back off a corner — the minus half of every scoring
     * control: -2/-3/-4 under the points, and a -1 beside each ladder.
     *
     * ── What it does ───────────────────────────────────────────────────────
     *
     *  · There IS a standing entry of that kind on that side → it is REVERSED.
     *    Both rows survive, the original is struck through in the log, and the
     *    total is the replay of the rest. This is the ordinary case: a mis-tap,
     *    corrected within seconds, by the person who made it.
     *  · There is not, and it was POINTS → a CORRECTION row is appended and the
     *    replay subtracts it. Points can reach a corner from a stalling award
     *    or from the other console at the same mat, and a table correcting a
     *    total it did not build must still be able to.
     *  · There is not, and it was a LADDER → refused, in words. An advantage
     *    and a penalty are only ever ONE ROW EACH on this mat, so "no advantage
     *    to take back" is the literal truth and a correction row invented for
     *    it would be a count nobody could trace to an act. The penalty ladder
     *    especially: it ends matches, and a ladder that can be lowered by a
     *    row pointing at nothing is a disqualification that can be argued away.
     *
     * Either way it is an APPEND. Nothing is deleted and nothing is edited, so
     * the match stays arguable afterwards — which is the only reason any of
     * this is a ledger rather than six integers.
     *
     * No reason is asked for, unlike `reverse`. The reach is the point: these
     * buttons exist so a wrong number comes off the wall NOW, at the same
     * distance as the button that put it there. A correction that wants a
     * sentence typed into it is one the table makes after the bout instead, and
     * the score log is still where that is done.
     */
    private function deduct(ClubEvent $event, string $court, MatState $state, array $payload): MatchEvent
    {
        $side = $this->side($payload);

        if (! $side) {
            throw new \RuntimeException(__('scoreboard::bjj_messages.unknown_side'));
        }

        $ladder = (string) ($payload['ladder'] ?? 'point');

        if (! in_array($ladder, self::LADDERS, true)) {
            throw new \RuntimeException(__('scoreboard::bjj_messages.unknown_point'));
        }

        // A point is taken back BY WORTH; an advantage and a penalty count one
        // each and have no worth to name. The amount is checked against the
        // server's own list either way — see Ledger::POINT_VALUES.
        $value = $ladder === 'point' ? (int) ($payload['value'] ?? 0) : 1;

        if ($ladder === 'point' && ! in_array($value, Ledger::POINT_VALUES, true)) {
            throw new \RuntimeException(__('scoreboard::bjj_messages.unknown_point'));
        }

        // A correction is not a callout: nothing is shouted on the wall for it.
        // (The same rule `reverse` keeps — the hall is shown scores, not the
        // officials' housekeeping.)
        $state->last_event = null;

        $reason = __('scoreboard::bjj_messages.correction_reason');
        $target = $this->ledger->lastStanding(
            $event, $court, $state->match_id, $side, $ladder,
            $ladder === 'point' ? $value : null,
        );

        if ($target) {
            return $this->ledger->append(
                event: $event,
                court: $court,
                action: 'reverse',
                matchId: $state->match_id,
                side: $side,
                reversesId: $target->id,
                reason: $reason,
                operatorId: $this->operator?->id,
                clockRemaining: round((float) $state->remaining, 2),
                clockDuration: round((float) $state->duration, 2),
                payload: ['undoes' => $ladder, 'source' => $target->source, 'deduct' => $value],
            );
        }

        // A ladder with nothing on it is a refusal, not a negative count.
        if ($ladder !== 'point') {
            throw new \RuntimeException(__('scoreboard::bjj_messages.ctl_nothing_to_deduct'));
        }

        return $this->ledger->append(
            event: $event,
            court: $court,
            action: Ledger::CORRECTION,
            matchId: $state->match_id,
            side: $side,
            value: $value,
            reason: $reason,
            operatorId: $this->operator?->id,
            clockRemaining: round((float) $state->remaining, 2),
            clockDuration: round((float) $state->duration, 2),
            payload: ['deduct' => $value],
        );
    }

    /**
     * An advantage. One row, no value on the row — and since 2026-09-12 it is
     * worth a point as well as a place on its own ladder.
     *
     * The point is NOT written here and there is no second row for it. The
     * ledger records the ACT; Ledger::tally() decides what an act is worth when
     * it replays, which is why taking an advantage back is an ordinary reversal
     * that removes the ladder entry and the point together. See Tally.
     */
    private function advantage(MatState $state, array $payload): MatchEvent
    {
        $side = $this->side($payload);

        if (! $side) {
            throw new \RuntimeException(__('scoreboard::bjj_messages.unknown_side'));
        }

        $state->last_event = ['kind' => 'advantage', 'side' => $side, 'ts' => (int) (microtime(true) * 1000)];

        // An advantage may be given for an all-but-completed action; recording
        // WHICH one is optional and is kept when the console offers it.
        $source = isset(Ledger::POINT_SOURCES[(string) ($payload['source'] ?? '')])
            ? (string) $payload['source'] : null;

        $row = $this->record($state, 'advantage', $side, 0, $source, $payload);

        /*
         * The top of the advantage ladder ENDS the match, in that corner's
         * favour — the mirror image of the penalty ladder below, which ends it
         * against them.
         *
         * Off unless a mat has asked for it (MatState::DEFAULT_RULES →
         * advantage_limit is 0, meaning no limit), because jiu-jitsu as it is
         * normally run has no such rule. Enforced HERE rather than on the
         * console for the same reason the penalty limit is: the console is not
         * the only thing that can score a mat, and a rule that lives in one
         * browser is a rule the other console does not have.
         *
         * Counted off the REPLAY, never off a running total, so an advantage
         * that was taken back does not count towards the cap.
         */
        $limit = (int) $state->rule('advantage_limit');

        if ($limit > 0) {
            $tally = $this->ledger->tally($state->eventModel(), $state->court, $state->match_id);
            $count = $side === 'blue' ? $tally->blueAdvantages : $tally->whiteAdvantages;

            if ($count >= $limit) {
                $this->autoEnd($state, winner: $side, method: 'advantages');
            }
        }

        return $row;
    }

    /**
     * A penalty, the ladder it sits on, and a point to the other man.
     *
     * Like an advantage, the point is not written here — Ledger::tally() credits
     * the OPPONENT one point for every standing penalty when it replays, so a
     * reversal takes the penalty and that point away in one act.
     *
     * The third warns that the next one disqualifies; the fourth does. Enforced
     * HERE rather than on the console, because the console is not the only
     * thing that can score a mat and a rule that lives in one browser is a rule
     * the other console does not have.
     */
    private function penalty(MatState $state, array $payload): MatchEvent
    {
        $side = $this->side($payload);

        if (! $side) {
            throw new \RuntimeException(__('scoreboard::bjj_messages.unknown_side'));
        }

        $source = in_array((string) ($payload['source'] ?? ''), Ledger::PENALTY_REASONS, true)
            ? (string) $payload['source'] : 'other';

        $row = $this->record($state, 'penalty', $side, 0, $source, $payload);

        $tally = $this->ledger->tally($state->eventModel(), $state->court, $state->match_id);
        $count = $side === 'blue' ? $tally->bluePenalties : $tally->whitePenalties;

        $state->last_event = [
            'kind' => 'penalty',
            'side' => $side,
            'source' => $source,
            'count' => $count,
            // The board's four-second notice reads "⚠ PENALTY — BLUE · …". The
            // warning that the NEXT one disqualifies is for the console; the
            // hall is not told what is about to happen to somebody.
            'ts' => (int) (microtime(true) * 1000),
        ];

        // The top of the ladder is a disqualification: it hands the match to the
        // other corner however the points stand. Ended here, on the server, so
        // both consoles and the wall agree and the reason is on the record.
        if ($count >= (int) $state->rule('penalty_limit')) {
            $this->autoEnd($state, winner: $side === 'blue' ? 'white' : 'blue', method: 'dq');
        }

        return $row;
    }

    /**
     * Take something back — by APPENDING, never by deleting.
     *
     * The reason is required and it reaches the record. That is the difference
     * between a scoreboard somebody can argue with afterwards and one they
     * cannot: the reversal, its reason and the row it undid all survive, and the
     * score is simply what replaying the rest says.
     */
    private function reverse(ClubEvent $event, string $court, MatState $state, array $payload): MatchEvent
    {
        $target = $this->ledger->reversible($event, $court, $state->match_id, (int) ($payload['ledger_id'] ?? 0));

        if (! $target) {
            throw new \RuntimeException(__('scoreboard::bjj_messages.nothing_to_reverse'));
        }

        $reason = trim((string) ($payload['reason'] ?? ''));

        if ($reason === '') {
            throw new \RuntimeException(__('scoreboard::bjj_messages.reason_required'));
        }

        // A correction is not a callout: nothing is shouted on the wall for it.
        $state->last_event = null;

        return $this->ledger->append(
            event: $event,
            court: $court,
            action: 'reverse',
            matchId: $state->match_id,
            side: $target->side === 'a' ? 'blue' : 'white',
            reversesId: $target->id,
            reason: $reason,
            operatorId: $this->operator?->id,
            clockRemaining: round((float) $state->remaining, 2),
            clockDuration: round((float) $state->duration, 2),
            payload: ['undoes' => $target->action, 'source' => $target->source],
        );
    }

    /**
     * The referee's stalling countdown — start, cancel, or apply.
     *
     * PRIVATE to the console while it runs. The public board learns nothing
     * about it: no countdown, no stall row, nothing that would tell a hall a
     * penalty is coming before the referee has decided to give one. Applying it
     * produces an ORDINARY penalty with `source: stalling`, which is what the
     * board's four-second notice then reads.
     */
    private function stall(MatState $state, array $payload): ?MatchEvent
    {
        $phase = (string) ($payload['phase'] ?? 'start');

        if ($phase === 'cancel') {
            $state->stall_side = null;
            $state->stall_until = null;

            return null;
        }

        if ($phase === 'apply') {
            $side = $state->stall_side;

            if (! in_array($side, [self::STALL_BOTH, 'blue', 'white'], true)) {
                throw new \RuntimeException(__('scoreboard::bjj_messages.no_stall_running'));
            }

            $state->stall_side = null;
            $state->stall_until = null;

            /*
             * Applied through the ordinary penalty path, so the ladder, the
             * disqualification at the top of it, and the board's notice all
             * behave exactly as they do for any other penalty.
             *
             * A DOUBLE stall applies two of them, one per corner — which is
             * precisely the two presses the referee would otherwise make by
             * hand, and deliberately nothing more than that. No new kind of
             * row, no new rule: if both corners happen to reach the
             * disqualification limit on the same count, the second one decides
             * it, exactly as it would have done pressed by hand in that order.
             */
            if ($side !== self::STALL_BOTH) {
                return $this->penalty($state, ['side' => $side, 'source' => 'stalling']);
            }

            $this->penalty($state, ['side' => 'blue', 'source' => 'stalling']);
            $row = $this->penalty($state, ['side' => 'white', 'source' => 'stalling']);

            // Both penalties, announced once. Each of the two calls above left
            // its own corner in last_event; the hall is told about the pair,
            // because two notices four seconds long for one decision is the
            // board talking over itself.
            $state->last_event = [
                'kind' => 'penalty',
                'side' => self::STALL_BOTH,
                'source' => 'stalling',
                'count' => 0,
                'ts' => (int) (microtime(true) * 1000),
            ];

            return $row;
        }

        // The other way out of a stalling count: points to the corner that was
        // being stalled against, rather than a penalty against the one that
        // stalled. Asked for 2026-09-10 — it is the second half of the
        // threshold card in drafts/Score Control Board.html.
        //
        // The console sends the AMOUNT and nothing else, and the amount is held
        // to Ledger::STALL_AWARDS here as well as at the endpoint: the console
        // is a convenience, this class is the rule. The row lands as an
        // ordinary `point` so the replay, the wall's notice and the score log
        // all treat it as what it is — points on the board — distinguishable
        // only by its source.
        if ($phase === 'award') {
            $side = $state->stall_side;

            // There is no "other corner" to award when the count was against
            // both of them, so this way out simply does not exist for a double
            // stall — said plainly rather than through the no-count message,
            // which would be a lie: a count IS running.
            if ($side === self::STALL_BOTH) {
                throw new \RuntimeException(__('scoreboard::bjj_messages.stall_award_needs_one_corner'));
            }

            if (! in_array($side, ['blue', 'white'], true)) {
                throw new \RuntimeException(__('scoreboard::bjj_messages.no_stall_running'));
            }

            $value = (int) ($payload['points'] ?? 0);

            if (! in_array($value, Ledger::STALL_AWARDS, true)) {
                // Read as a sentence, not as a list dump: an official sees
                // this on a toast at a mat, mid-bout.
                $allowed = Ledger::STALL_AWARDS;
                $last = array_pop($allowed);

                throw new \RuntimeException(__('scoreboard::bjj_messages.bad_stall_award', [
                    'values' => $allowed
                        ? implode(', ', $allowed).' '.__('scoreboard::bjj_messages.or').' '.$last
                        : (string) $last,
                ]));
            }

            // To the OPPOSITE corner. Never read off the payload: the console
            // knows which side stalled, but the state is the one that decides.
            $to = $side === 'blue' ? 'white' : 'blue';

            $state->stall_side = null;
            $state->stall_until = null;

            $state->last_event = ['kind' => 'point', 'side' => $to, 'value' => $value,
                'source' => Ledger::STALL_AWARD_SOURCE, 'ts' => (int) (microtime(true) * 1000)];

            return $this->record($state, 'point', $to, $value, Ledger::STALL_AWARD_SOURCE, $payload);
        }

        /*
         * Starting a count — against one man, or against BOTH.
         *
         * A double stall is the case where neither of them is engaging, and it
         * is ONE count, not two running side by side: the mat has always held
         * a single `stall_side`/`stall_until` pair because a referee watches a
         * situation rather than two clocks, and 'both' is a third value of that
         * same field rather than a second count beside it.
         *
         * Starting either kind replaces whatever was running, which is the
         * existing rule and the right one — a referee who decides the
         * situation has changed is not asking for two answers.
         */
        $side = strtolower((string) ($payload['side'] ?? '')) === self::STALL_BOTH
            ? self::STALL_BOTH
            : $this->side($payload);

        if (! $side) {
            throw new \RuntimeException(__('scoreboard::bjj_messages.unknown_side'));
        }

        $state->stall_side = $side;
        $state->stall_until = now()->addSeconds(max(3, min(60, (int) $state->rule('stall_seconds'))));

        return null;
    }

    /** The official corrects the clock. Never past the match length. */
    private function time(MatState $state, array $payload): void
    {
        $state->remaining = round(max(0, min((float) $state->duration, (float) ($payload['remaining'] ?? 0))), 1);
        $state->running = false;
    }

    /** Match length, in seconds. Resets the clock with it, and is a SETTING. */
    private function duration(MatState $state, array $payload, ClubEvent $event): void
    {
        $state->duration = max(30.0, round((float) ($payload['seconds'] ?? 300)));
        $state->remaining = $state->duration;
        $state->running = false;
        $state->persistSettings($event);
    }

    /**
     * Into overtime, with a clock of its own.
     *
     * A separate status rather than a re-used 'live', because the hall is told
     * which it is watching and the two are not the same match.
     */
    private function overtime(MatState $state, array $payload): void
    {
        $seconds = max(30.0, min(600.0, round((float) ($payload['seconds'] ?? 180))));

        $state->duration = $seconds;
        $state->remaining = $seconds;
        $state->running = false;
        $state->status = 'overtime';
        $state->mode = MatState::MODE_BOARD;
        $state->awaiting_decision = false;
        $state->winner = null;
        $state->win_method = null;
        $state->last_event = null;
    }

    /**
     * The match is over — and this is where WHO won is settled, not just WHEN.
     *
     * Two ways in. Without a winner in the payload it behaves as the score says:
     * the clock stops and the leader on the IBJJF tiebreak ladder has it. WITH
     * one, the officials have declared it — a submission, a disqualification, a
     * walkover, a doctor's call — and that outranks the points, which is the
     * whole reason it exists.
     *
     * Either way NOTHING is filed yet: commit is a separate, deliberate act.
     */
    private function declareEnd(MatState $state, array $payload): void
    {
        $winner = in_array($payload['winner'] ?? null, ['blue', 'white'], true) ? $payload['winner'] : null;
        $method = in_array($payload['method'] ?? null, MatState::WIN_METHODS, true) ? $payload['method'] : null;

        $tally = $this->ledger->tally($state->eventModel(), $state->court, $state->match_id);

        // A method that names somebody, with nobody named. See the note on
        // METHODS_NEEDING_WINNER — this is the one that used to become a points
        // win in silence.
        if ($winner === null && in_array($method, self::METHODS_NEEDING_WINNER, true)) {
            throw new \RuntimeException(__('scoreboard::bjj_messages.end_method_needs_winner', [
                'method' => __('scoreboard::bjj_messages.method_'.$method),
            ]));
        }

        // A level match cannot simply be "ended": somebody has to be given it,
        // and IBJJF says the referee decides. Refused here rather than filed as
        // a draw the bracket cannot carry.
        if ($winner === null && $tally->leader() === null) {
            throw new \RuntimeException(__('scoreboard::bjj_messages.end_level'));
        }

        $state->running = false;
        $state->last_event = null;
        $state->stall_side = null;
        $state->stall_until = null;
        $state->awaiting_decision = false;
        // An official has said how it ended — THIS is what releases the
        // celebration, on the table and, through the same flag, on the wall.
        $state->celebration_closed = false;

        // Declaring a winner takes the side named. NOT declaring one clears any
        // previous declaration rather than leaving it standing: an official who
        // ends the match again on the score has changed their mind, and a stale
        // override would quietly file the wrong athlete.
        $state->winner = $winner;
        $state->win_method = $winner === null ? 'points' : ($method ?: 'decision');

        // The three that are their own state on the board, so the hall is told
        // what it just watched rather than a generic FINISHED.
        $state->status = match ($state->win_method) {
            'submission' => 'submission',
            'dq' => 'dq',
            'walkover' => 'walkover',
            default => 'finished',
        };

        // Trimmed and capped to what the column holds. Never trusted as markup —
        // every screen renders it as text.
        $note = trim((string) ($payload['note'] ?? ''));
        $state->win_note = $note === '' ? null : mb_substr($note, 0, 200);

        if ($state->mode === MatState::MODE_VS) {
            $state->mode = MatState::MODE_BOARD;
        }
    }

    /** The referee decision a level match at 0:00 needs. Always a declaration. */
    private function decision(MatState $state, array $payload): void
    {
        $this->declareEnd($state, [
            'winner' => $payload['winner'] ?? null,
            'method' => 'decision',
            'note' => $payload['note'] ?? null,
        ]);
    }

    /**
     * A fresh match, same competitors.
     *
     * The LEDGER is not touched — it never is. What the reset does is close the
     * old attempt and open a new one, and because the score is derived from
     * rows tagged with this match id, the honest way to do that is to reverse
     * what is standing: every surviving scoring row gets a reversal naming this
     * reset, so the record still says what happened and the total returns to
     * nought.
     */
    private function reset(MatState $state): void
    {
        $event = $state->eventModel();

        if ($state->match_id) {
            $reason = __('scoreboard::bjj_messages.reset_reason');

            DB::transaction(function () use ($event, $state, $reason) {
                foreach ($this->standingRows($event, $state) as $row) {
                    $this->ledger->append(
                        event: $event,
                        court: $state->court,
                        action: 'reverse',
                        matchId: $state->match_id,
                        side: $row->side === 'a' ? 'blue' : 'white',
                        reversesId: $row->id,
                        reason: $reason,
                        operatorId: $this->operator?->id,
                        clockRemaining: round((float) $state->remaining, 2),
                        clockDuration: round((float) $state->duration, 2),
                        payload: ['undoes' => $row->action, 'reset' => true],
                    );
                }
            });
        }

        $state->remaining = $state->duration;
        $state->running = false;
        $state->status = $state->match_id ? 'idle' : 'idle';
        $state->celebration_closed = false;
        $state->awaiting_decision = false;
        $state->last_event = null;
        $state->stall_side = null;
        $state->stall_until = null;
        // …and nothing DECLARED either. A declaration that outlived the reset
        // meant for it would be filed by commit() with the score no longer
        // having a say.
        $state->winner = null;
        $state->win_method = null;
        $state->win_note = null;
    }

    /**
     * The scoring rows of this match that still stand.
     *
     * "Still stand" is the ledger's question, not this file's — a reversal can
     * itself be reversed now, so whether a row counts depends on the whole
     * chain above it. Asked of Ledger::standing() so there is ONE answer and
     * the reset below cannot disagree with the score on the wall.
     */
    private function standingRows(ClubEvent $event, MatState $state)
    {
        return $this->ledger->standing($event, $state->court, $state->match_id)
            ->filter(fn (MatchEvent $r) => in_array(
                $r->action, ['point', 'advantage', 'penalty', Ledger::CORRECTION], true
            ));
    }

    /**
     * The result leaves the scoreboard and becomes a fact.
     *
     * Everything up to here has been scaffolding. This is the one command that
     * writes a RESULT: it hands the match to the package's own recordOutcome(),
     * which records the scores, carries the winner into their next match, closes
     * the division's podium when a final lands, tells the two athletes and calls
     * whoever is now due on this mat. Nothing about a result is reimplemented
     * here — a second path to writing one is a second set of rules to get wrong.
     *
     * Then the mat moves on by itself: the next match in the running order is
     * introduced, or the screen goes back to the queue when the mat is done.
     */
    private function commit(ClubEvent $event, string $court, MatState $state): void
    {
        $tally = $this->ledger->tally($event, $court, $state->match_id);
        $winner = $state->winnerSide($tally);

        if ($winner === null) {
            throw new \RuntimeException(__('scoreboard::bjj_messages.commit_level'));
        }

        if (! $state->isFinished() && ! in_array($state->win_method, MatState::WIN_METHODS, true)) {
            throw new \RuntimeException(__('scoreboard::bjj_messages.commit_unfinished'));
        }

        app(EventTypeRegistry::class)->for($event)->recordOutcome($event, (int) $state->match_id, [
            // Blue is side 'a', white is side 'b', throughout.
            'winner' => $winner === 'blue' ? 'a' : 'b',
            'a_score' => (string) $tally->bluePoints,
            'b_score' => (string) $tally->whitePoints,
            // Why, when it was not simply the points. This is the part that has
            // to outlive the mat: "how did the athlete with two points win
            // that" is asked later, when nobody is standing at the table.
            'win_reason' => $this->outcomeReason($state->win_method),
            'win_note' => $state->win_note,
            'status' => 'done',
        ]);

        // Straight into the next one — the SAME queue the wall board announces,
        // so the table never loads a different match from the one the hall has
        // just been told to expect.
        $order = new RunningOrder;
        $next = $order->matQueue($event, $court)
            ->first(fn (EventMatch $m) => $m->id !== $state->match_id && $order->isRunnable($m));

        if ($next) {
            $this->load($event, $court, $state, $next->id, ['seconds' => $state->duration]);

            return;
        }

        $this->clear($state);
    }

    /**
     * This package's win method in the SHARED result vocabulary.
     *
     * `event_matches.win_reason` is a column the whole platform reads — the
     * podium, the profile, the export — and it holds the words the first two
     * combat packages agreed on. A jiu-jitsu submission is not in that list, so
     * it is mapped rather than smuggled in: the shared column records the KIND
     * of ending it can express, and the exact method stays in this package's
     * own ledger where it means something.
     */
    private function outcomeReason(?string $method): ?string
    {
        return match ($method) {
            // The score, or a clean finish. `advantages` is the score too — the
            // cap only decides WHEN it stopped, not what won it.
            null, 'points', 'submission', 'advantages' => null,
            'dq' => 'hansoku',
            'walkover' => 'no_show',
            'medical' => 'medical',
            'forfeit' => 'kiken',
            default => 'other',
        };
    }

    private function clear(MatState $state): void
    {
        $state->mode = MatState::MODE_UPCOMING;
        $state->status = 'idle';
        $state->match_id = null;
        $state->match_no = $state->stage = $state->division = null;
        $state->blue = [];
        $state->white = [];
        $state->remaining = $state->duration;
        $state->running = false;
        $state->winner = null;
        $state->win_method = null;
        $state->win_note = null;
        $state->awaiting_decision = false;
        $state->celebration_closed = false;
        $state->stall_side = null;
        $state->stall_until = null;
        $state->last_event = null;
    }

    /**
     * Correct what a corner is ANNOUNCED as.
     *
     * The names come from the draw, but a hall is not a database: a competitor
     * turns up under a different spelling, or a club is wrong on the entry. This
     * changes the SCREEN, never the registration.
     */
    private function cornerEdit(MatState $state, array $payload): void
    {
        $side = $this->side($payload);

        if (! $side) {
            return;
        }

        $corner = (array) $state->$side;

        foreach (['name', 'club', 'country'] as $field) {
            if (array_key_exists($field, $payload) && $payload[$field] !== null) {
                $corner[$field] = trim((string) $payload[$field]);
            }
        }

        if (! empty($payload['flag'])) {
            // Two letters or nothing: this ends up in a flag URL.
            $flag = strtolower(trim((string) $payload['flag']));
            $corner['flag'] = preg_match('/^[a-z]{2}$/', $flag) ? $flag : null;
        }

        $state->$side = $corner;
    }

    /** The header line: tournament, division, match number, mat, round. */
    private function meta(MatState $state, array $payload): void
    {
        foreach (['tournament' => 'tournament', 'division' => 'division', 'matchNo' => 'match_no',
            'courtLabel' => 'court_label', 'stage' => 'stage', 'referee' => 'referee',
            'ruleset' => 'ruleset'] as $field => $column) {
            if (array_key_exists($field, $payload) && $payload[$field] !== null) {
                $state->$column = trim((string) $payload[$field]) ?: null;
            }
        }
    }

    /**
     * The rules this mat is running.
     *
     * Every field is optional and absent means UNCHANGED — a console that only
     * knows about some of these must not silently switch off the ones it has
     * never heard of.
     */
    private function rules(MatState $state, array $payload, ClubEvent $event): void
    {
        $rules = ($state->rules ?? []) + MatState::DEFAULT_RULES;

        if (array_key_exists('warning', $payload)) {
            // Never longer than the match itself: a warning that starts before
            // the clock does would flash from the start to the bell.
            $rules['warning'] = round(max(0, min((float) $state->duration, (float) $payload['warning'])), 1);
        }

        if (array_key_exists('penalty_limit', $payload)) {
            $rules['penalty_limit'] = max(1, min(10, (int) $payload['penalty_limit']));
        }

        if (array_key_exists('penalty_warn_at', $payload)) {
            $rules['penalty_warn_at'] = max(1, min((int) $rules['penalty_limit'], (int) $payload['penalty_warn_at']));
        }

        // Zero is meaningful here and is the default: NO limit. So the floor is
        // 0 rather than 1, and a mat turns the cap off by typing it back.
        if (array_key_exists('advantage_limit', $payload)) {
            $rules['advantage_limit'] = max(0, min(20, (int) $payload['advantage_limit']));
        }

        if (array_key_exists('stall_seconds', $payload)) {
            $rules['stall_seconds'] = max(3, min(60, (int) $payload['stall_seconds']));
        }

        foreach (['referee_decision', 'time_up_buzzer'] as $flag) {
            if (array_key_exists($flag, $payload)) {
                $rules[$flag] = (bool) $payload[$flag];
            }
        }

        $state->rules = $rules;

        // …and the whole set is written to the event, so it survives this mat
        // and this session.
        $state->persistSettings($event);
    }

    /**
     * Dark arena LED, or a bright venue / projector.
     *
     * ONE switch, never per-colour edits: the venue theme is a coherent set of
     * substitutions (lighter text, a lighter divider, glows and texture off,
     * fatter pips) and letting an operator pick colours individually is how a
     * board ends up unreadable in a way nobody can undo.
     */
    private function theme(MatState $state, array $payload, ClubEvent $event): void
    {
        $state->theme = ($payload['theme'] ?? '') === 'venue' ? 'venue' : 'arena';
        $state->persistSettings($event);
    }

    /* ---------------- Helpers ---------------- */

    /** Append a scoring row and hand it back, so the console can offer an UNDO. */
    private function record(MatState $state, string $action, string $side, int $value, ?string $source, array $payload): MatchEvent
    {
        return $this->ledger->append(
            event: $state->eventModel(),
            court: $state->court,
            action: $action,
            matchId: $state->match_id,
            side: $side,
            value: $value,
            source: $source,
            operatorId: $this->operator?->id,
            clockRemaining: round((float) $state->remaining, 2),
            clockDuration: round((float) $state->duration, 2),
            payload: $this->publicPayload($payload),
        );
    }

    /** 'blue' or 'white', or null — never a side name the caller invented. */
    private function side(array $payload): ?string
    {
        $side = strtolower((string) ($payload['side'] ?? ''));

        return in_array($side, ['blue', 'white'], true) ? $side : null;
    }

    /**
     * Commands worth a context row.
     *
     * `resync` and `theme` change nothing about the match; logging them would
     * bury the officiating under housekeeping.
     */
    private function worthRecording(string $command): bool
    {
        return ! in_array($command, ['resync', 'theme', 'dismiss', 'celebrate', 'bell'], true);
    }

    /** The camera fleet's own vocabulary, which is smaller than this one. */
    private function cameraCommand(string $command): string
    {
        return match ($command) {
            'load' => 'load',
            'start', 'resume' => 'start',
            'end', 'decision', 'commit' => 'finish',
            'clear' => 'clear',
            default => $command,
        };
    }

    /** The payload minus anything internal. Never the CSRF token. */
    private function publicPayload(array $payload): array
    {
        unset($payload['_token'], $payload['mat'], $payload['command']);

        return $payload;
    }
}
