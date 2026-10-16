<?php

namespace Ahl\Certificates;

use Ahl\Certificates\Contracts\BrandsCertificates;
use Ahl\Certificates\Contracts\CertificateNotifier;
use Ahl\Certificates\Contracts\FileStore;
use Ahl\Certificates\Contracts\ImageRenderer;
use Ahl\Certificates\Contracts\OwnsTemplates;
use Ahl\Certificates\Contracts\PdfRenderer;
use Ahl\Certificates\Contracts\Recipient;
use Ahl\Certificates\Enums\CertificateStatus;
use Ahl\Certificates\Events\CertificateApproved;
use Ahl\Certificates\Events\CertificateDrafted;
use Ahl\Certificates\Events\CertificateRevoked;
use Ahl\Certificates\Models\Certificate;
use Ahl\Certificates\Models\CertificateTemplate;
use Ahl\Certificates\Support\Backgrounds;
use Ahl\Certificates\Support\Contrast;
use Ahl\Certificates\Support\DompdfRenderer;
use Ahl\Certificates\Support\Eraser;
use Ahl\Certificates\Support\Formats;
use Ahl\Certificates\Support\Layout;
use Ahl\Certificates\Support\Fonts;
use Ahl\Certificates\Support\Medal;
use Ahl\Certificates\Support\TextShaper;
use BackedEnum;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

/**
 * The certificate workflow: drafts from any source, approval (stores the final PDF and notifies the
 * recipient), revocation, signed download links, rendering and template previews.
 */
class CertificateService
{
    public const PDF_SLOT = 'pdf';

    /** The download is what is stored and shared; print honours a blank-paper template. */
    public const PURPOSE_DOWNLOAD = 'download';

    public const PURPOSE_PRINT = 'print';

    /** While set, renderView() hands back its page as HTML here instead of drawing it (bundle()). */
    private ?array $capture = null;

    public function __construct(
        private FileStore $files,
        private PdfRenderer $pdf,
        private CertificateNotifier $notifier,
    ) {}

    /**
     * Create a draft. Approved straight away when require_approval is off.
     *
     * @param  array{achievement?:string|null, grade?:string|BackedEnum|null, title?:string|null, context?:Model|null, source?:string|BackedEnum|null, source_id?:int|null, details?:array|null, issued_on?:string|null}  $data
     */
    public function createDraft(Model $recipient, string|BackedEnum $type, array $data = [], ?Authenticatable $by = null): Certificate
    {
        $type = Certificates::value($type);
        if (! Certificates::has('types', $type)) {
            throw ValidationException::withMessages(['type' => __('certificates::certificates.errors.unknown_type')]);
        }
        $context = $data['context'] ?? null;
        $template = $this->templateFor($type, $context);
        $locale = $this->localeOf($recipient);

        // Two drafts made at the same moment can both read the same "next" serial; the
        // unique index refuses the second, and it simply takes the next one.
        $certificate = retry(5, fn () => Certificates::model()::create([
            'recipient_type' => $recipient->getMorphClass(),
            'recipient_id' => $recipient->getKey(),
            'context_type' => $context?->getMorphClass(),
            'context_id' => $context?->getKey(),
            'type' => $type,
            'template_id' => $template->id,
            'title' => ($data['title'] ?? null) ?: $template->title($locale),
            'achievement' => $data['achievement'] ?? null,
            'grade' => Certificates::value($data['grade'] ?? null),
            'source' => Certificates::value($data['source'] ?? null) ?? 'manual',
            'source_id' => $data['source_id'] ?? null,
            'details' => $data['details'] ?? null,
            'issued_on' => $data['issued_on'] ?? today()->toDateString(),
            'issued_by' => $by?->getAuthIdentifier(),
            'status' => CertificateStatus::Draft,
        ]), 0, fn ($e) => $e instanceof UniqueConstraintViolationException && str_contains($e->getMessage(), 'certificate_no'));
        $certificate->setRelation('recipient', $recipient);

        CertificateDrafted::dispatch($certificate, $by);

        if (! Certificates::setting('require_approval', true)) {
            $this->approve($certificate, $by);
        }

        return $certificate;
    }

    /** True when the recipient already has a certificate from this source (revoked ones included). */
    public function exists(Model $recipient, string|BackedEnum $source, int|string $sourceId): bool
    {
        return Certificates::model()::query()->forRecipient($recipient)->fromSource(Certificates::value($source), $sourceId)->exists();
    }

    /**
     * The template a certificate of this type uses in this context: the host's owner
     * chain when it has one (Contracts\OwnsTemplates), otherwise the application-wide one.
     */
    public function templateFor(string|BackedEnum $type, ?Model $context = null): CertificateTemplate
    {
        $type = Certificates::value($type);
        $host = Certificates::host();

        return $host instanceof OwnsTemplates
            ? Certificates::templateModel()::resolve($type, $host->templateOwners($context))
            : Certificates::templateModel()::forType($type);
    }

    /** Edit a draft: achievement, title, grade, context, issued_on. */
    public function update(Certificate $certificate, array $data): Certificate
    {
        $this->assertDraft($certificate);
        $certificate->fill(array_intersect_key($data, array_flip(['achievement', 'grade', 'issued_on'])));
        if (array_key_exists('context', $data)) {
            $certificate->context_type = $data['context']?->getMorphClass();
            $certificate->context_id = $data['context']?->getKey();
        }
        if (! empty($data['title'])) {
            $certificate->title = $data['title'];
        }
        $certificate->save();

        return $certificate;
    }

    public function delete(Certificate $certificate): void
    {
        $this->assertDraft($certificate);
        DB::transaction(function () use ($certificate) {
            $this->files->delete($certificate, self::PDF_SLOT);
            $certificate->delete();
        });
    }

