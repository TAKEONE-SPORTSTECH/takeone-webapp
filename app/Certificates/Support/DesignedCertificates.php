<?php

namespace App\Certificates\Support;

use Ahl\Certificates\Certificates as Engine;
use Ahl\Certificates\Contracts\DesignedPages;
use Ahl\Certificates\Models\Certificate;
use App\Printables\Designer;
use App\Printables\Printer;

/**
 * Draws a certificate from its type's design in the new designer (App\Printables),
 * when the event has one — every live field filled for THIS holder: their name,
 * the context's own {tokens} (division, medal, club…), the date, their photo, the
 * host's logo and the QR that verifies this very certificate.
 *
 * Before an event saves a design, its certificates print the designer's starting
 * page — what the designer shows on first open — never the old template. Only with
 * the designer switched off (production) does the engine draw from its template.
 */
class DesignedCertificates implements DesignedPages
{
    public function __construct(private readonly Contexts $contexts) {}

    public function render(Certificate $certificate, array $fill, string $purpose, ?int $imageDpi): ?string
    {
        $context = $certificate->context_id ? $certificate->context : null;
        if (! Designer::enabled() || ! $context) {
            return null;
        }
        // The saved design — or, before one is saved, the designer's own starting page.
        // A certificate never falls back to the old template layout (the user's call, 2026-10-19).
        $design = $this->designFor($certificate);
        if (! $design) {
            return null;
        }

        return Printer::render($design, $this->values($certificate, $fill), [
            'stamp' => $fill['stamp'],
            'as' => $imageDpi ? 'png' : 'pdf',
            'dpi' => $imageDpi ?: 96,
        ]);
    }

    /**
     * Every live field of a design, filled for THIS certificate — what it prints with,
     * and what the designer's "real person" preview shows (CertificatePrintable).
     *
     * @param  array{name: string, qr: ?string, qr_text?: ?string, photo: ?string, date: string, locale: string}  $fill  CertificateService::designFill()
     * @return array<string, string>
     */
    public function values(Certificate $certificate, array $fill): array
    {
        $context = $certificate->context_id ? $certificate->context : null;
        $values = ['name' => $fill['name'], 'date' => $fill['date']];
        foreach (Engine::host()->placeholders($certificate, $fill['locale']) as $key => $value) {
            $values[$key] = (string) $value;
        }
        // An issued certificate prints the face its holder had then.
        $values['photo'] = (CertificateSnapshot::of($certificate) ? CertificateSnapshot::photoDataUri($certificate) : null) ?: ($fill['photo'] ?: '');
        $values['qr'] = $fill['qr'] ?: '';
        $values['qr_text'] = (string) ($fill['qr_text'] ?? '');
        $provider = $this->contexts->forModel($context);
        $values['logo'] = $provider?->brand($context)['logos'][0] ?? '';
        // The crest of the club the certificate is about, where the context knows one.
        $values['club_logo'] = $provider && method_exists($provider, 'clubLogo') ? ($provider->clubLogo($certificate) ?? '') : '';
        // Flags: the holder's nationality, and the country of their club.
        $values['nationality_flag'] = $provider && method_exists($provider, 'nationalityFlag') ? ($provider->nationalityFlag($certificate) ?? '') : '';
        $values['club_flag'] = $provider && method_exists($provider, 'clubFlag') ? ($provider->clubFlag($certificate) ?? '') : '';

        return $values;
    }

    /**
     * The design one certificate prints from: the most specific saved design the
     * context names for it (an official's own job — EventCertificates::designSlots),
     * else its type's design, else the type's starting page.
     */
    public function designFor(Certificate $certificate): ?\App\Printables\Models\PrintableDesign
    {
        $context = $certificate->context_id ? $certificate->context : null;
        if (! Designer::enabled() || ! $context) {
            return null;
        }
        $provider = $this->contexts->forModel($context);
        foreach ($provider && method_exists($provider, 'designSlots') ? $provider->designSlots($certificate) : [] as $slot) {
            if ($found = Designer::find($context, 'certificate', $slot)) {
                return $found;
            }
        }
        $type = $certificate->type instanceof \BackedEnum ? $certificate->type->value : (string) $certificate->type;

        return Designer::resolve($context, 'certificate', $type);
    }

    /**
     * The print shop's one PDF, when these certificates are drawn from a design: each
     * printed on its own (through $render, the engine's render()) and joined in order.
     * Null — the engine bundles as before — when their type has no design.
     *
     * @param  list<Certificate>  $certificates  all of one type (the engine bundles per design)
     * @param  callable(Certificate): string  $render
     */
    public function bundle(array $certificates, callable $render, ?callable $progress = null): ?string
    {
        $first = $certificates[0] ?? null;
        $context = $first?->context_id ? $first->context : null;
        $type = $first ? ($first->type instanceof \BackedEnum ? $first->type->value : (string) $first->type) : null;
        if (! Designer::enabled() || ! $context || ! Designer::resolve($context, 'certificate', $type)) {
            return null;
        }

        // A print run: certificates sharing a design drawn many to a Chromium, several at once.
        if (Printer::batching()) {
            return $this->batched($certificates, $progress);
        }

        return Printer::join(array_map(fn (Certificate $c) => fn () => $render($c), $certificates), $progress);
    }

    /**
     * The print run through Printer::renderMany(): certificates kept in order, each
     * run of neighbours that print from the same design (an official's job may have
     * its own) drawn together, filled exactly as each prints on its own.
     *
     * @param  list<Certificate>  $certificates
     */
    private function batched(array $certificates, ?callable $progress): string
    {
        $service = app(\Ahl\Certificates\CertificateService::class);
        $runs = [];
        foreach ($certificates as $c) {
            $design = $this->designFor($c);
            if (! $design) {
                continue;
            }
            $fill = $service->designFill($c);
            $item = ['values' => $this->values($c, $fill), 'stamp' => $fill['stamp']];
            $last = array_key_last($runs);
            if ($last !== null && $runs[$last]['design']->is($design)) {
                $runs[$last]['items'][] = $item;
            } else {
                $runs[] = ['design' => $design, 'items' => [$item]];
            }
        }

        $total = count($certificates);
        $before = 0;
        $pdfs = [];
        foreach ($runs as $run) {
            $pdfs[] = Printer::renderMany($run['design'], $run['items'], $progress ? fn (int $done) => $progress($before + $done, $total) : null);
            $before += count($run['items']);
        }

        return count($pdfs) === 1 ? $pdfs[0] : Printer::join(array_map(fn (string $pdf) => fn () => $pdf, $pdfs));
    }
}
