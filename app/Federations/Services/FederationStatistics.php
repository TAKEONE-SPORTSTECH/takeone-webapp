<?php

namespace App\Federations\Services;

use App\Federations\Models\FederationAthlete;
use App\Federations\Models\FederationAthleteClub;
use App\Federations\Models\FederationClub;
use App\Federations\Models\FederationClubApplication;
use App\Federations\Models\FederationDraw;
use App\Federations\Models\FederationEntry;
use App\Federations\Models\FederationEntryAthlete;
use App\Federations\Models\FederationEvent;
use App\Federations\Models\FederationTransfer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The Statistics page's figures, from the federation's own records.
 *
 * The designed page (Karate Statistics.dc.html) arrived computing every
 * number from a sample table times a guessed share ("Kata = 42%"). This
 * hands it the RECORDS instead — clubs, athletes, entries, bouts, medals,
 * officials — as compact fact rows, and the page filters and counts them
 * itself (FederationApp's `stats-*` rewrites). Nothing here is estimated:
 * what was never recorded (a kata score, a points margin, a ranking list)
 * is simply absent, and the page says so.
 *
 * Scope is the viewer's subtree and, for sport-limited staff, their sports —
 * the same scope as every other federation read. Rows are addressed by their
 * position in the payload, never by a database id or a Takeone ID.
 */
class FederationStatistics
{
    /**
     * The age categories the dashboard's own ageCategoryOf() assigns (its
     * draw divisions carry these names). Upper age inclusive.
     */
    private const AGE_BANDS = [
        [5, 'Mini poussins'], [7, 'Poussins'], [9, 'Pupilles'], [11, 'Benjamins'], [13, 'Minimes'],
        [15, 'Cadets'], [17, 'Juniors'], [34, 'Séniors'], [45, 'Vétérans 1'], [55, 'Vétérans 2'],
        [65, 'Vétérans 3'], [74, 'Vétérans 4'], [PHP_INT_MAX, 'Vétérans 5'],
    ];

    private const ENTRY_CAP = 20000;

    public function __construct(private FederationDirectory $directory, private FederationRegistry $registry) {}

    public function payload(array $access): array
    {
        $bodies = collect($this->directory->subtree($access['root']));
        $ids = $bodies->pluck('id');
        $today = CarbonImmutable::today();

        // ------------------------------------------------------------ clubs
        $clubRows = [];
        $clubIndex = [];   // 'fc:<id>' / 'fa:<id>' => row index
        FederationClub::with('club:id,club_name,gps_lat,gps_long')->whereIn('federation_id', $ids)
            ->where('status', FederationClub::STATUS_APPROVED)->get()
            ->each(function (FederationClub $c) use (&$clubRows, &$clubIndex) {
                if (! $c->club) {
                    return;
                }
                $clubIndex['fc:'.$c->id] = count($clubRows);
                $clubRows[] = [
                    'name' => $c->club->club_name,
                    'body' => $c->federation_id,
                    'lat' => is_numeric($c->club->gps_lat) ? (float) $c->club->gps_lat : null,
                    'lng' => is_numeric($c->club->gps_long) ? (float) $c->club->gps_long : null,
                    'affiliated' => true,
                    'joined' => $c->decided_at ? CarbonImmutable::parse($c->decided_at)->toDateString() : null,
                ];
            });
        // Applications are clubs not yet on the platform. They enter events,
        // so their athletes and medals count — but they are not affiliated.
        FederationClubApplication::whereIn('federation_id', $ids)
            ->where('status', '!=', FederationClubApplication::STATUS_REJECTED)->get()
            ->each(function (FederationClubApplication $a) use (&$clubRows, &$clubIndex) {
                if ($a->federation_club_id && isset($clubIndex['fc:'.$a->federation_club_id])) {
                    $clubIndex['fa:'.$a->id] = $clubIndex['fc:'.$a->federation_club_id];

                    return;
                }
                $clubIndex['fa:'.$a->id] = count($clubRows);
                $clubRows[] = ['name' => $a->name, 'body' => $a->federation_id, 'lat' => null, 'lng' => null, 'affiliated' => false, 'joined' => null,
                    'pending' => $a->status === FederationClubApplication::STATUS_PENDING];
            });
        $clubOf = fn ($clubId, $appId) => $clubId && isset($clubIndex['fc:'.$clubId]) ? $clubIndex['fc:'.$clubId]
            : ($appId && isset($clubIndex['fa:'.$appId]) ? $clubIndex['fa:'.$appId] : null);

        // ----------------------------------------------------------- events
        $eventsQ = FederationEvent::whereIn('federation_id', $ids);
        if ($access['sports'] !== []) {
            $eventsQ->whereIn('sport', $access['sports']);
        }
        $events = $eventsQ->orderBy('starts_on')->get();
        $eventIndex = $events->pluck('id')->flip();
        $eventRows = $events->map(function (FederationEvent $e) {
            $d = (array) $e->details;

            return [
                'name' => $e->name,
                'sport' => $e->sport,
                'status' => $e->status,
                'start' => $this->date($e->starts_on),
                'end' => $this->date($e->ends_on),
                'venue' => is_string($d['venue'] ?? null) && $d['venue'] !== 'TBC' ? $d['venue'] : null,
                'lat' => is_numeric($d['lat'] ?? null) ? (float) $d['lat'] : null,
                'lng' => is_numeric($d['lng'] ?? null) ? (float) $d['lng'] : null,
                'body' => $e->federation_id,
            ];
        })->values()->all();

        // ---------------------------------------------------------- entries
        $entries = FederationEntry::whereIn('federation_event_id', $events->pluck('id'))
            ->get(['id', 'federation_event_id', 'federation_club_id', 'club_application_id', 'officials', 'payments']);
        $entryAthletes = FederationEntryAthlete::whereIn('federation_entry_id', $entries->pluck('id'))
            ->limit(self::ENTRY_CAP)->get(['id', 'federation_entry_id', 'federation_athlete_id', 'weight', 'types', 'status', 'approval', 'created_at']);

        // --------------------------------------------------------- athletes
        $history = FederationAthleteClub::whereIn('federation_id', $ids)->get(['federation_athlete_id', 'federation_club_id', 'club_application_id', 'from_ym', 'to_ym']);
        $athleteIds = $history->pluck('federation_athlete_id')->merge($entryAthletes->pluck('federation_athlete_id'))->unique()->values();
        $athletes = FederationAthlete::with('user:id,full_name,name,gender,birthdate')->whereIn('id', $athleteIds)->get(['id', 'user_id', 'takeone_id', 'created_at']);
        $athleteIndex = $athletes->pluck('id')->flip();
        $byTakeone = $athletes->pluck('id', 'takeone_id');
        $historyBy = $history->groupBy('federation_athlete_id');
        $athleteRows = $athletes->map(function (FederationAthlete $a) use ($historyBy, $clubOf, $today) {
            $rows = $historyBy->get($a->id, collect());
            $current = $rows->whereNull('to_ym')->last();
            $firstYm = $rows->pluck('from_ym')->filter()->sort()->first();
            $u = $a->user;

            return [
                'name' => $u?->full_name ?: $u?->name,
                'g' => $u?->gender === 'Female' ? 'F' : ($u?->gender === 'Male' ? 'M' : null),
                'age' => $u?->birthdate ? $this->ageBand((int) floor($u->birthdate->diffInYears($today))) : null,
                'club' => $current ? $clubOf($current->federation_club_id, $current->club_application_id) : null,
                'reg' => $firstYm && preg_match('/^\d{4}-\d{2}$/', $firstYm) ? $firstYm.'-01' : $this->date($a->created_at),
            ];
        })->values()->all();

        $entryById = $entries->keyBy('id');
        $eventStart = $events->mapWithKeys(fn ($e) => [$e->id => $this->date($e->starts_on)]);
        $birth = $athletes->mapWithKeys(fn ($a) => [$a->id => $a->user?->birthdate]);
        $entryRows = $entryAthletes->map(function (FederationEntryAthlete $ea) use ($entryById, $eventIndex, $athleteIndex, $clubOf, $eventStart, $birth) {
            $en = $entryById[$ea->federation_entry_id];
            $at = $eventStart[$en->federation_event_id] ? CarbonImmutable::parse($eventStart[$en->federation_event_id]) : CarbonImmutable::today();
            $dob = $birth[$ea->federation_athlete_id] ?? null;

            return [
                'e' => $eventIndex[$en->federation_event_id],
                'a' => $athleteIndex[$ea->federation_athlete_id] ?? null,
                'c' => $clubOf($en->federation_club_id, $en->club_application_id),
                'disc' => $this->disciplines((array) $ea->types),
                'age' => $dob ? $this->ageBand((int) floor($dob->diffInYears($at))) : null,
                'w' => $ea->weight,
                'st' => $ea->status === 'Active' ? 'Active' : 'Withdrawn',
                'ok' => $ea->approval === 'Approved',
                'at' => $this->date($ea->created_at),
            ];
        })->filter(fn ($r) => $r['a'] !== null)->values()->all();

        // ------------------------------------------ draws → bouts & medals
        $bouts = [];
        $divisions = [];
        $medals = [];
        FederationDraw::whereIn('federation_event_id', $events->pluck('id'))->get()
            ->each(function (FederationDraw $d) use ($eventIndex, $byTakeone, $athleteIndex, &$bouts, &$divisions, &$medals) {
                [$g, $age, $div] = array_pad(explode('|', (string) $d->division, 3), 3, null);
                $idx = fn ($tk) => ($tk !== null && isset($byTakeone[$tk])) ? ($athleteIndex[$byTakeone[$tk]] ?? null) : null;
                $meta = ['e' => $eventIndex[$d->federation_event_id], 'div' => $div, 'g' => $g, 'age' => $age];
                $walk = $this->walk(array_values((array) $d->slots), (array) $d->results);

                $divisions[] = $meta + ['bouts' => $walk['total'], 'done' => count($walk['bouts']),
                    'athletes' => array_values(array_filter(array_map($idx, $walk['entrants']), fn ($x) => $x !== null))];
                foreach ($walk['bouts'] as $b) {
                    $bouts[] = $meta + ['r' => $b['round'], 'last' => $walk['rounds'] - 1, 'w' => $idx($b['winner']), 'l' => $idx($b['loser']), 'by' => $b['by']];
                }
                foreach ($walk['medals'] as [$tk, $place]) {
                    if (($a = $idx($tk)) !== null) {
                        $medals[] = $meta + ['a' => $a, 'place' => $place];
                    }
                }
            });

        // -------------------------------------------------------- officials
        $officials = [];
        foreach ($entries as $en) {
            foreach (['ref1', 'ref2', 'mgr'] as $slot) {
                $o = ((array) $en->officials)[$slot] ?? null;
                if (! is_array($o) || trim((string) ($o['name'] ?? '')) === '') {
                    continue;
                }
                $officials[] = [
                    'name' => trim((string) $o['name']),
                    'role' => $slot === 'mgr' ? 'Team manager' : 'Referee',
                    'lic' => trim((string) ($o['license'] ?? '')) ?: null,
                    'e' => $eventIndex[$en->federation_event_id],
                    'c' => $clubOf($en->federation_club_id, $en->club_application_id),
                ];
            }
        }

        return [
            'generatedAt' => now()->getTimestampMs(),
            'today' => $today->toDateString(),
            'federation' => $access['root']->name,
            // Where the map opens before any club has a location on it.
            'country' => strtoupper((string) (\App\Support\Countries::iso2($access['root']->country) ?? '')),
            'bodies' => $bodies->map(fn ($b) => ['id' => $b->id, 'name' => $b->name])->values()->all(),
            'clubs' => $clubRows,
            'athletes' => $athleteRows,
            'events' => $eventRows,
            'entries' => $entryRows,
            'divisions' => $divisions,
            'bouts' => $bouts,
            'medals' => $medals,
            'officials' => $officials,
            'alerts' => $this->alerts($ids, $events, $entries, $entryAthletes, $divisions),
        ];
    }

    /**
     * Walk one division's bracket the way the dashboard's buildBracket does:
     * the results FederationDraws kept are already consistent with the slots,
     * so each one is a bout that happened. Medals follow from the last two
     * rounds — the final's winner and loser, and each semi-final loser.
     *
     * @return array{rounds:int, total:int, entrants:array, bouts:array, medals:array}
     */
    private function walk(array $slots, array $results): array
    {
        $cur = array_map(fn ($s) => is_string($s) ? $s : null, $slots);
        $entrants = array_values(array_filter($cur));
        $rounds = 0;
        $total = 0;
        $out = [];
        $medals = [];
        $pending = "\0";
        $size = count($cur);
        $lastRound = $size >= 2 ? (int) round(log($size, 2)) - 1 : -1;

        for ($ri = 0; count($cur) >= 2; $ri++) {
            $rounds++;
            $next = [];
            for ($i = 0; $i + 1 < count($cur); $i += 2) {
                [$a, $b] = [$cur[$i], $cur[$i + 1]];
                $id = $ri.'-'.intdiv($i, 2);
                $realA = $a !== null && $a !== $pending;
                $realB = $b !== null && $b !== $pending;
                if (($a === null) !== ($b === null) && ($realA || $realB)) {   // a bye
                    $next[] = $realA ? $a : $b;

                    continue;
                }
                if ($a === null && $b === null) {
                    $next[] = null;

                    continue;
                }
                $total++;
                $res = $results[$id] ?? null;
                if ($realA && $realB && is_array($res) && in_array($res['winner'] ?? null, [$a, $b], true)) {
                    $w = $res['winner'];
                    $l = $w === $a ? $b : $a;
                    $out[] = ['round' => $ri, 'winner' => $w, 'loser' => $l, 'by' => in_array($res['by'] ?? null, FederationDraws::METHODS, true) ? $res['by'] : null];
                    if ($ri === $lastRound) {
                        $medals[] = [$w, 1];
                        $medals[] = [$l, 2];
                    } elseif ($ri === $lastRound - 1) {
                        $medals[] = [$l, 3];
                    }
                    $next[] = $w;
                } else {
                    $next[] = $pending;
                }
            }
            $cur = count($next) >= 2 ? $next : [];
        }

        return ['rounds' => max($rounds, 1), 'total' => $total, 'entrants' => $entrants, 'bouts' => $out, 'medals' => $medals];
    }

    /** What needs attention, counted from the records. Never a sample. */
    private function alerts(Collection $ids, Collection $events, Collection $entries, Collection $entryAthletes, array $divisions): array
    {
        $out = [];
        $notDone = $events->where('status', '!=', 'Completed')->pluck('id')->flip();
        $entryEvent = $entries->pluck('federation_event_id', 'id');

        $awaiting = $entryAthletes->filter(fn ($ea) => $ea->status === 'Active' && $ea->approval !== 'Approved' && isset($notDone[$entryEvent[$ea->federation_entry_id] ?? 0]));
        if ($awaiting->isNotEmpty()) {
            $n = $awaiting->count();
            $ev = $awaiting->map(fn ($ea) => $entryEvent[$ea->federation_entry_id])->unique()->count();
            $out[] = ['id' => 'approvals', 'sev' => 'Warning', 'text' => $n.' athlete registration'.($n > 1 ? 's' : '').' awaiting approval', 'sub' => 'Across '.$ev.' event'.($ev > 1 ? 's' : ''), 'due' => 'Registrations tab'];
        }

        $unpaid = 0;
        $amount = 0.0;
        foreach ($entries as $en) {
            foreach ((array) $en->payments as $p) {
                if (($p['status'] ?? '') === 'Awaiting transfer') {
                    $unpaid++;
                    $amount += (float) ($p['amount'] ?? 0);
                }
            }
        }
        if ($unpaid) {
            $out[] = ['id' => 'payments', 'sev' => 'Warning', 'text' => $unpaid.' club payment'.($unpaid > 1 ? 's' : '').' awaiting confirmation', 'sub' => 'Bank transfers of BHD '.number_format($amount, 3).' to confirm', 'due' => 'Financials'];
        }

        $transfers = FederationTransfer::whereIn('federation_id', $ids)->where('status', 'Pending')->get(['due_at', 'old_club']);
        if ($transfers->isNotEmpty()) {
            $n = $transfers->count();
            $due = $transfers->pluck('due_at')->filter()->sort()->first();
            $out[] = ['id' => 'transfers', 'sev' => 'Warning', 'text' => $n.' transfer request'.($n > 1 ? 's' : '').' awaiting a decision',
                'sub' => $transfers->whereIn('old_club', ['Released', 'Objected'])->count().' with the old club’s answer on file', 'due' => $due ? 'Due '.CarbonImmutable::parse($due)->format('j M') : 'Athletes tab'];
        }

        $apps = FederationClubApplication::whereIn('federation_id', $ids)->where('status', FederationClubApplication::STATUS_PENDING)->count();
        if ($apps) {
            $out[] = ['id' => 'apps', 'sev' => 'Informational', 'text' => $apps.' club application'.($apps > 1 ? 's' : '').' awaiting review', 'sub' => 'Clubs that entered without a platform account', 'due' => 'Clubs'];
        }

        // Division rows name their event by payload position.
        $live = $events->values()->filter(fn ($e) => in_array($e->status, ['Live', 'Completed'], true))->keys()->flip();
        $unfinished = collect($divisions)->filter(fn ($d) => isset($live[$d['e']]) && $d['done'] < $d['bouts']);
        if ($unfinished->isNotEmpty()) {
            $n = $unfinished->count();
            $out[] = ['id' => 'results', 'sev' => 'Informational', 'text' => $n.' division'.($n > 1 ? 's' : '').' with bouts still to record', 'sub' => $unfinished->sum(fn ($d) => $d['bouts'] - $d['done']).' bouts without a result', 'due' => 'Draw tab'];
        }

        return $out;
    }

    /** The discipline an entry's competition types belong to. */
    private function disciplines(array $types): array
    {
        $out = [];
        foreach ($types as $t) {
            $t = (string) $t;
            if (stripos($t, 'kata') !== false) {
                $out['Kata'] = true;
            } elseif (stripos($t, 'kumite') !== false) {
                $out['Kumite'] = true;
            } elseif ($t !== '') {
                $out['Other'] = true;
            }
        }

        return array_keys($out);
    }

    private function ageBand(int $age): string
    {
        foreach (self::AGE_BANDS as [$max, $name]) {
            if ($age <= $max) {
                return $name;
            }
        }

        return 'Vétérans 5';
    }

    private function date(mixed $v): ?string
    {
        if (! $v) {
            return null;
        }
        try {
            return CarbonImmutable::parse($v)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
