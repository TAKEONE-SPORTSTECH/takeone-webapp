<?php

namespace App\Events\OpenMat;

use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventCategory;
use App\Models\EventMatch;
use App\Models\EventOfficial;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * An open mat: two people, a scoreboard, and nothing else in the way.
 *
 * ── What makes it different from a sparring session ─────────────────────────
 *
 * Sparring is a coach's session: a floor of people who are here, a queue of
 * bouts the coach pairs in advance, on a club's mats. An open mat starts one
 * step earlier than that. Nobody is "here". Two people decide to fight, and the
 * only thing that has to exist before the clock runs is their two names.
 *
 * So there is NO entrant list and NO queue. A mat has two corners; filling both
 * and tapping start IS the bout. A corner may be:
 *
 *   · a TAKEONE member the operator found by search
 *   · a member who took the corner themselves by scanning the mat's code
 *   · a stranger, typed in — no account, no invitation, nothing to sign up for
 *
 * That last one is not a shortcut, it is the feature. Requiring an account to
 * be scored is what keeps a scoreboard inside one club; not requiring one is
 * what lets it travel.
 *
 * ── Why it is still an event underneath ─────────────────────────────────────
 *
 * Everything that makes a mat work — MatState, the pairing tokens, the MQTT
 * channels, the VS introduction, the sport's scoring table — is keyed by an
 * event and a court. An open mat therefore IS a ClubEvent, opened in one tap
 * and archived the same evening, exactly as a sparring session is. The whole
 * hall then works with no new plumbing, and this package stays deletable.
 *
 * ── Why it hangs on a club ──────────────────────────────────────────────────
 *
 * `club_events.tenant_id` is NOT NULL and every event in the product has a
 * host, so an open mat is opened FOR one of the opener's own clubs — the same
 * rule the personal event-create form already applies, and the same message a
 * member with no club already sees. Any member may open one; they do not need
 * to be an admin, a coach or an organiser of anything. That is the difference
 * from Sparring, whose door is the club-admin console.
 */
class OpenMatSession
{
    /** The one division every open-mat bout hangs on. Bouts need one. */
    public const DIVISION = 'Open Mat';

    /** `event_matches.round` for a bout that belongs to no round at all. */
    public const ROUND = 'open_mat';

    /** How many mats one person can plausibly run at once. */
    public const MAX_MATS = 4;

    /** A join code outlives an afternoon and no more. */
    private const CODE_TTL_MINUTES = 240;

    /* ==================== Opening and closing ==================== */

    /**
     * The open mat this member already has running for this club today, or null.
     *
     * Resumed rather than re-created for the same reason a sparring session is:
     * somebody who reaches for the scoreboard twice in one afternoon means the
     * same mat both times, and a second one would strand every screen already
     * paired to the first.
     */
    public function todaysFor(User $opener, Tenant $club, string $sport): ?ClubEvent
    {
        return ClubEvent::where('tenant_id', $club->id)
            ->where('event_type', OpenMat::TYPE)
            ->where('sport', $sport)
            ->where('created_by', $opener->id)
            ->whereDate('date', now()->toDateString())
            ->where('is_archived', false)
            ->where('status', '!=', 'cancelled')
            ->latest('id')
            ->first();
    }

    /**
     * Open a mat — or hand back the one already running.
     *
     * It starts STARTED, like a sparring session: there is no enrolment window,
     * no draw to lock and nobody to announce it to. The person is standing on
     * the mat.
     */
    public function openFor(User $opener, Tenant $club, string $sport, int $mats = 1, int $minutes = 3): ClubEvent
    {
        if ($existing = $this->todaysFor($opener, $club, $sport)) {
            return $existing;
        }

        $event = new ClubEvent([
            'tenant_id' => $club->id,
            'title' => __('event-open_mat::messages.session_title', ['date' => now()->isoFormat('D MMM')]),
            'date' => now()->toDateString(),
            'start_time' => now()->format('H:i'),
            'event_type' => OpenMat::TYPE,
            'sport' => $sport,
            // Internal, always. An open mat is for the people in the room; it
            // is never advertised to a country and never carries a fee.
            'scope' => 'internal',
            'status' => 'active',
            'courts' => max(1, min(self::MAX_MATS, $mats)),
            'minutes_per_match' => max(1, $minutes),
            'color' => OpenMat::COLOR,
            'icon' => 'bi-fire',
            'location' => $club->club_name,
            'created_by' => $opener->id,
        ]);

        // Not fillable — a mat is live the moment it is opened, and that is
        // this package's decision, not something a request may post.
        $event->started_at = now();
        $event->started_by = $opener->id;
        $event->save();

        $this->division($event);

        // The opener scores their own mat. EventAccess::canManage already
        // grants the creator everything; this row is what makes the sport's
        // scoring table answer to them as an appointed organiser, which is the
        // same door a sparring session's coaches come through.
        EventOfficial::firstOrCreate(
            ['event_id' => $event->id, 'user_id' => $opener->id, 'role' => EventOfficial::ROLE_ORGANISER],
            ['assigned_by' => $opener->id, 'compensation' => EventOfficial::COMP_VOLUNTEER],
        );

        return $event;
    }

