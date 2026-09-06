<?php

namespace App\Events\Support;

use App\Events\EventTypeRegistry;
use App\Members\Models\User;
use App\Members\Models\UserNotification;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventPublicEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * A stranger enters a competition — Door C.
 *
 * Phase C of Documentation/EVENTS-PUBLIC-ENTRY.md. Somebody who has never heard
 * of TAKEONE opens a shared link, reads the poster and decides to compete. The
 * account is a SIDE EFFECT; entering the competition is the thing they came for.
 *
 * The shape of it, and every part matters:
 *
 *   · The request is not an entry. It waits in `event_public_entries` and
 *     becomes a `ClubEventRegistration` only when an organiser accepts it. A
 *     pending request counts toward nothing — not the entrant count, not
 *     capacity, not the money — so a flood of them cannot corrupt a
 *     competition, only make a list somebody clears in one gesture.
 *   · The organiser's accept IS the gate. Not paperwork.
 *   · Nothing the stranger posts decides money, division or status. The fee
 *     comes from EventFee, the division from the event's own package, the state
 *     from here.
 *   · An account is created unverified. Verification is what KEEPS the place,
 *     not what takes it.
 *
 * Cross-sport by construction: it asks the event's package to classify, and
 * never knows which sport it is serving.
 */
class PublicEntry
{
    /** Marker for the one failure that has to unwind a half-made account. */
    private const PHOTO_REFUSED = 'takeone.photo-refused';

    /* Real-byte MIME sniffing, a server-assigned extension and SVG refused.
       An entrant's photo arrives as a data URI they typed the header of, so it
       is the one thing on this door that could be a shell if the header were
       believed (CLAUDE.md → Image Uploads Must Validate Real Bytes). */
    use \App\Traits\StoresBase64Images;

    public function __construct(
        private EntryService $entries,
        private EventTypeRegistry $registry,
        private PublicEvent $publisher,
    ) {}

    /* ==================== The stranger's side ==================== */

    /**
     * May anybody enrol through the public page right now?
     *
     * One answer, reused by the page, the button and the endpoint, so they can
     * never disagree about whether a place can still be taken.
     *
     * @return array{open: bool, note: ?string}
     */
    public function state(ClubEvent $event): array
    {
        if (! $this->publisher->isPublic($event)) {
            return ['open' => false, 'note' => null];
        }

        $state = $this->entries->entriesState($event);
        if (! $state['open']) {
            return $state;
        }

        if ($this->isFull($event)) {
            return ['open' => false, 'note' => __('events.entry_full')];
        }

        return ['open' => true, 'note' => null];
    }

    /**
     * Take the request.
     *
     * @param  array{full_name: string, email: string, password: string, birthdate: ?string, gender: ?string, weight: ?float, belt: ?string}  $data
     * @return array{ok: bool, message: string, field?: string, state?: string, division?: ?string}
     */
    /**
     * The `{code, number}` shape `users.mobile` is cast to.
     *
     * ⚠️ Writing a bare string here is silent data loss: the column is cast
     * `'mobile' => 'array'`, so a string lands as a JSON scalar and every
     * reader on the platform (`User::phone()`, the club member cards, the
     * officials sheet, the member's own profile) sees nothing at all. That is
     * exactly what happened to the first public entrants — their numbers were
     * on file and invisible on their own profiles at the same time.
     *
     * A number with no dial code stays a number with no dial code rather than
     * being guessed at; the picker supplies one in practice.
     *
     * @param  array<string, mixed>  $data
     * @return array{code: ?string, number: string}|null
     */
    private static function mobileColumn(array $data): ?array
    {
        $number = trim((string) ($data['mobile'] ?? ''));

        if ($number === '') {
            return null;
        }

        $code = trim((string) ($data['mobile_code'] ?? ''));
        $bare = preg_replace('/\s+/', '', $number);

        // A number typed with its country code already in it — "+973 3316 5444"
        // — is split rather than stored with the code twice.
        if ($code !== '' && str_starts_with((string) $bare, $code)) {
            $number = trim(substr((string) $bare, strlen($code)));
        }

        return ['code' => $code !== '' ? $code : null, 'number' => $number];
    }

