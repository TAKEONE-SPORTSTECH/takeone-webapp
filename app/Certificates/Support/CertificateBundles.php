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
            // Nothing to draw: say so at once, rather than leaving "building" up for half an hour.
            Cache::forget($this->markKey($provider, $context, $type));

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
        // A type, or a type for one job (`official_thanks.referee`) — the route's own pattern.
        if (! preg_match('/^[a-z_]{2,32}(\.[a-z0-9_-]{1,48})?$/', $type)) {
            return null;
        }

        return StoragePath::CACHE.'/certificates/bundles/'.$provider->alias().'-'.$provider->key($context).'/'.$type.'-'.$this->signatureFor($provider, $context, $type).'.pdf';
    }

    private function signatureFor(CertificateContext $provider, Model $context, string $type): string
    {
        return $provider instanceof PrintsPages ? $provider->printSignature($context, $type) : $this->signature($provider, $context, $type);
    }

    /** What the file holds: these certificates, as they and their designs stand now. */
    private function signature(CertificateContext $provider, Model $context, string $type): string
    {
        $rows = $this->issued($context, $type)->with(['recipient', 'context'])->get();
        $templates = Engine::templateModel()::query()->whereIn('id', $rows->pluck('template_id')->filter()->unique())->pluck('updated_at', 'id');
        // Drawn from the new designer: the file is stale the moment the design changes
        // (and a bundle built before the designer printed — 'pd1' — is never reused).
        $base = explode('.', $type, 2)[0];
        $design = \App\Printables\Designer::enabled() ? \App\Printables\Designer::find($context, 'certificate', $base) : null;
        $designed = \App\Printables\Designer::enabled() ? 'pd1:'.($design ? $design->uuid.'@'.$design->revision : 'starter') : '';
        // A job's own design of this type (`official_thanks.referee`) is part of the file too.
        if (\App\Printables\Designer::enabled()) {
            $designed .= '|'.\App\Printables\Models\PrintableDesign::query()->where('kind', 'certificate')
                ->where('owner_type', $context->getMorphClass())->where('owner_id', $context->getKey())
                ->where('slot', 'like', $base.'.%')->orderBy('id')->get(['uuid', 'revision'])
                ->map(fn ($d) => $d->uuid.'@'.$d->revision)->implode(',');
        }

        // Everything a page draws from outside its own row — the holder's name, face and
        // nationality, the club's name, crest and country (the context's pageStamp) — so
        // correcting a person or a club never hands back the file printed before it.
        $stamp = fn ($r) => method_exists($provider, 'pageStamp') ? '@'.$provider->pageStamp($r)
            : ($r->recipient && method_exists($r->recipient, 'certificateStamp') ? '@'.$r->recipient->certificateStamp() : '');

        return substr(sha1($rows->map(fn ($r) => $r->id.'@'.$r->updated_at.$stamp($r))->implode(',').'|'.$templates->map(fn ($t, $id) => $id.'@'.$t)->implode(',').'|'.$designed), 0, 16);
    }

    /**
     * May this be bundled here? A type the provider issues — or one of its
     * types for ONE JOB (`official_thanks.referee`), where the provider can say
     * who holds that job (bundleRecipients).
     */
    public function accepts(CertificateContext $provider, Model $context, string $type): bool
    {
        [$base, $job] = array_pad(explode('.', $type, 2), 2, null);
        if (! in_array($base, $provider->types(), true)) {
            return false;
        }
        if ($job !== null && str_starts_with($job, 'sel-')) {
            return $this->remembered($provider, $context, substr($job, 4)) !== null;
        }

        return $job === null || (method_exists($provider, 'bundleRecipients') && $provider->bundleRecipients($context, $base, $job) !== null);
    }

    /**
     * Remember a chosen set of certificates for two days and name it: the
     * bundle type that prints exactly them (`<type>.sel-<token>`). The same
     * set always gets the same name, so asking twice reuses one file.
     *
     * @param  list<int>  $ids  certificate ids, already checked to be this context's
     */
    public function remember(CertificateContext $provider, Model $context, string $type, array $ids): string
    {
        sort($ids);
        $token = substr(sha1($type.'|'.implode(',', $ids)), 0, 16);
        Cache::put($this->selectionKey($provider, $context, $token), $ids, now()->addDays(2));

        return $type.'.sel-'.$token;
    }

    /** @return list<int>|null */
    private function remembered(CertificateContext $provider, Model $context, string $token): ?array
    {
        if (! preg_match('/^[a-f0-9]{16}$/', $token)) {
            return null;
        }
        $ids = Cache::get($this->selectionKey($provider, $context, $token));

        return is_array($ids) && $ids !== [] ? $ids : null;
    }

    private function selectionKey(CertificateContext $provider, Model $context, string $token): string
    {
        return 'cert-sel:'.$provider->alias().':'.$provider->key($context).':'.$token;
    }

    private function issued(Model $context, string $type)
    {
        [$base, $job] = array_pad(explode('.', $type, 2), 2, null);
        $query = Engine::model()::query()->forContext($context)->where('status', 'approved')->where('type', $base)->orderBy('id');
        if ($job !== null && str_starts_with($job, 'sel-')) {
            // A chosen set (remember()): exactly those certificates, still issued.
            $provider = app(Contexts::class)->forModel($context);
            $query->whereIn('id', ($provider ? $this->remembered($provider, $context, substr($job, 4)) : null) ?: [0]);
        } elseif ($job !== null) {
            // One job's certificates only: whoever holds that job (an unknown job matches nobody).
            $provider = app(Contexts::class)->forModel($context);
            $ids = $provider && method_exists($provider, 'bundleRecipients') ? ($provider->bundleRecipients($context, $base, $job) ?? []) : [];
            $query->where('recipient_type', (new \App\Members\Models\User)->getMorphClass())->whereIn('recipient_id', $ids ?: [0]);
        }

        return $query;
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
