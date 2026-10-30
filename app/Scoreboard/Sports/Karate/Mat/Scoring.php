<?php

namespace App\Scoreboard\Sports\Karate\Mat;

use App\Events\EventTypeRegistry;
use App\Events\Support\MatchEventLog;
use App\Events\Sports\Karate\Tournament\RunningOrder;
use App\Events\Support\Cameras\CameraFleet;
use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventExhibitionBout;
use App\Events\Models\EventMatch;
use App\Events\Models\EventOfficial;
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
        'penalty',     // {side, dir} up/down the ladder, or {side, level} straight to one
        'senshu',      // {side} — exclusive; awarding one clears the other
        'time',        // {remaining} — the official corrects the clock
        'reset',       // back to a fresh bout, same competitors
        'finish',      // stop the clock and declare it over
        'clear',       // take the bout off the screen entirely (mode → upcoming)
        'commit',      // WRITE the result, advance the bracket, call the next bout
        'dismiss',     // put the celebration away — on the table AND the wall
        'celebrate',   // bring it back
        'resync',      // tell every screen on this mat to reload itself
        'refresh',     // re-read both corners from the entries; touches nothing else
        'board',       // end the VS introduction and show the scoreboard, clock untouched
        'intro',       // put the introduction back up — the other half of 'board'
        'duration',    // {minutes} — the operator sets the bout length
        'corner',      // {side, name, club, country, flag} — fix what is announced
        'meta',        // {tournament, division, matchNo, courtLabel, stage} — header text
        'rules',       // {senshuRule, autoSenshu, winByPenalties, atoshiWarn, timeUpBuzzer, gapOn, gap, warning}
        'start_day',   // put the competition under way FROM THE MAT, after a
                       //   filing command was refused — ResultFiling::startDay()
        'exhibition',  // {a, b} — two entrants of the event, OUTSIDE the draw
                       //   (EventExhibitionBout; behind events.exhibitions)
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
     * Commands that put a result on the RECORD.
     *
     * The command NAMES are this sport's own vocabulary; the RULE about them is
     * not. Whether a competition has begun is common to every martial art, so
     * the decision lives in App\Events\Support\ResultFiling and this constant
     * only says which of our commands it applies to.
     *
     * This sport had NO such check until 2026-09-22: a result could be written
     * into the bracket for an event nobody had started, with nothing recorded
     * about who did it.
     */
    private const FILES_A_RESULT = ['finish', 'commit'];

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
        if (in_array($command, self::NEEDS_BOUT, true) && ! $state->hasBout()) {
            throw new \RuntimeException(__('scoreboard::karate_messages.commit_no_bout'));
        }

        /*
         * A result cannot be filed against a competition nobody has started.
         *
         * Shared rule, shared message, shared escape hatch: the console turns
         * the EventNotUnderWay this throws into a "Start the day?" confirmation
         * so a scoring table with a finished bout is never stuck waiting for
         * somebody else's screen.
         */
        // An exhibition files nothing into the competition, so it is not held
        // to "has the competition started" — that rule protects the draw.
        if (in_array($command, self::FILES_A_RESULT, true) && ! $state->exhibitionId) {
            app(\App\Events\Support\ResultFiling::class)->refuseUnlessUnderWay($event);
        }

        // The clock is stored as "remaining as of a moment"; before anything
        // else touches it, bring it up to now. Otherwise a pause five seconds
        // after a start would record the time as though nothing had elapsed.
        $this->settleClock($state);

        match ($command) {
            'load' => $this->load($event, $court, $state, (int) ($payload['match_id'] ?? 0), $payload),
            'exhibition' => $this->exhibition($event, $court, $state, $payload),
            'start' => $this->start($state),
            'pause' => $this->pause($state),
            'point' => $this->point($state, $payload),
            'undo_point' => $this->point($state, $payload, subtract: true),
            'penalty' => $this->penalty($state, $payload),
            'senshu' => $this->senshu($state, $payload),
            'time' => $this->time($state, $payload),
            'reset' => $this->reset($state),
            'finish' => $this->declareEnd($state, $payload),
            'clear' => $this->clear($state),
            'commit' => $this->commit($event, $court, $state),
            /*
             * Start the competition from the scoring table. Offered by the
             * console only after a filing command was refused, so it is always
             * a deliberate second tap. Locks the draw as well as stamping
             * started_at — see ResultFiling::startDay().
             */
            'start_day' => app(\App\Events\Support\ResultFiling::class)
                ->startDay($event, \Illuminate\Support\Facades\Auth::user()),
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
            'refresh' => $this->refresh($event, $state),
            // Leave the introduction WITHOUT starting the bout. Hajime already
            // does both, and that was the only way off the VS screen — so an
            // introduction that had run its course held the wall until the
            // referee was ready to start, and an official who wanted the
            // scoreboard up early had to start the clock to get it. The clock is
            // not touched here: this is a change of what the wall shows, not of
            // the bout.
            'board' => $state->mode = MatState::MODE_SCOREBOARD,
            'intro' => $this->intro($state),
            'duration' => $this->durationCommand($state, $payload, $event),
            'corner' => $this->cornerEdit($state, $payload),
            'meta' => $this->meta($state, $payload),
            'rules' => $this->rules($state, $payload, $event),
            // A Karate STYLE engine's own commands (Styles\*\Scoring). The
            // shared engine has none — the endpoint refuses any command not in
            // the engine's COMMANDS before it gets here.
            default => $this->styleCommand($event, $court, $state, $command, $payload),
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
            clockRemaining: $state->hasBout() ? round($state->remaining, 2) : null,
            clockDuration: $state->hasBout() ? round($state->duration, 2) : null,
        );

        // The cameras on this mat, if any, are told the same thing the hall is:
        // a bout was loaded, started, or is over. Here for the same reason the
        // audit log is — this is the one funnel every command passes through,
        // so a camera cannot miss a bout because some other caller took a
        // shortcut. It cannot throw; a mat must never stop because a phone did.
        CameraFleet::observe(
            event: $event,
            court: $court,
            command: $command,
            matchId: $state->matchId,
            bout: [
                'number' => $state->matchNo,
                'stage' => $state->stage,
                'red' => $state->aka['name'] ?? null,
                'blue' => $state->ao['name'] ?? null,
            ],
        );

        return $state->save($event, $court);
    }

    /**
     * A command only a Karate STYLE engine knows (its COMMANDS adds it). The
     * shared engine knows none, so this does nothing here.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function styleCommand(ClubEvent $event, string $court, MatState $state, string $command, array $payload): void {}

    /** The penalty ladder this engine runs; its top rung ends the bout. */
    protected function penaltyLadder(): array
    {
        return MatState::PENALTIES;
    }

    /** Why the bout ended when a corner reaches the top of the ladder. */
    protected function penaltyEndReason(): string
    {
        return 'hansoku';
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

            // The bell is an automatic ending: it stops the bout, and then the
            // table is asked how it ended. Guarded on `finished` so settling
            // the clock twice does not re-ask a question already answered.
            if (! $state->finished) {
                $this->autoEnd($state);
            }
        }
    }

    /**
     * The bout ended on its own — the bell, the gap, or the top of the penalty
     * ladder.
     *
     * It stops there. The result is not announced to the hall and the
     * celebration does not run until an official at the table says how the bout
     * ended, because the score is not always who won: a disqualification hands
     * it the other way, and a level bout on the bell is not a result at all.
     *
     * The wall is held by `celebrationClosed` — the same flag an official uses
     * to put a celebration away — so no screen needs to learn a new state to
     * behave correctly here.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function autoEnd(MatState $state, array $payload = []): void
    {
        $this->finish($state, $payload);

        $state->awaitingDecision = true;
        $state->celebrationClosed = true;
    }

    /**
     * An official said how the bout ended. THIS is what releases the
     * celebration — on the table and, through the same flag, on every screen in
     * the hall.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function declareEnd(MatState $state, array $payload = []): void
    {
        $this->finish($state, $payload);

        $state->awaitingDecision = false;
        $state->celebrationClosed = false;
    }

    /* ---------------- Commands ---------------- */

    /**
     * Re-read the two people on the mat, without disturbing the bout.
     *
     * Everything the screens announce is assembled ONCE, by `load`, and then
     * lives in the mat's row — which is what makes the board instant and what
     * makes it survive a reload, but also means the board is a SNAPSHOT.
     * Correct a competitor's photograph, belt or weight afterwards and the
     * database changes while the wall carries on showing what was true when the
     * bout was called.
     *
     * Nothing else moves: not the clock, not the score, not the penalties, not
     * the mode, not the result. The same command exists under the same name in
     * the Taekwondo and BJJ engines.
     *
     * A bout that has gone leaves the corners as they are — a blank board is
     * worse than a stale one.
     */
    private function refresh(ClubEvent $event, MatState $state): void
    {
        $match = $state->exhibitionId
            ? $this->exhibitionAsMatch($event, $state->exhibitionId)
            : EventMatch::where('event_id', $event->id)
                ->with('category:id,name,weight_class')
                ->find((int) $state->matchId);

        if (! $match) {
            return;
        }

        $registrations = ClubEventRegistration::where('event_id', $event->id)
            ->whereIn('id', array_filter([$match->a_competitor_id, $match->b_competitor_id]))
            ->with(['user:id,full_name,name,gender,birthdate,nationality,height_cm,profile_picture,profile_picture_is_public',
                'user.certifications:id,user_id,title,issue_date',
                'user.skillAcquisitions:id,user_id,proficiency_level,start_date',
                'user.memberClubs:id,club_name,logo,country',
                'representingTenant:id,club_name,logo,country'])
            ->get()->keyBy('id');

        // The clubs the organiser WROTE DOWN for this event (`event_clubs`) —
        // teams the platform has never met, which is most of a real
        // competition. Without them the introduction named a club only for the
        // few entrants attached to a tenant.
        $written = \App\Events\Support\EntryClub::forMany($event, $registrations->keys()->all());

        $state->aka = $this->corner($match, 'a', $registrations, $written);
        $state->ao = $this->corner($match, 'b', $registrations, $written);
    }

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

        // A draw bout replaces an exhibition that was never finished.
        $this->abandonExhibition($state);

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
        // The clubs the organiser WROTE DOWN for this event (`event_clubs`) —
        // teams the platform has never met, which is most of a real
        // competition. Without them the introduction named a club only for the
        // few entrants attached to a tenant.
        $written = \App\Events\Support\EntryClub::forMany($event, $registrations->keys()->all());

        $state->aka = $this->corner($match, 'a', $registrations, $written);
        $state->ao = $this->corner($match, 'b', $registrations, $written);

        // A fresh bout: nothing carries over from whoever was on this mat
        // before — except the CLOCK LENGTH, which is a setting rather than
        // something the last bout did.
        //
        // It used to fall back to three minutes whenever the caller named no
        // length, so an official who set 2:00 in the settings panel watched it
        // become 3:00 again the moment they loaded the next bout off the queue.
        // Now the payload wins if it names one, and otherwise the mat keeps
        // what it was configured with.
        $state->duration = match (true) {
            array_key_exists('seconds', $payload) => max(30, round((float) $payload['seconds'])),
            array_key_exists('minutes', $payload) => max(30, (float) $payload['minutes'] * 60),
            default => max(30, $state->duration ?: 180),
        };
        $state->remaining = $state->duration;
        $state->warning = min($state->warning, $state->duration);
        $state->tally = [];
        $state->akaScore = $state->aoScore = 0;
        $state->akaPen = $state->aoPen = 0;
        $state->akaSenshu = $state->aoSenshu = false;
        $state->running = false;
        $state->finished = false;
        // Nothing carries over, the last bout's dismissed celebration included.
        $state->celebrationClosed = false;
        $state->awaitingDecision = false;
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
     * One entry drawn as a VS corner, for the console's preview of the VS
     * screen — through the SAME corner() the mat uses, so the preview shows
     * exactly the name, club, flag, photo and framing the wall will.
     */
    public function previewCorner(ClubEvent $event, ClubEventRegistration $reg): array
    {
        $match = new EventMatch;
        $match->forceFill(['event_id' => $event->id, 'a_competitor_id' => $reg->id]);

        $registrations = ClubEventRegistration::where('event_id', $event->id)->whereKey($reg->id)
            ->with(['user:id,full_name,name,gender,birthdate,nationality,height_cm,profile_picture,profile_picture_is_public',
                'user.certifications:id,user_id,title,issue_date',
                'user.skillAcquisitions:id,user_id,proficiency_level,start_date',
                'user.memberClubs:id,club_name,logo,country',
                'representingTenant:id,club_name,logo,country'])
            ->get()->keyBy('id');

        return $this->corner($match, 'a', $registrations, \App\Events\Support\EntryClub::forMany($event, [$reg->id]));
    }

    /**
     * One corner, as the screens announce it.
     *
     * A competitor's photo obeys profile_picture_is_public — a hall screen is a
     * publication, and a member who has not made their picture public does not
     * get their face projected on a wall.
     */
    private function corner(EventMatch $match, string $side, $registrations, array $written = []): array
    {
        $id = $match->{$side.'_competitor_id'};
        $reg = $id ? $registrations->get($id) : null;
        $user = $reg?->user;
        // The club they COMPETE FOR — see ClubEventRegistration::competingClub().
        $club = $reg?->competingClub();
        $belt = $user ? $this->belts->for($user, $reg) : null;
        // A tenant wins when the entry names one; otherwise the club the
        // organiser wrote down for this event stands in, name and crest alike.
        $other = (! $club && $id) ? ($written[$id] ?? null) : null;

        // The flag in the bracket's order (as HallScreen\CourtDisplay does):
        // countryCode() first, because it alone obeys the event's flag switch
        // (App\Events\Support\FlagSource) — club country, or the athlete's
        // nationality. Then the written-down club, then the bout's copy.
        $country = (string) (($reg?->countryCode() ?: '')
            ?: ($club?->country ?: ($other['country'] ?? ''))
            ?: ($match->{$side.'_country'} ?: ''));

        return [
            'name' => $match->{$side.'_name'} ?: ($user?->full_name ?? $user?->name ?? ''),
            'club' => $club?->club_name ?? ($other['name'] ?? ''),
            // One answer for the word and the flag (see $country above).
            'country' => $country,
            'flag' => strtolower((string) (\App\Support\Countries::iso2($country) ?: $country)) ?: null,
            // The person's own nationality, as an alpha-2 flag code. Read by
            // the Combat style's VS arena only (the owner's call, 2026-10-16):
            // the intro presents the athlete, the scoreboard still presents
            // the club. Null when unknown — the VS falls back to the club.
            'nationality' => \App\Support\Countries::iso2($user?->nationality),
            'logo' => $club?->logo ? file_url($club->logo) : ($other['logo'] ?? null),
            // The event's OWN photo wins, then the member's profile picture if
            // they published it. The first was uploaded by an organiser FOR this
            // competition — including for the many competitors who have no
            // account to have a profile picture on — so it needs no privacy gate
            // beyond the one that put it there. The second is somebody's private
            // picture and keeps its gate: a hall screen is a publication.
            'photo' => $reg?->photo
                ? file_url($reg->photo)
                : (($user?->profile_picture && $user->profile_picture_is_public)
                    ? file_url($user->profile_picture)
                    : null),
            // How that photo is PLACED on the VS screen — a zoom and a focal
            // point over the untouched original (App\Scoreboard\Models\
            // PhotoVsFrame), set per person in the console's Players tab.
            // Null: the panel's own framing, as always.
            'vs_frame' => \App\Scoreboard\Models\PhotoVsFrame::payloadFor($reg?->photo
                ?: (($user?->profile_picture && $user->profile_picture_is_public) ? $user->profile_picture : null)),
            // The drawn stand-in, for a corner with no picture of their own.
            // Always present — see App\Members\Support\Avatar for why an unknown
            // gender takes the male artwork rather than nothing.
            'fallback' => \App\Members\Support\Avatar::placeholder($user?->gender),
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
            throw new \RuntimeException(__('scoreboard::karate_messages.intro_running'));
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
        $before = $state->$field;
        $state->$field = max(0, $state->$field + ($subtract ? -$n : $n));

        // The callout is the point landing, so taking one back must not shout.
        $state->lastEvent = $subtract ? null : ['side' => $side, 'n' => $n, 'ts' => (int) (microtime(true) * 1000)];

        // Senshu to whoever opened the scoring. Only on the FIRST point of the
        // bout — both sides on nought and neither holding it — so a correction
        // that takes the score back to 0–0 does not hand it out a second time.
        if (! $subtract
            && $state->$field > $before
            && $state->senshuRule
            && $state->autoSenshu
            && ! $state->akaSenshu
            && ! $state->aoSenshu
            && ($state->akaScore + $state->aoScore) === $state->$field - $before) {
            $state->akaSenshu = $side === 'aka';
            $state->aoSenshu = $side === 'ao';
        }

        $this->checkGap($state);
    }

    /**
     * WKF's point gap ends a bout early.
     *
     * Enforced HERE rather than on the console, because the console is not the
     * only thing that can score a mat and a rule that lives in one browser is
     * a rule the other console does not have. `finish` with no winner in the
     * payload is the ordinary "the points decided it" ending.
     */
    protected function checkGap(MatState $state): void
    {
        if (! $state->gapOn || $state->gap < 1 || $state->finished || ! $state->hasBout()) {
            return;
        }

        if (abs($state->akaScore - $state->aoScore) >= $state->gap) {
            $this->autoEnd($state);
        }
    }

    /**
     * Up or down the ladder. The control page offers both directions because a
     * penalty given by mistake has to come off — a wrap-around alone would make
     * an official cycle through the whole ladder in front of a hall.
     */
    protected function penalty(MatState $state, array $payload): void
    {
        $side = $this->side($payload);

        if (! $side) {
            return;
        }

        $field = $side.'Pen';
        $before = $state->$field;
        $top = count($this->penaltyLadder());

        // Two ways in, one ladder. `level` is the console's five cells — the
        // official presses the penalty they are giving, rather than counting
        // presses up to it. `dir` is the older step, kept: it is what the
        // keyboard shortcuts send and what any other caller already uses.
        if (array_key_exists('level', $payload)) {
            $state->$field = max(0, min($top, (int) $payload['level']));
        } else {
            $dir = ((int) ($payload['dir'] ?? 1)) >= 0 ? 1 : -1;
            $state->$field = max(0, min($top, $state->$field + $dir));
        }

        // A penalty going UP is an event the hall should hear. Going down is a
        // correction and makes no noise — and neither does a press that changed
        // nothing because the ladder was already where it was asked for.
        $state->lastEvent = ($state->$field > $before)
            ? ['side' => $side, 'n' => 0, 'penalty' => true, 'ts' => (int) (microtime(true) * 1000)]
            : null;

        // The top of the ladder is a disqualification: hansoku hands the bout to
        // the other corner however the points stand. Ended here, on the server,
        // for the same reason the gap is — so both consoles and the wall agree,
        // and so the reason is on the record rather than in somebody's memory.
        if ($state->$field >= $top && $state->winByPenalties && ! $state->finished && $state->hasBout()) {
            // The winner is not in doubt here — the ladder decided it — but the
            // table still confirms, so one path ends a bout rather than two.
            $this->autoEnd($state, [
                'winner' => $side === 'aka' ? 'ao' : 'aka',
                'reason' => $this->penaltyEndReason(),
            ]);
        }
    }

    /**
     * The rules this mat is running.
     *
     * Every field is optional and absent means UNCHANGED — a console that only
     * knows about some of these must not silently switch off the ones it has
     * never heard of.
     */
    private function rules(MatState $state, array $payload, ?ClubEvent $event = null): void
    {
        foreach (['senshuRule', 'autoSenshu', 'winByPenalties', 'atoshiWarn', 'timeUpBuzzer', 'gapOn'] as $flag) {
            if (array_key_exists($flag, $payload)) {
                $state->$flag = (bool) $payload[$flag];
            }
        }

        if (array_key_exists('gap', $payload)) {
            $state->gap = max(1, min(20, (int) $payload['gap']));
        }

        // Never longer than the bout itself: a warning that starts before the
        // clock does would flash from hajime to the bell.
        if (array_key_exists('warning', $payload)) {
            $state->warning = round(max(0, min($state->duration, (float) $payload['warning'])), 1);
        }

        // Turning senshu off cannot leave one standing — it decides bouts.
        if (! $state->senshuRule) {
            $state->akaSenshu = $state->aoSenshu = false;
        }

        // A gap that was just armed, or narrowed, applies to the score already
        // on the board rather than waiting for the next point.
        $this->checkGap($state);

        // …and the whole set is written to the event, so it survives this cache
        // entry, this mat, and this session.
        $event?->exists && $state->persistSettings($event);
    }

    /** Bout length, in minutes and seconds. Resets the clock with it. */
    private function duration(MatState $state, array $payload): void
    {
        // `seconds` is what the settings panel sends (two mm:ss boxes);
        // `minutes` is the older payload and still the one the queue uses when
        // it loads a bout, so both are accepted.
        $state->duration = array_key_exists('seconds', $payload)
            ? max(10, round((float) $payload['seconds']))
            : max(10, round((float) ($payload['minutes'] ?? 3) * 60));

        $state->remaining = $state->duration;
        $state->running = false;
        $state->finished = false;

        // A warning that no longer fits inside the bout comes back to fit it.
        $state->warning = min($state->warning, $state->duration);
    }

    /** The bout length is a setting too — it outlives the bout it was set on. */
    private function durationCommand(MatState $state, array $payload, ClubEvent $event): void
    {
        $this->duration($state, $payload);
        $state->persistSettings($event);
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

        // A mat running without the senshu rule has no senshu to award.
        if (! $side || ! $state->senshuRule) {
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
        $state->tally = [];
        $state->akaScore = $state->aoScore = 0;
        $state->akaPen = $state->aoPen = 0;
        $state->akaSenshu = $state->aoSenshu = false;
        $state->remaining = $state->duration;
        $state->running = false;
        $state->finished = false;
        // A bout that is no longer over has nothing to celebrate. clear() runs
        // through here too, so taking a bout off the mat clears it as well.
        $state->celebrationClosed = false;
        $state->awaitingDecision = false;
        $state->lastEvent = null;
        // …and nothing DECLARED, either. A declaration outlived the reset that
        // was meant to undo it: an official who ended a bout on a disqualifica-
        // tion, reset it, then ran it again and filed the result was filing the
        // stale winner, because commit() reads this field and the score no
        // longer had a say. Reset means a fresh bout, all of it.
        $state->winner = null;
        $state->winReason = null;
        $state->winNote = null;
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

        // A bout can be over without ever having been scored: the opponent does
        // not turn up, the official ends it on the introduction and declares the
        // athlete who did. The wall must follow that. Left on the introduction
        // the celebration paints underneath it and the hall sees two athletes
        // about to fight a bout that is already won — so ending a bout takes the
        // introduction down, exactly as hajime would have.
        if ($state->mode === MatState::MODE_VS) {
            $state->mode = MatState::MODE_SCOREBOARD;
        }

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
        if ($state->exhibitionId) {
            $this->commitExhibition($state);

            return;
        }

        if (! $state->matchId) {
            throw new \RuntimeException(__('scoreboard::karate_messages.commit_no_bout'));
        }

        if (! $state->akaLeads() && ! $state->aoLeads()) {
            throw new \RuntimeException(__('scoreboard::karate_messages.commit_level'));
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
        $this->abandonExhibition($state);
        $state->mode = MatState::MODE_UPCOMING;
        $state->matchId = null;
        $state->matchNo = $state->stage = $state->division = null;
        $state->aka = $state->ao = [];
        $this->reset($state);
    }

    /* ── Side exhibition bouts ────────────────────────────────────────────
       Any two entrants of THIS event, put on the mat outside the draw (owner,
       2026-10-09). Stored only in event_exhibition_bouts: never an EventMatch,
       so the bracket, advancement, podiums, the running order and every W–L
       record are untouched by construction — there is no write path from here
       into any of them. */

    /**
     * Put two entrants on the mat as an exhibition.
     *
     * @param  array<string, mixed>  $payload  a, b — registration ids of this event
     */
    private function exhibition(ClubEvent $event, string $court, MatState $state, array $payload): void
    {
        if (! config('events.exhibitions', false)) {
            throw new \RuntimeException(__('scoreboard::karate_messages.ex_off'));
        }

        // Never over a bout whose clock is running — stop it first. A stopped
        // draw bout simply goes back to the queue: nothing of it was filed.
        if ($state->running) {
            throw new \RuntimeException(__('scoreboard::karate_messages.ex_running'));
        }

        $a = (int) ($payload['a'] ?? 0);
        $b = (int) ($payload['b'] ?? 0);

        if (! $a || ! $b || $a === $b) {
            throw new \RuntimeException(__('scoreboard::karate_messages.ctl_arc_same'));
        }

        // Both must be entries of THIS event — never another event's people.
        $regs = ClubEventRegistration::where('event_id', $event->id)
            ->whereIn('id', [$a, $b])
            ->with('user:id,full_name,name')
            ->get()->keyBy('id');

        if ($regs->count() !== 2) {
            throw new \RuntimeException(__('scoreboard::karate_messages.ex_not_entrants'));
        }

        // The name the draw prints for them, if the draw has one.
        $nameOf = function (int $id) use ($event, $regs): string {
            $m = EventMatch::where('event_id', $event->id)
                ->where(fn ($q) => $q->where('a_competitor_id', $id)->orWhere('b_competitor_id', $id))
                ->first(['a_competitor_id', 'a_name', 'b_name']);
            $drawn = $m ? ($m->a_competitor_id === $id ? $m->a_name : $m->b_name) : null;
            $user = $regs->get($id)?->user;

            return (string) ($drawn ?: ($user?->full_name ?: ($user?->name ?? '')));
        };

        $bout = \Illuminate\Support\Facades\DB::transaction(function () use ($event, $court, $a, $b, $nameOf) {
            $number = (int) EventExhibitionBout::where('event_id', $event->id)->lockForUpdate()->max('number') + 1;

            return EventExhibitionBout::create([
                'event_id' => $event->id,
                'number' => $number,
                'court' => $court,
                'a_registration_id' => $a,
                'b_registration_id' => $b,
                'a_name' => mb_substr($nameOf($a), 0, 255),
                'b_name' => mb_substr($nameOf($b), 0, 255),
                'status' => EventExhibitionBout::STATUS_RUNNING,
                'created_by' => \Illuminate\Support\Facades\Auth::id(),
            ]);
        });

        // Whatever was on the mat goes back where it came from first: a draw
        // bout simply returns to the queue (nothing was filed), an unfinished
        // exhibition is recorded as abandoned.
        $this->abandonExhibition($state);

        $match = $this->exhibitionAsMatch($event, $bout->id);

        $registrations = ClubEventRegistration::where('event_id', $event->id)
            ->whereIn('id', [$a, $b])
            ->with(['user:id,full_name,name,gender,birthdate,nationality,height_cm,profile_picture,profile_picture_is_public',
                'user.certifications:id,user_id,title,issue_date',
                'user.skillAcquisitions:id,user_id,proficiency_level,start_date',
                'user.memberClubs:id,club_name,logo,country',
                'representingTenant:id,club_name,logo,country'])
            ->get()->keyBy('id');

        $written = \App\Events\Support\EntryClub::forMany($event, [$a, $b]);

        $state->mode = MatState::MODE_VS;
        $state->matchId = null;
        $state->exhibitionId = $bout->id;
        $state->matchNo = $bout->code();
        $state->stage = __('scoreboard::karate_messages.ex_stage');
        $state->division = '';
        $state->referee = $this->officiatingName($event);
        $state->aka = $this->corner($match, 'a', $registrations, $written);
        $state->ao = $this->corner($match, 'b', $registrations, $written);

        // A fresh bout, exactly as load() starts one.
        $state->duration = match (true) {
            array_key_exists('seconds', $payload) => max(30, round((float) $payload['seconds'])),
            array_key_exists('minutes', $payload) => max(30, (float) $payload['minutes'] * 60),
            default => max(30, $state->duration ?: 180),
        };
        $state->remaining = $state->duration;
        $state->warning = min($state->warning, $state->duration);
        $state->tally = [];
        $state->akaScore = $state->aoScore = 0;
        $state->akaPen = $state->aoPen = 0;
        $state->akaSenshu = $state->aoSenshu = false;
        $state->running = false;
        $state->finished = false;
        $state->celebrationClosed = false;
        $state->awaitingDecision = false;
        $state->lastEvent = null;
        $state->winner = null;
        $state->winReason = null;
        $state->winNote = null;
    }

    /**
     * An exhibition, as an UNSAVED EventMatch — only so corner() can announce
     * its two entrants exactly as it announces a draw bout's. Never saved.
     */
    private function exhibitionAsMatch(ClubEvent $event, int $id): ?EventMatch
    {
        $bout = EventExhibitionBout::where('event_id', $event->id)->find($id);

        if (! $bout) {
            return null;
        }

        $m = new EventMatch;
        $m->a_competitor_id = $bout->a_registration_id;
        $m->b_competitor_id = $bout->b_registration_id;
        $m->a_name = $bout->a_name;
        $m->b_name = $bout->b_name;

        return $m;
    }

    /** File an exhibition's result on its own row, then clear the mat. */
    private function commitExhibition(MatState $state): void
    {
        if (! $state->akaLeads() && ! $state->aoLeads()) {
            throw new \RuntimeException(__('scoreboard::karate_messages.commit_level'));
        }

        EventExhibitionBout::whereKey($state->exhibitionId)
            ->where('status', EventExhibitionBout::STATUS_RUNNING)
            ->update([
                'winner' => $state->akaLeads() ? 'a' : 'b',
                'a_score' => (string) $state->akaScore,
                'b_score' => (string) $state->aoScore,
                'win_reason' => $state->winner !== null ? ($state->winReason ?: 'other') : null,
                'win_note' => $state->winner !== null ? $state->winNote : null,
                'status' => EventExhibitionBout::STATUS_DONE,
                'finished_by' => \Illuminate\Support\Facades\Auth::id(),
                'finished_at' => now(),
                'updated_at' => now(),
            ]);

        // Filed — so clearing the mat must not mark it abandoned.
        $state->exhibitionId = null;
        $this->clear($state);
    }

    /** An exhibition leaving the mat unfinished is recorded as abandoned. */
    private function abandonExhibition(MatState $state): void
    {
        if (! $state->exhibitionId) {
            return;
        }

        EventExhibitionBout::whereKey($state->exhibitionId)
            ->where('status', EventExhibitionBout::STATUS_RUNNING)
            ->update(['status' => EventExhibitionBout::STATUS_ABANDONED, 'updated_at' => now()]);

        $state->exhibitionId = null;
    }

    /** 'aka' or 'ao', or null — never a side name the caller invented. */
    protected function side(array $payload): ?string
    {
        $side = strtolower((string) ($payload['side'] ?? ''));

        return in_array($side, ['aka', 'ao'], true) ? $side : null;
    }
}