    /** Approve a draft: issue date = today, final PDF stored (now, or on first open — render_on_approve), recipient notified (notify_on_approve). */
    public function approve(Certificate $certificate, ?Authenticatable $by = null): Certificate
    {
        $this->assertDraft($certificate);

        $certificate->forceFill([
            'status' => CertificateStatus::Approved,
            'approved_by' => $by?->getAuthIdentifier(),
            'approved_at' => now(),
            'issued_on' => today()->toDateString(),
        ])->save();

        if (Certificates::setting('render_on_approve', true)) {
            $this->render($certificate);
        }
        CertificateApproved::dispatch($certificate, $by);

        if (Certificates::setting('notify_on_approve', true) && $this->notifier->enabled()) {
            $this->send($certificate);
        }

        return $certificate;
    }

    public function revoke(Certificate $certificate, Authenticatable $by, string $reason): Certificate
    {
        if (! $certificate->isApproved()) {
            throw ValidationException::withMessages(['status' => __('certificates::certificates.errors.not_approved')]);
        }

        DB::transaction(function () use ($certificate, $by, $reason) {
            $certificate->forceFill([
                'status' => CertificateStatus::Revoked,
                'revoked_by' => $by->getAuthIdentifier(),
                'revoked_at' => now(),
                'revoke_reason' => $reason,
            ])->save();
            // The stored PDF would still look valid; later downloads are rendered fresh with a stamp.
            $this->files->delete($certificate, self::PDF_SLOT);
        });
        CertificateRevoked::dispatch($certificate, $by);

        return $certificate;
    }

    /**
     * Several certificates of ONE design as one PDF, a page each, in the order given — the
     * file a print shop is sent. Every page is the same page its own download would be;
     * a picture shared by the pages (the artwork) is embedded once, so a hundred pages
     * stay the size of a few. All pages must share a page shape: a PDF from dompdf has
     * one, so the caller bundles per design. $progress(done, total) is told as it goes.
     */
    public function bundle(iterable $certificates, string $purpose = self::PURPOSE_DOWNLOAD, ?callable $progress = null): string
    {
        $list = is_array($certificates) ? $certificates : iterator_to_array($certificates, false);

        return $this->bundlePages(array_map(fn (Certificate $c) => fn () => $this->render($c, $purpose), $list), $progress);
    }

    /**
     * Pages of ANY format as one PDF: each item a callable that draws one page (render(),
     * renderDocument()…), in order. A format with a `sheet` (a badge) is laid out several
     * to a sheet — four A6 cards on A4 — with hairline cut lines between them; others get
     * a page each. Shared pictures are embedded once, as in bundle().
     *
     * @param  list<callable(): mixed>  $pages
     */
    public function bundlePages(array $pages, ?callable $progress = null): string
    {
        if (! $this->pdf instanceof DompdfRenderer) {
            throw new \LogicException('Bundling needs the dompdf renderer.');
        }
        $list = array_values($pages);
        $dir = rtrim(sys_get_temp_dir(), '/').'/cert-bundle-'.bin2hex(random_bytes(8));
        @mkdir($dir, 0700, true);
        $head = null;
        $pages = [];
        $shape = null;
        $shared = [];
        try {
            $format = null;
            foreach ($list as $i => $draw) {
                $this->capture = [];
                try {
                    $draw();
                    $page = $this->capture;
                } finally {
                    $this->capture = null;
                }
                $format ??= $page['format'] ?? null;
                $shape ??= [$page['orientation'], $page['paper']];
                if ([$page['orientation'], $page['paper']] !== $shape) {
                    throw new \InvalidArgumentException('Certificates of different page shapes cannot share one PDF.');
                }
                $html = $page['html'];
                // Cut by position: a page carries megabytes of picture, too much for a regex.
                $head ??= self::between($html, '<head>', '</head>');
                $body = self::between($html, '<body>', '</body>') ?: $html;
                // A large picture repeated on every page goes to one file, read once.
                $body = preg_replace_callback('#data:(image/(?:png|jpeg));base64,([A-Za-z0-9+/=]{20000,}+)#', function ($m) use ($dir, &$shared) {
                    $key = sha1($m[2]);
                    if (! isset($shared[$key])) {
                        $shared[$key] = $dir.'/'.$key.($m[1] === 'image/png' ? '.png' : '.jpg');
                        file_put_contents($shared[$key], base64_decode($m[2]));
                    }

                    return $shared[$key];
                }, $body) ?? $body;
                $pages[] = $body;
                if ($progress) {
                    $progress($i + 1, count($list));
                }
            }
            if ($pages === []) {
                return '';
            }
            if ($format instanceof \Ahl\Certificates\Support\Format && $format->sheet) {
                [$pages, $shape] = $this->impose($pages, $format, $shape[0]);
            }
            $html = '<!DOCTYPE html><html><head>'.$head.'</head><body>'
                .implode('<div style="page-break-before: always;"></div>', $pages).'</body></html>';

            return $this->pdf->renderHtml($html, $shape[0], $shape[1], [$dir]);
        } finally {
            array_map('unlink', glob($dir.'/*') ?: []);
            @rmdir($dir);
        }
    }

