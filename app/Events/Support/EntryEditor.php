<?php

namespace App\Events\Support;

use App\Clubs\Models\Tenant;
use App\Events\EventTypeRegistry;
use App\Members\Models\User;
use App\Members\Models\UserRelationship;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventCategory;
use App\Models\EventClub;
use App\Sports\Combat\BeltRank;
use App\Support\StoragePath;
use App\Traits\StoresBase64Images;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Changing an entry after it has been made.
 *
 * WHY THIS EXISTS
 * ---------------
 * A competitor enters once. `club_event_registrations` carries
 * `unique(event_id, user_id)`, and that is deliberate — thirty-nine places read
 * that table as "who is competing", so a second row would be one forgotten
 * `where` away from putting the same person in a bracket twice. It is also why
 * Gi and No-Gi are two EVENTS rather than two entries.
 *
 * But one-shot entry has a consequence nobody built for: whatever they typed at
 * two in the morning on a phone is what they compete as. Until now an athlete
 * could change exactly two things about their entry — their proof of payment
 * and which club they represent — and could not withdraw at all. Their weight,
 * their belt, their photograph, their date of birth and their gender were
 * frozen. Those are the five most error-prone fields on the form AND the ones
 * that decide their division and their safety bracket. The only remedy was to
 * telephone the organiser.
 *
 * So: one editor, two audiences. The athlete fixing their own entry and the
 * organiser fixing somebody else's are the same operation with different
 * authority and a different window, which is exactly the shape that belongs in
 * one class rather than two screens that drift.
 *
 * WHAT IT WILL NOT DO
 * -------------------
 *   · It never creates or deletes an entry. Entering is EntryService and
 *     PublicEntry; leaving is Withdrawal and the organiser's removal. This only
 *     edits a row that already exists.
 *   · It never lets a self-declared figure masquerade as a verified one. An
 *     athlete who edits their weight after an official has weighed them in
 *     CLEARS that verification and sends them back to the desk (see
 *     `applyWeight`). The desk's signature is not something a form can forge.
 *   · It never silently re-cuts a bracket. Division is re-derived only while
 *     the competition has not started; after that the organiser moves people
 *     deliberately, through the division screen that already exists.
 *
 * Cross-sport by construction: it asks the event's own package to classify and
 * to re-derive, and never knows which sport it is serving.
 */
class EntryEditor
{
    use StoresBase64Images;

    /*
     * Everything this editor knows how to change.
     *
     * `email` and `mobile` joined the list on 2026-09-06, and they are not
     * entry facts at all — they are how the person gets back into the account
     * the entry lives in.
     *
     * They are here because there was nowhere else. The public door makes an
     * email OPTIONAL and signs people in by TELEPHONE NUMBER, so an entrant can
     * end up holding an account with no address on it. Every other screen that
     * could add one sits behind `verified` — which an account with no email can
     * never pass — and password reset demands an address it does not have. So a
     * mistyped number or a forgotten password was permanent, and this panel is
     * the one screen such a person can always reach (`auth`, deliberately not
     * `verified`; see EntryPanelController).
     */
    public const FIELDS = ['weight', 'belt_colour', 'belt_grade', 'photo', 'club', 'name', 'birthdate', 'gender', 'nationality', 'email', 'mobile'];

    /**
     * The fields that are how a person gets back into their own account.
     *
     * Held apart from the rest because they carry a stricter permission rule
     * than a weight does: changing somebody's sign-in details is taking their
     * account, not correcting their entry.
     */
    public const CONTACT_FIELDS = ['email', 'mobile'];

    /** The fields that can move somebody into a different division. */
    private const DIVISION_FIELDS = ['weight', 'birthdate', 'gender'];

    public function __construct(
        private EventAccess $access,
        private EventTypeRegistry $registry,
        private EntryService $entries,
        private AudienceResolver $audience,
    ) {}

    /* ==================== What may be changed, and by whom ==================== */

