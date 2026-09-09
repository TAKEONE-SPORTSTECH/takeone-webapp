<?php

namespace App\Translation\Commands;

use App\Models\ClubEvent;
use App\Translation\Jobs\TranslateContent;
use App\Translation\Services\ContentLocales;
use App\Translation\Services\TranslationAgent;
use App\Translation\Services\Translator;
use Illuminate\Console\Command;

/**
 * Translate one event from the command line.
 *
 * Two jobs, both of them real:
 *
 *  1. **Proving the provider works** without standing on a public page tapping
 *     a language and hoping. `--sync` runs it in the foreground and prints what
 *     came back, so a wrong key or a model that will not produce JSON is a
 *     visible error in a terminal rather than a spinner that times out in
 *     somebody's hand.
 *
 *  2. **Warming a language before an event opens.** An organiser expecting a
 *     Brazilian squad can have the Portuguese written the night before, so the
 *     first of them to open the link waits for nothing at all.
 */
class TranslateEventCommand extends Command
{
    protected $signature = 'translate:event
        {event : the event uuid}
        {locale?* : one or more locales from config/content_locales.php}
        {--sync : run now and print the result, instead of queueing it}
        {--force : redo it even where a fresh translation already exists}';

    protected $description = "Write an event's own words into other languages";

    public function handle(Translator $translator, TranslationAgent $agent, ContentLocales $locales): int
    {
        $event = ClubEvent::query()->where('uuid', $this->argument('event'))->first();

        if (! $event) {
            $this->error('No event with that uuid.');

            return self::FAILURE;
        }

        $codes = (array) $this->argument('locale');

        if ($codes === []) {
            $this->error('Name at least one locale, e.g. `translate:event '.$event->uuid.' pt fr`.');

            return self::FAILURE;
        }

        $this->line('');
        $this->line("  <options=bold>{$event->title}</>");
        $this->line("  written in {$locales->name($event->sourceLocale())} · ".count(Translator::document($event)).' fields');
        $this->line('');

        $failed = 0;

        foreach ($codes as $raw) {
            $locale = $locales->normalise((string) $raw);

            if ($locale === null) {
                $this->line("  <fg=yellow>skip</>  {$raw} — not in config/content_locales.php");

                continue;
            }

            /*
             * Skipped only when the event really is entirely in this language.
             * An event declared English with an Arabic description has work to
             * do for an English reader — that is the whole point of the
             * per-field detection.
             */
            if ($locale === $event->sourceLocale() && $translator->required($event, $locale) === []) {
                $this->line("  <fg=gray>skip</>  {$locale} — the event is already entirely in this language");

                continue;
            }

            if ($this->option('force')) {
                // The machine's words only. A person's corrections survive
                // --force, exactly as they survive everything else here.
                \App\Translation\Translations::dropMachine($event, $locale);
            }

            if (! $this->option('sync')) {
                $this->line("  <fg=cyan>queued</>  {$locales->name($locale)}");
                $translator->ensure($event, $locale);

                continue;
            }

            $this->getOutput()->write("  <fg=cyan>·</> {$locales->name($locale)} … ");
            $started = microtime(true);

            try {
                (new TranslateContent($event->getMorphClass(), $event->id, $locale))
                    ->handle($translator, $agent, $locales);

                $done = $translator->isComplete($event, $locale, false);
                $this->getOutput()->writeln(sprintf(
                    '%s <fg=gray>(%.1fs)</>',
                    $done ? '<info>done</info>' : '<fg=yellow>partial</>',
                    microtime(true) - $started,
                ));

                if (! $done) {
                    $failed++;
                }

                if ($this->output->isVerbose()) {
                    foreach ($translator->for($event, $locale, false)->all() as $field => $value) {
                        $this->line(sprintf('      <fg=gray>%-18s</> %s', $field, mb_substr(str_replace("\n", ' ', $value), 0, 90)));
                    }
                }
            } catch (\Throwable $e) {
                $this->getOutput()->writeln('<error>failed</error>');
                $this->line('      '.$e->getMessage());
                $failed++;
            }
        }

        $this->line('');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