    /**
     * Small pages laid out on sheets: cols × rows to a sheet turned the same way as the
     * card (four upright A6 on an upright A4, four flat ones on a flat A4), centred, with
     * hairline cut lines on the joins — what a print shop guillotines along.
     *
     * @return array{0: list<string>, 1: array{0: string, 1: string}}
     */
    private function impose(array $pages, \Ahl\Certificates\Support\Format $format, string $orientation): array
    {
        $sheet = $format->sheet;
        [$cw, $ch] = $format->page($orientation);
        [$sw, $sh] = \Ahl\Certificates\Support\Formats::get(Formats::CERTIFICATE)->page($orientation);   // A4 the same way up
        $cols = (int) $sheet['cols'];
        $rows = (int) $sheet['rows'];
        if (! empty($sheet['fit'])) {
            // A card the organiser sized: as many as fit on the A4 turned the same way.
            $cols = max(1, (int) floor($sw / $cw));
            $rows = max(1, (int) floor($sh / $ch));
        }
        $ox = max(0, ($sw - $cols * $cw) / 2);
        $oy = max(0, ($sh - $rows * $ch) / 2);
        $cut = 'position:absolute; background:#9a94a8;';
        $out = [];
        foreach (array_chunk($pages, $cols * $rows) as $group) {
            $cells = '';
            foreach ($group as $n => $page) {
                $x = $ox + ($n % $cols) * $cw;
                $y = $oy + intdiv($n, $cols) * $ch;
                $cells .= sprintf('<div style="position:absolute; left:%.2fmm; top:%.2fmm; width:%.2fmm; height:%.2fmm; overflow:hidden;">%s</div>', $x, $y, $cw, $ch, $page);
            }
            for ($c = 1; $c < $cols; $c++) {
                $cells .= sprintf('<div style="%s left:%.2fmm; top:0; width:0.15mm; height:%.2fmm;"></div>', $cut, $ox + $c * $cw - 0.075, $sh - 0.5);
            }
            for ($r = 1; $r < $rows; $r++) {
                $cells .= sprintf('<div style="%s left:0; top:%.2fmm; width:%.2fmm; height:0.15mm;"></div>', $cut, $oy + $r * $ch - 0.075, $sw);
            }
            $out[] = sprintf('<div style="position:relative; width:%.2fmm; height:%.2fmm; overflow:hidden;">%s</div>', $sw, $sh - 0.5, $cells);
        }

        return [$out, [$orientation, $sheet['paper']]];
    }

    private static function between(string $html, string $open, string $close): string
    {
        $a = strpos($html, $open);
        $b = strrpos($html, $close);

        return $a !== false && $b !== false && $b > $a ? substr($html, $a + strlen($open), $b - $a - strlen($open)) : '';
    }

    /** Notify the recipient again (the host's notifier). */
    public function send(Certificate $certificate): int
    {
        if (! $certificate->isApproved()) {
            throw ValidationException::withMessages(['status' => __('certificates::certificates.errors.not_approved')]);
        }
        $sent = $this->notifier->send($certificate, $this->verifyUrl($certificate));
        if ($sent) {
            $certificate->forceFill(['sent_at' => now()])->save();
        }

        return $sent;
    }

    public function canSend(): bool
    {
        return $this->notifier->enabled();
    }

    public function verifyUrl(Certificate $certificate): string
    {
        return Certificates::host()->verifyUrl($certificate);
    }

    /** Signed, short-lived download link (no login needed while it lasts). */
    public function downloadUrl(Certificate $certificate, bool $print = false, bool $view = false): string
    {
        return URL::temporarySignedRoute('certificates.download', now()->addMinutes((int) Certificates::setting('link_minutes', 30)),
            ['certificate' => $certificate->getKey()] + ($print ? ['print' => 1] : []) + ($view ? ['view' => 1] : []));
    }

    /** Stored PDF for approved certificates; drafts and revoked ones are rendered on demand with a stamp. */
    public function pdfContents(Certificate $certificate): string
    {
        if ($certificate->isApproved() && ($file = $this->files->get($certificate, self::PDF_SLOT))) {
            return $file['contents'];
        }

        return $this->render($certificate);
    }

    /** The certificate as a PNG, for a screen that cannot show a PDF inline. Never stored here. */
    public function imageContents(Certificate $certificate, int $dpi = 96): string
    {
        return $this->render($certificate, self::PURPOSE_DOWNLOAD, $dpi);
    }

    /** For the paper it will be printed on: a blank-paper template prints text only. Never stored. */
    public function printContents(Certificate $certificate): string
    {
        return $this->render($certificate, self::PURPOSE_PRINT);
    }

    /**
     * Render the PDF. The approved DOWNLOAD is stored in the FileStore; a print copy is
     * rendered each time. A draft always follows the template as it is now, so an
     * organiser editing the design sees every draft change with it.
     */
    public function render(Certificate $certificate, string $purpose = self::PURPOSE_DOWNLOAD, ?int $imageDpi = null): string
    {
        $certificate->loadMissing('recipient', 'context', 'template');
        $template = $certificate->isDraft() || ! $certificate->template
            ? $this->templateFor($certificate->type, $certificate->context_id ? $certificate->context : null)
            : $certificate->template;
        $recipient = $certificate->recipient;
        $locale = $this->localeOf($recipient);
        $details = (array) ($certificate->details ?? []);

        $bytes = $this->renderView($template, $locale, [
            'name' => $recipient instanceof Recipient ? $recipient->certificateName() : (string) ($recipient?->getAttribute('name') ?? ''),
            'title' => $certificate->title ?: $template->title($locale),
            'body' => $this->fill($template->body($locale), $certificate, $locale),
            'grade' => $certificate->gradeLabel($locale),
            'date' => $certificate->issued_on ?? today(),
            'certificate_no' => $certificate->certificate_no,
            // A draft shows where its code will sit with the design room's stand-in (a link
            // that verifies nothing); the real one, which proves the certificate, comes on approval.
            'qr' => $certificate->isApproved() ? $this->qrDataUri($this->verifyUrl($certificate))
                : ($certificate->isDraft() ? $this->qrDataUri($this->verifyUrl(Certificates::model()::make(['verify_token' => 'sample']))) : null),
            'stamp' => match ($certificate->status) {
                CertificateStatus::Draft => __('certificates::certificates.draft_mark', [], $locale),
                CertificateStatus::Revoked => __('certificates::certificates.revoked_mark', [], $locale),
                default => null,
            },
            'photo' => $template->show_photo && $recipient instanceof Recipient ? $recipient->certificatePhoto() : null,
            'place' => isset($details['place']) ? (int) $details['place'] : null,
            'extras' => $this->extras($template, $certificate, $locale),
        ], $certificate, $purpose, $certificate->context_id ? $certificate->context : null, $imageDpi);

        if ($imageDpi === null && $this->capture === null && $certificate->isApproved() && $purpose === self::PURPOSE_DOWNLOAD) {
            $this->files->put($certificate, self::PDF_SLOT, $bytes, 'application/pdf', $certificate->certificate_no.'.pdf');
        }

        return $bytes;
    }