    /**
     * What this actor may change on this entry RIGHT NOW, and why not.
     *
     * Returned rather than thrown so a screen can render a locked field with
     * the reason beside it. A form that hides what it will not accept teaches
     * nobody; a form that says "your weight is fixed because the competition
     * has started" answers the question before it is asked.
     *
     * @return array{
     *     may_edit: bool,
     *     role: string,
     *     window: array{open: bool, reason: ?string},
     *     fields: array<string, array{editable: bool, reason: ?string}>
     * }
     */
    public function permissions(ClubEvent $event, ClubEventRegistration $registration, User $actor): array
    {
        $isSelf = (int) $registration->user_id === (int) $actor->id;
        $isManager = $this->access->canManage($event, $actor);

        $role = $isManager ? 'organiser' : ($isSelf ? 'athlete' : 'none');

        if (! $isSelf && ! $isManager) {
            return [
                'may_edit' => false,
                'role' => 'none',
                'window' => ['open' => false, 'reason' => __('events.entry_edit_not_yours')],
                'fields' => $this->allLocked(__('events.entry_edit_not_yours')),
            ];
        }

        $window = $this->window($event, $isManager);

        if (! $window['open']) {
            return [
                'may_edit' => false,
                'role' => $role,
                'window' => $window,
                'fields' => $this->allLocked($window['reason']),
            ];
        }

        $fields = [];

        foreach (self::FIELDS as $field) {
            $fields[$field] = ['editable' => true, 'reason' => null];
        }

        // Date of birth and gender are not entry facts — they are the PERSON,
        // and they belong to whoever that person is. An organiser fixing an
        // account they created themselves is doing data entry; an organiser
        // editing a real member's date of birth is editing somebody else's
        // identity, and that is not theirs to do however convenient it would
        // be. The division screen is the honest tool for the case they
        // actually have (this athlete is in the wrong age group).
        //
        // ⚠️ …unless the actor already holds that authority OVER THE PERSON
        // somewhere else in the platform. A super-admin and a confirmed
        // guardian can both change this member's date of birth on the member
        // profile screen (MemberController::authorizeMemberWrite — super-admin
        // → self → guardian), so locking them out HERE did not protect the
        // member from anything: it only sent the one person who may fix it to
        // another screen to do the same edit. The lock is for an organiser with
        // no relationship to the athlete, and that is exactly who it still
        // stops.
        /*
         * How somebody signs in is theirs, manager or not.
         *
         * An organiser correcting a weight is doing their job. An organiser
         * changing the email on an account they hold no authority over is
         * taking that account — it would let them receive the reset link. So
         * this is the same test a date of birth takes, applied WITHOUT the
         * `$isManager` qualifier: a manager has no extra claim here, and a
         * non-manager never reaches this class for somebody else's entry
         * anyway.
         */
        if (! $isSelf && ! $this->mayEditIdentity($actor, $registration->user)) {
            foreach (self::CONTACT_FIELDS as $field) {
                $fields[$field] = [
                    'editable' => false,
                    'reason' => __('events.entry_edit_identity_theirs', [
                        'name' => $registration->user?->full_name ?: $registration->user?->name ?: '',
                    ]),
                ];
            }
        }

        if ($isManager && ! $isSelf && ! $this->mayEditIdentity($actor, $registration->user)) {
            /* `name` is deliberately NOT in this list. A misspelled name on a
               draw sheet, a hall screen and an engraved medal is the
               organiser's problem to fix and nobody else's, and unlike a date
               of birth it moves nothing — it does not decide an age group, a
               weight class or a safeguarding rule. Date of birth, gender and
               nationality do describe the person rather than the entry, so
               they stay theirs. */
            foreach (['birthdate', 'gender', 'nationality'] as $field) {
                $fields[$field] = [
                    'editable' => false,
                    'reason' => __('events.entry_edit_identity_theirs', [
                        'name' => $registration->user?->full_name ?: $registration->user?->name ?: '',
                    ]),
                ];
            }
        }

        return ['may_edit' => true, 'role' => $role, 'window' => $window, 'fields' => $fields];
    }

    /**
     * May this actor edit the PERSON behind the entry — their date of birth,
     * gender and nationality?
     *
     * The same rule the rest of the platform writes a member's own record with
     * (MemberController::authorizeMemberWrite): a super-admin, or a confirmed
     * guardian of them. Plus the case this editor already allowed — a person
     * nobody has claimed, who exists only because an organiser typed them in
     * and therefore has no identity of their own to protect yet.
     *
     * Deliberately NOT "club admin of a club they belong to": that is the rule
     * for READING a member (AuthorizesClubAccess::canViewMember), and writing
     * somebody's identity is the stricter thing.
     */
    private function mayEditIdentity(User $actor, ?User $athlete): bool
    {
        if ($athlete === null || $athlete->is_unclaimed) {
            return true;
        }

        if ($actor->isSuperAdmin()) {
            return true;
        }

        return UserRelationship::where('guardian_user_id', $actor->id)
            ->where('dependent_user_id', $athlete->id)
            ->exists();
    }

    /**
     * Is the editing window open?
     *
     * For the ATHLETE it closes when the competition starts. Not at weigh-in —
     * an entrant who notices at the door that their birth year is wrong should
     * be able to fix it, and the desk re-verifies anything that matters anyway.
     *
     * For the ORGANISER it never closes. They are the authority on their own
     * entry list, including after the last bout: a medal engraved from a
     * misspelled entry is fixed by editing the entry.
     *
     * @return array{open: bool, reason: ?string}
     */
    private function window(ClubEvent $event, bool $isManager): array
    {
        if ($isManager) {
            return ['open' => true, 'reason' => null];
        }

        if ($event->status === 'cancelled') {
            return ['open' => false, 'reason' => __('events.entry_edit_event_cancelled')];
        }

        if ($event->hasEnded()) {
            return ['open' => false, 'reason' => __('events.entry_edit_event_over')];
        }

        if ($event->hasStarted() || $event->isOverdueToStart()) {
            return ['open' => false, 'reason' => __('events.entry_edit_event_started')];
        }

        return ['open' => true, 'reason' => null];
    }

    /** @return array<string, array{editable: bool, reason: ?string}> */
    private function allLocked(?string $reason): array
    {
        return array_fill_keys(self::FIELDS, ['editable' => false, 'reason' => $reason]);
    }

