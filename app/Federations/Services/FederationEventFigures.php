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
                    ['label' => 'Participants', 'note' => (string) $athletes, 'tab' => 'regs', 'hint' => 'Open this event’s participants'],
                    ['label' => 'Clubs', 'note' => (string) $clubs, 'tab' => 'clubs', 'hint' => 'Open this event’s clubs'],
                ]],
                ['done' => $divisions > 0 && $single === 0 && $unplaced === 0, 'label' => 'Divisions', 'note' => $divNote, 'subs' => $this->divisionRows($ev, $perDivision, $unplaced)],
                ['done' => $athletes > 0 && $weighed === $athletes, 'label' => 'Weigh-in', 'note' => $weighed.' of '.$athletes.' weighed in'],
                ['done' => $drawable->isNotEmpty() && $drawn === $drawable->count(), 'label' => 'Draw', 'note' => $drawn.' of '.$drawable->count().' divisions drawn'],
                ['done' => $officials > 0, 'label' => 'Officials appointed', 'note' => (string) $officials, 'subs' => $this->officialJobs($officialRows, $ev), 'tab' => 'offs', 'hint' => 'Open this event’s officials'],
            ],
            // The Overview's cockpit (UX brief 2026-10-22): where the event is in
            // its life, the one next step, and what needs attention — derived.
            'cockpit' => rescue(fn () => $this->cockpit($e, $ev, $regs, $perDivision, $drawable, $drawn, $athletes, $weighed, $unplaced, $officials, $details), null, report: true),
        ];
    }

    /**
     * Lifecycle, next step and the "needs attention" list for one platform-run
     * event. Read-only; every item names the tab that fixes it.
     *
     * @return array{steps: list<array{label:string, state:string}>, current:int, next:?array{label:string, tab:string}, attention: list<array{sev:string, title:string, text:string, tab:string, action:string}>, glance: list<array{label:string, value:string}>}
     */
    private function cockpit(FederationEvent $e, ClubEvent $ev, $regs, $perDivision, $drawable, int $drawn, int $athletes, int $weighed, int $unplaced, int $officials, array $details): array
    {
        $bouts = DB::table('event_matches')->where('event_id', $ev->id)->whereNotNull('a_competitor_id')->whereNotNull('b_competitor_id');
        $boutCount = (clone $bouts)->count();
        $fought = (clone $bouts)->whereNotNull('winner')->count();
        $decided = $drawable->isEmpty() ? 0 : DB::table('event_categories')->whereIn('id', $drawable)->whereNotNull('podium')->where('podium', '!=', '[]')->count();
        $entries = $regs->whereNotNull('category_id')->count();
        $ended = $e->hasEnded();

        $done = [
            trim((string) ($details['venue'] ?? '')) !== '' && $e->starts_on,       // Setup
            $athletes > 0 && ($weighed > 0 || $drawn > 0 || $ended),                 // Registration
            $athletes > 0 && $weighed === $athletes,                                 // Weigh-in
            $drawable->isNotEmpty() && $drawn === $drawable->count(),                // Draw
            $fought > 0,                                                             // Live
            $drawable->isNotEmpty() && $decided >= $drawable->count(),               // Results
            $ended && $drawable->isNotEmpty() && $decided >= $drawable->count(),     // Closed
        ];
        $labels = ['Setup', 'Registration', 'Weigh-in', 'Draw', 'Live', 'Results', 'Closed'];
        $current = 7;
        foreach ($done as $i => $d) {
            if (! $d) {
                $current = $i;
                break;
            }
        }
        $steps = [];
        foreach ($labels as $i => $l) {
            $steps[] = ['label' => $l, 'state' => $i < $current ? 'done' : ($i === $current ? 'current' : 'todo')];
        }
        $next = [
            0 => ['label' => 'Set the venue and dates', 'tab' => 'edit'],
            1 => ['label' => 'Enter athletes', 'tab' => 'regs'],
            2 => ['label' => 'Weigh in athletes', 'tab' => 'regs'],
            3 => ['label' => 'Make the draw', 'tab' => 'divs'],
            4 => ['label' => 'Run the bouts', 'tab' => 'divs'],
            5 => ['label' => 'Finish the divisions', 'tab' => 'results'],
            6 => ['label' => 'Review the final results', 'tab' => 'results'],
        ][$current] ?? null;

        // ── Needs attention, most serious first ─────────────────────────
        $att = [];
        $conflicts = count(\App\Events\Support\OneDivisionRule::conflicts($ev));
        if ($conflicts) {
            $att[] = ['sev' => 'critical', 'title' => $conflicts.' '.($conflicts === 1 ? 'athlete is' : 'athletes are').' in two divisions',
                'text' => $ev->one_division_per_person ? 'One division per person is on. Choose where each one competes.' : 'They will fight in both brackets.', 'tab' => 'divs', 'action' => 'Resolve', 'open' => 'double-entries'];
        }
        $below = max(2, (int) config('events.division_merge.below', 3));
        $small = $perDivision->filter(fn ($n) => $n > 0 && $n < $below);
        if ($small->isNotEmpty()) {
            $names = DB::table('event_categories')->whereIn('id', $small->keys())->orderBy('sort_order')->limit(4)->pluck('name')->all();
            $att[] = ['sev' => 'high', 'title' => $small->count().' '.($small->count() === 1 ? 'division has' : 'divisions have').' fewer than '.$below.' athletes',
                'text' => implode(' · ', $names).($small->count() > 4 ? ' …' : ''), 'tab' => 'divs', 'action' => 'Review merges'];
        }
        $outside = DB::table('club_event_registrations as r')->join('event_categories as c', 'c.id', '=', 'r.category_id')
            ->where('r.event_id', $ev->id)->where('r.role', 'participant')->whereNotNull('r.weighed_in_at')->whereNotNull('r.weight')
            ->where(fn ($q) => $q->where(fn ($w) => $w->whereNotNull('c.max_weight')->whereColumn('r.weight', '>', 'c.max_weight'))
                ->orWhere(fn ($w) => $w->whereNotNull('c.min_weight')->whereColumn('r.weight', '<', 'c.min_weight')))
            ->distinct()->count('r.user_id');
        if ($outside) {
            $att[] = ['sev' => 'high', 'title' => $outside.' '.($outside === 1 ? 'athlete' : 'athletes').' weighed outside their division',
                'text' => 'Move them, or keep them there on purpose.', 'tab' => 'regs', 'action' => 'Check weigh-ins'];
        }
        if ($unplaced) {
            $att[] = ['sev' => 'high', 'title' => $unplaced.' '.($unplaced === 1 ? 'entry is' : 'entries are').' in no division',
                'text' => 'They will not appear in any draw.', 'tab' => 'divs', 'action' => 'Place them'];
        }
        if ($athletes > 0 && $weighed < $athletes && ! $ended) {
            $att[] = ['sev' => 'medium', 'title' => ($athletes - $weighed).' of '.$athletes.' athletes not weighed in yet',
                'text' => 'Weigh-in decides the division each one fights in.', 'tab' => 'regs', 'action' => 'Weigh in'];
        }
        if ($drawable->isNotEmpty() && $drawn < $drawable->count()) {
            $att[] = ['sev' => 'medium', 'title' => ($drawable->count() - $drawn).' '.(($drawable->count() - $drawn) === 1 ? 'division has' : 'divisions have').' no draw yet',
                'text' => $drawn.' of '.$drawable->count().' drawn.', 'tab' => 'divs', 'action' => 'Make the draw'];
        }
        if ($ended && $drawable->isNotEmpty() && $decided < $drawable->count()) {
            $att[] = ['sev' => 'medium', 'title' => ($drawable->count() - $decided).' '.(($drawable->count() - $decided) === 1 ? 'division has' : 'divisions have').' no result',
                'text' => 'The event is over; record the remaining finals.', 'tab' => 'results', 'action' => 'Open results'];
        }
        if ($officials === 0 && $athletes > 0) {
            $att[] = ['sev' => 'medium', 'title' => 'No officials appointed', 'text' => 'Referees and judges are named on the Officials tab.', 'tab' => 'offs', 'action' => 'Appoint'];
        }

        // ── The rules the draw follows (Draw tab, brief §2.3) — read from where
        // each one already lives; nothing here is a second copy of a setting.
        $mergeOn = \App\Events\Support\DivisionMerge::enabled();
        $rules = [
            ['label' => 'Minimum athletes per division', 'value' => (string) $below, 'note' => 'Fewer than this and the division needs a decision: merge, combine or exhibition.'],
            ['label' => 'One division per athlete', 'value' => $ev->one_division_per_person ? 'On' : 'Off', 'note' => $ev->one_division_per_person ? 'Nobody can be placed in a second bracket.' : 'An athlete may stand in several divisions.'],
            ['label' => 'Bronze', 'value' => \App\Events\Support\BronzeRule::playsOff($ev) ? 'One' : 'Two', 'note' => \App\Events\Support\BronzeRule::playsOff($ev) ? 'A third-place bout decides it.' : 'Both semi-final losers take bronze.'],
            ['label' => 'Merging small divisions', 'value' => $mergeOn ? 'Allowed' : 'Off', 'note' => $mergeOn
                ? 'Same gender only; consent needed over '.rtrim(rtrim(number_format((float) config('events.division_merge.consent_gap', 12), 1), '0'), '.').'% weight gap, never over '.rtrim(rtrim(number_format((float) config('events.division_merge.max_gap', 15), 1), '0'), '.').'%; ages at most '.(int) config('events.division_merge.max_age_months', 24).' months apart.'
                : 'Small divisions are not merged by the platform.'],
            ['label' => 'Draw guard', 'value' => \App\Events\Support\DrawGuard::enabled() ? 'On' : 'Off', 'note' => \App\Events\Support\DrawGuard::enabled() ? 'The draw is refused while anyone stands in two divisions.' : 'The draw is never blocked; the checks above are advice.'],
        ];
        $checks = [
            ['key' => 'double', 'label' => 'In two divisions', 'n' => $conflicts, 'sev' => 'critical', 'open' => 'double-entries', 'tab' => 'divs'],
            ['key' => 'small', 'label' => 'Below the minimum', 'n' => $small->count(), 'sev' => 'high', 'tab' => 'divs'],
            ['key' => 'outside', 'label' => 'Weighed outside', 'n' => $outside, 'sev' => 'high', 'tab' => 'regs'],
            ['key' => 'unplaced', 'label' => 'In no division', 'n' => $unplaced, 'sev' => 'high', 'tab' => 'divs'],
        ];

        return [
            'rules' => $rules,
            'checks' => $checks,
            'steps' => $steps,
            'current' => $current,
            'next' => $next,
            'attention' => $att,
            'glance' => [
                ['label' => 'Athletes', 'value' => (string) $athletes],
                ['label' => 'Division entries', 'value' => (string) $entries],
                ['label' => 'Divisions', 'value' => (string) $perDivision->count()],
                ['label' => 'Bouts', 'value' => $fought.' / '.$boutCount],
                ['label' => 'Clubs', 'value' => (string) $this->clubCount($e)],
                ['label' => 'Weighed in', 'value' => $weighed.' / '.$athletes],
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
    private function officialJobs(\Illuminate\Support\Collection $rows, ?\App\Events\Models\ClubEvent $ev = null): array
    {
        // The same names the Officials tab groups by, so a job here opens a chip that exists there.
        $names = $ev ? rescue(fn () => app(\App\Events\Support\OfficialRoles::class)->labels($ev), [], false) : [];

        return $rows->map(fn ($r) => self::jobOf($r->role, $r->role_label, $names))->countBy()->sortDesc()->map(fn ($n, $job) => [
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
    public static function jobOf(?string $role, ?string $roleLabel, array $roleNames = []): string
    {
        $names = ['jury' => 'Jury', 'weigh_in' => 'Weigh-in', 'payments' => 'Payments', 'organiser' => 'Organisers'];
        $label = trim((string) preg_replace('/\s*\d+$/', '', (string) $roleLabel));
        if ($label === '') {
            // A job from the event's list (OfficialRoles::labels, passed in)
            // reads as its own name — a referee is a referee, not "Other".
            return $names[(string) $role] ?? $roleNames[(string) $role] ?? 'Other';
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
            $rows[] = ['label' => 'Not placed', 'note' => (string) $unplaced, 'tab' => 'regs', 'hint' => 'Open this event’s participants'];
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
            ->with(['club.club:id,club_name,logo,owner_user_id,owner_name', 'club.club.owner:id,full_name,name', 'application:id,uuid,name,coach', 'athletes' => fn ($q) => $q->where('status', 'Active')->whereHas('athlete.user')->with('athlete.user:id,gender')])
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
                $manager = $entries->map(fn ($en) => trim((string) data_get($en->officials, 'mgr.name')))->first(fn ($n) => ! \App\Federations\Support\NobodyName::is($n));
                $owner = trim((string) ($tenant?->owner?->full_name ?: $tenant?->owner?->name ?: $tenant?->owner_name ?: $entries->first()->application?->coach));

                $first = $entries->first();

                return [
                    // The club as its entries name it (fc-… / fa-…), for "Remove from event".
                    'key' => $first->federation_club_id ? ($first->club ? 'fc-'.$first->club->uuid : null) : ($first->application ? 'fa-'.$first->application->uuid : null),
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
