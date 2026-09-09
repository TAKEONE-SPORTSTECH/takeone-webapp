<?php

namespace App\Translation\Commands;

use App\Translation\Models\InterfaceTranslation;
use App\Translation\Services\ContentLocales;
use App\Translation\Services\DatabaseTranslationLoader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Throw away machine-written interface strings for a language.
 *
 * ── Why this needs to exist ─────────────────────────────────────────────────
 *
 * On 2026-09-09 the AI account ran out of credit mid-run. The provider chain
 * fell through to `qwen3-coder:30b` — a code model — which translated 19,666
 * strings into sixty-seven languages, confidently and wrongly. In Albanian the
 * GOLD medal came back as "Medalja e zezë", the black medal.
 *
 * Text like that is worse than no translation: English on a Chinese page is
 * obviously untranslated, but a fluent wrong sentence gets believed. So when a
 * run turns out to have been made by the wrong model, the answer is to remove
 * it rather than to leave it and hope the next pass overwrites everything.
 *
 * ⚠️ A PERSON'S CORRECTIONS ARE NEVER TOUCHED. `origin = human` survives, for
 * the same reason it survives a re-translation: somebody read that string and
 * decided what it should say.
 *
 * Removes the exported lang files too, unless told not to — they are a mirror
 * of these rows (translate:export-lang) and leaving them behind would keep
 * serving the very text this command exists to delete. `en` and `ar` are never
 * touched: they are hand-written and tracked in git.
 *
 *   php artisan translate:discard-interface sq --force
 *   php artisan translate:discard-interface --model=qwen --force
 */
class DiscardInterfaceCommand extends Command
{
    protected $signature = 'translate:discard-interface
        {locale?* : locales to clear; omit for every locale that has machine rows}
        {--model= : only rows whose recorded model contains this string}
        {--keep-files : leave lang/<code>/*.php alone}
        {--force : required — this deletes}';

    protected $description = 'Delete machine-written interface strings (never a human correction) so a language can be regenerated cleanly';

    /** Hand-written and tracked in git; never generated, never deleted here. */
    private const PROTECTED = ['en', 'ar'];

    public function handle(ContentLocales $locales): int
    {
        $wanted = (array) $this->argument('locale');
        $model = (string) $this->option('model');

        $query = InterfaceTranslation::query()->where('origin', InterfaceTranslation::MACHINE);

        if ($wanted !== []) {
            $codes = array_values(array_filter(array_map(
                fn ($c) => $locales->normalise((string) $c),
                $wanted,
            )));

            if ($codes === []) {
                $this->error('None of those are languages this platform serves.');

                return self::FAILURE;
            }

            $query->whereIn('locale', $codes);
        }

        $query->whereNotIn('locale', self::PROTECTED);

        if ($model !== '') {
            $query->where('model', 'like', '%'.$model.'%');
        }

        $affected = (clone $query)->selectRaw('locale, COUNT(*) n')->groupBy('locale')->pluck('n', 'locale');
        $total = $affected->sum();

        if ($total === 0) {
            $this->info('Nothing to discard.');

            return self::SUCCESS;
        }

        $this->line('');
        $this->line('  This will delete <options=bold>'.number_format($total).'</> machine-written strings:');

        foreach ($affected as $locale => $n) {
            $human = InterfaceTranslation::where('locale', $locale)
                ->where('origin', InterfaceTranslation::HUMAN)->count();

            $this->line(sprintf('    %-6s %6s machine%s', $locale, number_format($n),
                $human ? '  ('.$human.' human corrections kept)' : ''));
        }

        if (! $this->option('force')) {
            $this->line('');
            $this->warn('  Nothing deleted. Re-run with --force.');
            $this->line('');

            return self::FAILURE;
        }

        $query->delete();

        foreach (array_keys($affected->all()) as $locale) {
            DatabaseTranslationLoader::forgetLocale((string) $locale);

            if ($this->option('keep-files')) {
                continue;
            }

            // The exported mirror. Leaving it would keep serving the text this
            // command just deleted, through the loader's file fallback.
            foreach ((array) glob(lang_path($locale.'/*.php')) as $file) {
                File::delete($file);
            }

            @rmdir(lang_path((string) $locale));
        }

        $this->line('');
        $this->line('  <options=bold>'.number_format($total).'</> discarded. Regenerate with:');
        $this->line('    php artisan translate:interface <locale> --tier=event');
        $this->line('');

        return self::SUCCESS;
    }
}