    /**
     * Shut the mat.
     *
     * Archived rather than deleted: what was fought stays readable, and the
     * casual records already filed are untouched by it.
     */
    public function close(ClubEvent $event): void
    {
        $event->is_archived = true;
        $event->status = 'completed';
        $event->end_time = now()->format('H:i');
        $event->save();

        // Shutting the mat retires its codes: they are printed on things and
        // read off screens, and one that still resolves after the mat is closed
        // is a way onto a floor that no longer exists. The rows go with it.
        foreach (OpenMatCode::where('event_id', $event->id)->get() as $row) {
            Cache::forget($this->lookupKey($row->code));
            $row->delete();
        }
    }

    /** The mat's single division. Bouts need one; an open mat has no others. */
    public function division(ClubEvent $event): EventCategory
    {
        return EventCategory::firstOrCreate(
            ['event_id' => $event->id, 'name' => self::DIVISION],
            ['status' => 'enrolling', 'sort_order' => 0],
        );
    }

    /* ==================== Mats ==================== */

    /** "Mat 1" … "Mat N". Mats exist before any bout does. */
    public function mats(ClubEvent $event): array
    {
        $n = max(1, (int) ($event->courts ?: 1));

        return collect(range(1, $n))
            ->map(fn (int $i) => __('event-open_mat::messages.mat_n', ['n' => $i]))
            ->all();
    }

    public function setMats(ClubEvent $event, int $mats): void
    {
        $event->courts = max(1, min(self::MAX_MATS, $mats));
        $event->save();
    }

    /** Refuse a court this mat does not run, rather than inventing one. */
    public function assertMat(ClubEvent $event, string $court): string
    {
        if (! in_array($court, $this->mats($event), true)) {
            throw new \RuntimeException(__('event-open_mat::messages.unknown_mat'));
        }

        return $court;
    }

    /* ==================== The floor ==================== */

    /**
     * Everybody who is here, and where they are standing.
     *
     * This is what makes the mat runnable without leaving the scoreboard. The
     * first cut had two corners and no memory of anyone else, so every new pair
     * meant navigating back to a console to find both people again — at an open
     * mat where eight people rotate through in an hour, that is the evening.
     *
     * Sorted by who has fought LEAST, because that is the decision the operator
     * is actually making when they look at this list: whose turn is it.
     */
    public function floor(ClubEvent $event): Collection
    {
        $standing = OpenMatCorner::where('event_id', $event->id)
            ->whereNotNull('person_id')
            ->get(['person_id', 'court', 'side'])
            ->keyBy('person_id');

        return OpenMatPerson::where('event_id', $event->id)
            ->with('user:id,gender,profile_picture,profile_picture_is_public')
            ->orderBy('bouts')
            ->orderBy('name')
            ->get()
            ->map(function (OpenMatPerson $p) use ($standing) {
                $at = $standing->get($p->id);

                return $p->present($at ? $at->court.'/'.$at->side : null);
            });
    }

    /** Put a MEMBER on the floor — searched for, and re-checked here. */
    public function addMember(ClubEvent $event, int $userId, User $actor): OpenMatPerson
    {
        $user = $this->findableBy($actor, $userId);

        if (! $user) {
            throw new \RuntimeException(__('event-open_mat::messages.person_not_findable'));
        }

        return $this->rememberMember($event, $user, OpenMatPerson::SOURCE_PICKED, $actor->id);
    }

