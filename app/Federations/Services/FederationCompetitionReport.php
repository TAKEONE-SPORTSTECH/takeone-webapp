<?php

namespace App\Federations\Services;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Support\BracketPdf;
use App\Federations\Models\FederationEvent;
use App\Members\Models\User;
use App\Support\Countries;
use App\Support\Flag;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * The whole competition as one printed report (owner, 2026-10-17: "the
 * report … cover page, the information of the event, table of contents,
 * officials, the clubs with their medals, weight classes, the brackets and
 * the winners, then statistics and analytics — make it unique").
 *
 * Three printed parts joined into one PDF, page numbers running through all
 * of them:
 *   A. front matter  — federations::report.front (this class lays it out)
 *   B. the brackets  — the draw report's own sheets (BracketPdf), so every
 *                      bracket IS the board, podium included
 *   C. analytics     — federations::report.back, from FederationBoutAnalytics
 * Every page count is decided before anything is printed, so the contents
 * page can say where each part starts.
 *
 * Reuses, never re-draws: the cover data and officials (BracketPdf::cover),
 * the clubs pages (FederationResultsPdf::clubPagesFor), the report cover
 * (FederationReportCover), the brackets (BracketPdf::html).
 */
class FederationCompetitionReport
{
    /** A4 body under the band, in mm; fixed row heights the views keep. */
    private const BODY_MM = 222;

    private const TABLE_HEAD_MM = 24;

    private const TABLE_ROW_MM = 9;

    /** Clubs on one performance page. */
    private const PERF_ROWS = 16;

    public function __construct(
        private FederationEventBracket $brackets,
        private FederationBoutAnalytics $analytics,
        private FederationResultsPdf $results,
        private FederationReportCover $cover,
        private BracketPdf $pdf,
    ) {}

    public static function available(): bool
    {
        return BracketPdf::available();
    }

    /** The finished report's bytes, or null when the event has no draw to report on. */
    public function build(FederationEvent $fe, ClubEvent $event, User $viewer): ?string
    {
        $b = $this->brackets->of($fe, withEntry: true);
        $divisions = $this->pdf->divisions($event, $viewer);
        if (! $b || $divisions === []) {
            return null;
        }
        $divisions = $this->asPrinted($event, $divisions);

        $data = $this->data($fe, $event, $viewer, $b, $divisions);

        // ── Page plan: every part's length, before printing anything ──────
        $plan = [
            'cover' => 1, 'info' => 1, 'toc' => 1,
            'officials' => count($data['team']),
            'medals' => count($data['medalPages']),
            'clubs' => count($data['clubPages']),
            'divisions' => count($data['divisionPages']),
        ];
        $front = array_sum($plan);
        $brackets = count($divisions);
        $backPlan = ['numbers' => 1, 'won' => 1, 'time' => 1, 'discipline' => 1, 'stars' => 1,
            'performance' => count($data['perfPages']), 'signoff' => 1];
        $total = $front + $brackets + array_sum($backPlan);

        $starts = [];
        $at = 1;
        foreach ($plan as $k => $n) {
            $starts[$k] = $n ? $at : null;
            $at += $n;
        }
        $starts['brackets'] = $at;
        $at += $brackets;
        foreach ($backPlan as $k => $n) {
            $starts[$k] = $n ? $at : null;
            $at += $n;
        }
        $data['toc'] = $this->toc($starts);
        $data['total'] = $total;
        $data['starts'] = $starts;

        // ── Print the three parts and join them ──────────────────────────
        $dir = storage_path('app/private/tmp/competition-report/'.Str::uuid());
        File::ensureDirectoryExists($dir, 0750);
        try {
            file_put_contents($dir.'/a.pdf', $this->pdf->render(view('federations::report.front', $data)->render()));
            file_put_contents($dir.'/b.pdf', $this->pdf->render($this->pdf->html($event, $viewer, $divisions, 'linear', 'match', false, true, $front, $total)));
            file_put_contents($dir.'/c.pdf', $this->pdf->render(view('federations::report.back', $data + ['base' => $front + $brackets])->render()));

            $join = new Process(['pdfunite', $dir.'/a.pdf', $dir.'/b.pdf', $dir.'/c.pdf', $dir.'/report.pdf']);
            $join->setTimeout(60)->run();
            if (! $join->isSuccessful() || ! is_file($dir.'/report.pdf')) {
                throw new \RuntimeException('pdfunite failed: '.Str::limit(trim($join->getErrorOutput()), 300));
            }

            return (string) file_get_contents($dir.'/report.pdf');
        } finally {
            File::deleteDirectory($dir);
        }
    }

    /**
     * Everything the two views draw.
     *
     * @return array<string, mixed>
     */
    public function data(FederationEvent $fe, ClubEvent $event, User $viewer, array $b, array $divisions): array
    {
        // Officials headed the way results books print them (organising
        // committee · technical delegate & jury · referees & judges · table · teams).
        $cover = $this->pdf->cover($event, $divisions, $viewer, officialsBySection: true);
        $stats = $this->analytics->for($event, $b);

        // The stars' faces, inlined and re-authorised for this viewer (a face
        // they may not see prints as the silhouette).
        $faces = [];
        $face = function (?array $p) use ($viewer, &$faces): ?array {
            if (! $p) {
                return $p;
            }
            $url = $p['photo'] ?? null;
            $p['face'] = $url && str_contains($url, '/file/') ? ($faces[$url] ??= $this->pdf->dataUri($url, $viewer)) : null;
            $p['name'] = FederationResultsPdf::shortName((string) ($p['name'] ?? ''));

            return $p;
        };
        foreach (['topScorers', 'ipponMasters', 'unbeaten'] as $k) {
            $stats[$k] = array_map(fn ($r) => ['person' => $face($r['person'])] + $r, $stats[$k]);
        }
        foreach ($stats['highlights'] as $k => $h) {
            $stats['highlights'][$k]['A'] = $face($h['A']);
            $stats['highlights'][$k]['B'] = $face($h['B']);
        }
        $host = $event->host();

        // Places won, per entry, from the podiums the Results tab shows.
        $placed = [];
        $podiumPeople = [];
        foreach ($b['cats'] as $c) {
            foreach ($c['podium'] as $p) {
                if (! empty($p['reg']) && $p['place'] >= 1 && $p['place'] <= 3) {
                    $placed[(int) $p['reg']][] = (int) $p['place'];
                    $podiumPeople[] = $p;
                }
            }
        }
        $clubPages = $this->results->clubPagesFor($event, $placed, $viewer);
        $clubs = collect($clubPages)->flatten(1)->reject(fn ($c) => $c['continued'])->values()->all();

        // ── Medal table by nation ─────────────────────────────────────────
        $regs = ClubEventRegistration::where('event_id', $event->id)->where('role', 'participant')
            ->with(['user:id,nationality,gender,birthdate', 'representingTenant:id,country'])->get();
        $nations = [];
        foreach ($regs as $r) {
            $iso = Flag::iso2($r->countryCode());
            $key = $iso ? strtolower($iso) : '—';
            $nations[$key] ??= ['iso' => $iso ? strtolower($iso) : null, 'name' => $iso ? (Countries::name($iso) ?: strtoupper($iso)) : 'Not stated',
                'athletes' => [], 'medals' => [1 => 0, 2 => 0, 3 => 0]];
            $nations[$key]['athletes'][$r->user_id ?: 'r'.$r->id] = true;
        }
        foreach ($podiumPeople as $p) {
            $key = $p['iso'] ?? '—';
            if (isset($nations[$key])) {
                $nations[$key]['medals'][$p['place']]++;
            }
        }
        $nations = collect($nations)->map(fn ($n) => ['athletes' => count($n['athletes'])] + $n)
            ->sortBy(fn ($n) => [$n['iso'] === null, -$n['medals'][1], -$n['medals'][2], -$n['medals'][3], -$n['athletes'], $n['name']])
            ->values()->all();
        foreach ($nations as $i => &$n) {
            $n['rank'] = array_sum($n['medals']) > 0 && $n['iso'] ? $i + 1 : null;
        }
        unset($n);

        // ── Participation ─────────────────────────────────────────────────
        $people = $regs->unique(fn ($r) => $r->user_id ?: 'r'.$r->id);
        $gender = ['Male' => 0, 'Female' => 0];
        foreach ($people as $r) {
            $g = strtolower((string) $r->user?->gender);
            $gender[in_array($g, ['f', 'female'], true) ? 'Female' : 'Male']++;
        }
        $ages = [];
        foreach ($b['cats'] as $c) {
            $age = $this->analytics->ageGroup($c['label']);
            $g = preg_match('/\bfemale\b/i', $c['label']) ? 'F' : 'M';
            $ages[$age][$g] = ($ages[$age][$g] ?? 0) + $c['athletes'];
        }
        uksort($ages, fn ($x, $y) => $this->analytics->ageRank($x) <=> $this->analytics->ageRank($y));

        // ── Club performance: bouts, wins, points, medals ─────────────────
        $clubOfReg = [];
        foreach ($b['matches'] as $m) {
            foreach (['A', 'B'] as $k) {
                if (! empty($m[$k]['reg'])) {
                    $clubOfReg[$m[$k]['reg']] = (string) ($m[$k]['club'] ?? '');
                }
            }
        }
        $perf = [];
        foreach ($clubs as $c) {
            $perf[Str::lower($c['name'])] = ['name' => $c['name'], 'logo' => $c['logo'], 'athletes' => $c['count'], 'medals' => $c['medals'],
                'bouts' => 0, 'wins' => 0, 'for' => 0, 'against' => 0, 'none' => $c['none']];
        }
        foreach ($stats['entries'] as $reg => $e) {
            $key = Str::lower($clubOfReg[$reg] ?? '');
            if ($key === '' || ! isset($perf[$key])) {
                continue;
            }
            $perf[$key]['bouts'] += $e['bouts'];
            $perf[$key]['wins'] += $e['wins'];
            $perf[$key]['for'] += $e['points'];
            $perf[$key]['against'] += $e['conceded'];
        }
        $perf = collect($perf)->reject(fn ($p) => $p['none'])
            ->sortBy(fn ($p) => [-$p['medals'][1], -$p['medals'][2], -$p['medals'][3], -$p['wins'], $p['name']])->values()->all();

        // ── Pages of the front matter's tables ───────────────────────────
        $medalTables = [
            ['kind' => 'clubs', 'title' => 'Medal table — clubs', 'rows' => array_values(array_filter($clubs, fn ($c) => ! $c['none']))],
            ['kind' => 'nations', 'title' => 'Medal table — nations', 'rows' => $nations],
        ];

        return [
            'title' => (string) ($event->title ?: $fe->name),
            'host' => (string) ($host->name ?? ''),
            'hostLogo' => ($logo = $host->logo) ? $this->pdf->dataUri($logo, $viewer, original: true, maxPx: 320) : null,
            'date' => $event->date ? \App\Support\Cldr::dayMonth($event->date).' '.$event->date->format('Y') : '',
            'coverImage' => $this->cover->imageDataUri($event),
            'cover' => $cover,
            'team' => $cover['team'] ?? [],
            'medalPages' => $this->tablePages($medalTables),
            'clubPages' => $clubPages,
            'clubs' => $clubs,
            'nations' => $nations,
            'divisionPages' => $this->divisionPages($cover['groups'] ?? []),
            'stats' => $stats,
            'gender' => $gender,
            'ages' => $ages,
            'athletes' => $people->count(),
            'perfPages' => array_chunk($perf, self::PERF_ROWS) ?: [[]],
            'podiums' => count(array_filter($b['cats'], fn ($c) => $c['podium'] !== [])),
            'divisionCount' => count($divisions),
            'flagCss' => 'file://'.public_path('images/flags/flag-icons.css'),
            'generatedAt' => now($event->zone())->format('j M Y, H:i'),
            'silhouette' => 'file://'.public_path(is_file(public_path('images/avatars/male.jpg')) ? 'images/avatars/male.jpg' : 'images/avatars/male.svg'),
        ];
    }

    /**
     * The report's own copy of the brackets (owner, 2026-10-18: "the flags
     * are not the nationality of the players … I need to see their weights").
     * Every competitor the boards draw — bracket cards and podium alike:
     *  · the flag taken NOW by the event's rule (ClubEventRegistration::countryCode),
     *    not the copy each bout stored when it was drawn — that copy is only
     *    re-taken when the event's flag switch changes (FlagSource::set), so a
     *    nationality filled in afterwards leaves it stale;
     *  · the weight on the line under the name ("24 kg · Club"), the line both
     *    the card and the podium print. The board has no weight line of its own.
     * Only this copy changes — nothing is written, the live boards are untouched.
     */
    private function asPrinted(ClubEvent $event, array $divisions): array
    {
        $regs = ClubEventRegistration::where('event_id', $event->id)
            ->with(['user:id,nationality', 'representingTenant:id,country'])->get()->keyBy('id');
        $kg = fn ($w) => $w === null ? null : rtrim(rtrim(number_format((float) $w, 2, '.', ''), '0'), '.').' kg';

        $walk = function (array $node) use (&$walk, $regs, $kg): array {
            if (isset($node['competitor_id'], $node['name']) && ($r = $regs->get((int) $node['competitor_id']))) {
                $iso = Flag::iso2($r->countryCode());
                if ($iso) {
                    $node['country'] = strtoupper($iso);
                }
                if ($w = $kg($r->weight)) {
                    $club = trim((string) ($node['club'] ?? ''));
                    $node['club'] = $club !== '' ? $w.' · '.$club : $w;
                }

                return $node;
            }
            foreach ($node as $k => $v) {
                if (is_array($v)) {
                    $node[$k] = $walk($v);
                }
            }

            return $node;
        };

        return array_map($walk, $divisions);
    }

    /** Medal tables laid onto pages: a table never starts without room for a few rows. */
    private function tablePages(array $tables): array
    {
        $pages = [[]];
        $left = self::BODY_MM;
        foreach ($tables as $t) {
            $rows = $t['rows'];
            $continued = false;
            while ($rows !== [] || ! $continued) {
                if ($left < self::TABLE_HEAD_MM + 3 * self::TABLE_ROW_MM && $pages[array_key_last($pages)] !== []) {
                    $pages[] = [];
                    $left = self::BODY_MM;
                }
                $fit = max(1, (int) floor(($left - self::TABLE_HEAD_MM) / self::TABLE_ROW_MM));
                $chunk = array_splice($rows, 0, $fit);
                $pages[array_key_last($pages)][] = $t + ['chunk' => $chunk, 'continued' => $continued];
                $left -= self::TABLE_HEAD_MM + count($chunk) * self::TABLE_ROW_MM;
                $continued = true;
            }
        }

        return array_values(array_filter($pages));
    }

    /** The division list, two columns to a page, never past its foot. */
    private function divisionPages(array $groups): array
    {
        // One long group fills one column and leaves the other empty: halve it.
        if (count($groups) === 1 && count($groups[0]['rows']) > 10) {
            $half = (int) ceil(count($groups[0]['rows']) / 2);
            $groups = [array_merge($groups[0], ['rows' => array_slice($groups[0]['rows'], 0, $half)]),
                array_merge($groups[0], ['rows' => array_slice($groups[0]['rows'], $half)])];
        }
        $pages = [[]];
        $left = 2 * 200;    // two columns of ~200 mm
        foreach ($groups as $g) {
            $cost = 12 + count($g['rows']) * 7;
            if ($cost > $left && $pages[array_key_last($pages)] !== []) {
                $pages[] = [];
                $left = 2 * 200;
            }
            $pages[array_key_last($pages)][] = $g;
            $left -= $cost;
        }

        return array_values(array_filter($pages)) ?: [[]];
    }

    /** @return list<array{title: string, page: int, icon: string, color: string, part: string}> */
    private function toc(array $s): array
    {
        $rows = [
            ['info', 'The competition', 'bi-info-circle-fill', '#16213d', 'Front'],
            ['officials', 'Competition officials', 'bi-person-badge-fill', '#16213d', 'Front'],
            ['medals', 'Medal tables — clubs & nations', 'bi-trophy-fill', '#d8b25f', 'Front'],
            ['clubs', 'Clubs & their athletes', 'bi-shield-fill', '#d8b25f', 'Front'],
            ['divisions', 'Divisions & weight classes', 'bi-list-ol', '#d8b25f', 'Front'],
            ['brackets', 'Brackets & winners', 'bi-diagram-3-fill', '#2a78d6', 'Draw'],
            ['numbers', 'The championship in numbers', 'bi-bar-chart-fill', '#eb6834', 'Analytics'],
            ['won', 'How bouts are won', 'bi-bullseye', '#eb6834', 'Analytics'],
            ['time', 'Time — when points land', 'bi-stopwatch-fill', '#eb6834', 'Analytics'],
            ['discipline', 'Discipline & the corners', 'bi-flag-fill', '#eb6834', 'Analytics'],
            ['stars', 'Stars of the championship', 'bi-star-fill', '#eb6834', 'Analytics'],
            ['performance', 'Club performance', 'bi-graph-up-arrow', '#eb6834', 'Analytics'],
            ['signoff', 'Approval', 'bi-pen-fill', '#16213d', 'Close'],
        ];

        return array_values(array_map(fn ($r) => ['title' => $r[1], 'page' => $s[$r[0]], 'icon' => $r[2], 'color' => $r[3], 'part' => $r[4], 'key' => $r[0]],
            array_filter($rows, fn ($r) => ($s[$r[0]] ?? null) !== null)));
    }
}