    /* ==================== Applying a change ==================== */

    /**
     * Apply an edit.
     *
     * ABSENT IS NOT BLANK. Only keys actually present in `$data` are touched —
     * a panel that sends one field must never wipe the other six, and a partial
     * request is the normal case here (a photo sheet sends a photo, a weight
     * slider sends a weight). This mirrors the `$optional` block in
     * MemberController::update(); see CLAUDE.md, "Who Fills The Form Decides".
     *
     * @param  array<string, mixed>  $data  any subset of self::FIELDS
     * @return array{ok: bool, message: string, changed?: array<int, string>,
     *               entry?: array, division_changed?: bool, reweigh?: bool, field?: string}
     */
    public function apply(ClubEvent $event, ClubEventRegistration $registration, array $data, User $actor): array
    {
        // Scoped, always: an entry id from another competition must not be
        // writable through this competition's URL.
        if ((int) $registration->event_id !== (int) $event->id) {
            return ['ok' => false, 'message' => __('events.entry_edit_not_found')];
        }

        $permissions = $this->permissions($event, $registration, $actor);

        if (! $permissions['may_edit']) {
            return ['ok' => false, 'message' => $permissions['window']['reason'] ?? __('events.entry_edit_not_yours')];
        }

        // Refuse a field this actor may not touch, rather than dropping it
        // silently — a form that appears to save and does not is worse than one
        // that says no.
        foreach (array_keys($data) as $field) {
            if (! in_array($field, self::FIELDS, true)) {
                continue;
            }

            if (! ($permissions['fields'][$field]['editable'] ?? false)) {
                return [
                    'ok' => false,
                    'field' => $field,
                    'message' => $permissions['fields'][$field]['reason'] ?? __('events.entry_edit_not_yours'),
                ];
            }
        }

        $isSelf = (int) $registration->user_id === (int) $actor->id;
        $athlete = $registration->user;

        if (! $athlete) {
            return ['ok' => false, 'message' => __('events.entry_edit_not_found')];
        }

        $changed = [];
        $reweigh = false;
        $previousPhoto = null;
        $newPhoto = null;
        $categoryBefore = $registration->category_id ? EventCategory::find($registration->category_id) : null;

        try {
            DB::transaction(function () use (
                $event, $registration, $athlete, $data, $isSelf,
                &$changed, &$reweigh, &$previousPhoto, &$newPhoto
            ) {
                $entryUpdates = [];
                $personUpdates = [];

                if (array_key_exists('weight', $data)) {
                    [$entryUpdates, $reweigh] = $this->applyWeight($registration, $data['weight'], $isSelf, $entryUpdates);
                    $changed[] = 'weight';
                }

                if (array_key_exists('belt_colour', $data)) {
                    // Normalised to the stored vocabulary. The desk used to
                    // write 'White' while every other door wrote 'white'.
                    $colour = $data['belt_colour'] === null || $data['belt_colour'] === ''
                        ? null
                        : mb_strtolower(trim((string) $data['belt_colour']));

                    $entryUpdates['belt_colour'] = $colour;
                    $changed[] = 'belt_colour';
                }

                if (array_key_exists('belt_grade', $data)) {
                    $grade = trim((string) ($data['belt_grade'] ?? ''));
                    $entryUpdates['belt_grade'] = $grade !== '' ? mb_substr($grade, 0, 32) : null;
                    $changed[] = 'belt_grade';
                }

                if (array_key_exists('club', $data)) {
                    $entryUpdates = array_merge($entryUpdates, $this->applyClub($event, $registration, $athlete, $data['club'], $isSelf));
                    $changed[] = 'club';
                }

                if (array_key_exists('photo', $data)) {
                    $previousPhoto = $registration->photo;
                    $newPhoto = $this->applyPhoto($event, $registration, $data['photo']);

                    if ($newPhoto === false) {
                        throw new \RuntimeException('photo');
                    }

                    $entryUpdates['photo'] = $newPhoto;
                    $changed[] = 'photo';
                }

                // These live on the PERSON, not the entry.
                if (array_key_exists('name', $data)) {
                    $clean = trim(preg_replace('/\s+/u', ' ', (string) $data['name']));

                    // The one field that may never be blanked: a member with no
                    // name breaks every listing, card and search result on the
                    // platform (CLAUDE.md, "Who Fills The Form Decides").
                    if ($clean !== '') {
                        $personUpdates['full_name'] = mb_substr($clean, 0, 120);
                        $changed[] = 'name';
                    }
                }

                if (array_key_exists('nationality', $data)) {
                    $iso = strtoupper(trim((string) ($data['nationality'] ?? '')));
                    $personUpdates['nationality'] = preg_match('/^[A-Z]{2}$/', $iso) ? $iso : null;
                    $changed[] = 'nationality';
                }

                if (array_key_exists('birthdate', $data)) {
                    $personUpdates['birthdate'] = $data['birthdate'] ?: null;
                    $changed[] = 'birthdate';
                }

                if (array_key_exists('gender', $data)) {
                    $personUpdates['gender'] = $data['gender'] ?: null;
                    $changed[] = 'gender';
                }

                /*
                 * ===== How they sign in =====
                 *
                 * NEVER leave an account with no way back into it. Checked
                 * FIRST and across BOTH fields, because it is the one rule that
                 * cannot be judged from one of them: clearing an email is fine
                 * while a phone remains, and clearing a phone is fine while an
                 * address remains, and doing both at once locks the person out
                 * of the platform for good. The panel therefore sends both
                 * together whenever either changes, and an absent key means
                 * "leave that one as it is" rather than "blank it".
                 */
                if (array_key_exists('email', $data) || array_key_exists('mobile', $data)) {
                    $emailAfter = array_key_exists('email', $data)
                        ? trim((string) ($data['email'] ?? ''))
                        : trim((string) $athlete->email);

                    $mobileAfter = array_key_exists('mobile', $data)
                        ? trim((string) ($data['mobile'] ?? ''))
                        : trim((string) (is_array($athlete->mobile) ? ($athlete->mobile['number'] ?? '') : $athlete->mobile));

                    if ($emailAfter === '' && $mobileAfter === '') {
                        throw new \RuntimeException('contact');
                    }
                }

                if (array_key_exists('email', $data)) {
                    $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
                    $before = mb_strtolower(trim((string) $athlete->email));

                    if ($email !== $before) {
                        if ($email === '') {
                            $personUpdates['email'] = null;
                        } else {
                            /*
                             * Taken by somebody else.
                             *
                             * Refused BEFORE the write, because the column is
                             * unique and a database error here would surface as
                             * a 500 nobody can act on. The answer is the entry
                             * door's own sentence, unchanged and on purpose
                             * (CLAUDE.md, anti-enumeration): the same reply for
                             * a member of ten years and for somebody who
                             * mistyped, confirming nothing either way.
                             */
                            if (User::where('email', $email)->where('id', '!=', $athlete->id)->exists()) {
                                throw new \RuntimeException('email_taken');
                            }

                            $personUpdates['email'] = $email;

                            /*
                             * A NEW address has not been proved yet. Clearing
                             * the stamp is what puts the verification mail back
                             * within reach, which is the entire point of
                             * allowing this edit — and it is also what stops
                             * somebody inheriting a verified state they never
                             * earned by swapping the address on the account.
                             */
                            $personUpdates['email_verified_at'] = null;
                        }

                        $changed[] = 'email';
                    }
                }

                if (array_key_exists('mobile', $data)) {
                    $number = trim((string) ($data['mobile'] ?? ''));

                    /*
                     * ⚠️ `users.mobile` IS CAST TO ARRAY — `{code, number}`.
                     *
                     * Writing a bare string there is silent data loss: it saves
                     * without complaint and every reader on the platform (the
                     * profile modal, the entry door, the importer, the admin
                     * screens) then reads `['number']` off a string and shows
                     * nothing. So the value is built in the one shape they all
                     * expect — see App\Events\Support\PublicEntry::mobileColumn(),
                     * whose splitting rule this mirrors.
                     *
                     * `User::saving` derives `phone_key` from this column, so
                     * the sign-in lookup follows the change without being
                     * touched here. That is the whole reason a mistyped number
                     * is fixable at all.
                     */
                    $value = $number === ''
                        ? null
                        : $this->mobileColumn($number, $data['mobile_code'] ?? null, $athlete);

                    if ($value !== ($athlete->mobile ?: null)) {
                        $personUpdates['mobile'] = $value;
                        $changed[] = 'mobile';
                    }
                }

                if ($entryUpdates) {
                    $registration->forceFill($entryUpdates)->save();
                }

                if ($personUpdates) {
                    $athlete->forceFill($personUpdates)->save();
                }
            });
        } catch (\RuntimeException $e) {
            // The address belongs to another account. Answered with the entry
            // door's own sentence so the two doors say the same thing, and so
            // that neither confirms an account exists.
            if ($e->getMessage() === 'email_taken') {
                return ['ok' => false, 'field' => 'email', 'message' => __('events.public_enrol_sign_in')];
            }

            if ($e->getMessage() === 'contact') {
                return ['ok' => false, 'field' => 'email', 'message' => __('events.entry_edit_contact_needed')];
            }

            if ($e->getMessage() !== 'photo') {
                throw $e;
            }

            return ['ok' => false, 'field' => 'photo', 'message' => __('events.entry_edit_photo_rejected')];
        }

        // Only once the new one is safely stored, and only if it moved
        // (CLAUDE.md, "Delete Files Before Records" — the inverse case: on
        // REPLACE the old file goes only after the new one is committed).
        if ($previousPhoto && $newPhoto && $previousPhoto !== $newPhoto) {
            // Via EntryPhoto: `photo` may be a REFERENCE to the athlete's own
            // profile picture rather than a file this entry owns.
            EntryPhoto::discard($previousPhoto);
        }

        if (! $changed) {
            return ['ok' => true, 'message' => __('events.entry_edit_nothing'), 'changed' => [],
                    'entry' => $this->present($event, $registration->fresh(), $actor)];
        }

        $divisionChanged = $this->reclassify($event, $registration, $changed, $categoryBefore);

        $registration->refresh();

        $this->announce($event, $registration, $changed, $actor);

        return [
            'ok' => true,
            'message' => $reweigh
                ? __('events.entry_edit_saved_reweigh')
                : __('events.entry_edit_saved'),
            'changed' => array_values(array_unique($changed)),
            'division_changed' => $divisionChanged,
            'reweigh' => $reweigh,
            'entry' => $this->present($event, $registration, $actor),
        ];
    }

