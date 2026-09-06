<?php

namespace App\Media;

use App\Jobs\TranscodeMedia;
use App\Models\MediaFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * A file arrives; this is what happens to it.
 *
 * The one door into media. A camera's clip, an organiser's upload and anything
 * later all come through here, so the rules — where it goes, what it is named,
 * what gets probed, when it is transcoded — are stated once.
 *
 * ── What it deliberately does not do ───────────────────────────────────────
 *
 * It does not decide who may see the file (the owner does, at serve time), and
 * it does not know what a bout is. It takes bytes and an owner and returns a
 * media file. Everything about competitions stays in the event packages.
 */
class MediaIngest
{
    /** The container formats we accept. Anything else is refused, not guessed. */
    private const EXTENSIONS = ['mp4', 'mov', 'm4v', 'webm', 'mkv'];

    public function __construct(
        private MediaVaults $vaults,
        private Ffmpeg $ffmpeg,
    ) {}

    /**
     * Take a finished scratch file and make it media.
     *
     * The scratch file is CONSUMED — moved onto its storage when they share a
     * filesystem, copied when they do not. Callers must not use the path again.
     *
     * @param  string  $scratchAbs  the assembled upload, on local disk
     * @param  string  $directory  the canonical folder, from App\Support\StoragePath
     * @param  Model|null  $owner  what these bytes belong to (a clip, a recording)
     * @param  array<string,mixed>  $attributes  extra columns, e.g. original_name
     */
    public function fromFile(
        string $scratchAbs,
        string $directory,
        ?Model $owner = null,
        ?int $eventId = null,
        string $kind = MediaFile::KIND_CLIP,
        array $attributes = [],
    ): ?MediaFile {
        if (! is_file($scratchAbs) || filesize($scratchAbs) === 0) {
            Log::warning('media ingest: nothing to ingest', ['path' => $scratchAbs]);

            return null;
        }

        // Probe BEFORE the file moves: on an SMB vault it would have to be
        // fetched back to be read, which is a needless round trip of gigabytes.
        $probe = $this->ffmpeg->probe($scratchAbs);

        // And take its fingerprint while it is still here and local. This is what
        // a later migration verifies the copy against, and what tells "the same
        // bout uploaded twice" from "two bouts". Computed once, in a queue job,
        // never on a request.
        $checksum = @hash_file('sha256', $scratchAbs) ?: null;

        // The extension is decided HERE, from a whitelist, and the stored name is
        // a uuid — an uploaded filename never reaches a path (Upload Storage
        // Structure and File Naming). The DIRECTORY comes from the caller, built
        // by App\Support\StoragePath, which is where the platform's layout lives.
        $extension = $this->extensionFor($scratchAbs, $attributes['original_name'] ?? null);
        $uuid = (string) Str::uuid();
        $relPath = $this->vaults->pathFor($directory, $uuid, $extension);

        $file = $this->vaults->store($scratchAbs, $relPath, array_merge([
            'kind' => $kind,
            'mime' => $this->mimeFor($extension),
            'duration_seconds' => $probe['duration'],
            'width' => $probe['width'],
            'height' => $probe['height'],
            'checksum' => $checksum,
            'meta' => array_filter([
                'orientation' => $probe['orientation'],
                'event_id' => $eventId,
            ]),
        ], $attributes, [
            'owner_type' => $owner ? $owner->getMorphClass() : null,
            'owner_id' => $owner?->getKey(),
        ]));

        if ($file->status === MediaFile::STATUS_FAILED) {
            Log::error('media ingest: could not store the file anywhere', ['file' => $file->uuid]);

            return $file;
        }

        // Watchable is a separate, slower step, and it must not hold up the
        // camera that is waiting for a reply.
        TranscodeMedia::dispatch($file->uuid)->onQueue('media');

        Log::info('media ingest: stored', [
            'file' => $file->uuid,
            'vault' => $file->vault?->name ?? 'local',
            'bytes' => $file->bytes,
            'kind' => $kind,
        ]);

        return $file;
    }

    /**
     * The extension to store under, from what the bytes actually are.
     *
     * The client's filename is a hint of last resort, never the answer: the
     * container is read from the file itself, and a name that disagrees loses.
     */
    private function extensionFor(string $absPath, ?string $originalName): string
    {
        $mime = (string) (@mime_content_type($absPath) ?: '');

        $fromMime = match (true) {
            str_contains($mime, 'quicktime') => 'mov',
            str_contains($mime, 'webm') => 'webm',
            str_contains($mime, 'matroska') => 'mkv',
            str_contains($mime, 'mp4') => 'mp4',
            default => null,
        };

        if ($fromMime !== null) {
            return $fromMime;
        }

        $claimed = strtolower((string) pathinfo((string) $originalName, PATHINFO_EXTENSION));

        return in_array($claimed, self::EXTENSIONS, true) ? $claimed : 'mp4';
    }

    private function mimeFor(string $extension): string
    {
        return match ($extension) {
            'mov' => 'video/quicktime',
            'webm' => 'video/webm',
            'mkv' => 'video/x-matroska',
            default => 'video/mp4',
        };
    }
}
