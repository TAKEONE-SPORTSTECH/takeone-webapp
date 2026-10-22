<?php

namespace App\Federations\Services;

use App\Events\Models\ClubEvent;
use App\Events\Support\BracketPdf;
use App\Federations\Models\FederationEvent;
use App\Members\Models\User;

/**
 * The event's Results tab as an A4 PDF (owner, 2026-10-17: "a download button
 * with 3 options — results, bouts, both", "same style as the report"): the
 * podium of every decided division and/or every bout, numbered, mat by mat.
 *
 * Built from FederationEventBracket::of() — the same rows the tab draws, so
 * the paper never says something the screen does not — on the draw report's
 * sheet (events::participants-pdf-style: navy band, gold slab, fixed-height
 * rows the server paginates by) and printed by the draw PDF's headless
 * Chromium. Faces are inlined only when FileAccess lets THIS viewer read them
 * (BracketPdf::dataUri); any other prints as the silhouette.
 */
class FederationResultsPdf
{
    public const KINDS = ['results', 'bouts', 'both'];

    /** Body height a page offers under its band and above its footer, in mm. */
    private const BODY_MM = 228;

    /** A block's header: its gap above, the title bar and the column heads. */
    private const HEAD_MM = 20;

    /** One row, per part — the view sets these exact heights. */
    private const ROW_MM = ['results' => 12.5, 'bouts' => 11, 'pending' => 7];

    public function __construct(private FederationEventBracket $brackets, private BracketPdf $pdf) {}

    public static function available(): bool
    {
        return BracketPdf::available();
    }

    /** NULL when the event has no bracket to print. */
    public function html(FederationEvent $fe, ClubEvent $event, User $viewer, string $kind): ?string
    {
        $b = $this->brackets->of($fe, withEntry: true);
        if (! $b || ($b['cats'] === [] && $b['matches'] === [])) {
            return null;
        }
        // Flag, crest and weight come with each person (FederationEventBracket —
        // the tab draws the same three); the crest is inlined and re-authorised.
        $crests = [];
        $ident = function (array $p) use ($viewer, &$crests): array {
            $url = $p['logo'] ?? null;
            if ($url && ! array_key_exists($url, $crests)) {
                $crests[$url] = $this->pdf->dataUri($url, $viewer, original: true, maxPx: 96);
            }

            return ['flag' => $p['iso'] ?? null, 'logo' => $url ? $crests[$url] : null, 'kg' => $p['kg'] ?? null];
        };

        $faces = [];
        $face = function (?string $url) use ($viewer, &$faces): ?string {
            return $url ? ($faces[$url] ??= $this->pdf->dataUri($url, $viewer)) : null;
        };
        $gender = fn (string $label) => preg_match('/\bfemale\b/i', $label) ? 'female' : (preg_match('/\bmale\b/i', $label) ? 'male' : null);
        $labelOf = collect($b['cats'])->pluck('label', 'key')->all();

        $parts = [];
        if ($kind !== 'bouts') {
            $blocks = collect($b['cats'])->filter(fn ($c) => $c['podium'] !== [])->map(fn ($c) => [
                'type' => 'results', 'name' => $c['label'], 'gender' => $gender($c['label']),
                'rows' => array_map(fn ($p) => ['place' => $p['place'], 'name' => self::shortName($p['name']), 'club' => $p['club'],
                    'photo' => $face($p['photo'])] + $ident($p), $c['podium']),
            ])->values()->all();
            $pending = collect($b['cats'])->filter(fn ($c) => $c['podium'] === [] && $c['athletes'] >= 2)
                ->map(fn ($c) => ['name' => $c['label'], 'gender' => $gender($c['label']), 'athletes' => $c['athletes']])->values()->all();
            if ($pending !== []) {
                $blocks[] = ['type' => 'pending', 'name' => __('federations::messages.results_pdf_pending'), 'gender' => null, 'rows' => $pending];
            }
            $parts[] = ['part' => 'results', 'blocks' => $blocks];
        }
        if ($kind !== 'results') {
            $side = fn (?array $p) => $p ? ['name' => self::shortName($p['name']), 'club' => $p['club'] !== '—' ? $p['club'] : '']
                + $ident($p) : null;
            $blocks = collect($b['matches'])
                ->groupBy(fn ($m) => ($m['day'] ? 'Day '.$m['day'].' · ' : '').($m['court'] ?: 'Mat '.$m['ring']))
                ->sortKeysUsing(fn ($x, $y) => strnatcasecmp((string) $x, (string) $y))
                ->map(fn ($list, $title) => ['type' => 'bouts', 'name' => (string) $title, 'gender' => null,
                    'rows' => $list->sortBy('no')->map(fn ($m) => [
                        'no' => $m['no'], 'division' => $labelOf[$m['cat']] ?? '', 'gender' => $gender($labelOf[$m['cat']] ?? ''), 'round' => $m['round'],
                        'a' => $side($m['A']), 'b' => $side($m['B']), 'w' => $m['w'],
                        'sa' => $m['done'] ? $m['sa'] : null, 'sb' => $m['done'] ? $m['sb'] : null,
                        'how' => $m['done'] ? ($m['by'] ?: __('federations::messages.results_pdf_decided'))
                            : __('federations::messages.results_pdf_'.($m['ready'] ? 'to_fight' : 'waiting')),
                        'done' => $m['done'],
                    ])->values()->all()])
                ->values()->all();
            $parts[] = ['part' => 'bouts', 'blocks' => $blocks];
        }

        $host = $event->host();
        $pages = $this->paginate($parts);

        // First, the clubs (owner, 2026-10-17: "the club, the people from each
        // club and how many gold, silver and bronze each gained") — the draw
        // report's own club pages (BracketPdf::clubPages), from the podiums above.
        $clubPages = [];
        if ($kind !== 'bouts') {
            $placed = [];
            foreach ($b['cats'] as $c) {
                foreach ($c['podium'] as $p) {
                    if (! empty($p['reg']) && $p['place'] >= 1 && $p['place'] <= 3) {
                        $placed[(int) $p['reg']][] = (int) $p['place'];
                    }
                }
            }
            $clubPages = $this->clubPagesFor($event, $placed, $viewer);
        }

        return view('federations::results-pdf', [
            'title' => (string) ($event->title ?: $fe->name),
            'host' => (string) ($host->name ?? ''),
            'hostLogo' => ($logo = $host->logo) ? $this->pdf->dataUri($logo, $viewer, original: true, maxPx: 320) : null,
            'date' => $event->date ? \App\Support\Cldr::dayMonth($event->date).' '.$event->date->format('Y') : '',
            'pages' => $pages,
            'clubPages' => $clubPages,
            // The organiser's own cover page, stretched over the first page.
            'coverImage' => app(FederationReportCover::class)->imageDataUri($event),
            'totals' => [
                'divisions' => collect($b['cats'])->filter(fn ($c) => $c['podium'] !== [])->count(),
                'bouts' => count($b['matches']),
                'done' => collect($b['matches'])->where('done', true)->count(),
            ],
            'flagCss' => 'file://'.public_path('images/flags/flag-icons.css'),
            'placeholder' => 'file://'.public_path(is_file(public_path('images/avatars/male.jpg')) ? 'images/avatars/male.jpg' : 'images/avatars/male.svg'),
        ])->render();
    }

