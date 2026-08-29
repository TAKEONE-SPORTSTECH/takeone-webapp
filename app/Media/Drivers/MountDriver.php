<?php

namespace App\Media\Drivers;

/**
 * A NAS that is already mounted into the filesystem — the good case.
 *
 * Because the share is a directory, media can be written to it and STREAMED OUT
 * OF IT with nothing copied to our own disk. That is the only arrangement that
 * really satisfies "don't keep the video on our storage": with SMB spoken by
 * hand, every video anybody watches lands here first.
 *
 * Mounting is the box's job, not ours — a line in fstab, done once, by whoever
 * owns the machine. This driver's whole contribution is to REFUSE TO PRETEND
 * when that has not happened.
 *
 * ── The bug this driver exists to prevent ──────────────────────────────────
 *
 * A mount point is an ordinary empty directory when nothing is mounted on it.
 * Write to it then, and the bytes land on the local disk under a path that
 * claims to be a NAS — and everything keeps working, quietly, until the mount
 * comes back and hides those files forever. So `reachable()` reads
 * /proc/mounts and requires the path (or a parent of it) to genuinely be a
 * mount point, and the driver never creates its own root.
 */
class MountDriver extends LocalDriver
{
    private string $mountRoot;

    /**
     * @param  string  $mountPath  where the share is mounted (must be a real mount)
     * @param  string|null  $rootPath  a subfolder inside it to confine ourselves to
     */
    public function __construct(string $mountPath, ?string $rootPath = null)
    {
        $this->mountRoot = rtrim($mountPath, '/');

        $full = $this->mountRoot;

        if (filled($rootPath)) {
            $full .= '/'.trim(str_replace('\\', '/', $rootPath), '/');
        }

        parent::__construct($full);
    }

    public function reachable(): bool
    {
        // Cheap and re-asked often, so the answer is cached briefly — but much
        // shorter than a remote probe, because reading /proc/mounts costs
        // nothing and a mount can drop at any moment.
        static $cache = [];

        $key = $this->mountRoot;

        if (isset($cache[$key]) && $cache[$key]['at'] > microtime(true) - 5) {
            return $cache[$key]['ok'];
        }

        $ok = $this->isMounted() && is_writable($this->mountRoot);

        $cache[$key] = ['ok' => $ok, 'at' => microtime(true)];

        return $ok;
    }

    public function put(string $localAbsPath, string $relPath): bool
    {
        // Never write into an unmounted mount point. This is the guard, not the
        // optimisation: without it a failed mount silently fills the local disk
        // with files that look like they are on the NAS.
        return $this->reachable() && parent::put($localAbsPath, $relPath);
    }

    public function mkdirp(string $relPath): void
    {
        if (! $this->reachable()) {
            return;
        }

        parent::mkdirp($relPath);
    }

    public function servesInPlace(): bool
    {
        return true;
    }

    public function probe(): array
    {
        if (! $this->isMounted()) {
            return [
                'ok' => false,
                'message' => "Nothing is mounted at {$this->mountRoot}. Mount the share on the server first (fstab), then test again.",
            ];
        }

        if (! is_writable($this->mountRoot)) {
            return ['ok' => false, 'message' => "Mounted at {$this->mountRoot}, but not writable by the web user."];
        }

        // A real write, not just a stat. A CIFS mount can be present, readable
        // and refuse writes, and finding that out on the first bout of an event
        // is not acceptable.
        $usage = $this->usage();
        $probeRel = '.takeone-write-probe';

        $tmp = tempnam(sys_get_temp_dir(), 'vaultprobe_');
        file_put_contents($tmp, 'takeone');

        $wrote = parent::put($tmp, $probeRel);
        @unlink($tmp);

        if (! $wrote) {
            return ['ok' => false, 'message' => 'Mounted, but a test write failed. Check permissions on the share.'];
        }

        $this->delete($probeRel);

        return [
            'ok' => true,
            'message' => 'Mounted and writable'
                .($usage['free'] !== null ? ' — '.round($usage['free'] / 1073741824, 1).' GB free' : '')
                .'. Video is served straight off this vault.',
        ];
    }

    /**
     * Is this path, or any parent of it, an actual mount point?
     *
     * A parent counts: mounting `/mnt/nas` and pointing a vault at
     * `/mnt/nas/takeone/media` is the normal arrangement.
     */
    private function isMounted(): bool
    {
        $mounts = @file('/proc/mounts', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($mounts === false) {
            // No /proc to read (an unusual container). Fall back to the weaker
            // test rather than refusing outright.
            return is_dir($this->mountRoot);
        }

        $points = [];

        foreach ($mounts as $line) {
            $parts = preg_split('/\s+/', $line);

            if (isset($parts[1])) {
                // /proc/mounts escapes spaces and friends as octal.
                $points[] = stripcslashes($parts[1]);
            }
        }

        $path = $this->mountRoot;

        while ($path !== '' && $path !== '/') {
            if (in_array($path, $points, true)) {
                return true;
            }

            $path = dirname($path);
        }

        return false;
    }
}
