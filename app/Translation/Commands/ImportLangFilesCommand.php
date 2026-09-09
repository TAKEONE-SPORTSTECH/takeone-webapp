<?php

namespace App\Translation\Commands;

use App\Translation\Services\ContentLocales;
use App\Translation\Services\InterfaceStore;
use Illuminate\Console\Command;

/**
 * Move what is already on disk into the database.
 *
 * The one-off that makes the move to database-backed interface strings
 * lossless. Eleven languages were generated into `lang/<code>/` before the
 * table existed, and every one of them is untracked in git on a box whose next
 * deploy is an rsync — so they were one deploy away from being gone with no
 * trace but a page quietly speaking English again.
 *
 *   php artisan translate:import-lang            every generated locale
 *   php artisan translate:import-lang zh ja      just these
 *
 * Safe to re-run: a string a PERSON wrote is never overwritten, and re-importing
 * an unchanged file writes the same values back.
 */
class ImportLangFilesCommand extends Command
{
    protected $signature = 'translate:import-lang
        {locale?* : locales to import; omit for every one that has files}';

    protected $description = 'Load the lang files already on disk into the interface_translations table';

    public function handle(InterfaceStore $store, ContentLocales $locales): int
    {
        $wanted = (array) $this->argument('locale');

        if ($wanted === []) {
            // Every locale with a directory of its own, minus English — the
            // source lives in lang/en, is tracked, and is not this table's job.
            foreach (glob(lang_path('*'), GLOB_ONLYDIR) ?: [] as $dir) {
                $code = basename($dir);

                if ($code !== 'en') {
                    $wanted[] = $code;
                }
            }
        }

        if ($wanted === []) {
            $this->info('Nothing to import.');

            return self::SUCCESS;
        }

        $this->line('');
        $totalStrings = 0;

        foreach ($wanted as $code) {
            $locale = $locales->normalise((string) $code);

            if ($locale === null) {
                $this->warn(sprintf('  %-6s skipped — not a language this platform serves', $code));

                continue;
            }

            $result = $store->importFiles($locale);
            $totalStrings += $result['strings'];

            $this->line(sprintf(
                '  <options=bold>%-6s</> %4d files · <info>%5d strings</info> stored',
                $locale,
                $result['files'],
                $result['strings'],
            ));
        }

        $this->line('');
        $this->line("  <options=bold>{$totalStrings}</> strings are now in the database and survive a deploy.");
        $this->line('');

        return self::SUCCESS;
    }
}
