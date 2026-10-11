<?php

/*
|--------------------------------------------------------------------------
| Certificates (ahl/laravel-certificates, vendored at packages/ahl/certificates)
|--------------------------------------------------------------------------
| The package is the engine: templates, the draft → approved → revoked
| workflow, the PDF and the QR. Everything TakeOne-shaped — who may issue,
| where files live, what an event puts on the page — is App\Certificates.
|
| This file lives in config/ rather than in the module folder on purpose: it
| configures a VENDOR package, whose provider registers before any module, and
| an app config file is the one thing guaranteed to win over the package's own
| defaults (a module-merged config would lose to them).
*/

return [

    /*
     * The feature switch. Off by default (CLAUDE.md RULE #1): until it is on,
     * no console row, no page and no automatic drafting exists anywhere.
     */
    'enabled' => (bool) env('CERTIFICATES_ENABLED', false),

    'models' => [
        'certificate' => Ahl\Certificates\Models\Certificate::class,
        'template' => Ahl\Certificates\Models\CertificateTemplate::class,
    ],
    'policy' => App\Certificates\Support\CertificatePolicy::class,
    'host' => App\Certificates\Support\TakeOneHost::class,
    'notifier' => App\Certificates\Support\TakeOneNotifier::class,
    'files' => App\Certificates\Support\TakeOneFileStore::class,
    'pdf_renderer' => Ahl\Certificates\Support\DompdfRenderer::class,

    // A certificate goes to a person. Per-division detail travels in `details`.
    'recipients' => [
        'user' => App\Members\Models\User::class,
    ],
    'recipient_search_column' => 'name',

    'contexts' => [
        'club_event' => App\Events\Models\ClubEvent::class,
    ],

    /*
     * What a certificate can be FOR: alias => App\Certificates\Contracts\CertificateContext.
     * A belt test or a course is one more line here.
     */
    'providers' => [
        'club_event' => App\Events\Support\EventCertificates::class,
        // Officials' badges: the same engine, design room and print-shop PDF, format `badge`.
        'event_badges' => App\Events\Support\EventBadges::class,
    ],

    'types' => [
        'participation' => 'cert::messages.types.participation',
        'podium' => 'cert::messages.types.podium',
        'official_badge' => 'cert::messages.types.official_badge',
    ],

    // The page format each type is drawn in (Ahl\Certificates\Support\Formats). A type
    // not named here is a certificate: A4, as every certificate always was.
    'formats' => [
        'official_badge' => 'badge',
    ],
    'grades' => [],
    'sources' => [
        'manual' => 'cert::messages.sources.manual',
        'event_entry' => 'cert::messages.sources.event_entry',
        'event_podium' => 'cert::messages.sources.event_podium',
    ],

    'locales' => ['en', 'ar'],
    'fallback_locale' => 'en',
    'defaults_translation' => 'cert::defaults',

    'require_approval' => true,
    'notify_on_approve' => true,
    // An approved certificate's PDF is drawn the first time it is opened, and stored
    // then — never up front for a whole event, most of which nobody opens.
    'render_on_approve' => false,
    'link_minutes' => 30,

    'number' => ['prefix' => 'TK', 'digits' => 5],

    // The public page the QR opens. {token} is the 32-character random token,
    // never the numeric id (CLAUDE.md → Unpredictable Resource Identifiers).
    'verify_url' => rtrim((string) env('APP_URL', 'http://localhost'), '/').'/verify/{token}',

    'issuer' => env('APP_NAME', 'TakeOne'),

    'pdf' => [
        'view' => 'certificates::pdf.certificate',
        'paper' => 'a4',
        'orientation' => 'landscape',
    ],

    // Derived and disposable (StoragePath's cache/ root): drawn backgrounds, dompdf font metrics.
    'backgrounds_cache' => storage_path('app/cache/certificates/backgrounds'),
    'font_cache' => storage_path('app/cache/certificates/fonts'),

    'storage' => [
        'disk' => 'local',
        'directory' => 'certificates',
    ],

    // The package's own JSON API is Sanctum-token shaped and addresses
    // certificates by numeric id. TakeOne mounts its own session + CSRF routes
    // keyed by uuid instead, so the package's are never registered.
    'routes' => [
        'enabled' => false,
        'prefix' => 'api',
        'middleware' => ['api', 'auth:sanctum'],
        'public_middleware' => ['api'],
    ],

    'migrations' => true,
];
