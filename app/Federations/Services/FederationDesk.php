<?php

namespace App\Federations\Services;

use App\Federations\Models\FederationAthlete;
use App\Federations\Models\FederationAthleteClub;
use App\Federations\Models\FederationClub;
use App\Federations\Models\FederationClubApplication;
use App\Federations\Models\FederationEntry;
use App\Federations\Models\FederationEntryAthlete;
use App\Federations\Models\FederationEvent;
use App\Federations\Models\FederationTransfer;
use App\Members\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The federation staff's side of entries: what the dashboard's store holds
 * (clubs, athletes, transfers, entries per event, personal links) and the
 * decisions staff make on them.
 *
 * Scope is the viewer's own subtree, never the tree above it; a sport-limited
 * viewer's events and entries are their sports only.
 */
class FederationDesk
{
    public function __construct(
        private FederationDirectory $directory,
        private FederationRegistry $registry,
        private FederationEvents $events,
    ) {}

    private function ids(array $access): Collection
    {
        return collect($this->directory->subtree($access['root']))->pluck('id');
    }

    /** @return Collection<int, FederationEvent> */
    private function eventsOf(array $access): Collection
    {
        $q = FederationEvent::whereIn('federation_id', $this->ids($access));
        if ($access['sports'] !== []) {
            $q->whereIn('sport', $access['sports']);
        }

        return $q->get();
    }

    /** `window.__TAKEONE_FED.fedstore` for the dashboard. */
    public function store(array $access): array
    {
        $ids = $this->ids($access);
        $clubs = $this->registry->clubsOf($ids);
        $events = $this->eventsOf($access);
        $entries = FederationEntry::with('event', 'club.club', 'application')->whereIn('federation_event_id', $events->pluck('id'))->get();

        $athleteIds = FederationAthleteClub::whereIn('federation_id', $ids)->whereNull('to_ym')->pluck('federation_athlete_id')
            ->merge(FederationEntryAthlete::whereIn('federation_entry_id', $entries->pluck('id'))->pluck('federation_athlete_id'))
            ->unique();
        $transfers = FederationTransfer::with('athlete.user')->whereIn('federation_id', $ids)->latest('id')->limit(500)->get();
        $athleteIds = $athleteIds->merge($transfers->pluck('federation_athlete_id'))->unique();

        $byEvent = [];
        foreach ($entries as $en) {
            $row = $this->registry->entry($en, $ids);
            if ($row['clubId']) {
                $byEvent[$en->event->uuid][$row['clubId']] = $row;
            }
        }

        // Clubs that appear only as the other side of a transfer: name only.
        $known = $clubs->map(fn ($c) => $this->registry->clubKey($c))->all();
        $extra = collect();
        foreach ($transfers as $t) {
            foreach ([[$t->from_club_id, FederationClub::class], [$t->to_club_id, FederationClub::class], [$t->from_application_id, FederationClubApplication::class], [$t->to_application_id, FederationClubApplication::class]] as [$id, $class]) {
                if ($id && ($c = $class::find($id)) && ! in_array($this->registry->clubKey($c), $known, true)) {
                    $extra->push($this->registry->club($c, false));
                    $known[] = $this->registry->clubKey($c);
                }
            }
        }

        return [
            'directory' => $this->directory($ids, $athleteIds),
            'officials' => $this->officials($entries),
            'clubs' => $clubs->map(fn ($c) => $this->registry->club($c))->concat($extra)->values()->all(),
            'athletes' => FederationAthlete::with('user')->whereIn('id', $athleteIds)->get()->map(fn ($a) => $this->registry->athlete($a, $ids))->values()->all(),
            'transfers' => $transfers->map(fn ($t) => $this->registry->transfer($t))->all(),
            'entriesByEvent' => (object) $byEvent,
            'inviteEvents' => (object) $events->mapWithKeys(fn ($e) => [$e->uuid => $this->registry->eventForInvite($e)])->all(),
            'clubLinks' => (object) $clubs->mapWithKeys(fn ($c) => [$this->registry->clubKey($c) => $this->registry->token($c)])->all(),
        ];
    }

    /**
     * The Athletes page's list, in the prototype's own row shape (its built-in
     * `athData`): who they are, where they compete, and what they have
     * entered. Real records only.
     */
    private function directory(Collection $ids, Collection $athleteIds): array
    {
        return FederationAthlete::with('user')->whereIn('id', $athleteIds)->orderBy('takeone_id')->get()->map(function (FederationAthlete $a) use ($ids) {
            $u = $a->user;
            $cur = $this->registry->currentClub($a, $ids);
            $club = $cur ? ($cur->club?->club?->club_name ?? $cur->application?->name) : null;
            $mobile = is_array($u?->mobile) ? trim(($u->mobile['code'] ?? '').' '.($u->mobile['number'] ?? '')) : '';
            $entries = FederationEntryAthlete::with('entry.event')->where('federation_athlete_id', $a->id)->where('status', 'Active')->get();
            $sports = $entries->map(fn ($ea) => [(string) $ea->entry?->event?->sport, $ea->weight ? rtrim(rtrim(number_format($ea->weight, 1), '0'), '.').' kg' : '—'])
                ->unique(fn ($x) => $x[0])->values()->all();

            return [
                'name' => $u?->full_name ?: $u?->name,
                'club' => $club ?: '—',
                'gender' => $u?->gender === 'Female' ? 'F' : 'M',
                'dob' => $u?->birthdate ? $u->birthdate->format('j M Y') : '—',
                'age' => $u?->birthdate ? $u->birthdate->age : '—',
                'nat' => $a->nationality ?: '—',
                'natId' => (string) ($a->national_id ?: '—'),
                'mobile' => $mobile !== '' ? $mobile : '—',
                'email' => $u?->email ?: '—',
                'tk' => $a->takeone_id,
                'events' => $entries->count(),
                'sports' => $sports,
            ];
        })->values()->all();
    }

