<?php

namespace App\Events\Support;

use App\Clubs\Models\Tenant;
use App\Events\EventTypeRegistry;
use App\Members\Models\User;
use App\Members\Models\UserRelationship;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventEntryClaim;
use App\Members\Models\UserNotification;
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
        $state = $this->entries->entriesState($event);
        if (! $state['open']) {
            return ['ok' => false, 'message' => $state['note'] ?? __('events.entry_closed_generic')];
        }

        if ($event->max_capacity && $event->participantRegistrations()->count() >= $event->max_capacity) {
            return ['ok' => false, 'message' => __('events.entry_full')];
        }

        // Two people of the same name in one competition is nearly always the
        // same person entered twice. Refuse it and say so — a duplicate in a
        // draw is discovered on the mat, which is the worst place to find it.
        if ($this->nameAlreadyEntered($event, $fullName)) {
            return ['ok' => false, 'message' => __('events.claim_duplicate_name', ['name' => $fullName])];
        }

        $tenantId = $this->issuingClub($actor, $clubIds, $event);

        // What the person at the desk happens to know. NONE of it is required —
        // a name alone is still the whole contract (CLAUDE.md → "Who Fills The
        // Form Decides What It Demands") — but refusing to RECORD it when they
        // do know is its own kind of loss: an entry with no gender, age or
        // weight cannot be sorted into a division, so somebody would have to
        // chase the same athlete twice.
        $tenantId = $this->representedClub($details, $clubIds, $tenantId);

        $result = DB::transaction(function () use ($event, $actor, $fullName, $email, $phone, $tenantId, $details, $optionKeys) {
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
            // Only ever a person nobody has claimed. A real member entered by
            // name and later matched is never touched.
            if ($athlete && $athlete->is_unclaimed) {
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

        foreach ([$event->enrollment_ends_at, $event->date] as $limit) {
            if ($limit && $limit->lt($window)) {
                $window = $limit->copy()->endOfDay();
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
        $needle = mb_strtolower($fullName);

        return ClubEventRegistration::where('event_id', $event->id)
            ->where('role', 'participant')
            ->whereHas('user', fn ($q) => $q->whereRaw('lower(trim(full_name)) = ?', [$needle]))
            ->exists();
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
    private function issuerScope(ClubEvent $event, User $actor): \Illuminate\Support\Collection
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