    /**
     * The fee options the entrant ticked, as bare uuids.
     *
     * Nothing is validated here on purpose — EventFee::quote() is the only
     * thing that decides whether a key names a real, active option of this
     * event, and duplicating that judgement in the door would give it two
     * places to drift apart. All this does is refuse to carry anything that is
     * not a string, which is what a hand-rolled JSON body can arrive as.
     *
     * Capped, because the list is a request field and an unbounded array of
     * keys is a cheap way to make an expensive lookup. No event sells fifty
     * things.
     *
     * @return array<int, string>
     */
    private static function chosenOptions(array $data): array
    {
        $keys = $data['fee_options'] ?? [];

        if (! is_array($keys)) {
            return [];
        }

        return array_slice(array_values(array_filter(
            $keys,
            fn ($k) => is_string($k) && $k !== '',
        )), 0, 50);
    }

    public function enrol(ClubEvent $event, array $data, ?string $ip = null): array
    {
        $state = $this->state($event);
        if (! $state['open']) {
            return ['ok' => false, 'message' => $state['note'] ?? __('events.public_enrol_closed')];
        }

        // The priced extras they ticked. Read here, once, so both branches of
        // this door price the same way — and read as UUIDS ONLY: what they cost
        // is the event's business, never the form's.
        $chosen = self::chosenOptions($data);

        $fullName = trim(preg_replace('/\s+/u', ' ', $data['full_name']));

        // Optional since 2026-09-04: the TELEPHONE NUMBER is the account now,
        // and an email is only the way back in if the password is forgotten.
        // Empty stays NULL rather than '' — the column is unique, and SQLite
        // (like every other engine here) allows many NULLs but only one ''.
        $email = trim((string) ($data['email'] ?? ''));
        $email = $email === '' ? null : mb_strtolower($email);

        // An address already in use is not something to reason about out loud.
        // One sentence, and it is the same sentence for a member of ten years
        // and for somebody who mistyped: sign in. No account is touched, and
        // nothing here confirms or denies that one exists.
        if ($email !== null && User::where('email', $email)->exists()) {
            return ['ok' => false, 'message' => __('events.public_enrol_sign_in'), 'field' => 'email'];
        }

        // Deliberately NO duplicate-name check, unlike the coach's door. There
        // it protects a squad sheet the coach can see; here it would answer
        // "is this person entered?" to anybody who can type a name, which is
        // the roster we are refusing to publish.

        try {
            $entry = DB::transaction(function () use ($event, $fullName, $email, $data, $ip, $chosen) {
            $athlete = User::create([
                'full_name' => $fullName,
                'name' => $fullName,
                'email' => $email,
                'password' => Hash::make($data['password']),
                'birthdate' => $data['birthdate'] ?: null,
                'gender' => $data['gender'] ?: null,
                'mobile' => self::mobileColumn($data),
                'nationality' => ($data['nationality'] ?? null) ? strtoupper($data['nationality']) : null,
                // Off until they choose otherwise in their own settings. They
                // came to compete, not to be listed; and nobody unaccepted
                // should be reachable through discovery.
                'is_discoverable' => false,
            ]);

            // The face. Stored only once the account exists, so the path is
            // owner-first under their own uuid (App\Support\StoragePath's
            // shape).
            //
            // Since the photo became REQUIRED, bytes that are not an image
            // abort the whole enrolment rather than leaving somebody entered
            // without the thing we insisted on. Thrown rather than returned so
            // the transaction takes the half-made account back with it; caught
            // just outside and turned into the field error the flow shows.
            if (! empty($data['photo'])) {
                $path = $this->storeBase64Image(
                    $data['photo'],
                    'members/'.$athlete->uuid.'/profile',
                    (string) Str::uuid(),
                );

                if ($path === null) {
                    throw new \RuntimeException(self::PHOTO_REFUSED);
                }

                /*
                 * Visible, and deliberately so (decided 2026-09-06).
                 *
                 * This used to be stored with the flag OFF — "they came to
                 * compete, not to publish their face". It read as principled
                 * and behaved as a bug: entering through the public link
                 * REQUIRES a photo, every competition surface then showed it,
                 * and the same athlete's own profile one tap away drew a blank
                 * silhouette. The face was already public where it mattered;
                 * the flag only hid it from the one page a reader goes to
                 * looking for the person.
                 *
                 * So a face supplied to enter a public competition is stored
                 * the way any other member's picture is, and
                 * `profile_picture_is_public` stays theirs to turn OFF in their
                 * own settings — the same switch every other member has, rather
                 * than a default nobody could find the effect of.
                 */
                $athlete->forceFill(['profile_picture' => $path, 'profile_picture_is_public' => true])->save();
            }

            $club = $this->resolveClub($data, $event);

            return EventPublicEntry::create([
                'uuid' => (string) Str::uuid(),
                'event_id' => $event->id,
                'user_id' => $athlete->id,
                'birthdate' => $data['birthdate'] ?: null,
                'gender' => $data['gender'] ?: null,
                'weight' => $data['weight'] ?: null,
                'belt_colour' => $data['belt'] ?: null,
                // Held rather than priced: a request becomes an entry when
                // somebody ACCEPTS it, and that is when it is charged. Storing
                // the keys means a reviewed entrant is billed for exactly what
                // an auto-accepted one is.
                'fee_options' => $chosen ?: null,
                'representing_tenant_id' => $club['tenant_id'],
                'club_name' => $club['name'],
                'state' => 'pending',
                'ip' => $ip,
            ]);
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== self::PHOTO_REFUSED) {
                throw $e;
            }

            // Nothing was kept: the account and the entry went back with the
            // transaction. The one sentence that is true and useful.
            return ['ok' => false, 'message' => __('events.public_enrol_photo_invalid'), 'field' => 'photo'];
        }

        // Best-effort, and outside the transaction: a mail queue that is down
        // must not roll back an entry somebody just made.
        // Only if there is somewhere to send it. An athlete who joined by
        // telephone has no address, and nothing to verify.
        if ($entry->athlete?->email) {
            rescue(fn () => $entry->athlete->sendEmailVerificationNotification(), null, false);
        }

        // An organiser who wants no gatekeeping said so per event. The accept
        // still HAPPENS — it is the same code path, taken immediately — so an
        // auto-accepted entry is indistinguishable from a reviewed one
        // afterwards, and can be declined later like any other.
        if ($event->public_entry_auto_accept) {
            $accepted = $this->settle($entry->fresh(), null, $chosen);

            return [
                'ok' => true,
                'state' => 'accepted',
                'division' => $accepted['division'],
                'message' => __('events.public_enrol_accepted'),
                // Who was just created. The caller signs them in — see
                // PublicEntryController::store(). Never echoed to the client.
                'athlete_id' => (int) $entry->user_id,
            ];
        }

        $this->notifyOrganiser($event, $entry);

        return [
            'ok' => true,
            'state' => 'pending',
            'division' => null,
            'message' => __('events.public_enrol_received'),
            'athlete_id' => (int) $entry->user_id,
        ];
    }

    /**
     * Take the request from somebody who ALREADY has an account.
     *
     * The public page asks "do you have an account?" before it asks anything
     * else, and this is the yes branch: they sign in through the ordinary login
     * — which is the only place on this platform that authenticates anybody, so
     * the throttle, the email-verification gate and two-factor all still apply
     * — come back here, and enter with the profile already on file.
     *
     * It is the SAME door as `enrol()`, not a shortcut past it: an
     * EventPublicEntry in `pending`, the organiser's accept, the same
     * `settle()`, the same auto-accept. A member arriving through a shared link
     * is still somebody from outside asking to be let in, and the organiser
     * sees one queue rather than two.
     *
     * What differs is only what is NOT done: no account is created, no password
     * is taken, no verification mail is sent, and their profile is not written
     * to. Their name, birthdate and gender are READ from it — that is what
     * "enter with your data" means. The weight and belt come from the form
     * because they belong to this entry, not to the person.
     *
     * @param  array{weight: ?float, belt: ?string, fee_options?: array<int, string>}  $data
     * @return array{ok: bool, message: string, state?: string, division?: ?string}
     */
    public function enrolExisting(ClubEvent $event, User $athlete, array $data, ?string $ip = null): array
    {
        $state = $this->state($event);
        if (! $state['open']) {
            return ['ok' => false, 'message' => $state['note'] ?? __('events.public_enrol_closed')];
        }

        // The priced extras they ticked. Read here, once, so both branches of
        // this door price the same way — and read as UUIDS ONLY: what they cost
        // is the event's business, never the form's.
        $chosen = self::chosenOptions($data);

        // Barred. Asked HERE and not only on the coach's path, because a
        // block that one door honoured and the other did not would be no block
        // at all: the person an organiser removed and blocked could walk back
        // in through the public link the same minute. One rule, borrowed from
        // EntryService rather than copied.
        if (app(EntryService::class)->isBanned($event, (int) $athlete->id)) {
            return ['ok' => false, 'state' => 'banned',
                    'message' => __('events.entry_banned', ['name' => $athlete->full_name ?: $athlete->name])];
        }

        // Already competing. Not an error to apologise for — say so and stop,
        // rather than adding a second request behind the place they hold.
        if ($this->isEntered($event, $athlete)) {
            return ['ok' => false, 'state' => 'already', 'message' => __('events.public_enrol_already_in')];
        }

        // Already asked. Idempotent on purpose: a double tap, a back button or
        // a reload must not put two of the same person in the organiser's
        // queue, and the honest answer is the one they already got.
        if ($existing = $this->pendingFor($event, $athlete)) {
            return ['ok' => true, 'state' => 'pending', 'division' => null,
                    'message' => __('events.public_enrol_already_pending')];
        }

        $club = $this->resolveClub($data, $event);

        // A row they ALREADY have, settled one way or the other. There is a
        // UNIQUE(event_id, user_id) on this table, so creating a second one
        // raises a constraint violation the athlete meets as a bare 500 — and
        // did, in production, every time somebody an organiser had removed
        // tried to come back. Two ways to arrive here, and re-applying is
        // right for both:
        //
        //   · `accepted`, but the registration is gone — an organiser removed
        //     them WITHOUT blocking them. Removal is "not in this list", not
        //     "not welcome"; the block above is what means the latter.
        //   · `declined` — the organiser said no. Also not permanent: a
        //     decline made by mistake would otherwise bar that person from
        //     that competition forever, with no way for the organiser to undo
        //     it. An organiser who means it permanently blocks.
        //
        // So the request is REOPENED rather than duplicated: same row, back to
        // pending, carrying this attempt's weight, belt and club, with the old
        // decision cleared so the queue does not show a stale verdict.
        if ($stale = $this->settledFor($event, $athlete)) {
            $stale->update([
                'birthdate' => $athlete->birthdate ? $athlete->birthdate->toDateString() : null,
                'gender' => $athlete->gender ?: null,
                'weight' => $data['weight'] ?: null,
                'belt_colour' => $data['belt'] ?: null,
                // Held rather than priced: a request becomes an entry when
                // somebody ACCEPTS it, and that is when it is charged. Storing
                // the keys means a reviewed entrant is billed for exactly what
                // an auto-accepted one is.
                'fee_options' => $chosen ?: null,
                'representing_tenant_id' => $club['tenant_id'],
                'club_name' => $club['name'],
                'state' => 'pending',
                'decided_by' => null,
                'decided_at' => null,
                'registration_id' => null,
                'ip' => $ip,
            ]);

            $entry = $stale->fresh();

            if ($event->public_entry_auto_accept) {
                $accepted = $this->settle($entry, null, $chosen);

                return [
                    'ok' => true,
                    'state' => 'accepted',
                    'division' => $accepted['division'],
                    'message' => __('events.public_enrol_accepted'),
                ];
            }

            $this->notifyOrganiser($event, $entry);

            return [
                'ok' => true,
                'state' => 'pending',
                'division' => null,
                'message' => __('events.public_enrol_received'),
            ];
        }

        $entry = EventPublicEntry::create([
            'uuid' => (string) Str::uuid(),
            'event_id' => $event->id,
            'user_id' => $athlete->id,
            // From the PROFILE, not from the request: they are signed in, so
            // these are already known and re-asking would invite a typo over a
            // fact. A blank stays blank — a birthdate is never demanded.
            'birthdate' => $athlete->birthdate ? $athlete->birthdate->toDateString() : null,
            'gender' => $athlete->gender ?: null,
            'weight' => $data['weight'] ?: null,
            'belt_colour' => $data['belt'] ?: null,
            // Held rather than priced: a request becomes an entry when
            // somebody ACCEPTS it, and that is when it is charged. Storing
            // the keys means a reviewed entrant is billed for exactly what
            // an auto-accepted one is.
            'fee_options' => $chosen ?: null,
            'representing_tenant_id' => $club['tenant_id'],
            'club_name' => $club['name'],
            'state' => 'pending',
            'ip' => $ip,
        ]);

        if ($event->public_entry_auto_accept) {
            $accepted = $this->settle($entry->fresh(), null, $chosen);

            return [
                'ok' => true,
                'state' => 'accepted',
                'division' => $accepted['division'],
                'message' => __('events.public_enrol_accepted'),
            ];
        }

        $this->notifyOrganiser($event, $entry);

        return [
            'ok' => true,
            'state' => 'pending',
            'division' => null,
            'message' => __('events.public_enrol_received'),
        ];
    }

    /**
     * The club this entry names, as the two columns that hold it.
     *
     * A PUBLIC KEY is what the page sends, never an id — an auto-increment id
     * is not a public key (CLAUDE.md → Unpredictable Resource Identifiers) —
     * and it is resolved here rather than trusted: a key naming nothing simply
     * yields no club, exactly as if nothing had been picked.
     *
     * Two keys are accepted, in this order, and `club_slug` carries either:
     * the uuid of a club on THIS EVENT'S list (what the picker sends now), and
     * failing that a platform club's slug (what it used to send, and what a
     * page rendered before this change still sends).
     *
     * A resolved club wins over a typed name every time. They are alternatives,
     * not a pair: the foreign key is what the draw, the entry list and the flag
     * beside a competitor's name read, and the typed name exists only so the
     * organiser can see where somebody came from when that somewhere is not on
     * this platform.
     *
     * @param  array<string, mixed>  $data
     * @return array{tenant_id: ?int, name: ?string}
     */
    private function resolveClub(array $data, ?ClubEvent $event = null): array
    {
        $key = trim((string) ($data['club_slug'] ?? ''));

        /*
         * THIS EVENT'S clubs first.
         *
         * The picker now sends the uuid of a club on the event's own list —
         * invited, or written down by the organiser — rather than any slug on
         * the platform. A club with an account resolves to its tenant; a
         * temporary one has no tenant, so its NAME is carried instead and the
         * organiser still sees where the entrant says they came from.
         *
         * A declined invitation is not an answer: a club that said no is not
         * competing, so naming it resolves to nothing.
         */
        if ($key !== '' && $event !== null) {
            $onEvent = \App\Models\EventClub::query()
                ->where('event_id', $event->id)
                ->where('state', '!=', 'declined')
                ->where('uuid', $key)
                ->first(['id', 'tenant_id', 'name']);

            if ($onEvent !== null) {
                return [
                    'tenant_id' => $onEvent->tenant_id ? (int) $onEvent->tenant_id : null,
                    'name' => $onEvent->tenant_id ? null : $onEvent->name,
                ];
            }
        }

        /*
         * The platform slug, still.
         *
         * Kept deliberately: this door is in production use, a page rendered
         * before this change is still open in somebody's browser and will post
         * a tenant slug, and other callers may still pass one. An unknown or
         * inactive slug yields no club, exactly as it always did.
         */
        if ($key !== '') {
            $tenant = \App\Clubs\Models\Tenant::where('slug', $key)
                ->where('status', 'active')
                ->first(['id']);

            if ($tenant !== null) {
                return ['tenant_id' => $tenant->id, 'name' => null];
            }
        }

        $typed = trim(preg_replace('/\s+/u', ' ', (string) ($data['club_name'] ?? '')));

        return ['tenant_id' => null, 'name' => $typed !== '' ? mb_substr($typed, 0, 120) : null];
    }

    /** Is this person already a competitor at this event? */
    public function isEntered(ClubEvent $event, User $athlete): bool
    {
        return $event->registrations()
            ->where('user_id', $athlete->id)
            ->where('role', 'participant')
            ->exists();
    }

    /** Their outstanding request for this event, if they have one. */
    public function pendingFor(ClubEvent $event, User $athlete): ?EventPublicEntry
    {
        return EventPublicEntry::where('event_id', $event->id)
            ->where('user_id', $athlete->id)
            ->where('state', 'pending')
            ->first();
    }

    /**
     * A request of theirs that has already been decided — accepted or declined.
     *
     * The table holds at most ONE row per person per event (a unique index
     * says so), so this is the row that would collide if a second request were
     * created. Callers reopen it instead.
     */
    public function settledFor(ClubEvent $event, User $athlete): ?EventPublicEntry
    {
        return EventPublicEntry::where('event_id', $event->id)
            ->where('user_id', $athlete->id)
            ->where('state', '!=', 'pending')
            ->first();
    }

    /* ==================== The organiser's side ==================== */

    /**
     * The queue: everybody waiting on this organiser, newest first.
     *
     * @return array<int, array>
     */
    public function pending(ClubEvent $event, User $actor): array
    {
        if (! app(EventAccess::class)->canManage($event, $actor)) {
            return [];
        }

        // A CONSTRAINED eager load: every column present() reads has to be
        // named here or it silently comes back null — which is exactly how the
        // phone and nationality went missing the first time. The relation is
        // named too, so a picked club is one query rather than one per row.
        return EventPublicEntry::with([
            'athlete:id,full_name,email,email_verified_at,mobile,nationality',
            'representingTenant:id,club_name',
        ])
            ->where('event_id', $event->id)
            ->where('state', 'pending')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (EventPublicEntry $e) => $this->present($e))
            ->values()->all();
    }

