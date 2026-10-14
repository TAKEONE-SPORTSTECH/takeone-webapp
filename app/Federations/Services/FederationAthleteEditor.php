<?php

namespace App\Federations\Services;

use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventOfficial;
use App\Federations\Models\FederationAthlete;
use App\Federations\Models\FederationEntryAthlete;
use App\Members\Models\User;
use App\Members\Support\ProfilePhoto;
use App\Support\Countries;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Edit an entered athlete from the event's Athletes tab: who they are, their
 * national ID, their weight on this entry, and their photograph.
 *
 * Scope is the entry: the athlete must stand, active, on an entry of an event
 * of this viewer's federation (FederationDesk::entryAthleteInScope), and only
 * a manager writes.
 *
 * WHOSE record it is decides what may change. A person the federation wrote
 * down (`is_unclaimed` — an import, an enrolment) has nobody else to keep
 * their details, so the federation keeps them: name, gender, birthdate,
 * nationality and the photo. Once they have claimed their account those are
 * theirs (CLAUDE.md → A Person's Photo Has One Home: "Their profile is
 * theirs"), and only what the federation itself holds — national ID,
 * nationality on its register, weight — stays editable here.
 */
class FederationAthleteEditor
{
    public function __construct(private FederationDesk $desk) {}

    /** What the edit dialog shows. Null when the athlete is not in scope. */
    public function show(array $access, string $entryUuid, string $takeoneId): ?array
    {
        $row = $this->desk->entryAthleteInScope($access, $entryUuid, $takeoneId);
        if (! $row || ! $row->athlete?->user) {
            return null;
        }

        return $this->shape($row) + [
            'countries' => collect(Countries::all())->map(fn ($c) => [
                'iso3' => Countries::iso3($c['code']), 'name' => $c['name'],
            ])->filter(fn ($c) => $c['iso3'] !== 'UNK')->values()->all(),
        ];
    }

    /**
     * @param  array{name?: ?string, gender?: ?string, dob?: ?string, nat?: ?string, natId?: ?string, weight?: mixed, photo?: ?string}  $data
     * @return array{ok: bool, message: string, athlete?: array}
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
        $ours = (bool) $user->is_unclaimed;

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

        $changed = [];
        $photo = null;
        DB::transaction(function () use ($data, $ours, $user, $athlete, $row, $natId, $iso3, &$changed) {
            // Absent is not blank: a key the request did not send is left alone.
            if ($ours) {
                if (array_key_exists('name', $data)) {
                    $name = mb_substr(trim(strip_tags((string) $data['name'])), 0, 150);
                    if ($name !== '' && $name !== $user->full_name) {
                        $user->full_name = $name;
                        $user->name = $name;
                        $changed[] = 'name';
                    }
                }
                if (array_key_exists('gender', $data) && $data['gender'] !== $user->gender) {
                    $user->gender = $data['gender'] ?: null;
                    $changed[] = 'gender';
                }
                if (array_key_exists('dob', $data)) {
                    $dob = $data['dob'] ?: null;
                    if ($dob !== ($user->birthdate?->format('Y-m-d'))) {
                        $user->birthdate = $dob;
                        $changed[] = 'birthdate';
                    }
                }
            }
            if (array_key_exists('nat', $data)) {
                $iso2 = $iso3 ? strtoupper((string) Countries::iso2($iso3)) : null;
                if ($athlete->nationality !== $iso3) {
                    $athlete->nationality = $iso3;
                    $changed[] = 'nationality';
                }
                if ($ours && $user->nationality !== $iso2) {
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
        });

        if ($ours && is_string($data['photo'] ?? null) && $data['photo'] !== '') {
            $photo = $this->replacePhoto($user->fresh(), $data['photo']);
            if ($photo === null) {
                return ['ok' => false, 'message' => $changed ? 'The details were saved, but that picture could not be used. Try a JPG or PNG.' : 'That picture could not be used. Try a JPG or PNG.'];
            }
            $changed[] = 'photo';
        }

        // Who changed which fields, never the values (CLAUDE.md, Part 3 §16).
        if ($changed) {
            Log::info('federation.athlete.edited', ['by' => $by->id, 'athlete' => $athlete->takeone_id, 'fields' => $changed]);
        }

        return ['ok' => true, 'message' => $changed ? 'Athlete updated.' : 'Nothing to change.', 'athlete' => $this->shape($row->fresh(['athlete.user']))];
    }

    /**
     * The new face goes into the person's own store (ProfilePhoto) and becomes
     * their picture; every entry and appointment that was showing the OLD face
     * follows it, so the bracket, the hall screen and the lists agree. The old
     * file stays in their gallery — it is never deleted from here.
     */
    private function replacePhoto(User $user, string $base64): ?string
    {
        $old = trim((string) $user->profile_picture);
        $path = ProfilePhoto::store($user, $base64);
        if ($path === null) {
            return null;
        }
        if ($old !== '' && $old !== $path) {
            $user->forceFill(['profile_picture' => $path])->save();
            ClubEventRegistration::where('user_id', $user->id)->where('photo', $old)->get()->each(fn ($r) => $r->forceFill(['photo' => $path])->save());
            EventOfficial::where('user_id', $user->id)->where('photo', $old)->get()->each(fn ($o) => $o->forceFill(['photo' => $path])->save());
        }

        return $path;
    }

    private function shape(FederationEntryAthlete $row): array
    {
        $a = $row->athlete;
        $u = $a->user;
        $path = ProfilePhoto::path($u);

        return [
            'takeoneId' => $a->takeone_id,
            'name' => (string) ($u->full_name ?: $u->name),
            'gender' => $u->gender === 'Female' ? 'Female' : ($u->gender === 'Male' ? 'Male' : ''),
            'dob' => $u->birthdate?->format('Y-m-d') ?? '',
            'nat' => $a->nationality ?: (Countries::iso3($u->nationality) !== 'UNK' ? Countries::iso3($u->nationality) : ''),
            'natId' => (string) ($a->national_id ?? ''),
            'weight' => $row->weight,
            // The federation keeps this person's details only while nobody has
            // claimed the record; after that they are the member's own.
            'ownDetails' => (bool) $u->is_unclaimed,
            'photo' => $path ? file_url($path) : null,
        ];
    }
}
