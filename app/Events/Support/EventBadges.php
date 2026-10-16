<?php

namespace App\Events\Support;

use Ahl\Certificates\CertificateService;
use Ahl\Certificates\Models\Certificate;
use Ahl\Certificates\Models\CertificateTemplate;
use App\Events\Models\ClubEvent;
use App\Events\Models\EventOfficial;
use App\Members\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The officials' badges of an event — the second thing the printing engine makes
 * (Ahl\Certificates, format `badge`: A6, four to an A4 sheet).
 *
 * A badge is not a certificate: nothing is drafted, approved or stored per person. It is
 * drawn from the event's officials as they stand, whenever it is looked at or printed,
 * so appointing somebody gives them a badge and removing them takes it away — their QR
 * code then verifies nothing (the token lived on the appointment, which is gone).
 *
 * Shares everything else with the event's certificates by extending them: who may run
 * it (EventAccess), whose design is in force (this event's own, or the host's default),
 * where its files live, its branding. Only what it issues, and to whom, is its own. It
 * answers to its own alias, `event_badges`, and claims no certificate (handles() is false).
 */
class EventBadges extends EventCertificates implements \App\Certificates\Contracts\PrintsPages
{
    public const ALIAS = 'event_badges';

    public const TYPE = 'official_badge';

    public function alias(): string
    {
        return self::ALIAS;
    }

    /** Never the owner of a certificate record: those all belong to EventCertificates. */
    public function handles(Model $context): bool
    {
        return false;
    }

    public function types(): array
    {
        return [static::TYPE];
    }

    public function placeholderKeys(): array
    {
        return ['name', 'role', 'club', 'event', 'nationality'];
    }

    /** A badge's {tokens} are the person's, whichever design (not a certificate's). */
    public function typePlaceholders(string $type): array
    {
        return $this->placeholderKeys();
    }

    public function placeholders(Certificate $certificate, string $locale): array
    {
        return [];
    }

    public function previewDetails(string $type, Model $context): array
    {
        return [];
    }

    public function settings(Model $context): array
    {
        return [];
    }

    public function saveSettings(Model $context, array $input): array
    {
        return [];
    }

    public function eligible(Model $context): array
    {
        return ['officials' => $context instanceof ClubEvent ? $this->officials($context)->count() : 0];
    }

    public function generate(Model $context, ?User $by): array
    {
        return ['created' => 0, 'pending' => 0];
    }

    public function describe(Certificate $certificate, string $locale): string
    {
        return '';
    }

    /* ── the badges themselves ─────────────────────────────────────── */

    /**
     * The event's officials, ONE PER PERSON (owner, 2026-10-15): somebody holding two
     * jobs gets one badge naming both, carried by their first appointment.
     *
     * @return Collection<int, EventOfficial>
     */
    public function officials(ClubEvent $event): Collection
    {
        return EventOfficial::where('event_id', $event->id)->whereNotNull('user_id')->with('user')->orderBy('id')->get()
            ->filter(fn (EventOfficial $o) => $o->user !== null)->unique('user_id')->values();
    }

    /** Every job this official's person holds at the event, in the badge's words ("Referee 1 · Scorekeeper"). */
    public function rolesOf(EventOfficial $official, string $locale): string
    {
        return EventOfficial::where('event_id', $official->event_id)->where('user_id', $official->user_id)->orderBy('id')->get()
            ->map(fn (EventOfficial $o) => $this->roleLabel($o, $locale))->filter()->unique()->implode(' · ')
            ?: $this->roleLabel($official, $locale);
    }

    /** The event's own page — what every badge's QR opens (owner, 2026-10-15). */
    public function eventPageUrl(ClubEvent $event): string
    {
        return route('me.events.show', ['event' => $event->uuid]);
    }

    /** A person's nationality as a badge prints it: the country's name. */
    protected function nationalityOf(?\App\Members\Models\User $user): ?string
    {
        $code = trim((string) ($user?->nationality ?? ''));

        return $code !== '' ? (\App\Support\Countries::name($code) ?: $code) : null;
    }

    public function template(ClubEvent $event): CertificateTemplate
    {
        return app(CertificateService::class)->templateFor(static::TYPE, $event);
    }

    /** The random code behind the badge's QR, made the first time the badge is drawn. */
    public function token(EventOfficial $official): string
    {
        if (! $official->badge_token) {
            // Written past the model: being handed a badge is not a change to the appointment,
            // so it must not move updated_at (which the print-shop file's signature reads).
            $token = Str::random(32);
            \Illuminate\Support\Facades\DB::table('event_officials')->where('id', $official->id)->whereNull('badge_token')->update(['badge_token' => $token]);
            $official->setRawAttributes(['badge_token' => \Illuminate\Support\Facades\DB::table('event_officials')->where('id', $official->id)->value('badge_token') ?: $token] + $official->getAttributes(), true);
        }

        return (string) $official->badge_token;
    }

    public function verifyUrl(EventOfficial $official): string
    {
        return route('badges.verify', ['token' => $this->token($official)]);
    }

    /** The job as the badge says it: a fixed role's word, or the organiser's own ("Referee 1"). */
    public function roleLabel(EventOfficial $official, string $locale): string
    {
        if ($official->role === EventOfficial::ROLE_OTHER) {
            return trim((string) $official->role_label) ?: __('personal.personal_event_officials_role_other', [], $locale);
        }

        // A platform role's word, else the sport's own name for a job on the mat
        // ("Tatami Manager", "Judge 1 (Fukushin)") — never the raw key.
        $key = 'personal.personal_event_officials_role_'.$official->role;
        if (trans()->has($key, $locale)) {
            return __($key, [], $locale);
        }
        $names = $official->event ? rescue(fn () => app(OfficialRoles::class)->labels($official->event), [], false) : [];

        return (string) ($names[$official->role] ?? \Illuminate\Support\Str::headline((string) $official->role));
    }