    /** Accept a request — this is the moment it becomes an entry. */
    public function accept(EventPublicEntry $entry, User $actor): array
    {
        if (! $entry->event || ! app(EventAccess::class)->canManage($entry->event, $actor)) {
            return ['ok' => false, 'message' => __('events.public_review_not_yours')];
        }

        if (! $entry->isPending()) {
            return ['ok' => false, 'message' => __('events.public_review_already_decided')];
        }

        $event = $entry->event;

        // A competition that is underway is being RUN from its entry list —
        // the draw is cut from it and the mats are assigned off it — so a name
        // cannot join it now, however long the request has been waiting.
        if ($event->hasStarted() || $event->isOverdueToStart() || $event->hasEnded()) {
            return ['ok' => false, 'message' => __('events.entry_event_started')];
        }

        if ($this->isFull($event)) {
            return ['ok' => false, 'message' => __('events.entry_full')];
        }

        /*
         * Priced from what the ENTRANT ticked, kept on the request since
         * 2026-09-06 (`event_public_entries.fee_options`).
         *
         * Before that column existed, an auto-accepted entrant was charged for
         * their extras and a reviewed one silently was not — the same form, the
         * same competitor, a different bill decided by a setting they never saw.
         *
         * The stored value is a list of UUIDS, never amounts, so acceptance
         * re-prices from the event's own rows exactly as every other door does.
         * An option the organiser retired while the request was waiting simply
         * drops out in `quote()`, which is the right answer and one nobody had
         * to write. Note this is the entrant's own selection read back, not
         * anything the organiser's screen posted.
         */
        $result = $this->settle($entry, $actor, is_array($entry->fee_options) ? $entry->fee_options : []);

        return [
            'ok' => true,
            'message' => __('events.public_review_accepted', ['name' => $entry->athlete?->full_name ?? '']),
            'division' => $result['division'],
            'going' => $event->participantRegistrations()->count(),
        ];
    }

