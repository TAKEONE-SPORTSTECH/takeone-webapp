<?php

namespace App\Certificates\Jobs;

use Ahl\Certificates\CertificateService;
use App\Certificates\Support\CertificateBundles;
use App\Certificates\Support\CertificateRealtime;
use App\Certificates\Support\Contexts;
use App\Members\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Draws one design's issued certificates into one PDF (Support\CertificateBundles).
 * Runs on the `media` queue — the www-data worker with a long timeout — because a
 * hundred pages take half a minute. Re-checks that the person who asked still runs
 * the context, then tells everyone who does that the file is ready.
 */
class BuildCertificateBundle implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 1200;

    public int $tries = 1;

    public function __construct(public string $context, public string $key, public string $type, public int $userId) {}

    public function handle(Contexts $contexts, CertificateBundles $bundles, CertificateService $service): void
    {
        $found = $contexts->resolve($this->context, $this->key);
        $user = User::find($this->userId);
        if (! $found || ! $user || ! $found[0]->canManage($found[1], $user) || ! in_array($this->type, $found[0]->types(), true)) {
            return;
        }
        [$provider, $model] = $found;
        ini_set('memory_limit', '1024M');

        $path = $bundles->build($provider, $model, $this->type, fn (array $list, callable $progress) => $service->bundle($list, CertificateService::PURPOSE_DOWNLOAD, $progress));
        Log::info('certificates.bundle_built', ['context' => $this->context, 'key' => $this->key, 'type' => $this->type, 'by' => $this->userId, 'path' => $path]);
        CertificateRealtime::refresh($provider->audience($model));
    }
}