    /**
     * The Officials page (the prototype's `offData` rows): the team managers
     * and referees clubs named on their entries. Bouts are counted once the
     * draw is run on the platform; until then there are none.
     */
    private function officials(Collection $entries): array
    {
        $rows = [];
        foreach ($entries as $en) {
            foreach (['ref1', 'ref2', 'mgr'] as $slot) {
                $o = ($en->officials ?? [])[$slot] ?? null;
                if (! $o || empty($o['name'])) {
                    continue;
                }
                $key = mb_strtolower($o['name']).'|'.$en->event?->uuid;
                $rows[$key] ??= [
                    $o['name'],
                    (string) $en->event?->sport,
                    $slot === 'mgr' ? 'Team manager' : (($o['license'] ?? '') !== '' ? 'Referee · '.$o['license'] : 'Referee'),
                    'Assigned',
                    (string) $en->event?->name,
                    0,
                ];
            }
        }

        return array_values($rows);
    }

    /** `takeone_club_regs`: the registration feed, every event in scope. */
    public function regs(array $access): array
    {
        $ids = $this->ids($access);

        return FederationEntry::with('event', 'club.club', 'application')->whereIn('federation_event_id', $this->eventsOf($access)->pluck('id'))
            ->get()->map(fn ($en) => $this->registry->regFeed($en, $ids))->values()->all();
    }

    private function entryInScope(array $access, ?string $uuid): ?FederationEntry
    {
        return $uuid ? FederationEntry::whereIn('federation_event_id', $this->eventsOf($access)->pluck('id'))->where('uuid', $uuid)->first() : null;
    }

    /** The federation confirms a club's bank transfer arrived. */
    public function confirmEntryPayment(User $user, array $access, string $entryUuid): bool
    {
        $entry = $this->entryInScope($access, $entryUuid);
        if (! $entry) {
            return false;
        }
        $pays = $entry->payments ?: [];
        $sum = 0;
        foreach ($pays as $i => $p) {
            if (($p['status'] ?? '') === 'Awaiting transfer') {
                $pays[$i]['status'] = 'Paid';
                $pays[$i]['confirmedAt'] = now()->getTimestampMs();
                $pays[$i]['confirmedBy'] = $user->id;
                $sum += (float) $p['amount'];
            }
        }
        if (! $sum) {
            return true;
        }
        $entry->payments = $pays;
        $entry->log = array_merge($entry->log ?: [], [['ts' => now()->getTimestampMs(), 'key' => 'logPaid', 'a' => 'BHD '.number_format($sum, 3), 'm' => 'Bank transfer confirmed']]);
        $entry->save();

        return true;
    }

    private function transferInScope(array $access, string $uuid): ?FederationTransfer
    {
        return FederationTransfer::whereIn('federation_id', $this->ids($access))->where('uuid', $uuid)->first();
    }

    /** Record the old club's answer (the federation takes it by phone or email). */
    public function transferReply(array $access, string $uuid, bool $released): bool
    {
        $t = $this->transferInScope($access, $uuid);
        if (! $t || $t->status !== 'Pending') {
            return false;
        }
        $t->update(['old_club' => $released ? 'Released' : 'Objected']);

        return true;
    }

    /**
     * Approve or decline. Approving moves the athlete's FEDERATION record to
     * the new club and closes the old history row — nothing on the club side.
     */
    public function decideTransfer(User $user, array $access, string $uuid, bool $approve): bool
    {
        $t = $this->transferInScope($access, $uuid);
        if (! $t || $t->status !== 'Pending') {
            return false;
        }

        DB::transaction(function () use ($t, $user, $approve) {
            $t->update(['status' => $approve ? 'Approved' : 'Declined', 'decided_at' => now(), 'decided_by' => $user->id]);
            if (! $approve) {
                return;
            }
            $ym = now()->format('Y-m');
            FederationAthleteClub::where('federation_athlete_id', $t->federation_athlete_id)
                ->where('federation_id', $t->federation_id)->whereNull('to_ym')->update(['to_ym' => $ym]);
            FederationAthleteClub::create([
                'federation_athlete_id' => $t->federation_athlete_id,
                'federation_id' => $t->federation_id,
                'federation_club_id' => $t->to_club_id,
                'club_application_id' => $t->to_application_id,
                'from_ym' => $ym,
            ]);
        });

        return true;
    }

    /** The federation approves one athlete's registration. */
    public function approveReg(array $access, ?string $entryUuid, string $takeoneId): bool
    {
        $entry = $this->entryInScope($access, $entryUuid);
        if (! $entry) {
            return false;
        }

        return (bool) $entry->athletes()->whereHas('athlete', fn ($q) => $q->where('takeone_id', $takeoneId))
            ->where('status', 'Active')->update(['approval' => 'Approved']);
    }

    /**
     * The Registrations tab's delete / clear-all. On the platform it is a
     * withdrawal, never a deletion — and only a manager may do it.
     *
     * @param  array<int, array{entry?: string, athlete?: string}>  $rows
     */
    public function withdrawRegs(array $access, array $rows): bool
    {
        if (! $access['manager']) {
            return false;
        }
        foreach (array_slice($rows, 0, 1000) as $r) {
            $entry = $this->entryInScope($access, is_string($r['entry'] ?? null) ? $r['entry'] : null);
            if (! $entry) {
                continue;
            }
            $entry->athletes()->whereHas('athlete', fn ($q) => $q->where('takeone_id', (string) ($r['athlete'] ?? '')))
                ->where('status', 'Active')->update(['status' => 'Withdrawn', 'withdrawn_at' => now()]);
        }

        return true;
    }
}