    /**
     * Put a GUEST on the floor — somebody with no account, for tonight only.
     *
     * Nothing is created for them: no user, no invitation, no record. The name
     * reaches a wall screen, so it is cleaned and capped here rather than
     * trusted from the console.
     */
    public function addGuest(ClubEvent $event, string $name, ?string $country, User $actor): OpenMatPerson
    {
        $name = $this->cleanName($name);

        if ($name === '') {
            throw new \RuntimeException(__('event-open_mat::messages.name_required'));
        }

        return OpenMatPerson::create([
            'event_id' => $event->id,
            'user_id' => null,
            'name' => $name,
            'country' => $this->cleanCountry($country),
            'source' => OpenMatPerson::SOURCE_GUEST,
            'added_by' => $actor->id,
        ]);
    }

    /** A member joins the floor once; a second add finds the same row. */
    private function rememberMember(ClubEvent $event, User $user, string $source, ?int $actorId): OpenMatPerson
    {
        $person = OpenMatPerson::firstOrCreate(
            ['event_id' => $event->id, 'user_id' => $user->id],
            [
                'name' => $user->full_name ?: $user->name,
                'source' => $source,
                'added_by' => $actorId,
            ] + $this->clubFor($user),
        );

        // Their name may have been typed as a guest earlier in the evening and
        // corrected since; the account is authoritative once it is attached.
        if (! $person->wasRecentlyCreated) {
            // The account is authoritative for the name — and for the club they
            // are standing for, which is the thing most likely to have been
            // blank when they were typed in as a guest earlier in the evening.
            $person->fill(['name' => $user->full_name ?: $user->name] + $this->clubFor($user))->save();
        }

        return $person;
    }

    /** Take somebody off the floor — but never while they are in a corner. */
    public function removePerson(ClubEvent $event, int $personId): void
    {
        $person = OpenMatPerson::where('event_id', $event->id)->find($personId);

        if (! $person) {
            return;
        }

        if (OpenMatCorner::where('event_id', $event->id)->where('person_id', $person->id)->exists()) {
            throw new \RuntimeException(__('event-open_mat::messages.person_standing'));
        }

        $person->delete();
    }

    /* ==================== Corners ==================== */

    /** Both corners of one mat, as the console draws them. */
    public function corners(ClubEvent $event, string $court): array
    {
        $rows = OpenMatCorner::where('event_id', $event->id)
            ->where('court', $court)
            ->with('user:id,gender,profile_picture,profile_picture_is_public')
            ->get()
            ->keyBy('side');

        return [
            'aka' => $rows->get(OpenMatCorner::SIDE_AKA)?->present(),
            'ao' => $rows->get(OpenMatCorner::SIDE_AO)?->present(),
        ];
    }

    /**
     * Stand somebody from the floor in a corner.
     *
     * The one move the scoring table's panel makes, and the reason the floor
     * exists: two taps and the next pair is set, without the page ever changing.
     */
    /**
     * Put a person in a corner.
     *
     * ── Why `$selfJoin` exists ─────────────────────────────────────────────
     *
     * Two callers reach this method and they are not the same act. The OPERATOR
     * at the scoring table deliberately swaps people around: that is their job,
     * and a corner already holding somebody is exactly what they are correcting.
     * A JOINER holding the six-character code is doing something much weaker —
     * putting themselves forward — and must not be able to:
     *
     *   · replace a fighter who is already standing in that corner, or
     *   · touch either corner of a mat while a bout is being fought on it.
     *
     * Without this, anybody who could read the code off a wall screen could take
     * a named athlete off the board mid-bout, and the result would go into that
     * athlete's record. The code is printed in a hall; it is a convenience, not
     * a credential, and it must not carry an operator's authority.
     */
    public function assign(
        ClubEvent $event,
        string $court,
        string $side,
        int $personId,
        bool $selfJoin = false,
    ): array {
        $this->assertMat($event, $court);
        $side = $this->assertSide($side);

        $person = OpenMatPerson::where('event_id', $event->id)->findOrFail($personId);

        $this->assertNotAlreadyOn($event, $court, $side, $person);

        if ($selfJoin) {
            // A bout in progress freezes both corners. The board and the record
            // are being written from them right now.
            if ($this->hasLiveBout($event, $court)) {
                throw new \RuntimeException(__('event-open_mat::messages.join_bout_running'));
            }

            $held = OpenMatCorner::where('event_id', $event->id)
                ->where('court', $court)->where('side', $side)->first();

            // Taken by somebody else. Taken by THEM is fine — that is a re-tap,
            // and it should read as success rather than an error.
            if ($held && $held->user_id !== null && (int) $held->user_id !== (int) $person->user_id) {
                throw new \RuntimeException(__('event-open_mat::messages.join_corner_taken', ['name' => $held->name]));
            }

            if ($held && $held->user_id === null) {
                throw new \RuntimeException(__('event-open_mat::messages.join_corner_taken', ['name' => $held->name]));
            }
        }

        // Somebody can only stand in one corner at a time. Taking them from
        // wherever they were is what makes "put them in red" mean what it
        // looks like, rather than silently cloning them across two mats.
        OpenMatCorner::where('event_id', $event->id)
            ->where('person_id', $person->id)
            ->delete();

        OpenMatCorner::updateOrCreate(
            ['event_id' => $event->id, 'court' => $court, 'side' => $side],
            [
                'person_id' => $person->id,
                // Denormalised beside the pointer, exactly as event_matches
                // keeps a_name beside a_competitor_id: a board reads names.
                'user_id' => $person->user_id,
                'name' => $person->name,
                'country' => $person->country,
                'club' => $person->club,
                'source' => $person->source,
                'placed_by' => $person->added_by,
            ],
        );

        return $this->corners($event, $court);
    }

