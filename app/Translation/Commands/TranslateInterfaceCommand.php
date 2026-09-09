<?php

namespace App\Translation\Commands;

use App\Translation\Services\ContentLocales;
use App\Translation\Services\InterfaceAgent;
use App\Translation\Services\InterfaceStore;
use App\Translation\Services\LangFileCatalog;
use App\Translation\Services\LangFileValidator;
use App\Translation\Services\LangFileWriter;
use Illuminate\Console\Command;

/**
 * Translate the product's own interface into a language.
 *
 * The other half of the promise. `translate:event` gives a reader the
 * organiser's words in their language; this gives them the buttons, the
 * headings and the empty states too — because a Portuguese event inside an
 * English app is a half-finished product, and a half-finished product is what
 * the reader notices.
 *
 * ── How it stays safe to run ─────────────────────────────────────────────────
 *
 *   · A key that already exists is NEVER overwritten. Correct a string by hand
 *     and it stays corrected; the next run only fills gaps. `--rewrite` is the
 *     explicit way to overrule that.
 *   · Every string is checked before it is kept (LangFileValidator) and every
 *     FILE is linted and executed in isolation before it is installed
 *     (LangFileWriter). A refused string is simply absent, and Laravel falls
 *     back to English for that key alone — the state we are in today, so a
 *     refusal can never be a regression.
 *   · Writing `lang/pt/` changes NOTHING on its own. A locale is served as an
 *     interface language only once it is listed in config/locales.php, which
 *     is a deliberate act taken after `--check` passes.
 *
 * ── Start here ───────────────────────────────────────────────────────────────
 *
 *   php artisan translate:interface ar --check     prove the checker is sane
 *   php artisan translate:interface pt --tier=visitor
 *   php artisan translate:interface pt --check
 */
