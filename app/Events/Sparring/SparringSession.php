<?php

namespace App\Events\Sparring;

use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventCategory;
use App\Models\EventMatch;
use App\Models\EventOfficial;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A sparring session: the coach says "let's fight", and there is a scoreboard.
 *
 * ── Why this is an EVENT ────────────────────────────────────────────────────
 *
 * Nothing about training is an event in the ordinary sense — nobody enters, no
 * medal is awarded, and it is over in an hour. But every screen that makes a
 * mat work is keyed by an event and a court: MatState, the pairing tokens, the
 * MQTT channels, the scoring table, the VS introduction. Making the scoreboard's
 * owner polymorphic would mean rewriting all of that for a session that lasts
 * an afternoon, and the only thing gained would be a tidier diagram.
 *
 * So a session IS a ClubEvent — internal, unlisted, opened in one tap and shut
 * the same way. Everything the hall already knows how to do then works with no
 * new plumbing: a screen pairs to it, a tablet scores on it, the board follows.
 *
 * ── What it is NOT ──────────────────────────────────────────────────────────
 *
 * There is no draw, so no bout is derived from another and nothing advances. A
 * bout exists because a coach put two people on a mat, and the result is a fact
 * about that bout alone. That is the whole difference from a tournament, and it
 * is why this package implements recordOutcome() itself rather than borrowing
 * the championship's.
 */
class SparringSession
{
    /** The one division every sparring bout hangs on. */
    public const DIVISION = 'Sparring';

    /** `event_matches.round` for a bout that belongs to no round at all. */
    public const ROUND = 'sparring';

    /** How many mats a club can plausibly run at once. */
    public const MAX_MATS = 8;

    /**
     * Today's open session for this club, or null.
     *
     * Resumed rather than re-created, because the coach who reaches for the
     * scoreboard twice in one afternoon means the same session both times — and
     * a second one would strand every screen already paired to the first.
     */
    public function todaysFor(Tenant $club): ?ClubEvent
    {
        return ClubEvent::where('tenant_id', $club->id)
            ->where('event_type', Sparring::TYPE)
            ->whereDate('date', now()->toDateString())
            ->where('is_archived', false)
            ->where('status', '!=', 'cancelled')
            ->latest('id')
            ->first();
    }

    /**
     * Open a session — or hand back the one already running.
     *
     * It starts STARTED. A training session has no enrolment window, no draw to
     * lock and nobody to announce it to; the coach is standing on the mat. So
     * `started_at` is stamped here and the session is live from its first
     * second, which is also what makes the scoreboard reachable immediately.
     */
    public function openFor(Tenant $club, User $coach, string $sport, int $mats = 1, int $minutes = 3): ClubEvent
    {
        if ($existing = $this->todaysFor($club)) {
            return $existing;
        }

        $mats = max(1, min(self::MAX_MATS, $mats));

        $event = new ClubEvent([
            'tenant_id' => $club->id,
            'title' => __('event-sparring::messages.session_title', ['date' => now()->isoFormat('D MMM')]),
            'date' => now()->toDateString(),
            'start_time' => now()->format('H:i'),
            'event_type' => Sparring::TYPE,
            'sport' => $sport,
            // Internal, always. A training session is for the people in the
            // room; it is not advertised to a country and never carries a fee.
            'scope' => 'internal',
            'status' => 'active',
            'courts' => $mats,
            'minutes_per_match' => max(1, $minutes),
            'color' => '#0EA5E9',
            'icon' => 'bi-lightning-charge',
            'location' => $club->club_name,
            'created_by' => $coach->id,
        ]);

        // Not fillable — a session is live the moment it is opened, and that is
        // this package's decision, not something a request may post.
        $event->started_at = now();
        $event->started_by = $coach->id;
        $event->save();

        $this->division($event);
        $this->appointCoaches($event, $club, $coach);

        return $event;
    }

    /**
     * Everyone who can also score this session.
     *
     * EventAccess::canManage is the event's CREATOR alone, which is right for a
     * championship and wrong for a Tuesday: the coach who opened the session is
     * often not the one who ends up at the table. So the club's owner and its
     * admins are appointed as organisers here — a role EventAccess::canScore
     * already honours — and nobody else is. It grants scoring on this one
     * session and nothing else, to people the club already trusts with the club.
     */
    private function appointCoaches(ClubEvent $event, Tenant $club, User $coach): void
    {
        $ids = collect([$club->owner_user_id])
            ->merge(
                DB::table('user_roles')
                    ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                    ->where('user_roles.tenant_id', $club->id)
                    ->whereIn('roles.name', ['club-admin', 'instructor'])
                    ->pluck('user_roles.user_id')
            )
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $id === $coach->id)
            ->unique();

