<?php

namespace App\Events\Sports\Taekwondo\Tournament\Scoreboard;

use App\Events\EventTypeRegistry;
use App\Events\Sports\Taekwondo\Tournament\RunningOrder;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventMatch;
use App\Models\EventOfficial;
use App\Sports\Combat\BeltRank;

/**
 * Applies what an official does at the table to the state of a Taekwondo mat.
 *
 * Every rule lives here rather than in the control page's JavaScript, for one
 * reason: the control page is not the only thing that can be wrong. A second
 * official opening the page, a laptop reconnecting mid-match, a screen catching
 * up after a dropped socket — all of them must arrive at the SAME score, and
 * they only do if the state is computed in one place and pushed out.
 *
 * The control page therefore sends intentions ("aka landed a turning head
 * kick"), never results ("aka now has 12"). A client that has fallen behind
 * cannot overwrite the truth with a stale total.
 *
 * ── What is different from Karate's Scoring ─────────────────────────────────
 * A WT match is a series, so the two things this has that Karate's does not are
 * a ROUND boundary and a way to cross it. Points and gam-jeom belong to the
 * round and die with it; rounds won belong to the match. Crossing the boundary
 * is an explicit command rather than something the clock does by itself, for
 * the same reason Karate refuses to break a level bout: at the end of a round
 * the officials' table has a decision to make, and software that guesses it
 * would be inventing a result.
 */
class Scoring
{
    /** Every command an official can issue. Anything else is rejected. */
    public const COMMANDS = [
        'load',         // put a match on the screen (mode → vs)
        'start',        // start the clock — also leaves the VS introduction
        'pause',        // stop the clock
        'score',        // {side, action} — one of MatState::ACTIONS
        'adjust',       // {side, n} — the ±1 correction on the approved control
        'gamjeom',      // {side, dir} — given or taken back; +1 to the opponent
        'time',         // {remaining} — the official corrects the clock
        'rest',         // start the break between rounds
        'award_round',  // {side?} — close the round; omit side to use the score
        'golden',       // start the golden round
        'reset_round',  // this round back to 0–0, same round number
        'reset',        // the whole match back to the start, same competitors
        'undo',         // reverse the last scoring action
        'finish',       // stop the clock and call the round over
        'refresh',      // re-read both corners from the draw; touches nothing else
        'clear',        // take the match off the screen (mode → upcoming)
        'commit',       // WRITE the result, advance the bracket, call the next
        'duration',     // {minutes} — round length
        'rounds',       // {n} — how many rounds this match is
        'corner',       // {side, name, club, country, flag} — fix what is announced
        'meta',         // {tournament, division, category, matchNo, courtLabel, stage}
    ];

    /**
     * Commands that only mean something with a match on the mat.
     *
     * `load` and `clear` change WHICH match is there, and the configuration and
     * header commands prepare the mat before one arrives — those may run on an
     * empty mat. Everything else is refused, because the control page is a
     * convenience and this endpoint is the contract: a second laptop, a stale
     * tab or a replayed request must all be refused here.
     */
    private const NEEDS_BOUT = [
        'start', 'pause', 'score', 'adjust', 'gamjeom', 'time', 'rest',
        'award_round', 'golden', 'reset_round', 'reset', 'undo', 'finish', 'commit', 'refresh',
    ];

    /** The undo list is carried in every payload to every screen. */
    private const LOG_LIMIT = 12;

    /**
     * May a point be scored, taken away, or a gam-jeom given, right now?
     *
     * The rest is the case that matters and the one that is easy to get wrong.
     * The board keeps the finished round's score up during the break — that is
     * what a hall expects to see — but those numbers are now a RESULT, not a
     * running total, and a stray keypress must not add to them. `finished` does
     * not cover it: awardRound() sets it and then opens the rest, which clears
     * it again, so the only reliable test is the phase itself.
     *
     * A decided match is closed for the same reason.
     */
    private function scoringOpen(MatState $state): bool
    {
        return ! $state->matchOver
            && ! $state->finished
            && $state->phase !== MatState::PHASE_REST;
    }

