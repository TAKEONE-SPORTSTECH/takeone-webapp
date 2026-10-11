<?php

namespace App\Certificates\Support;

use Ahl\Certificates\Certificates as Engine;
use App\Certificates\Contracts\CertificateContext;
use App\Certificates\Contracts\PrintsPages;
use App\Certificates\Jobs\BuildCertificateBundle;
use App\Support\StoragePath;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Every issued certificate of one design, as ONE PDF — the file a print shop is sent.
 *
 * Drawing a hundred pages takes half a minute, so it is built in the background
 * (Jobs\BuildCertificateBundle) and kept: under cache/ (derived, safe to delete), named
 * by a signature of what it holds — which certificates, and when they and their design
 * last changed. Ask again with nothing changed and the same file answers at once; issue
 * one more, or touch the design, and the next ask builds a fresh one.
 *
 * One file per design, not one for the event: a dompdf PDF has a single page shape,
 * and a participation design may stand upright while the podium one lies flat.
 */
class CertificateBundles
{
    /** How long a "building" mark survives a worker that died mid-way. */
    private const BUILDING_TTL = 1800;

    /** @return list<array{type: string, count: int, state: string, done: int, total: int, size: ?int}> */
    public function states(CertificateContext $provider, Model $context): array
    {
        $out = [];
        foreach ($provider->types() as $type) {
            $count = $provider instanceof PrintsPages ? $provider->printCount($context, $type) : $this->issued($context, $type)->count();
            if ($count > 0) {
                $out[] = ['type' => $type, 'label' => Engine::label('types', $type, app()->getLocale()), 'count' => $count] + $this->state($provider, $context, $type);
            }
        }

        return $out;
    }

    /** @return array{state: string, done: int, total: int, size: ?int} */
    public function state(CertificateContext $provider, Model $context, string $type): array
    {
        $path = $this->path($provider, $context, $type);
        if ($path && Storage::disk('local')->exists($path)) {
            return ['state' => 'ready', 'done' => 0, 'total' => 0, 'size' => Storage::disk('local')->size($path)];
        }
        $mark = Cache::get($this->markKey($provider, $context, $type));
        if (is_array($mark) && ($mark['sig'] ?? null) === $this->signatureFor($provider, $context, $type)) {
            return ['state' => ($mark['failed'] ?? false) ? 'failed' : 'building', 'done' => (int) ($mark['done'] ?? 0), 'total' => (int) ($mark['total'] ?? 0), 'size' => null];
        }

        return ['state' => 'idle', 'done' => 0, 'total' => 0, 'size' => null];
    }

    /** Start a build unless one is ready or already running. */
    public function start(CertificateContext $provider, Model $context, string $type, int $byUserId): array
    {
        $state = $this->state($provider, $context, $type);
        if (in_array($state['state'], ['ready', 'building'], true)) {
            return $state;
        }
        $total = $provider instanceof PrintsPages ? $provider->printCount($context, $type) : $this->issued($context, $type)->count();
        if ($total === 0) {
            return $state;
        }
        $this->mark($provider, $context, $type, 0, $total);
        BuildCertificateBundle::dispatch($provider->alias(), $provider->key($context), $type, $byUserId)->onQueue('media');

        return $this->state($provider, $context, $type);
    }

    /** Draw it (called by the job). Older files for this design go once the new one is in place. */
    public function build(CertificateContext $provider, Model $context, string $type, callable $bundle): ?string
    {
        $path = $this->path($provider, $context, $type);
        $tick = fn (int $done, int $total) => $done % 5 === 0 ? $this->mark($provider, $context, $type, $done, $total) : null;
        $list = $provider instanceof PrintsPages ? [] : $this->issued($context, $type)->with(['recipient', 'context', 'template'])->get()->all();
        if (! $path || ($list === [] && ! ($provider instanceof PrintsPages && $provider->printCount($context, $type) > 0))) {
            return null;
        }
        try {
            // Pages that are not certificate records (badges) are drawn by their own context.
            $pdf = $provider instanceof PrintsPages ? $provider->printAll($context, $type, $tick) : $bundle($list, $tick);
        } catch (\Throwable $e) {
            $this->mark($provider, $context, $type, 0, $provider instanceof PrintsPages ? $provider->printCount($context, $type) : count($list), true);
            throw $e;
        }
        $disk = Storage::disk('local');
        $disk->put($path, $pdf);
        foreach ($disk->files(dirname($path)) as $old) {
            if ($old !== $path && str_starts_with(basename($old), $type.'-')) {
                $disk->delete($old);
            }
        }
        Cache::forget($this->markKey($provider, $context, $type));

        return $path;
    }

    public function path(CertificateContext $provider, Model $context, string $type): ?string
    {
        if (! preg_match('/^[a-z_]{2,32}$/', $type)) {
            return null;
        }

        return StoragePath::CACHE.'/certificates/bundles/'.$provider->alias().'-'.$provider->key($context).'/'.$type.'-'.$this->signatureFor($provider, $context, $type).'.pdf';
    }

    private function signatureFor(CertificateContext $provider, Model $context, string $type): string
    {
        return $provider instanceof PrintsPages ? $provider->printSignature($context, $type) : $this->signature($context, $type);
    }

    /** What the file holds: these certificates, as they and their designs stand now. */
    private function signature(Model $context, string $type): string
    {
        $rows = $this->issued($context, $type)->get(['id', 'updated_at', 'template_id']);
        $templates = Engine::templateModel()::query()->whereIn('id', $rows->pluck('template_id')->filter()->unique())->pluck('updated_at', 'id');

        return substr(sha1($rows->map(fn ($r) => $r->id.'@'.$r->updated_at)->implode(',').'|'.$templates->map(fn ($t, $id) => $id.'@'.$t)->implode(',')), 0, 16);
    }

    private function issued(Model $context, string $type)
    {
        return Engine::model()::query()->forContext($context)->where('status', 'approved')->where('type', $type)->orderBy('id');
    }

    private function mark(CertificateContext $provider, Model $context, string $type, int $done, int $total, bool $failed = false): void
    {
        Cache::put($this->markKey($provider, $context, $type), ['sig' => $this->signatureFor($provider, $context, $type), 'done' => $done, 'total' => $total, 'failed' => $failed], self::BUILDING_TTL);
    }

    private function markKey(CertificateContext $provider, Model $context, string $type): string
    {
        return 'cert-bundle:'.$provider->alias().':'.$provider->key($context).':'.$type;
    }
}
