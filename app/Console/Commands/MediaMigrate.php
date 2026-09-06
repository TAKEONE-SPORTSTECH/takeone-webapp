<?php

namespace App\Console\Commands;

use App\Jobs\MigrateMediaToVault;
use App\Media\MediaVaults;
use App\Models\MediaFile;
use App\Models\MediaVault;
use Illuminate\Console\Command;

/**
 * Move everything on local disk onto attached storage.
 *
 * The manual and scheduled face of MigrateMediaToVault. Attaching a vault
 * through the admin page starts a migration by itself; this exists for the two
 * cases that page cannot cover:
 *
 *   `media:migrate --auto`   run on a schedule. Catches the NORMAL failure of a
 *                            competition day: the NAS was unreachable for an
 *                            hour, clips fell back to local disk to avoid losing
 *                            a bout, and now the share is back. Without this
 *                            they would sit here indefinitely.
 *
 *   `media:migrate <vault>`  do it now, in a terminal, with progress — the right
 *                            way to move a large existing library, where a
 *                            queue's batching is just latency.
 */
class MediaMigrate extends Command
{
    protected $signature = 'media:migrate
        {vault? : The vault (uuid or name) to move files ONTO}
        {--auto : Pick the highest-priority reachable vault, and stay silent when there is nothing to do}
        {--queue : Hand the work to the queue instead of doing it here}
        {--limit=0 : Stop after this many files (0 = no limit)}
        {--dry-run : List what would move and change nothing}';

    protected $description = 'Move media from local disk onto attached storage, verifying every file';

    public function handle(MediaVaults $vaults): int
    {
        $auto = (bool) $this->option('auto');

        $vault = $this->argument('vault')
            ? MediaVault::where('uuid', $this->argument('vault'))->orWhere('name', $this->argument('vault'))->first()
            : $vaults->writeVault();

        if ($vault === null) {
            // No storage attached is the platform's default state, not an error —
            // so a scheduled run says nothing at all about it.
            $auto or $this->warn('No attached storage to migrate onto.');

            return self::SUCCESS;
        }

        if (! $vault->enabled || $vault->read_only) {
            $auto or $this->error("{$vault->name} is not accepting new files (disabled or read-only).");

            return self::FAILURE;
        }

        if (! $vaults->isReachable($vault)) {
            $auto or $this->error("{$vault->name} cannot be reached.");

            return self::FAILURE;
        }

        $query = MediaFile::query()
            ->whereNull('vault_id')
            ->whereNotIn('status', [MediaFile::STATUS_UPLOADING, MediaFile::STATUS_PROCESSING, MediaFile::STATUS_MISSING])
            ->orderBy('id');

        if (($limit = (int) $this->option('limit')) > 0) {
            $query->limit($limit);
        }

        $total = $query->count();

        if ($total === 0) {
            $auto or $this->info("Nothing on local disk to move. {$vault->name} already holds everything.");

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->line("Would move {$total} file(s) onto {$vault->name}:");

            foreach ($query->get() as $file) {
                $this->line('  '.$file->rel_path.'  ('.$file->human_size.')');
            }

            return self::SUCCESS;
        }

        if ($this->option('queue')) {
            MigrateMediaToVault::dispatch($vault->uuid)->onQueue('media');
            $this->info("Queued: {$total} file(s) → {$vault->name}.");

            return self::SUCCESS;
        }

        $this->line("Moving {$total} file(s) onto {$vault->name} — copy, verify, repoint, then delete.");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $moved = 0;
        $failed = 0;

        foreach ($query->cursor() as $file) {
            $vaults->moveTo($file, $vault) ? $moved++ : $failed++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Prove it, rather than asserting it. The whole point of the exercise is
        // that nothing is left behind, so the last thing it does is look.
        $left = MediaFile::whereNull('vault_id')
            ->whereNotIn('status', [MediaFile::STATUS_UPLOADING, MediaFile::STATUS_PROCESSING, MediaFile::STATUS_MISSING])
            ->count();

        $this->info("Moved {$moved}.");

        if ($failed > 0) {
            $this->warn("{$failed} could not be moved — their sources are untouched and still readable.");
        }

        if ($left > 0) {
            $this->warn("{$left} file(s) still on local disk. Run `media:verify` to see why.");

            return self::FAILURE;
        }

        $this->info('Nothing left on local disk.');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
