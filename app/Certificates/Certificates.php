<?php

namespace App\Certificates;

use App\Support\Modules\AbstractModule;

/**
 * Certificates: printed, signed, verifiable by QR (2026-09-29).
 *
 * The engine is the vendored package `ahl/laravel-certificates`
 * (packages/ahl/certificates): templates per owner, draft → approved → revoked,
 * the PDF and the QR. This module is everything TakeOne-shaped around it — who
 * may issue and see, where the files live, the screens, the public verification
 * page and the MCP tools.
 *
 * It knows nothing about any one reason for a certificate. A CONTEXT does
 * (App\Certificates\Contracts\CertificateContext): events are the first —
 * participation and podium, App\Events\Support\EventCertificates — and a belt
 * test or a course is one more provider, not a change here.
 *
 * Switched by config('certificates.enabled') — off by default.
 */
class Certificates extends AbstractModule
{
    public function key(): string
    {
        return 'cert';
    }
}
