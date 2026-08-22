<?php

namespace App\Events\Sports\Karate\Tournament\Scoreboard;

use App\Events\EventTypeRegistry;
use App\Events\Support\MatchEventLog;
use App\Events\Sports\Karate\Tournament\RunningOrder;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventMatch;
use App\Models\EventOfficial;
use App\Sports\Combat\BeltRank;

/**
 * Applies what an official does at the table to the state of a mat.
 *
 * Every rule lives here rather than in the control page's JavaScript, for one
 * reason: the control page is not the only thing that can be wrong. A second
 * official opening the page, a laptop reconnecting mid-bout, a screen catching
 * up after a dropped socket — all of them must arrive at the SAME score, and
 * they only do if the state is computed in one place and pushed out.
 *
 * The control page therefore sends intentions ("aka scored 2"), never results
 * ("aka now has 6"). A client that has fallen behind can't overwrite the truth
 * with a stale total.
 */
class Scoring
{
    /** Every command an official can issue. Anything else is rejected. */
    /**
     * Why a bout was won when the score is not the answer.
     *
     * WKF's own vocabulary, plus the two an official actually needs beyond it.
     * A closed list because it reaches the record: 'other' is the escape hatch
     * and it is the one that expects the note to be filled in.
     */
    public const WIN_REASONS = [
        'points',    // the normal case — the score decided it
        'hansoku',   // disqualification for a foul
        'shikkaku',  // disqualification for serious misconduct
        'kiken',     // withdrawal / did not continue
        'medical',   // retired injured, or withdrawn by the doctor
        'no_show',   // never came to the mat
        'other',     // anything else — say what in the note
    ];

    public const COMMANDS = [
        'load',        // put a bout on the screen (mode → vs)
        'start',       // hajime — also leaves the VS intro for the scoreboard
        'pause',       // yame
        'point',       // {side, n}
        'undo_point',  // {side, n} — takes the same points back off
        'penalty',     // {side, dir} — up or down the ladder
        'senshu',      // {side} — exclusive; awarding one clears the other
        'time',        // {remaining} — the official corrects the clock
        'reset',       // back to a fresh bout, same competitors
        'finish',      // stop the clock and declare it over
        'clear',       // take the bout off the screen entirely (mode → upcoming)
        'commit',      // WRITE the result, advance the bracket, call the next bout
        'dismiss',     // put the celebration away — on the table AND the wall
        'celebrate',   // bring it back
        'resync',      // tell every screen on this mat to reload itself
        'board',       // end the VS introduction and show the scoreboard, clock untouched
        'intro',       // put the introduction back up — the other half of 'board'
        'duration',    // {minutes} — the operator sets the bout length
        'corner',      // {side, name, club, country, flag} — fix what is announced
        'meta',        // {tournament, division, matchNo, courtLabel, stage} — header text
    ];

    /**
     * Commands that only mean something with a bout on the mat.
     *
     * `load` and `clear` change WHICH bout is there, and `duration`/`meta`
     * prepare the mat before one arrives — those are the four that may run on
     * an empty mat.
     */
    private const NEEDS_BOUT = [
        'start', 'pause', 'point', 'undo_point', 'penalty',
        'senshu', 'time', 'reset', 'finish', 'commit', 'board', 'intro',
    ];

    public function __construct(private BeltRank $belts) {}