    public function __construct(private BeltRank $belts, private \App\Events\Sports\Taekwondo\Tournament\CompetitorPhoto $photos) {}

    /**
     * Apply one command and hand back the new state.
     *
     * @param  array<string, mixed>  $payload
     */
    public function apply(ClubEvent $event, string $court, string $command, array $payload = []): MatState
    {
        $state = MatState::load($event, $court);

        if (in_array($command, self::NEEDS_BOUT, true) && ! $state->matchId) {
            throw new \RuntimeException(__('event-taekwondo_tournament::messages.commit_no_bout'));
        }

        // The clock is stored as "remaining as of a moment"; before anything
        // else touches it, bring it up to now. Otherwise a pause five seconds
        // after a start would record the time as though nothing had elapsed.
        $this->settleClock($state);

        match ($command) {
            'load' => $this->load($event, $court, $state, (int) ($payload['match_id'] ?? 0), $payload),
            'start' => $this->start($state),
            'pause' => $this->pause($state),
            'score' => $this->score($state, $payload),
            'adjust' => $this->adjust($state, $payload),
            'gamjeom' => $this->gamjeom($state, $payload),
            'time' => $this->time($state, $payload),
            'rest' => $this->rest($state),
            'award_round' => $this->awardRound($state, $payload),
            'golden' => $this->golden($state),
            'reset_round' => $this->resetRound($state),
            'reset' => $this->resetMatch($state),
            'undo' => $this->undo($state),
            'finish' => $this->finish($state),
            'refresh' => $this->refresh($event, $state),
            'clear' => $this->clear($state),
            'commit' => $this->commit($event, $court, $state),
            'duration' => $this->duration($state, $payload),
            'rounds' => $this->rounds($state, $payload),
            'corner' => $this->cornerEdit($state, $payload),
            'meta' => $this->meta($state, $payload),
            default => null,
        };

        return $state->save($event, $court);
    }

    /* ---------------- The clock ---------------- */

    /**
     * Charge elapsed time against a running clock, and stop it at zero.
     *
     * A rest running out is not the same event as a round running out: the rest
     * simply ends and the next round is ready to start, whereas a round ending
     * closes the round and hands the table a decision.
     */
    private function settleClock(MatState $state): void
    {
        if (! $state->running || ! $state->at) {
            return;
        }

        $elapsed = max(0, now()->diffInMilliseconds($state->at, absolute: true) / 1000);
        $state->remaining = round(max(0, $state->remaining - $elapsed), 1);

        if ($state->remaining > 0) {
            return;
        }

        $state->running = false;

        if ($state->phase === MatState::PHASE_REST) {
            $this->beginRound($state);

            return;
        }

        $state->finished = true;
    }

    /* ---------------- Commands ---------------- */

    /**
     * Put a match on the mat.
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
        $registrations = $this->registrationsFor($event, $match);

        $state->mode = MatState::MODE_VS;
        $state->matchId = $match->id;
        $state->matchNo = $match->match_no !== null ? (string) $match->match_no : null;
        $state->stage = (string) ($match->phase ?: $match->round ?: '');
        $state->division = (string) ($match->category?->weight_class ?: $match->category?->name ?: '');
        $state->category = (string) ($match->category?->name ?: '');
        $state->referee = $this->officiatingName($event);
        $state->aka = $this->corner($match, 'a', $registrations, $event);
        $state->ao = $this->corner($match, 'b', $registrations, $event);

        // A fresh match: nothing carries over from whoever was on this mat.
        $state->rounds = max(1, min(5, (int) ($payload['rounds'] ?? $state->rounds ?: 3)));
        $minutes = (float) ($payload['minutes'] ?? ($state->duration / 60) ?: 2);
        $state->duration = max(30, $minutes * 60);
        $this->resetMatch($state);
    }

    /** Both corners' entries, with everything the screens announce. */
    private function registrationsFor(ClubEvent $event, EventMatch $match)
    {
        return ClubEventRegistration::where('event_id', $event->id)
            ->whereIn('id', array_filter([$match->a_competitor_id, $match->b_competitor_id]))
            ->with(['user:id,full_name,name,gender,birthdate,nationality,height_cm,profile_picture,profile_picture_is_public',
                'user.certifications:id,user_id,title,issue_date',
                'user.skillAcquisitions:id,user_id,proficiency_level,start_date',
                'user.memberClubs:id,club_name,logo,country'])
            ->get()->keyBy('id');
    }

