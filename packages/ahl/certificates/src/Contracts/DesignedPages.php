<?php

namespace Ahl\Certificates\Contracts;

use Ahl\Certificates\Models\Certificate;

/**
 * A certificate drawn from a design made OUTSIDE this package (a host's own page
 * designer) instead of from a template. Bound by `certificates.designed_pages`.
 *
 * render() answers null when there is no such design for this certificate, and the
 * engine draws it from its template exactly as before — so a host turns this on one
 * certificate type at a time, and every type it has not designed is untouched.
 */
interface DesignedPages
{
    /**
     * @param  array{name: string, qr: ?string, stamp: ?string, photo: ?string, date: string, locale: string}  $fill
     *         what the engine already worked out for this certificate: the holder's name,
     *         the QR as a data: URI (the real one once approved), a DRAFT/REVOKED stamp,
     *         the photo as a data: URI, the issue date in the holder's language
     * @return string|null PDF bytes, or PNG bytes when $imageDpi is given; null = no design
     */
    public function render(Certificate $certificate, array $fill, string $purpose, ?int $imageDpi): ?string;
}
