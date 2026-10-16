<?php

namespace App\Federations\Services;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventOfficial;
use App\Events\Support\EventAccess;
use App\Federations\Models\FederationEvent;
use App\Members\Models\User;
use App\Members\Support\ProfilePhoto;
use App\Support\Countries;
use Illuminate\Support\Facades\Log;

/**
 * The person behind an appointment on a federation event's Officials tab:
 * name, gender, nationality and photo (owner, 2026-10-15: "make the officials
 * edit popup the same" as the athletes'). The APPOINTMENT itself — job, club,
 * volunteer or paid — is still written by the event's own officiating
 * endpoints (PersonalEventController::updateOfficial and friends), never here.
 *
 * Who may: the event must be one of this viewer's federation's events, and the
 * viewer must be able to manage it (EventAccess::canManage — the same rule the
 * officiating endpoints enforce). As with athletes, the federation edits the
 * person's real profile whether or not they have claimed their account; a new
 * photo becomes their face everywhere (FederationAthleteEditor::replacePhoto)
 * and this appointment's own picture.
 */
class FederationOfficialEditor
{
    public function __construct(
        private FederationDirectory $directory,
        private FederationAthleteEditor $athletes,
    ) {}

    /** The platform event behind one of this viewer's federation events, when they may manage it. */
    public function eventInScope(array $access, ?User $viewer, string $eventUuid): ?ClubEvent
    {
        $ev = ClubEvent::where('uuid', $eventUuid)->first();
        if (! $ev || ! $viewer) {
            return null;
        }
        $q = FederationEvent::where('club_event_id', $ev->id)
            ->whereIn('federation_id', collect($this->directory->subtree($access['root']))->pluck('id'));
        if (($access['sports'] ?? []) !== []) {
            $q->whereIn('sport', $access['sports']);
        }
        if (! $q->exists() || ! app(EventAccess::class)->canManage($ev, $viewer)) {
            return null;
        }

        return $ev;
    }

    /** What the edit window shows about one appointment. Null when out of scope. */
    public function show(array $access, User $viewer, string $eventUuid, int $officialId): ?array
    {
        $ev = $this->eventInScope($access, $viewer, $eventUuid);
        $o = $ev ? EventOfficial::where('event_id', $ev->id)->whereKey($officialId)->with('user')->first() : null;

        return $o && $o->user ? $this->shape($o) : null;
    }

    /**
     * @param  array{name?: ?string, gender?: ?string, nat?: ?string, photo?: ?string}  $data
     * @return array{ok: bool, message: string, official?: array}
     */
    public function update(array $access, User $viewer, string $eventUuid, int $officialId, array $data): array
    {
        $ev = $this->eventInScope($access, $viewer, $eventUuid);
        $o = $ev ? EventOfficial::where('event_id', $ev->id)->whereKey($officialId)->with('user')->first() : null;
        $user = $o?->user;
        if (! $o || ! $user) {
            return ['ok' => false, 'message' => 'That official could not be found.'];
        }

        $changed = [];
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
        if (array_key_exists('nat', $data)) {
            $iso2 = trim((string) $data['nat']) !== '' ? Countries::iso2((string) $data['nat']) : null;
            if (trim((string) $data['nat']) !== '' && ! $iso2) {
                return ['ok' => false, 'message' => 'Choose the official\'s nationality from the list.'];
            }
            $iso2 = $iso2 ? strtoupper($iso2) : null;
            if ($iso2 !== ($user->nationality ?: null)) {
                $user->nationality = $iso2;
                $changed[] = 'nationality';
            }
        }
        $user->isDirty() && $user->save();

        if (is_string($data['photo'] ?? null) && $data['photo'] !== '') {
            $path = $this->athletes->replacePhoto($user->fresh(), $data['photo']);
            if ($path === null) {
                return ['ok' => false, 'message' => $changed ? 'The details were saved, but that picture could not be used. Try a JPG or PNG.' : 'That picture could not be used. Try a JPG or PNG.'];
            }
            // This appointment shows the new face, whatever it showed before.
            $o->forceFill(['photo' => $path])->save();
            $changed[] = 'photo';
        }

        if ($changed) {
            Log::info('federation.official.edited', ['by' => $viewer->id, 'official' => $o->id, 'event' => $ev->uuid, 'fields' => $changed, 'claimed' => ! $user->is_unclaimed]);
        }

        return ['ok' => true, 'message' => $changed ? 'Official updated.' : 'Nothing to change.', 'official' => $this->shape($o->fresh('user'))];
    }

    /**
     * Every club of this federation that is on the platform, for the club
     * picker — the event's own list only holds clubs already linked to it, so
     * a referee from a club with nobody entered (or not entered yet) could not
     * be named with it (2026-10-15: Energy Zone). Keyed by the tenant slug,
     * which EventClubChoice::resolve() accepts for any active club.
     *
     * @return list<array{slug: string, name: string, logo: ?string}>
     */
    public function clubs(array $access): array
    {
        $ids = collect($this->directory->subtree($access['root']))->pluck('id');

        return \App\Federations\Models\FederationClub::with('club')->whereIn('federation_id', $ids)
            ->where('status', \App\Federations\Models\FederationClub::STATUS_APPROVED)->get()
            ->map(fn ($fc) => $fc->club)->filter(fn ($t) => $t && $t->status === 'active' && $t->slug)
            ->unique('id')
            ->map(fn ($t) => ['slug' => (string) $t->slug, 'name' => (string) $t->club_name, 'logo' => $t->logo ? file_url($t->logo) : null])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
    }

    private function shape(EventOfficial $o): array
    {
        $u = $o->user;
        $photo = $o->photo ?: ProfilePhoto::path($u);
        $club = $o->club();

        return [
            'id' => $o->id,
            'name' => (string) ($u->full_name ?: $u->name),
            'gender' => $u->gender === 'Female' ? 'Female' : ($u->gender === 'Male' ? 'Male' : ''),
            'nat' => ($i = Countries::iso2((string) $u->nationality)) ? strtoupper($i) : '',
            'photo' => $photo ? file_url($photo) : null,
            'role' => (string) $o->role,
            'roleLabel' => (string) ($o->role_label ?: FederationEventFigures::jobOf($o->role, null)),
            'club' => $club['slug'] ?? '',
            // Shown even when that club is not one of the event's own options.
            'clubName' => $club['name'] ?? null,
            'clubLogo' => $club['logo'] ?? null,
            'compensation' => (string) $o->compensation,
            'fee' => $o->fee !== null ? (float) $o->fee : null,
            'claimed' => ! $u->is_unclaimed,
            'userId' => (int) $u->id,
            // Every job this person holds at the event (one appointment each):
            // the window shows them as ticks, adding and removing appointments.
            'jobs' => EventOfficial::where('event_id', $o->event_id)->where('user_id', $u->id)->orderBy('id')->get()
                ->map(fn (EventOfficial $j) => [
                    'id' => $j->id, 'role' => (string) $j->role,
                    'label' => (string) ($j->role_label ?: FederationEventFigures::jobOf($j->role, null)),
                    'compensation' => (string) $j->compensation, 'fee' => $j->fee !== null ? (float) $j->fee : null,
                ])->values()->all(),
        ];
    }
}
