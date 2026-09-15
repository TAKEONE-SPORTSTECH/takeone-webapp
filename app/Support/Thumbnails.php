<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Small renditions of stored images, for the lists that paint them tiny.
 *
 * ── Why ─────────────────────────────────────────────────────────────────────
 * A photograph taken at a weigh-in desk is whatever the phone produced —
 * 1836×3264 and half a megabyte is ordinary. An entry list draws that in a
 * 42×56 box, sixty times. The event people page was shipping 5.6 MB of camera
 * originals to paint thumbnails smaller than a postage stamp, and the organiser
 * watched them arrive one by one on the morning they had least patience for it.
 *
 * So the card asks for a SIZE and gets a file made for it. The original is
 * untouched and is still what the bout screens, the lightbox and any download
 * read — this only adds a derivative beside it.
 *
 * ── The rules this obeys ────────────────────────────────────────────────────
 * · **Named sizes only.** A caller picks from SIZES; a number from a request
 *   would let a stranger fill the disk one pixel at a time, and generating an
 *   image is expensive work behind a cheap URL (CLAUDE.md Part 3 §14).
 * · **Authorisation is NOT here.** A thumbnail of a private photograph is just
 *   as private. FileController checks the SOURCE path with FileAccess before it
 *   ever asks for a rendition, exactly as it does when serving the original.
 * · **Derived, therefore disposable.** Everything lands under `cache/thumbs/`
 *   (StoragePath::thumb). Delete the tree to reclaim disk; the next request
 *   redraws what it needs.
 * · **Never fails the page.** Anything unreadable, animated, or too odd to
 *   resize returns null and the caller serves the original. A missing thumbnail
 *   is a slow card; an exception is a blank one.
 */
class Thumbnails
{
    /**
     * The sizes the product actually paints, as [max width, max height].
     *
     * Generous relative to the CSS box because phone screens are 2–3×: the card
     * paints 42×56, so `card` is drawn at 3× and still costs a tenth of the
     * original. Add a name here when a surface needs one — never a free number.
     */
    public const SIZES = [
        'card' => [126, 168],   // an entrant card's portrait, at 3×
        'row' => [96, 96],      // a square list avatar
        'tile' => [400, 400],   // a club logo / cover thumbnail
    ];

    /** Bytes above which an image is left alone — a guard, not a limit. */
    private const MAX_SOURCE_BYTES = 25 * 1024 * 1024;

    /** JPEG quality. 82 is indistinguishable at this scale and ~half the bytes of 92. */
    private const QUALITY = 82;

    public static function knows(string $size): bool
    {
        return isset(self::SIZES[$size]);
    }

    /**
     * The stored path of a rendition, making it if it is not there yet.
     *
     * @return string|null  a path on the `local` disk, or null to use the original
     */
    public static function for(string $sourcePath, string $size): ?string
    {
        if (! self::knows($size)) {
            return null;
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($sourcePath)) {
            return null;
        }

        $target = StoragePath::thumb($sourcePath, $size);

        /* Already made, and still younger than its source. A photograph that is
           replaced in place (the desk retakes one) must not keep serving the
           face that was there before. */
        if ($disk->exists($target)
            && $disk->lastModified($target) >= $disk->lastModified($sourcePath)) {
            return $target;
        }

        return self::make($disk->path($sourcePath), $target, $size) ? $target : null;
    }

    /**
     * Draw the rendition. Every failure is swallowed into `false` — the caller
     * serves the original, which is slow but correct.
     */
    private static function make(string $absoluteSource, string $target, string $size): bool
    {
        try {
            if (! is_file($absoluteSource) || filesize($absoluteSource) > self::MAX_SOURCE_BYTES) {
                return false;
            }

            $info = @getimagesize($absoluteSource);

            if (! $info || $info[0] < 1 || $info[1] < 1) {
                return false;
            }

            [$srcW, $srcH] = $info;
            [$maxW, $maxH] = self::SIZES[$size];

            // Already small enough: copying the bytes is cheaper than re-encoding
            // them, and re-encoding a small JPEG only makes it worse.
            if ($srcW <= $maxW && $srcH <= $maxH) {
                return false;
            }

            $image = self::read($absoluteSource, $info[2]);

            if (! $image) {
                return false;
            }

            // Fit inside the box, never stretch: a face squeezed to a different
            // aspect is a different face.
            $scale = min($maxW / $srcW, $maxH / $srcH);
            $dstW = max(1, (int) round($srcW * $scale));
            $dstH = max(1, (int) round($srcH * $scale));

            $canvas = imagecreatetruecolor($dstW, $dstH);

            // A transparent PNG flattens onto white rather than onto black,
            // which is what the JPEG below would otherwise give it.
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefilledrectangle($canvas, 0, 0, $dstW, $dstH, $white);
            imagecopyresampled($canvas, $image, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
            imagedestroy($image);

            $disk = Storage::disk('local');
            $disk->makeDirectory(dirname($target));

            // Written beside the target and moved into place, so a request that
            // arrives mid-write never reads half an image.
            $temp = $disk->path($target).'.'.bin2hex(random_bytes(4)).'.tmp';
            $ok = imagejpeg($canvas, $temp, self::QUALITY);
            imagedestroy($canvas);

            if (! $ok || ! is_file($temp)) {
                @unlink($temp);

                return false;
            }

            return rename($temp, $disk->path($target));
        } catch (\Throwable $e) {
            Log::warning('Thumbnail failed', ['size' => $size, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /** Decode only the formats we are sure of — never guess from a name. */
    private static function read(string $path, int $type)
    {
        return match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
            // A GIF may be animated, and resizing one throws the animation away.
            default => null,
        } ?: null;
    }
}
