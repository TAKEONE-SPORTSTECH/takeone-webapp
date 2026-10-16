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
 * Every bracket sheet is LANDSCAPE, however short the bracket — flipping
 * between portrait and landscape pages made the PDF awkward to move through
 * (owner's rule, 2026-10-10; it replaced the 2026-10-06 "landscape from three
 * rounds" rule). The cover, contents and teams pages stay portrait.
 */
class BracketPdf
{
    /** Rounds from which a bracket counts as long, and is printed landscape. */
    public const LANDSCAPE_FROM_ROUNDS = 3;

    /** @var array<string, string>  the pictures of the page being built, once each */
    private array $media = [];

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
        return true;
    }

    /**
     * The print page, as HTML. Separate from render() so it can be tested and
     * looked at without a browser.
     *
     * @param  list<array<string, mixed>>  $divisions
     */
    public function html(ClubEvent $event, User $viewer, array $divisions, string $layout, string $third, bool $withCover = false): string
    {
        $this->media = [];
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
            // The organiser's own mark on every page head, inlined and
            // re-authorised like every other picture on the sheet.
            'media' => $this->media,
            'hostLogo' => ($logo = $event->host()->logo) ? $this->dataUri($logo, $viewer, original: true, maxPx: 320) : null,
            'date' => $event->date ? \App\Support\Cldr::dayMonth($event->date).' '.$event->date->format('Y') : '',
            'pages' => array_map(fn (array $d) => [
                'id' => (int) $d['id'],
                'name' => (string) $d['name'],
                // The sheet's title: the name, then its ages and weights from the
                // division's own columns (owner, 2026-10-16) — a group called
                // "Group A" says nothing about who is in it on its own.
                'title' => GroupName::isGenerated((string) $d['name']) && str_contains((string) $d['name'], ' : ')
                    ? (string) $d['name']   // "Group A : 5-6y, 17-23kg" already says it
                    : collect([(string) $d['name'], $this->rangeLine((array) ($d['range'] ?? []))])->filter()->implode(' · '),
                'entrants' => (int) ($d['entrants'] ?? 0),
                // The division's own gender first; the name only for one that has none.
                'gender' => ($d['range']['gender'] ?? null) ?: $this->genderOf((string) $d['name']),
                'landscape' => $this->landscape($d),
                'podium' => $podium = $this->finishedPodium($d),
                // The gold "not yet confirmed" dot (unpaid / not weighed in) only
                // means something while the division is still to be fought.
                'provisional' => $podium === null && $this->hasProvisional($d),
            ], $divisions),
            'finished' => $this->finished($event, $divisions),
            'dataUrl' => 'data:application/json;base64,'.base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)),
            'layout' => $layout === 'split' ? 'split' : 'linear',
            'third' => $third === 'shared' ? 'shared' : 'match',
            'appCss' => $this->builtCss(),
            'cover' => $withCover ? $this->cover($event, $divisions, $viewer) : null,
            'flagCss' => 'file://'.public_path('images/flags/flag-icons.css'),
        ])->render();
    }

    /** "5–6 yrs · 17–23 kg" from a division's range; '' when it narrows neither. */
    private function rangeLine(array $r): string
    {
        $kg = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
        $a1 = $r['min_age'] ?? null;
        $a2 = $r['max_age'] ?? null;
        $w1 = isset($r['min_weight']) && (float) $r['min_weight'] > 0 ? $r['min_weight'] : null;
        $w2 = isset($r['max_weight']) && (float) $r['max_weight'] > 0 && (float) $r['max_weight'] < 200 ? $r['max_weight'] : null;

        $bits = [];
        if ($a1 !== null || $a2 !== null) {
            $bits[] = match (true) {
                $a1 !== null && $a2 !== null => ($a1 === $a2 ? $a1 : $a1.'–'.$a2).' yrs',
                $a1 !== null => $a1.'+ yrs',
                default => 'up to '.$a2.' yrs',
            };
        }
        if ($w1 !== null || $w2 !== null) {
            $bits[] = match (true) {
                $w1 !== null && $w2 !== null => $kg($w1).'–'.$kg($w2).' kg',
                $w1 !== null => '+'.$kg($w1).' kg',
                default => '−'.$kg($w2).' kg',
            };
        }

        return implode(' · ', $bits);
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

        // Once it is over, a competition day reads as it actually ran: from the
        // first bout's start to the last bout's finish, as the mats logged them.
        if ($this->finished($event, $divisions)) {
            $ran = $this->actualHours($event);
            foreach ((array) ($p['phases'] ?? []) as $ph) {
                $iso = (string) ($ph['date'] ?? '');
                if (empty($ph['segments']) || ! isset($ran[$iso], $days[$iso])) {
                    continue;
                }
                // The day's planned segments (play, breaks) give way to one row,
                // in the same place among that date's other rows.
                $label = (string) ($ph['label'] ?? '');
                $rows = [];
                $placed = false;
                foreach ($days[$iso]['rows'] as $r) {
                    if ($r['label'] !== $label) {
                        $rows[] = $r;
                    } elseif (! $placed) {
                        $rows[] = ['time' => $ran[$iso], 'label' => $label,
                            'sub' => __('events::events.draw_print_actual_hours'), 'break' => false];
                        $placed = true;
                    }
                }
                $days[$iso]['rows'] = $rows;
            }
            $schedule = array_values($days);
        }

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
                $rows[] = ['id' => (int) $id, 'name' => $name, 'n' => $counts[(int) $id], 'gender' => $this->genderOf($name)];
                $seen[(int) $id] = true;
            }
            if ($rows) {
                $groups[] = ['heading' => (string) ($sec['heading'] ?? ''), 'rows' => $rows];
            }
        }
        // A drawn division no heading names still gets its line.
        $loose = collect($divisions)->reject(fn ($d) => isset($seen[(int) $d['id']]))
            ->map(fn ($d) => ['id' => (int) $d['id'], 'name' => (string) $d['name'], 'n' => (int) ($d['entrants'] ?? 0), 'gender' => $this->genderOf((string) $d['name'])])
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
            'clubs' => $this->clubs($event, $divisions, $viewer),
            'groups' => $groups,
            'total' => $counts->sum(),
        ];
    }

    /**
     * The hours each competition day actually ran, keyed by its date in the
     * event's own zone: the first bout clock started after the organiser
     * started the event (so a rehearsal on the mats the day before does not
     * count) to the last bout finished. Byes are not bouts.
     *
     * @return array<string, string>  'Y-m-d' => '10:53 AM – 5:30 PM'
     */
    private function actualHours(ClubEvent $event): array
    {
        $rows = \Illuminate\Support\Facades\DB::table('event_match_events as e')
            ->join('event_matches as m', 'm.id', '=', 'e.match_id')
            ->where('e.event_id', $event->id)
            ->where('m.event_id', $event->id)
            ->where('m.status', 'done')
            ->where(fn ($q) => $q->whereNull('m.won_by_bye')->orWhere('m.won_by_bye', false))
            ->whereIn('e.command', ['start', 'finish', 'commit'])
            ->when($event->started_at, fn ($q) => $q->where('e.occurred_at', '>=', $event->started_at))
            ->get(['e.command', 'e.occurred_at']);

        $zone = $event->zone();
        $days = [];
        foreach ($rows as $r) {
            $at = \Illuminate\Support\Carbon::parse($r->occurred_at, 'UTC')->setTimezone($zone);
            $d = &$days[$at->format('Y-m-d')];
            $d ??= ['from' => null, 'to' => null];
            if ($r->command === 'start') {
                $d['from'] = $d['from'] && $d['from']->lte($at) ? $d['from'] : $at;
            } else {
                $d['to'] = $d['to'] && $d['to']->gte($at) ? $d['to'] : $at;
            }
            unset($d);
        }

        // Cldr::time formats the wall clock it is given, so hand it the local time.
        $clock = fn ($t) => \App\Support\Cldr::time(\Illuminate\Support\Carbon::parse($t->format('Y-m-d H:i:s')));

        return collect($days)
            ->filter(fn ($d) => $d['from'] && $d['to'] && $d['to']->gt($d['from']))
            ->map(fn ($d) => $clock($d['from']).' – '.$clock($d['to']))
            ->all();
    }

    /** Rows of athletes (three to a row) a teams page holds, and what a club header costs. */
    private const CLUB_LINES_PER_PAGE = 23;

    private const CLUB_HEAD_COST = 3;

    private const CLUB_COLUMNS = 3;

    /**
     * The teams that took part — each club with its crest, how many it brought,
     * the medals it won, and every athlete who entered under it with their
     * divisions. Paginated: a big club runs on to the next page under the same
     * header, marked as continued.
     *
     * A club is resolved the way the board resolves it (EntryClub: a tenant
     * first, then the club the organiser wrote down), so the name on this page
     * is the name under the athlete on every sheet. Ranked like a medal table
     * once any medal is won; by size before that.
     *
     * @return list<list<array<string, mixed>>>  pages of club blocks
     */
    private function clubs(ClubEvent $event, array $divisions, User $viewer): array
    {
        $regs = $event->participantRegistrations()
            ->with(['user:id,name,full_name', 'category:id,name', 'representingTenant'])
            ->get();
        if ($regs->isEmpty()) {
            return [];
        }

        // Places won, per entry, from the podiums the sheets print.
        $placed = [];
        foreach ($divisions as $d) {
            if ($this->finishedPodium($d) === null) {
                continue;
            }
            foreach ((array) ($d['podium'] ?? []) as $w) {
                $place = (int) ($w['place'] ?? 0);
                if ($place >= 1 && $place <= 3 && ! empty($w['competitor_id'])) {
                    $placed[(int) $w['competitor_id']][] = $place;
                }
            }
        }

        $written = EntryClub::forMany($event, $regs->pluck('id'));
        $clubs = [];
        $none = __('events::events.draw_print_no_club');

        foreach ($regs as $r) {
            $tenant = $r->competingClub();
            $club = $tenant
                ? ['name' => (string) $tenant->club_name, 'logo' => $tenant->logo ? file_url($tenant->logo) : null]
                : ($written[$r->id] ?? null);
            $name = trim((string) ($club['name'] ?? '')) ?: $none;
            $key = Str::lower($name);

            $clubs[$key] ??= ['name' => $name, 'logo' => $club['logo'] ?? null, 'none' => $name === $none, 'people' => [], 'medals' => [1 => 0, 2 => 0, 3 => 0]];
            $clubs[$key]['logo'] ??= $club['logo'] ?? null;

            // One line per PERSON, however many divisions they entered.
            $who = $r->user_id ? 'u'.$r->user_id : 'r'.$r->id;
            $person = &$clubs[$key]['people'][$who];
            $person ??= ['name' => (string) ($r->user?->full_name ?: $r->user?->name ?: __('events::events.athlete')), 'divisions' => [], 'medals' => []];
            if ($r->category?->name) {
                // id => name: each division links to its own sheet in the PDF.
                $person['divisions'][(int) $r->category->id] = (string) $r->category->name;
            }
            foreach ($placed[$r->id] ?? [] as $place) {
                $person['medals'][] = $place;
                $clubs[$key]['medals'][$place]++;
            }
            unset($person);
        }

        $rows = array_map(function (array $c) use ($viewer) {
            $people = array_values($c['people']);
            foreach ($people as &$p) {
                sort($p['medals']);
                $p['divisions'] = array_map(fn ($id, $name) => ['id' => $id, 'name' => $name], array_keys($p['divisions']), $p['divisions']);
            }
            unset($p);
            // Medallists first, best first; then by name.
            usort($people, fn ($a, $b) => [($a['medals'][0] ?? 9), $a['name']] <=> [($b['medals'][0] ?? 9), $b['name']]);

            return [
                'name' => $c['name'],
                'none' => $c['none'],
                'logo' => $c['logo'] ? $this->dataUri((string) $c['logo'], $viewer, original: true, maxPx: 240) : null,
                'count' => count($people),
                'medals' => $c['medals'],
                'people' => $people,
            ];
        }, array_values($clubs));

        $anyMedal = (bool) array_filter($rows, fn ($c) => array_sum($c['medals']) > 0);
        usort($rows, function ($a, $b) use ($anyMedal) {
            if ($a['none'] !== $b['none']) {
                return $a['none'] <=> $b['none'];   // "no club" always last
            }
            $ka = $anyMedal ? [-$a['medals'][1], -$a['medals'][2], -$a['medals'][3], -$a['count']] : [-$a['count']];
            $kb = $anyMedal ? [-$b['medals'][1], -$b['medals'][2], -$b['medals'][3], -$b['count']] : [-$b['count']];

            return [$ka, $a['name']] <=> [$kb, $b['name']];
        });
        foreach ($rows as $i => &$c) {
            $c['rank'] = $anyMedal && ! $c['none'] ? $i + 1 : null;
        }
        unset($c);

        // Lay the blocks onto pages; a club too long for what is left runs on.
        $pages = [[]];
        $left = self::CLUB_LINES_PER_PAGE;
        foreach ($rows as $c) {
            $people = $c['people'];
            $continued = false;
            while (true) {
                if ($left < self::CLUB_HEAD_COST + 2) {
                    $pages[] = [];
                    $left = self::CLUB_LINES_PER_PAGE;
                }
                $fit = ($left - self::CLUB_HEAD_COST) * self::CLUB_COLUMNS;
                $chunk = array_splice($people, 0, $fit);
                $pages[count($pages) - 1][] = ['continued' => $continued, 'people' => $chunk] + $c;
                $left -= self::CLUB_HEAD_COST + (int) ceil(count($chunk) / self::CLUB_COLUMNS);
                if ($people === []) {
                    break;
                }
                $continued = true;
                $left = 0;
            }
        }

        return $pages;
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
                'logo' => ! empty($r['club_logo']) ? $this->dataUri((string) $r['club_logo'], $viewer, original: true, maxPx: 240) : null,
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
    /**
     * The podium for a division whose every bout is decided — first, second,
     * then third (one or two bronzes, whatever the draw settled). NULL while
     * any bout is still to fight: half a podium on paper reads as a result.
     *
     * @return list<array{place:int, name:string, club:?string, country:?string, photo:?string, placeholder:?string}>|null
     */
    public function finishedPodium(array $division): ?array
    {
        $bouts = 0;
        foreach ((array) ($division['rounds'] ?? []) as $round) {
            foreach ((array) ($round['matches'] ?? []) as $m) {
                $bouts++;
                if (($m['status'] ?? null) !== 'done') {
                    return null;
                }
            }
        }

        $podium = array_values(array_filter((array) ($division['podium'] ?? []),
            fn ($p) => in_array((int) ($p['place'] ?? 0), [1, 2, 3], true) && ($p['name'] ?? '') !== ''));
        if ($bouts === 0 || ! array_filter($podium, fn ($p) => (int) $p['place'] === 1)) {
            return null;
        }
        usort($podium, fn ($a, $b) => (int) $a['place'] <=> (int) $b['place']);

        $silhouette = $this->genderOf((string) ($division['name'] ?? '')) === 'female' ? 'female' : 'male';
        $fallback = is_file(public_path("images/avatars/{$silhouette}.jpg"))
            ? "images/avatars/{$silhouette}.jpg" : "images/avatars/{$silhouette}.svg";

        return array_map(fn ($p) => [
            'place' => (int) $p['place'],
            // First and last name only: a podium card holds one line (owner,
            // 2026-10-10). The full name is on the sheet above and on the
            // teams page.
            'name' => $this->shortName((string) $p['name']),
            'club' => ! empty($p['club']) ? (string) $p['club'] : null,
            'country' => ! empty($p['country']) ? (string) $p['country'] : null,
            // Already re-authorised and inlined by inlinePhotos(); never a URL.
            'club_logo' => $this->resolveMedia($p['club_logo'] ?? null),
            'photo' => $this->resolveMedia($p['photo'] ?? null),
            'placeholder' => 'file://'.public_path($fallback),
        ], $podium);
    }

    /**
     * The competition is over: the organiser closed it, its end has passed, or
     * every division on the paper has its podium. Then the paper says RESULTS.
     */
    public function finished(ClubEvent $event, array $divisions): bool
    {
        if ($event->status === 'completed' || $event->hasEnded()) {
            return true;
        }

        return $divisions !== [] && ! array_filter($divisions, fn ($d) => $this->finishedPodium($d) === null);
    }

    private function hasProvisional(array $division): bool
    {
        foreach ((array) ($division['rounds'] ?? []) as $round) {
            foreach ((array) ($round['matches'] ?? []) as $m) {
                if (! empty($m['a']['provisional']) || ! empty($m['b']['provisional'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /** "Sayed Ali Reda Sharif" → "Sayed Sharif"; one or two words stay as they are. */
    private function shortName(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return count($words) > 2 ? $words[0].' '.end($words) : trim($name);
    }

    public function genderOf(string $name): ?string
    {
        return match (true) {
            (bool) preg_match('/\b(Female|Women)\b/i', $name) => 'female',
            (bool) preg_match('/\b(Male|Men)\b/i', $name) => 'male',
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
        $refs = [];

        /* Each picture is inlined ONCE, into $this->media, and the payload
           carries 'ref:<n>' (the board resolves it from window.BracketMedia).
           Inlined per corner, six club crests became 256 copies and 5.7 MB of
           JSON, copied again at every step of the render — past PHP's memory
           limit on the live site (2026-10-10). */
        $ref = function (string $key, callable $make) use (&$refs): ?string {
            if (! array_key_exists($key, $refs)) {
                $uri = $make();
                $refs[$key] = $uri ? (string) count($this->media) : null;
                if ($uri) {
                    $this->media[$refs[$key]] = $uri;
                }
            }

            return $refs[$key] === null ? null : 'ref:'.$refs[$key];
        };

        $walk = function (array $n) use (&$walk, $ref, $viewer): array {
            foreach ($n as $k => $v) {
                if (is_array($v)) {
                    $n[$k] = $walk($v);
                } elseif ($k === 'photo' && is_string($v) && $v !== '') {
                    $n[$k] = $ref('photo:'.$v, fn () => $this->dataUri($v, $viewer));
                } elseif ($k === 'club_logo' && is_string($v) && $v !== '') {
                    // A crest, read whole: thumbnails are JPEG and lose its transparency.
                    $n[$k] = $ref('logo:'.$v, fn () => $this->dataUri($v, $viewer, original: true, maxPx: 96));
                }
            }

            return $n;
        };

        return $walk($node);
    }

    /** A picture the payload refers to ('ref:<n>'), as its data URI. */
    private function resolveMedia(mixed $v): ?string
    {
        return is_string($v) && str_starts_with($v, 'ref:') ? ($this->media[substr($v, 4)] ?? null) : null;
    }

    /**
     * @param  bool  $original  read the uploaded file itself — for a LOGO. The
     *                          thumbnails are JPEG, which has no alpha, so a
     *                          transparent PNG would print on a white square.
     */
    public function dataUri(string $url, User $viewer, bool $original = false, int $maxPx = 0): ?string
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
        $read = $original ? $path : ((Thumbnails::knows($size) ? Thumbnails::for($path, $size) : null) ?? $path);

        $disk = Storage::disk('local');

        if (! $disk->exists($read)) {
            return null;
        }

        $bytes = $disk->get($read);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);

        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            return null;
        }

        if ($maxPx > 0) {
            [$bytes, $mime] = $this->shrink($bytes, $mime, $maxPx);
        }

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }

    /**
     * A logo at the size it is printed, transparency kept. The same crest is
     * inlined once per corner on every sheet — a 400px original repeated a few
     * hundred times ran the page past PHP's memory limit (2026-10-10).
     *
     * @return array{0: string, 1: string}  bytes and mime; the input untouched when it is already small or GD cannot read it
     */
    private function shrink(string $bytes, string $mime, int $maxPx): array
    {
        $img = function_exists('imagecreatefromstring') ? @imagecreatefromstring($bytes) : false;
        if (! $img) {
            return [$bytes, $mime];
        }
        $w = imagesx($img);
        $h = imagesy($img);
        if (max($w, $h) <= $maxPx) {
            imagedestroy($img);

            return [$bytes, $mime];
        }
        $scale = $maxPx / max($w, $h);
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $out = imagecreatetruecolor($nw, $nh);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);

        ob_start();
        imagepng($out, null, 9);
        $png = (string) ob_get_clean();
        imagedestroy($img);
        imagedestroy($out);

        return $png !== '' ? [$png, 'image/png'] : [$bytes, $mime];
    }
}
