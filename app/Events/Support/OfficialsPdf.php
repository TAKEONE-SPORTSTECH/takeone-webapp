<?php

namespace App\Events\Support;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventOfficial;
use App\Members\Models\User;
use Illuminate\Support\Collection;

/**
 * The event's officials, printed (owner, 2026-10-15) — the participants PDF's
 * page and print engine (ParticipantsPdf, BracketPdf), for the people running
 * the competition. Four editions:
 *
 *   · `by_job`  — one block per job (Referees, Judges, Team managers…); somebody
 *                 with two jobs is under both, as an athlete in two divisions is.
 *   · `all`     — everybody ONCE, A–Z, their jobs on one line.
 *   · `signin`  — the DESK SHEET: everybody once, a tick box and a line to sign.
 *   · `by_club` — one block per club, each official once under it.
 *
 * What it prints is what the officiating sheet shows: face, name, flag, jobs,
 * club, gender. Email, phone and pay are appointment paperwork and never print.
 * A face only where its owner allowed one (or the desk took one for this
 * event), re-authorised for the viewer by BracketPdf::dataUri.
 */
class OfficialsPdf
{
    public const KINDS = ['by_job', 'all', 'signin', 'by_club'];

    private const BODY_MM = 228;

    private const HEAD_MM = 20;

    private const ROW_MM = 12.5;

    public function __construct(private BracketPdf $pdf, private EventBadges $badges) {}

    public static function available(): bool
    {
        return ParticipantsPdf::available();
    }

    /** @return list<array{id: ?int, name: string, gender: ?string, logo?: ?string, people: list<array<string, mixed>>}> */
    public function groups(ClubEvent $event, User $viewer, string $kind): array
    {
        $locale = app()->getLocale();
        $appointed = EventOfficial::where('event_id', $event->id)->with('user')->orderBy('id')->get()->filter(fn ($o) => $o->user !== null);
        if ($appointed->isEmpty()) {
            return [];
        }

        $logos = [];
        $logo = function (?string $url) use (&$logos, $viewer) {
            if (! $url) {
                return null;
            }

            return $logos[$url] ??= $this->pdf->dataUri($url, $viewer, original: true, maxPx: 96);
        };

        // One row per PERSON: their jobs joined, their first appointment's club and face.
        $people = $appointed->groupBy('user_id')->map(function (Collection $jobs) use ($locale, $logo, $viewer) {
            $o = $jobs->first();
            $u = $o->user;
            $club = collect($jobs)->map(fn ($j) => $j->club())->first(fn ($c) => ! empty($c['name']));
            $g = strtolower((string) $u->gender);
            $face = $jobs->pluck('photo')->filter()->first()
                ?: (($u->profile_picture && $u->profile_picture_is_public) ? $u->profile_picture : null);

            return [
                'key' => (string) $u->uuid,
                'name' => (string) ($u->full_name ?: $u->name),
                'country' => $u->nationality ? strtolower((string) \App\Support\Flag::iso2($u->nationality)) : null,
                'nationality' => $u->nationality ? (\App\Support\Countries::name($u->nationality) ?: $u->nationality) : null,
                'jobs' => $jobs->map(fn ($j) => $this->badges->roleLabel($j, $locale))->filter()->unique()->values()->all(),
                'club' => $club['name'] ?? null,
                'club_logo' => $logo($club['logo'] ?? null),
                'gender' => $g === 'female' ? 'female' : 'male',
                'sex' => in_array($g, ['male', 'female'], true) ? $g : null,
                // dataUri reads a /file/ address and re-checks the viewer may see it.
                'photo' => $face ? $this->pdf->dataUri(file_url((string) $face), $viewer) : null,
            ];
        })->sortBy(fn ($p) => mb_strtolower($p['name']))->values();

        if ($kind === 'all' || $kind === 'signin') {
            return [['id' => null, 'name' => __('events::events.officials_pdf_all_heading'), 'gender' => null, 'people' => $people->all()]];
        }

        if ($kind === 'by_club') {
            return $people->groupBy(fn ($p) => (string) ($p['club'] ?? ''))
                ->sortKeysUsing(fn ($a, $b) => ($a === '') <=> ($b === '') ?: strnatcasecmp($a, $b))
                ->map(fn ($list, $club) => ['id' => null, 'name' => $club !== '' ? $club : __('events::events.participants_pdf_no_club'),
                    'gender' => null, 'logo' => $list->first()['club_logo'] ?? null, 'people' => $list->values()->all()])
                ->values()->all();
        }

        // by_job: under every job they hold, the jobs in the order of the most people first.
        $byJob = [];
        foreach ($people as $p) {
            foreach ($p['jobs'] ?: [__('events::events.officials_pdf_no_job')] as $job) {
                $byJob[$job][] = $p;
            }
        }
        uksort($byJob, fn ($a, $b) => count($byJob[$b]) <=> count($byJob[$a]) ?: strnatcasecmp($a, $b));

        return array_map(fn ($job, $list) => ['id' => null, 'name' => $job, 'gender' => null, 'people' => $list], array_keys($byJob), array_values($byJob));
    }

    /** @return list<list<array<string, mixed>>> */
    public function paginate(array $groups): array
    {
        $pages = [[]];
        $left = self::BODY_MM;
        foreach ($groups as $d) {
            $people = $d['people'];
            $n = 0;
            $continued = false;
            while ($people !== []) {
                if ($left < self::HEAD_MM + 2 * self::ROW_MM) {
                    $pages[] = [];
                    $left = self::BODY_MM;
                }
                $fit = max(1, (int) floor(($left - self::HEAD_MM) / self::ROW_MM));
                $chunk = array_splice($people, 0, $fit);
                $pages[array_key_last($pages)][] = [
                    'name' => $d['name'], 'gender' => $d['gender'], 'logo' => $d['logo'] ?? null,
                    'total' => count($d['people']), 'continued' => $continued, 'start' => $n, 'people' => $chunk,
                ];
                $n += count($chunk);
                $left -= self::HEAD_MM + count($chunk) * self::ROW_MM;
                $continued = true;
            }
        }

        return array_values(array_filter($pages));
    }

    public function html(ClubEvent $event, User $viewer, array $groups, string $kind): string
    {
        $people = collect($groups)->flatMap(fn ($d) => $d['people'])->unique('key');
        $host = $event->host();

        return view('events::officials-pdf', [
            'kind' => $kind,
            'title' => (string) $event->title,
            'host' => (string) ($host->name ?? ''),
            'hostLogo' => ($logo = $host->logo) ? $this->pdf->dataUri($logo, $viewer, original: true, maxPx: 320) : null,
            'date' => $event->date ? \App\Support\Cldr::dayMonth($event->date).' '.$event->date->format('Y') : '',
            'pages' => $this->paginate($groups),
            'totals' => [
                'officials' => $people->count(),
                'clubs' => $people->pluck('club')->filter()->unique()->count(),
                'jobs' => $people->pluck('jobs')->flatten()->unique()->count(),
            ],
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

    private function placeholder(string $gender): string
    {
        $file = is_file(public_path("images/avatars/{$gender}.jpg")) ? "images/avatars/{$gender}.jpg" : "images/avatars/{$gender}.svg";

        return 'file://'.public_path($file);
    }
}
