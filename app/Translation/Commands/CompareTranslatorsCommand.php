<?php

namespace App\Translation\Commands;

use App\Models\AiProvider;
use App\Models\ClubEvent;
use App\Translation\Services\ContentLocales;
use App\Translation\Services\ProviderChain;
use App\Translation\Services\TranslationAgent;
use App\Translation\Services\Translator;
use Illuminate\Console\Command;

/**
 * Put the models side by side on your OWN content, and pick one on evidence.
 *
 * The question this answers is the one that decides whether the platform can
 * run on its own hardware: *is a model I can self-host good enough for this?*
 * Benchmarks will not tell you — a 7B model that scores well on translation
 * benchmarks may still turn "Victory BJJ Academy" into something a competitor
 * cannot find, or quietly drop the minus from a weight class. The only honest
 * test is this event, this sport's vocabulary, this organiser's writing.
 *
 * So it runs the real agent — same prompt, same guards, same parser — through
 * every configured provider in turn, and prints what each one produced next to
 * what it cost in seconds and how many fields survived the checks.
 *
 * WRITES NOTHING. It never touches content_translations, so it is safe to run
 * against a live event as often as you like. It does spend tokens at every paid
 * provider it is pointed at, which is why it says so before it starts.
 */
class CompareTranslatorsCommand extends Command
{
    protected $signature = 'translate:compare
        {event : the event uuid}
        {locale : the language to translate into, e.g. pt}
        {--providers= : comma-separated ai_providers ids; default is every enabled one plus the built-in local model}
        {--fields=4 : how many fields to print per model}';

    protected $description = 'Translate one event with every configured model and print the results side by side';

    public function handle(TranslationAgent $agent, ProviderChain $chain, ContentLocales $locales): int
    {
        $event = ClubEvent::query()->where('uuid', $this->argument('event'))->first();

        if (! $event) {
            $this->error('No event with that uuid.');

            return self::FAILURE;
        }

        $locale = $locales->normalise((string) $this->argument('locale'));

        if ($locale === null) {
            $this->error('Not a language this platform serves. See config/content_locales.php.');

            return self::FAILURE;
        }

        $document = Translator::document($event);

        if ($document === []) {
            $this->error('This event has no words to translate.');

            return self::FAILURE;
        }

        $links = $this->links($chain);

        if ($links === []) {
            $this->error('No providers to compare. Add one under Admin → AI Providers.');

            return self::FAILURE;
        }

        $this->line('');
        $this->line("  <options=bold>{$event->title}</>");
        $this->line('  '.$locales->name($event->sourceLocale()).' → '.$locales->name($locale)
            .' · '.count($document).' fields · '.count($links).' models');
        $this->line('  <fg=gray>Nothing is saved. Paid providers are charged for each run.</>');
        $this->line('');

        $show = max(1, (int) $this->option('fields'));
        $rows = [];

        foreach ($links as $link) {
            $this->getOutput()->write('  '.str_pad($link->describe(), 44).' … ');

            $started = microtime(true);

            try {
                /*
                 * The agent is asked for THIS link only, so each model is
                 * measured on its own rather than the chain's first success —
                 * which is the whole point of a comparison.
                 */
                $result = $agent->translateWith($link, $document, $event->sourceLocale(), $locale, $event->translationContext());
                $seconds = microtime(true) - $started;

                $got = count($result['values']);
                $total = count($document);

                $this->getOutput()->writeln(sprintf(
                    '%s <fg=gray>%d/%d fields · %.1fs</>',
                    $got === $total ? '<info>ok     </info>' : '<fg=yellow>partial</>',
                    $got, $total, $seconds,
                ));

                $rows[] = [$link, $result['values'], $seconds, null];
            } catch (\Throwable $e) {
                $this->getOutput()->writeln('<error>failed </error> <fg=gray>'.mb_substr($e->getMessage(), 0, 70).'</>');
                $rows[] = [$link, [], microtime(true) - $started, $e->getMessage()];
            }
        }

        // ── The words themselves, field by field, every model under each ────
        //
        // Grouped by FIELD rather than by model on purpose: the judgement being
        // made is "which of these sentences is best", and that is unanswerable
        // when the candidates are three screens apart.
        foreach (array_slice(array_keys($document), 0, $show) as $field) {
            $this->line('');
            $this->line("  <options=bold>{$field}</>");
            $this->line('    <fg=gray>source  </> '.$this->clip($document[$field]));

            foreach ($rows as [$link, $values, , $error]) {
                $label = str_pad(mb_substr($link->label, 0, 8), 8);

                if ($error !== null) {
                    $this->line("    <fg=red>{$label}</> —");

                    continue;
                }

                $value = $values[$field] ?? null;

                $this->line($value === null
                    // Refused by the guards, or simply not returned. Either way
                    // the page would show the source here.
                    ? "    <fg=yellow>{$label}</> <fg=gray>(not translated — left in the source language)</>"
                    : "    <fg=cyan>{$label}</> ".$this->clip($value));
            }
        }

        $this->line('');
        $this->line('  <fg=gray>Set the winner as the default under Admin → AI Providers, or pin the</>');
        $this->line('  <fg=gray>order with TRANSLATION_PROVIDERS (see config/translation.php).</>');
        $this->line('');

        return self::SUCCESS;
    }

    /** @return array<int, \App\Translation\Services\Link> */
    private function links(ProviderChain $chain): array
    {
        $requested = array_filter(array_map('intval', explode(',', (string) $this->option('providers'))));

        if ($requested === []) {
            return $chain->links();
        }

        // A named subset — compare two candidates without involving the rest.
        config(['translation.providers' => $requested]);

        $missing = array_diff($requested, AiProvider::query()->whereIn('id', $requested)->pluck('id')->all());

        foreach ($missing as $id) {
            $this->warn("  No provider with id {$id}; skipping it.");
        }

        return $chain->links();
    }

    private function clip(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        return mb_strlen($text) > 96 ? mb_substr($text, 0, 95).'…' : $text;
    }
}
