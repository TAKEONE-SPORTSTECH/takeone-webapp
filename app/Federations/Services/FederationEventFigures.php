<?php

namespace App\Federations\Services;

use App\Events\Models\ClubEvent;
use App\Federations\Models\FederationEntryAthlete;
use App\Federations\Models\FederationEvent;
use Illuminate\Support\Facades\DB;

/**
 * The numbers a federation's dashboard prints about one event — athletes,
 * divisions, check-ins and the readiness checklist — DERIVED, never typed.
 *
 * When the federation's event runs as a platform event (`club_event_id`),
 * the platform event IS the competition: its entrants, the divisions they
 * were placed in, who was weighed, which draws exist. Every figure is read
 * from there, so removing or adding an athlete anywhere moves every number
 * (FederationPlatformEntries::entrantChanged pulses the open dashboards).
 * An event not on the platform yet can only count what its clubs submitted.
 */
class FederationEventFigures
{
    /** @return array{reg: int, divisions: int, checkins: int, readiness: array<int, array{done: bool, label: string, note: string}>} */
    public function of(FederationEvent $e, array $details = []): array
    {
        $ev = $e->club_event_id ? ClubEvent::find($e->club_event_id) : null;

        return $ev ? $this->platform($e, $ev, $details) : $this->submitted($e, $details);
    }

    private function platform(FederationEvent $e, ClubEvent $ev, array $details): array
    {
        // Participants whose account still exists — a deleted member is not an entrant.
        $regs = DB::table('club_event_registrations as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')->whereNull('u.deleted_at')
            ->where('r.event_id', $ev->id)->where('r.role', 'participant')
            ->get(['r.user_id', 'r.category_id', 'r.weighed_in_at']);

        $athletes = $regs->pluck('user_id')->unique()->count();
        $weighed = $regs->whereNotNull('weighed_in_at')->pluck('user_id')->unique()->count();

        $perDivision = $regs->whereNotNull('category_id')->countBy('category_id');
        $unplaced = $regs->whereNull('category_id')->count();
        $divisions = $perDivision->count();
        $single = $perDivision->filter(fn ($n) => $n < 2)->count();

        $drawable = $perDivision->filter(fn ($n) => $n >= 2)->keys();
        $drawn = $drawable->isEmpty() ? 0 : DB::table('event_matches')->where('event_id', $ev->id)
            ->whereIn('category_id', $drawable)->distinct()->count('category_id');

        $officialRows = DB::table('event_officials as o')->join('users as u', 'u.id', '=', 'o.user_id')
            ->whereNull('u.deleted_at')->where('o.event_id', $ev->id)->get(['o.user_id', 'o.role', 'o.role_label']);
        $officials = $officialRows->pluck('user_id')->unique()->count();

        $clubs = $this->clubCount($e);

        $divNote = $divisions.' '.($divisions === 1 ? 'division' : 'divisions');
        if ($single) {
            $divNote .= ' · '.$single.' with one athlete';
        }
        if ($unplaced) {
            $divNote .= ' · '.$unplaced.' not placed';
        }

        return [
            'reg' => $athletes,
            'entryClubs' => $this->entryClubs($e),
            'divisions' => $divisions,
            'checkins' => $weighed,
            'readiness' => [
                $this->venue($details),
                ['done' => $athletes > 0, 'label' => 'Athletes entered', 'note' => $athletes.' from '.$clubs.' '.($clubs === 1 ? 'club' : 'clubs'), 'subs' => [
                    ['label' => 'Athletes', 'note' => (string) $athletes, 'tab' => 'regs', 'hint' => 'Open this event’s athletes'],
                    ['label' => 'Clubs', 'note' => (string) $clubs, 'tab' => 'clubs', 'hint' => 'Open this event’s clubs'],
                ]],
                ['done' => $divisions > 0 && $single === 0 && $unplaced === 0, 'label' => 'Divisions', 'note' => $divNote, 'subs' => $this->divisionRows($ev, $perDivision, $unplaced)],
                ['done' => $athletes > 0 && $weighed === $athletes, 'label' => 'Weigh-in', 'note' => $weighed.' of '.$athletes.' weighed in'],
                ['done' => $drawable->isNotEmpty() && $drawn === $drawable->count(), 'label' => 'Draw', 'note' => $drawn.' of '.$drawable->count().' divisions drawn'],
                ['done' => $officials > 0, 'label' => 'Officials appointed', 'note' => (string) $officials, 'subs' => $this->officialJobs($officialRows), 'tab' => 'offs', 'hint' => 'Open this event’s officials'],
            ],
        ];
    }

