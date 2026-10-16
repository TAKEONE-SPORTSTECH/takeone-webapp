<?php

namespace App\Events\Support;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Members\Models\User;
use App\Sports\Combat\BeltRank;
use App\Support\Countries;
use App\Sports\Combat\SportRegistry;
use App\Translation\Translations;
use Illuminate\Support\Facades\App;

/**
 * The registration form — a BLANK entry form for one event, printed A4 on two
 * pages, for the organiser to hand out and collect at a desk (owner,
 * 2026-10-14; design source: drafts/The Gost Team Tournament Reg. Form.doc).
 *
 * Bilingual on purpose: every label is English AND Arabic side by side,
 * whatever language the organiser reads the site in. The words live in
 * `events::regform` (en + ar only) and are read with an explicit locale.
 *
 * The same "Fight night" navy and gold as the draw and entry-list PDFs, so the
 * three read as one set on the organiser's desk. What is particular to the
 * event comes from the event: its title (and its Arabic translation where one
 * exists), dates, host and logo, entry fee, and — from the SPORT, never a
 * branch here — the disciplines a competitor ticks (Karate: Kata · Kumite;
 * `entryDisciplines()`, empty for a sport with no such choice).
 *
 * The browser and the image inlining are BracketPdf's, called rather than
 * copied (Shared Stays Shared). It carries no personal data at all — it is
 * the empty form — but it is a headless browser per request, so the route
 * holds it to the event's staff and the draw PDF's limiter.
 */
class RegistrationFormPdf
{
    public function __construct(private BracketPdf $pdf) {}

    public static function available(): bool
    {
        return (bool) config('events.registration_form_pdf.enabled') && BracketPdf::available();
    }