    /**
     * Apply one command and hand back the new state.
     *
     * @param  array<string, mixed>  $payload
     */
    public function apply(ClubEvent $event, string $court, string $command, array $payload = []): MatState
    {
        $state = MatState::load($event, $court);

        // Nothing that acts on a BOUT may run when no bout is on the mat.
        //
        // Without this, pressing Hajime on an empty mat put the wall into
        // mode=scoreboard with no competitors — a blank board with no names, no
        // scores and no introduction, because the mode had skipped straight past
        // it. The control page should not offer these either, and now does not,
        // but the page is a convenience and the endpoint is the contract: an
        // official on a second laptop, a stale tab, or a replayed request must
        // all be refused here.
        if (in_array($command, self::NEEDS_BOUT, true) && ! $state->matchId) {
            throw new \RuntimeException(__('event-karate_tournament::messages.commit_no_bout'));
        }

        // The clock is stored as "remaining as of a moment"; before anything
        // else touches it, bring it up to now. Otherwise a pause five seconds
        // after a start would record the time as though nothing had elapsed.
        $this->settleClock($state);

        match ($command) {
            'load' => $this->load($event, $court, $state, (int) ($payload['match_id'] ?? 0), $payload),
            'start' => $this->start($state),
            'pause' => $this->pause($state),
            'point' => $this->point($state, $payload),
            'undo_point' => $this->point($state, $payload, subtract: true),
            'penalty' => $this->penalty($state, $payload),
            'senshu' => $this->senshu($state, $payload),
            'time' => $this->time($state, $payload),
            'reset' => $this->reset($state),
            'finish' => $this->finish($state, $payload),
            'clear' => $this->clear($state),
            'commit' => $this->commit($event, $court, $state),
            // Neither of these touches the bout: they decide whether the hall is
            // still being shown a celebration for a result that is already
            // decided and not yet filed.
            'dismiss' => $state->celebrationClosed = true,
            'celebrate' => $state->celebrationClosed = false,
            // A no-op on purpose. It changes nothing and saves the state
            // unchanged; what makes it useful is the reload the controller
            // publishes afterwards, which is the one recovery a screen with no
            // keyboard has.
            'resync' => null,
            // Leave the introduction WITHOUT starting the bout. Hajime already
            // does both, and that was the only way off the VS screen — so an
            // introduction that had run its course held the wall until the
            // referee was ready to start, and an official who wanted the
            // scoreboard up early had to start the clock to get it. The clock is
            // not touched here: this is a change of what the wall shows, not of
            // the bout.
            'board' => $state->mode = MatState::MODE_SCOREBOARD,
            'intro' => $this->intro($state),
            'duration' => $this->duration($state, $payload),
            'corner' => $this->cornerEdit($state, $payload),
            'meta' => $this->meta($state, $payload),
            default => null,
        };

        // Append the command to the officiating timeline, after the dispatch
        // above has mutated the state and before it is saved — so the scores
        // recorded are the running totals as of this command, and no caller
        // can reach the scoreboard without passing through here.
        //
        // Corners are handed over as neutral sides: aka is 'a', ao is 'b',
        // exactly as load() built them from the bout's a_/b_ columns.
        //
        // This call cannot throw; see MatchEventLog. A mat must never stop
        // because an audit row did not insert.
        MatchEventLog::record(
            event: $event,
            court: $court,
            sport: 'karate',
            command: $command,
            payload: $payload,
            matchId: $state->matchId,
            scoreA: $state->akaScore,
            scoreB: $state->aoScore,
            // The clock has already been settled against now() above, so this is
            // the bout time as the command landed rather than as it was last
            // painted — the difference is seconds, and seconds are the whole
            // point of recording it.
            clockRemaining: $state->matchId ? round($state->remaining, 2) : null,
            clockDuration: $state->matchId ? round($state->duration, 2) : null,
        );

        return $state->save($event, $court);
    }

    /* ---------------- The clock ---------------- */

    /** Charge elapsed time against a running clock, and stop it at zero. */
    private function settleClock(MatState $state): void
    {
        if (! $state->running || ! $state->at) {
            return;
        }

        $elapsed = max(0, now()->diffInMilliseconds($state->at, absolute: true) / 1000);
        $state->remaining = round(max(0, $state->remaining - $elapsed), 1);

        if ($state->remaining <= 0) {
            $state->running = false;
            $state->finished = true;
        }
    }

    /* ---------------- Commands ---------------- */