    /** Longer than this (in characters), a name of three or more parts loses one. */
    private const NAME_MAX = 22;

    /** …and in the club lists' narrow column, so every name sits on one line (owner, 2026-10-17). */
    private const CLUB_NAME_MAX = 16;

    /**
     * A long name with one middle name taken out (owner, 2026-10-17: "if the
     * names of the players are too long remove one name from the middle").
     * Exactly one, never more, and never the first or the family name: the
     * middle of three, or the one just before the family name (the
     * grandfather, in Arabic naming) of four or more. Never an ellipsis.
     */
    public static function shortName(string $name, int $max = self::NAME_MAX): string
    {
        $name = trim($name);
        $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (mb_strlen($name) <= $max || count($parts) < 3) {
            return $name;
        }
        array_splice($parts, count($parts) === 3 ? 1 : count($parts) - 2, 1);

        return implode(' ', $parts);
    }

    /**
     * The clubs pages from places already settled — faces, flags and the
     * measured layout — shared with the competition report.
     *
     * @param  array<int, list<int>>  $placed  registration id => places won (1–3)
     */
    public function clubPagesFor(ClubEvent $event, array $placed, User $viewer): array
    {
        return $this->clubLayout($this->withFaces($event, $viewer, $this->pdf->clubPages($event, $placed, $viewer)));
    }

    /** Club page: what a page offers under the band, and a block's fixed parts, in mm. */
    private const CLUB_BODY_MM = 222;

    private const CLUB_HEAD_MM = 28;

    /** One row of three athletes — the view fixes `.ath.with-face` at this height. */
    private const CLUB_ROW_MM = 15;

    private const CLUB_COLUMNS = 3;