    /**
     * Decline it.
     *
     * The account stays — they created it, it is theirs, and deleting a
     * person's account because one organiser said no is not ours to do. They
     * are simply not in this competition.
     */
    public function decline(EventPublicEntry $entry, User $actor): array
    {
        if (! $entry->event || ! app(EventAccess::class)->canManage($entry->event, $actor)) {
            return ['ok' => false, 'message' => __('events.public_review_not_yours')];
        }

        if (! $entry->isPending()) {
            return ['ok' => false, 'message' => __('events.public_review_already_decided')];
        }

        $entry->update([
            'state' => 'declined',
            'decided_by' => $actor->id,
            'decided_at' => now(),
        ]);

        $this->notifyAthlete($entry, false, null);

        return [
            'ok' => true,
            'message' => __('events.public_review_declined', ['name' => $entry->athlete?->full_name ?? '']),
        ];
    }

    /* ==================== Internals ==================== */

    /**
     * Turn an accepted request into a real entry.
     *
     * `$actor` is null for the auto-accept path — nobody decided, the organiser
     * decided in advance — and the row records that honestly.
     *
     * @return array{division: ?string}
     */
    private function settle(EventPublicEntry $entry, ?User $actor, array $optionKeys = []): array
    {
        $event = $entry->event;
        $athlete = $entry->athlete;

        $registration = DB::transaction(function () use ($entry, $event, $athlete, $actor, $optionKeys) {
            $registration = ClubEventRegistration::updateOrCreate(
                ['event_id' => $event->id, 'user_id' => $athlete->id],
                [
                    'role' => 'participant',
                    'status' => 'joined',
                    // Nothing the entrant typed decides this. If there is a fee
                    // it is owed, and it settles by the proof-upload path every
                    // other individual entry uses.
                    'paid' => ! EventFee::isPaid($event, 'participant'),
                    'registered_at' => now(),
                    // They entered themselves: the individual channel, and no
                    // club's name on the sheet unless one is claimed later.
                    'entry_channel' => 'individual',
                    'representing_tenant_id' => $entry->representing_tenant_id,
                    'entry_state' => 'complete',
                    'weight' => $entry->weight,
                    'belt_colour' => $entry->belt_colour,
                    /*
                     * The face they supplied AT ENROLMENT, stamped on the ENTRY.
                     *
                     * A photo is required to enter through the public link, and
                     * it is required so the competition can announce them — the
                     * board, the bracket, the entry list.
                     *
                     * `ClubEventRegistration.photo` is the seam that already
                     * exists for exactly this: the entry's OWN picture, read by
                     * every competition surface, scoped to this event and gone
                     * when the entry goes. Note this points AT their
                     * `users.profile_picture` rather than copying the bytes —
                     * App\Events\Support\EntryPhoto is what stops a discarded
                     * entry from deleting somebody's face out from under it.
                     */
                    'photo' => $athlete?->profile_picture,
                ],
            );

            // What this entry costs, frozen onto it as lines.
            //
            // ⚠️ The uuids came from a STRANGER on the public link, so they are
            // treated as nothing more than that: quote() prices them from this
            // event's own active rows and silently drops anything that does not
            // resolve. No amount is ever read from the request — the whole
            // reason the money is decided here rather than in the form.
            //
            // `paid` above is untouched: the lines say what was charged, `paid`
            // says whether it has been handed over, and those stay separate.
            /*
             * Priced as of when they ASKED, not when the organiser got round to
             * clicking accept.
             *
             * An auto-accepted request settles in the same breath as the form,
             * so `now()` and the request time are the same instant and nothing
             * shows. A REVIEWED one can sit for days: pricing it at acceptance
             * charged a competitor who applied two days before the deadline a
             * late penalty because their organiser opened the list on Monday.
             * That is the same "same form, different bill, decided by something
             * they never saw" this door was just fixed for.
             */
            $quote = EventFee::quote($event, 'participant', $optionKeys, $entry->created_at);

            EventFee::commit($registration, $quote);

            // Settled only when this entry genuinely owes nothing. `isPaid()`
            // reads the base fee alone, so an event priced entirely as options
            // would have waved every public entrant through as paid.
            if ($quote['total'] > 0 && $registration->paid) {
                $registration->forceFill(['paid' => false])->save();
            }

            $entry->update([
                'state' => 'accepted',
                'decided_by' => $actor?->id,
                'decided_at' => now(),
                'registration_id' => $registration->id,
            ]);

            return $registration;
        });

        // The package places them, exactly as it places anybody else. A missing
        // weight simply means no division yet and the weigh-in desk settles it.
        $division = $this->registry->for($event)->classifyEntry($event, $registration->fresh());
        $this->registry->for($event)->onEntrantsChanged($event, $division);

        $this->notifyAthlete($entry->fresh(), true, $division?->name);

        return ['division' => $division?->name];
    }