    /**
     * Put a bout on the mat.
     *
     * Everything the two screens announce is assembled HERE, once, from the
     * draw and the athletes' records — the control page never types a
     * competitor's name, club, flag or belt, so what the hall sees cannot
     * disagree with what the system holds.
     */
    private function load(ClubEvent $event, string $court, MatState $state, int $matchId, array $payload): void
    {
        $match = EventMatch::where('event_id', $event->id)->with('category:id,name,weight_class')->find($matchId);

        if (! $match) {
            return;
        }

        // Keyed by REGISTRATION id, not user id: a_competitor_id/b_competitor_id
        // name the entry in this event, which is what carries the weigh-in
        // weight and the belt presented. RunningOrder and CourtDisplay both
        // resolve it the same way.
        $registrations = ClubEventRegistration::where('event_id', $event->id)
            ->whereIn('id', array_filter([$match->a_competitor_id, $match->b_competitor_id]))
            ->with(['user:id,full_name,name,gender,birthdate,nationality,height_cm,profile_picture,profile_picture_is_public',
                'user.certifications:id,user_id,title,issue_date',
                'user.skillAcquisitions:id,user_id,proficiency_level,start_date',
                'user.memberClubs:id,club_name,logo,country',
                // The club they compete FOR, which is what the screens print.
                'representingTenant:id,club_name,logo,country'])
            ->get()->keyBy('id');

        $state->mode = MatState::MODE_VS;
        $state->matchId = $match->id;
        $state->matchNo = $match->match_no !== null ? (string) $match->match_no : null;
        $state->stage = (string) ($match->phase ?: $match->round ?: '');
        $state->division = (string) ($match->category?->weight_class ?: $match->category?->name ?: '');
        // Whoever is actually appointed to officiate this event. Nobody
        // appointed means the chip is hidden — a hall screen naming a referee
        // who was never assigned is worse than one that names none.
        $state->referee = $this->officiatingName($event);
        $state->aka = $this->corner($match, 'a', $registrations);
        $state->ao = $this->corner($match, 'b', $registrations);

        // A fresh bout: nothing carries over from whoever was on this mat before.
        $minutes = (float) ($payload['minutes'] ?? 3);
        $state->duration = max(30, $minutes * 60);
        $state->remaining = $state->duration;
        $state->akaScore = $state->aoScore = 0;
        $state->akaPen = $state->aoPen = 0;
        $state->akaSenshu = $state->aoSenshu = false;
        $state->running = false;
        $state->finished = false;
        // Nothing carries over, the last bout's dismissed celebration included.
        $state->celebrationClosed = false;
        $state->lastEvent = null;
    }

    /**
     * The appointed jury, as the introduction announces them.
     *
     * Abbreviated to an initial and a surname ("S. Petrov"), which is both the
     * convention on a competition screen and what the layout is sized for: the
     * chip sits on one centred row with the bout number and the mat, and a full
     * name wraps that row onto two lines — which then rides up into the
     * athletes' own chips. Nobody appointed returns null and the chip is hidden.
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
     * A competitor's win–loss record across every event they have fought in.
     *
     * The draw stores a REGISTRATION id per corner, and a registration belongs
     * to one event — so a career record means gathering all of this athlete's
     * registrations first, then every decided bout either of them appears in.
     * Two competitors per bout, so this is two small queries at load time and
     * nothing during the bout itself.
     */
    private function record(?int $userId): ?string
    {
        if (! $userId) {
            return null;
        }

        $entries = ClubEventRegistration::where('user_id', $userId)->pluck('id');

        if ($entries->isEmpty()) {
            return null;
        }

        $bouts = EventMatch::whereNotNull('winner')
            ->where(fn ($q) => $q->whereIn('a_competitor_id', $entries)->orWhereIn('b_competitor_id', $entries))
            ->get(['a_competitor_id', 'b_competitor_id', 'winner']);

        if ($bouts->isEmpty()) {
            return null;   // no history yet — the chip is hidden, not zeroed
        }

        $won = $bouts->filter(function (EventMatch $m) use ($entries) {
            $side = $entries->contains($m->a_competitor_id) ? 'a' : 'b';

            return $m->winner === $side;
        })->count();

        return $won.'W – '.($bouts->count() - $won).'L';
    }