    /**
     * The officials by job, most first — the indented rows under "Officials
     * appointed". A typed job keeps its wording with any trailing number
     * dropped, so the clubs' "Referee 1" and "Referee 2" are one job; a
     * platform role reads as its name.
     *
     * @return array<int, array{label: string, note: string}>
     */
    private function officialJobs(\Illuminate\Support\Collection $rows): array
    {
        return $rows->map(fn ($r) => self::jobOf($r->role, $r->role_label))->countBy()->sortDesc()->map(fn ($n, $job) => [
            // Every job opens the dashboard's own Officials tab, filtered to it.
            'label' => $job, 'note' => (string) $n, 'tab' => 'offs', 'job' => $job, 'hint' => 'Open this event’s '.mb_strtolower($job),
        ])->values()->all();
    }

    /**
     * An official's JOB, as the dashboard groups them: a typed job keeps its
     * wording with any trailing number dropped, so the clubs' "Referee 1" and
     * "Referee 2" are one job ("Referees"); a platform role reads as its name.
     * One definition, used by the checklist and the event's Officials tab.
     */
    public static function jobOf(?string $role, ?string $roleLabel): string
    {
        $names = ['jury' => 'Jury', 'weigh_in' => 'Weigh-in', 'payments' => 'Payments', 'organiser' => 'Organisers'];
        $label = trim((string) preg_replace('/\s*\d+$/', '', (string) $roleLabel));
        if ($label === '') {
            return $names[(string) $role] ?? 'Other';
        }
        $label = mb_strtolower($label);

        return ucfirst(preg_match('/(s|jury|weigh-in|payments)$/i', $label) ? $label : $label.'s');
    }

    /** Not on the platform: only what the clubs submitted is known. */
    private function submitted(FederationEvent $e, array $details): array
    {
        $athletes = $this->activeRows($e)->count();
        $clubs = $this->clubCount($e);

        return [
            'reg' => $athletes,
            'entryClubs' => $this->entryClubs($e),
            'divisions' => 0,
            'checkins' => 0,
            'readiness' => [
                $this->venue($details),
                ['done' => $athletes > 0, 'label' => 'Athletes entered', 'note' => $athletes.' from '.$clubs.' '.($clubs === 1 ? 'club' : 'clubs'), 'subs' => [['label' => 'Athletes', 'note' => (string) $athletes], ['label' => 'Clubs', 'note' => (string) $clubs]]],
            ],
        ];
    }

    private function venue(array $details): array
    {
        $venue = trim((string) ($details['venue'] ?? ''));
        $set = $venue !== '' && $venue !== 'TBC';

        return ['done' => $set, 'label' => 'Venue', 'note' => $set ? $venue : 'Not set'];
    }

    private function activeRows(FederationEvent $e)
    {
        return FederationEntryAthlete::whereHas('entry', fn ($q) => $q->where('federation_event_id', $e->id))
            ->where('status', 'Active')->whereHas('athlete.user');
    }