    /* ==================== One field at a time ==================== */

    /**
     * A new weight.
     *
     * The one rule worth stating out loud: if an official has already weighed
     * this athlete and the ATHLETE changes the figure, the verification is
     * cleared and they go back to the desk. The alternative — letting a form
     * overwrite a measurement somebody took in person and signed for — would
     * make `weighed_in_by` mean nothing, and it is the column the final draw
     * filters on.
     *
     * An ORGANISER changing it keeps the verification, because an organiser
     * correcting a mistyped figure IS the desk.
     *
     * @return array{0: array<string, mixed>, 1: bool}
     */
    private function applyWeight(ClubEventRegistration $registration, mixed $weight, bool $isSelf, array $updates): array
    {
        $value = $weight === null || $weight === '' ? null : round((float) $weight, 2);
        $updates['weight'] = $value;

        $wasVerified = $registration->weighed_in_by !== null;
        $moved = (float) $registration->weight !== (float) $value;

        if ($isSelf && $wasVerified && $moved) {
            $updates['weighed_in_at'] = null;
            $updates['weighed_in_by'] = null;

            return [$updates, true];
        }

        return [$updates, false];
    }

    /**
     * Which club they compete for.
     *
     * Per ENTRY, not per person — an athlete competes for one club this weekend
     * and another next year, which is why it lives on the registration.
     *
     * An ATHLETE may only claim a club they are actually an active member of
     * (`EntryService::representableClubs` is the same authority `register()`
     * checks) — OR one the organiser has put on this event's own list, which is
     * curated rather than claimed. An ORGANISER may name any active club.
     *
     * The value posted is one of two key shapes and both resolve here: an
     * `event_clubs` uuid (this event's list, including visiting teams with no
     * account on the platform) or a tenant slug (the platform, and every page
     * rendered before this change). A club with no account cannot be written to
     * `representing_tenant_id` — there is no tenant — so it is recorded on
     * `event_club_entrants` instead, and the two can never both be set.
     *
     * @return array<string, mixed>
     */
    private function applyClub(ClubEvent $event, ClubEventRegistration $registration, User $athlete, mixed $club, bool $isSelf): array
    {
        $key = is_array($club) ? ($club['slug'] ?? null) : $club;
        $key = is_string($key) ? trim($key) : null;

        // Explicitly unattached.
        if ($key === null || $key === '') {
            $this->syncEventClubEntrant($registration, null);

            return ['representing_tenant_id' => null, 'club_disowned_at' => null];
        }

        /*
         * THIS EVENT'S OWN LIST FIRST.
         *
         * The picker offers the clubs the organiser put behind this event
         * alongside the athlete's affiliations, and a club with no account on
         * the platform can only be named by the uuid of its row. Same key
         * shape, same order of resolution as the public door
         * (App\Events\Support\PublicEntry::resolveClub) — one club, one
         * answer, whichever screen asked.
         *
         * A club on the event's list is offered to the ATHLETE too, and is
         * accepted from them: the organiser curated that list, so it is not the
         * "any club on the platform" claim the membership check below guards.
         */
        $onEvent = EventClub::query()
            ->where('event_id', $event->id)
            ->where('state', '!=', 'declined')
            ->where('uuid', $key)
            ->first(['id', 'tenant_id']);

        if ($onEvent !== null && ! $onEvent->tenant_id) {
            // Written down by hand. There is no tenant to name, so the entry
            // records it through the event's own roster instead.
            $this->syncEventClubEntrant($registration, (int) $onEvent->id);

            return ['representing_tenant_id' => null, 'club_disowned_at' => null];
        }

        $tenant = $onEvent !== null
            ? Tenant::where('id', $onEvent->tenant_id)->where('status', 'active')->first(['id'])
            : Tenant::where('slug', $key)->where('status', 'active')->first(['id']);

        if (! $tenant) {
            $this->syncEventClubEntrant($registration, null);

            return ['representing_tenant_id' => null, 'club_disowned_at' => null];
        }

        // Naming a real club supersedes any written-down one: two answers to
        // "who did this entrant come with" is one answer too many.
        $this->syncEventClubEntrant($registration, null);

        if ($onEvent !== null) {
            return ['representing_tenant_id' => (int) $tenant->id, 'club_disowned_at' => null];
        }

        if ($isSelf) {
            $allowed = collect($this->entries->representableClubs($athlete))
                ->pluck('id')->map('intval')->all();

            if (! in_array((int) $tenant->id, $allowed, true)) {
                // Not a member of it. Silently dropping the claim would be a
                // lie, but this is reached only by a hand-made request — the
                // picker offers what they may claim — so it fails closed.
                return ['representing_tenant_id' => null, 'club_disowned_at' => null];
            }
        }

        // A fresh claim clears a previous disown: the club is being named
        // again, so the earlier "not ours" no longer describes anything.
        return ['representing_tenant_id' => (int) $tenant->id, 'club_disowned_at' => null];
    }