    /**
     * One corner, as the screens announce it.
     *
     * A competitor's photo obeys profile_picture_is_public — a hall screen is a
     * publication, and a member who has not made their picture public does not
     * get their face projected on a wall.
     */
    private function corner(EventMatch $match, string $side, $registrations): array
    {
        $id = $match->{$side.'_competitor_id'};
        $reg = $id ? $registrations->get($id) : null;
        $user = $reg?->user;
        // The club they COMPETE FOR — see ClubEventRegistration::competingClub().
        $club = $reg?->competingClub();
        $belt = $user ? $this->belts->for($user, $reg) : null;

        return [
            'name' => $match->{$side.'_name'} ?: ($user?->full_name ?? $user?->name ?? ''),
            'club' => $club?->club_name ?? '',
            // The club's country, never the person's passport: they are here
            // as their club, and that is what the hall is told.
            'country' => $club?->country ?: ($match->{$side.'_country'} ?: ''),
            'flag' => strtolower((string) ($match->{$side.'_country'} ?: $club?->country ?: '')) ?: null,
            'logo' => $club?->logo ? asset('storage/'.$club->logo) : null,
            // The event's OWN photo wins, then the member's profile picture if
            // they published it. The first was uploaded by an organiser FOR this
            // competition — including for the many competitors who have no
            // account to have a profile picture on — so it needs no privacy gate
            // beyond the one that put it there. The second is somebody's private
            // picture and keeps its gate: a hall screen is a publication.
            'photo' => $reg?->photo
                ? asset('storage/'.$reg->photo)
                : (($user?->profile_picture && $user->profile_picture_is_public)
                    ? asset('storage/'.$user->profile_picture)
                    : null),
            'belt' => $belt['label'] ?? null,
            'record' => $this->record($user?->id),
            // The stat line. Each part is null when unknown, and the screen
            // hides what is null rather than printing a placeholder — invented
            // detail on a hall screen reads as fact.
            'age' => $user?->birthdate ? $user->birthdate->age : null,
            'height' => $user?->height_cm ?: null,
            'weight' => $reg?->weight ? (float) $reg->weight : null,
        ];
    }

    private function start(MatState $state): void
    {
        if ($state->finished || $state->remaining <= 0) {
            return;
        }
        // Hajime is also what ends the introduction — the VS screen animates
        // itself out and the scoreboard is behind it.
        $state->mode = MatState::MODE_SCOREBOARD;
        $state->running = true;
    }

    /**
     * Back to the introduction.
     *
     * The other half of 'board', so the console's one button can go both ways —
     * an introduction is shown, dismissed too early, and wanted again more often
     * than anybody would guess, and until now the only way back was to reload
     * the bout.
     *
     * Refused while the clock is RUNNING, and that refusal is the point: the
     * introduction covers the score, and a bout in progress whose scoreboard has
     * been replaced by two portraits is a hall that cannot see what is
     * happening. Stop the clock first, deliberately.
     */
    private function intro(MatState $state): void
    {
        if ($state->running) {
            throw new \RuntimeException(__('event-karate_tournament::messages.intro_running'));
        }

        $state->mode = MatState::MODE_VS;
    }

    private function pause(MatState $state): void
    {
        $state->running = false;
    }

    private function point(MatState $state, array $payload, bool $subtract = false): void
    {
        $side = $this->side($payload);
        $n = max(1, min(3, (int) ($payload['n'] ?? 1)));

        if (! $side) {
            return;
        }

        $field = $side.'Score';
        $state->$field = max(0, $state->$field + ($subtract ? -$n : $n));

        // The callout is the point landing, so taking one back must not shout.
        $state->lastEvent = $subtract ? null : ['side' => $side, 'n' => $n, 'ts' => (int) (microtime(true) * 1000)];
    }