    /**
     * What one badge prints. The face: the photo taken for THIS appointment, otherwise
     * the person's profile picture — public or not. The owner's decision (2026-10-11): a
     * badge is an identity card the organiser prints, and is only ever reached by the
     * event's managers; the public QR page (BadgeVerifyController) draws no face.
     */
    public function fields(EventOfficial $official, string $locale, bool $withQr = true): array
    {
        $user = $official->user;
        $photo = $this->face($official->photo);
        if (! $photo && $user?->profile_picture) {
            $photo = $this->face($user->profile_picture);
        }

        return [
            'name' => (string) ($user?->full_name ?: $user?->name ?: ''),
            // Every job they hold at the event, not only this appointment's.
            'role' => $this->rolesOf($official, $locale),
            'club' => $official->club()['name'] ?? null,
            'event' => $official->event ? $this->headline($official->event) : '',
            'nationality' => $this->nationalityOf($user),
            'photo' => $photo,
            // The QR opens the event's page (owner, 2026-10-15), not a check of the badge.
            'qr' => $withQr && $official->event ? $this->eventPageUrl($official->event) : null,
            'certificate_no' => strtoupper(substr($this->token($official), 0, 8)),
        ];
    }

    /** A sample badge for the design room: the first official, or a made-up one when there is none yet. */
    public function sampleFields(ClubEvent $event, string $locale): array
    {
        $first = $this->officials($event)->first();
        if ($first) {
            return $this->fields($first, $locale);
        }

        return [
            'name' => __('certificates::certificates.sample_name', [], $locale),
            'role' => __('personal.personal_event_officials_role_jury', [], $locale),
            'club' => EventHost::of($event)->name ?: null,
            'event' => $this->headline($event),
            'nationality' => null,
            'photo' => null,
            'qr' => $this->eventPageUrl($event),
            'certificate_no' => 'SAMPLE',
        ];
    }

    /** Draw one badge — a PNG for a screen ($dpi), the PDF otherwise. */
    public function render(EventOfficial $official, string $locale, ?int $dpi = null, string $purpose = CertificateService::PURPOSE_DOWNLOAD): string
    {
        $event = $official->event;

        return app(CertificateService::class)->renderDocument($this->template($event), $locale, $this->fields($official, $locale), $event, $purpose, $dpi);
    }

    /** Every official's badge, four to an A4 sheet with cut lines — the file a print shop is sent. */
    public function sheets(ClubEvent $event, string $locale, ?callable $progress = null): string
    {
        $service = app(CertificateService::class);
        $template = $this->template($event);
        $pages = $this->officials($event)->map(fn (EventOfficial $o) => fn () => $service->renderDocument($template, $locale, $this->fields($o, $locale), $event))->all();

        return $service->bundlePages($pages, $progress);
    }

    /** What the badges in force depend on: who the officials are, and the design. For a bundle's name. */
    public function signature(ClubEvent $event, string $locale): string
    {
        $o = $this->officials($event);

        return substr(sha1($locale.'|'.$o->map(fn ($x) => $x->id.'@'.$x->updated_at.'@'.$x->user?->updated_at)->implode(',').'|'.$this->template($event)->updated_at), 0, 16);
    }

    public function printCount(Model $context, string $type): int
    {
        return $context instanceof ClubEvent && $type === static::TYPE ? $this->officials($context)->count() : 0;
    }

    public function printSignature(Model $context, string $type): string
    {
        return $context instanceof ClubEvent ? $this->signature($context, $this->locale($context)) : '';
    }

    public function printAll(Model $context, string $type, ?callable $progress = null): string
    {
        return $context instanceof ClubEvent ? $this->sheets($context, $this->locale($context), $progress) : '';
    }

    /**
     * The language the event's badges are printed in: the event's own (the language its
     * organiser wrote it in), whoever is looking — so the hall's badges all match, and
     * the print-shop file is the same file whoever asks for it.
     */
    public function locale(ClubEvent $event): string
    {
        return \Ahl\Certificates\Certificates::locale(in_array($event->source_locale, \Ahl\Certificates\Certificates::locales(), true) ? $event->source_locale : null);
    }

    /** A face for print: cut to 3:4 to fill its box, at most 480 × 640, as a data URI. */
    protected function face(?string $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }
        $bytes = rescue(fn () => Storage::disk('local')->exists($path) ? Storage::disk('local')->get($path) : null, null, false);
        $im = $bytes ? @imagecreatefromstring($bytes) : false;
        if (! $im instanceof \GdImage) {
            return null;
        }
        [$w, $h] = [imagesx($im), imagesy($im)];
        [$cw, $ch] = $w / $h > 0.75 ? [(int) round($h * 0.75), $h] : [$w, (int) round($w / 0.75)];
        $out = imagecreatetruecolor(480, 640);
        imagecopyresampled($out, $im, 0, 0, (int) (($w - $cw) / 2), (int) (($h - $ch) / 2), 480, 640, $cw, $ch);
        imagedestroy($im);
        ob_start();
        imagejpeg($out, null, 86);
        $jpg = (string) ob_get_clean();
        imagedestroy($out);

        return 'data:image/jpeg;base64,'.base64_encode($jpg);
    }
}
