<?php

namespace App\Translation\Commands;

use App\Translation\Services\ContentLocales;
use App\Translation\Services\LangFileCatalog;
use App\Translation\Services\LangFileWriter;
use App\Translation\Services\InterfaceStore;
use App\Translation\Models\InterfaceTranslation;
use Illuminate\Console\Command;

/**
 * Write the database's interface strings back out as lang files.
 *
 * ── Why a store that was moved OFF files still writes them ──────────────────
 *
 * The rows are the store; the files are the FALLBACK, and the fallback is what
 * crosses a deploy. Translations live in the database of the machine they were
 * generated on — so a language translated on staging does not exist on
 * production, which has its own database and would render English.
 *
 * Committing the mirror is what carries them. The loader reads rows first and
 * files behind (Services\DatabaseTranslationLoader), so wherever both exist the
 * rows win and a correction made in the admin screen still outranks the file
 * it was generated from.
 *
 *   php artisan translate:export-lang            every locale with rows
 *   php artisan translate:export-lang zh ja
 *
 * ⚠️ Not the reverse of `translate:import-lang` in effect, only in direction:
 * import is a one-off that rescued files into the database, this is routine and
 * safe to re-run. Neither destroys anything the other made.
 */
class ExportLangFilesCommand extends Command
{
    protected $signature = 'translate:export-lang
        {locale?* : locales to write; omit for every one that has rows}';

    protected $description = 'Mirror the interface_translations rows into lang/<code>/*.php so a deploy carries them';

    public function handle(
        LangFileCatalog $catalog,
        LangFileWriter $writer,
        ContentLocales $locales,
        InterfaceStore $store,
    ): int {
        $wanted = (array) $this->argument('locale');

        if ($wanted === []) {
            $wanted = InterfaceTranslation::query()
                ->select('locale')->distinct()->pluck('locale')->all();
        }

        $this->line('');
        $files = 0;
        $strings = 0;

        foreach ($wanted as $code) {
            $locale = $locales->normalise((string) $code);

            if ($locale === null || $locale === 'en') {
                continue;   // English is the source and lives in lang/en
            }

            $wrote = 0;

            foreach ($catalog->files($locale) as $id => $file) {
                [$namespace, $group] = InterfaceTranslation::splitFileId($id);

                $rows = InterfaceTranslation::query()
                    ->where('locale', $locale)
                    ->where('group', $group)
                    ->when($namespace === null,
                        fn ($q) => $q->whereNull('namespace'),
                        fn ($q) => $q->where('namespace', $namespace))
                    ->pluck('value', 'key')
                    ->all();

                if ($rows === []) {
                    continue;
                }

                /*
                 * Merged over whatever the file already holds rather than
                 * replacing it: `lang/ar` is hand-written and tracked, and a
                 * key the database has no row for is not a key to delete.
                 */
                $existing = is_file($file['target']) ? $catalog->strings($file['target']) : [];

                $result = $writer->write($file['target'], $existing, $rows);

                if ($result['error'] !== null) {
                    $this->warn(sprintf('  %-6s %-34s %s', $locale, $id, $result['error']));

                    continue;
                }

                $files++;
                $wrote += count($rows);
            }

            $strings += $wrote;
            $this->line(sprintf('  <options=bold>%-6s</> <info>%5d strings</info> written to lang/%s/', $locale, $wrote, $locale));
        }

        $this->line('');
        $this->line("  <options=bold>{$strings}</> strings across {$files} files. Commit them and a deploy carries the languages.");
        $this->line('');

        return self::SUCCESS;
    }
}