    /**
     * A new competitor photograph.
     *
     * Real bytes, server-assigned extension, server-built path — the folder
     * comes from StoragePath and the event's own public id, never from the
     * request (CLAUDE.md, "Image Uploads Must Validate Real Bytes" and "Upload
     * Storage Structure").
     *
     * @return string|false|null  path, false on refusal, null to clear
     */
    private function applyPhoto(ClubEvent $event, ClubEventRegistration $registration, mixed $photo): string|false|null
    {
        if ($photo === null || $photo === '') {
            return null;
        }

        if (! is_string($photo) || ! str_starts_with($photo, 'data:image/')) {
            return false;
        }

        return $this->storeBase64Image(
            $photo,
            StoragePath::event($event, 'competitors'),
            'c'.$registration->id.'-'.Str::random(16),
        ) ?? false;
    }

    /* ==================== Putting them in the right division ==================== */

    /**
     * Re-derive the division after a change that could have moved it.
     *
     * `EventType::classifyEntry()` deliberately refuses to touch an entry that
     * already HAS a division — re-cutting a drawn competitor on a weigh-in is
     * an organiser's decision, not a side effect of the desk. So a genuine
     * re-classification has to clear the category first, on purpose, which is
     * what this does — and only while the competition has not started.
     *
     * Both divisions are then re-cut: the one they left and the one they
     * joined. `updateDivisionMembers()` is the existing precedent for that pair
     * of calls, and skipping the vacated one leaves a bracket with a hole in it.
     */
    private function reclassify(ClubEvent $event, ClubEventRegistration $registration, array $changed, ?EventCategory $before): bool
    {
        if (! array_intersect($changed, self::DIVISION_FIELDS)) {
            return false;
        }

        // Once it is running, the entry list is what the mats are being run
        // from. An organiser moves people deliberately, on the division screen.
        if ($event->hasStarted() || $event->hasEnded() || $event->isOverdueToStart()) {
            return false;
        }

        $type = $this->registry->for($event);

        $registration->forceFill(['category_id' => null])->save();

        rescue(fn () => $type->classifyEntry($event, $registration), null, false);

        $registration->refresh();

        $after = $registration->category_id ? EventCategory::find($registration->category_id) : null;

        $movedTo = (int) ($after?->id ?? 0);
        $movedFrom = (int) ($before?->id ?? 0);

        // Re-cut what changed. Same category on both sides means one call.
        rescue(fn () => $type->onEntrantsChanged($event, $before), null, false);

        if ($movedTo !== $movedFrom) {
            rescue(fn () => $type->onEntrantsChanged($event, $after), null, false);
        }

        return $movedTo !== $movedFrom;
    }