    /**
     * Re-read both corners from the draw, and change nothing else.
     *
     * This is what the console's "Refresh VS screen" sends after an official
     * has added a competitor's picture at the desk. It deliberately does NOT
     * go through load(): load() is "put a different match on this mat" and
     * resets the score, the clock and the rounds with it, which mid-match would
     * be a catastrophe. Everything about the CONTEST is left exactly as it is;
     * only who the screens say is fighting is refreshed.
     *
     * Any hand-typed correction an official made with the corner editor is
     * overwritten, because the point of pressing this is to take what the
     * database now holds.
     */
    private function refresh(ClubEvent $event, MatState $state): void
    {
        $match = EventMatch::where('event_id', $event->id)
            ->with('category:id,name,weight_class')
            ->find($state->matchId);

        if (! $match) {
            return;
        }

        $registrations = $this->registrationsFor($event, $match);
        $state->aka = $this->corner($match, 'a', $registrations, $event);
        $state->ao = $this->corner($match, 'b', $registrations, $event);
    }

    /**
     * The appointed jury, as the introduction announces them.
     *
     * Abbreviated to an initial and a surname ("S. Petrov") — the convention on
     * a competition screen, and what the layout is sized for. Nobody appointed
     * returns null and the chip is hidden, because a hall screen naming a
     * referee who was never assigned is worse than one that names none.
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
    private function corner(EventMatch $match, string $side, $registrations, ClubEvent $event): array
    {
        $id = $match->{$side.'_competitor_id'};
        $reg = $id ? $registrations->get($id) : null;
        $user = $reg?->user;
        $club = $user?->memberClubs->first();
        $belt = $user ? $this->belts->for($user, $reg) : null;

        return [
            'name' => $match->{$side.'_name'} ?: ($user?->full_name ?? $user?->name ?? ''),
            'club' => $club?->club_name ?? '',
            'country' => $user?->nationality ?: ($match->{$side.'_country'} ?: ''),
            'flag' => strtolower((string) ($match->{$side.'_country'} ?: $user?->nationality ?: $club?->country ?: '')) ?: null,
            // Same resolver as the picture: the crest an official supplied for
            // this event first, then one supplied for this club anywhere in it,
            // then the club's own logo.
            'logo' => $this->photos->crestUrl($reg, $club, $event->id),
            // One resolver for every screen — the event's own photo first, then
            // one this person already has on another entry in this event, then
            // their account picture only if they published it. See
            // App\Events\Sports\Taekwondo\Tournament\CompetitorPhoto.
            'photo' => $this->photos->url($reg, $user, $event->id),
            'belt' => $belt['label'] ?? null,
            'record' => $this->record($user?->id),
            // Each part is null when unknown, and the screen hides what is null
            // rather than printing a placeholder — invented detail on a hall
            // screen reads as fact.
            'age' => $user?->birthdate ? $user->birthdate->age : null,
            'height' => $user?->height_cm ?: null,
            'weight' => $reg?->weight ? (float) $reg->weight : null,
        ];
    }

    private function start(MatState $state): void
    {
        if ($state->matchOver || $state->remaining <= 0) {
            return;
        }

        // Starting the clock is also what ends the introduction — the VS screen
        // animates itself out and the scoreboard is behind it.
        $state->mode = MatState::MODE_SCOREBOARD;
        $state->running = true;
        $state->finished = false;
    }

    private function pause(MatState $state): void
    {
        $state->running = false;
    }

    /**
     * A technique lands.
     *
     * Then the two ways a round can stop by itself are checked, because both
     * are consequences of the point that was just scored and neither should
     * wait for the clock: the point gap, and — via a gam-jeom's own point — the
     * moment a round becomes unwinnable.
     */
    private function score(MatState $state, array $payload): void
    {
        $side = $this->side($payload);
        $action = (string) ($payload['action'] ?? '');

        if (! $side || ! isset(MatState::ACTIONS[$action]) || ! $this->scoringOpen($state)) {
            return;
        }

        $n = MatState::ACTIONS[$action]['value'];
        $field = $side.'Score';
        $state->$field += $n;

        $state->lastEvent = ['side' => $side, 'n' => $n, 'label' => MatState::ACTIONS[$action]['label'], 'ts' => $this->now()];
        $this->push($state, ['type' => 'score', 'side' => $side, 'n' => $n], $this->cornerName($state, $side).' · '.MatState::ACTIONS[$action]['label'].' +'.$n);

        $this->checkRoundEnd($state);
    }

