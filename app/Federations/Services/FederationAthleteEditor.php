<?php

namespace App\Federations\Services;

use App\Events\EventTypeRegistry;
use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Events\Models\EventOfficial;
use App\Events\Support\CategorySystems;
use App\Events\Support\EntryEditor;
use App\Events\Support\EventStreams;
use App\Federations\Models\FederationAthlete;
use App\Federations\Models\FederationClub;
use App\Federations\Models\FederationEntryAthlete;
use App\Members\Models\User;
use App\Members\Support\ProfilePhoto;
use App\Support\Countries;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Edit an entered athlete from the event's Participants tab: who they are
 * (name, gender, date of birth OR a stated age, nationality, photo), their
 * national ID (CPR), their weight on this entry, and the club they are
 * entered under.
 *
 * Scope is the entry: the athlete must stand, active, on an entry of an event
 * of this viewer's federation (FederationDesk::entryAthleteInScope), and only
 * a manager writes.
 *
 * WHOSE record it is no longer limits what changes (owner, 2026-10-15: "let
 * the federation edit the profile"). A federation manager edits the person's
 * real profile whether or not they have claimed their account — including
 * replacing a photo the member chose. That is a deliberate exception to "A
 * desk photograph never replaces one the member chose" (CLAUDE.md), for this
 * one screen, made by the owner. Every change is logged (fields, never values).
 *
 * A stated age is never turned into a birthdate: the date of birth is cleared
 * and the age is kept on the event entry (club_event_registrations.declared_age),
 * which division placement reads when there is no birthdate.
 *
 * After a save the athlete is made sure of a place in the platform event:
 * the route's SyncPlatformEntries brings the entry across, and a change to
 * gender, age or weight re-places them while the event has not started.
 */
class FederationAthleteEditor
{
    public function __construct(
        private FederationDesk $desk,
        private FederationDirectory $directory,
        private FederationRegistry $registry,
        private FederationInvitation $invitation,
        private FederationPlatformEvent $platform,
    ) {}

    /** What the edit window shows. Null when the athlete is not in scope. */
    public function show(array $access, string $entryUuid, string $takeoneId): ?array
    {
        $row = $this->desk->entryAthleteInScope($access, $entryUuid, $takeoneId);
        if (! $row || ! $row->athlete?->user) {
            return null;
        }
        $ids = collect($this->directory->subtree($access['root']))->pluck('id');

        return $this->shape($row) + [
            'clubs' => $this->registry->clubsOf($ids)->map(function ($c) {
                $card = $this->registry->club($c, false);

                return ['value' => $this->registry->clubKey($c), 'label' => (string) $card['name'],
                    'logo' => $card['logo'] ?? null, 'pending' => ! $c instanceof FederationClub];
            })->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)->values()->all(),
        ];
    }

    /**
     * @param  array{name?: ?string, gender?: ?string, ageMode?: ?string, dob?: ?string, age?: mixed, nat?: ?string, natId?: ?string, weight?: mixed, photo?: ?string, club?: ?string}  $data
     * @return array{ok: bool, message: string, athlete?: array, entry?: string}
     */
    public function update(User $by, array $access, string $entryUuid, string $takeoneId, array $data): array
    {
        if (! ($access['manager'] ?? false)) {
            return ['ok' => false, 'message' => 'Only a federation manager can edit an athlete.'];
        }
        $row = $this->desk->entryAthleteInScope($access, $entryUuid, $takeoneId);
        $athlete = $row?->athlete;
        $user = $athlete?->user;
        if (! $row || ! $athlete || ! $user) {
            return ['ok' => false, 'message' => 'That athlete could not be found.'];
        }

        // National ID: one person per number on the register.
        $natId = array_key_exists('natId', $data) ? mb_substr(trim((string) $data['natId']), 0, 40) : null;
        if ($natId !== null && $natId !== '') {
            $hash = FederationAthlete::hashNationalId($natId);
            if ($hash && FederationAthlete::where('national_id_hash', $hash)->whereKeyNot($athlete->id)->exists()) {
                return ['ok' => false, 'message' => 'That national ID is already registered to another athlete.'];
            }
        }

        $iso3 = null;
        if (array_key_exists('nat', $data) && trim((string) $data['nat']) !== '') {
            $iso3 = Countries::iso3((string) $data['nat']);
            if ($iso3 === 'UNK' || ! Countries::iso2($iso3)) {
                return ['ok' => false, 'message' => 'Choose the athlete\'s nationality from the list.'];
            }
        }

        $ids = collect($this->directory->subtree($access['root']))->pluck('id');
        $club = null;
        if (! empty($data['club'])) {
            $club = $this->registry->clubFromKey((string) $data['club'], $ids);
            if (! $club) {
                return ['ok' => false, 'message' => 'Choose the athlete\'s club from the list.'];
            }
        }

        // Date of birth OR a stated age — one or the other, never both.
        // A caller that sends a date and no switch means a date of birth.
        $mode = ($data['ageMode'] ?? null) === 'age' ? 'age'
            : (array_key_exists('ageMode', $data) || array_key_exists('dob', $data) ? 'dob' : null);
        $age = null;
        if ($mode === 'age') {
            $age = is_numeric($data['age'] ?? null) ? (int) $data['age'] : null;
            if ($age === null || $age < 3 || $age > 99) {
                return ['ok' => false, 'message' => 'Enter the athlete\'s age, from 3 to 99.'];
            }
        }

        $ev = $row->entry?->event ? $this->platform->of($row->entry->event) : null;
        $changed = [];

        DB::transaction(function () use ($data, $user, $athlete, &$row, $natId, $iso3, $mode, $age, $ev, $club, $by, &$changed) {
            // Absent is not blank: a key the request did not send is left alone.
            if (array_key_exists('name', $data)) {
                $name = mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $data['name']))), 0, 150);
                if ($name !== '' && $name !== $user->full_name) {
                    $user->full_name = $name;
                    $user->name = $name;
                    $changed[] = 'name';
                }
            }
            if (array_key_exists('gender', $data) && ($data['gender'] ?: null) !== $user->gender) {
                $user->gender = $data['gender'] ?: null;
                $changed[] = 'gender';
            }
            if ($mode === 'dob' && array_key_exists('dob', $data)) {
                $dob = $data['dob'] ?: null;
                if ($dob !== ($user->birthdate?->format('Y-m-d'))) {
                    $user->birthdate = $dob;
                    $changed[] = 'birthdate';
                }
            } elseif ($mode === 'age' && $user->birthdate !== null) {
                $user->birthdate = null;
                $changed[] = 'birthdate';
            }
            if (array_key_exists('nat', $data)) {
                $iso2 = $iso3 ? strtoupper((string) Countries::iso2($iso3)) : null;
                if ($athlete->nationality !== $iso3) {
                    $athlete->nationality = $iso3;
                    $changed[] = 'nationality';
                }
                if ($user->nationality !== $iso2) {
                    $user->nationality = $iso2;
                }
            }
            if ($natId !== null && $natId !== (string) $athlete->national_id) {
                $athlete->national_id = $natId !== '' ? $natId : null;
                $athlete->national_id_hash = FederationAthlete::hashNationalId($natId);
                $changed[] = 'national ID';
            }
            if (array_key_exists('weight', $data)) {
                $w = is_numeric($data['weight']) ? max(10, min(250, round((float) $data['weight'], 1))) : null;
                if ($w !== $row->weight) {
                    $row->weight = $w;
                    $changed[] = 'weight';
                }
            }
            $user->isDirty() && $user->save();
            $athlete->isDirty() && $athlete->save();
            $row->isDirty() && $row->save();

            // The stated age lives on the event entry (every row they hold).
            if ($ev && $mode !== null) {
                $declared = $mode === 'age' ? $age : null;
                $regs = ClubEventRegistration::where('event_id', $ev->id)->where('user_id', $user->id)->where('role', 'participant')->get();
                foreach ($regs as $r) {
                    if (($r->declared_age !== null ? (int) $r->declared_age : null) !== $declared) {
                        $r->forceFill(['declared_age' => $declared])->save();
                        in_array('age', $changed, true) || $changed[] = 'age';
                    }
                }
            }

            if ($club && $this->registry->clubKey($club) !== $this->currentClubKey($row)) {
                $row = $this->invitation->moveAthlete($row, $club, $by);
                $changed[] = 'club';
                if ($ev) {
                    $this->platformClub($ev, $user, $club);
                }
            }
        });

        if (is_string($data['photo'] ?? null) && $data['photo'] !== '') {
            $photo = $this->replacePhoto($user->fresh(), $data['photo']);
            if ($photo === null) {
                return ['ok' => false, 'message' => $changed ? 'The details were saved, but that picture could not be used. Try a JPG or PNG.' : 'That picture could not be used. Try a JPG or PNG.'];
            }
            $changed[] = 'photo';
        }

        if ($ev) {
            // Make sure they are in the event the draw is made from.
            rescue(fn () => app(FederationPlatformEntries::class)->ensureEntered($row->entry->event, $row), null, true);
            $this->followOnPlatform($ev, $user->fresh(), $row, $changed);
        }

        // Who changed which fields, never the values (CLAUDE.md, Part 3 §16).
        if ($changed) {
            Log::info('federation.athlete.edited', ['by' => $by->id, 'athlete' => $athlete->takeone_id, 'fields' => $changed, 'claimed' => ! $user->is_unclaimed]);
        }

        return ['ok' => true, 'message' => $changed ? 'Athlete updated.' : 'Nothing to change.',
            'athlete' => $this->shape($row->fresh(['athlete.user', 'entry.event'])), 'entry' => $row->entry?->uuid];
    }

    /**
     * The platform event's own copy follows: the corrected name onto the
     * draw's snapshots, the entry's weight while unweighed, and — when gender,
     * age or weight changed and nothing has started — the division again.
     */
    private function followOnPlatform(ClubEvent $ev, User $user, FederationEntryAthlete $row, array $changed): void
    {
        $regs = ClubEventRegistration::where('event_id', $ev->id)->where('user_id', $user->id)->where('role', 'participant')->get();
        if ($regs->isEmpty()) {
            return;        // not entered yet: SyncPlatformEntries enters and places them
        }

        if (in_array('name', $changed, true)) {
            rescue(fn () => app(EntryEditor::class)->restampName($ev, (int) $user->id, (string) $user->full_name), null, true);
        }
        // A typed weight stands in only until the scale: once the person is
        // weighed on any entry, that reading is the weight on all of them.
        if (\App\Events\Support\BodyWeight::weighed($ev->id, (int) $user->id) !== null) {
            \App\Events\Support\BodyWeight::spread($ev->id, (int) $user->id);
        } elseif (in_array('weight', $changed, true) && $row->weight) {
            $regs->whereNull('weighed_in_at')->each(fn ($r) => $r->forceFill(['weight' => $row->weight])->save());
        }

        if (! array_intersect($changed, ['gender', 'birthdate', 'age', 'weight'])
            || $ev->hasStarted() || $ev->hasEnded() || EventStreams::many($ev)) {
            return;
        }
        $type = app(EventTypeRegistry::class)->for($ev);
        $systems = app(CategorySystems::class);
        foreach ($regs as $r) {
            $r->refresh();
            if (EventMatch::where('event_id', $ev->id)->where(fn ($q) => $q->where('a_competitor_id', $r->id)->orWhere('b_competitor_id', $r->id))->contested()->exists()) {
                continue;  // a fought bout fixes their division
            }
            $before = $r->category_id ? EventCategory::find($r->category_id) : null;
            $r->forceFill(['category_id' => null, 'category_source' => null])->save();
            $after = rescue(fn () => $systems->placeCreating($ev, $r->fresh('user')), null, true);
            $before && rescue(fn () => $type->onEntrantsChanged($ev, $before->setRelation('event', $ev)), null, true);
            if ($after && (int) $after->id !== (int) ($before?->id ?? 0)) {
                rescue(fn () => $type->onEntrantsChanged($ev, $after->setRelation('event', $ev)), null, true);
            }
        }
    }

    /** The club the athlete represents on the platform event follows the move. */
    private function platformClub(ClubEvent $ev, User $user, $club): void
    {
        $tenantId = $club instanceof FederationClub ? $club->tenant_id : null;
        $regs = ClubEventRegistration::where('event_id', $ev->id)->where('user_id', $user->id)->where('role', 'participant');
        $ids = (clone $regs)->pluck('id')->all();
        $regs->update(['representing_tenant_id' => $tenantId, 'updated_at' => now()]);
        // Off any written-down (non-platform) club; the sync puts an applicant club back on.
        foreach (\App\Events\Models\EventClub::where('event_id', $ev->id)->whereNull('tenant_id')->get() as $ec) {
            $ec->entrants()->detach($ids);
        }
    }

    private function currentClubKey(FederationEntryAthlete $row): ?string
    {
        $c = $row->entry?->club ?? $row->entry?->application;

        return $c ? $this->registry->clubKey($c) : null;
    }

    /**
     * The new face goes into the person's own store (ProfilePhoto) and becomes
     * their picture; every entry and appointment that was showing the OLD face
     * follows it, so the bracket, the hall screen and the lists agree. The old
     * file stays in their gallery — it is never deleted from here.
     */
    public function replacePhoto(User $user, string $base64): ?string
    {
        $old = trim((string) $user->profile_picture);
        $path = ProfilePhoto::store($user, $base64);
        if ($path === null) {
            return null;
        }
        if ($old !== $path) {
            $user->forceFill(['profile_picture' => $path])->save();
            if ($old !== '') {
                ClubEventRegistration::where('user_id', $user->id)->where('photo', $old)->get()->each(fn ($r) => $r->forceFill(['photo' => $path])->save());
                EventOfficial::where('user_id', $user->id)->where('photo', $old)->get()->each(fn ($o) => $o->forceFill(['photo' => $path])->save());
            }
        }

        return $path;
    }

    private function shape(FederationEntryAthlete $row): array
    {
        $a = $row->athlete;
        $u = $a->user;
        $path = ProfilePhoto::path($u);
        $ev = $row->entry?->event ? $this->platform->of($row->entry->event) : null;
        $regs = $ev ? ClubEventRegistration::where('event_id', $ev->id)->where('user_id', $u->id)->where('role', 'participant')->with('category:id,name')->get() : collect();
        $declared = $regs->pluck('declared_age')->filter(fn ($v) => $v !== null)->first();
        $on = $ev?->date ? Carbon::parse($ev->date) : now();

        return [
            'takeoneId' => $a->takeone_id,
            'name' => (string) ($u->full_name ?: $u->name),
            'gender' => $u->gender === 'Female' ? 'Female' : ($u->gender === 'Male' ? 'Male' : ''),
            'dob' => $u->birthdate?->format('Y-m-d') ?? '',
            'age' => $declared !== null ? (int) $declared : null,
            'ageMode' => ! $u->birthdate && $declared !== null ? 'age' : 'dob',
            'ageOnDay' => $u->birthdate ? (int) $u->birthdate->diffInYears($on) : ($declared !== null ? (int) $declared : null),
            'nat' => $a->nationality ?: (Countries::iso3($u->nationality) !== 'UNK' ? Countries::iso3($u->nationality) : ''),
            'natIso2' => ($i = Countries::iso2($a->nationality ?: (string) $u->nationality)) ? strtoupper($i) : '',
            'natId' => (string) ($a->national_id ?? ''),
            'weight' => $row->weight,
            'club' => $this->currentClubKey($row),
            'claimed' => ! $u->is_unclaimed,
            'photo' => $path ? file_url($path) : null,
            // Is the athlete in the platform event the draw is made from?
            'registered' => $regs->isNotEmpty(),
            'divisions' => $regs->pluck('category.name')->filter()->values()->all(),
            'platform' => (bool) $ev,
        ];
    }
}
