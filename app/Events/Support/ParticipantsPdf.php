<?php

namespace App\Events\Support;

use App\Events\Models\ClubEvent;
use App\Members\Models\User;
use Illuminate\Support\Collection;

/**
 * The entry list, printed — A4 portrait, one block per division, in the same
 * "Fight night" navy and gold as the draw (BracketPdf). Three editions:
 *
 *   · `list`    — the ENTRY LIST to share or pin up: face, name, flag, club,
 *                 age, weight and belt. The weight was added at the owner's
 *                 request (2026-10-13), knowing it puts every athlete's body
 *                 weight — children's included — on a sheet meant to be
 *                 handed round; it is why only staff can print it.
 *   · `checkin` — the DESK SHEET: a tick box per entry, the weight on file
 *                 (or a box to write it in) and whether it is paid. Payment
 *                 and body weight are staff facts (PublicEventController::
 *                 readinessFor states the rule), so the controller lets only
 *                 the organiser and the event's officials print it.
 *   · `all`     — everybody ONCE, A–Z, no division sections: the entry
 *                 list's columns on one long list (owner, 2026-10-13).
 *   · `checkin_club` — the desk sheet again, but one block per CLUB, each
 *                 athlete once under it with the divisions they entered, so a
 *                 club's coach can be checked in as a team (owner, 2026-10-14).
 *                 Staff-only for the same reason as `checkin`.
 *
 * Every edition carries the gender (owner, 2026-10-13).
 *
 * ── One source for who is on it ───────────────────────────────────────────
 * The people come from the SAME rows the participants page draws
 * (PublicEvent::readerPayload → participants.rows): the club resolved the way
 * the board resolves it, the face only where its owner allowed one, the
 * division names in the reader's language. Grouped here by division, so an
 * athlete in two divisions is on both — as the organiser asked (2026-10-13).
 *
 * The browser, the image inlining and the re-authorisation of every picture
 * are BracketPdf's, called rather than copied (Shared Stays Shared).
 *
 * ── Pages ─────────────────────────────────────────────────────────────────
 * Laid out here, in millimetres, because every page carries its own band and
 * "n / total" — which print CSS cannot number. Rows are a FIXED height in the
 * view (names shrink to fit, never wrap or cut), so the arithmetic below is
 * what Chromium actually prints. A division too long for what is left runs on
 * to the next page under the same header, marked as continued.
 */
class ParticipantsPdf
{
    /** `all` is everybody once, A–Z, with no division sections (owner, 2026-10-13). */
    public const KINDS = ['list', 'checkin', 'all', 'checkin_club'];

    /** Body height a page offers under its band and above its footer, in mm. */
    private const BODY_MM = 228;

    /** A division's header: its gap above, the title bar and the column heads. */
    private const HEAD_MM = 20;

    /** One row, per edition — the view sets these exact heights. */
    private const ROW_MM = ['list' => 12.5, 'checkin' => 11, 'all' => 12.5, 'checkin_club' => 11];

    public function __construct(private BracketPdf $pdf) {}

    public static function available(): bool
    {
        return (bool) config('events.participants_pdf.enabled') && BracketPdf::available();
    }

