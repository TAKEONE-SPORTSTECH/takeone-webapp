<?php

namespace App\Console\Commands;

use App\Media\MediaVaults;
use App\Models\MediaFile;
use App\Models\MediaVault;
use Illuminate\Console\Command;

/**
 * Move media between storage, from a terminal rather than a request.
 *
 * Two jobs, which are the same job in opposite directions:
 *
 *   media:drain <vault>   — empty a vault so it can be detached
 *   media:drain --to=<v>  — fill a newly attached vault with what is already here
 *
 * A request cannot do either: these copy whole competitions over a network. The
 * admin page drains in small batches so it can show progress; this does the
 * whole thing and can be left running.
 *
 * Copy → verify → repoint → delete, per file (see MediaVaults::moveTo), so
 * stopping it at any point leaves every file readable somewhere.
 */
class MediaDrain extends Command
{
    protected $signature = 'media:drain
        {vault? : The vault (uuid or name) to move files OFF}
        {--to= : The vault (uuid or name) to move files ON TO; omit for local disk}
        {--limit=0 : Stop after this many files (0 = no limit)}
        {--dry-run : List what would move and change nothing}';

    protected $description = 'Move stored media between vaults (or onto local disk)';

    public function handle(MediaVaults $vaults): int
    {
        $source = $this->resolve($this->argument('vault'));
        $target = $this->resolve($this->option('to'));

        if ($this->argument('vault') && ! $source) {
            $this->error('No vault matches "'.$this->argument('vault').'".');

            return self::FAILURE;
        }

        if ($this->option('to') && ! $target) {
            $this->error('No vault matches "'.$this->option('to').'".');

            return self::FAILURE;
        }

        if ($source && $target && $source->is($target)) {
            $this->error('Source and destination are the same vault.');

            return self::FAILURE;
        }

        // Files currently on the source. No source given means "everything on
        // local disk", which is the fill-a-new-vault direction.
        $query = MediaFile::query()
            ->when($source, fn ($q) => $q->where('vault_id', $source->id))
            ->when(! $source, fn ($q) => $q->whereNull('vault_id'))
            ->orderBy('id');

        $limit = (int) $this->option('limit');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $total = $query->count();

        $from = $source?->name ?? 'local disk';
        $to = $target?->name ?? 'local disk';

        if ($total === 0) {
            $this->info("Nothing to move from {$from}.");

            return self::SUCCESS;
        }

        $this->line("Moving {$total} file(s): {$from} → {$to}");

        if ($this->option('dry-run')) {
            foreach ($query->get() as $file) {
                $this->line('  would move  '.$file->rel_path.'  ('.$file->human_size.')');
            }

            $this->info('Dry run — nothing changed.');

            return self::SUCCESS;
        }

        // Stop new writes landing on a vault that is being emptied, so the drain
        // converges instead of chasing files arriving behind it.
        if ($source && ! $source->read_only) {
            $source->forceFill(['read_only' => true])->save();
            $this->line('  (source set read-only for the duration)');
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $moved = 0;
        $failed = 0;

        foreach ($query->cursor() as $file) {
            $vaults->moveTo($file, $target) ? $moved++ : $failed++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Moved {$moved} file(s).");

        if ($failed > 0) {
            $this->warn("{$failed} could not be moved and were left where they are.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** A vault by uuid or by name. Null when nothing was asked for. */
    private function resolve(?string $key): ?MediaVault
    {
        if (! filled($key)) {
            return null;
        }

        return MediaVault::query()
            ->where('uuid', $key)
            ->orWhere('name', $key)
            ->first();
    }
}