    /** The ±1 correction the approved control puts beside each score. */
    private function adjust(MatState $state, array $payload): void
    {
        $side = $this->side($payload);
        $n = (int) ($payload['n'] ?? 0);

        if (! $side || $n === 0 || ! $this->scoringOpen($state)) {
            return;
        }

        $n = $n > 0 ? 1 : -1;
        $field = $side.'Score';
        $before = $state->$field;
        $state->$field = max(0, $before + $n);

        // A correction at zero changes nothing, so it must not be undoable —
        // an undo entry that reverses nothing is worse than no entry.
        if ($state->$field === $before) {
            return;
        }

        $this->push($state, ['type' => 'score', 'side' => $side, 'n' => $n], $this->cornerName($state, $side).' · correction '.($n > 0 ? '+1' : '−1'));
        $this->checkRoundEnd($state);
    }

    /**
     * A gam-jeom, given or taken back.
     *
     * It is two things at once and both have to move together: a mark against
     * the athlete who earned it, and a point to the other one. Taking it back
     * has to undo both, which is why this is one command and not two.
     */
    private function gamjeom(MatState $state, array $payload): void
    {
        $side = $this->side($payload);

        if (! $side || ! $this->scoringOpen($state)) {
            return;
        }

        $dir = ((int) ($payload['dir'] ?? 1)) >= 0 ? 1 : -1;
        $other = $side === 'aka' ? 'ao' : 'aka';
        $gam = $side.'Gam';
        $before = $state->$gam;

        $state->$gam = max(0, min(MatState::GAM_JEOM_LIMIT, $before + $dir));

        if ($state->$gam === $before) {
            return;
        }

        $opponent = $other.'Score';
        $state->$opponent = max(0, $state->$opponent + $dir);

        $this->push($state, ['type' => 'gamjeom', 'side' => $side, 'dir' => $dir],
            $this->cornerName($state, $side).' · gam-jeom '.($dir > 0 ? 'given' : 'withdrawn'));

        $this->checkRoundEnd($state);
    }