    /* ==================== Telling everyone ==================== */

    /**
     * Push the change to everyone it affects, in the same request.
     *
     * A REFRESH signal rather than a crafted payload: the athlete's panel, the
     * organiser's entrant list and the weigh-in desk render the same entry
     * three different ways with three different permission sets, and
     * hand-building per-user cards is exactly what CLAUDE.md's realtime rule
     * says not to do. Each view re-fetches what it is allowed to see.
     *
     * Best-effort — the database stays the source of truth — but always
     * attempted.
     */
    private function announce(ClubEvent $event, ClubEventRegistration $registration, array $changed, User $actor): void
    {
        $watchers = $this->audience->entryWatchers($event, (int) $registration->user_id);

        $payload = [
            'action' => 'entry-updated',
            'event' => $event->uuid,
            'registration' => (int) $registration->id,
            'athlete' => (int) $registration->user_id,
            'fields' => array_values(array_unique($changed)),
            'by' => (int) $actor->id,
        ];

        rescue(fn () => \Realtime()->publishMany(array_map(
            fn (int $id) => ['topic' => \Realtime()->userTopic($id, 'events'), 'payload' => $payload],
            $watchers,
        )), null, false);
    }

    /* ==================== Reading it back ==================== */

    /**
     * The entry as a panel needs it.
     *
     * Only what the athlete and their organiser may both see. No payment proof
     * path, no internal ids beyond the registration's own, no storage paths —
     * the photo goes out as a URL through the one file door.
     *
     * @return array<string, mixed>
     */
    public function present(ClubEvent $event, ClubEventRegistration $registration, ?User $viewer = null): array
    {
        $athlete = $registration->user;
        $category = $registration->category_id ? EventCategory::find($registration->category_id) : null;
        $club = $registration->representing_tenant_id
            ? Tenant::find($registration->representing_tenant_id, ['id', 'slug', 'club_name', 'logo', 'country'])
            : null;

        /*
         * A club with no account on the platform is named through the event's
         * own list, not through `representing_tenant_id`, so it has to be read
         * from there or the sheet shows "unattached" for an entrant who plainly
         * is not. Same shape either way, and `slug` carries the key the picker
         * posts back — the event club's uuid (see clubChoices/applyClub).
         */
        $writtenDown = $club === null
            ? EventClub::query()
                ->join('event_club_entrants', 'event_club_entrants.event_club_id', '=', 'event_clubs.id')
                ->where('event_club_entrants.registration_id', $registration->id)
                ->where('event_clubs.event_id', $event->id)
                ->first(['event_clubs.uuid', 'event_clubs.name', 'event_clubs.logo', 'event_clubs.country'])
            : null;

        return [
            'registration' => (int) $registration->id,
            'name' => $athlete?->full_name ?: $athlete?->name,
            'photo' => $registration->photo ? file_url($registration->photo) : null,
            'weight' => $registration->weight !== null ? (float) $registration->weight : null,
            'weighed_in' => $registration->weighed_in_by !== null,
            'weighed_in_at' => $registration->weighed_in_at?->toIso8601String(),
            'belt_colour' => $registration->belt_colour,
            'belt_grade' => $registration->belt_grade,
            'birthdate' => $athlete?->birthdate?->toDateString(),
            'gender' => $athlete?->gender,
            'nationality' => $athlete?->nationality,
            'paid' => (bool) $registration->paid,
            'division' => $category?->name,
            'club' => $club ? [
                'slug' => $club->slug,
                'name' => $club->club_name,
                'logo' => $club->logo ? file_url($club->logo) : null,
                'country' => $club->country,
            ] : ($writtenDown ? [
                'slug' => $writtenDown->uuid,
                'name' => $writtenDown->name,
                'logo' => $writtenDown->logo ? file_url($writtenDown->logo) : null,
                'country' => $writtenDown->country,
            ] : null),
            'belts' => BeltRank::ladder(),
            'clubs' => $this->clubChoices($event, $registration, $athlete),
        ] + $this->contactFor($event, $athlete, $viewer);
    }

