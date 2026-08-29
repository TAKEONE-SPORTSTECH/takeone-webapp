<?php

namespace App\Media\Drivers;

use App\Media\Contracts\VaultDriver;
use Illuminate\Support\Facades\Log;

/**
 * A NAS we speak SMB to ourselves, one file at a time.
 *
 * For a share nobody will mount for us. It works, and it is honest about what it
 * is: bytes cannot be read where they lie, so anything anybody watches is copied
 * down first. That makes this ARCHIVE storage with a local cache, not an origin
 * — `servesInPlace()` returns false and the rest of the system plans around it.
 * Prefer a `mount` vault whenever the box can be given one.
 *
 * ── Why shelling out to smbclient ──────────────────────────────────────────
 *
 * Because it is already how this is done next door on the video platform, it
 * needs no PHP extension, and it is the one SMB client that is always available
 * on a Debian box. Every argument that reaches the shell goes through
 * `escapeshellarg`, and the credential is passed by FILE rather than on the
 * command line — an `-U user%password` argument is visible in `ps` to every
 * process on the machine, including anything an attacker already has.
 */
class SmbDriver implements VaultDriver
{
    private ?bool $reachable = null;

    public function __construct(
        private string $host,
        private string $share,
        private ?string $username = null,
        private ?string $password = null,
        private ?string $domain = null,
        private ?string $rootPath = null,
        private int $port = 445,
    ) {}

    public function reachable(): bool
    {
        if ($this->reachable !== null) {
            return $this->reachable;
        }

        // A TCP probe with a short deadline, not a full SMB handshake: this runs
        // on request paths, and a NAS on another subnet with no route to it will
        // otherwise hold the request open until something times out.
        $socket = @fsockopen($this->host, $this->port, $errno, $errstr, 2);

        if ($socket) {
            fclose($socket);

            return $this->reachable = true;
        }

        return $this->reachable = false;
    }

    public function put(string $localAbsPath, string $relPath): bool
    {
        if (! is_file($localAbsPath) || ! $this->reachable()) {
            return false;
        }

        $remote = $this->remote($relPath);

        if ($remote === null) {
            return false;
        }

        $this->mkdirp(dirname($relPath));

        [$code, $out] = $this->run('put '.$this->quoteSmb($localAbsPath).' '.$this->quoteSmb($remote));

        if ($code !== 0) {
            Log::warning('media vault: smb put failed', ['path' => $remote, 'out' => $out]);
        }

        return $code === 0;
    }

    public function get(string $relPath, string $localAbsPath): bool
    {
        if (! $this->reachable()) {
            return false;
        }

        $remote = $this->remote($relPath);

        if ($remote === null) {
            return false;
        }

        if (! is_dir(dirname($localAbsPath))) {
            @mkdir(dirname($localAbsPath), 0775, true);
        }

        [$code] = $this->run('get '.$this->quoteSmb($remote).' '.$this->quoteSmb($localAbsPath));

        if ($code !== 0 || ! is_file($localAbsPath)) {
            // Half a file is worse than none — a partial download that stays on
            // disk would be served as if it were the video.
            @unlink($localAbsPath);

            return false;
        }

        return true;
    }

    public function exists(string $relPath): bool
    {
        if (! $this->reachable()) {
            return false;
        }

        $remote = $this->remote($relPath);

        if ($remote === null) {
            return false;
        }

        [$code] = $this->run('ls '.$this->quoteSmb($remote));

        return $code === 0;
    }

    /**
     * Ask the share how big the file it holds is.
     *
     * smbclient's `ls` prints one line per match:
     *   `name.mp4   A   1048576  Mon Aug 25 10:00:00 2026`
     * The size is the first standalone integer after the attribute flags.
     */
    public function size(string $relPath): ?int
    {
        if (! $this->reachable()) {
            return null;
        }

        $remote = $this->remote($relPath);

        if ($remote === null) {
            return null;
        }

        [$code, $out] = $this->run('ls '.$this->quoteSmb($remote));

        if ($code !== 0) {
            return null;
        }

        foreach ($out as $line) {
            if (preg_match('/\s+[A-Za-z]*\s+(\d+)\s+\w{3}\s+\w{3}/', $line, $m)) {
                return (int) $m[1];
            }
        }

        return null;
    }

    public function delete(string $relPath): void
    {
        if (! $this->reachable()) {
            return;
        }

        $remote = $this->remote($relPath);

        if ($remote !== null) {
            $this->run('del '.$this->quoteSmb($remote));
        }
    }