    /**
     * End the round when the rules end it — and award it, because in these
     * cases the rules also say WHO won.
     *
     * Only ever called after a scoring change. The clock's own expiry is
     * handled in settleClock, because that one happens without anybody
     * pressing anything, and time running out can leave a round LEVEL — which
     * is a judgement for the table, not something to decide here.
     *
     * These three are the opposite: each names a winner on its own.
     *
     *   · gam-jeom ceiling — five in a round hands it to the opponent, full stop
     *   · point gap        — twelve clear, the round is stopped and won
     *   · golden round     — the first point of any kind takes the match
     *
     * Awarding automatically because there is nothing to decide. Leaving it to
     * the operator meant the clock simply stopped with the console still
     * reading "Round 1" and no indication that anything had happened — five
     * gam-jeom went in and the match sat there. A rule the software knows and
     * the screen does not show is worse than no rule.
     */
    private function checkRoundEnd(MatState $state): void
    {
        // PUN — the gam-jeom ceiling. This one ends the MATCH, not the round:
        // the opponent is declared the winner wherever the series stands, so
        // there is no round to award and nothing further to fight.
        $pun = match (true) {
            $state->akaGam >= MatState::GAM_JEOM_LIMIT => 'ao',
            $state->aoGam >= MatState::GAM_JEOM_LIMIT => 'aka',
            default => null,
        };

        if ($pun !== null) {
            $state->running = false;
            $state->finished = true;
            $state->matchOver = true;
            $state->endReason = 'gamjeom';
            $state->punWinner = $pun;
            $state->lastEvent = null;

            // Deliberately NO log entry. PUN is a consequence, not something
            // the operator did — the gam-jeom that triggered it already has
            // its own undo entry, and a second one here would mean pressing
            // Undo twice to take back a single mis-click.

            return;
        }

        // The other two end the ROUND, and each names its own winner.
        $reason = match (true) {
            $state->phase === MatState::PHASE_GOLDEN && ($state->akaScore > 0 || $state->aoScore > 0) => 'golden',
            $state->pointGapReached() => 'gap',
            default => null,
        };

        if ($reason === null) {
            return;
        }

        $state->running = false;
        $state->endReason = $reason;
        $this->awardRound($state, ['side' => $state->roundWinner()]);
    }

    /**
     * Move the mat into the break between rounds.
     *
     * `$run` is the difference between the operator pressing "Rest 1:00" — they
     * asked for it, so it runs — and a round simply having been awarded, which
     * only puts the mat INTO the break and leaves the clock stopped.
     *
     * Nothing on this mat starts a clock except the person at the table. A
     * round award used to start the break counting by itself, so a timer the
     * operator had not touched was running in front of them; the approved
     * console has a Rest button precisely because starting it is their call.
     */
    private function rest(MatState $state, bool $run = true): void
    {
        $state->phase = MatState::PHASE_REST;
        $state->remaining = $state->restDuration;
        $state->running = $run;
        $state->finished = false;
    }

    /**
     * Close the round and give it to somebody.
     *
     * `side` is how the officials' table settles a level round: WT decides
     * those on superiority, which is a judgement made at the table and not
     * something this can compute from a score of 4–4. Without a side the round
     * goes to whoever the score and the gam-jeom ceiling say it does, and a
     * level round with no side named is refused rather than guessed.
     */
    private function awardRound(MatState $state, array $payload): void
    {
        // A decided match has no round left to give. Without this an operator
        // pressing the button twice hands out a fourth round in a best-of-three
        // and takes the score to 2–2, which then commits as a "win" that nobody
        // can read.
        if ($state->matchOver) {
            throw new \RuntimeException(__('event-taekwondo_tournament::messages.match_over'));
        }

        // Nor can a round be awarded during the break BEFORE it. The score on
        // the board through a rest belongs to the round that just finished, so
        // awarding here would hand the next round to whoever won the last one,
        // without a second of it having been fought.
        if ($state->phase === MatState::PHASE_REST) {
            throw new \RuntimeException(__('event-taekwondo_tournament::messages.round_not_running'));
        }

        $side = $this->side($payload) ?? $state->roundWinner();

        if (! $side) {
            throw new \RuntimeException(__('event-taekwondo_tournament::messages.round_level'));
        }

        $field = $side.'Rounds';
        $state->$field++;
        $state->running = false;
        $state->finished = true;
        $state->lastEvent = null;

        $this->push($state, ['type' => 'round', 'side' => $side],
            'Round '.$state->round.' → '.$this->cornerName($state, $side));

        // The match may be decided by that round.
        if ($state->matchWinner()) {
            $state->matchOver = true;

            return;
        }

        // Every round fought and still level → sudden death.
        if ($state->round >= $state->rounds) {
            $this->golden($state);

            return;
        }

        $state->round++;
        $this->rest($state, run: false);
    }

    /** Sudden death: a fresh clock and the first point takes the match. */
    private function golden(MatState $state): void
    {
        $state->phase = MatState::PHASE_GOLDEN;
        $state->akaScore = $state->aoScore = 0;
        $state->akaGam = $state->aoGam = 0;
        $state->remaining = $state->duration;
        $state->running = false;
        $state->finished = false;
        $state->lastEvent = null;
        $state->endReason = null;
    }