    /**
     * Is there still room?
     *
     * Counts ENTRIES, never requests — a queue of people asking does not fill
     * a competition.
     */
    private function isFull(ClubEvent $event): bool
    {
        return $event->max_capacity
            && $event->participantRegistrations()->count() >= (int) $event->max_capacity;
    }

    /** One row, in the shape the organiser's review list renders. */
    private function present(EventPublicEntry $entry): array
    {
        $athlete = $entry->athlete;

        return [
            'uuid' => $entry->uuid,
            'name' => $athlete?->full_name ?? '—',
            // The organiser is deciding whether to admit a stranger, so they
            // get the one contact fact that decision rests on and its state.
            'email' => $athlete?->email,
            'verified' => (bool) $athlete?->email_verified_at,
            'gender' => $entry->gender,
            'age' => $entry->birthdate ? $entry->birthdate->age : null,
            'weight' => $entry->weight ? (float) $entry->weight : null,
            'belt' => $entry->belt_colour,
            // Where they came from, which is most of what an organiser is
            // judging. A resolved club is named with its own record; a typed
            // one is shown as typed, because "Al Hala Karate Club" that we have
            // never heard of is still the answer to the question.
            'club' => $entry->representingTenant?->club_name ?: $entry->club_name,
            'club_confirmed' => $entry->representing_tenant_id !== null,
            // The column is `{code, number}`; the reviewer wants a number they
            // can dial. `User`'s `mobile_formatted` accessor is the one place
            // that composes the two halves.
            'phone' => $athlete?->mobile_formatted,
            'nationality' => $athlete?->nationality,
            'asked' => $entry->created_at?->diffForHumans(),
        ];
    }

