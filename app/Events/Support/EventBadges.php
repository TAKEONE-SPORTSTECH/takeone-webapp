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
        return [self::TYPE];
    }

    public function placeholderKeys(): array
    {
        return ['name', 'role', 'club', 'event'];
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

    /** @return Collection<int, EventOfficial> the event's officials, in the order they were appointed */
    public function officials(ClubEvent $event): Collection
    {
        return EventOfficial::where('event_id', $event->id)->whereNotNull('user_id')->with('user')->orderBy('id')->get()
            ->filter(fn (EventOfficial $o) => $o->user !== null)->values();
    }

    public function template(ClubEvent $event): CertificateTemplate
    {
        return app(CertificateService::class)->templateFor(self::TYPE, $event);
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

        return __('personal.personal_event_officials_role_'.$official->role, [], $locale);
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
            'role' => $this->roleLabel($official, $locale),
            'club' => $official->club()['name'] ?? null,
            'event' => $official->event ? $this->headline($official->event) : '',
            'photo' => $photo,
            'qr' => $withQr ? $this->verifyUrl($official) : null,
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
            'photo' => null,
            'qr' => route('badges.verify', ['token' => str_repeat('0', 32)]),
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
        return $context instanceof ClubEvent && $type === self::TYPE ? $this->officials($context)->count() : 0;
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
    private function face(?string $path): ?string
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
