<?php

namespace App\DevBoard\Services;

use App\DevBoard\Models\Asset;
use App\DevBoard\Models\Card;
use App\Members\Models\User;
use App\Support\StoragePath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pictures placed in notes.
 *
 * Nothing the client sent is written to disk as-is. The real bytes are sniffed
 * (JPEG, PNG, WebP, GIF — never SVG), DECODED, turned upright, scaled down and
 * RE-ENCODED as WebP by GD. What lands on disk is a fresh image this server
 * drew, so a polyglot or a payload riding in metadata does not survive; the
 * EXIF block (location included) is dropped by the same step.
 */
class Pictures
{
    private const MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    private const MAX_PIXELS = 40_000_000;

    private const PREVIEW_PX = 720;

    /** @return Asset|null null when the file is not a picture we accept */
    public function store(Card $card, User $by, UploadedFile $file): ?Asset
    {
        $bytes = @file_get_contents($file->getRealPath());
        if ($bytes === false || $bytes === '') {
            return null;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $info = @getimagesizefromstring($bytes);
        if (! in_array($mime, self::MIMES, true) || ! $info || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > self::MAX_PIXELS) {
            return null;
        }

        $img = @imagecreatefromstring($bytes);
        if (! $img) {
            return null;
        }
        $img = $this->upright($img, $mime, $bytes);

        $uuid = (string) Str::uuid();
        $dir = StoragePath::platform('devboard/cards/'.$card->uuid);
        $path = StoragePath::file($dir, $uuid, 'webp');
        $preview = StoragePath::file($dir, $uuid.'-preview', 'webp');

        [$full, $w, $h] = $this->encode($img, (int) config('devboard.limits.image_px', 2400), 84);
        [$small] = $this->encode($img, self::PREVIEW_PX, 78);
        imagedestroy($img);

        if ($full === null || $small === null) {
            return null;
        }

        $disk = Storage::disk('local');
        $disk->put($path, $full);
        $disk->put($preview, $small);

        return Asset::create([
            'uuid' => $uuid,
            'card_id' => $card->id,
            'path' => $path,
            'preview_path' => $preview,
            'width' => $w,
            'height' => $h,
            'bytes' => strlen($full),
            'created_by' => $by->id,
        ]);
    }

    /** A phone photo carries its rotation in EXIF; GD ignores it, so apply it. */
    private function upright(\GdImage $img, string $mime, string $bytes): \GdImage
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $img;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($bytes));
        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180, 6 => -90, 8 => 90, default => 0,
        };

        if ($angle === 0) {
            return $img;
        }

        $rotated = imagerotate($img, $angle, 0);
        if ($rotated) {
            imagedestroy($img);

            return $rotated;
        }

        return $img;
    }

    /** @return array{0:?string,1:int,2:int} webp bytes, width, height */
    private function encode(\GdImage $img, int $longSide, int $quality): array
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $scale = min(1, $longSide / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $out = imagecreatetruecolor($nw, $nh);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);

        ob_start();
        $ok = imagewebp($out, null, $quality);
        $data = ob_get_clean();
        imagedestroy($out);

        return [$ok && $data ? $data : null, $nw, $nh];
    }
}