    public function mkdirp(string $relPath): void
    {
        $remote = $this->remote($relPath);

        if ($remote === null || ! $this->reachable()) {
            return;
        }

        // smbclient's mkdir makes one level at a time, so walk it. Each step may
        // already exist, which fails harmlessly.
        $built = '';

        foreach (explode('/', trim($remote, '/')) as $segment) {
            if ($segment === '') {
                continue;
            }

            $built .= ($built === '' ? '' : '/').$segment;
            $this->run('mkdir '.$this->quoteSmb($built));
        }
    }

    public function servesInPlace(): bool
    {
        return false;
    }

    public function absolutePath(string $relPath): ?string
    {
        return null;
    }

    public function usage(): array
    {
        if (! $this->reachable()) {
            return ['free' => null, 'total' => null];
        }

        [$code, $out] = $this->run('du');

        if ($code !== 0) {
            return ['free' => null, 'total' => null];
        }

        // smbclient prints e.g.
        //   "65535 blocks of size 2097152. 12345 blocks available"
        if (preg_match('/(\d+)\s+blocks of size\s+(\d+)\..*?(\d+)\s+blocks available/s', implode(' ', $out), $m)) {
            $blockSize = (int) $m[2];

            return [
                'free' => (int) $m[3] * $blockSize,
                'total' => (int) $m[1] * $blockSize,
            ];
        }

        return ['free' => null, 'total' => null];
    }

    public function probe(): array
    {
        if (! $this->reachable()) {
            return [
                'ok' => false,
                'message' => "Cannot reach {$this->host}:{$this->port}. Check the address, the port, and that this server has a route to it.",
            ];
        }

        [$code, $out] = $this->run('ls');

        if ($code !== 0) {
            $why = trim(implode(' ', array_slice($out, 0, 3)));

            return ['ok' => false, 'message' => 'Reached the NAS but could not open the share: '.($why ?: 'unknown error')];
        }

        $usage = $this->usage();

        return [
            'ok' => true,
            'message' => 'Connected to \\\\'.$this->host.'\\'.$this->share
                .($usage['free'] !== null ? ' — '.round($usage['free'] / 1073741824, 1).' GB free' : '')
                .'. Copied down before playback (mount the share for direct streaming).',
        ];
    }

    /** The path as the share sees it, or null if it tries to escape the root. */
    private function remote(string $relPath): ?string
    {
        $clean = trim(str_replace('\\', '/', $relPath), '/');

        if ($clean === '' || $clean === '.' || str_contains($clean, '../') || str_contains($clean, "\0")) {
            return null;
        }

        $root = trim((string) $this->rootPath, '/');

        return $root === '' ? $clean : $root.'/'.$clean;
    }

    /**
     * Run one smbclient command against the share.
     *
     * @return array{0:int,1:array<int,string>}
     */
    private function run(string $command): array
    {
        // The credential goes in a file, mode 0600, deleted immediately. Never
        // on the command line: /proc is world-readable and `ps` would print the
        // NAS password to any user on the box.
        $authFile = tempnam(sys_get_temp_dir(), 'vaultauth_');
        @chmod($authFile, 0600);

        file_put_contents($authFile, implode("\n", array_filter([
            'username = '.(string) $this->username,
            'password = '.(string) $this->password,
            filled($this->domain) ? 'domain = '.$this->domain : null,
        ]))."\n");

        $cmd = implode(' ', [
            escapeshellcmd(config('media.smbclient', 'smbclient')),
            escapeshellarg('//'.$this->host.'/'.$this->share),
            '-A '.escapeshellarg($authFile),
            '-p '.(int) $this->port,
            '-t '.(int) config('media.smb_timeout', 20),
            '-c '.escapeshellarg($command),
            '2>&1',
        ]);

        $output = [];
        $code = 0;
        exec($cmd, $output, $code);

        @unlink($authFile);

        return [$code, $output];
    }

    /**
     * Quote a path for smbclient's OWN command parser.
     *
     * Two layers of quoting are in play and conflating them is how command
     * injection gets in: `escapeshellarg` protects the SHELL from the whole
     * `-c` string, and this protects smbclient's internal parser from spaces in
     * a filename. Anything that could terminate the inner quoting is removed
     * rather than escaped — our own paths never contain it.
     */
    private function quoteSmb(string $path): string
    {
        return '"'.str_replace(['"', '`', '$', "\n", "\r"], '', $path).'"';
    }
}
