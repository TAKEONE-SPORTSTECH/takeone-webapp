<?php

namespace App\Media\Drivers;

use App\Media\Contracts\VaultDriver;

/**
 * This server's own disk — the place media lives when nothing is attached.
 *
 * Not a fallback and not a degraded mode: it is the platform's default state,
 * and a deployment that never attaches a vault is a working deployment. It is
 * also the one driver that always exists and can never be detached, which is
 * why it is a driver at all rather than a special case sprinkled through the
 * manager.
 *
 * Root is private storage (`data/media` under the storage path, outside the
 * public disk). Nothing here is reachable by URL; media is served by a
 * controller that authorises first.
 */
class LocalDriver implements VaultDriver
{
    public function __construct(private string $root)
    {
        $this->root = rtrim($root, '/');
    }

    public function reachable(): bool
    {
        // Reachable once the root can be created. A disk that cannot be written
        // to is a real failure, but it is not this method's job to hide it.
        return is_dir($this->root) || @mkdir($this->root, 0775, true) || is_dir($this->root);
    }

    public function put(string $localAbsPath, string $relPath): bool
    {
        $target = $this->abs($relPath);

        if ($target === null || ! is_file($localAbsPath)) {
            return false;
        }

        $this->mkdirp(dirname($relPath));

        // rename() first: an ingest normally hands us a file that is already on
        // the same filesystem, and moving it is instant where copying a
        // multi-gigabyte bout is not. Falls back to copy across devices.
        if (@rename($localAbsPath, $target)) {
            return true;
        }

        return @copy($localAbsPath, $target);
    }

    public function get(string $relPath, string $localAbsPath): bool
    {
        $source = $this->abs($relPath);

        if ($source === null || ! is_file($source)) {
            return false;
        }

        if ($source === $localAbsPath) {
            return true;
        }

        if (! is_dir(dirname($localAbsPath))) {
            @mkdir(dirname($localAbsPath), 0775, true);
        }

        return @copy($source, $localAbsPath);
    }

    public function exists(string $relPath): bool
    {
        $abs = $this->abs($relPath);

        return $abs !== null && file_exists($abs);
    }

    public function size(string $relPath): ?int
    {
        $abs = $this->abs($relPath);

        if ($abs === null || ! is_file($abs)) {
            return null;
        }

        $size = @filesize($abs);

        return $size === false ? null : (int) $size;
    }

    public function delete(string $relPath): void
    {
        $abs = $this->abs($relPath);

        if ($abs === null) {
            return;
        }

        if (is_dir($abs)) {
            $this->deleteTree($abs);

            return;
        }

        @unlink($abs);
    }

    public function mkdirp(string $relPath): void
    {
        $abs = $this->abs($relPath);

        if ($abs !== null && ! is_dir($abs)) {
            @mkdir($abs, 0775, true);
        }
    }

    public function servesInPlace(): bool
    {
        return true;
    }

    public function absolutePath(string $relPath): ?string
    {
        $abs = $this->abs($relPath);

        return ($abs !== null && is_file($abs)) ? $abs : null;
    }

    public function usage(): array
    {
        // Ask about the nearest directory that actually exists. A vault's own
        // folder may not have been created yet, and `disk_free_space()` on a
        // path that is not there returns false — which would report a healthy
        // share as having unknown space purely because it is empty.
        $path = $this->root;

        while ($path !== '' && $path !== '/' && ! is_dir($path)) {
            $path = dirname($path);
        }

        $free = @disk_free_space($path);
        $total = @disk_total_space($path);

        return [
            'free' => $free === false ? null : (int) $free,
            'total' => $total === false ? null : (int) $total,
        ];
    }

    public function probe(): array
    {
        if (! $this->reachable()) {
            return ['ok' => false, 'message' => 'Local media directory could not be created.'];
        }

        $usage = $this->usage();

        return [
            'ok' => true,
            'message' => 'Local disk'.($usage['free'] !== null
                ? ' — '.round($usage['free'] / 1073741824, 1).' GB free'
                : ''),
        ];
    }

    /**
     * Resolve a relative path inside the root, or null if it tries to leave.
     *
     * The guard is not decoration: `rel_path` values come out of the database,
     * and a repair script, an import or a future admin tool could put something
     * unexpected there. A path that escapes the root is refused rather than
     * normalised into something plausible.
     */
    private function abs(string $relPath): ?string
    {
        $clean = ltrim(str_replace('\\', '/', $relPath), '/');

        if ($clean === '' || str_contains($clean, '../') || str_contains($clean, "\0")) {
            return null;
        }

        return $this->root.'/'.$clean;
    }

    private function deleteTree(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir.'/'.$entry;
            is_dir($path) ? $this->deleteTree($path) : @unlink($path);
        }

        @rmdir($dir);
    }
}