    /**
     * The person's contact details — only for eyes that already hold them.
     *
     * An address and a phone number are how somebody signs in, so they leave
     * this service only when the viewer IS that person, or already holds
     * authority over them elsewhere on the platform. A caller that passes no
     * viewer gets NOTHING, which is the safe default: a payload that has to be
     * asked for cannot leak into a roster by accident, and every existing
     * caller of present() that predates this keeps behaving exactly as it did.
     *
     * @return array<string, mixed>
     */
    private function contactFor(ClubEvent $event, ?User $athlete, ?User $viewer): array
    {
        if (! $athlete || ! $viewer) {
            return [];
        }

        $isSelf = (int) $athlete->id === (int) $viewer->id;

        // Somebody else's details need BOTH: a reason to be reading this
        // event's entries at all, and authority over that person. Either alone
        // is not enough — `mayEditIdentity` passes for ANYONE when the athlete
        // is unclaimed, which is right for an organiser doing data entry and
        // quite wrong for a competitor reading a roster.
        if (! $isSelf && ! ($this->access->canManage($event, $viewer) && $this->mayEditIdentity($viewer, $athlete))) {
            return [];
        }

        $mobile = is_array($athlete->mobile) ? $athlete->mobile : [];
        $code = trim((string) ($mobile['code'] ?? ''));
        $number = trim((string) ($mobile['number'] ?? ''));

        return [
            'email' => $athlete->email,
            'email_verified' => (bool) $athlete->email_verified_at,
            // The two halves separately, because that is the shape the column
            // is stored in and the shape the editor writes back — plus one
            // joined string for the row that only has to READ as a number.
            'mobile' => $number !== '' ? $number : null,
            'mobile_code' => $code !== '' ? $code : null,
            'mobile_display' => $number !== '' ? trim($code.' '.$number) : null,
        ];
    }

    /**
     * A typed telephone number, in the `{code, number}` shape the column holds.
     *
     * Mirrors App\Events\Support\PublicEntry::mobileColumn() deliberately,
     * including its one piece of cleverness: a number typed with its country
     * code already in it ("+973 3316 5444") is split rather than stored with
     * the code written twice. A missing code falls back to the one already on
     * the account, so somebody correcting the last digit of their number does
     * not silently lose their country.
     *
     * @return array{code: ?string, number: string}
     */
    private function mobileColumn(string $number, mixed $code, User $athlete): array
    {
        $existing = is_array($athlete->mobile) ? (string) ($athlete->mobile['code'] ?? '') : '';
        $code = trim((string) (is_string($code) && trim($code) !== '' ? $code : $existing));

        $bare = (string) preg_replace('/\s+/', '', $number);

        if ($code !== '' && str_starts_with($bare, $code)) {
            $number = trim(substr($bare, strlen($code)));
        }

        return [
            'code' => $code !== '' ? mb_substr($code, 0, 8) : null,
            'number' => mb_substr(trim($number), 0, 24),
        ];
    }