        foreach ($ids as $id) {
            EventOfficial::firstOrCreate(
                ['event_id' => $event->id, 'user_id' => $id, 'role' => EventOfficial::ROLE_ORGANISER],
                ['assigned_by' => $coach->id, 'compensation' => EventOfficial::COMP_VOLUNTEER],
            );
        }
    }

    /** The session's single division. Bouts need one; sparring has no others. */
    public function division(ClubEvent $event): EventCategory
    {
        return EventCategory::firstOrCreate(
            ['event_id' => $event->id, 'name' => self::DIVISION],
            ['status' => 'enrolling', 'sort_order' => 0],
        );
    }

    /** "Mat 1" … "Mat N" — the session's mats, which exist before any bout does. */
    public function mats(ClubEvent $event): array
    {
        $n = max(1, (int) ($event->courts ?: 1));

        return collect(range(1, $n))
            ->map(fn (int $i) => __('event-sparring::messages.mat_n', ['n' => $i]))
            ->all();
    }

    /**
     * The mats a scoring table can actually be opened on.
     *
     * The sport's console resolves its mat list from the bouts on it, and 404s
     * on a mat that has none — reasonable for a championship, where every mat
     * comes off a draw, and a trap here, where a coach adds Mat 2 and taps its
     * table before pairing anybody on it. So the console only offers the link
     * once the mat has a bout, and says why when it does not.
     */
    public function readyMats(ClubEvent $event): array
    {
        return EventMatch::where('event_id', $event->id)
            ->whereNotNull('court')
            ->distinct()
            ->pluck('court')
            ->all();
    }

    /** Mats can be added while the session runs — a second one opens up. */
    public function setMats(ClubEvent $event, int $mats): void
    {
        $event->courts = max(1, min(self::MAX_MATS, $mats));
        $event->save();
    }

    /**
     * Put people on the floor.
     *
     * Only members of the host club, and that is a security boundary rather
     * than a convenience: the entrant list decides whose face and name a wall
     * screen prints, so it may never be a list of arbitrary platform user ids
     * posted by whoever holds the console.
     */
    public function addEntrants(ClubEvent $event, array $userIds): Collection
    {
        $allowed = User::whereIn('id', array_map('intval', $userIds))
            ->whereHas('memberClubs', fn ($q) => $q->whereKey($event->tenant_id))
            ->pluck('id');

        $division = $this->division($event);

        foreach ($allowed as $id) {
            ClubEventRegistration::firstOrCreate(
                ['event_id' => $event->id, 'user_id' => $id],
                [
                    'status' => 'joined',
                    'role' => 'participant',
                    'registered_at' => now(),
                    'category_id' => $division->id,
                    // Training is never charged for. Marked settled so no
                    // session can ever surface as an unpaid entry.
                    'paid' => true,
                ],
            );
        }

        return $this->entrants($event);
    }

    /** Take somebody off the floor — but never mid-bout. */
    public function removeEntrant(ClubEvent $event, int $registrationId): void
    {
        $entry = ClubEventRegistration::where('event_id', $event->id)->find($registrationId);

        if (! $entry) {
            return;
        }

        $queued = EventMatch::where('event_id', $event->id)
            ->whereNull('winner')
            ->where(fn ($q) => $q->where('a_competitor_id', $entry->id)->orWhere('b_competitor_id', $entry->id))
            ->exists();

        if ($queued) {
            throw new \RuntimeException(__('event-sparring::messages.entrant_queued'));
        }

        $entry->delete();
    }

    /**
     * Who is here, as the console lists them.
     *
     * Photos follow the same rule as everywhere else: a member's own picture is
     * shown only if they published it, because the next thing the console does
     * with it is put it on a wall.
     */
    public function entrants(ClubEvent $event): Collection
    {
        return ClubEventRegistration::where('event_id', $event->id)
            ->where('role', 'participant')
            ->with(['user:id,full_name,name,gender,birthdate,profile_picture,profile_picture_is_public'])
            ->get()
            ->map(fn (ClubEventRegistration $r) => [
                'id' => $r->id,
                'name' => $r->user?->full_name ?: $r->user?->name ?: '',
                'gender' => $r->user?->gender,
                'age' => $r->user?->birthdate ? $r->user->birthdate->age : null,
                'photo' => ($r->user?->profile_picture && $r->user->profile_picture_is_public)
                    ? asset('storage/'.$r->user->profile_picture)
                    : null,
                'bouts' => $this->boutCount($event, $r->id),
            ])
            ->sortBy('name')
            ->values();
    }

    /** How many bouts this person has already had today — the coach's fairness check. */
    private function boutCount(ClubEvent $event, int $registrationId): int
    {
        return EventMatch::where('event_id', $event->id)
            ->whereNotNull('winner')
            ->where(fn ($q) => $q->where('a_competitor_id', $registrationId)->orWhere('b_competitor_id', $registrationId))
            ->count();
    }

    /**
     * Queue a bout on a mat.
     *
     * This is the sparring answer to a draw: there is no bracket to derive the
     * pairing from, so the coach makes it. Both corners must be entrants of
     * THIS session (never a raw user id), the mat must be one this session runs,
     * and the two must be different people — each check is here rather than in
     * the console, because the console is a convenience and this is the door.
     */
    public function queueBout(ClubEvent $event, int $aId, int $bId, string $mat, ?float $minutes = null): EventMatch
    {
        if ($aId === $bId) {
            throw new \RuntimeException(__('event-sparring::messages.same_person'));
        }

        if (! in_array($mat, $this->mats($event), true)) {
            throw new \RuntimeException(__('event-sparring::messages.unknown_mat'));
        }

        $entries = ClubEventRegistration::where('event_id', $event->id)
            ->whereIn('id', [$aId, $bId])
            ->with('user:id,full_name,name')
            ->get()->keyBy('id');

        if ($entries->count() !== 2) {
            throw new \RuntimeException(__('event-sparring::messages.unknown_entrant'));
        }

        $division = $this->division($event);

        return EventMatch::create([
            'event_id' => $event->id,
            'category_id' => $division->id,
            'round' => self::ROUND,
            'slot' => 0,
            'court' => $mat,
            'match_no' => $this->nextBoutNo($event),
            // NULL on purpose: RunningOrder buckets bouts by competition day,
            // and a session that has no days must never fall outside "today".
            'day' => null,
            'status' => 'upcoming',
            'a_competitor_id' => $aId,
            'b_competitor_id' => $bId,
            // Denormalised for the boards, exactly as the scheduler does it for
            // a championship — the wall reads names, not joins.
            'a_name' => $this->nameOf($entries->get($aId)),
            'b_name' => $this->nameOf($entries->get($bId)),
        ]);
    }

    /** Un-queue a bout that has not been fought. A fought one is a record. */
    public function unqueue(ClubEvent $event, int $matchId): void
    {
        $match = EventMatch::where('event_id', $event->id)->find($matchId);

        if (! $match) {
            return;
        }

        if ($match->winner || $match->status === 'done') {
            throw new \RuntimeException(__('event-sparring::messages.bout_fought'));
        }

        $match->delete();
    }

    /** The session's next bout number, counted across every mat. */
    private function nextBoutNo(ClubEvent $event): int
    {
        return (int) EventMatch::where('event_id', $event->id)->max('match_no') + 1;
    }

    private function nameOf(?ClubEventRegistration $entry): string
    {
        return $entry?->user?->full_name ?: $entry?->user?->name ?: '';
    }

    /**
     * What was fought, newest first — the session's whole output.
     *
     * A sparring result is deliberately shallow: two names, a score, and why it
     * ended. Nothing is written to anybody's record from here, because a
     * training bout is not a competitive result and putting it on a profile
     * beside real medals would make both harder to read.
     */
    public function bouts(ClubEvent $event, ?string $mat = null): Collection
    {
        return EventMatch::where('event_id', $event->id)
            ->when($mat, fn ($q) => $q->where('court', $mat))
            ->orderByDesc('id')
            ->get()
            ->map(fn (EventMatch $m) => [
                'id' => $m->id,
                'no' => $m->match_no,
                'mat' => $m->court,
                'aka' => $m->a_name,
                'ao' => $m->b_name,
                'aka_id' => $m->a_competitor_id,
                'ao_id' => $m->b_competitor_id,
                'aka_score' => $m->a_score,
                'ao_score' => $m->b_score,
                'winner' => $m->winner,
                'reason' => $m->win_reason,
                'note' => $m->win_note,
                'done' => $m->winner !== null || $m->status === 'done',
            ]);
    }

    /**
     * Shut the session.
     *
     * Archived rather than deleted: the bouts that were fought stay readable
     * for as long as anybody wants them, and archiving is what takes the
     * session off every listing. Screens paired to it are left alone — they are
     * the club's hardware, and they go back to a pairing code by themselves
     * when the session they point at stops answering.
     */
    public function close(ClubEvent $event): void
    {
        $event->is_archived = true;
        $event->status = 'completed';
        $event->end_time = now()->format('H:i');
        $event->save();
    }
}
