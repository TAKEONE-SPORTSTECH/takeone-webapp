<?php

namespace App\Federations\Services;

use App\Events\Models\ClubEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The competition, read back from what the mats logged (owner, 2026-10-17:
 * "you have the data of the bouts … time, scores and fouls — build something
 * that makes me proud"). Every figure here is derived from the officiating
 * log (`event_match_events`) and the draw (`event_matches`) — nobody typed a
 * statistic in. Read-only.
 *
 * How the log is read (Karate's mat, App\Scoreboard\Sports\Karate\Mat\Scoring):
 *  · point n=1/2/3 — Yuko / Waza-ari / Ippon; undo_point takes the same back
 *  · penalty level — the corner's penalty STATE after the call (1 C1 … 4 HC,
 *    5 Hansoku, 0 cleared): a foul is a step up
 *  · load / reset — a fresh attempt: only the last attempt before `finish` counts
 *  · clock_remaining at an event, against clock_duration, is when in the bout
 *    it happened; occurred_at (UTC) is when on the wall clock
 * Only bouts the draw decided, from after the event was started (the draw
 * report's own rule, BracketPdf::actualHours); exhibitions are counted apart
 * and never mixed into the official figures.
 */
class FederationBoutAnalytics
{
    /**
     * @param  array  $bracket  FederationEventBracket::of($fe, withEntry: true)
     * @return array<string, mixed>
     */
    public function for(ClubEvent $event, array $bracket): array
    {
        $zone = $event->zone();
        $local = fn (string $utc) => Carbon::parse($utc, 'UTC')->setTimezone($zone);

        $matches = DB::table('event_matches')->where('event_id', $event->id)->where('status', 'done')
            ->where(fn ($q) => $q->whereNull('won_by_bye')->orWhere('won_by_bye', false))
            ->get(['id', 'category_id', 'round', 'winner', 'a_score', 'b_score', 'win_reason', 'a_competitor_id', 'b_competitor_id', 'court', 'match_no'])
            ->keyBy('id');

        $logs = DB::table('event_match_events')->where('event_id', $event->id)
            ->when($event->started_at, fn ($q) => $q->where('occurred_at', '>=', $event->started_at))
            ->orderBy('occurred_at')->orderBy('id')
            ->get(['match_id', 'court', 'command', 'payload', 'side', 'points', 'occurred_at', 'clock_remaining', 'clock_duration']);

        // Who is who: every person the draw names, with face, flag and club.
        $people = [];
        foreach ($bracket['matches'] as $m) {
            foreach (['A', 'B'] as $k) {
                if (! empty($m[$k]['reg'])) {
                    $people[$m[$k]['reg']] = $m[$k];
                }
            }
        }
        $catOf = DB::table('event_categories')->where('event_id', $event->id)->pluck('name', 'id');

        // ── Each bout, replayed from its log ──────────────────────────────
        $bouts = [];
        $exhibitions = 0;
        foreach ($logs->groupBy(fn ($e) => $e->match_id ?: 'x') as $mid => $events) {
            if ($mid === 'x') {
                $exhibitions = $events->where('command', 'exhibition')->count();

                continue;
            }
            $m = $matches[$mid] ?? null;
            $b = $m ? $this->replay($events->values()->all()) : null;
            if (! $b) {
                continue;
            }
            $division = (string) ($catOf[$m->category_id] ?? '');
            $sa = is_numeric($m->a_score) ? (int) $m->a_score : $b['sa'];
            $sb = is_numeric($m->b_score) ? (int) $m->b_score : $b['sb'];
            $bouts[] = array_merge($b, [
                'id' => (int) $mid, 'no' => (int) $m->match_no, 'division' => $division, 'age' => $this->ageGroup($division),
                'round' => (string) $m->round, 'mat' => $this->matName($m->court ?: ($events->first()->court ?? '')),
                'a' => (int) $m->a_competitor_id, 'b' => (int) $m->b_competitor_id,
                'winner' => in_array($m->winner, ['a', 'b'], true) ? $m->winner : null,
                'sa' => $sa, 'sb' => $sb, 'reason' => $this->how($m->win_reason, $sa, $sb),
                'startLocal' => $b['start'] ? $local($b['start']) : null,
                'endLocal' => $b['end'] ? $local($b['end']) : null,
            ]);
        }

        $decided = array_values(array_filter($bouts, fn ($b) => $b['winner'] !== null));

        // ── Scoring ───────────────────────────────────────────────────────
        $points = collect($bouts)->flatMap(fn ($b) => $b['points']);
        $tech = ['Yuko' => 0, 'Waza-ari' => 0, 'Ippon' => 0];
        foreach ($points as $p) {
            $tech[[1 => 'Yuko', 2 => 'Waza-ari', 3 => 'Ippon'][$p['n']]]++;
        }
        // When in the bout: tenths of the regulation time.
        $phase = array_fill(0, 10, 0);
        foreach ($points as $p) {
            $phase[min(9, (int) floor($p['frac'] * 10))]++;
        }
        $lastSeconds = $points->filter(fn ($p) => $p['left'] !== null && $p['left'] <= 15)->count();

        $techByAge = [];
        foreach ($bouts as $b) {
            foreach ($b['points'] as $p) {
                $techByAge[$b['age']][$p['n']] = ($techByAge[$b['age']][$p['n']] ?? 0) + 1;
            }
        }
        uksort($techByAge, fn ($x, $y) => $this->ageRank($x) <=> $this->ageRank($y));

        // ── Who scored first, senshu, comebacks, corners ─────────────────
        $firstWon = $firstAny = $senshuWon = $senshuAny = $comebacks = 0;
        $corner = ['a' => 0, 'b' => 0];
        foreach ($decided as $b) {
            $corner[$b['winner']]++;
            if ($b['first']) {
                $firstAny++;
                $firstWon += $b['first'] === $b['winner'] ? 1 : 0;
            }
            if ($b['senshu']) {
                $senshuAny++;
                $senshuWon += $b['senshu'] === $b['winner'] ? 1 : 0;
            }
            $comebacks += $b['trailed'][$b['winner']] ? 1 : 0;
        }

        // ── Fouls ────────────────────────────────────────────────────────
        $fouls = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        $foulsByAge = [];
        foreach ($bouts as $b) {
            foreach ($b['fouls'] as $lvl) {
                $fouls[$lvl]++;
            }
            $foulsByAge[$b['age']] = ($foulsByAge[$b['age']] ?? 0) + count($b['fouls']);
        }
        $boutsByAge = collect($bouts)->countBy('age')->all();

        // ── Time ─────────────────────────────────────────────────────────
        $fight = collect($bouts)->pluck('fight')->filter(fn ($v) => $v !== null && $v >= 0);
        $wall = collect($bouts)->pluck('wall')->filter(fn ($v) => $v !== null && $v >= 0 && $v < 3600);
        $early = collect($decided)->filter(fn ($b) => $b['fight'] !== null && $b['duration'] && $b['fight'] < $b['duration'] - 1 && $b['reason'] !== 'Withdrawal')->count();

        // ── The mats through the day ─────────────────────────────────────
        $timed = array_values(array_filter($bouts, fn ($b) => $b['startLocal'] && $b['endLocal']));
        $mats = collect($timed)->groupBy('mat')->sortKeysUsing(fn ($x, $y) => strnatcasecmp((string) $x, (string) $y))
            ->map(function ($list, $mat) {
                $list = $list->sortBy(fn ($b) => $b['startLocal']->getTimestamp())->values();
                $gaps = [];
                for ($i = 1; $i < $list->count(); $i++) {
                    $g = $list[$i]['startLocal']->getTimestamp() - $list[$i - 1]['endLocal']->getTimestamp();
                    if ($g >= 0 && $g < 1800) {
                        $gaps[] = $g;
                    }
                }

                return ['mat' => (string) $mat, 'bouts' => $list->count(),
                    'from' => $list->first()['startLocal'], 'to' => $list->max(fn ($b) => $b['endLocal']),
                    'fight' => (int) $list->sum('fight'),
                    'changeover' => $gaps ? (int) round(array_sum($gaps) / count($gaps)) : null,
                    'blocks' => $list->map(fn ($b) => ['from' => $b['startLocal'], 'to' => $b['endLocal'], 'age' => $b['age'], 'no' => $b['no']])->all()];
            })->values()->all();
        $perHour = [];
        foreach ($timed as $b) {
            $h = $b['startLocal']->format('H');
            $perHour[$h] = ($perHour[$h] ?? 0) + 1;
        }
        ksort($perHour);

        // ── People: top scorers, ippon masters, unbeaten ─────────────────
        $by = [];
        foreach ($bouts as $b) {
            foreach (['a', 'b'] as $k) {
                if (! $b[$k]) {
                    continue;
                }
                $r = $by[$b[$k]] ?? ['reg' => $b[$k], 'points' => 0, 'ippon' => 0, 'bouts' => 0, 'wins' => 0, 'conceded' => 0];
                $r['bouts']++;
                $r['wins'] += $b['winner'] === $k ? 1 : 0;
                $r['points'] += $k === 'a' ? $b['sa'] : $b['sb'];
                $r['conceded'] += $k === 'a' ? $b['sb'] : $b['sa'];
                $r['ippon'] += collect($b['points'])->where('side', $k)->where('n', 3)->count();
                $by[$b[$k]] = $r;
            }
        }
        $who = fn (array $r) => $r + ['person' => $people[$r['reg']] ?? null];
        $rank = fn (string ...$keys) => fn ($r) => array_map(fn ($k) => $r[$k], $keys);

        // ── Bouts worth remembering ──────────────────────────────────────
        $withPeople = fn ($b) => $b ? $b + ['A' => $people[$b['a']] ?? null, 'B' => $people[$b['b']] ?? null] : null;
        $margins = [];
        foreach ($decided as $b) {
            $d = abs($b['sa'] - $b['sb']);
            $key = $d >= 8 ? '8+' : (string) $d;
            $margins[$key] = ($margins[$key] ?? 0) + 1;
        }
        uksort($margins, fn ($x, $y) => (int) $x <=> (int) $y);

        return [
            'bouts' => count($bouts),
            'decided' => count($decided),
            'exhibitions' => $exhibitions,
            'points' => $points->count(),
            'scored' => (int) $points->sum('n'),
            'pointsPerBout' => count($bouts) ? round($points->sum('n') / count($bouts), 1) : 0,
            'tech' => $tech,
            'phase' => $phase,
            'lastSeconds' => $lastSeconds,
            'techByAge' => $techByAge,
            'first' => ['won' => $firstWon, 'of' => $firstAny],
            'senshu' => ['won' => $senshuWon, 'of' => $senshuAny],
            'comebacks' => $comebacks,
            'corner' => $corner,
            'reasons' => collect($decided)->countBy('reason')->sortDesc()->all(),
            'fouls' => $fouls,
            'foulsTotal' => array_sum($fouls),
            'foulsByAge' => collect($foulsByAge)->map(fn ($f, $age) => ['age' => (string) $age, 'fouls' => $f, 'perBout' => round($f / max(1, $boutsByAge[$age] ?? 1), 2)])
                ->sortBy(fn ($r) => $this->ageRank($r['age']))->values()->all(),
            'fight' => ['total' => (int) $fight->sum(), 'avg' => $fight->count() ? (int) round($fight->avg()) : null],
            'wall' => ['avg' => $wall->count() ? (int) round($wall->avg()) : null],
            'early' => $early,
            'mats' => $mats,
            'day' => ['from' => collect($timed)->min(fn ($b) => $b['startLocal']), 'to' => collect($timed)->max(fn ($b) => $b['endLocal'])],
            'perHour' => $perHour,
            'topScorers' => collect($by)->sortByDesc($rank('points', 'wins'))->take(8)->map($who)->values()->all(),
            'ipponMasters' => collect($by)->filter(fn ($r) => $r['ippon'] > 0)->sortByDesc($rank('ippon', 'points'))->take(5)->map($who)->values()->all(),
            'unbeaten' => collect($by)->filter(fn ($r) => $r['wins'] >= 2 && $r['wins'] === $r['bouts'])
                ->sortByDesc(fn ($r) => [$r['wins'], $r['points'] - $r['conceded']])->take(6)->map($who)->values()->all(),
            'highlights' => array_filter([
                'highest' => $withPeople(collect($decided)->sortByDesc(fn ($b) => $b['sa'] + $b['sb'])->first()),
                'widest' => $withPeople(collect($decided)->sortByDesc(fn ($b) => abs($b['sa'] - $b['sb']))->first()),
                'fastest' => $withPeople(collect($decided)->filter(fn ($b) => $b['fight'] !== null && $b['fight'] > 0 && $b['reason'] === 'Points')->sortBy('fight')->first()),
                'closest' => $withPeople(collect($decided)->filter(fn ($b) => $b['sa'] + $b['sb'] > 0 && $b['sa'] !== $b['sb'])
                    ->sortBy(fn ($b) => [abs($b['sa'] - $b['sb']), -($b['sa'] + $b['sb'])])->first()),
            ]),
            'margins' => $margins,
            // Per entry (registration id): bouts, wins, points for and against.
            'entries' => $by,
        ];
    }

    /**
     * One bout from its log: the last attempt before it was finished.
     *
     * @param  list<object>  $events
     * @return array<string, mixed>|null
     */
    private function replay(array $events): ?array
    {
        $fresh = fn (?float $duration = null) => ['points' => [], 'fouls' => [], 'foulSides' => [], 'level' => ['a' => 0, 'b' => 0],
            'senshu' => null, 'first' => null, 'start' => null, 'end' => null, 'duration' => $duration, 'left' => null,
            'run' => ['a' => 0, 'b' => 0], 'trailed' => ['a' => false, 'b' => false]];
        $s = $fresh();
        $done = null;
        foreach ($events as $e) {
            $p = json_decode((string) $e->payload, true) ?: [];
            $raw = $p['side'] ?? null;
            $side = $raw === 'aka' ? 'a' : ($raw === 'ao' ? 'b' : (in_array($e->side, ['a', 'b'], true) ? $e->side : null));
            $dur = $e->clock_duration !== null ? (float) $e->clock_duration : null;
            $left = $e->clock_remaining !== null ? (float) $e->clock_remaining : null;
            switch ($e->command) {
                case 'load':
                case 'reset':
                    $s = $fresh($dur);
                    break;
                case 'start':
                    $s['start'] ??= (string) $e->occurred_at;
                    $s['duration'] ??= $dur;
                    break;
                case 'point':
                    $n = (int) ($p['n'] ?? $e->points ?? 1);
                    if ($side && $n >= 1 && $n <= 3) {
                        $d = $dur ?: $s['duration'];
                        $s['points'][] = ['side' => $side, 'n' => $n, 'left' => $left,
                            'frac' => $d && $left !== null ? max(0, min(0.999, ($d - $left) / $d)) : 0];
                        $s['run'][$side] += $n;
                        $s['first'] ??= $side;
                        foreach (['a' => 'b', 'b' => 'a'] as $x => $y) {
                            $s['trailed'][$x] = $s['trailed'][$x] || $s['run'][$x] < $s['run'][$y];
                        }
                    }
                    break;
                case 'undo_point':
                    $n = (int) ($p['n'] ?? 1);
                    for ($i = count($s['points']) - 1; $i >= 0; $i--) {
                        if ($s['points'][$i]['side'] === $side && $s['points'][$i]['n'] === $n) {
                            array_splice($s['points'], $i, 1);
                            $s['run'][$side] -= $n;
                            break;
                        }
                    }
                    break;
                case 'penalty':
                    $lvl = (int) ($p['level'] ?? 0);
                    if ($side) {
                        if ($lvl > $s['level'][$side] && $lvl <= 5) {
                            $s['fouls'][] = $lvl;
                            $s['foulSides'][] = $side;
                        }
                        $s['level'][$side] = $lvl;
                    }
                    break;
                case 'senshu':
                    $s['senshu'] = $side;
                    break;
                case 'finish':
                    $s['end'] = (string) $e->occurred_at;
                    $s['left'] = $left;
                    $s['duration'] ??= $dur;
                    $done = $s;
                    break;
            }
        }
        if (! $done) {
            return null;
        }
        $d = $done['duration'];

        return $done + [
            'fight' => $d !== null && $done['left'] !== null ? (int) round($d - $done['left']) : null,
            'wall' => $done['start'] && $done['end'] ? Carbon::parse($done['end'], 'UTC')->getTimestamp() - Carbon::parse($done['start'], 'UTC')->getTimestamp() : null,
            'sa' => $done['run']['a'],
            'sb' => $done['run']['b'],
        ];
    }

    /** The tab's own words for how a bout was won (FederationEventBracket::how). */
    private function how(?string $reason, $a, $b): string
    {
        return match ($reason) {
            'hansoku', 'shikkaku' => 'Disqualification',
            'kiken', 'medical', 'no_show' => 'Withdrawal',
            'ippon' => 'Ippon',
            'hantei', 'decision' => 'Decision',
            'other' => is_numeric($a) && is_numeric($b) && (float) $a !== (float) $b ? 'Points' : 'Decision',
            default => 'Points',
        };
    }

    private function matName(?string $court): string
    {
        return preg_match('/(\d+)/', (string) $court, $m) ? 'Mat '.(int) $m[1] : ((string) $court ?: 'Mat 1');
    }

    /** "Under 10 Years", "Cadets", … read off a division's name. */
    public function ageGroup(string $label): string
    {
        if (preg_match('/^(Under\s+\d+\s+Years|U\d+|Cadets?|Juniors?|Seniors?|Séniors|Veterans?|Masters?)/iu', trim($label), $m)) {
            return preg_replace('/^U(\d+)$/i', 'Under $1 Years', ucwords(mb_strtolower($m[1])));
        }

        return 'Open';
    }

    public function ageRank(string $age): int
    {
        if (preg_match('/(\d+)/', $age, $m)) {
            return (int) $m[1];
        }

        return match (true) {
            stripos($age, 'cadet') === 0 => 40,
            stripos($age, 'junior') === 0 => 50,
            stripos($age, 'senior') === 0, stripos($age, 'sénior') === 0 => 60,
            stripos($age, 'veteran') === 0, stripos($age, 'master') === 0 => 70,
            default => 80,
        };
    }
}