    /**
     * A sample PDF of a template in one language, for the template editor. With a
     * context, the sample is filled from it (and from Host::previewDetails) the way a
     * real certificate there would be.
     */
    public function preview(CertificateTemplate $template, string $locale, ?Model $context = null, string $purpose = self::PURPOSE_DOWNLOAD, ?int $imageDpi = null): string
    {
        $locale = Certificates::locale($locale);
        $grade = Certificates::keys('grades')[0] ?? null;
        $host = Certificates::host();
        $details = $host instanceof BrandsCertificates ? $host->previewDetails($template->type, $context) : [];
        $sample = Certificates::model()::make(['type' => $template->type, 'achievement' => __('certificates::certificates.sample_achievement', [], $locale), 'grade' => $grade, 'issued_on' => today(), 'verify_token' => 'sample', 'details' => $details]);
        if ($context) {
            $sample->context_type = $context->getMorphClass();
            $sample->context_id = $context->getKey();
            $sample->setRelation('context', $context);
        }

        return $this->renderView($template, $locale, [
            'name' => __('certificates::certificates.sample_name', [], $locale),
            'title' => $template->title($locale),
            'body' => $this->fill($template->body($locale), $sample, $locale, __('certificates::certificates.sample_name', [], $locale)),
            'grade' => Certificates::label('grades', $grade, $locale),
            'date' => today(),
            'certificate_no' => config('certificates.number.prefix', 'C').now()->format('y').str_repeat('0', (int) config('certificates.number.digits', 5)),
            'qr' => $this->qrDataUri($this->verifyUrl($sample)),
            'stamp' => null,
            'photo' => null,
            'place' => isset($details['place']) ? (int) $details['place'] : null,
            'extras' => $this->extras($template, $sample, $locale, __('certificates::certificates.sample_name', [], $locale)),
        ], null, $purpose, $context, $imageDpi);
    }

    /**
     * The organiser's own text elements, in one language, placeholders filled.
     *
     * @return array<string, array{text:string, style:string}>
     */
    public function extras(CertificateTemplate $template, Certificate $certificate, string $locale, ?string $name = null): array
    {
        $out = [];
        $fallback = config('certificates.fallback_locale', 'en');
        foreach ((array) ($template->elements ?? []) as $el) {
            if (! is_array($el) || ! is_string($el['key'] ?? null) || ! preg_match(Layout::CUSTOM_KEY, $el['key'])) {
                continue;
            }
            $text = (array) ($el['text'] ?? []);
            $words = (string) ($text[$locale] ?? '') !== '' ? (string) $text[$locale] : (string) ($text[$fallback] ?? collect($text)->first(fn ($v) => $v !== null && $v !== '') ?? '');
            $out[$el['key']] = [
                'text' => $this->fill($words, $certificate, $locale, $name),
                'style' => isset(Layout::CUSTOM_STYLES[$el['style'] ?? '']) ? $el['style'] : 'body',
            ];
        }

        return $out;
    }

    public function qrDataUri(string $text): string
    {
        return 'data:image/png;base64,'.base64_encode((new Writer(new GDLibRenderer(220, 1)))->writeString($text));
    }

    /** Placeholder tokens a template body may use. */
    public function placeholderKeys(): array
    {
        return array_values(array_unique([...['name', 'achievement', 'grade', 'context', 'date'], ...Certificates::host()->placeholderKeys()]));
    }

    /** Replace {name}, {achievement}, {grade}, {context}, {date} and the host's extra placeholders. */
    public function fill(string $text, Certificate $certificate, string $locale, ?string $name = null): string
    {
        $recipient = $certificate->relationLoaded('recipient') || $certificate->recipient_id ? $certificate->recipient : null;
        $context = $certificate->context_id ? $certificate->context : null;
        $values = [
            'name' => $name ?? ($recipient instanceof Recipient ? $recipient->certificateName() : ''),
            'achievement' => (string) $certificate->achievement,
            'grade' => (string) $certificate->gradeLabel($locale),
            'context' => $context ? (string) (Certificates::host()->presentContext($context)['name'] ?? '') : '',
            'date' => (string) $certificate->issued_on?->toDateString(),
        ];
        foreach (Certificates::host()->placeholders($certificate, $locale) as $key => $value) {
            $values[$key] = (string) $value;
        }

        return trim(preg_replace('/\s{2,}/u', ' ', strtr($text, collect($values)->mapWithKeys(fn ($v, $k) => ['{'.$k.'}' => $v])->all())));
    }

    public function localeOf(?Model $recipient): string
    {
        return Certificates::locale($recipient instanceof Recipient ? $recipient->certificateLocale() : null);
    }

    /** Signature image as a data: URI, or null. */
    public function signatureDataUri(CertificateTemplate $template, int $slot): ?string
    {
        $file = $this->files->get($template, "signature_{$slot}");

        return $file ? 'data:'.$file['mime'].';base64,'.base64_encode($file['contents']) : null;
    }