    /** Search-and-stand, in one move — what the phone console's sheet does. */
    public function placeMember(ClubEvent $event, string $court, string $side, int $userId, User $actor): array
    {
        $this->assertMat($event, $court);
        $this->assertSide($side);

        return $this->assign($event, $court, $side, $this->addMember($event, $userId, $actor)->id);
    }

    /** Type-and-stand, in one move. */
    public function placeGuest(ClubEvent $event, string $court, string $side, string $name, ?string $country, User $actor): array
    {
        $this->assertMat($event, $court);
        $this->assertSide($side);

        return $this->assign($event, $court, $side, $this->addGuest($event, $name, $country, $actor)->id);
    }

    /**
     * A member takes a corner themselves, having scanned the mat's code.
     *
     * The consent path: nobody searched for them and nobody typed their name —
     * they chose to stand there. It is also the only way somebody from another
     * club, another country, or an account nobody can search reaches a corner,
     * which is what makes this work outside one club's walls.
     */
    public function takeCorner(ClubEvent $event, string $court, string $side, User $joiner): array
    {
        $this->assertMat($event, $court);
        $this->assertSide($side);

        $person = $this->rememberMember($event, $joiner, OpenMatPerson::SOURCE_JOINED, $joiner->id);

        // selfJoin: a code-holder putting themselves forward, not an operator
        // placing people. See assign().
        return $this->assign($event, $court, $side, $person->id, selfJoin: true);
    }

    /**
     * Is a bout being FOUGHT on this mat right now?
     *
     * Deliberately narrower than OpenMat::liveBoutId(), which answers "what does
     * Resume point at" and therefore counts a bout that is merely loaded on the
     * scoreboard and not yet started. That is the wrong test for this guard: a
     * loaded bout snapshots its two names when it is queued, so a corner change
     * before the clock starts cannot rewrite it — and refusing joins for it would
     * lock the corners of every mat that has ever had a bout queued.
     *
     * `status = live` is what "the clock is running" means throughout this
     * platform (see Advancement in the karate and taekwondo packages).
     */
    public function hasLiveBout(ClubEvent $event, string $court): bool
    {
        return \App\Models\EventMatch::where('event_id', $event->id)
            ->where('court', $court)
            ->where('status', 'live')
            ->whereNull('winner')
            ->exists();
    }

    /** Empty a corner. The person stays on the floor. */
    public function clearCorner(ClubEvent $event, string $court, string $side): array
    {
        $this->assertMat($event, $court);
        $side = $this->assertSide($side);

        OpenMatCorner::where('event_id', $event->id)->where('court', $court)->where('side', $side)->delete();

        return $this->corners($event, $court);
    }

    /** Swap red and blue — the one rearrangement anybody ever wants. */
    public function swapCorners(ClubEvent $event, string $court): array
    {
        $this->assertMat($event, $court);

        $rows = OpenMatCorner::where('event_id', $event->id)->where('court', $court)->get()->keyBy('side');
        $aka = $rows->get(OpenMatCorner::SIDE_AKA);
        $ao = $rows->get(OpenMatCorner::SIDE_AO);

        // The unique index is (event, court, side), so the two cannot both hold
        // the same side even for an instant. Park one, move the other, return.
        if ($aka) {
            $aka->update(['side' => 'swap']);
        }
        if ($ao) {
            $ao->update(['side' => OpenMatCorner::SIDE_AKA]);
        }
        if ($aka) {
            $aka->update(['side' => OpenMatCorner::SIDE_AO]);
        }

        return $this->corners($event, $court);
    }