    /** The organiser has somebody waiting. */
    private function notifyOrganiser(ClubEvent $event, EventPublicEntry $entry): void
    {
        $organiserId = (int) $event->created_by;
        if (! $organiserId) {
            return;
        }

        rescue(fn () => UserNotification::notifyUser($organiserId, 'event',
            __('events.public_review_notify_title', ['title' => $event->title]), [
                'body' => __('events.public_review_notify_body', [
                    'name' => $entry->athlete?->full_name ?? '',
                ]),
                'icon' => 'bi-person-plus-fill',
                'action_url' => route('me.events.manage', $event->uuid),
                'subject_type' => (new ClubEvent)->getMorphClass(),
                'subject_id' => $event->id,
                'tenant_id' => $event->tenant_id,
            ]), null, false);
    }

    /** They asked; they are told either way, without having to check back. */
    private function notifyAthlete(EventPublicEntry $entry, bool $accepted, ?string $division): void
    {
        $event = $entry->event;
        $athleteId = (int) $entry->user_id;

        $title = $accepted
            ? __('events.public_entry_accepted_title', ['title' => $event->title])
            : __('events.public_entry_declined_title', ['title' => $event->title]);

        $body = $accepted
            ? __('events.public_entry_accepted_body', ['division' => $division ?: __('events.claim_at_weigh_in')])
            : __('events.public_entry_declined_body', ['club' => $event->tenant?->club_name ?? '']);

        rescue(fn () => UserNotification::notifyUser($athleteId, 'event', $title, [
            'body' => $body,
            'icon' => $accepted ? 'bi-check-circle-fill' : 'bi-x-circle',
            'action_url' => $accepted ? route('me.events.show', $event->uuid) : route('events.public', $event->uuid),
            'subject_type' => (new ClubEvent)->getMorphClass(),
            'subject_id' => $event->id,
            'tenant_id' => $event->tenant_id,
        ]), null, false);
    }
}