class TranslateInterfaceCommand extends Command
{
    protected $signature = 'translate:interface
        {locale : a code from config/content_locales.php, e.g. pt}
        {--tier=event : event | visitor | member | staff | all}
        {--file= : one lang file only, e.g. events}
        {--check : validate what is already on disk and write nothing}
        {--dry-run : say what would be translated, call no model}
        {--rewrite : replace existing keys instead of only filling gaps}
        {--write-files : ALSO mirror the result into lang/<code>/*.php (rows are the store)}
        {--promote : after a clean --check, list the locale in config/locales.php so it is actually served}';

    protected $description = "Translate the interface — buttons, headings, labels — into another language";

    /**
     * What the software IS, told to the translator once.
     *
     * Without it "Draw", "Open", "Seed", "Bye" and "Mat" are guesses. Short
     * strings carry no context of their own, which is exactly why they are the
     * hard case.
     */
    private const CONTEXT = 'A platform used by sports clubs — mostly combat sports such as taekwondo, karate and Brazilian jiu-jitsu, plus football, swimming and fitness. '
        .'Members join clubs, pay subscriptions, follow schedules and enter competitions. Competitions have divisions by weight, belt rank and age; a DRAW is a knockout bracket, a MAT is where bouts are fought, '
        .'a BOUT is one match, a SEED is a competitor\'s position in the draw, a BYE is an unopposed advance. Club staff run admin screens for members, packages, invoices and events.';

    public function handle(
        LangFileCatalog $catalog,
        LangFileValidator $validator,
        LangFileWriter $writer,
        InterfaceAgent $agent,
        ContentLocales $locales,
        InterfaceStore $store,
    ): int {
        $locale = $locales->normalise((string) $this->argument('locale'));

        if ($locale === null) {
            $this->error('Not a language this platform serves. See config/content_locales.php.');

            return self::FAILURE;
        }

        if ($locale === 'en') {
            $this->error('English is the source. There is nothing to translate it into.');

            return self::FAILURE;
        }

        /*
         * `--file` accepts either the plain group ("events") or the full
         * namespaced id ("scoreboard::bjj_messages"), because both are how the
         * files are named in this command's own output.
         */
        $wanted = (string) $this->option('file');

        $files = $wanted !== ''
            ? array_filter(
                $catalog->files($locale),
                fn ($f, $id) => $id === $wanted || $f['group'] === $wanted,
                ARRAY_FILTER_USE_BOTH,
            )
            : $catalog->filesFor((string) $this->option('tier'), $locale);

        if ($files === []) {
            $this->error('No lang files matched. --tier=visitor|member|staff|all, or --file=events.');

            return self::FAILURE;
        }

        $this->line('');
        $this->line("  <options=bold>{$locales->name($locale)}</> ({$locales->native($locale)}) · "
            .count($files).' files · '.($this->option('check') ? 'checking' : 'translating'));
        $this->line('');

        $result = $this->option('check')
            ? $this->check($files, $catalog, $validator, $locale, $store)
            : $this->generate($files, $locale, $catalog, $validator, $writer, $agent, $store);

        /*
         * Promotion is what actually SERVES the language. Generating
         * `lang/pt/` changes nothing on its own — `config/locales.php` is the
         * switch — and doing it by hand for sixty-six languages is sixty-six
         * chances to typo a config file every page load reads.
         *
         * Gated on a clean run, so a language is never served half-checked.
         */
        /*
         * ⚠️ Only from a FULL tier. Being listed in config/locales.php offers
         * the language to members as their account language, so it must mean
         * the whole product is translated — not just an event. An event-tier
         * language already works inside events without being listed.
         */
        if ($this->option('promote') && (string) $this->option('tier') === 'event') {
            $this->line('');
            $this->line('  <fg=yellow>Not promoting.</> This is EVENT coverage, and it already works inside');
            $this->line('  events without being listed. config/locales.php offers a language as a');
            $this->line('  member\'s ACCOUNT language, which needs --tier=all.');

            return $result;
        }

        /*
         * ⚠️ Promotion re-verifies. It does not trust `$result`.
         *
         * Listing a locale in config/locales.php is what OFFERS it to a member
         * as their account language, and doing that with gaps is precisely the
         * half-English product this whole exercise exists to stop — French and
         * Spanish were promoted and un-promoted the same day in September 2026
         * for exactly that. So the gate is a fresh, independent completeness
         * check of what is actually on disk, whatever the generation step
         * thought it had done.
         */
        if ($this->option('promote')) {
            if ($result !== self::SUCCESS || $this->check($files, $catalog, $validator, $locale, $store) !== self::SUCCESS) {
                $this->line('');
                $this->line('  <error>Not promoting.</> This locale is not complete, and a language that is');
                $this->line('  offered but half-translated is worse than one that is not offered at all.');
                $this->line('');

                return self::FAILURE;
            }

            $this->promote($locale, $locales);
        }

        return $result;
    }

    /**
     * On the `event` tier, keep only the strings the event surface asks for.
     *
     * ⚠️ This is the difference between translating a competition and
     * translating the whole product: ~880 strings instead of ~9,500, which is
     * a couple of minutes and pennies rather than half an hour and a few
     * dollars — per language, every language.
     *
     * An event page is what gets shared with the world; an admin panel is not.
     *
     * @param  array<string,string>  $strings
     * @return array<string,string>
     */
    private function narrow(LangFileCatalog $catalog, string $id, array $strings): array
    {
        if ((string) $this->option('tier') !== 'event' || $this->option('file')) {
            return $strings;
        }

        /*
         * ⚠️ WHOLE FILES. The key-level narrowing is gone.
         *
         * Scanning the source for `__('x.y')` and translating only what it
         * found looked like the obvious economy and was wrong. A static scan
         * cannot see a key assembled at runtime, a component reached
         * indirectly, or a file nobody thought to list — and every one of those
         * came back as an English button on a page that was otherwise
         * translated. Measured on this codebase: the scan claimed 1,264 of the
         * 6,805 strings in the same files were needed, and readers kept finding
         * English anyway.
         *
         * So the files the event surface draws on are taken ENTIRE, which
         * cannot miss anything by construction. It costs about a dollar a
         * language instead of a quarter — the right trade, because a page with
         * English buttons on it is expensive in a way a spare translated string
         * is not.
         *
         * `admin` is the single exception: 2,100 strings of club-staff chrome
         * of which an event page touches five. Whole-filing it for sixty-six
         * languages would be a third of the entire bill for strings no visitor
         * can reach.
         */
        if ($id !== 'admin') {
            return $strings;
        }

        $this->eventKeys ??= $catalog->eventSurfaceKeys();

        return $catalog->narrowToEvent($strings, $this->eventKeys, $id);
    }

    /** @var array<int,string>|null */
    private ?array $eventKeys = null;

    /**
     * Add the locale to config/locales.php, so the interface is actually
     * served in it.
     *
     * Appended as a plain line in the file's own style rather than rewritten
     * programmatically: this file is read on every request and a person needs
     * to be able to read, review and revert it as a diff.
     */
    private function promote(string $locale, ContentLocales $locales): void
    {
        $path = config_path('locales.php');
        $php = (string) file_get_contents($path);

        if (str_contains($php, "'".$locale."' =>")) {
            $this->line("  <fg=gray>{$locale} is already served as an interface language.</>");

            return;
        }

        $meta = $locales->meta($locale);

        if (! $meta) {
            return;
        }

        $line = sprintf(
            "    '%s' => ['name' => '%s', 'native' => '%s', 'dir' => '%s', 'flag' => %s],
",
            $locale,
            addslashes($meta['name']),
            addslashes($meta['native']),
            ($meta['dir'] ?? 'ltr') === 'rtl' ? 'rtl' : 'ltr',
            $locales->flag($locale) ? "'".$locales->flag($locale)."'" : 'null',
        );

        // Before the closing bracket of the returned array.
        $at = strrpos($php, '];');

        if ($at === false) {
            $this->warn('  Could not find where to add it in config/locales.php; add it by hand.');

            return;
        }

        file_put_contents($path, substr($php, 0, $at).$line.substr($php, $at));

        $this->line("  <info>Promoted.</info> The interface is now served in {$locales->name($locale)}.");
    }

    /** Validate what is on disk. Calls no model, writes nothing, costs nothing. */
    /**
     * Validate what is on disk and write nothing.
     *
     * ⚠️ A MISSING KEY FAILS THIS. It did not until 2026-09-09: only the
     * validator's rejections counted, so a file with thirteen absent strings
     * printed `ok`, the command exited zero, and `--promote` would happily have
     * served the language. The gap then had to be found by a person reading a
     * page — which is exactly how "Gallery" was reported in English on a
     * Chinese poster.
     *
     * Absent and rejected are the same thing to a reader: an English word on a
     * page that is not in English.
     */
    private function check(array $files, LangFileCatalog $catalog, LangFileValidator $validator, string $locale, InterfaceStore $store): int
    {
        $keys = 0;
        $missing = 0;
        $bad = 0;

        foreach ($files as $id => $file) {
            // Narrowed on the `event` tier, so "falling back to English" counts
            // the strings this surface actually asks for — not the 9,500 in the
            // tree, most of which it will never render.
            $source = $this->narrow($catalog, $id, $catalog->strings($file['source']));

            if ($source === []) {
                continue;
            }

            $target = array_intersect_key($store->stringsFor($locale, $id, $file['target']), $source);

            if ($target === []) {
                $this->line(sprintf('  <error>%-34s not translated</error>  <fg=gray>0/%d</>', $id, count($source)));
                $missing += count($source);

                continue;
            }

            $result = $validator->file($source, $target);

            /*
             * Refusals a person has read and accepted (config/translation.php).
             * Reported as `accepted`, never hidden: the whole point of this
             * check is that nothing passes without somebody deciding it should.
             */
            $accepted = [];

            /*
             * ⚠️ Read the array, do NOT pass this through `config()` with the
             * key appended. Laravel splits a config key on dots, so
             * `accepted_refusals.zh:personal.personal_event_create_…` is looked
             * up as a nested path and always misses — the whole "zh:file.key"
             * string IS the array key.
             */
            $exceptions = (array) config('translation.accepted_refusals', []);

            foreach (array_keys($result['problems']) as $key) {
                $reason = $exceptions[$locale.':'.$id.'.'.$key] ?? null;

                if (is_string($reason)) {
                    $accepted[$key] = $reason;
                    unset($result['problems'][$key]);
                }
            }

            $keys += count($target);
            $missing += count($result['missing']);
            $bad += count($result['problems']);

            $gaps = count($result['missing']);

            $this->line(sprintf(
                '  %-34s %s  <fg=gray>%d/%d</>%s',
                $id,
                $result['problems'] === [] && $gaps === 0 ? '<info>ok     </info>' : '<error>problem</error>',
                count($target) - count($result['problems']),
                count($source),
                $result['extra'] !== [] ? ' <fg=yellow>+'.count($result['extra']).' unknown</>' : '',
            ));

            foreach (array_slice($result['problems'], 0, 5, true) as $key => $why) {
                $this->line("      <fg=red>{$key}</>: {$why}");
            }

            foreach ($accepted as $key => $why) {
                $this->line("      <fg=cyan>{$key}</>: accepted — {$why}");
            }

            /*
             * Name them, with the ENGLISH beside each one.
             *
             * ⚠️ `$result['missing']` is a LIST of key names (array_values in
             * LangFileValidator::file()), not a map — reading it with
             * `array_keys()` printed "0", "1", "2" and told nobody anything,
             * which is the same uselessness as a bare count.
             */
            foreach (array_slice($result['missing'], 0, 8) as $key) {
                $english = mb_substr((string) ($source[$key] ?? ''), 0, 44);
                $this->line("      <fg=red>{$key}</>: absent — still English: \"{$english}\"");
            }
        }

        $this->line('');
        $this->line("  {$keys} translated · <".($missing ? 'error' : 'info').">{$missing} falling back to English</> · <"
            .($bad ? 'error' : 'info').">{$bad} rejected</>");

        if ($missing > 0 || $bad > 0) {
            $this->line('');
            $this->line('  <error>NOT COMPLETE.</> Re-run without --check to fill the gaps; a string the');
            $this->line('  model keeps refusing has to be written by hand into the locale file.');
            $this->line('  This locale must not be promoted until this reports zero.');
        }

        $this->line('');

        return ($bad > 0 || $missing > 0) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Translate a set, then go back for whatever the model left out.
     *
     * ⚠️ A model answering a sixty-item batch with fifty-seven items is
     * ORDINARY. It is not an error, nothing throws, and the response looks
     * perfectly well-formed — the missing three simply are not there. Before
     * this, those keys were written off in silence and stayed English, because
     * a later re-run only fills gaps and would lose them the same way again.
     *
     * So the stragglers are re-asked in progressively smaller batches. A short
     * list is easier for a model to complete than a long one, which is why the
     * retry usually succeeds where the first pass did not.
     *
     * @param  array<string, string>  $todo
     * @return array{values: array<string, string>, model: string}
     */
    private function translateWithRetries(InterfaceAgent $agent, array $todo, string $locale, string $id): array
    {
        $values = [];
        $model = '';
        $outstanding = $todo;

        for ($attempt = 1; $attempt <= 3 && $outstanding !== []; $attempt++) {
            $label = $attempt === 1 ? $id : $id.' (retry '.($attempt - 1).')';

            $result = $agent->translate($outstanding, $locale, self::CONTEXT, function ($done, $total) use ($label) {
                $this->getOutput()->write("\r  ".str_pad('', 40)."\r  ".str_pad($label, 34)." {$done}/{$total}\r");
            });

            $model = $result['model'] ?: $model;

            foreach ($result['values'] as $key => $value) {
                if (isset($outstanding[$key])) {
                    $values[$key] = $value;
                }
            }

            $outstanding = array_diff_key($outstanding, $values);
        }

        return ['values' => $values, 'model' => $model];
    }

    private function generate(
        array $files,
        string $locale,
        LangFileCatalog $catalog,
        LangFileValidator $validator,
        LangFileWriter $writer,
        InterfaceAgent $agent,
        InterfaceStore $store,
    ): int {
        $failed = 0;
        $totalWritten = 0;
        $totalRefused = 0;
        $totalDropped = 0;

        foreach ($files as $id => $file) {
            $source = $this->narrow($catalog, $id, $catalog->strings($file['source']));

            if ($source === []) {
                continue;
            }

            if ($this->option('dry-run')) {
                $gaps = array_diff_key($source, $store->stringsFor($locale, $id, $file['target']));

                $this->line($gaps === []
                    ? sprintf('  <fg=gray>%-34s complete</>', $id)
                    : sprintf('  %-34s <fg=cyan>%d strings</> would be translated', $id, count($gaps)));

                continue;
            }

            $started = microtime(true);
            $written = 0;
            $refused = [];
            $stopped = false;

            /*
             * ⚠️ PASSES, not one shot. Asked for by the user on 2026-09-09:
             * "redo the translation to make sure all is available and not
             * missing".
             *
             * One pass is not enough and never was. A model answering a batch
             * of sixty with fifty-seven is ordinary — nothing throws, the reply
             * is well-formed, the three are simply absent — and a single pass
             * wrote off whatever came back short, in silence. That is how the
             * word "Gallery" stayed English on a Chinese poster while this
             * command reported success.
             *
             * So each pass re-reads the file FROM DISK and asks only for what
             * is still missing. The batches shrink every time, and a short list
             * is markedly easier for a model to return complete than a long
             * one, which is why a second pass usually closes what the first
             * left. Previously-refused keys are asked again too: these models
             * are stochastic, and a string one attempt mangles the next often
             * gets right.
             *
             * Bounded at four. Past that it is not a dropped item — it is a
             * string this model will not translate, and it needs a person.
             */
            for ($pass = 1; $pass <= 4; $pass++) {
                $existing = $store->stringsFor($locale, $id, $file['target']);

                $todo = ($this->option('rewrite') && $pass === 1)
                    ? $source
                    : array_diff_key($source, $existing);

                if ($todo === []) {
                    break;
                }

                $label = $pass === 1 ? $id : $id.' (pass '.$pass.')';
                $this->getOutput()->write(sprintf('  %-34s ', $label));

                try {
                    $result = $this->translateWithRetries($agent, $todo, $locale, $label);
                } catch (\Throwable $e) {
                    $this->getOutput()->writeln('<error>failed</error> '.mb_substr($e->getMessage(), 0, 90));
                    $failed++;
                    $stopped = true;

                    /*
                     * Stop the whole run on the first file that cannot reach a
                     * provider. Grinding through thirty-four files to produce
                     * thirty-four empty ones wastes minutes, buries the real
                     * error under a wall of output, and leaves behind files
                     * that look finished. If the providers are down for one
                     * file they are down for all of them.
                     */
                    if (str_contains($e->getMessage(), 'every provider failed')) {
                        $this->line('');
                        $this->error('  Stopping: no AI provider is answering.');

                        if (str_contains($e->getMessage(), 'credit balance')) {
                            $this->line('  <fg=yellow>The Anthropic account is out of credit.</> Top it up, or point');
                            $this->line('  translation at another model under Admin → AI Providers.');
                            $this->line('');
                            $this->line('  <fg=yellow>It must be a model that can WRITE THE LANGUAGE.</> A code model');
                            $this->line('  answers fluently and wrongly — that is how sixty-seven languages');
                            $this->line('  were filled with broken text on 2026-09-09 without one error being');
                            $this->line('  raised. Code models are refused now (config/translation.php →');
                            $this->line('  unfit_models), which is why this stopped instead of carrying on.');
                        }

                        $this->line('');

                        return self::FAILURE;
                    }

                    break;
                }

                // Refuse anything that would break a page, and keep the rest.
                $good = [];
                $refused = [];

                foreach ($result['values'] as $key => $value) {
                    if (! isset($source[$key])) {
                        continue;
                    }

                    if ($why = $validator->reject($key, $source[$key], $value)) {
                        $refused[$key] = $why;

                        continue;
                    }

                    $good[$key] = trim($value);
                }

                /*
                 * ⚠️ ROWS, not a PHP file.
                 *
                 * The generated languages used to be written as
                 * `lang/<code>/*.php`, and that was three problems at once: only
                 * `lang/en` and `lang/ar` are tracked in git, so a deploy on
                 * this box would have deleted eleven generated languages without
                 * a trace; a UI could never trigger this, because it composes
                 * EXECUTABLE PHP out of a language model's output; and nobody
                 * could correct a word without a deploy. See the
                 * interface_translations migration.
                 *
                 * `putMany()` also carries the invariant the files could not: a
                 * string a PERSON wrote is skipped, never overwritten.
                 */
                /*
                 * ⚠️ The model that ANSWERED, not the name of this command.
                 *
                 * This column said "translate:interface" for every row, which
                 * meant that when sixty-seven languages turned out to have been
                 * written by a code model, nothing in the database could say so
                 * — the evidence was in a log line that had already rotated.
                 * Quality is a per-model question and this is the only place
                 * the answer can be kept.
                 */
                $written = $store->putMany($locale, $id, $good, $result['model'] ?: 'unknown');
                $write = ['written' => $written, 'error' => null];

                /*
                 * Files stay OPT-IN, for the two locales that are tracked in git
                 * and hand-maintained (en, ar) and for anybody who wants a diff
                 * to read. They are a mirror now, never the source.
                 */
                if ($this->option('write-files')) {
                    $write = $writer->write(
                        $file['target'],
                        ($this->option('rewrite') && $pass === 1) ? array_diff_key($existing, $good) : $existing,
                        $good,
                    );
                }

                if ($write['error'] !== null) {
                    $this->getOutput()->writeln("\r  ".str_pad($label, 34).' <error>REFUSED</error> '.$write['error']);
                    $failed++;
                    $stopped = true;

                    break;
                }

                $written += $write['written'];

                $this->getOutput()->writeln(sprintf(
                    "\r  %-34s <info>%4d written</info> <fg=gray>%s%.1fs</>",
                    $label,
                    $write['written'],
                    $refused !== [] ? count($refused).' refused · ' : '',
                    microtime(true) - $started,
                ));

                // Nothing landed this pass: asking a fifth time will not help.
                if ($write['written'] === 0) {
                    break;
                }
            }

            if ($stopped) {
                continue;
            }

            $totalWritten += $written;
            $totalRefused += count($refused);

            /*
             * ⚠️ The verdict is taken from the FILE ON DISK, not from what the
             * model claimed to return. Anything still absent here is a gap a
             * reader will see, and it is named — a count alone is what let
             * thirteen of these hide behind an "ok".
             */
            $stillMissing = array_diff_key($source, $store->stringsFor($locale, $id, $file['target']));

            if ($stillMissing !== []) {
                $totalDropped += count($stillMissing);

                $this->line(sprintf('  <error>%-34s %d STILL MISSING after 4 passes</error>', $id, count($stillMissing)));

                foreach (array_slice(array_keys($stillMissing), 0, 5) as $key) {
                    $reason = $refused[$key] ?? 'the model never returned it';
                    $this->line("      <fg=red>{$key}</>: {$reason} <fg=gray>(stays English)</>");
                }
            }
        }

        $this->line('');
        $this->line("  <options=bold>{$totalWritten}</> strings written"
            .($totalRefused ? ", {$totalRefused} refused" : ''));

        /*
         * ⚠️ A RUN THAT LEAVES A GAP IS A FAILED RUN.
         *
         * It used to return SUCCESS as long as no provider had fallen over,
         * so a language with thirteen missing strings finished green, passed
         * `--check` (which only counted rejections) and was eligible for
         * `--promote`. The gap then had to be found by a person reading a page.
         *
         * Incomplete is not a warning here. Nothing downstream — the check, the
         * promotion, a CI step — may treat this locale as done.
         */
        if ($totalDropped > 0) {
            $this->line('');
            $this->line("  <error>INCOMPLETE: {$totalDropped} strings are still English after four passes.</>");
            $this->line('  Re-run this command to try them again. A string that keeps coming back');
            $this->line('  refused has to be written into the locale file by hand — the validator is');
            $this->line('  refusing it for a reason (a changed number, an invented placeholder).');
            $this->line('');

            return self::FAILURE;
        }

        if (! $this->option('dry-run') && $failed === 0) {
            $this->line('');
            $this->line("  <info>Complete.</> Every string this tier asks for exists in {$locale}.");
            $this->line("  Next: <options=bold>php artisan translate:interface {$locale} --check</>");
            $this->line("  Then add '{$locale}' to config/locales.php to serve it as an interface language.");
        }

        $this->line('');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
