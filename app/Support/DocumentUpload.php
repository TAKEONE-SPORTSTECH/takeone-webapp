<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Validate and store an uploaded document.
 *
 * The rule this exists to enforce (CLAUDE.md → "Image Uploads Must Validate
 * Real Bytes", extended to documents): the stored extension is decided by the
 * SERVER from the file's actual bytes, never from the client's filename or
 * Content-Type. The original filename is discarded entirely — the display name
 * comes from its own form field.
 *
 * OOXML gotcha: .docx/.xlsx are ZIP containers, and finfo very often reports
 * them as `application/zip` (or octet-stream) rather than their true type. So a
 * zip is opened and checked for the marker entry that only a real Word/Excel
 * file has. That is still byte-level proof — we never fall back to trusting the
 * extension the client sent.
 */
class DocumentUpload
{
    /**
     * 50 MB. Large enough for a scanned rulebook or a photo-heavy entry pack.
     *
     * NOTE: this is only the application's ceiling. PHP (`upload_max_filesize`,
     * `post_max_size`) and the web server (nginx `client_max_body_size`) each
     * impose their own, and the LOWEST wins — a request over the PHP limit is
     * truncated before Laravel sees it, so validation never even runs. Keep
     * those at or above this value, or uploads will fail with an empty file
     * rather than a clear message.
     */
    public const MAX_BYTES = 50 * 1024 * 1024;

    /** Sniffed MIME => extension the server will assign. Nothing else is stored. */
    private const ALLOWED = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    ];

    /**
     * Zip marker => [mime, extension]. A real .docx always contains
     * word/document.xml; a real .xlsx always contains xl/workbook.xml. A macro
     * file (.docm/.xlsm) is deliberately absent from this list, so it is
     * rejected even though it is also a zip with those entries — it is caught
     * by the [Content_Types].xml check below.
     */
    private const ZIP_MARKERS = [
        'word/document.xml' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'docx'],
        'xl/workbook.xml' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xlsx'],
    ];

    /**
     * Inspect the real bytes and return ['mime' => …, 'extension' => …],
     * or null when the file is not something we accept.
     */
    public function inspect(UploadedFile $file): ?array
    {
        if (! $file->isValid() || $file->getSize() === false || $file->getSize() > self::MAX_BYTES) {
            return null;
        }

        $path = $file->getRealPath();
        if (! $path || ! is_readable($path)) {
            return null;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: '';

        if (isset(self::ALLOWED[$mime])) {
            // An image must also decode as one — a PDF renamed is caught above,
            // but a text file with a spoofed magic header is caught here.
            if (str_starts_with($mime, 'image/') && @getimagesize($path) === false) {
                return null;
            }

            return ['mime' => $mime, 'extension' => self::ALLOWED[$mime]];
        }

        // Office formats usually land here as a plain zip.
        if (in_array($mime, ['application/zip', 'application/octet-stream'], true)) {
            return $this->inspectZip($path);
        }

        return null;
    }

    /**
     * Prove a zip really is a .docx/.xlsx by looking inside it, and reject the
     * macro-enabled cousins outright.
     */
    private function inspectZip(string $path): ?array
    {
        if (! class_exists(\ZipArchive::class)) {
            return null;   // cannot verify => do not accept (deny by default)
        }

        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            return null;
        }

        try {
            // [Content_Types].xml names the real part types. Macro-enabled
            // documents declare ms-word.document.macroEnabled / ms-excel.sheet
            // .macroEnabled here — refuse those before anything else.
            $types = $zip->getFromName('[Content_Types].xml');
            if ($types === false || stripos($types, 'macroEnabled') !== false) {
                return null;
            }

            foreach (self::ZIP_MARKERS as $marker => [$mime, $ext]) {
                if ($zip->locateName($marker) !== false) {
                    return ['mime' => $mime, 'extension' => $ext];
                }
            }
        } finally {
            $zip->close();
        }

        return null;
    }

    /**
     * Store the file under an app-generated path and filename.
     *
     * $folder must be built by the caller from the owning entity's PUBLIC id —
     * never passed through from client input.
     *
     * Returns ['path','mime','extension','size'] or null when rejected.
     */
    public function store(UploadedFile $file, string $folder, string $disk = 'local'): ?array
    {
        $info = $this->inspect($file);
        if ($info === null) {
            return null;
        }

        // Random, non-meaningful filename; extension assigned by us, not the client.
        $name = (string) Str::ulid().'.'.$info['extension'];
        $folder = trim($folder, '/');

        $stored = Storage::disk($disk)->putFileAs($folder, $file, $name);
        if ($stored === false) {
            return null;
        }

        return $info + ['path' => $stored, 'size' => (int) $file->getSize()];
    }
}
