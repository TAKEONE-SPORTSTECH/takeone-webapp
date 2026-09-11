<?php

namespace App\Events\Support;

use App\Clubs\Models\Tenant;
use App\Events\EventTypeRegistry;
use App\Members\Models\User;
use App\Members\Models\UserRelationship;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventCategory;
use App\Models\EventEntryClaim;
use App\Members\Models\UserNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Entering somebody the coach cannot describe, and the link that completes them.
 *
 * Documentation/EVENTS-PUBLIC-ENTRY.md, Door B. The whole point is that the
 * person who COMMITS an entry and the person who SUPPLIES the athlete's details
 * are not the same person. A coach knows who is fighting; he does not know
 * their weight or their birthdate, and asking him produces an invented number
 * that drives age groups, weight divisions and the minor safeguards — worse
 * than a blank, because nobody ever revisits it.
 *
 * So: the coach gives a NAME. That commits a real entry (and, from Phase 3, the
 * club's money) immediately. The entry is `incomplete` and carries a single-use
 * claim link, which the athlete opens to give what only they know.
 *
 * Cross-sport by construction — it asks the event's own package for the gate
 * and the classification and never knows which sport it is serving.
 */
class EntryClaim
{
    /** A link never outlives the entry deadline, and never lives longer than this. */
    public const TTL_DAYS = 14;

    public function __construct(
        private EntryService $entries,
        private EventTypeRegistry $registry,
    ) {}

    /* ==================== The coach's side ==================== */

    /**
     * Commit an entry from a name alone, and issue the link that completes it.
     *
     * `$optionKeys` are the priced extras the person at the desk ticked for
     * THIS athlete — Gi, No-Gi, a T-shirt. They belong to the coach's side of
     * the door rather than the athlete's: the coach is the payer here (one bill
     * per payer), so what is bought is decided at the moment the entry is
     * committed and does not wait on the claim link being opened. Optional, and
     * empty by default, so an event with no options behaves exactly as before.
     *
     * ── THE SAME PERSON, A SECOND ACTIVITY ────────────────────────────────
     * `$categoryId` names the activity being entered. One event may hold
     * several — Gi and No-Gi at one jiu-jitsu championship — and a paper
     * entrant enters as many as the club is paying for (owner's ruling,
     * 2026-09-11). So a name already on the list is no longer refused outright
     * when an activity is named: the person who ALREADY EXISTS gets a second
     * registration, and no second account is minted. That distinction is the
     * whole reason this is handled here rather than by relaxing the duplicate
     * check — two accounts for one human, both claimable, is a far worse bug
     * than a duplicate in a draw.
     *
     * Passing null keeps the original behaviour exactly, including the
     * duplicate-name refusal.
     *
     * @param  array<int, string>  $optionKeys  fee-option UUIDs
     * @return array{ok: bool, message: string, claim?: array}
     */
    public function issue(
        ClubEvent $event,
        User $actor,
        string $fullName,
        ?string $email = null,
        ?string $phone = null,
        array $details = [],
        array $optionKeys = [],
        ?int $categoryId = null,
    ): array {
        $fullName = trim(preg_replace('/\s+/u', ' ', $fullName));

        // TWO authorities, either of which is enough, because they answer
        // different questions. The club's — "I enter athletes for the club I
        // run" — and the event's — "I am running this competition". Before the
        // second existed, an appointed official at the entry desk could not
        // enter a walk-in unless they also administered some club, which is the
        // one moment the appointment is for. See EventAccess::canEnterAthletes.
        $clubIds = $this->entries->administeredClubIds($actor);

        if ($clubIds === []
            && ! $actor->isSuperAdmin()
            && ! app(EventAccess::class)->canEnterAthletes($event, $actor)) {
            return ['ok' => false, 'message' => __('events.claim_no_authority')];
        }

        // The same gate that closes the join button and the coach's roster —
        // one answer, so they can never disagree about whether a place is still
        // available. Asked BEFORE anything is created.
        // The actor's own view of it: the closing date the organiser set is
        // not a rule against the organiser at the desk (EntryService::
        // entriesState). Started, ended and full still refuse them.
        $state = $this->entries->entriesState($event, $actor);
        if (! $state['open']) {
            return ['ok' => false, 'message' => $state['note'] ?? __('events.entry_closed_generic')];
        }

        if ($event->max_capacity && $event->participantRegistrations()->count() >= $event->max_capacity) {
            return ['ok' => false, 'message' => __('events.entry_full')];
        }

        // The activity being entered, and only if it belongs to THIS event. A
        // category id from anywhere else is ignored rather than trusted.
        $target = $categoryId
            ? $event->categories()->competing()->whereKey($categoryId)->first()
            : null;

        if ($categoryId && ! $target) {
            return ['ok' => false, 'message' => __('events.division_not_found')];
        }

        // Two people of the same name in one competition is nearly always the
        // same person entered twice. Refuse it and say so — a duplicate in a
        // draw is discovered on the mat, which is the worst place to find it.
        //
        // UNLESS an activity was named: then it is the ordinary case of the
        // same athlete entering the event's other activity, and what is wanted
        // is a second ENTRY for the person who already exists.
        $entered = $this->enteredAs($event, $fullName);

        if ($entered->isNotEmpty()) {
            if (! $target) {
                return ['ok' => false, 'message' => __('events.claim_duplicate_name', ['name' => $fullName])];
            }

            return $this->alsoEnter($event, $actor, $fullName, $entered, $target, $optionKeys);
        }

        $tenantId = $this->issuingClub($actor, $clubIds, $event);

        // What the person at the desk happens to know. NONE of it is required —
        // a name alone is still the whole contract (CLAUDE.md → "Who Fills The
        // Form Decides What It Demands") — but refusing to RECORD it when they
        // do know is its own kind of loss: an entry with no gender, age or
        // weight cannot be sorted into a division, so somebody would have to
        // chase the same athlete twice.
        $tenantId = $this->representedClub($details, $clubIds, $tenantId);

        $result = DB::transaction(function () use ($event, $actor, $fullName, $email, $phone, $tenantId, $details, $optionKeys, $target) {
            $athlete = $this->makeUnclaimedPerson($fullName, $details);

            $registration = ClubEventRegistration::create([
                'event_id' => $event->id,
                'user_id' => $athlete->id,
                'role' => 'participant',
                'status' => 'joined',
                // The COACH commits the money (one bill per payer — see
                // EVENTS-ENTRY-BILLING.md). Whether the athlete ever opens the
                // link does not change what the club owes.
                'paid' => ! EventFee::isPaid($event, 'participant'),
                'registered_at' => now(),
                'entered_by' => $actor->id,
                'entry_channel' => 'club',
                'representing_tenant_id' => $tenantId,
                // 'incomplete' is about who OWNS the account, not how much is
                // filled in: nobody has claimed this person yet. An organiser
                // supplying a weight does not make the entry claimed.
                'entry_state' => 'incomplete',
                'weight' => $details['weight'] ?? null,
                'belt_colour' => $details['belt_colour'] ?? null,
                // The activity, when the desk named one. Without it the entry
                // is unplaced and the package's own gate (or the weigh-in)
                // decides later, exactly as before.
                'category_id' => $target?->id,
            ]);

            // What the club is committing to pay, frozen onto the entry the
            // moment it exists. Priced from the event's own option rows — a
            // posted amount is never read — and inside the same transaction as
            // the registration, because an entry with no charge record and a
            // charge record with no entry are both worse than neither.
            EventFee::commit($registration, EventFee::quote($event, 'participant', $optionKeys));

            return [$athlete, $registration, $this->mint($event, $registration, $athlete, $actor, $email, $phone)];
        });

        [$athlete, $registration, $minted] = $result;

        // An incomplete entry has no division, so it cannot be drawn — but the
        // entrant COUNT changed, and the package owns what that means.
        $this->registry->for($event)->onEntrantsChanged($event);

        return [
            'ok' => true,
            'message' => __('events.claim_issued', ['name' => $fullName]),
            'claim' => $this->present($minted['claim'], $athlete, $registration, $minted['url']),
        ];
    }

    /**
     * The entries this actor has committed that are still waiting on somebody.
     *
     * @return array<int, array>
     */
    public function pending(ClubEvent $event, User $actor): array
    {
        return EventEntryClaim::with(['athlete:id,full_name', 'registration:id,entry_state,category_id'])
            ->where('event_id', $event->id)
            ->whereIn('created_by', $this->issuerScope($event, $actor))
            ->orderByDesc('id')
            ->limit(120)
            ->get()
            ->map(fn (EventEntryClaim $c) => $this->present($c, $c->athlete, $c->registration))
            ->values()->all();
    }

    /**
     * Withdraw an athlete the coach entered but nobody claimed.
     *
     * The link dies, the person record goes with it if nobody ever signed into
     * it, and the entry is removed — a coach must be able to take back a name
     * he typed by mistake without leaving a ghost in the draw.
     */
    public function revoke(EventEntryClaim $claim, User $actor): array
    {
        if (! $this->mayManage($claim, $actor)) {
            return ['ok' => false, 'message' => __('events.claim_not_yours')];
        }

        if ($claim->claimed_at) {
            return ['ok' => false, 'message' => __('events.claim_already_claimed')];
        }

        $event = $claim->event;

        DB::transaction(function () use ($claim) {
            $athlete = $claim->athlete;
            $claim->update(['revoked_at' => now()]);
            $claim->registration?->delete();

            /*
             * Only ever a person nobody has claimed — and only when nothing
             * else stands in their name.
             *
             * One paper entrant may hold an entry in each activity the event
             * runs (alsoEnter()), and deleting the person out from under a
             * sibling entry would leave a nameless competitor in a draw. The
             * link and its own entry are withdrawn either way; the person
             * survives as long as one entry does.
             */
            $elsewhere = $athlete
                ? ClubEventRegistration::where('user_id', $athlete->id)->exists()
                : false;

            if ($athlete && $athlete->is_unclaimed && ! $elsewhere) {
                $athlete->delete();
            }
        });

        $this->registry->for($event)->onEntrantsChanged($event);

        return ['ok' => true, 'message' => __('events.claim_revoked')];
    }

    /**
     * Issue a fresh secret for the same entry — the old link stops working.
     *
     * For the ordinary case of a link sent to the wrong number, or one that
     * expired while the coach was chasing the athlete.
     */
    public function regenerate(EventEntryClaim $claim, User $actor): array
    {
        if (! $this->mayManage($claim, $actor)) {
            return ['ok' => false, 'message' => __('events.claim_not_yours')];
        }

        if ($claim->claimed_at) {
            return ['ok' => false, 'message' => __('events.claim_already_claimed')];
        }

        $secret = Str::random(48);
        $claim->update([
            'token_hash' => hash('sha256', $secret),
            'expires_at' => $this->deadline($claim->event),
            'revoked_at' => null,
        ]);

        return [
            'ok' => true,
            'message' => __('events.claim_relinked'),
            'claim' => $this->present($claim->fresh(), $claim->athlete, $claim->registration, $this->url($claim, $secret)),
        ];
    }

    /* ==================== The athlete's side ==================== */

    /**
     * Resolve a link to a LIVE claim, or null.
     *
     * One answer for every failure — unknown uuid, wrong secret, expired,
     * revoked, already used. Distinguishing them would tell a stranger which
     * links are real.
     */
    public function resolve(?string $uuid, ?string $secret): ?EventEntryClaim
    {
        if (! $uuid || ! $secret) {
            return null;
        }

        $claim = EventEntryClaim::with(['event', 'registration', 'athlete'])
            ->where('uuid', $uuid)->first();

        if (! $claim || ! $claim->isLive()) {
            return null;
        }

        if (! hash_equals($claim->token_hash, hash('sha256', $secret))) {
            return null;
        }

        // An event that has been archived, deleted or started is not something
        // anybody can still be entered into, however good their link is.
        if (! $claim->event || $claim->event->is_archived || $claim->event->hasStarted() || $claim->event->hasEnded()) {
            return null;
        }

        return $claim;
    }

    /**
     * The athlete supplies what only they know, and the stub becomes an account.
     *
     * A minor never gets a bare login of their own: the credentials create the
     * GUARDIAN, who is linked to the athlete exactly as the family flows do it.
     *
     * @param  array{birthdate: ?string, gender: ?string, weight: ?float, belt: ?string, email: string, password: string, guardian_name: ?string}  $data
     */
    public function complete(EventEntryClaim $claim, array $data, ?string $ip = null): array
    {
        $athlete = $claim->athlete;
        $registration = $claim->registration;

        if (! $athlete || ! $registration) {
            return ['ok' => false, 'message' => __('events.claim_dead')];
        }

        $birthdate = $data['birthdate'] ?: null;
        // `age` rather than a diff: Carbon's signed diff reads a past date as a
        // NEGATIVE number of years, which made every adult a minor.
        $isMinor = $birthdate !== null && \Carbon\Carbon::parse($birthdate)->age < 18;

        // An address already in use is not evidence of anything we may say out
        // loud — the same sentence either way, and no account is touched.
        if (User::where('email', $data['email'])->exists()) {
            return ['ok' => false, 'message' => __('events.claim_email_taken'), 'field' => 'email'];
        }

        DB::transaction(function () use ($claim, $athlete, $registration, $data, $birthdate, $isMinor, $ip) {
            // What the athlete knows about themselves, on the person record.
            $athlete->fill(array_filter([
                'birthdate' => $birthdate,
                'gender' => $data['gender'] ?: null,
            ]));
            $athlete->is_unclaimed = false;

            if ($isMinor) {
                // The account belongs to the guardian; the athlete stays a
                // person record with no credentials of their own.
                $guardian = User::create([
                    'full_name' => $data['guardian_name'] ?: __('events.claim_guardian_default'),
                    'name' => $data['guardian_name'] ?: __('events.claim_guardian_default'),
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                    'is_discoverable' => false,
                ]);

                UserRelationship::firstOrCreate([
                    'guardian_user_id' => $guardian->id,
                    'dependent_user_id' => $athlete->id,
                ], ['relationship_type' => 'guardian']);
            } else {
                $athlete->email = $data['email'];
                $athlete->password = Hash::make($data['password']);
            }

            $athlete->save();

            // Weight and belt are facts about THIS competition, recorded on the
            // entry — the weigh-in desk overwrites them on the day, and that is
            // the correct authority (see the registration's belt_colour note).
            $registration->fill(array_filter([
                'weight' => $data['weight'] ?: null,
                'belt_colour' => $data['belt'] ?: null,
            ]));
            $registration->entry_state = 'complete';
            $registration->save();

            /*
             * EVERY entry this person holds in this event, not only the one the
             * link was minted against.
             *
             * The claim completes the PERSON, and one person may hold an entry
             * in each activity the event runs (alsoEnter()). The siblings would
             * otherwise stay `incomplete` for ever — a claimed athlete showing
             * as waiting on themselves — and carry no weight, which is the one
             * fact the desk needed from them.
             */
            ClubEventRegistration::where('event_id', $registration->event_id)
                ->where('user_id', $athlete->id)
                ->where('role', 'participant')
                ->whereKeyNot($registration->id)
                ->get()
                ->each(function (ClubEventRegistration $sibling) use ($data) {
                    /*
                     * One body, one weight. What the athlete says about
                     * themselves lands on every entry they hold — two entries
                     * disagreeing about the same person's weight is how one of
                     * them ends up in the wrong division.
                     *
                     * The exception is a SIGNED weigh-in: an official standing
                     * at the scale outranks anything typed, so an entry that
                     * has been weighed keeps its reading.
                     */
                    $signed = $sibling->weighed_in_by !== null;

                    $sibling->fill(array_filter([
                        'weight' => $signed ? null : ($data['weight'] ?: null),
                        'belt_colour' => $signed ? null : ($data['belt'] ?: null),
                    ]));
                    $sibling->entry_state = 'complete';
                    $sibling->save();
                });

            $claim->update(['claimed_at' => now(), 'claimed_ip' => $ip]);
        });

        // Now that a weight exists, the package places them in a division and
        // re-derives whatever it derives.
        $event = $claim->event;
        $division = $this->registry->for($event)->classifyEntry($event, $registration->fresh());
        $this->registry->for($event)->onEntrantsChanged($event, $division);

        // The coach who committed the entry finds out it is settled without
        // having to open the console and count.
        $this->notifyIssuer($claim, $athlete, $division?->name);

        return [
            'ok' => true,
            'message' => __('events.claim_completed'),
            'division' => $division?->name,
            'is_minor' => $isMinor,
        ];
    }

    /* ==================== Internals ==================== */

    /** The URL that carries a claim: a public uuid plus the secret half. */
    public function url(EventEntryClaim $claim, string $secret): string
    {
        return route('entry.claim', ['claim' => $claim->uuid]).'?t='.$secret;
    }

    private function mint(ClubEvent $event, ClubEventRegistration $registration, User $athlete, User $actor, ?string $email, ?string $phone): array
    {
        $secret = Str::random(48);

        $claim = EventEntryClaim::create([
            'uuid' => (string) Str::uuid(),
            'token_hash' => hash('sha256', $secret),
            'registration_id' => $registration->id,
            'event_id' => $event->id,
            'user_id' => $athlete->id,
            'created_by' => $actor->id,
            'contact_email' => $email ?: null,
            'contact_phone' => $phone ?: null,
            'expires_at' => $this->deadline($event),
        ]);

        return ['claim' => $claim, 'url' => $this->url($claim, $secret)];
    }

    /** The entry deadline, or the standard window — whichever comes first. */
    private function deadline(ClubEvent $event): \Illuminate\Support\Carbon
    {
        $window = now()->addDays(self::TTL_DAYS);

        // The entry deadline, read where the COMPETITION is (EntryWindow) so a
        // link cannot outlive the door it leads to — nor die three hours before
        // it, which is what `endOfDay()` in UTC did for a Bahrain event.
        foreach ([EntryWindow::closesAt($event), $event->date?->copy()->endOfDay()] as $limit) {
            if ($limit && $limit->lt($window)) {
                $window = $limit->copy();
            }
        }

        return $window->isPast() ? now()->addHours(6) : $window;
    }

    /**
     * A person record for somebody who has never heard of us.
     *
     * Deliberately not an account: no password, no email, not discoverable, and
     * flagged so every listing that means "members" leaves them out.
     */
    /**
     * A person who is not on the platform.
     *
     * No email, no password, not discoverable — a record of somebody who
     * competed, which they can later claim and own. Gender and birthdate are
     * written only when the person entering them actually knew: a blank is
     * honest, and a guessed birthdate is worse than none at all because it
     * drives age groups and the minor safeguards.
     */
    private function makeUnclaimedPerson(string $fullName, array $details = []): User
    {
        return User::create([
            'full_name' => $fullName,
            'name' => $fullName,
            'email' => null,
            'password' => null,
            'is_unclaimed' => true,
            'is_discoverable' => false,
            'gender' => $details['gender'] ?? null,
            'birthdate' => $details['birthdate'] ?? null,
        ]);
    }

    /**
     * The club named on the entry.
     *
     * An explicit choice wins, but only over a club the actor may actually enter
     * for — otherwise anyone entering a walk-in could attribute them to a club
     * they have nothing to do with. Everything else falls back to the club this
     * entry would have been made for anyway.
     */
    private function representedClub(array $details, array $clubIds, ?int $fallback): ?int
    {
        $asked = $details['representing_tenant_id'] ?? null;

        if ($asked !== null && in_array((int) $asked, $clubIds, true)) {
            return (int) $asked;
        }

        return $fallback;
    }

    private function nameAlreadyEntered(ClubEvent $event, string $fullName): bool
    {
        return $this->enteredAs($event, $fullName)->isNotEmpty();
    }

    /**
     * The entries already standing in this event under exactly this name.
     *
     * The same query the duplicate check has always made, returning the ROWS
     * instead of a boolean — because "this name is already here" and "here is
     * the person it belongs to" are the same lookup, and a second activity
     * needs the second answer.
     *
     * @return Collection<int, ClubEventRegistration>
     */
    private function enteredAs(ClubEvent $event, string $fullName): Collection
    {
        $needle = mb_strtolower($fullName);

        return ClubEventRegistration::where('event_id', $event->id)
            ->where('role', 'participant')
            ->whereHas('user', fn ($q) => $q->whereRaw('lower(trim(full_name)) = ?', [$needle]))
            ->get(['id', 'user_id', 'category_id', 'representing_tenant_id', 'weight', 'belt_colour', 'entry_state']);
    }

    /**
     * The same athlete, the event's OTHER activity.
     *
     * Reached only from issue(), and only when the desk named an activity for a
     * name that is already on the list. It creates a second REGISTRATION
     * against the person who already exists and mints no person and no new
     * claim link: the claim completes the PERSON, so the one already issued
     * (or the account they have since claimed) covers both entries.
     *
     * Three refusals, and they are all about not guessing:
     *   · two different people share the name — which of them is entering?
     *   · they already hold this activity — there is nothing to add.
     *   · the entry is somebody a different club brought — not this desk's.
     *
     * @param  Collection<int, ClubEventRegistration>  $entered
     * @param  array<int, string>  $optionKeys
     * @return array{ok: bool, message: string, claim?: array}
     */
    private function alsoEnter(
        ClubEvent $event,
        User $actor,
        string $fullName,
        Collection $entered,
        EventCategory $target,
        array $optionKeys,
    ): array {
        $userIds = $entered->pluck('user_id')->unique();

        if ($userIds->count() > 1) {
            return ['ok' => false, 'message' => __('events.claim_duplicate_name', ['name' => $fullName])];
        }

        if ($entered->contains(fn (ClubEventRegistration $r) => (int) $r->category_id === (int) $target->id)) {
            return ['ok' => false, 'message' => __('events.claim_already_in_activity', [
                'name' => $fullName,
                'activity' => $target->name,
            ])];
        }

        /*
         * The WHOLE row, re-read. `enteredAs()` selects a handful of columns
         * for the duplicate check, and `replicate()` copies only what was
         * loaded — a partial model produced a sibling with no event_id, role or
         * status, which the NOT NULL constraint caught.
         */
        $first = ClubEventRegistration::find($entered->first()->id);

        if (! $first) {
            return ['ok' => false, 'message' => __('events.claim_dead')];
        }

        $registration = DB::transaction(function () use ($event, $actor, $first, $target, $optionKeys) {
            /*
             * A copy of the entry they already hold, in the other activity —
             * the same shape `updateDivisionMembers()` writes when an organiser
             * puts an existing entrant into a second group, so the two doors
             * produce identical rows.
             *
             * ⚠️ replicate(), never getAttributes(): the latter hands back RAW
             * json for cast columns, which the model then encodes a second time.
             */
            $sibling = $first->replicate();
            $sibling->category_id = $target->id;
            $sibling->registered_at = now();
            $sibling->entered_by = $actor->id;
            $sibling->entry_channel = 'club';
            // The money is this entry's own: nothing has been paid for it, and
            // whatever the first entry settled says nothing about this one.
            $sibling->paid = ! EventFee::isPaid($event, 'participant');
            $sibling->paid_at = null;
            $sibling->paid_by = null;
            $sibling->payment_proof = null;
            // A weigh-in is signed per entry. The weight itself is the same
            // body and is carried over; the SIGNATURE is not.
            $sibling->weighed_in_at = null;
            $sibling->weighed_in_by = null;
            $sibling->save();

            /* What this activity costs, frozen onto its own entry — the second
               activity IS a second purchase (the owner's model: "paid for
               separately"), priced from the event's own rows. */
            EventFee::commit($sibling, EventFee::quote($event, 'participant', $optionKeys));

            return $sibling;
        });

        $this->registry->for($event)->onEntrantsChanged($event, $target);

        /*
         * The claim link is NOT re-minted. It belongs to the person, it may
         * already have been used, and a link's secret is shown once — so the
         * answer carries the entry that was made and nothing that looks like a
         * fresh credential.
         */
        return [
            'ok' => true,
            'message' => __('events.claim_also_entered', [
                'name' => $fullName,
                'activity' => $target->name,
            ]),
            'claim' => [
                'competitor_id' => $registration->id,
                'name' => $fullName,
                'division' => $target->name,
                'url' => null,
            ],
        ];
    }

    /**
     * The club this entry is made FOR.
     *
     * One club is unambiguous. Somebody who runs several — a chain owner, the
     * organiser of the event itself — is most likely entering for the club
     * hosting it, so that wins when it is one of theirs. Otherwise nothing is
     * recorded rather than guessed: an entry with no club competes unattached,
     * which is honest, and the coach can set it afterwards.
     */
    private function issuingClub(User $actor, array $clubIds, ClubEvent $event): ?int
    {
        if (count($clubIds) === 1) {
            return (int) $clubIds[0];
        }

        if (in_array((int) $event->tenant_id, $clubIds, true)) {
            return (int) $event->tenant_id;
        }

        $owned = Tenant::where('owner_user_id', $actor->id)->pluck('id');

        return $owned->count() === 1 ? (int) $owned->first() : null;
    }

    /**
     * Whose claims this actor may see on this event.
     *
     * The coach sees his own. The organiser running the event sees every one,
     * because "who is still incomplete" is the checklist the day depends on.
     */
    private function issuerScope(ClubEvent $event, User $actor): Collection
    {
        if (app(EventAccess::class)->canManage($event, $actor)) {
            return EventEntryClaim::where('event_id', $event->id)->distinct()->pluck('created_by');
        }

        return collect([$actor->id]);
    }

    private function mayManage(EventEntryClaim $claim, User $actor): bool
    {
        if ($claim->created_by === $actor->id || $actor->isSuperAdmin()) {
            return true;
        }

        if ($claim->event && app(EventAccess::class)->canManage($claim->event, $actor)) {
            return true;
        }

        $tenantId = $claim->registration?->representing_tenant_id;

        return $tenantId !== null && in_array((int) $tenantId, $this->entries->administeredClubIds($actor), true);
    }

    /** One row, in the shape the coach's sheet renders. Never carries the token. */
    private function present(EventEntryClaim $claim, ?User $athlete, ?ClubEventRegistration $registration, ?string $url = null): array
    {
        return [
            'uuid' => $claim->uuid,
            'name' => $athlete?->full_name ?? '—',
            // The ENTRY this claim stands for. The screen that just registered
            // somebody needs it to put them straight into a group without
            // hunting for them by name in a list of two hundred.
            'competitor_id' => $registration?->id,
            'state' => $claim->state(),
            'entry_state' => $registration?->entry_state ?? 'incomplete',
            'division' => $registration?->category?->name,
            'expires' => $claim->expires_at->diffForHumans(),
            'contact' => $claim->contact_email ?: $claim->contact_phone,
            // Present ONLY on the response that just minted it. A link is a
            // credential: it is shown to the person who created it, once, and
            // never handed back out of a listing.
            'url' => $url,
        ];
    }

    private function notifyIssuer(EventEntryClaim $claim, User $athlete, ?string $division): void
    {
        $issuer = $claim->issuer;
        if (! $issuer) {
            return;
        }

        rescue(fn () => UserNotification::notifyUser($issuer->id, 'event',
            __('events.claim_completed_title', ['name' => $athlete->full_name]), [
                'body' => __('events.claim_completed_body', [
                    'name' => $athlete->full_name,
                    'division' => $division ?: __('events.claim_at_weigh_in'),
                ]),
                'icon' => 'bi-person-check-fill',
                'action_url' => route('me.events.show', $claim->event->uuid),
                'subject_type' => (new ClubEvent)->getMorphClass(),
                'subject_id' => $claim->event_id,
                'tenant_id' => $claim->event->tenant_id,
            ]), null, false);
    }
}