    /**
     * Any page of a template drawn from plain data — the door for a format whose pages
     * are not certificates (an official's badge, Support\Formats). $fields is what the
     * format's view prints: name, role, club, photo (a data URI), qr (a verification
     * URL, drawn as the code), title and anything else it reads from `cert`.
     */
    public function renderDocument(CertificateTemplate $template, string $locale, array $fields, ?Model $context = null, string $purpose = self::PURPOSE_DOWNLOAD, ?int $imageDpi = null): string
    {
        $locale = Certificates::locale($locale);
        $qr = isset($fields['qr']) && is_string($fields['qr']) && str_starts_with($fields['qr'], 'http') ? $this->qrDataUri($fields['qr']) : null;

        return $this->renderView($template, $locale, array_merge([
            'name' => '', 'title' => $template->title($locale), 'body' => '', 'grade' => null, 'place' => null,
            'date' => today(), 'certificate_no' => '', 'stamp' => null, 'photo' => null,
            'extras' => $this->extrasFrom($template, $locale, $fields),
        ], $fields, ['qr' => $qr]), null, $purpose, $context, $imageDpi);
    }

    /**
     * The organiser's own texts for a page drawn from plain data: each {placeholder} is
     * the field of that name ({name}, {role}, {club}, {event}…), and an unknown one
     * comes out empty rather than as braces on the page.
     *
     * @return array<string, array{text:string, style:string}>
     */
    public function extrasFrom(CertificateTemplate $template, string $locale, array $values): array
    {
        $fallback = config('certificates.fallback_locale', 'en');
        $out = [];
        foreach ((array) ($template->elements ?? []) as $el) {
            if (! is_array($el) || ! is_string($el['key'] ?? null) || ! preg_match(Layout::CUSTOM_KEY, $el['key'])) {
                continue;
            }
            $text = (array) ($el['text'] ?? []);
            $words = (string) ($text[$locale] ?? '') !== '' ? (string) $text[$locale] : (string) ($text[$fallback] ?? collect($text)->first(fn ($v) => $v !== null && $v !== '') ?? '');
            $words = preg_replace_callback('/\{([a-z_]{2,30})\}/', fn ($m) => is_scalar($values[$m[1]] ?? null) ? (string) $values[$m[1]] : '', $words) ?? $words;
            $out[$el['key']] = [
                'text' => trim(preg_replace('/\s{2,}/u', ' ', $words) ?? $words),
                'style' => isset(Layout::CUSTOM_STYLES[$el['style'] ?? '']) ? $el['style'] : 'body',
            ];
        }

        return $out;
    }

    /** Every page is drawn in its own format (Support\Formats), whose size the shared helpers then read. */
    private function renderView(CertificateTemplate $template, string $locale, array $cert, ?Certificate $certificate, string $purpose = self::PURPOSE_DOWNLOAD, ?Model $context = null, ?int $imageDpi = null): string
    {
        return Formats::using($template->format(), fn () => $this->drawPage($template, $locale, $cert, $certificate, $purpose, $context, $imageDpi));
    }