    /** Nobody fights themselves. */
    private function assertNotAlreadyOn(ClubEvent $event, string $court, string $side, OpenMatPerson $person): void
    {
        $other = OpenMatCorner::where('event_id', $event->id)
            ->where('court', $court)
            ->where('side', '!=', $side)
            ->value('person_id');

        if ($other !== null && (int) $other === $person->id) {
            throw new \RuntimeException(__('event-open_mat::messages.same_person'));
        }
    }

    private function assertSide(string $side): string
    {
        if (! in_array($side, OpenMatCorner::SIDES, true)) {
            throw new \RuntimeException(__('event-open_mat::messages.unknown_side'));
        }

        return $side;
    }

    /** A wall screen prints this. Cap it, and strip anything that is not text. */
    private function cleanName(string $name): string
    {
        // Control characters out, runs of whitespace collapsed — a wall screen
        // prints this, and "Marco  Rossi" is not a name anybody typed.
        $name = preg_replace('/[\p{C}]+/u', ' ', $name) ?? '';
        $name = preg_replace('/\s+/u', ' ', $name) ?? '';

        return trim(Str::limit(trim($name), 60, ''));
    }

    private function cleanCountry(?string $country): ?string
    {
        $country = strtolower(trim((string) $country));

        return preg_match('/^[a-z]{2}$/', $country) ? $country : null;
    }

    /**
     * The country beside a competitor is their CLUB's, never their passport —
     * the same rule every other screen in the product follows.
     */
    private function countryFor(User $user): ?string
    {
        return $this->clubFor($user)['country'];
    }

    /**
     * The club this person stands for, and the flag that goes with it.
     *
     * Resolved as a PAIR, in one query, on purpose: the flag beside a competitor
     * is the club's country and not the member's nationality (a Bahraini club's
     * athlete competes under Bahrain), so a name and a flag that came from two
     * different clubs would be a quiet lie on a wall screen. Taking both from the
     * same row makes that impossible rather than unlikely.
     *
     * A member of several clubs gets their first — which is the same club the
     * flag has always come from, so nothing that was showing changes.
     */
    private function clubFor(User $user): array
    {
        $club = $user->memberClubs()->first(['tenants.club_name', 'tenants.country']);

        return [
            'club' => is_string($club?->club_name) ? trim($club->club_name) : null,
            'country' => $this->cleanCountry(is_string($club?->country) ? $club->country : null),
        ];
    }

    /* ==================== Finding an opponent ==================== */

    /**
     * Who this person may put on a mat: search by NAME, PHONE or EMAIL.
     *
     * One box, three ways in, all partial — because at a mat you know the
     * person in front of you by their name, and possibly by the number in your
     * phone, and nothing else. An earlier version restricted name matching to
     * the searcher's own club-mates and demanded an EXACT email or phone for
     * anybody else. That was too tight to use: a visitor from the club across
     * town could not be found at all, and on a small club it meant the search
     * returned nothing ever.
     *
     * YOURSELF IS INCLUDED, and that is the important one. The person holding
     * the phone is usually one of the two fighters — they opened the mat
     * because they are about to fight on it — so excluding the searcher, which
     * is the reflex for a "find people" box, made the commonest case of all
     * impossible.
     *
     * ── What still holds the line ───────────────────────────────────────────
     * This is a search, not a directory, and the difference is enforced:
     *
     *   · `users.is_discoverable` is honoured — a member who opted out of being
     *     found is not returned to anybody. (Except to themselves: you can
     *     always find you.)
     *   · Blocks are mutual, both directions.
     *   · A minimum term length, so an empty or one-character box cannot be
     *     used to walk the user table, and a hard cap of a dozen rows.
     *   · The row carries a name, a club and a photo the member PUBLISHED —
     *     the same three facts their public profile already shows anyone
     *     signed in. Nothing is exposed here that `people.show` does not.
     *   · The endpoint is throttled, and placing somebody re-runs this same
     *     query as the authorisation check (see `findableBy`), so a user id
     *     that could not be found cannot be posted either.
     */
    public function searchOpponents(User $actor, string $term, int $limit = 12): Collection
    {
        $term = trim($term);
        $digits = preg_replace('/\D+/', '', $term) ?? '';

        // Two characters of a name, or three of a number. Below that it is not
        // a search, it is an enumeration attempt.
        if (Str::length($term) < 2 && Str::length($digits) < 3) {
            return collect();
        }

        return $this->findablePool($actor)
            ->where(function ($q) use ($term, $digits) {
                $q->where('full_name', 'like', '%'.$term.'%')
                    ->orWhere('name', 'like', '%'.$term.'%')
                    ->orWhere('email', 'like', '%'.$term.'%');

                // The mobile is a {"code","number"} JSON blob, so the digits
                // match inside it. Three digits minimum, or every search with a
                // stray number in it would sweep the column.
                //
                // Typed WITH the country code ("+973 33165444") the raw digits
                // are 97333165444 and the stored number is 33165444, so a plain
                // substring match finds nothing — which is exactly how most
                // people have the number saved in their phone. Matching the
                // TAIL as well covers it: a national number is the last stretch
                // of digits whatever prefix is in front of it.
                foreach ($this->phoneNeedles($digits) as $needle) {
                    $q->orWhere('mobile', 'like', '%'.$needle.'%');
                }
            })
            ->with('memberClubs:id,club_name,country')
            // The searcher first when they match: they are usually one of the
            // two fighters, and scrolling for yourself is absurd.
            ->orderByRaw('case when users.id = ? then 0 else 1 end', [$actor->id])
            ->orderBy('full_name')
            ->limit(max(1, min(24, $limit)))
            ->get($this->opponentColumns())
            ->map(fn (User $u) => $this->opponentRow($u, $actor))
            ->values();
    }

