<?php

namespace App\Federations\Services;

use App\Federations\Models\FederationAthlete;
use App\Federations\Models\FederationAthleteClub;
use App\Federations\Models\FederationClub;
use App\Federations\Models\FederationEntry;
use App\Federations\Models\FederationEntryAthlete;
use App\Federations\Models\FederationEvent;
use App\Federations\Models\FederationTransfer;
use App\Members\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The Registrations tab's "Enroll athlete": federation staff enter one person
 * by hand — somebody already in the federation's directory (an athlete, or a
 * member of one of its clubs) or somebody new.
 *
 * This class decides WHO and WHERE, inside the viewer's scope; the entry
 * itself is written by FederationInvitation::enterByStaff(), the same code a
 * club's own submission runs. Behind `federations.manual_enrolment`.
 */
class FederationEnrolment
{
    public function __construct(
        private FederationDirectory $directory,
        private FederationRegistry $registry,
        private FederationInvitation $invitation,
        private FederationAthleteMerge $identity,
    ) {}

    public function mayEnrol(array $access): bool
    {
        return (bool) config('federations.manual_enrolment');
    }

    /**
     * @param  array{event:string, person?:?string, club?:?string, new?:?array, weight?:mixed, types?:array}  $p
     * @return array{ok:bool, message:string, takeoneId?:string}
     */
    public function enrol(User $by, array $access, array $p, bool $lenient = false): array
    {
        $ids = collect($this->directory->subtree($access['root']))->pluck('id');
        $q = FederationEvent::whereIn('federation_id', $ids)->where('uuid', (string) $p['event']);
        if ($access['sports'] !== []) {
            $q->whereIn('sport', $access['sports']);
        }
        $event = $q->first();
        if (! $event) {
            return ['ok' => false, 'message' => 'That event could not be found.'];
        }

        $weight = is_numeric($p['weight'] ?? null) ? max(10, min(250, round((float) $p['weight'], 1))) : null;
        $types = array_values(array_filter((array) ($p['types'] ?? []), 'is_string'));
        $person = (string) ($p['person'] ?? '');
        $athlete = null;
        $club = null;

        if ($person === '' && empty($p['club']) && trim((string) ($p['newClub']['name'] ?? '')) !== '') {
            // Somebody new, at a club that is not on the list yet: the club is added
            // as PENDING (the Clubs page reviews it) — or, when the name already
            // matches one, that one is used. In ONE transaction with the entry, so
            // an enrolment that fails leaves no orphan club behind.
            try {
                return DB::transaction(function () use ($by, $access, $ids, $event, $p, $weight, $types, $lenient) {
                    $club = $this->clubByName($access, $ids, $event, (string) $p['newClub']['name'], (string) ($p['newClub']['coach'] ?? ''), $by);
                    $r = $this->invitation->enterByStaff($event, $club, null, (array) ($p['new'] ?? []), $weight, $types, $by, $lenient);
                    if (! $r['ok']) {
                        throw new \RuntimeException($r['message']);
                    }

                    return $r;
                });
            } catch (\RuntimeException $e) {
                return ['ok' => false, 'message' => $e->getMessage()];
            }
        }

        if ($person === '') {
            // Somebody new: the staff member chooses their club.
            $club = $this->registry->clubFromKey((string) ($p['club'] ?? ''), $ids);
            if (! $club) {
                return ['ok' => false, 'message' => 'Choose the athlete\'s club.'];
            }
        } else {
            if (str_starts_with($person, 'm-')) {
                // A club member with no TakeOne ID yet gets one first.
                $user = User::where('uuid', substr($person, 2))->first();
                $athlete = $user ? FederationAthlete::where('user_id', $user->id)->first() : null;
                if (! $athlete) {
                    $r = $this->identity->issue($by, $access, $person);
                    if (isset($r['error'])) {
                        return ['ok' => false, 'message' => $r['error']];
                    }
                    $athlete = FederationAthlete::where('takeone_id', $r['takeoneId'])->first();
                }
            } else {
                $athlete = FederationAthlete::where('takeone_id', $person)->first();
            }
            if (! $athlete || ! $this->visible($ids)->contains($athlete->id)) {
                return ['ok' => false, 'message' => 'That athlete could not be found.'];
            }
            // They enter under the club they stand at in this federation.
            $cur = FederationAthleteClub::where('federation_athlete_id', $athlete->id)->whereIn('federation_id', $ids)->whereNull('to_ym')->latest('id')->first();
            $club = $cur?->federation_club_id ? FederationClub::where('id', $cur->federation_club_id)->where('status', FederationClub::STATUS_APPROVED)->first()
                : ($cur?->application ?? null);
            if (! $club && $athlete->user_id) {
                // No club row yet: the federation club they are a member of.
                $tenants = DB::table('memberships')->where('user_id', $athlete->user_id)->where('status', 'active')->pluck('tenant_id');
                $club = FederationClub::whereIn('federation_id', $ids)->where('status', FederationClub::STATUS_APPROVED)->whereIn('tenant_id', $tenants)->orderBy('id')->first();
            }
            if (! $club) {
                return ['ok' => false, 'message' => 'This athlete has no club in the federation yet, so there is no entry to put them on.'];
            }
        }

        return $this->invitation->enterByStaff($event, $club, $athlete, $person === '' ? (array) ($p['new'] ?? []) : null, $weight, $types, $by, $lenient);
    }

    /** A club of this federation by name (Arabic spellings folded), else a new PENDING one. */
    private function clubByName(array $access, Collection $ids, FederationEvent $event, string $name, string $coach, User $by): \App\Federations\Models\FederationClub|\App\Federations\Models\FederationClubApplication
    {
        $name = mb_substr(trim(strip_tags($name)), 0, 150);
        $key = \App\Federations\Support\ClubName::norm($name);
        foreach ($this->registry->clubsOf($ids) as $c) {
            if (\App\Federations\Support\ClubName::norm((string) $this->registry->club($c, false)['name']) === $key) {
                return $c;
            }
        }
        $app = \App\Federations\Models\FederationClubApplication::create([
            'federation_id' => $access['root']->id,
            'federation_event_id' => $event->id,
            'name' => $name,
            'coach' => ($coach = mb_substr(trim(strip_tags($coach)), 0, 150)) !== '' ? $coach : null,
            'logo_shape' => 'circle',
            'invite_token' => \Illuminate\Support\Str::random(48),
            'status' => \App\Federations\Models\FederationClubApplication::STATUS_PENDING,
        ]);
        activity()->causedBy($by)->performedOn($app)->withProperties(['event' => $event->uuid])->log('federation.club_added_by_staff');

        return $app;
    }

    /** The athlete ids this viewer's federation tree can see — for the bulk import's matching. */
    public function visibleIds(array $access): Collection
    {
        return $this->visible(collect($this->directory->subtree($access['root']))->pluck('id'));
    }

    /** Athletes this federation tree sees: its rosters, its events' entries, its transfers — and its members' records. */
    private function visible(Collection $ids): Collection
    {
        $events = FederationEvent::whereIn('federation_id', $ids)->pluck('id');
        $entries = FederationEntry::whereIn('federation_event_id', $events)->pluck('id');

        return FederationAthleteClub::whereIn('federation_id', $ids)->pluck('federation_athlete_id')
            ->merge(FederationEntryAthlete::whereIn('federation_entry_id', $entries)->pluck('federation_athlete_id'))
            ->merge(FederationTransfer::whereIn('federation_id', $ids)->pluck('federation_athlete_id'))
            ->unique()->values();
    }
}