    /**
     * The divisions in the poster's own order, each with its people.
     *
     * @return list<array{id: ?int, name: string, gender: ?string, people: list<array<string, mixed>>}>
     */
    public function divisions(ClubEvent $event, User $viewer, string $kind): array
    {
        $poster = app(PublicEvent::class)->readerPayload($event) ?? [];
        $rows = (array) ($poster['participants']['rows'] ?? []);
        if ($rows === []) {
            return [];
        }

        // The desk's two facts, per ENTRY: one person in two divisions paid
        // (or not) for each of them separately.
        $entries = $this->entries($event);

        $logos = [];
        $person = function (array $r, ?int $division) use ($kind, $entries, $viewer, &$logos) {
            $logo = $r['club_logo'] ?? null;
            if ($logo && ! array_key_exists($logo, $logos)) {
                $logos[$logo] = $this->pdf->dataUri((string) $logo, $viewer, original: true, maxPx: 96);
            }
            $g = strtolower((string) ($r['gender'] ?? ''));
            $female = in_array($g, ['f', 'female'], true);

            $row = [
                'key' => (string) ($r['uuid'] ?? ''),
                'name' => (string) $r['name'],
                'country' => ! empty($r['country']) ? strtolower((string) \App\Support\Flag::iso2($r['country'])) : null,
                'club' => $r['club'] ?? null,
                'club_logo' => $logo ? $logos[$logo] : null,
                'age' => $r['age'] ?? null,
                'stage' => $r['life_stage'] ?? null,
                'belt' => $r['belt'] ?? null,
                // The silhouette's gender (always one), and the column's (blank when unknown).
                'gender' => $female ? 'female' : 'male',
                'sex' => $female ? 'female' : (in_array($g, ['m', 'male'], true) ? 'male' : null),
                'divs' => array_map(fn ($d) => (int) $d['id'], (array) ($r['division_rows'] ?? [])),
            ];

            // The weight is on every edition (owner, 2026-10-13); payment only on the desk's.
            // On the flat list a person is one row whatever they entered: one body,
            // one weight — the one signed at the scale first, else the one declared.
            $mine = $entries->filter(fn ($v, $k) => str_starts_with($k, ($r['uuid'] ?? '').'|'));
            $e = in_array($kind, ['all', 'checkin_club'], true)
                ? ($mine->first(fn ($v) => $v['signed'] && $v['weight'] !== null) ?? $mine->first(fn ($v) => $v['weight'] !== null))
                : $entries->get(($r['uuid'] ?? '').'|'.($division ?? ''));
            $row['weight'] = $e['weight'] ?? null;
            $row['signed'] = (bool) ($e['signed'] ?? false);
            // The face on every edition — the desk matches the athlete in front of it to the row.
            $row['photo'] = ! empty($r['photo']) ? $this->pdf->dataUri((string) $r['photo'], $viewer) : null;
            if ($kind === 'checkin') {
                $row['paid'] = (bool) ($e['paid'] ?? false);
            } elseif ($kind === 'checkin_club') {
                // One row for every entry they hold: ticked only when ALL are paid.
                $row['paid'] = $mine->isNotEmpty() && $mine->every(fn ($v) => $v['paid']);
                $row['divnames'] = array_values(array_map(fn ($d) => (string) $d['name'], (array) ($r['division_rows'] ?? [])));
            }

            return $row;
        };

        // The flat list: everybody once, in the payload's own A–Z order.
        if ($kind === 'all') {
            return [['id' => null, 'name' => __('events::events.participants_pdf_all_heading'), 'gender' => null,
                'people' => array_map(fn ($r) => $person($r, null), $rows)]];
        }

        // Each club's athletes under it, clubs A–Z, the clubless last.
        if ($kind === 'checkin_club') {
            $clubs = [];
            foreach ($rows as $r) {
                $p = $person($r, null);
                $clubs[(string) ($p['club'] ?? '')][] = $p;
            }
            uksort($clubs, fn ($a, $b) => ($a === '') <=> ($b === '') ?: strnatcasecmp($a, $b));
            // Inside a club: males A–Z, then females A–Z (owner, 2026-10-14).
            foreach ($clubs as &$people) {
                usort($people, fn ($a, $b) => ($a['gender'] === 'female') <=> ($b['gender'] === 'female')
                    ?: strnatcasecmp($a['name'], $b['name']));
            }
            unset($people);

            return array_map(fn ($name, $people) => [
                'id' => null,
                'name' => $name !== '' ? $name : __('events::events.participants_pdf_no_club'),
                'gender' => null,
                'logo' => $people[0]['club_logo'] ?? null,
                'people' => $people,
            ], array_keys($clubs), array_values($clubs));
        }

        // Everybody, filed under each division they entered.
        $byDivision = [];
        $names = [];
        $loose = [];
        foreach ($rows as $r) {
            $divs = (array) ($r['division_rows'] ?? []);
            if ($divs === []) {
                $loose[] = $person($r, null);

                continue;
            }
            foreach ($divs as $d) {
                $byDivision[(int) $d['id']][] = $person($r, (int) $d['id']);
                $names[(int) $d['id']] = (string) $d['name'];
            }
        }

        // The poster's order (its sections), then any division it does not list.
        $order = [];
        foreach ((array) ($poster['division_sections'] ?? []) as $sec) {
            foreach ((array) ($sec['items_ids'] ?? []) as $id) {
                $order[(int) $id] = true;
            }
        }
        foreach (array_keys($byDivision) as $id) {
            $order[$id] = true;
        }

        $out = [];
        foreach (array_keys($order) as $id) {
            if (! isset($byDivision[$id])) {
                continue;
            }
            $out[] = ['id' => $id, 'name' => $names[$id], 'gender' => $this->pdf->genderOf($names[$id]), 'people' => $byDivision[$id]];
        }
        if ($loose) {
            $out[] = ['id' => null, 'name' => __('events::events.participants_pdf_no_division'), 'gender' => null, 'people' => $loose];
        }

        return $out;
    }

