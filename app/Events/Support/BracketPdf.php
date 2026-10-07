<?php

namespace App\Events\Support;

use App\Events\EventTypeRegistry;
use App\Events\Models\ClubEvent;
use App\Members\Models\User;
use App\Support\FileAccess;
use App\Support\Thumbnails;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * The draw, printed — A4, one division per page.
 *
 * ── Why a browser and not a PDF library ───────────────────────────────────
 * The owner's requirement is that the PDF looks EXACTLY like the board on the
 * screen: the cards, the connectors, the names, the colours (minus the podium,
 * which paper leaves out — owner, 2026-10-07). The
 * board is drawn by JavaScript (components/bracket/runtime), measured in the
 * real font and laid out from those measurements. A PDF library would be a
 * second drawing of it, and a second drawing drifts. So the export runs the
 * SAME runtime in headless Chromium and prints what it draws — the PDF is the
 * screen, not a likeness of it.
 *
 * ── How the board gets its data without a session ─────────────────────────
 * Headless Chromium is nobody: it holds no cookie, so it cannot read the
 * member feed. It does not need to. The payload is built HERE, for the
 * requesting user, through the same `bracketView()` the feed calls, and handed
 * to the board as a `data:` URL. Nothing in the page talks to this server, and
 * nothing it renders is more than this user could already see on the board.
 *
 * Faces travel the same way: each photo the payload names is re-checked with
 * FileAccess for THIS user (the same gate `/file/{path}` applies), shrunk to
 * the board's own rendition, and inlined. One that fails the check is dropped,
 * which the board draws as the silhouette it always draws for no picture.
 *
 * ── Orientation ───────────────────────────────────────────────────────────
 * Per division, decided here before layout: a long bracket (three rounds or
 * more — quarter-finals onwards) goes LANDSCAPE, a short one PORTRAIT. The
 * owner's rule, 2026-10-06.
 */
class BracketPdf
{
    /** Rounds from which a bracket counts as long, and is printed landscape. */
    public const LANDSCAPE_FROM_ROUNDS = 3;

    public function __construct(private EventTypeRegistry $registry) {}

    /** Switched on, and the binary is actually there to run. */
    public static function available(): bool
    {
        return (bool) config('events.bracket_pdf.enabled')
            && is_executable((string) config('events.bracket_pdf.binary'));
    }

    /**
     * The divisions this user would see on the board, ready to print.
     *
     * @return list<array<string, mixed>>  only divisions with a draw
     */
    public function divisions(ClubEvent $event, User $viewer, ?int $only = null): array
    {
        $all = $this->registry->for($event)->bracketView($event, $viewer);

        return array_values(array_filter($all, function (array $d) use ($only) {
            if (! empty($d['is_heading'])) {
                return false;
            }
            if ($only !== null && (int) $d['id'] !== $only) {
                return false;
            }

            return $this->rounds($d) > 0;
        }));
    }

    /** Rounds of the ladder, not counting a third-place bout (it is not a round). */
    public function rounds(array $division): int
    {
        return count(array_filter((array) ($division['rounds'] ?? []), fn ($r) => ($r['name'] ?? '') !== 'Third place'));
    }

    public function landscape(array $division): bool
    {
        return $this->rounds($division) >= self::LANDSCAPE_FROM_ROUNDS;
    }

