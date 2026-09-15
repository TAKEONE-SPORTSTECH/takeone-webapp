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

if (! function_exists('thumb_url')) {
    /**
     * The same file, asked for at a size a list actually paints.
     *
     * Takes a URL `file_url()` already built (or a bare path) and adds the
     * named size. The door does the rest: it authorises the SOURCE exactly as
     * before and serves a rendition instead of the original, falling back to
     * the original whenever one cannot be drawn — so a caller can always use
     * this and never has to handle a missing thumbnail.
     *
     * Sizes are the names in App\Support\Thumbnails::SIZES and nothing else.
     * An unknown one is left off rather than passed through, so a typo costs a
     * few kilobytes instead of silently becoming a number on a URL.
     *
     * ⚠️ Only for pictures drawn SMALL. A screen showing somebody's face at
     * size — the bout page, the lightbox — reads the original.
     */
    function thumb_url(?string $url, string $size = 'card'): ?string
    {
        $url = (string) ($url ?? '');

        if ($url === '' || ! \App\Support\Thumbnails::knows($size)) {
            return $url ?: null;
        }

        // An absolute foreign URL is somebody else's file; we resize nothing.
        if (! str_contains($url, '/file/')) {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').'s='.$size;
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
