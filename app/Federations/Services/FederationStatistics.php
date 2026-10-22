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
     * The age categories the dashboard's own ageCategoryOf() assigns, by their
     * ENGLISH names (KarateShotukanWeightclasses::englishName — saved draws
     * still carry the French key and are translated as they are read).
     * Upper age inclusive.
     */
    private const AGE_BANDS = [
        [5, 'Under 6 Years'], [7, 'Under 8 Years'], [9, 'Under 10 Years'], [11, 'Under 12 Years'], [13, 'Under 14 Years'],
        [15, 'Cadets'], [17, 'Juniors'], [34, 'Seniors'], [45, 'Veterans 1'], [55, 'Veterans 2'],
        [65, 'Veterans 3'], [74, 'Veterans 4'], [PHP_INT_MAX, 'Veterans 5'],
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
        FederationClub::with('club:id,club_name,logo,gps_lat,gps_long')->whereIn('federation_id', $ids)
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
                    // The club's own logo, served through /file (authorised there).
                    'logo' => $c->club->logo ? file_url($c->club->logo) : null,
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
                    'logo' => $a->logo ? file_url($a->logo) : null,
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
                $meta = ['e' => $eventIndex[$d->federation_event_id], 'div' => $div, 'g' => $g, 'age' => $age !== null ? \App\Support\KarateShotukanWeightclasses::englishName($age) : null];
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

        // ------------------- platform-run events → bouts & medals (2026-10-18)
        // An event run on the platform keeps its draw and every result in its
        // own event (event_categories / event_matches), not in FederationDraws —
        // read them there. An event that also has dashboard draws keeps those
        // alone, so nothing is counted twice.
        $drawn = FederationDraw::whereIn('federation_event_id', $events->pluck('id'))->distinct()->pluck('federation_event_id')->flip();
        $byUser = $athletes->pluck('id', 'user_id');
        $platformStatus = [];
        foreach ($events as $fe) {
            if (! $fe->club_event_id || isset($drawn[$fe->id])) {
                continue;
            }
            $r = rescue(fn () => $this->platformResults($fe, $eventIndex[$fe->id], fn ($userId) => isset($byUser[$userId]) ? ($athleteIndex[$byUser[$userId]] ?? null) : null, fn ($a) => $a === null ? null : ($athleteRows[$a]['g'] ?? null)), null, report: true);
            if (! $r) {
                continue;
            }
            array_push($divisions, ...$r['divisions']);
            array_push($bouts, ...$r['bouts']);
            array_push($medals, ...$r['medals']);
        }
        // One status for every screen (FederationEvent::lifecycleStatus) — the
        // events list and the event header read the same rule.
        foreach ($events as $fe) {
            if (isset($eventIndex[$fe->id]) && ($st = $fe->lifecycleStatus()) !== $fe->status) {
                $platformStatus[$eventIndex[$fe->id]] = $st;
            }
        }
        foreach ($platformStatus as $i => $st) {
            $eventRows[$i]['status'] = $st;
        }

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
            // `parent` places each body in the tree, so the page can roll a sub-federation's
            // own sub-bodies into its row; `focus` is the body these figures are for.
            'focus' => $access['root']->id,
            'bodies' => $bodies->map(fn ($b) => ['id' => $b->id, 'name' => $b->name, 'parent' => $b->parent_id])->values()->all(),
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

    /**
     * One platform-run event's divisions, bouts and medals, in the same row
     * shapes the dashboard draws produce. Bouts are the mats' recorded results
     * (byes are not bouts); medals are each division's podium as Advancement
     * settled it — places 1 to 3, two bronzes where the event awards two.
     *
     * A mixed group has no gender of its own, so its bouts and medals take
     * the athlete's (a bout only when both fighters share one). A division
     * named for a weight RANGE ("Group A : 5-6y, 17-24kg") carries `wl`, its
     * weight label, built from the category's own bounds.
     *
     * @param  \Closure(int): ?int  $athleteOf  user id → athlete row index
     * @param  \Closure(?int): ?string  $genderOf  athlete row index → 'M' / 'F' / null
     * @return array{divisions: array, bouts: array, medals: array, done: int}
     */
    private function platformResults(FederationEvent $fe, int $e, \Closure $athleteOf, \Closure $genderOf): array
    {
        $eventId = (int) $fe->club_event_id;
        $cats = \Illuminate\Support\Facades\DB::table('event_categories')->where('event_id', $eventId)->where('is_heading', false)
            ->get(['id', 'name', 'gender', 'weight_class', 'min_age', 'min_weight', 'max_weight', 'podium'])->keyBy('id');
        $userOf = \Illuminate\Support\Facades\DB::table('club_event_registrations')->where('event_id', $eventId)
            ->where('role', 'participant')->pluck('user_id', 'id');
        $matches = \Illuminate\Support\Facades\DB::table('event_matches')->where('event_id', $eventId)->where('won_by_bye', false)
            ->get(['category_id', 'round', 'status', 'winner', 'win_reason', 'a_competitor_id', 'b_competitor_id'])->groupBy('category_id');
        $regs = \Illuminate\Support\Facades\DB::table('club_event_registrations')->where('event_id', $eventId)
            ->where('role', 'participant')->whereNotNull('category_id')->get(['id', 'category_id'])->groupBy('category_id');

        $order = ['Round Robin', 'Round of 128', 'Round of 64', 'Round of 32', 'Round of 16', 'Quarterfinal', 'Semifinal', 'Third place', 'Final'];
        $by = ['points' => 'Points', 'hansoku' => 'Disqualification', 'shikkaku' => 'Disqualification', 'kiken' => 'Withdrawal', 'medical' => 'Withdrawal', 'no_show' => 'Withdrawal'];
        $idx = fn ($regId) => $regId && isset($userOf[$regId]) ? $athleteOf((int) $userOf[$regId]) : null;

        $out = ['divisions' => [], 'bouts' => [], 'medals' => [], 'done' => 0];
        foreach ($cats as $c) {
            $ms = $matches->get($c->id, collect());
            $podium = json_decode((string) $c->podium, true);
            $people = $regs->get($c->id, collect());
            if ($ms->isEmpty() && ! is_array($podium) && $people->isEmpty()) {
                continue;
            }
            $d = FederationEventBracket::division($c->name, $c->weight_class, $c->min_age !== null ? (int) $c->min_age : null);
            $g = strtolower(substr((string) $c->gender, 0, 1));
            $meta = ['e' => $e, 'div' => $d['division'], 'g' => $g === 'f' ? 'F' : ($g === 'm' ? 'M' : null),
                'age' => $d['ageGroup'] !== '—' ? \App\Support\KarateShotukanWeightclasses::englishName($d['ageGroup']) : null];
            if (! preg_match('/(?:^|\s)[-+]\d+(?:\.\d+)?\s*kg\b/i', $d['division'])) {
                $meta['wl'] = $this->weightRange($c->min_weight, $c->max_weight);
            }

            $rounds = $ms->pluck('round')->unique()->map(fn ($r) => array_search($r, $order, true))->filter(fn ($i) => $i !== false)->sort()->values();
            $done = $ms->filter(fn ($m) => $m->status === 'done' && in_array($m->winner, ['a', 'b'], true) && $m->a_competitor_id && $m->b_competitor_id);
            foreach ($done as $m) {
                [$w, $l] = $m->winner === 'a' ? [$m->a_competitor_id, $m->b_competitor_id] : [$m->b_competitor_id, $m->a_competitor_id];
                $pos = $rounds->search(array_search($m->round, $order, true));
                $g = $meta['g'] ?? (($gw = $genderOf($idx($w))) === $genderOf($idx($l)) ? $gw : null);
                $out['bouts'][] = ['g' => $g] + $meta + ['r' => $pos === false ? 0 : $pos, 'last' => max(0, $rounds->count() - 1), 'w' => $idx($w), 'l' => $idx($l), 'by' => $by[$m->win_reason] ?? null];
            }
            $out['done'] += $done->count();
            $out['divisions'][] = $meta + ['bouts' => $ms->count(), 'done' => $done->count(),
                'athletes' => $people->map(fn ($r) => $idx($r->id))->filter(fn ($x) => $x !== null)->unique()->values()->all()];

            foreach (is_array($podium) ? $podium : [] as $p) {
                $place = (int) ($p['place'] ?? 0);
                if ($place >= 1 && $place <= 3 && ($a = $idx($p['competitor_id'] ?? null)) !== null) {
                    $out['medals'][] = ['g' => $meta['g'] ?? $genderOf($a)] + $meta + ['a' => $a, 'place' => $place];
                }
            }
        }

        return $out;
    }

    /** "17–24 kg", "77.6 kg", "-40 kg" or "+55 kg" from a category's bounds; '' when it has none. */
    private function weightRange(mixed $min, mixed $max): string
    {
        $n = fn ($v) => is_numeric($v) && (float) $v > 0 ? rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.') : null;
        [$lo, $hi] = [$n($min), $n($max)];
        if ($hi !== null && (float) $hi >= 200) {
            $hi = null;
        }

        return match (true) {
            $lo !== null && $hi !== null => $lo === $hi ? $lo.' kg' : $lo.'–'.$hi.' kg',
            $hi !== null => '-'.$hi.' kg',
            $lo !== null => '+'.$lo.' kg',
            default => '',
        };
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

        return 'Veterans 5';
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
