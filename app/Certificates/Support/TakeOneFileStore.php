<?php

namespace App\Certificates\Support;

use Ahl\Certificates\Contracts\FileStore;
use Ahl\Certificates\Models\Certificate;
use Ahl\Certificates\Models\CertificateTemplate;
use App\Members\Models\User;
use App\Support\StoragePath;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Where certificate files live — owner first, public ids, generated names
 * (CLAUDE.md → Upload Storage Structure), on the private `local` disk. None of
 * these paths is ever a URL: every file is served by a controller that
 * authorises first.
 *
 *   events/{event-uuid}/certificates/{certificate-uuid}.pdf          an event's
 *   members/{user-uuid}/certificates/{certificate-uuid}.pdf          no context
 *   {owner root}/certificates/templates/{template-uuid}/{slot}       signatures, artwork
 *
 * The slot names are the package's own fixed words (pdf, signature_1,
 * signature_2, background), never anything a client sent.
 */
class TakeOneFileStore implements FileStore
{
    private const SLOTS = ['pdf', 'signature_1', 'signature_2', 'background'];

    public function __construct(private Contexts $contexts) {}

    public function put(Model $owner, string $slot, string $contents, string $mime, string $filename): void
    {
        $path = $this->path($owner, $slot);
        if ($path !== null) {
            $this->disk()->put($path, $contents);
        }
    }

    public function get(Model $owner, string $slot): ?array
    {
        $path = $this->path($owner, $slot);
        if ($path === null || ! $this->disk()->exists($path)) {
            return null;
        }
        $contents = (string) $this->disk()->get($path);

        return ['contents' => $contents, 'mime' => (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents) ?: 'application/octet-stream'];
    }

    public function exists(Model $owner, string $slot): bool
    {
        $path = $this->path($owner, $slot);

        return $path !== null && $this->disk()->exists($path);
    }

    public function delete(Model $owner, string $slot): void
    {
        $path = $this->path($owner, $slot);
        if ($path !== null) {
            $this->disk()->delete($path);
        }
    }

    /** Every file a template holds — for deleting a template (files before the row). */
    public function purgeTemplate(CertificateTemplate $template): void
    {
        foreach (['signature_1', 'signature_2', 'background'] as $slot) {
            $this->delete($template, $slot);
        }
    }

    public function path(Model $owner, string $slot): ?string
    {
        if (! in_array($slot, self::SLOTS, true)) {
            return null;
        }

        if ($owner instanceof Certificate && $slot === 'pdf') {
            if (! $owner->uuid) {
                return null;
            }
            $context = $owner->context_id ? $owner->context : null;
            $provider = $this->contexts->forModel($context);
            $root = $provider && $context
                ? $provider->storageRoot($context)
                : ($owner->recipient instanceof User ? StoragePath::member($owner->recipient, 'certificates') : StoragePath::platform('certificates'));

            return StoragePath::file($root, $owner->uuid, 'pdf');
        }

        if ($owner instanceof CertificateTemplate && $slot !== 'pdf' && $owner->uuid) {
            $key = [(string) $owner->owner_type, (int) $owner->owner_id];
            $root = $key[0] !== '' ? $this->contexts->forOwnerType($key[0])?->ownerStorageRoot($key) : null;

            return rtrim($root ?? StoragePath::platform('certificates'), '/').'/templates/'.$owner->uuid.'/'.$slot;
        }

        return null;
    }

    private function disk(): Filesystem
    {
        return Storage::disk('local');
    }
}
