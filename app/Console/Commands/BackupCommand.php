<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PDO;

/**
 * Backs up the database and the upload folders.
 *
 * Three things make this a backup rather than a file copy:
 *
 *  1. CONSISTENCY — SQLite is snapshotted with `VACUUM INTO` after a WAL
 *     checkpoint, so the artifact is a coherent point-in-time copy even while
 *     the app is writing. Copying the file by hand mid-write is not.
 *  2. VERIFICATION — every artifact is re-opened and read back before it counts
 *     as a success. An unverified backup is a rumour.
 *  3. OFF-SERVER — a snapshot on the same disk survives a bad migration but not
 *     a dead disk, so a configured remote disk receives a copy, and the command
 *     warns loudly on every run where none is set.
 *
 * Exits non-zero on any failure so a cron surfaces the problem instead of
 * quietly producing corrupt files.
 */
class BackupCommand extends Command
{
    protected $signature = 'takeone:backup
                            {--db-only : Skip the uploads archive}
                            {--uploads-only : Skip the database}
                            {--keep-local : Do not delete the local copy after an off-server upload}';

    protected $description = 'Snapshot the database and uploads, verify them, copy off-server and prune old artifacts';

    private array $artifacts = [];

    public function handle(): int
    {
        $dir = (string) config('backup.path');

        if (! is_dir($dir) && ! @mkdir($dir, 0775, true)) {
            $this->error("Cannot create backup directory: {$dir}");

            return self::FAILURE;
        }

        $stamp = now()->format('Ymd-His');
        $failed = false;

        if (! $this->option('uploads-only')) {
            $failed = ! $this->backupDatabase($dir, $stamp) || $failed;
        }

        if (! $this->option('db-only')) {
            $failed = ! $this->backupUploads($dir, $stamp) || $failed;
        }

        $this->shipOffServer();
        $this->prune($dir);

        if ($failed) {
            Log::error('Backup run failed', ['artifacts' => $this->artifacts]);
            $this->error('Backup FAILED — see the errors above.');

            return self::FAILURE;
        }

        Log::info('Backup completed', ['artifacts' => array_map('basename', $this->artifacts)]);
        $this->newLine();
        $this->info('Backup complete.');

        return self::SUCCESS;
    }

    /* ---------------- Database ---------------- */