    private function drawPage(CertificateTemplate $template, string $locale, array $cert, ?Certificate $certificate, string $purpose = self::PURPOSE_DOWNLOAD, ?Model $context = null, ?int $imageDpi = null): string
    {
        /** @var CarbonInterface $date */
        $date = $cert['date'];
        $host = Certificates::host();
        \Ahl\Certificates\Support\Drawn::reset();
        $brand = $host instanceof BrandsCertificates ? $host->brand($certificate, $template, $context, $locale) : [];
        $accent = $this->hex($template->accent) ?? $this->hex($brand['accent'] ?? null) ?? '#1f4e79';
        $background = $template->backgroundFor($purpose);
        $place = $cert['place'] ?? null;
        $rtl = in_array($locale, ['ar', 'fa', 'he', 'ur'], true);
        $metal = $place ? (Medal::METAL[$place] ?? null) : null;
        $orientation = $template->pageOrientation();
        $bg = $this->backgroundBytes($template, $background, $accent, $brand['watermark'] ?? null, $orientation);
        if ($imageDpi !== null && $bg) {
            $bg = $this->forScreen($bg, $imageDpi, $orientation);   // a preview never needs print resolution
        }
        if ($background === 'artwork' && ($bg['mime'] ?? '') === 'image/png' && ! $this->files->exists($template, CertificateTemplate::BACKGROUND_SLOT)) {
            $background = 'plain';   // artwork chosen, none uploaded yet: the classic sheet, frame and all
        }
        $resolved = Layout::resolve($template->layoutForPage(), $place !== null, $rtl, $orientation, $template->customKeys($orientation));
        // Areas the organiser erased from the picture, painted out before anything reads its colours.
        if ($bg) {
            $areas = [];
            foreach ($resolved as $k => $b) {
                if (preg_match(Layout::ERASE_KEY, $k) && empty($b['hidden'])) {
                    $areas[] = ['x' => (float) $b['x'], 'y' => (float) $b['y'], 'w' => (float) $b['w'], 'h' => (float) $b['h']];
                }
            }
            [$pw, $ph] = Layout::page($orientation);
            $bg = Eraser::apply($bg, $areas, $pw, $ph, config('certificates.backgrounds_cache'));
        }
        $layout = $this->inked($resolved, $bg, $accent, $metal, $background, $orientation);

        $data = $host->viewData([
            'locale' => $locale,
            'rtl' => $rtl,
            'ornament' => in_array($template->ornament_level, CertificateTemplate::ORNAMENTS, true) ? $template->ornament_level : 'full',
            'issuer' => $host->issuer($locale),
            'date' => $date->copy()->locale($locale)->translatedFormat('j F Y'),
            'date_secondary' => $host->secondaryDate($date, $locale),
            'signatures' => collect(CertificateTemplate::SIGNATURE_SLOTS)->map(fn ($slot) => [
                'name' => $template->{"signature{$slot}_name"},
                'title' => $template->{"signature{$slot}_title"},
                'image' => $this->signatureDataUri($template, $slot),
            ])->filter(fn ($s) => $s['name'] || $s['title'] || $s['image'])->values()->all(),
            'cert' => $cert,
            'accent' => $accent,
            'metal' => $metal,
            'background' => $background,
            'background_image' => $bg ? 'data:'.$bg['mime'].';base64,'.base64_encode($bg['contents']) : null,
            'layout' => $layout,
            'orientation' => $orientation,
            'page' => Layout::page($orientation),
            // Blank paper brings its own frame, logos and seal; artwork keeps ours (each can be hidden).
            'decorated' => $background !== 'blank',
            'offset' => $background === 'blank'
                ? ['x' => max(-30, min(30, (int) $template->print_offset_x)), 'y' => max(-30, min(30, (int) $template->print_offset_y))]
                : ['x' => 0, 'y' => 0],
            'logos' => array_values(array_slice(array_filter((array) ($brand['logos'] ?? [])), 0, 2)),
            'seal' => $brand['seal'] ?? null,
            'medal' => $place ? (new Medal(Fonts::path('Inter-Bold.ttf')))->dataUri($place, $accent) : null,
            'ribbon' => $place && $place <= 3 ? __("certificates::certificates.places.{$place}", [], $locale) : null,
            'fonts' => Fonts::css(),
            'shaper' => app(TextShaper::class),
            // Drawn as a picture (the screen) or as the PDF: the PDF's lines are re-spaced
            // (Support\LineFit) so both put every word in the same place.
            'raster' => $imageDpi !== null,
            // What the certificate belongs to (or, for a preview, would belong to), for Host::viewData.
            'context' => $context,
        ], $certificate, $locale);

        // A certificate keeps its configurable view and paper; another format brings its own.
        $format = Formats::current();
        $view = $format->key === Formats::CERTIFICATE ? config('certificates.pdf.view', $format->view) : $format->view;
        // A page the organiser sized (1.4) is drawn on exactly that paper.
        $paper = $format->key === Formats::CERTIFICATE && $format->paper !== 'custom' ? config('certificates.pdf.paper', 'a4') : $format->paperFor($orientation);

        if ($this->capture !== null) {
            $this->capture = ['html' => view($view, $data)->render(), 'orientation' => $orientation, 'paper' => $paper, 'format' => $format];

            return '';
        }

        if ($imageDpi !== null) {
            if (! $this->pdf instanceof ImageRenderer) {
                throw new \LogicException('The configured PDF renderer cannot draw images.');
            }

            return $this->pdf->renderImage($view, $data, $orientation, $paper, $imageDpi);
        }

        return $this->pdf->render($view, $data, $orientation, $paper);
    }

    /**
     * Where each element's ink landed on the page just drawn (Support\Drawn), for an
     * editor that wants its handles to hug the words rather than their whole box.
     *
     * @return array<string, array{x:float,y:float,w:float,h:float}>
     */
    public function drawn(): array
    {
        return \Ahl\Certificates\Support\Drawn::all();
    }

    /** True when the renderer can draw PNG previews. */
    public function canPreviewImages(): bool
    {
        return $this->pdf instanceof ImageRenderer;
    }

    /** @return array{contents:string, mime:string}|null the page behind the certificate; null for blank paper */
    private function backgroundBytes(CertificateTemplate $template, string $mode, string $accent, ?string $watermark, string $orientation = 'landscape'): ?array
    {
        if ($mode === 'blank') {
            return null;
        }
        if ($mode === 'artwork') {
            $fit = in_array($template->artwork_fit, CertificateTemplate::ARTWORK_FITS, true) ? $template->artwork_fit : null;
            if ($fit && ($source = $this->files->get($template, CertificateTemplate::BACKGROUND_SOURCE_SLOT) ?? $this->files->get($template, CertificateTemplate::BACKGROUND_SLOT))) {
                return $this->compose($source, $fit, $orientation);
            }
            $file = $this->files->get($template, CertificateTemplate::BACKGROUND_SLOT);
            if ($file) {
                return $this->fitToPage($file, $orientation);
            }
            $mode = 'plain';   // artwork chosen but not uploaded yet
        }

        return ['contents' => (new Backgrounds(config('certificates.backgrounds_cache')))->png($mode, $accent, $watermark, $orientation), 'mime' => 'image/png'];
    }

