<?php

namespace App\Printables\Support;

use App\Clubs\Models\Tenant;
use App\Events\Models\ClubEvent;
use App\Members\Models\User;
use App\Printables\Models\PrintableAsset;
use App\Printables\Models\PrintableDesign;
use App\Support\StoragePath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pictures uploaded into a design.
 *
 * Checked by their BYTES (getimagesize + finfo), never by name or the browser's
 * claim; raster only — an SVG can carry script, and the built-in graphics cover
 * what vector art the designer needs. Stored private, under the owner's own
 * subtree (CLAUDE.md → Upload Storage Structure), with a server-made name.
 */
final class AssetStore
{
    private const TYPES = [
        IMAGETYPE_JPEG => ['image/jpeg', 'jpg'],
        IMAGETYPE_PNG => ['image/png', 'png'],
        IMAGETYPE_WEBP => ['image/webp', 'webp'],
        IMAGETYPE_GIF => ['image/gif', 'gif'],
    ];

    public function store(PrintableDesign $design, UploadedFile $file, ?User $by): ?PrintableAsset
    {
        $bytes = @file_get_contents($file->getRealPath());
        if (! is_string($bytes) || $bytes === '' || strlen($bytes) > (int) config('printables.limits.asset_bytes', 12 * 1024 * 1024)) {
            return null;
        }
        $info = @getimagesizefromstring($bytes);
        $sniffed = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (! $info || ! isset(self::TYPES[$info[2]]) || self::TYPES[$info[2]][0] !== $sniffed) {
            return null;
        }
        [$mime, $ext] = self::TYPES[$info[2]];

        $uuid = (string) Str::uuid();
        $path = $this->directory($design).'/'.$uuid.'.'.$ext;
        if (! Storage::disk('local')->put($path, $bytes)) {
            return null;
        }

        $asset = new PrintableAsset([
            'design_id' => $design->id,
            'disk' => 'local',
            'path' => $path,
            'mime' => $mime,
            'width' => (int) $info[0],
            'height' => (int) $info[1],
            'bytes' => strlen($bytes),
            'created_by' => $by?->id,
        ]);
        $asset->uuid = $uuid;
        $asset->save();

        return $asset;
    }

    /**
     * The same picture, for another design: a copy of the file under that
     * design's own folder, its own row and uuid — so either design can drop it
     * without the other losing it (Delete Files Before Records). Null when the
     * source file is missing.
     */
    public function copy(PrintableAsset $from, PrintableDesign $to, ?User $by): ?PrintableAsset
    {
        $disk = Storage::disk($from->disk ?: 'local');
        if (! $from->path || ! $disk->exists($from->path)) {
            return null;
        }
        $uuid = (string) Str::uuid();
        $ext = pathinfo((string) $from->path, PATHINFO_EXTENSION) ?: 'bin';
        $path = $this->directory($to).'/'.$uuid.'.'.$ext;
        if (! Storage::disk('local')->put($path, $disk->get($from->path))) {
            return null;
        }
        $asset = new PrintableAsset([
            'design_id' => $to->id, 'disk' => 'local', 'path' => $path, 'mime' => $from->mime,
            'width' => $from->width, 'height' => $from->height, 'bytes' => $from->bytes, 'created_by' => $by?->id,
        ]);
        $asset->uuid = $uuid;
        $asset->save();

        return $asset;
    }

    /** Owner first, then this design: one subtree per owner to browse, total up or remove. */
    private function directory(PrintableDesign $design): string
    {
        $owner = $design->owner;
        $tail = 'printables/'.$design->uuid;

        return match (true) {
            $owner instanceof ClubEvent => StoragePath::event($owner, $tail),
            $owner instanceof Tenant => StoragePath::club($owner, $tail),
            $owner instanceof User => StoragePath::member($owner, $tail),
            default => StoragePath::platform($tail),
        };
    }
}