    /**
     * Every athlete in the club pages gets their face and flag (owner,
     * 2026-10-17): the face by the platform's competitor rule (CompetitorFace —
     * the desk photograph, else their own published picture) re-authorised for
     * this viewer, the silhouette when there is none; the flag by the event's
     * rule (ClubEventRegistration::countryCode).
     */
    private function withFaces(ClubEvent $event, User $viewer, array $pages): array
    {
        $ids = collect($pages)->flatten(1)->flatMap(fn ($c) => array_column($c['people'], 'reg'))->filter()->unique()->all();
        $regs = \App\Events\Models\ClubEventRegistration::where('event_id', $event->id)->whereIn('id', $ids)
            ->with(['user:id,gender,nationality,profile_picture,profile_picture_is_public,updated_at', 'representingTenant:id,country'])->get()->keyBy('id');
        $silhouette = function (?string $g): string {
            $f = in_array(strtolower((string) $g), ['f', 'female'], true) ? 'female' : 'male';

            return 'file://'.public_path(is_file(public_path("images/avatars/{$f}.jpg")) ? "images/avatars/{$f}.jpg" : "images/avatars/{$f}.svg");
        };

        foreach ($pages as &$page) {
            foreach ($page as &$club) {
                foreach ($club['people'] as &$a) {
                    $r = $regs[$a['reg'] ?? 0] ?? null;
                    $url = $r ? \App\Events\Support\CompetitorFace::for($r) : null;
                    $iso = $r ? \App\Support\Flag::iso2($r->countryCode()) : null;
                    $a['photo'] = $url ? $this->pdf->dataUri($url, $viewer) : null;
                    $a['placeholder'] = $silhouette($r?->user?->gender);
                    $a['flag'] = $iso ? strtolower($iso) : null;
                    $a['name'] = self::shortName($a['name'], self::CLUB_NAME_MAX);
                }
                unset($a);
            }
            unset($club);
        }
        unset($page);

        return $pages;
    }

    /**
     * The club blocks laid onto this report's pages by their real height —
     * every row a fixed height, a clear margin kept above the footer. A club
     * longer than what is left runs on to the next page, marked continued.
     *
     * @param  list<list<array<string, mixed>>>  $pages  BracketPdf::clubPages() (laid out for the draw report)
     * @return list<list<array<string, mixed>>>
     */
    private function clubLayout(array $pages): array
    {
        // Put each club back together first: the draw report split them for its own page.
        $clubs = [];
        foreach (collect($pages)->flatten(1) as $block) {
            if ($block['continued'] && $clubs !== []) {
                array_push($clubs[array_key_last($clubs)]['people'], ...$block['people']);
            } else {
                $clubs[] = $block;
            }
        }

        $out = [[]];
        $left = self::CLUB_BODY_MM;
        foreach ($clubs as $club) {
            $people = $club['people'];
            $continued = false;
            while ($people !== []) {
                if ($left < self::CLUB_HEAD_MM + self::CLUB_ROW_MM && $out[array_key_last($out)] !== []) {
                    $out[] = [];
                    $left = self::CLUB_BODY_MM;
                }
                $rows = max(1, (int) floor(($left - self::CLUB_HEAD_MM) / self::CLUB_ROW_MM));
                $chunk = array_splice($people, 0, $rows * self::CLUB_COLUMNS);
                $out[array_key_last($out)][] = array_merge($club, ['people' => $chunk, 'continued' => $continued]);
                $left -= self::CLUB_HEAD_MM + (int) ceil(count($chunk) / self::CLUB_COLUMNS) * self::CLUB_ROW_MM;
                $continued = true;
            }
        }

        return array_values(array_filter($out));
    }

    public function render(string $html): string
    {
        return $this->pdf->render($html);
    }

    /**
     * Each part starts its own page. A podium is never split across two
     * pages; a mat's bout list runs on with "continued".
     *
     * @return list<array{part: string, first: bool, blocks: list<array<string, mixed>>}>
     */
    private function paginate(array $parts): array
    {
        $pages = [];
        foreach ($parts as $part) {
            $pages[] = ['part' => $part['part'], 'first' => true, 'blocks' => []];
            $left = self::BODY_MM;
            foreach ($part['blocks'] as $d) {
                $row = self::ROW_MM[$d['type']];
                $rows = $d['rows'];
                $n = 0;
                $continued = false;
                while ($rows !== []) {
                    $whole = self::HEAD_MM + count($rows) * $row;
                    $need = $d['type'] === 'results' ? $whole : self::HEAD_MM + min(2, count($rows)) * $row;
                    if ($left < $need && $pages[array_key_last($pages)]['blocks'] !== []) {
                        $pages[] = ['part' => $part['part'], 'first' => false, 'blocks' => []];
                        $left = self::BODY_MM;
                    }
                    $fit = max(1, (int) floor(($left - self::HEAD_MM) / $row));
                    $chunk = array_splice($rows, 0, $fit);
                    $pages[array_key_last($pages)]['blocks'][] = array_merge($d, [
                        'rows' => $chunk, 'total' => count($d['rows']), 'continued' => $continued, 'start' => $n,
                    ]);
                    $n += count($chunk);
                    $left -= self::HEAD_MM + count($chunk) * $row;
                    $continued = true;
                }
            }
        }

        return $pages;
    }
}
