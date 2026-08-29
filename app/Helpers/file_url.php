<?php

use Illuminate\Support\Facades\Storage;

if (! function_exists('file_url')) {
    /**
     * The URL for one stored file.
     *
     * Replaces `file_url($path)`. That built a link straight into the
     * web root, which meant the file was readable by anyone holding the URL —
     * access decided by which folder the file happened to be in. Now every file
     * goes through one route that asks App\Support\FileAccess who is looking.
     *
     * Returns null for an empty path so callers can keep using
     * `$path ? file_url($path) : $fallback` without a special case.
     */
    function file_url(?string $path): ?string
    {
        $path = ltrim((string) $path, '/');

        if ($path === '') {
            return null;
        }

        // Already absolute — a remote avatar, a seeded placeholder. Left alone.
        if (str_contains($path, '://')) {
            return $path;
        }

        // Historic values were stored with the disk prefix baked in.
        $path = preg_replace('#^(storage/|public/)#', '', $path);

        return route('file.show', ['path' => $path]);
    }
}

if (! function_exists('file_exists_in_storage')) {
    /** Does this stored path exist, on the one storage root? */
    function file_exists_in_storage(?string $path): bool
    {
        $path = ltrim((string) $path, '/');

        return $path !== '' && Storage::disk('local')->exists($path);
    }
}