    /**
     * The print page, as HTML. Separate from render() so it can be tested and
     * looked at without a browser.
     *
     * @param  list<array<string, mixed>>  $divisions
     */
    public function html(ClubEvent $event, User $viewer, array $divisions, string $layout, string $third, bool $withCover = false): string
    {
        $divisions = $this->inlinePhotos($divisions, $viewer);

        $payload = [
            'divisions' => $divisions,
            'third_place_match' => BronzeRule::playsOff($event),
            // A printout decides nothing and arranges nothing.
            'can_arrange' => false,
            'can_manage' => false,
            'locked' => false,
        ];

        return view('events::bracket-pdf', [
            'title' => (string) $event->title,
            // One look for every printout, whatever the event's own colour:
            // navy and gold, the bracket board's own (owner picked it 2026-10-07).
            'color' => '#d8b25f',
            'host' => (string) ($event->host()->name ?? ''),
            'date' => $event->date ? \App\Support\Cldr::dayMonth($event->date).' '.$event->date->format('Y') : '',
            'pages' => array_map(fn (array $d) => [
                'id' => (int) $d['id'],
                'name' => (string) $d['name'],
                'entrants' => (int) ($d['entrants'] ?? 0),
                'gender' => $this->genderOf((string) $d['name']),
                'landscape' => $this->landscape($d),
            ], $divisions),
            'dataUrl' => 'data:application/json;base64,'.base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)),
            'layout' => $layout === 'split' ? 'split' : 'linear',
            'third' => $third === 'shared' ? 'shared' : 'match',
            'appCss' => $this->builtCss(),
            'cover' => $withCover ? $this->cover($event, $divisions, $viewer) : null,
            'flagCss' => 'file://'.public_path('images/flags/flag-icons.css'),
        ])->render();
    }

    /**
     * Pages 1 and 2 of a full download: the competition, then its divisions.
     *
     * Page 1 is the organiser, venue, every time frame, the rules it runs under
     * and its officials; page 2 every division with how many are entered. Read
     * from the poster payload, so it says exactly what the event page says. A
     * fact the event does not hold is left out rather than printed blank (the
     * owner's rule, 2026-10-07).
     *
     * @param  list<array<string, mixed>>  $divisions
     * @return array<string, mixed>
     */
    private function cover(ClubEvent $event, array $divisions, User $viewer): array
    {
        // The caller has authorised this viewer (EventAccess::canArrange).
        $p = app(PublicEvent::class)->readerPayload($event) ?? [];
        $sk = fn ($date, string $pattern, string $fallback) => \App\Support\Cldr::skeleton($date, $pattern, $fallback);

        // Every time frame, grouped by the day it falls on — a day with a
        // break reads as its parts.
        $days = [];
        foreach ((array) ($p['phases'] ?? []) as $ph) {
            $iso = (string) ($ph['date'] ?? '');
            if (! isset($days[$iso])) {
                $d = $iso !== '' ? \Illuminate\Support\Carbon::parse($iso) : null;
                $days[$iso] = [
                    'day' => $d ? $sk($d, 'd', 'j') : '',
                    'mon' => $d ? $sk($d, 'MMM', 'M') : '',
                    'wday' => $d ? $sk($d, 'EEEE', 'l') : '',
                    'short' => $d ? $sk($d, 'EEEdMMM', 'D j M') : '',
                    'rows' => [],
                ];
            }
            if (! empty($ph['segments'])) {
                foreach ($ph['segments'] as $seg) {
                    $days[$iso]['rows'][] = ['time' => (string) ($seg['time'] ?? ''), 'label' => (string) ($ph['label'] ?? ''),
                        'sub' => (string) ($seg['label'] ?? ''), 'break' => ($seg['kind'] ?? '') === 'break'];
                }
            } else {
                $days[$iso]['rows'][] = ['time' => (string) ($ph['time'] ?? ''), 'label' => (string) ($ph['label'] ?? ''), 'sub' => '', 'break' => false];
            }
        }
        $schedule = array_values($days);

        // The day itself, as the cover's date block.
        $on = $event->date ? \Illuminate\Support\Carbon::parse($event->date->format('Y-m-d')) : null;
        $when = $on ? ['day' => $sk($on, 'd', 'j'), 'mon' => $sk($on, 'MMM', 'M'), 'year' => $on->format('Y'), 'wday' => $sk($on, 'EEEE', 'l')] : null;

        $formats = collect($divisions)->pluck('format')->filter()->unique()
            ->map(fn ($f) => match ($f) {
                'knockout' => __('events::events.draw_print_format_knockout'),
                'round_robin' => __('events::events.draw_print_format_round_robin'),
                default => null,
            })->filter()->implode(' · ');
        $knockout = collect($divisions)->contains('format', 'knockout');
        $mats = count(EventMats::of($event));

        $rules = array_values(array_filter([
            ['icon' => 'bi-dribbble', 'label' => __('events::events.draw_print_sport'), 'value' => $p['sport_label'] ?? null],
            ['icon' => 'bi-diagram-3-fill bracket-icon', 'label' => __('events::events.draw_print_format'), 'value' => $formats ?: null],
            ['icon' => 'bi-award', 'label' => __('events::events.draw_print_third'),
                'value' => $knockout ? __('events::events.draw_print_third_'.(BronzeRule::playsOff($event) ? 'match' : 'shared')) : null],
            ['icon' => 'bi-grid-3x3-gap', 'label' => __('events::events.draw_print_mats'), 'value' => $mats > 0 ? (string) $mats : null],
        ], fn ($r) => filled($r['value'])));

        $hours = trim(($p['time'] ?? '').(! empty($p['end']) ? ' – '.$p['end'] : ''));
        $facts = array_values(array_filter([
            ['icon' => 'bi-calendar3', 'label' => __('events::events.draw_print_date'),
                'value' => ($p['date'] ?? '').(! empty($p['end_date']) && $p['end_date'] !== ($p['date'] ?? null) ? ' – '.$p['end_date'] : '')],
            ['icon' => 'bi-clock', 'label' => __('events::events.draw_print_hours'), 'value' => $hours],
            ['icon' => 'bi-geo-alt', 'label' => __('events::events.draw_print_venue'), 'value' => $p['location'] ?? null],
            ['icon' => 'bi-building', 'label' => __('events::events.draw_print_organiser'), 'value' => $p['host'] ?? null],
        ], fn ($r) => filled($r['value'])));

        $figures = array_values(array_filter([
            ['icon' => 'bi-people-fill', 'label' => __('events::events.draw_print_athletes'), 'value' => (int) ($p['participants']['count'] ?? 0)],
            ['icon' => 'bi-shield-fill', 'label' => __('events::events.draw_print_clubs'), 'value' => (int) ($p['participants']['clubs'] ?? 0)],
            ['icon' => 'bi-diagram-3-fill bracket-icon', 'label' => __('events::events.draw_print_divisions'), 'value' => count($divisions)],
            ['icon' => 'bi-lightning-charge-fill', 'label' => __('events::events.draw_print_bouts'), 'value' => (int) ($p['stats']['bouts'] ?? 0)],
        ], fn ($r) => $r['value'] > 0));

        // Page 2: the drawn divisions under the poster's own headings.
        $counts = collect($divisions)->mapWithKeys(fn ($d) => [(int) $d['id'] => (int) ($d['entrants'] ?? 0)]);
        $groups = [];
        $seen = [];
        foreach ((array) ($p['division_sections'] ?? []) as $sec) {
            $rows = [];
            foreach ((array) ($sec['items_ids'] ?? []) as $i => $id) {
                if (! $counts->has((int) $id)) {
                    continue;
                }
                $name = (string) ($sec['items'][$i] ?? '');
                $rows[] = ['name' => $name, 'n' => $counts[(int) $id], 'gender' => $this->genderOf($name)];
                $seen[(int) $id] = true;
            }
            if ($rows) {
                $groups[] = ['heading' => (string) ($sec['heading'] ?? ''), 'rows' => $rows];
            }
        }
        // A drawn division no heading names still gets its line.
        $loose = collect($divisions)->reject(fn ($d) => isset($seen[(int) $d['id']]))
            ->map(fn ($d) => ['name' => (string) $d['name'], 'n' => (int) ($d['entrants'] ?? 0), 'gender' => $this->genderOf((string) $d['name'])])
            ->values()->all();
        if ($loose) {
            $groups[] = ['heading' => '', 'rows' => $loose];
        }

        return [
            // "Kyokushin Karate Championship" — sport, style and type, the way the poster reads it.
            'type' => EventClassification::line($p['sport_label'] ?? null, (string) ($p['type'] ?? '')),
            'when' => $when,
            'facts' => $facts,
            'figures' => $figures,
            'schedule' => $schedule,
            'rules' => $rules,
            'requirements' => array_values(array_filter((array) ($p['requirements'] ?? []))),
            'officials' => (array) ($p['officials']['groups'] ?? []),
            'team' => $this->team($event, (array) ($p['officials']['rows'] ?? []), $viewer),
            'groups' => $groups,
            'total' => $counts->sum(),
        ];
    }

    /** Cards that fit on one team page, and what a section heading costs of them. */
    private const TEAM_PER_PAGE = 14;

    private const TEAM_PER_ROW = 2;

    /** A heading takes a row's worth of room. */
    private const TEAM_HEADING_COST = 2;

    /**
     * The people running the event, as pages of cards: the management team
     * (organiser, jury, weigh-in, payments, any job the organiser named) and
     * the match officials (the sport's own mat roles). Face, name, club, job.
     *
     * Drawn from the poster's officials sheet, so the face follows its rule (a
     * photo taken for this event, else a profile picture its owner made
     * public) and is then re-checked for THIS viewer before it is inlined. No
     * face draws the gendered silhouette, never initials.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<list<array{heading: string, cards: list<array<string, mixed>>}>>  pages of sections
     */
    private function team(ClubEvent $event, array $rows, User $viewer): array
    {
        if ($rows === []) {
            return [];
        }

        // Which job words are the platform's (management) and which the sport's.
        $platform = collect(\App\Events\Models\EventOfficial::roles())
            ->map(fn ($r) => __('personal.personal_event_officials_role_'.$r))->all();
        $sport = array_diff(array_values(app(OfficialRoles::class)->labels($event)), $platform);
        $organiser = __('personal.personal_event_officials_role_organiser');

        $card = function (array $r) use ($viewer) {
            $photo = $r['photo'] ? $this->dataUri((string) $r['photo'], $viewer) : null;
            $silhouette = in_array(strtolower((string) ($r['gender'] ?? '')), ['f', 'female'], true) ? 'female' : 'male';
            $fallback = is_file(public_path("images/avatars/{$silhouette}.jpg"))
                ? "images/avatars/{$silhouette}.jpg" : "images/avatars/{$silhouette}.svg";

            return [
                'name' => (string) $r['name'],
                'job' => (string) $r['job'],
                'club' => $r['club'] ? (string) $r['club'] : null,
                'logo' => ! empty($r['club_logo']) ? $this->dataUri((string) $r['club_logo'], $viewer) : null,
                'photo' => $photo,
                'placeholder' => $photo ? null : 'file://'.public_path($fallback),
            ];
        };

        $sections = array_values(array_filter([
            ['heading' => __('events::events.draw_print_management'),
                // The organiser heads the team; everyone else keeps the sheet's order.
                'cards' => collect($rows)->reject(fn ($r) => in_array($r['job'], $sport, true))
                    ->sortBy(fn ($r, $i) => [$r['job'] === $organiser ? 0 : 1, $i])->map($card)->values()->all()],
            ['heading' => __('events::events.draw_print_match_officials'),
                'cards' => array_values(array_map($card, array_filter($rows, fn ($r) => in_array($r['job'], $sport, true))))],
        ], fn ($s) => $s['cards'] !== []));

        // Flowed onto as many pages as it takes; a section that breaks carries
        // its heading onto the next page too.
        $pages = [[]];
        $left = self::TEAM_PER_PAGE;
        foreach ($sections as $sec) {
            $chunk = [];
            foreach ($sec['cards'] as $c) {
                if ($chunk === [] && $left < self::TEAM_HEADING_COST + self::TEAM_PER_ROW) {
                    $pages[] = [];
                    $left = self::TEAM_PER_PAGE;
                }
                if ($chunk === []) {
                    $left -= self::TEAM_HEADING_COST;
                }
                $chunk[] = $c;
                $left--;
                if ($left <= 0) {
                    $pages[array_key_last($pages)][] = ['heading' => $sec['heading'], 'cards' => $chunk];
                    $chunk = [];
                    $pages[] = [];
                    $left = self::TEAM_PER_PAGE;
                }
            }
            if ($chunk !== []) {
                $pages[array_key_last($pages)][] = ['heading' => $sec['heading'], 'cards' => $chunk];
                // A short last row still uses the whole row.
                $left -= (self::TEAM_PER_ROW - count($chunk) % self::TEAM_PER_ROW) % self::TEAM_PER_ROW;
            }
        }

        return array_values(array_filter($pages));
    }

    /**
     * Print the page with headless Chromium and hand back the PDF's bytes.
     *
     * Runs in a throwaway directory of its own (profile and all), removed on
     * the way out whatever happens. The sandbox stays ON: the page is ours,
     * but a browser is the last thing to run unsandboxed on a web server.
     */
    public function render(string $html): string
    {
        $dir = storage_path('app/private/tmp/bracket-pdf/'.Str::uuid());
        File::ensureDirectoryExists($dir, 0750);

        try {
            file_put_contents($dir.'/draw.html', $html);

            $process = new Process([
                (string) config('events.bracket_pdf.binary'),
                '--headless=new',
                '--disable-gpu',
                '--no-first-run',
                '--no-default-browser-check',
                '--disable-extensions',
                '--disable-sync',
                '--hide-scrollbars',
                '--mute-audio',
                '--user-data-dir='.$dir.'/profile',
                '--window-size=1600,1100',
                '--run-all-compositor-stages-before-draw',
                // Virtual time: lets the board fetch its data, wait for the
                // fonts and lay every division out before the page is printed.
                '--virtual-time-budget=25000',
                '--no-pdf-header-footer',
                '--print-to-pdf='.$dir.'/draw.pdf',
                'file://'.$dir.'/draw.html',
            ], $dir, ['HOME' => $dir]);

            $process->setTimeout((int) config('events.bracket_pdf.timeout', 90));
            $process->run();

            $pdf = $dir.'/draw.pdf';

            if (! is_file($pdf) || filesize($pdf) < 100) {
                throw new \RuntimeException('Chromium produced no PDF: '.Str::limit(trim($process->getErrorOutput()), 300));
            }

            return (string) file_get_contents($pdf);
        } finally {
            File::deleteDirectory($dir);
        }
    }

    /** The built Tailwind sheet, read from disk — the page is file://, not the site. */
    private function builtCss(): ?string
    {
        $manifest = public_path('build/manifest.json');

        if (! is_file($manifest)) {
            return null;
        }

        $file = json_decode((string) file_get_contents($manifest), true)['resources/css/app.css']['file'] ?? null;

        return $file ? 'file://'.public_path('build/'.$file) : null;
    }

    /** "♂ 10-11 Men" on the division pill, the way the board's own menu shows it. */
    private function genderOf(string $name): ?string
    {
        return match (true) {
            (bool) preg_match('/\bWomen\b/i', $name) => 'female',
            (bool) preg_match('/\bMen\b/i', $name) => 'male',
            default => null,
        };
    }

    /**
     * Every photo the payload names, re-authorised for this viewer and inlined.
     *
     * Walks the whole structure rather than knowing where photos live — a
     * corner, the podium, the bench — so a face added to the payload later is
     * covered without anyone remembering to come back here.
     */
    private function inlinePhotos(array $node, User $viewer): array
    {
        $cache = [];

        $walk = function (array $n) use (&$walk, &$cache, $viewer): array {
            foreach ($n as $k => $v) {
                if (is_array($v)) {
                    $n[$k] = $walk($v);
                } elseif ($k === 'photo' && is_string($v) && $v !== '') {
                    $n[$k] = $cache[$v] ??= $this->dataUri($v, $viewer);
                }
            }

            return $n;
        };

        return $walk($node);
    }

    private function dataUri(string $url, User $viewer): ?string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';

        if (! str_starts_with($path, '/file/')) {
            return null;    // a remote avatar is not fetched by the server
        }

        $path = ltrim(rawurldecode(substr($path, strlen('/file/'))), '/');

        if ($path === '' || str_contains($path, '..') || ! FileAccess::allows($path, $viewer)) {
            return null;
        }

        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        $size = (string) ($q['s'] ?? 'face');
        $read = (Thumbnails::knows($size) ? Thumbnails::for($path, $size) : null) ?? $path;

        $disk = Storage::disk('local');

        if (! $disk->exists($read)) {
            return null;
        }

        $bytes = $disk->get($read);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);

        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }
}
