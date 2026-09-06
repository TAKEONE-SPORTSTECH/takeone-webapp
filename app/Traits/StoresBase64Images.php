<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;

trait StoresBase64Images
{
    /**
     * Allowed image MIME types and the extension we assign them.
     * SVG is intentionally excluded — SVG files can carry embedded
     * JavaScript and execute as markup in the browser.
     */
    private const ALLOWED_IMAGE_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    /**
     * Decode a base64 data-URI, verify its actual binary content is an
     * allowed image type, then store it and return the storage path.
     *
     * Returns null if the input is missing, malformed, or not a
     * whitelisted image type — callers should treat null as a no-op.
     */
    private function storeBase64Image(string $base64, string $folder, string $filenameBase, string $disk = 'public'): ?string
    {
        // Must look like a data URI before we do anything else.
        if (! str_starts_with($base64, 'data:image')) {
            return null;
        }

        // Split into header and payload.  Bail if malformed.
        $parts = explode(';base64,', $base64, 2);
        if (count($parts) !== 2) {
            return null;
        }

        // Strict base64 decode — returns false on invalid characters.
        $binary = base64_decode($parts[1], strict: true);
        if ($binary === false || $binary === '') {
            return null;
        }

        // Inspect the actual bytes, not the client-supplied header.
        // finfo is bundled with PHP 8+ and always available.
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($binary);

        // Reject anything not in the whitelist — this stops PHP/HTML/SVG/EXE
        // files even if they claim to be images in their data-URI header.
        if (! array_key_exists($mimeType, self::ALLOWED_IMAGE_TYPES)) {
            return null;
        }

        // Extension comes from our map, never from client input.
        $extension = self::ALLOWED_IMAGE_TYPES[$mimeType];
        $fullPath = trim($folder, '/').'/'.$filenameBase.'.'.$extension;

        // A write can fail for reasons that have nothing to do with the bytes —
        // a folder the web user cannot write into, a full disk. `put()` reports
        // that by returning false, and this used to ignore it and hand back the
        // path anyway: the caller then saved a path to a file that was never
        // written, told the user it had worked, and the picture came back 404
        // on every screen that asked for it. Silence is the worst outcome here,
        // so it is logged as well as refused — a permissions fault is an
        // operator's problem and cannot be diagnosed from a 422.
        if (Storage::disk($disk)->put($fullPath, $binary) === false) {
            report(new \RuntimeException("Failed to store uploaded image at [{$disk}://{$fullPath}]."));

            return null;
        }

        return $fullPath;
    }

    /**
     * Like storeBase64Image(), but re-encodes to a size-optimized image before
     * storing: caps the longest edge and encodes WebP (falling back to JPEG when
     * the GD build lacks WebP). Best "max quality / minimal disk" tradeoff for
     * user-uploaded photos and certificate scans.
     *
     * Keeps the same security guarantees — the real MIME is sniffed from the bytes
     * and non-image / SVG payloads are rejected before GD ever touches them.
     *
     * Returns the stored path, or null on any failure (treat as a no-op).
     */
    private function storeOptimizedBase64Image(
        string $base64,
        string $folder,
        string $filenameBase,
        int $maxEdge = 1600,
        int $quality = 82,
        string $disk = 'public'
    ): ?string {
        if (! str_starts_with($base64, 'data:image')) {
            return null;
        }
        $parts = explode(';base64,', $base64, 2);
        if (count($parts) !== 2) {
            return null;
        }
        $binary = base64_decode($parts[1], strict: true);
        if ($binary === false || $binary === '') {
            return null;
        }

        // Sniff real bytes — reject anything not a whitelisted raster image.
        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary);
        if (! array_key_exists($mimeType, self::ALLOWED_IMAGE_TYPES)) {
            return null;
        }

        // GD must be present to re-encode; without it, store the bytes unchanged.
        if (! function_exists('imagecreatefromstring')) {
            $ext = self::ALLOWED_IMAGE_TYPES[$mimeType];
            $path = trim($folder, '/').'/'.$filenameBase.'.'.$ext;

            // Same as above: a refused write must never be reported as a stored file.
            if (Storage::disk($disk)->put($path, $binary) === false) {
                report(new \RuntimeException("Failed to store uploaded image at [{$disk}://{$path}]."));

                return null;
            }

            return $path;
        }

        $src = @imagecreatefromstring($binary);
        if ($src === false) {
            return null;
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1.0, $maxEdge / max($w, $h));   // never upscale
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $dst = imagecreatetruecolor($nw, $nh);
        // Preserve transparency (png/gif/webp source → webp output).
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);

        $webp = function_exists('imagewebp');
        $ext = $webp ? 'webp' : 'jpg';
        $path = trim($folder, '/').'/'.$filenameBase.'.'.$ext;

        ob_start();
        if ($webp) {
            imagewebp($dst, null, $quality);
        } else {
            // JPEG has no alpha — flatten onto white first.
            $flat = imagecreatetruecolor($nw, $nh);
            imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
            imagecopy($flat, $dst, 0, 0, 0, 0, $nw, $nh);
            imagejpeg($flat, null, $quality);
            imagedestroy($flat);
        }
        $out = ob_get_clean();
        imagedestroy($dst);

        if ($out === false || $out === '') {
            return null;
        }

        if (Storage::disk($disk)->put($path, $out) === false) {
            report(new \RuntimeException("Failed to store uploaded image at [{$disk}://{$path}]."));

            return null;
        }

        return $path;
    }
}
