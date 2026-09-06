<?php

namespace App\EventLab\Support;

use App\EventLab\Models\SandboxEventClub;
use App\Events\Support\AudienceResolver;
use App\Events\Support\EntryService;
use App\Events\Support\EventAccess;
use App\Events\Support\EntryPhoto;

use App\Clubs\Models\Tenant;
use App\Events\EventTypeRegistry;
use App\Members\Models\User;
use App\Members\Models\UserRelationship;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventCategory;
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

    /** Everything this editor knows how to change. */
    /*
     * `email` and `mobile` were added here in the sandbox, 2026-09-05.
     *
     * The public door makes an email OPTIONAL and signs people in by phone, so
     * an entrant can end up holding an account with no address on it. Nothing
     * on the platform would then let them add one: `/me` sits behind `verified`
     * and the verify screen offers only Resend and Sign out, password reset
     * demands an email, and this editor — the one screen such a person can
     * always reach — did not carry either field. A mistyped phone number or a
     * forgotten password was therefore permanent.
     *
     * They are contact details, not entry facts, which is why the permission
     * rule below is stricter than for a weight: they are also how somebody
     * signs in, so only the person themselves (or somebody who already holds
     * authority over that person) may change them.
     */
    public const FIELDS = ['weight', 'belt_colour', 'belt_grade', 'photo', 'club', 'name', 'birthdate', 'gender', 'nationality', 'email', 'mobile'];

    /** The fields that are how a person gets back into their own account. */
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
        // How somebody signs in is theirs. An organiser correcting a weight is
        // doing their job; an organiser changing the email on an account they
        // do not hold authority over is taking it. Same test as a date of
        // birth, applied whether or not they are a manager.
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
                    $entryUpdates = array_merge($entryUpdates, $this->applyClub($event, $athlete, $data['club'], $isSelf));
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

                // An address, and the one rule that matters: it must not
                // already belong to somebody else, or the save would fail on
                // the unique index with a message nobody can act on.
                if (array_key_exists('email', $data)) {
                    $email = mb_strtolower(trim((string) ($data['email'] ?? '')));

                    if ($email === '') {
                        // Never leave an account with no way in at all. Blank
                        // is accepted only while a phone remains.
                        if (! trim((string) ($data['mobile'] ?? $athlete->mobile))) {
                            throw new \RuntimeException('contact');
                        }

                        $personUpdates['email'] = null;
                    } else {
                        $taken = User::where('email', $email)
                            ->where('id', '!=', $athlete->id)
                            ->exists();

                        if ($taken) {
                            throw new \RuntimeException('email_taken');
                        }

                        $personUpdates['email'] = $email;

                        // A NEW address has not been proved yet. Clearing the
                        // stamp is what puts the verification mail back within
                        // reach — which is the whole point of allowing this.
                        if ($email !== mb_strtolower((string) $athlete->email)) {
                            $personUpdates['email_verified_at'] = null;
                        }
                    }

                    $changed[] = 'email';
                }

                // The phone. `User::saving` derives `phone_key` from it, so the
                // sign-in lookup follows the change without being touched here.
                if (array_key_exists('mobile', $data)) {
                    $mobile = trim((string) ($data['mobile'] ?? ''));

                    if ($mobile === '' && ! trim((string) ($data['email'] ?? $athlete->email))) {
                        throw new \RuntimeException('contact');
                    }

                    $personUpdates['mobile'] = $mobile !== '' ? mb_substr($mobile, 0, 24) : null;
                    $changed[] = 'mobile';
                }

                if ($entryUpdates) {
                    $registration->forceFill($entryUpdates)->save();
                }

                if ($personUpdates) {
                    $athlete->forceFill($personUpdates)->save();
                }
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'email_taken') {
                return ['ok' => false, 'field' => 'email', 'message' => __('eventlab::messages.contact_email_taken')];
            }

            if ($e->getMessage() === 'contact') {
                return ['ok' => false, 'field' => 'email', 'message' => __('eventlab::messages.contact_need_one')];
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
     * checks). An ORGANISER may name any active club, or type a name for a club
     * that is not on this platform at all — visiting teams are the ordinary
     * case at an open competition.
     *
     * @return array<string, mixed>
     */
    private function applyClub(ClubEvent $event, User $athlete, mixed $club, bool $isSelf): array
    {
        $key = is_array($club) ? ($club['slug'] ?? null) : $club;
        $key = is_string($key) ? trim($key) : null;

        // Explicitly unattached — and off whichever of the event's clubs had
        // them.
        if ($key === null || $key === '') {
            $this->detachFromEventClubs($athlete, $event);

            return ['representing_tenant_id' => null, 'club_disowned_at' => null];
        }

        /*
         * One of THIS event's clubs, named by its uuid.
         *
         * Checked against the event, not against the platform: an id from
         * another competition, or a club nobody invited, is not an answer to
         * "who is this athlete competing for here".
         */
        $onEvent = SandboxEventClub::where('event_id', $event->id)
            ->where('state', '!=', 'declined')
            ->where('uuid', $key)
            ->first();

        if ($onEvent) {
            $this->linkToEventClub($athlete, $event, $onEvent);

            // A temporary club has no tenant to point at — the link IS the
            // answer, and representing_tenant_id stays empty until the club is
            // registered after the competition.
            return [
                'representing_tenant_id' => $onEvent->tenant_id ? (int) $onEvent->tenant_id : null,
                'club_disowned_at' => null,
            ];
        }

        /*
         * The value the entry already held, kept.
         *
         * Only that one: clubChoices() offers it so an entry made before the
         * clubs list existed can be saved without being moved, and this is the
         * matching half. Anything else is refused rather than written.
         */
        $tenant = Tenant::where('slug', $key)->where('status', 'active')->first(['id']);
        $registration = ClubEventRegistration::where('event_id', $event->id)
            ->where('user_id', $athlete->id)
            ->first(['id', 'representing_tenant_id']);

        if ($tenant && $registration && (int) $registration->representing_tenant_id === (int) $tenant->id) {
            return ['representing_tenant_id' => (int) $tenant->id, 'club_disowned_at' => null];
        }

        // Not on the event, and not what they already had. Fails closed — only
        // reachable by a hand-made request, because the picker offers nothing
        // else.
        $this->detachFromEventClubs($athlete, $event);

        return ['representing_tenant_id' => null, 'club_disowned_at' => null];
    }

    /** Put this athlete's entry under one of the event's clubs. */
    private function linkToEventClub(User $athlete, ClubEvent $event, SandboxEventClub $club): void
    {
        $id = ClubEventRegistration::where('event_id', $event->id)
            ->where('user_id', $athlete->id)
            ->value('id');

        if (! $id) {
            return;
        }

        // One athlete, one club, one competition — the unique key on
        // registration_id makes naming a second club a MOVE, not a copy.
        DB::table('sandbox_event_club_entrants')->updateOrInsert(
            ['registration_id' => $id],
            ['sandbox_event_club_id' => $club->id, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    /** Take them off whichever of the event's clubs had them. */
    private function detachFromEventClubs(User $athlete, ClubEvent $event): void
    {
        $id = ClubEventRegistration::where('event_id', $event->id)
            ->where('user_id', $athlete->id)
            ->value('id');

        if ($id) {
            DB::table('sandbox_event_club_entrants')->where('registration_id', $id)->delete();
        }
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
            ] : null,
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
     * viewer gets nothing, which is the safe default: a payload that has to be
     * asked for cannot leak into a roster by accident.
     *
     * @return array<string, mixed>
     */
    private function contactFor(ClubEvent $event, ?User $athlete, ?User $viewer): array
    {
        if (! $athlete || ! $viewer) {
            return [];
        }

        $isSelf = (int) $athlete->id === (int) $viewer->id;

        // Somebody else's details need BOTH: a reason to be looking at this
        // event's entries at all, and authority over that person. Either alone
        // is not enough — `mayEditIdentity` passes for anyone when the athlete
        // is unclaimed, which is right for an organiser doing data entry and
        // quite wrong for a competitor reading a roster.
        if (! $isSelf && ! ($this->access->canManage($event, $viewer) && $this->mayEditIdentity($viewer, $athlete))) {
            return [];
        }

        return [
            'email' => $athlete->email,
            'email_verified' => (bool) $athlete->email_verified_at,
            'mobile' => $athlete->mobile,
        ];
    }

    /**
     * The clubs this entry could be moved to, for the picker.
     *
     * Three sources, deduplicated and in this order of usefulness:
     *
     *  1. The club it names NOW — always present, even if the athlete has since
     *     left it, so an organiser opening the sheet sees the current answer in
     *     the list rather than a value the control cannot show.
     *  2. The athlete's OWN active clubs — what they may legitimately
     *     represent, and the only ones `applyClub` will accept from the athlete
     *     themselves (an organiser may name any active club, but these are the
     *     honest suggestions).
     *  3. The clubs already competing at THIS event — a small, relevant list,
     *     and the realistic answer to "which club is this walk-in with?".
     *
     * Deliberately not "every active club on the platform": that is a search,
     * not a picker, and this is a bottom sheet at a mat.
     *
     * @return array<int, array{slug: string, name: string, logo: ?string}>
     */
    private function clubChoices(ClubEvent $event, ClubEventRegistration $registration, ?User $athlete): array
    {
        /*
         * ONLY the clubs the organiser put on this event.
         *
         * It used to offer the athlete's own clubs plus every club already
         * competing — which is how somebody at the desk could enter an athlete
         * for a club that has nothing to do with this competition. A start list
         * is not a directory of the platform: the clubs taking part are decided
         * on the Clubs screen, by inviting one or writing one down, and this
         * picker is a view of THAT decision.
         *
         * Both kinds are offered, because to the person at the desk there is no
         * difference: a club with an account and a club that only has a name
         * are both just "who is this athlete with?". They are keyed by the
         * sandbox club's uuid so applyClub() can tell them apart and file the
         * answer in the right place.
         *
         * A declined invitation is not offered — a club that said no is not
         * competing.
         */
        $clubs = SandboxEventClub::with('tenant:id,slug,club_name,logo,country')
            ->where('event_id', $event->id)
            ->where('state', '!=', 'declined')
            ->orderBy('name')
            ->get();

        $choices = $clubs->map(fn (SandboxEventClub $c) => [
            // The picker's value. A uuid, not a slug: a temporary club has no
            // slug because it has no tenant.
            'slug' => (string) $c->uuid,
            'name' => (string) ($c->tenant?->club_name ?: $c->name),
            'logo' => ($logo = $c->tenant?->logo ?: $c->logo) ? file_url($logo) : null,
            'temporary' => $c->isTemporary(),
        ])->values()->all();

        /*
         * Whatever this entry names RIGHT NOW, even if it is not on that list.
         *
         * These entries arrived naming clubs from before the list existed, and
         * a control that cannot show its own current value reads as broken —
         * worse, saving would silently move the athlete. It is offered so it
         * can be KEPT; it is marked so nobody mistakes it for one of the
         * event's clubs.
         */
        if ($registration->representing_tenant_id
            && ! $clubs->contains(fn (SandboxEventClub $c) => (int) $c->tenant_id === (int) $registration->representing_tenant_id)) {
            $current = Tenant::find($registration->representing_tenant_id, ['id', 'slug', 'club_name', 'logo']);

            if ($current) {
                array_unshift($choices, [
                    'slug' => (string) $current->slug,
                    'name' => (string) $current->club_name,
                    'logo' => $current->logo ? file_url($current->logo) : null,
                    'off_list' => true,
                ]);
            }
        }

        return $choices;
    }
}
