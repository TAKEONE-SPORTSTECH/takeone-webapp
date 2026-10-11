<?php

namespace App\Events\Support;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventPhoto;
use App\Members\Models\User;
use App\Support\StoragePath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * An event's photographs — the gallery's pictures, beside its bout videos.
 *
 * Every upload is DECODED and RE-ENCODED here, never stored as sent:
 *   · the real bytes decide what it is (finfo + a successful GD decode), so a
 *     script with an image header never reaches the disk;
 *   · re-encoding drops every byte of metadata — a phone photo's EXIF carries
 *     the GPS position it was taken at, and these are published pictures of
 *     children at a competition;
 *   · the stored name and extension are ours (a ULID + `.jpg`).
 *
 * Two renditions: a display copy (long side ≤ 2400px) and a thumbnail
 * (≤ 720px) for the grid. Both on the private disk, served only by
 * EventPhotoController.
 */
class EventPhotos
{
    /** The application's ceiling for one picture (PHP's upload limit also applies). */
    public const MAX_BYTES = 15 * 1024 * 1024;

    /** Refuse anything that would decode into more pixels than this (decompression bombs). */
    private const MAX_PIXELS = 36_000_000;

    private const FULL_EDGE = 2400;

    private const THUMB_EDGE = 720;

    private const ALLOWED = ['image/jpeg', 'image/png', 'image/webp'];

    public static function enabled(): bool
    {
        return (bool) config('events.photos', false);
    }

    /** Store one upload for this event, or null when it is not a picture we accept. */
    public function store(UploadedFile $file, ClubEvent $event, User $by): ?EventPhoto
    {
        if (! $file->isValid() || (int) $file->getSize() <= 0 || $file->getSize() > self::MAX_BYTES) {
            return null;
        }

        $path = $file->getRealPath();
        if (! $path || ! is_readable($path)) {
            return null;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: '';
        if (! in_array($mime, self::ALLOWED, true)) {
            return null;
        }

        $info = @getimagesize($path);
        if ($info === false || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > self::MAX_PIXELS) {
            return null;
        }

        // A 36 MP decode is ~150 MB of GD memory; the default ceiling is lower.
        $limit = ini_get('memory_limit');
        @ini_set('memory_limit', '512M');

        try {
            $img = match ($mime) {
                'image/jpeg' => @imagecreatefromjpeg($path),
                'image/png' => @imagecreatefrompng($path),
                'image/webp' => @imagecreatefromwebp($path),
            };

            if (! $img) {
                return null;
            }

            $img = $this->upright($img, $mime, $path);

            $full = $this->fit($img, self::FULL_EDGE);
            $thumb = $this->fit($img, self::THUMB_EDGE);
            imagedestroy($img);

            $folder = StoragePath::event($event, 'photos');
            $name = strtolower((string) Str::ulid());
            $fullPath = $folder.'/'.$name.'.jpg';
            $thumbPath = $folder.'/'.$name.'-thumb.jpg';

            $fullBytes = $this->jpeg($full, 85);
            $thumbBytes = $this->jpeg($thumb, 78);
            $width = imagesx($full);
            $height = imagesy($full);
            imagedestroy($full);
            imagedestroy($thumb);

            $disk = Storage::disk('local');
            if (! $disk->put($fullPath, $fullBytes) || ! $disk->put($thumbPath, $thumbBytes)) {
                $disk->delete([$fullPath, $thumbPath]);

                return null;
            }

            return EventPhoto::create([
                'event_id' => $event->id,
                'path' => $fullPath,
                'thumb_path' => $thumbPath,
                'width' => $width,
                'height' => $height,
                'bytes' => strlen($fullBytes),
                'uploaded_by' => $by->id,
            ]);
        } finally {
            @ini_set('memory_limit', (string) $limit);
        }
    }

    /**
     * The gallery's pictures as the page reads them. Only addresses and sizes —
     * never a storage path, never who uploaded it.
     *
     * @return list<array<string, mixed>>
     */
    public function forEvent(ClubEvent $event, bool $canManage = false): array
    {
        if (! self::enabled()) {
            return [];
        }

        return $event->photos()->limit(1000)->get()
            ->map(fn (EventPhoto $p) => $this->present($p, $event, $canManage))
            ->all();
    }

    public function count(ClubEvent $event): int
    {
        return self::enabled() ? $event->photos()->count() : 0;
    }

    /** @return array<string, mixed> */
    public function present(EventPhoto $photo, ClubEvent $event, bool $canManage = false): array
    {
        $key = ['event' => $event->uuid, 'photo' => $photo->uuid];

        return [
            'uuid' => $photo->uuid,
            'w' => $photo->width,
            'h' => $photo->height,
            'thumb' => route('events.photos.show', $key + ['size' => 'thumb']),
            'full' => route('events.photos.show', $key + ['size' => 'full']),
            'caption' => $photo->caption,
            'delete_url' => $canManage ? route('me.events.photos.destroy', $key) : null,
        ];
    }

    /** Turn a JPEG the way its camera meant it — the orientation tag is lost on re-encode. */
    private function upright(\GdImage $img, string $mime, string $path): \GdImage
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $img;
        }

        $exif = @exif_read_data($path);
        $o = (int) ($exif['Orientation'] ?? 1);

        $turned = match ($o) {
            3 => imagerotate($img, 180, 0),
            6 => imagerotate($img, -90, 0),
            8 => imagerotate($img, 90, 0),
            default => null,
        };

        if ($turned instanceof \GdImage) {
            imagedestroy($img);

            return $turned;
        }

        return $img;
    }

    /** A copy whose long side is at most $edge, on white (a PNG's transparency has nowhere to go in a JPEG). */
    private function fit(\GdImage $img, int $edge): \GdImage
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $scale = min(1, $edge / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $out = imagecreatetruecolor($nw, $nh);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);

        return $out;
    }

    private function jpeg(\GdImage $img, int $quality): string
    {
        imageinterlace($img, true);
        ob_start();
        imagejpeg($img, null, $quality);

        return (string) ob_get_clean();
    }
}