    /**
     * The organiser's picture laid on the page the way they chose — the desktop-wallpaper
     * choices: fill (cover, the overhang trimmed), fit (shown whole, on a blurred copy of
     * itself), stretch (to the page, proportions ignored), tile (repeated at its own size)
     * and center (at its own size, in the middle). "Its own size" is its pixels at 300 dpi.
     * Cached on disk by content, mode and orientation, so a sheet pays for it once.
     *
     * @param  array{contents:string, mime:string}  $source
     * @return array{contents:string, mime:string}
     */
    private function compose(array $source, string $fit, string $orientation): array
    {
        [$tw, $th] = $orientation === 'portrait' ? [2480, 3508] : [3508, 2480];
        $dir = config('certificates.backgrounds_cache');
        $cache = $dir ? rtrim($dir, '/').'/art-'.sha1($source['contents']).'-'.$fit.'-'.$orientation.'.jpg' : null;
        if ($cache && is_file($cache) && ($hit = @file_get_contents($cache)) !== false) {
            return ['contents' => $hit, 'mime' => 'image/jpeg'];
        }
        $im = @imagecreatefromstring($source['contents']);
        if (! $im instanceof \GdImage) {
            return $source;
        }
        [$w, $h] = [imagesx($im), imagesy($im)];
        $page = imagecreatetruecolor($tw, $th);
        imagefill($page, 0, 0, imagecolorallocate($page, ...Backgrounds::PAPER));
        imagealphablending($page, true);

        switch ($fit) {
            case 'stretch':
                imagecopyresampled($page, $im, 0, 0, 0, 0, $tw, $th, $w, $h);
                break;
            case 'center':
                // At its own size; a picture bigger than the page is trimmed evenly.
                $sx = max(0, (int) (($w - $tw) / 2));
                $sy = max(0, (int) (($h - $th) / 2));
                imagecopy($page, $im, max(0, (int) (($tw - $w) / 2)), max(0, (int) (($th - $h) / 2)), $sx, $sy, min($w, $tw), min($h, $th));
                break;
            case 'tile':
                for ($y = 0; $y < $th; $y += $h) {
                    for ($x = 0; $x < $tw; $x += $w) {
                        imagecopy($page, $im, $x, $y, 0, 0, min($w, $tw - $x), min($h, $th - $y));
                    }
                }
                break;
            case 'fit':
                $tiny = imagecreatetruecolor(max(1, (int) ($tw / 40)), max(1, (int) ($th / 40)));
                $scale = max($tw / $w, $th / $h);
                [$sw, $sh] = [(int) round($tw / $scale), (int) round($th / $scale)];
                imagecopyresampled($tiny, $im, 0, 0, (int) (($w - $sw) / 2), (int) (($h - $sh) / 2), imagesx($tiny), imagesy($tiny), $sw, $sh);
                imagecopyresampled($page, $tiny, 0, 0, 0, 0, $tw, $th, imagesx($tiny), imagesy($tiny));
                imagedestroy($tiny);
                imagefilter($page, IMG_FILTER_GAUSSIAN_BLUR);
                $f = min($tw / $w, $th / $h);
                [$fw, $fh] = [(int) round($w * $f), (int) round($h * $f)];
                imagecopyresampled($page, $im, (int) (($tw - $fw) / 2), (int) (($th - $fh) / 2), 0, 0, $fw, $fh, $w, $h);
                break;
            default: // fill
                $scale = max($tw / $w, $th / $h);
                [$sw, $sh] = [(int) round($tw / $scale), (int) round($th / $scale)];
                imagecopyresampled($page, $im, 0, 0, (int) (($w - $sw) / 2), (int) (($h - $sh) / 2), $tw, $th, $sw, $sh);
        }
        imagedestroy($im);
        ob_start();
        imagejpeg($page, null, 86);
        $bytes = (string) ob_get_clean();
        imagedestroy($page);
        if ($cache) {
            @is_dir(dirname($cache)) || @mkdir(dirname($cache), 0775, true);
            @file_put_contents($cache, $bytes, LOCK_EX);
        }

        return ['contents' => $bytes, 'mime' => 'image/jpeg'];
    }

    /**
     * Artwork the other way up from the page (a landscape picture on a portrait page, say)
     * is shown whole on a blurred copy of itself rather than stretched. Artwork that
     * already matches the page is returned untouched. Cached on disk by content.
     *
     * @param  array{contents:string, mime:string}  $file
     * @return array{contents:string, mime:string}
     */
    private function fitToPage(array $file, string $orientation): array
    {
        $info = @getimagesizefromstring($file['contents']);
        if (! $info || ! $info[1]) {
            return $file;
        }
        $portrait = $orientation === 'portrait';
        if (($info[0] < $info[1]) === $portrait || abs($info[0] / $info[1] - 1) < 0.05) {
            return $file;
        }
        [$tw, $th] = $portrait ? [2480, 3508] : [3508, 2480];
        $dir = config('certificates.backgrounds_cache');
        $cache = $dir ? rtrim($dir, '/').'/fit-'.sha1($file['contents']).'-'.$orientation.'.jpg' : null;
        if ($cache && is_file($cache) && ($hit = @file_get_contents($cache)) !== false) {
            return ['contents' => $hit, 'mime' => 'image/jpeg'];
        }
        $im = @imagecreatefromstring($file['contents']);
        if (! $im instanceof \GdImage) {
            return $file;
        }
        [$w, $h] = [imagesx($im), imagesy($im)];
        $page = imagecreatetruecolor($tw, $th);
        $tiny = imagecreatetruecolor(max(1, (int) ($tw / 40)), max(1, (int) ($th / 40)));
        $scale = max($tw / $w, $th / $h);
        [$sw, $sh] = [(int) round($tw / $scale), (int) round($th / $scale)];
        imagecopyresampled($tiny, $im, 0, 0, (int) (($w - $sw) / 2), (int) (($h - $sh) / 2), imagesx($tiny), imagesy($tiny), $sw, $sh);
        imagecopyresampled($page, $tiny, 0, 0, 0, 0, $tw, $th, imagesx($tiny), imagesy($tiny));
        imagedestroy($tiny);
        imagefilter($page, IMG_FILTER_GAUSSIAN_BLUR);
        $fit = min($tw / $w, $th / $h);
        [$fw, $fh] = [(int) round($w * $fit), (int) round($h * $fit)];
        imagecopyresampled($page, $im, (int) (($tw - $fw) / 2), (int) (($th - $fh) / 2), 0, 0, $fw, $fh, $w, $h);
        imagedestroy($im);
        ob_start();
        imagejpeg($page, null, 86);
        $bytes = (string) ob_get_clean();
        imagedestroy($page);
        if ($cache) {
            @is_dir(dirname($cache)) || @mkdir(dirname($cache), 0775, true);
            @file_put_contents($cache, $bytes, LOCK_EX);
        }

        return ['contents' => $bytes, 'mime' => 'image/jpeg'];
    }