    /**
     * The rows under "Divisions": one per gender, its athletes and its
     * divisions on one line, coloured as everywhere else (Male blue, Female pink); the
     * athletes not yet placed in a division close the list.
     *
     * @return array<int, array<string, mixed>>
     */
    private function divisionRows(ClubEvent $ev, \Illuminate\Support\Collection $perDivision, int $unplaced): array
    {
        $genders = DB::table('event_categories')->where('event_id', $ev->id)
            ->whereIn('id', $perDivision->keys())->pluck('gender', 'id');

        $groups = ['male' => ['Male', '#2563eb'], 'female' => ['Female', '#db2777'], '' => ['Mixed', '#6b7689']];

        $rows = [];
        foreach ($groups as $key => [$label, $color]) {
            $counts = $perDivision->filter(fn ($n, $id) => $key === ''
                ? ! in_array((string) ($genders[$id] ?? ''), ['male', 'female'], true)
                : (string) ($genders[$id] ?? '') === $key);
            if ($counts->isEmpty()) {
                continue;
            }
            $rows[] = ['label' => $label, 'note' => 'Athletes '.$counts->sum().' · Divisions '.$counts->count(), 'color' => $color,
                'tab' => 'divs', 'hint' => 'Open this event’s divisions'];
        }

        if ($unplaced) {
            $rows[] = ['label' => 'Not placed', 'note' => (string) $unplaced, 'tab' => 'regs', 'hint' => 'Open this event’s athletes'];
        }

        return $rows;
    }

    /**
     * The clubs entered in the event, for its Clubs tab — every club with
     * an athlete entered (the same ones the checklist counts), with its active
     * athletes split by gender, most athletes first. Name, logo and who
     * answers for the club — a name, never a phone or an email.
     *
     * @return array<int, array{name: string, contact: ?string, contactRole: ?string, logo: ?string, athletes: int, male: int, female: int}>
     */
    private function entryClubs(FederationEvent $e): array
    {
        return \App\Federations\Models\FederationEntry::where('federation_event_id', $e->id)
            ->with(['club.club:id,club_name,logo,owner_user_id,owner_name', 'club.club.owner:id,full_name,name', 'application:id,name,coach', 'athletes' => fn ($q) => $q->where('status', 'Active')->whereHas('athlete.user')->with('athlete.user:id,gender')])
            ->get()
            // A club not yet approved entered through its application.
            ->groupBy(fn ($en) => $en->federation_club_id ? 'c'.$en->federation_club_id : 'a'.$en->club_application_id)
            ->map(function ($entries) {
                $tenant = $entries->first()->club?->club;
                $people = $entries->flatMap->athletes;
                $female = $people->filter(fn ($a) => $a->athlete?->user?->gender === 'Female')->count();

                // Who answers for the club at this event: the team manager its
                // entry names, else the club's owner (or, for a club not yet
                // approved, the coach on its application). A name only.
                $manager = $entries->map(fn ($en) => trim((string) data_get($en->officials, 'mgr.name')))->first(fn ($n) => $n !== '');
                $owner = trim((string) ($tenant?->owner?->full_name ?: $tenant?->owner?->name ?: $tenant?->owner_name ?: $entries->first()->application?->coach));

                return [
                    'name' => (string) ($tenant?->club_name ?: $entries->first()->application?->name ?: 'Unknown club'),
                    'contact' => $manager ?: ($owner !== '' ? $owner : null),
                    'contactRole' => $manager ? 'Team manager' : ($owner !== '' ? ($tenant ? 'Owner' : 'Coach') : null),
                    'logo' => $tenant?->logo ? file_url($tenant->logo) : null,
                    'athletes' => $people->count(),
                    'male' => $people->count() - $female,
                    'female' => $female,
                ];
            })
            // Clubs with an athlete in, as the checklist counts them.
            ->filter(fn ($c) => $c['athletes'] > 0)
            ->sortBy(fn ($c) => [-$c['athletes'], mb_strtolower($c['name'])])
            ->values()->all();
    }

    private function clubCount(FederationEvent $e): int
    {
        return (int) $this->activeRows($e)->distinct()->count('federation_entry_id');
    }
}