    /**
     * The digit strings a typed number could match on.
     *
     * The whole thing as typed, plus its last seven and eight digits once it is
     * long enough to be carrying a country code. Seven is the shortest national
     * number worth matching on; below that this returns nothing rather than
     * sweeping the column on a two-digit fragment.
     */
    private function phoneNeedles(string $digits): array
    {
        if (Str::length($digits) < 3) {
            return [];
        }

        $needles = [$digits];

        foreach ([9, 8, 7] as $tail) {
            if (Str::length($digits) > $tail) {
                $needles[] = substr($digits, -$tail);
            }
        }

        return array_values(array_unique($needles));
    }

    /** One member, as the picker draws them — and as the mat will print them. */
    public function opponentRow(User $u, User $actor): array
    {
        return [
            'id' => $u->id,
            'name' => $u->full_name ?: $u->name,
            'photo' => ($u->profile_picture && $u->profile_picture_is_public)
                ? asset('storage/'.$u->profile_picture)
                : null,
            'fallback' => \App\Support\Avatar::placeholder($u->gender),
            'club' => $u->memberClubs->first()?->club_name,
            'is_me' => $u->id === $actor->id,
        ];
    }

    /**
     * The pool every lookup starts from — the one definition of "findable".
     *
     * Both the search and the authorisation check behind `placeMember()` build
     * on this, so they can never drift apart: if the box could not have shown
     * them, the endpoint will not place them.
     */
    private function findablePool(User $actor)
    {
        // ⚠️ `idsBlockedEitherWayWith` PUSHES THE CALLER onto its own list. That
        // is right for the people directory it was written for — you do not
        // want yourself in "find people" — and exactly wrong here, where the
        // person searching is usually one of the two fighters. Taken at face
        // value it made the searcher invisible to themselves by every route:
        // name, phone and email alike. So the blocks are kept and the caller is
        // taken back out, rather than changing a helper the directory depends on.
        $blocked = UserBlock::idsBlockedEitherWayWith($actor->id)
            ->reject(fn ($id) => (int) $id === $actor->id);

        return User::query()
            // Opting out of discovery hides you from everyone — but never from
            // yourself, or you could not put yourself in a corner.
            ->where(fn ($q) => $q->where('is_discoverable', true)->orWhere('id', $actor->id))
            ->whereNotIn('id', $blocked);
    }

    /**
     * Re-run the pool as an authorisation check.
     *
     * `placeMember()` calls this rather than trusting the id the console
     * posted, because the console is a convenience and the endpoint is the
     * door: a user id the actor could never have found is one they may not
     * place on a mat and print on a wall.
     */
    private function findableBy(User $actor, int $userId): ?User
    {
        return $this->findablePool($actor)
            ->whereKey($userId)
            ->with('memberClubs:id,club_name,country')
            ->first();
    }

    private function opponentColumns(): array
    {
        return ['id', 'full_name', 'name', 'gender', 'profile_picture', 'profile_picture_is_public'];
    }

    /* ==================== The join code ==================== */