    /**
     * The background at the size a screen preview actually shows (a 3508 px artwork is
     * decoded and embedded on every redraw otherwise, which is most of a preview's time).
     * Cached on disk by content, so a page being arranged pays for it once.
     *
     * @param  array{contents:string, mime:string}  $bg
     * @return array{contents:string, mime:string}
     */
    private function forScreen(array $bg, int $dpi, string $orientation = 'landscape'): array
    {
        $width = (int) ceil(Layout::page($orientation)[0] / 25.4 * $dpi * 1.25);
        $dir = config('certificates.backgrounds_cache');
        $file = $dir ? rtrim($dir, '/').'/screen-'.sha1($bg['contents']).'-'.$width.'.jpg' : null;
        if ($file && is_file($file) && ($hit = @file_get_contents($file)) !== false) {
            return ['contents' => $hit, 'mime' => 'image/jpeg'];
        }
        $im = @imagecreatefromstring($bg['contents']);
        if (! $im instanceof \GdImage || imagesx($im) <= $width) {
            return $bg;
        }
        $small = imagescale($im, $width, -1, IMG_BICUBIC);
        imagedestroy($im);
        if (! $small instanceof \GdImage) {
            return $bg;
        }
        ob_start();
        imagejpeg($small, null, 82);
        $bytes = (string) ob_get_clean();
        imagedestroy($small);
        if ($file) {
            @is_dir(dirname($file)) || @mkdir(dirname($file), 0775, true);
            @file_put_contents($file, $bytes, LOCK_EX);
        }

        return ['contents' => $bytes, 'mime' => 'image/jpeg'];
    }

    /**
     * Give every element the colour it prints in: the organiser's own choice when they
     * made one, otherwise its intended colour — switched to dark or light ink when that
     * would not read against what is actually behind it (Support\Contrast).
     */
    private function inked(array $layout, ?array $bg, string $accent, ?string $metal, string $background, string $orientation = 'landscape'): array
    {
        $intended = [
            'headline' => $metal ?? $accent, 'title' => '#1f1a2e', 'ribbon' => $metal ?? $accent, 'lead' => '#6b6478',
            'name' => $metal ? '#1f1a2e' : $accent, 'divider' => $metal ?? '#c9a86a', 'body' => '#3b3548',
            'signatures' => '#1f1a2e', 'seal' => $metal ?? $accent, 'meta' => '#6b6478', 'panel' => '#fbf8f2',
            // A badge's role sits on a band of the event's colour; its club reads quietly under it.
            'role' => $accent, 'club' => '#4a4458',
        ];
        $large = ['title', 'name'];
        $sampler = new Contrast($bg['contents'] ?? null, $background === 'blank' ? '#ffffff' : '#fbf8f2', $orientation);
        $panel = $layout['panel'] ?? null;
        $inPanel = fn (array $b) => $panel && ! $panel['hidden'] && ($panel['opacity'] ?? 0) >= 0.6
            && $b['x'] + $b['w'] / 2 >= $panel['x'] && $b['x'] + $b['w'] / 2 <= $panel['x'] + $panel['w']
            && $b['y'] + $b['h'] / 2 >= $panel['y'] && $b['y'] + $b['h'] / 2 <= $panel['y'] + $panel['h'];

        $votes = [];
        foreach ($layout as $key => $box) {
            $layout[$key]['backing'] = null;
            if (preg_match(Layout::ERASE_KEY, $key)) {
                $layout[$key]['ink'] = null;   // an erased area carries no ink and has no say in the bands
                continue;
            }
            $want = $intended[$key] ?? '#1f1a2e';
            if ($box['color']) {
                $layout[$key]['ink'] = $box['color'];
            } elseif (in_array($key, ['ribbon', 'divider', 'panel', 'role'], true)) {
                $layout[$key]['ink'] = $want;   // fills, not type: they are the ground, not on it
            } elseif ($inPanel($box)) {
                $layout[$key]['ink'] = Contrast::ink($want, $intended['panel'], in_array($key, $large, true));
            } else {
                $isLarge = in_array($key, $large, true);
                $need = $isLarge ? 3.0 : 4.5;
                $cells = $sampler->cells($box['x'], $box['y'], $box['w'], $box['h']);
                $ink = Contrast::ink($want, $sampler->behind($box['x'], $box['y'], $box['w'], $box['h']), $isLarge);
                // Busy ground (a photograph): if no single ink reads everywhere under the
                // box, the words get a soft band. Which kind is decided once for the page below.
                if (Contrast::worst($ink, $cells) < $need && ! in_array($key, ['seal', 'logo_start', 'logo_end', 'photo', 'medal', 'qr'], true)) {
                    $votes[$key] = Contrast::worst(Contrast::LIGHT, $cells) >= Contrast::worst(Contrast::DARK, $cells) ? 'dark' : 'light';
                }
                $layout[$key]['ink'] = $ink;
            }
        }

        // One style of band for the whole page, so the sheet reads as one design: the
        // style most elements need (dark band + white ink on a photograph, as a rule).
        if ($votes) {
            $dark = count(array_filter($votes, fn ($v) => $v === 'dark')) >= count($votes) / 2;
            foreach (array_keys($votes) as $key) {
                $layout[$key]['ink'] = $dark ? Contrast::LIGHT : Contrast::DARK;
                $layout[$key]['backing'] = $dark ? 'rgba(15, 12, 30, 0.55)' : 'rgba(251, 248, 242, 0.85)';
            }
        }

        return $layout;
    }

    private function hex(?string $value): ?string
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : null;
    }

    private function assertDraft(Certificate $certificate): void
    {
        if (! $certificate->isDraft()) {
            throw ValidationException::withMessages(['status' => __('certificates::certificates.errors.not_draft')]);
        }
    }
}