    /**
     * Payment and weight per entry, keyed "user-uuid|division-id".
     *
     * @return Collection<string, array{paid: bool, weight: ?string, signed: bool}>
     */
    private function entries(ClubEvent $event): Collection
    {
        return $event->participantRegistrations()
            ->with('user:id,uuid')
            ->get(['id', 'user_id', 'category_id', 'paid', 'weight', 'weighed_in_at', 'weighed_in_by'])
            ->filter(fn ($r) => $r->user !== null)
            ->mapWithKeys(fn ($r) => [$r->user->uuid.'|'.($r->category_id ?? '') => [
                'paid' => (bool) $r->paid,
                // Trailing zeros go: a scale reading of 62.00 is written 62.
                'weight' => $r->weight === null ? null
                    : rtrim(rtrim(number_format((float) $r->weight, 2, '.', ''), '0'), '.'),
                // Signed off at the scale, not just declared on the form.
                'signed' => $r->weighed_in_by !== null || $r->weighed_in_at !== null,
            ]]);
    }

    /**
     * The division blocks laid onto pages.
     *
     * @param  list<array<string, mixed>>  $divisions
     * @return list<list<array<string, mixed>>>
     */
    public function paginate(array $divisions, string $kind): array
    {
        $row = self::ROW_MM[$kind];
        $pages = [[]];
        $left = self::BODY_MM;

        foreach ($divisions as $d) {
            $people = $d['people'];
            $n = 0;
            $continued = false;
            while ($people !== []) {
                // Not even a header and two rows left: start a fresh page.
                if ($left < self::HEAD_MM + 2 * $row) {
                    $pages[] = [];
                    $left = self::BODY_MM;
                }
                $fit = max(1, (int) floor(($left - self::HEAD_MM) / $row));
                $chunk = array_splice($people, 0, $fit);
                $pages[array_key_last($pages)][] = [
                    'id' => $d['id'], 'name' => $d['name'], 'gender' => $d['gender'], 'logo' => $d['logo'] ?? null,
                    'total' => count($d['people']), 'continued' => $continued,
                    'start' => $n, 'people' => $chunk,
                ];
                $n += count($chunk);
                $left -= self::HEAD_MM + count($chunk) * $row;
                $continued = true;
            }
        }

        return array_values(array_filter($pages));
    }

    /**
     * The print page, as HTML — separate from rendering so it can be tested
     * and looked at without a browser.
     *
     * @param  list<array<string, mixed>>  $divisions
     */
    public function html(ClubEvent $event, User $viewer, array $divisions, string $kind): string
    {
        $people = collect($divisions)->flatMap(fn ($d) => $d['people']);
        $host = $event->host();

        return view('events::participants-pdf', [
            'kind' => $kind,
            'title' => (string) $event->title,
            'host' => (string) ($host->name ?? ''),
            'hostLogo' => ($logo = $host->logo) ? $this->pdf->dataUri($logo, $viewer, original: true, maxPx: 320) : null,
            'date' => $event->date ? \App\Support\Cldr::dayMonth($event->date).' '.$event->date->format('Y') : '',
            'pages' => $this->paginate($divisions, $kind),
            'totals' => [
                'athletes' => $people->pluck('key')->unique()->count(),
                'clubs' => $people->pluck('club')->filter()->unique()->count(),
                'divisions' => $people->pluck('divs')->flatten()->unique()->count(),
            ],
            'flagCss' => 'file://'.public_path('images/flags/flag-icons.css'),
            'placeholders' => [
                'male' => $this->placeholder('male'),
                'female' => $this->placeholder('female'),
            ],
        ])->render();
    }

    public function render(string $html): string
    {
        return $this->pdf->render($html);
    }

    /** The gendered silhouette, never initials (A Person's Photo Has One Home). */
    private function placeholder(string $gender): string
    {
        $file = is_file(public_path("images/avatars/{$gender}.jpg")) ? "images/avatars/{$gender}.jpg" : "images/avatars/{$gender}.svg";

        return 'file://'.public_path($file);
    }
}