    /**
     * The six characters printed on the mat, and the QR that carries them.
     *
     * STORED, not cached. It used to live only in the cache with a four-hour TTL
     * on the file driver, which meant any deploy voided every live code
     * mid-session — and the resulting failure is deliberately indistinguishable
     * from a typo, so nobody in the hall could tell. Now it lives as long as the
     * mat does, and the cache is a fast index over it rather than the only copy.
     *
     * Ambiguous characters are left out of the alphabet: this gets read off a
     * screen across a room and typed on a phone, where O and 0 are the same
     * character to a human.
     */
    public function joinCode(ClubEvent $event, string $court): string
    {
        $this->assertMat($event, $court);

        $row = OpenMatCode::firstWhere(['event_id' => $event->id, 'court' => $court]);

        if ($row === null) {
            $row = OpenMatCode::create([
                'event_id' => $event->id,
                'court' => $court,
                'code' => $this->mintCode(),
            ]);
        }

        $this->indexCode($row->code, $event, $court);

        return $row->code;
    }

    /**
     * A fresh code for this mat. The old one stops working immediately — which is
     * the whole point: it is how an operator shuts out somebody who should not
     * still have it.
     */
    public function rotateCode(ClubEvent $event, string $court): string
    {
        $this->assertMat($event, $court);

        $row = OpenMatCode::firstWhere(['event_id' => $event->id, 'court' => $court]);

        if ($row) {
            Cache::forget($this->lookupKey($row->code));
        }

        $code = $this->mintCode();

        OpenMatCode::updateOrCreate(
            ['event_id' => $event->id, 'court' => $court],
            ['code' => $code, 'rotated_at' => now()],
        );

        $this->indexCode($code, $event, $court);

        return $code;
    }

    /** Six characters nobody else is using. */
    private function mintCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (OpenMatCode::where('code', $code)->exists());