    /**
     * Up or down the ladder. The control page offers both directions because a
     * penalty given by mistake has to come off — a wrap-around alone would make
     * an official cycle through the whole ladder in front of a hall.
     */
    private function penalty(MatState $state, array $payload): void
    {
        $side = $this->side($payload);

        if (! $side) {
            return;
        }

        $dir = ((int) ($payload['dir'] ?? 1)) >= 0 ? 1 : -1;
        $field = $side.'Pen';
        $before = $state->$field;
        $state->$field = max(0, min(count(MatState::PENALTIES), $state->$field + $dir));

        // A penalty going UP is an event the hall should hear. Going down is a
        // correction and makes no noise — and neither does a press that changed
        // nothing because the ladder was already at its end.
        $state->lastEvent = ($dir === 1 && $state->$field !== $before)
            ? ['side' => $side, 'n' => 0, 'penalty' => true, 'ts' => (int) (microtime(true) * 1000)]
            : null;
    }

    /** Bout length, in minutes and seconds. Resets the clock with it. */
    private function duration(MatState $state, array $payload): void
    {
        $state->duration = max(10, round((float) ($payload['minutes'] ?? 3) * 60));
        $state->remaining = $state->duration;
        $state->running = false;
        $state->finished = false;
    }

    /**
     * Correct what a corner is announced as.
     *
     * The names come from the draw, but a hall is not a database: a competitor
     * turns up under a different spelling, or a club is wrong on the entry. The
     * operator can fix what the wall says without touching the draw itself —
     * this changes the SCREEN, never the registration.
     */
    private function cornerEdit(MatState $state, array $payload): void
    {
        $side = $this->side($payload);

        if (! $side) {
            return;
        }

        $corner = $state->$side;
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

    /** The header line: tournament, division, bout number, mat, round. */
    private function meta(MatState $state, array $payload): void
    {
        foreach (['tournament', 'division', 'matchNo', 'courtLabel', 'stage', 'referee'] as $field) {
            if (array_key_exists($field, $payload) && $payload[$field] !== null) {
                $state->$field = trim((string) $payload[$field]) ?: null;
            }
        }
    }

    /** Exclusive: senshu belongs to whoever scored first, so only one has it. */
    private function senshu(MatState $state, array $payload): void
    {
        $side = $this->side($payload);

        if (! $side) {
            return;
        }

        $on = ! $state->{$side.'Senshu'};
        $state->akaSenshu = $side === 'aka' && $on;
        $state->aoSenshu = $side === 'ao' && $on;
    }

    private function time(MatState $state, array $payload): void
    {
        $state->remaining = round(max(0, min($state->duration, (float) ($payload['remaining'] ?? 0))), 1);
        $state->finished = $state->remaining <= 0 && $state->finished;
    }

    private function reset(MatState $state): void
    {
        $state->akaScore = $state->aoScore = 0;
        $state->akaPen = $state->aoPen = 0;
        $state->akaSenshu = $state->aoSenshu = false;
        $state->remaining = $state->duration;
        $state->running = false;
        $state->finished = false;
        // A bout that is no longer over has nothing to celebrate. clear() runs
        // through here too, so taking a bout off the mat clears it as well.
        $state->celebrationClosed = false;
        $state->lastEvent = null;
    }

    /**
     * The bout is over — and this is where WHO won is settled, not just WHEN.
     *
     * Two ways in. Without a winner in the payload it behaves exactly as it
     * always did: the clock stops, the score decides, and senshu breaks a tie.
     * WITH one, the official has declared it — a disqualification, a withdrawal,
     * a doctor's call — and that outranks the points, which is the whole reason
     * this exists. Either way nothing is filed yet: commit is still a separate,
     * deliberate act.
     */
    private function finish(MatState $state, array $payload = []): void
    {
        $state->running = false;
        $state->finished = true;
        $state->lastEvent = null;

        $winner = in_array($payload['winner'] ?? null, ['aka', 'ao'], true) ? $payload['winner'] : null;

        // Declaring a winner takes the side that was named. Not declaring one
        // CLEARS any previous declaration rather than leaving it standing: an
        // official who ends the bout again on the score has changed their mind,
        // and a stale override would quietly file the wrong athlete.
        $state->winner = $winner;

        if ($winner === null) {
            $state->winReason = null;
            $state->winNote = null;

            return;
        }

        $reason = (string) ($payload['reason'] ?? 'other');
        $state->winReason = in_array($reason, self::WIN_REASONS, true) && $reason !== 'points'
            ? $reason
            : 'other';

        // Trimmed and capped to what the column holds. Never trusted as markup —
        // every screen renders it as text.
        $note = trim((string) ($payload['note'] ?? ''));
        $state->winNote = $note === '' ? null : mb_substr($note, 0, 200);
    }

    /**
     * The result leaves the scoreboard and becomes a fact.
     *
     * Everything up to here has been scaffolding in a cache. This is the one
     * command that writes: it hands the bout to the package's own
     * recordOutcome(), which is what records the scores, carries the winner
     * into their next bout, closes the division's podium when a final lands,
     * tells the two athletes, and calls whoever is now due on this mat. Nothing
     * about a result is reimplemented here — a second path to writing a result
     * is a second set of rules to get wrong.
     *
     * Then the mat moves on by itself: the next bout in the running order is
     * introduced, or the screen goes back to the queue when the mat is done.
     *
     * Refuses a bout that is level with no senshu. WKF has no draw — somebody
     * has to be given it, and the officials' table is where that is decided,
     * not here.
     */
    private function commit(ClubEvent $event, string $court, MatState $state): void
    {
        if (! $state->matchId) {
            throw new \RuntimeException(__('event-karate_tournament::messages.commit_no_bout'));
        }

        if (! $state->akaLeads() && ! $state->aoLeads()) {
            throw new \RuntimeException(__('event-karate_tournament::messages.commit_level'));
        }

        app(EventTypeRegistry::class)->for($event)->recordOutcome($event, $state->matchId, [
            // akaLeads() already answers the DECLARED winner when there is one,
            // so a disqualification advances the right athlete through the same
            // path as a bout won on points.
            'winner' => $state->akaLeads() ? 'a' : 'b',
            'a_score' => (string) $state->akaScore,
            'b_score' => (string) $state->aoScore,
            // Why, when it was not the score. This is the part that has to
            // outlive the mat state: the cache is gone by the afternoon and
            // "how did the athlete with two points win that" is asked later.
            'win_reason' => $state->winner !== null ? ($state->winReason ?: 'other') : null,
            'win_note' => $state->winner !== null ? $state->winNote : null,
            'status' => 'done',
        ]);

        // Straight into the next one. The running order is recomputed by
        // recordOutcome above, so this is the bout that is genuinely next —
        // including one the finished bout just fed a competitor into.
        // The SAME queue the wall board announces — see RunningOrder::matQueue.
        // Deciding "next" independently here is exactly what made the table load
        // a different bout from the one the hall had just been told to expect.
        $order = new RunningOrder;
        $next = $order->matQueue($event, $court)
            ->first(fn (EventMatch $m) => $m->id !== $state->matchId && $order->isRunnable($m));

        $minutes = $state->duration / 60;

        if ($next) {
            $this->load($event, $court, $state, $next->id, ['minutes' => $minutes]);

            return;
        }

        $this->clear($state);
    }

    private function clear(MatState $state): void
    {
        $state->mode = MatState::MODE_UPCOMING;
        $state->matchId = null;
        $state->matchNo = $state->stage = $state->division = null;
        $state->aka = $state->ao = [];
        $this->reset($state);
    }

    /** 'aka' or 'ao', or null — never a side name the caller invented. */
    private function side(array $payload): ?string
    {
        $side = strtolower((string) ($payload['side'] ?? ''));

        return in_array($side, ['aka', 'ao'], true) ? $side : null;
    }
}
