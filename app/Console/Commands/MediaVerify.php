<?php

namespace App\Console\Commands;

use App\Media\MediaVaults;
use App\Models\MediaFile;
use Illuminate\Console\Command;

/**
 * "Is every record's video actually there?"
 *
 * The audit half of the storage guarantee. A migration verifies each file as it
 * moves it; this verifies the whole library at rest — after a migration, after a
 * NAS was detached, after a restore, or on a schedule so nobody finds out on a
 * competition morning.
 *
 * Two directions, because a broken link has two shapes:
 *
 *   · a RECORD with no bytes — the row points at a file that is not there. Marked
 *     `missing`, which is what makes it visible in the admin page instead of
 *     failing silently when somebody presses play.
 *
 *   · BYTES with no record — a file on storage nothing points at. Left alone and
 *     merely reported: deleting a video because the database lost track of it is
 *     exactly the wrong instinct, and an orphan is usually the survivor of an
 *     interrupted move.
 */
class MediaVerify extends Command
{
    protected $signature = 'media:verify
        {--deep : Also re-hash files that can be read in place (slow, catches corruption)}
        {--orphans : Also scan local storage for files no record points at}
        {--quiet-when-clean : Say nothing at all when everything checks out (for cron)}';

    protected $description
        = 'Check that every media record has its bytes, and report anything unaccounted for';

    public function handle(MediaVaults $vaults): int
    {
        $total = MediaFile::count();

        if ($total === 0) {
            $this->option('quiet-when-clean') or $this->info('No media stored yet.');

            return self::SUCCESS;
        }

        $deep = (bool) $this->option('deep');

        $this->option('quiet-when-clean') or $this->line(
            "Checking {$total} media file(s)".($deep ? ' with checksums' : '').'…'
        );

        $ok = 0;
        $problems = [];

        foreach (MediaFile::with('vault')->cursor() as $file) {
            $result = $vaults->verify($file, $deep);

            if ($result['ok']) {
                $ok++;

                continue;
            }

            $problems[] = [
                substr($file->uuid, 0, 8),
                $file->vault?->name ?? 'local disk',
                $result['reason'],
                $file->rel_path,
            ];
        }

        if ($problems !== []) {
            $this->newLine();
            $this->error(count($problems).' file(s) could not be verified:');
            $this->table(['file', 'storage', 'problem', 'path'], $problems);
        }

        $orphans = $this->option('orphans') ? $this->findOrphans() : [];

        if ($orphans !== []) {
            $this->newLine();
            $this->warn(count($orphans).' file(s) on local storage that no record points at:');

            foreach (array_slice($orphans, 0, 20) as $path) {
                $this->line('  '.$path);
            }

            if (count($orphans) > 20) {
                $this->line('  … and '.(count($orphans) - 20).' more');
            }

            $this->line('Nothing was deleted. These are usually the remains of an interrupted move.');
        }

        $clean = $problems === [] && $orphans === [];

        if ($clean) {
            $this->option('quiet-when-clean') or $this->info("All {$ok} file(s) verified. Nothing missing, nothing unaccounted for.");

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line("Verified {$ok} of {$total}.");

        return self::FAILURE;
    }

    /**
     * Files under the local media root that no record claims.
     *
     * Skips `cache/` entirely — everything in there is derived by definition and
     * is supposed to have no record of its own.
     *
     * @return array<int,string>
     */
    private function findOrphans(): array
    {
        $root = rtrim((string) config('media.local_root'), '/');

        if (! is_dir($root)) {
            return [];
        }

        $known = MediaFile::whereNull('vault_id')->pluck('rel_path')->all();
        $known = array_fill_keys($known, true);

        $orphans = [];

        $walk = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($walk as $entry) {
            $rel = ltrim(str_replace($root, '', $entry->getPathname()), '/');

            if (str_starts_with($rel, 'cache/')) {
                continue;
            }

            // A folder's own label file belongs to the folder, not to a record.
            if (basename($rel) === 'meta.json') {
                continue;
            }

            if (! isset($known[$rel])) {
                $orphans[] = $rel;
            }
        }

        return $orphans;
    }
}