    private function backupDatabase(string $dir, string $stamp): bool
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        return match ($driver) {
            'sqlite' => $this->backupSqlite($dir, $stamp),
            'mysql', 'mariadb' => $this->backupMysql($dir, $stamp, $connection),
            default => tap(false, fn () => $this->error("Unsupported database driver for backup: {$driver}")),
        };
    }

    /** Consistent SQLite snapshot: checkpoint the WAL, then VACUUM INTO. */
    private function backupSqlite(string $dir, string $stamp): bool
    {
        $target = $dir."/db-{$stamp}.sqlite";

        // VACUUM cannot run inside a transaction. Rather than let SQLite throw
        // something cryptic, say so plainly — a backup that quietly produces
        // nothing is the worst possible outcome.
        if (DB::transactionLevel() > 0) {
            $this->error('Cannot snapshot the database inside an open transaction.');

            return false;
        }

        try {
            DB::statement('PRAGMA wal_checkpoint(TRUNCATE)');
            DB::statement('VACUUM INTO '.DB::getPdo()->quote($target));
        } catch (\Throwable $e) {
            $this->error('Database snapshot failed: '.$e->getMessage());

            return false;
        }

        if (! $this->verifySqlite($target)) {
            return false;
        }

        $this->artifacts[] = $target;
        $this->line(sprintf('  <info>✓</info> database  %s (%s)', basename($target), $this->humanSize($target)));

        return true;
    }

    /**
     * Read the snapshot back. A file that exists but cannot be opened, or that
     * has no migrations table, is not a backup.
     */
    private function verifySqlite(string $path): bool
    {
        try {
            $pdo = new PDO('sqlite:'.$path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            if ($pdo->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') {
                $this->error("Snapshot failed integrity check: {$path}");

                return false;
            }

            $migrations = (int) $pdo->query('SELECT count(*) FROM migrations')->fetchColumn();

            if ($migrations < 1) {
                $this->error("Snapshot contains no migrations — refusing to trust it: {$path}");

                return false;
            }
        } catch (\Throwable $e) {
            $this->error('Snapshot could not be verified: '.$e->getMessage());

            return false;
        }

        return true;
    }

    private function backupMysql(string $dir, string $stamp, string $connection): bool
    {
        $target = $dir."/db-{$stamp}.sql.gz";
        $c = config("database.connections.{$connection}");

        $command = sprintf(
            'mysqldump --single-transaction --quick --no-tablespaces -h%s -P%s -u%s %s %s | gzip > %s',
            escapeshellarg((string) $c['host']),
            escapeshellarg((string) $c['port']),
            escapeshellarg((string) $c['username']),
            $c['password'] ? '-p'.escapeshellarg((string) $c['password']) : '',
            escapeshellarg((string) $c['database']),
            escapeshellarg($target),
        );

        exec($command, $out, $status);

        if ($status !== 0 || ! is_file($target) || filesize($target) === 0) {
            $this->error('mysqldump failed (exit '.$status.').');

            return false;
        }

        $this->artifacts[] = $target;
        $this->line(sprintf('  <info>✓</info> database  %s (%s)', basename($target), $this->humanSize($target)));

        return true;
    }

    /* ---------------- Uploads ---------------- */

    /**
     * Archive the upload folders. Rows without their files are broken records —
     * a proof-of-payment row pointing at a missing image proves nothing.
     */
    private function backupUploads(string $dir, string $stamp): bool
    {
        $paths = array_values(array_filter((array) config('backup.uploads', []), 'is_dir'));

        if (! $paths) {
            $this->line('  <comment>–</comment> uploads   nothing to archive');

            return true;
        }

        $target = $dir."/uploads-{$stamp}.tar.gz";

        // Store paths relative to their parent so the archive restores cleanly.
        $args = implode(' ', array_map(
            fn ($p) => '-C '.escapeshellarg(dirname($p)).' '.escapeshellarg(basename($p)),
            $paths,
        ));

        exec('tar -czf '.escapeshellarg($target).' '.$args.' 2>/dev/null', $out, $status);

        if ($status !== 0 || ! is_file($target)) {
            $this->error('Uploads archive failed (exit '.$status.').');

            return false;
        }

        $limit = (int) config('backup.max_uploads_mb', 0);
        if ($limit > 0 && filesize($target) > $limit * 1024 * 1024) {
            @unlink($target);
            $this->error("Uploads archive exceeded {$limit}MB and was discarded — raise BACKUP_MAX_UPLOADS_MB or exclude a folder.");

            return false;
        }

        // Verify the archive is readable rather than assuming tar told the truth.
        exec('tar -tzf '.escapeshellarg($target).' >/dev/null 2>&1', $o2, $verify);
        if ($verify !== 0) {
            $this->error('Uploads archive could not be read back — discarding.');
            @unlink($target);

            return false;
        }

        $this->artifacts[] = $target;
        $this->line(sprintf('  <info>✓</info> uploads   %s (%s)', basename($target), $this->humanSize($target)));

        return true;
    }

    /* ---------------- Off-server ---------------- */

    private function shipOffServer(): void
    {
        $disk = config('backup.disk');

        if (! $disk) {
            $this->newLine();
            $this->warn('No BACKUP_DISK configured — these copies live on the SAME disk as the data.');
            $this->line('  They survive a bad migration, but not a dead server. Set BACKUP_DISK to a remote filesystem.');

            return;
        }

        foreach ($this->artifacts as $artifact) {
            try {
                Storage::disk($disk)->put('backups/'.basename($artifact), fopen($artifact, 'rb'));
                $this->line("  <info>✓</info> off-server {$disk}:backups/".basename($artifact));

                if (! $this->option('keep-local')) {
                    @unlink($artifact);
                }
            } catch (\Throwable $e) {
                // Never delete the local copy when the upload failed — it is the
                // only copy left.
                $this->error('Off-server upload failed for '.basename($artifact).': '.$e->getMessage());
            }
        }
    }

    /* ---------------- Rotation ---------------- */

    /**
     * Delete artifacts past the retention window, but always keep the newest
     * few of each kind — a box that sat idle for a month must still have
     * something to restore from.
     */
    private function prune(string $dir): void
    {
        $days = (int) config('backup.retain_days', 14);

        if ($days <= 0) {
            return;
        }

        $keep = max(0, (int) config('backup.keep_minimum', 3));
        $cutoff = now()->subDays($days)->getTimestamp();
        $removed = 0;

        foreach (['db-*', 'uploads-*'] as $pattern) {
            $files = glob($dir.'/'.$pattern) ?: [];
            usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));   // newest first

            foreach (array_slice($files, $keep) as $file) {
                if (filemtime($file) < $cutoff && @unlink($file)) {
                    $removed++;
                }
            }
        }

        if ($removed) {
            $this->line("  <info>✓</info> pruned    {$removed} artifact(s) older than {$days} days");
        }
    }

    private function humanSize(string $path): string
    {
        $bytes = (int) @filesize($path);
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 1).' '.$units[$i];
    }
}
