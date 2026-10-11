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
            'divisions' => $divisions,
            'checkins' => $weighed,
            'readiness' => [
                $this->venue($details),
                ['done' => $athletes > 0, 'label' => 'Athletes entered', 'note' => $athletes.' from '.$clubs.' '.($clubs === 1 ? 'club' : 'clubs')],
                ['done' => $divisions > 0 && $single === 0 && $unplaced === 0, 'label' => 'Divisions', 'note' => $divNote],
                ['done' => $athletes > 0 && $weighed === $athletes, 'label' => 'Weigh-in', 'note' => $weighed.' of '.$athletes.' weighed in'],
                ['done' => $drawable->isNotEmpty() && $drawn === $drawable->count(), 'label' => 'Draw', 'note' => $drawn.' of '.$drawable->count().' divisions drawn'],
                ['done' => $officials > 0, 'label' => 'Officials appointed', 'note' => (string) $officials, 'subs' => $this->officialJobs($officialRows, $officialsUrl = route('me.events.officiating', $ev->uuid)), 'url' => $officialsUrl],
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
    private function officialJobs(\Illuminate\Support\Collection $rows, string $url): array
    {
        return $rows->map(fn ($r) => self::jobOf($r->role, $r->role_label))->countBy()->sortDesc()->map(fn ($n, $job) => [
            // Every job opens the event's own Officials screen — the full list
            // behind the number, where an official is added, edited or removed.
            'label' => $job, 'note' => (string) $n, 'url' => $url,
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
            'divisions' => 0,
            'checkins' => 0,
            'readiness' => [
                $this->venue($details),
                ['done' => $athletes > 0, 'label' => 'Athletes entered', 'note' => $athletes.' from '.$clubs.' '.($clubs === 1 ? 'club' : 'clubs')],
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

    private function clubCount(FederationEvent $e): int
    {
        return (int) $this->activeRows($e)->distinct()->count('federation_entry_id');
    }
}