    /** Open a round after the rest — same numbers, clean slate. */
    private function beginRound(MatState $state): void
    {
        $state->phase = MatState::PHASE_ROUND;
        $state->akaScore = $state->aoScore = 0;
        // Gam-jeom deliberately NOT cleared: the ceiling is a match total, so
        // clearing it every round would make five unreachable and the rule
        // decorative. Only the round's points reset.
        $state->remaining = $state->duration;
        $state->running = false;
        $state->finished = false;
        $state->lastEvent = null;
        $state->endReason = null;
    }

    private function resetRound(MatState $state): void
    {
        $this->beginRound($state);
        $this->push($state, ['type' => 'none'], 'Round '.$state->round.' reset');
    }

    /** The whole match back to the start, same competitors. */
    private function resetMatch(MatState $state): void
    {
        $state->akaRounds = $state->aoRounds = 0;
        $state->akaGam = $state->aoGam = 0;
        $state->punWinner = null;
        $state->round = 1;
        $state->phase = MatState::PHASE_ROUND;
        $state->matchOver = false;
        $state->log = [];
        $this->beginRound($state);
    }

    /**
     * Reverse the last scoring action.
     *
     * Only the reversible kinds are on the list — a round award is not one of
     * them, because undoing it after the next round has started would leave the
     * match in a shape nobody at the table could reason about. The entry stays
     * visible so the operator can see it happened.
     */
    private function undo(MatState $state): void
    {
        $entry = array_shift($state->log);

        if (! $entry) {
            return;
        }

        $undo = $entry['undo'] ?? [];
        $side = $undo['side'] ?? null;

        if (! in_array($side, ['aka', 'ao'], true)) {
            return;
        }

        if (($undo['type'] ?? '') === 'score') {
            $field = $side.'Score';
            $state->$field = max(0, $state->$field - (int) ($undo['n'] ?? 0));
        }

        if (($undo['type'] ?? '') === 'gamjeom') {
            $dir = (int) ($undo['dir'] ?? 1);
            $gam = $side.'Gam';
            $opponent = ($side === 'aka' ? 'ao' : 'aka').'Score';
            $state->$gam = max(0, $state->$gam - $dir);
            $state->$opponent = max(0, $state->$opponent - $dir);
        }

        // A mis-clicked fifth gam-jeom ends the match. Taking it back has to
        // take the match back with it, or one stray press ends a fight that is
        // still being fought and nothing on the console can undo it.
        if ($state->punWinner !== null
            && $state->akaGam < MatState::GAM_JEOM_LIMIT
            && $state->aoGam < MatState::GAM_JEOM_LIMIT) {
            $state->punWinner = null;
            $state->matchOver = false;
            $state->finished = false;
            $state->endReason = null;
        }

        $state->lastEvent = null;
    }

    /** Push an undo entry, newest first, capped. */
    private function push(MatState $state, array $undo, string $message): void
    {
        array_unshift($state->log, [
            'msg' => $message,
            'time' => now()->format('H:i:s'),
            'undo' => $undo,
        ]);

        $state->log = array_slice($state->log, 0, self::LOG_LIMIT);
    }

    private function cornerName(MatState $state, string $side): string
    {
        return (string) (($side === 'aka' ? $state->aka : $state->ao)['name'] ?? strtoupper($side));
    }

    private function time(MatState $state, array $payload): void
    {
        $state->remaining = round(max(0, min($state->duration, (float) ($payload['remaining'] ?? 0))), 1);
        $state->finished = $state->remaining <= 0 && $state->finished;
    }

    /** Round length, in minutes. Resets the clock with it. */
    private function duration(MatState $state, array $payload): void
    {
        $state->duration = max(10, round((float) ($payload['minutes'] ?? 2) * 60));
        $state->remaining = $state->duration;
        $state->running = false;
        $state->finished = false;
    }

