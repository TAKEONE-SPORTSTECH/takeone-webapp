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
        'image/png'  => 'png',
        'image/gif'  => 'gif',
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
        if (!str_starts_with($base64, 'data:image')) {
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
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($binary);

        // Reject anything not in the whitelist — this stops PHP/HTML/SVG/EXE
        // files even if they claim to be images in their data-URI header.
        if (!array_key_exists($mimeType, self::ALLOWED_IMAGE_TYPES)) {
            return null;
        }

        // Extension comes from our map, never from client input.
        $extension = self::ALLOWED_IMAGE_TYPES[$mimeType];
        $fullPath  = trim($folder, '/') . '/' . $filenameBase . '.' . $extension;

        Storage::disk($disk)->put($fullPath, $binary);

        return $fullPath;
    }
}