    /**
     * The form's HTML — blank, or with `$fill` (from fill()) written into it:
     * the same pages, the same boxes, the answers already in them.
     */
    public function html(ClubEvent $event, User $viewer, ?array $fill = null): string
    {
        $host = $event->host();
        $sport = app(SportRegistry::class)->get($event->sport);

        $titleEn = Translations::of($event, 'en')->get('title', (string) $event->title);
        $titleAr = Translations::of($event, 'ar')->get('title', (string) $event->title);

        $disciplines = [];
        if ($sport && method_exists($sport, 'entryDisciplines')) {
            $ns = 'sport-'.$sport->key().'::messages.discipline_';
            foreach ($sport->entryDisciplines() as $d) {
                $k = preg_replace('/[^a-z0-9_]/', '', (string) ($d['key'] ?? ''));
                if ($k === '') {
                    continue;
                }
                $disciplines[] = [
                    'en' => __($ns.$k, [], 'en'),
                    'ar' => __($ns.$k, [], 'ar'),
                    'sub' => __($ns.$k.'_sub', [], 'en'),
                    'sub_ar' => __($ns.$k.'_sub', [], 'ar'),
                ];
            }
        }

        $fee = (float) ($event->participant_fee_amount ?? 0) > 0
            ? rtrim(rtrim(number_format((float) $event->participant_fee_amount, 3, '.', ''), '0'), '.').' '.strtoupper((string) ($event->fee_currency ?: ''))
            : null;

        return view('events::registration-form-pdf', [
            'titleEn' => $titleEn,
            // A title nobody translated comes back in English — the Arabic half
            // then says what the form is rather than repeating the English.
            'titleAr' => $titleAr !== $titleEn ? $titleAr : null,
            'sportEn' => $sport ? $this->inLocale('en', fn () => $sport->label()) : null,
            'sportAr' => $sport ? $this->inLocale('ar', fn () => $sport->label()) : null,
            'dateEn' => $this->dates($event, 'en'),
            // Arabic-Indic digits on the Arabic half, as the original form wrote them.
            'dateAr' => strtr($this->dates($event, 'ar'), ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']),
            'host' => (string) ($host->name ?? ''),
            'hostLogo' => ($logo = $host->logo) ? $this->pdf->dataUri($logo, $viewer, original: true, maxPx: 320) : null,
            'disciplines' => $disciplines,
            'fee' => $fee,
            'rankHint' => $sport && method_exists($sport, 'beltLadder') && $sport->beltLadder() !== [],
            'fill' => $fill,
        ])->render();
    }

    /**
     * One entrant's answers, for a form that only needs signing (owner,
     * 2026-10-14) — printed from the organiser's sheet on the entry list.
     *
     * ── One source, one rule ──────────────────────────────────────────────
     * Everything is read through EntryEditor::present(), the organiser's own
     * editor read, so the form says exactly what the editor would show this
     * viewer and nothing more. In particular the telephone number arrives only
     * when present() hands it over (EntryEditor::contactFor — the viewer runs
     * the event AND holds authority over that person), and the emergency
     * contact rides on that SAME decision rather than a second rule. Without
     * it both are left as lines to write on.
     *
     * What the platform does not hold — the CPR / passport number, the style —
     * stays blank for the hand. Nothing is guessed: a blank beats an invented
     * answer (CLAUDE.md, "Who fills the form decides what it demands").
     *
     * The picture goes through BracketPdf::dataUri, which re-authorises it for
     * the viewer, exactly as on the entry-list PDF.
     *
     * @return array<string, mixed>
     */
    public function fill(ClubEvent $event, ClubEventRegistration $registration, User $viewer): array
    {
        $p = app(EntryEditor::class)->present($event, $registration, $viewer);
        $person = $registration->user;

        // Every division they hold here — one person, one form.
        $theirs = ClubEventRegistration::query()
            ->where('event_id', $event->id)
            ->where('user_id', $registration->user_id)
            ->with('category:id,name')
            ->get();
        $divisions = $theirs->map(fn ($r) => $r->category?->name)->filter()->unique()->values()->all();

        // A name, split the way the form asks for it: first · middle · last,
        // into the row of the script it is written in.
        $name = trim((string) ($p['name'] ?? ''));
        $tokens = $name === '' ? [] : preg_split('/\s+/u', $name);
        $parts = match (count($tokens)) {
            0 => ['', '', ''],
            1 => [$tokens[0], '', ''],
            default => [$tokens[0], implode(' ', array_slice($tokens, 1, -1)), $tokens[count($tokens) - 1]],
        };
        $arabic = (bool) preg_match('/\p{Arabic}/u', $name);

        $dob = ! empty($p['birthdate']) ? \Illuminate\Support\Carbon::parse($p['birthdate']) : null;
        $applied = $theirs->min(fn ($r) => $r->registered_at ?? $r->created_at);

        $belt = BeltRank::chip($p['belt_colour'] ?? null, $p['belt_grade'] ?? null);

        // The person's own profile picture (owner, 2026-10-14) — their face has
        // one home (CLAUDE.md); the entry's desk photo only when there is none.
        $photo = $person?->profile_picture ?: $registration->photo;

        // The emergency contact rides on present()'s contact decision.
        $mayContact = array_key_exists('email', $p);
        $emergency = null;
        if ($mayContact && is_array($list = $person?->emergency_contacts) && ! empty($list[0]) && is_array($list[0])) {
            $c = $list[0];
            $emergency = [
                'name' => (string) ($c['name'] ?? ''),
                'relation' => ucfirst((string) ($c['relationship'] ?? '')),
                'phone' => trim(($c['phone_code'] ?? '').' '.($c['phone'] ?? '')),
            ];
        }

        $gender = strtolower((string) ($p['gender'] ?? ''));

        return [
            'name_en' => $arabic ? null : $parts,
            'name_ar' => $arabic ? $parts : null,
            'dob' => $dob ? [$dob->format('d'), $dob->format('m'), $dob->format('Y')] : null,
            'gender' => in_array($gender, ['male', 'female'], true) ? $gender : null,
            'nationality' => ! empty($p['nationality']) ? (Countries::name($p['nationality']) ?? $p['nationality']) : null,
            'applied' => $applied ? [$applied->format('d'), $applied->format('m'), $applied->format('Y')] : null,
            'rank' => $belt ? ['label' => $belt['label'], 'colour' => $belt['colour'], 'pale' => $belt['pale']] : null,
            'club' => $p['club']['name'] ?? null,
            'club_logo' => ! empty($p['club']['logo']) ? $this->pdf->dataUri((string) $p['club']['logo'], $viewer, original: true, maxPx: 160) : null,
            'height' => $person?->height_cm ? (string) (int) $person->height_cm : null,
            'weight' => $p['weight'] !== null ? rtrim(rtrim(number_format((float) $p['weight'], 1, '.', ''), '0'), '.') : null,
            'mobile' => $p['mobile_display'] ?? null,
            'emergency' => $emergency,
            'divisions' => $divisions,
            'photo' => $photo ? $this->pdf->dataUri(file_url($photo), $viewer) : null,
            'paid' => $theirs->isNotEmpty() && $theirs->every(fn ($r) => (bool) $r->paid),
            'weighed' => ($p['weighed_in'] ?? false) && $p['weight'] !== null,
            'entry_no' => (string) $registration->id,
        ];
    }

    /**
     * Every participant's form, already filled in, in one document — one form per
     * PERSON (all their divisions on it), A–Z, each on its own pages (owner,
     * 2026-10-15: "in the Download button, pre-filled, and another empty").
     * Null when nobody is entered.
     */
    public function htmlForAll(ClubEvent $event, User $viewer): ?string
    {
        $entries = ClubEventRegistration::where('event_id', $event->id)->where('role', 'participant')
            ->with('user')->orderBy('id')->get()
            ->filter(fn ($r) => $r->user !== null)->unique('user_id')
            ->sortBy(fn ($r) => mb_strtolower((string) ($r->user->full_name ?: $r->user->name)))->values();
        if ($entries->isEmpty()) {
            return null;
        }

        $head = null;
        $bodies = [];
        foreach ($entries as $reg) {
            $html = $this->html($event, $viewer, $this->fill($event, $reg, $viewer));
            $open = strpos($html, '<body>');
            $close = strrpos($html, '</body>');
            if ($open === false || $close === false) {
                continue;
            }
            $head ??= substr($html, 0, $open);
            $bodies[] = substr($html, $open + 6, $close - $open - 6);
        }

        return $head === null ? null : $head.'<body>'.implode("\n", $bodies).'</body></html>';
    }

    public function render(string $html): string
    {
        return $this->pdf->render($html);
    }

    /** "16 – 17 October 2026", day first (Design Rule #9), in either language. */
    private function dates(ClubEvent $event, string $locale): string
    {
        if (! $event->date) {
            return '';
        }

        $from = $event->date->copy()->locale($locale);
        $to = $event->end_date && ! $event->end_date->isSameDay($event->date) ? $event->end_date->copy()->locale($locale) : null;

        if (! $to) {
            return $from->translatedFormat('j F Y');
        }

        return $from->isSameMonth($to)
            ? $from->translatedFormat('j').' – '.$to->translatedFormat('j F Y')
            : $from->translatedFormat('j F').' – '.$to->translatedFormat('j F Y');
    }

    /** A sport's own label in a given language, the app's locale restored after. */
    private function inLocale(string $locale, \Closure $read): string
    {
        $was = App::getLocale();
        App::setLocale($locale);

        try {
            return (string) $read();
        } finally {
            App::setLocale($was);
        }
    }
}