        return $code;
    }

    /** Warm the reverse lookup. Best-effort: the table is the truth. */
    private function indexCode(string $code, ClubEvent $event, string $court): void
    {
        Cache::put(
            $this->lookupKey($code),
            ['event' => $event->uuid, 'court' => $court],
            now()->addMinutes(self::CODE_TTL_MINUTES),
        );
    }

    /**
     * The mat a code points at, or null. Never says which part was wrong.
     *
     * Cache first because this is on the join path and hit once per attempt; the
     * table behind it is what makes a cleared cache cost a query instead of an
     * evening.
     */
    public function resolveCode(string $code): ?array
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');

        if (strlen($code) !== 6) {
            return null;
        }

        $hit = Cache::get($this->lookupKey($code));

        if (! is_array($hit)) {
            $row = OpenMatCode::where('code', $code)->first();

            if ($row === null) {
                return null;
            }

            $event = ClubEvent::where('id', $row->event_id)
                ->where('event_type', OpenMat::TYPE)
                ->where('is_archived', false)
                ->first();

            if ($event === null) {
                return null;
            }

            $this->indexCode($code, $event, $row->court);

            return ['event' => $event, 'court' => (string) $row->court];
        }

        $event = ClubEvent::where('uuid', $hit['event'] ?? '')
            ->where('event_type', OpenMat::TYPE)
            ->where('is_archived', false)
            ->first();

        return $event ? ['event' => $event, 'court' => (string) ($hit['court'] ?? '')] : null;
    }

    private function lookupKey(string $code): string
    {
        return 'openmat:join:'.strtoupper($code);
    }

    /* ==================== The bout ==================== */

    /**
     * Start the fight.
     *
     * This is the whole difference from every other event in the product: there
     * is no queue to draw from and nothing was entered in advance. Both corners
     * are filled, somebody taps start, and the bout comes into existence at
     * that moment — created, then handed to the sport's own scoring table
     * exactly as a championship bout would be.
     *
     * A member corner also gets a registration row, because that is what the
     * scoring table reads to find a face, a club, a belt and a record for the
     * introduction. A guest corner gets none and prints their name alone.
     */
    public function startBout(ClubEvent $event, string $court): EventMatch
    {
        $this->assertMat($event, $court);

        $rows = OpenMatCorner::where('event_id', $event->id)
            ->where('court', $court)
            ->with('person')
            ->get()->keyBy('side');

        $aka = $rows->get(OpenMatCorner::SIDE_AKA);
        $ao = $rows->get(OpenMatCorner::SIDE_AO);

        if (! $aka || ! $ao) {
            throw new \RuntimeException(__('event-open_mat::messages.need_two_corners'));
        }

        if ($aka->person_id !== null && $aka->person_id === $ao->person_id) {
            throw new \RuntimeException(__('event-open_mat::messages.same_person'));
        }

        // A bout already live on this mat is not replaced. Two bouts on one mat
        // is not a state the scoreboard has, and silently overwriting the one
        // being fought would wipe a running score.
        $live = EventMatch::where('event_id', $event->id)
            ->where('court', $court)
            ->whereNull('winner')
            ->where('status', '!=', 'done')
            ->first();

        if ($live) {
            return $live;
        }

        $division = $this->division($event);

        $match = EventMatch::create([
            'event_id' => $event->id,
            'category_id' => $division->id,
            'round' => self::ROUND,
            'slot' => 0,
            'court' => $court,
            'match_no' => (int) EventMatch::where('event_id', $event->id)->max('match_no') + 1,
            // NULL on purpose: an open mat has no competition days, and a bout
            // with a day would fall outside "today" in the running order.
            'day' => null,
            'status' => 'upcoming',
            'a_competitor_id' => $this->registrationFor($event, $aka, $division),
            'b_competitor_id' => $this->registrationFor($event, $ao, $division),
            'a_name' => $aka->name,
            'b_name' => $ao->name,
            'a_country' => $aka->country,
            'b_country' => $ao->country,
        ]);

        // The fairness counter the operator reads when picking the next pair.
        // Bumped when the bout STARTS rather than when it is filed: somebody
        // who stepped on and had their turn has had their turn, whether or not
        // the result was ever committed.
        OpenMatPerson::whereIn('id', array_filter([$aka->person_id, $ao->person_id]))
            ->update(['bouts' => DB::raw('bouts + 1'), 'last_bout_at' => now()]);

        return $match;
    }

    /**
     * The entry row behind a member corner — created on the spot, never asked
     * for. Free, settled, and belonging to this mat alone.
     */
    private function registrationFor(ClubEvent $event, OpenMatCorner $corner, EventCategory $division): ?int
    {
        if (! $corner->user_id) {
            return null;
        }

        return ClubEventRegistration::firstOrCreate(
            ['event_id' => $event->id, 'user_id' => $corner->user_id],
            [
                'status' => 'joined',
                'role' => 'participant',
                'registered_at' => now(),
                'category_id' => $division->id,
                // An open mat is never charged for. Marked settled so no mat
                // can ever surface as an unpaid entry.
                'paid' => true,
            ],
        )->id;
    }

    /**
     * File the result, and put it on both members' casual records.
     *
     * Written here rather than derived later because the corners can change the
     * moment the bout ends — the next pair steps on — and the record has to say
     * who actually fought.
     */
    public function fileResult(ClubEvent $event, EventMatch $match, array $payload): void
    {
        $winner = in_array($payload['winner'] ?? null, ['a', 'b'], true) ? $payload['winner'] : null;

        $registrations = ClubEventRegistration::where('event_id', $event->id)
            ->whereIn('id', array_filter([$match->a_competitor_id, $match->b_competitor_id]))
            ->pluck('user_id', 'id');

        OpenMatResult::updateOrCreate(
            ['match_id' => $match->id],
            [
                'event_id' => $event->id,
                'sport' => (string) $event->sport,
                'court' => $match->court,
                'a_user_id' => $registrations[$match->a_competitor_id] ?? null,
                'b_user_id' => $registrations[$match->b_competitor_id] ?? null,
                'a_name' => (string) $match->a_name,
                'b_name' => (string) $match->b_name,
                'winner' => $winner,
                'a_score' => (int) $match->a_score,
                'b_score' => (int) $match->b_score,
                'win_reason' => $payload['win_reason'] ?? $match->win_reason,
                'win_note' => $payload['win_note'] ?? $match->win_note,
                'fought_at' => now(),
            ],
        );
    }

    /** What has been fought on this mat, newest first. */
    public function bouts(ClubEvent $event, ?string $court = null): Collection
    {
        return EventMatch::where('event_id', $event->id)
            ->when($court, fn ($q) => $q->where('court', $court))
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (EventMatch $m) => [
                'id' => $m->id,
                'no' => $m->match_no,
                'mat' => $m->court,
                'aka' => $m->a_name,
                'ao' => $m->b_name,
                'aka_score' => (int) $m->a_score,
                'ao_score' => (int) $m->b_score,
                'winner' => $m->winner,
                'reason' => $m->win_reason,
                'live' => $m->winner === null && $m->status !== 'done',
                'done' => $m->winner !== null || $m->status === 'done',
            ]);
    }
}