    /** How many rounds this match is — the approved control allows 1 to 5. */
    private function rounds(MatState $state, array $payload): void
    {
        $state->rounds = max(1, min(5, (int) ($payload['n'] ?? 3)));
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

    /** The header line: tournament, division, category, match number, mat, round. */
    private function meta(MatState $state, array $payload): void
    {
        foreach (['tournament', 'division', 'category', 'matchNo', 'courtLabel', 'stage', 'referee'] as $field) {
            if (array_key_exists($field, $payload) && $payload[$field] !== null) {
                $state->$field = trim((string) $payload[$field]) ?: null;
            }
        }
    }

    /**
     * Stop the clock and call the round over.
     *
     * Not during the rest: there is no round running to end, and marking one
     * finished there would let award_round through the guard it just failed.
     */
    private function finish(MatState $state): void
    {
        if ($state->phase === MatState::PHASE_REST || $state->matchOver) {
            return;
        }

        $state->running = false;
        $state->finished = true;
        $state->lastEvent = null;
    }

    /**
     * The result leaves the scoreboard and becomes a fact.
     *
     * Everything up to here has been scaffolding in a cache. This is the one
     * command that writes: it hands the match to the package's own
     * recordOutcome(), which records the scores, carries the winner into their
     * next match, closes the division's podium when a final lands, tells the
     * two athletes, and calls whoever is now due on this mat. Nothing about a
     * result is reimplemented here — a second path to writing a result is a
     * second set of rules to get wrong.
     *
     * The score recorded is ROUNDS, not points. "2–1" is the result of a WT
     * match; the aggregate points are an artefact of how it was won and are
     * routinely misleading, since a 12–0 round counts exactly as much as a 2–1
     * one.
     *
     * Refuses a match that has not been decided. A series is over when somebody
     * has won enough rounds, and nothing else — the table closes the last round
     * first, which is where a level one gets settled.
     */
    private function commit(ClubEvent $event, string $court, MatState $state): void
    {
        if (! $state->matchId) {
            throw new \RuntimeException(__('event-taekwondo_tournament::messages.commit_no_bout'));
        }

        $winner = $state->matchWinner();

        if (! $winner) {
            throw new \RuntimeException(__('event-taekwondo_tournament::messages.commit_undecided'));
        }

        app(EventTypeRegistry::class)->for($event)->recordOutcome($event, $state->matchId, [
            'winner' => $winner === 'aka' ? 'a' : 'b',
            'a_score' => (string) $state->akaRounds,
            'b_score' => (string) $state->aoRounds,
            'status' => 'done',
        ]);

        // Straight into the next one. The running order is recomputed by
        // recordOutcome above, so this is the match that is genuinely next —
        // including one the finished match just fed a competitor into. The SAME
        // queue the wall board announces (RunningOrder::matQueue); deciding
        // "next" independently here is exactly what made the table load a
        // different bout from the one the hall had just been told to expect.
        $order = new RunningOrder;
        $next = $order->matQueue($event, $court)
            ->first(fn (EventMatch $m) => $m->id !== $state->matchId && $order->isRunnable($m));

        $minutes = $state->duration / 60;
        $rounds = $state->rounds;

        if ($next) {
            $this->load($event, $court, $state, $next->id, ['minutes' => $minutes, 'rounds' => $rounds]);

            return;
        }

        $this->clear($state);
    }

    private function clear(MatState $state): void
    {
        $state->mode = MatState::MODE_UPCOMING;
        $state->matchId = null;
        $state->matchNo = $state->stage = $state->division = $state->category = null;
        $state->aka = $state->ao = [];
        $this->resetMatch($state);
    }

    /** 'aka' or 'ao', or null — never a side name the caller invented. */
    private function side(array $payload): ?string
    {
        $side = strtolower((string) ($payload['side'] ?? ''));

        return in_array($side, ['aka', 'ao'], true) ? $side : null;
    }

    private function now(): int
    {
        return (int) (microtime(true) * 1000);
    }
}