    /**
     * The clubs this entry could be moved to, for the picker.
     *
     * FOUR sources, rolled into ONE list with no value appearing twice:
     *
     *  1. The club it names NOW — always present, even if the athlete has since
     *     left it, so an organiser opening the sheet sees the current answer in
     *     the list rather than a value the control cannot show.
     *  2. The athlete's OWN active clubs — the clubs they are actually a member
     *     of, and the only ones `applyClub` accepts from the athlete themselves
     *     on the platform-slug path.
     *  3. The clubs already competing at THIS event, read off the other
     *     entries — the realistic answer to "which club is this walk-in with?".
     *  4. The clubs the ORGANISER put on this event's own list (`event_clubs`),
     *     including the ones written down by hand that have no account on the
     *     platform at all. A visiting team is entered once, on the clubs page,
     *     and then has to be nameable here.
     *
     * ⚠️ Deduplicated across the two KINDS of club, not just within each.
     * The same club reaches this list as a tenant (an affiliation, or another
     * entry naming it) and as a row on the event's list, and a picker showing
     * "Bahrain TKD" twice is a picker that cannot be answered. A tenant-backed
     * event club folds onto its tenant by id; a written-down one folds onto a
     * tenant of the same name, compared case- and space-insensitively, because
     * that is the only thing the two records share.
     *
     * The surviving entry is always the one that carries the most: a real
     * tenant (slug, logo, country) beats a name somebody typed.
     *
     * `slug` is the key the browser posts back, and it is one of two shapes —
     * a tenant slug, or an event club's uuid for a club with no account.
     * `applyClub` resolves both. Deliberately not "every active club on the
     * platform": that is a search, not a picker, and this is a bottom sheet at
     * a mat.
     *
     * @return array<int, array{slug: string, name: string, logo: ?string}>
     */
    private function clubChoices(ClubEvent $event, ClubEventRegistration $registration, ?User $athlete): array
    {
        $ids = [];

        if ($registration->representing_tenant_id) {
            $ids[] = (int) $registration->representing_tenant_id;
        }

        if ($athlete) {
            foreach ($this->entries->representableClubs($athlete) as $club) {
                $ids[] = (int) $club['id'];
            }
        }

        foreach (DB::table('club_event_registrations')
            ->where('event_id', $event->id)
            ->whereNotNull('representing_tenant_id')
            ->distinct()->pluck('representing_tenant_id') as $id) {
            $ids[] = (int) $id;
        }

        // The event's own list. A declined invitation is not an answer: a club
        // that said no is not competing, so it is not offered.
        $eventClubs = EventClub::query()
            ->where('event_id', $event->id)
            ->where('state', '!=', 'declined')
            ->orderBy('name')
            ->get(['id', 'uuid', 'tenant_id', 'name', 'logo']);

        foreach ($eventClubs as $row) {
            if ($row->tenant_id) {
                $ids[] = (int) $row->tenant_id;
            }
        }

        $ids = array_values(array_unique(array_filter($ids)));

        $out = [];
        $seenTenants = [];
        $seenNames = [];

        if ($ids) {
            foreach (Tenant::whereIn('id', $ids)
                ->where('status', 'active')
                ->orderBy('club_name')
                ->get(['id', 'slug', 'club_name', 'logo']) as $t) {
                $seenTenants[(int) $t->id] = true;
                $seenNames[$this->clubNameKey((string) $t->club_name)] = true;

                $out[] = [
                    'slug' => (string) $t->slug,
                    'name' => (string) $t->club_name,
                    'logo' => $t->logo ? file_url($t->logo) : null,
                ];
            }
        }

        foreach ($eventClubs as $row) {
            // A tenant-backed row is already in the list above — or its tenant
            // is not active, and then it is not selectable at all.
            if ($row->tenant_id) {
                continue;
            }

            $key = $this->clubNameKey((string) $row->name);

            if ($key === '' || isset($seenNames[$key])) {
                continue;
            }

            $seenNames[$key] = true;

            $out[] = [
                'slug' => (string) $row->uuid,
                'name' => (string) $row->name,
                'logo' => $row->logo ? file_url($row->logo) : null,
            ];
        }

        return $out;
    }

    /**
     * The shape of a club name used to tell two records apart.
     *
     * Case folded, runs of whitespace collapsed. Enough to catch the real
     * collision (the organiser typing a club the platform already carries),
     * and deliberately not clever: normalising harder would fold two genuinely
     * different clubs into one, which is worse than showing both.
     */
    private function clubNameKey(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name)));
    }

    /**
     * Record — or clear — which club on the EVENT'S list brought this entrant.
     *
     * Only a club with no account on the platform needs this: a real one is
     * named by `representing_tenant_id` on the entry itself. `unique(registration_id)`
     * means one athlete represents one club at one competition, so this is an
     * upsert, and passing null is how "no written-down club" is said.
     */
    private function syncEventClubEntrant(ClubEventRegistration $registration, ?int $eventClubId): void
    {
        DB::table('event_club_entrants')->where('registration_id', $registration->id)->delete();

        if ($eventClubId === null) {
            return;
        }

        DB::table('event_club_entrants')->insert([
            'event_club_id' => $eventClubId,
            'registration_id' => $registration->id,
            'added_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

}
