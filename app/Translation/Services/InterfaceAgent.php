<?php

namespace App\Translation\Services;

use Illuminate\Support\Facades\Log;

/**
 * Translates the product's OWN words — buttons, headings, empty states.
 *
 * A sibling of TranslationAgent, not an extension of it, because the two jobs
 * only look alike. That one is handed a whole event and asked to write it again
 * for a reader deciding whether to enter a competition; prose, context, voice.
 * This one is handed four hundred disconnected labels — "Draw", "Open",
 * "Seed", "Bye", "Match" — and the hard part is the opposite: those words are
 * unambiguous only to something that knows this is a bracket. Short strings are
 * the DIFFICULT case, not the easy one, and they need a different instruction.
 *
 * Editing TranslationAgent to serve both would have made one prompt worse at
 * both jobs, so its prompt is left exactly as it is.
 *
 * ── The batching trick ───────────────────────────────────────────────────────
 *
 * Strings are sent INDEX-KEYED — `{"1": "Save", "2": "Cancel"}` — with the real
 * dotted keys held here. Sending `personal.event_show_how_it_runs` as the key
 * would spend a token on every segment of every key in BOTH directions, and the
 * keys are roughly 60% of the payload. The model does not need them and cannot
 * misspell what it never sees.
 */
class InterfaceAgent
{
    /** Strings per request. Small enough to retry cheaply, big enough for context. */
    private const BATCH = 60;

    public function __construct(private ProviderChain $chain, private ContentLocales $locales) {}

    /**
     * Translate a group of strings into one language.
     *
     * @param  array<string,string>  $strings  dotted key => English
     * @param  callable|null  $progress  called with (done, total) after each batch
     * @return array{values: array<string,string>, model: string, refused: array<string,string>}
     */
    public function translate(array $strings, string $locale, string $context, ?callable $progress = null): array
    {
        $links = $this->chain->links();

        if ($links === []) {
            throw new \RuntimeException('No AI provider is configured. Add one under Admin → AI Providers.');
        }

        $values = [];
        $model = '';
        $done = 0;
        $total = count($strings);

        foreach (array_chunk($strings, self::BATCH, true) as $batch) {
            $keys = array_keys($batch);
            $indexed = [];

            foreach (array_values($batch) as $i => $text) {
                $indexed[(string) ($i + 1)] = $text;
            }

            $answer = $this->ask($indexed, $locale, $context, $links, $model);

            foreach ($answer as $index => $text) {
                $position = (int) $index - 1;

                // Key-matched against what was sent. An index we did not ask
                // about has nowhere to go and is dropped.
                if (isset($keys[$position]) && is_string($text)) {
                    $values[$keys[$position]] = $text;
                }
            }

            $done += count($batch);

            if ($progress) {
                $progress($done, $total);
            }
        }

        return ['values' => $values, 'model' => $model, 'refused' => []];
    }

    /**
     * One batch, down the provider chain until something answers.
     *
     * @param  array<string,string>  $indexed
     * @param  array<int,Link>  $links
     * @return array<string,string>
     */
    private function ask(array $indexed, string $locale, string $context, array $links, string &$model): array
    {
        $failures = [];

        foreach ($links as $link) {
            try {
                $reply = $link->driver->chat(
                    [
                        ['role' => 'system', 'content' => $this->system($locale, $context)],
                        ['role' => 'user', 'content' => json_encode($indexed, JSON_UNESCAPED_UNICODE)],
                    ],
                    [],
                    [
                        'max_tokens' => (int) config('translation.max_tokens', 8000),
                        'temperature' => (float) config('translation.temperature', 0.3),
                        'json' => config('translation.json_mode', true) && $link->supportsJsonMode(),
                        // Labels are not a reasoning problem either.
                        'effort' => config('translation.effort', 'low'),
                    ],
                );

                $decoded = $this->decode((string) ($reply['content'] ?? ''));

                if (is_array($decoded) && $decoded !== []) {
                    $model = $link->model;

                    return $decoded;
                }

                $failures[] = $link->describe().': nothing usable';
            } catch (\Throwable $e) {
                $failures[] = $link->describe().': '.mb_substr($e->getMessage(), 0, 100);
            }
        }

        Log::warning('translation.interface_batch_failed', ['locale' => $locale, 'after' => $failures]);

        /*
         * ⚠️ THROW, do not return silence.
         *
         * This used to return [] on the reasoning that a failed batch is just
         * a gap — Laravel falls back to English per key, so nothing breaks.
         * True, and it hid a total outage: when the AI account ran out of
         * credit, every batch of every language failed, the command reported
         * "0 strings written" in a calm green line, and empty lang files were
         * created that looked for all the world like finished work.
         *
         * A whole batch failing means the providers are not answering. That is
         * an incident, not a gap, and the operator has to hear about it.
         */
        throw new \RuntimeException('every provider failed: '.implode(' | ', $failures));
    }

    private function system(string $locale, string $context): string
    {
        $target = $this->locales->name($locale);
        $native = $this->locales->native($locale);
        $rtl = $this->locales->dir($locale) === 'rtl';

        $lines = [];

        $lines[] = "You are localising the user interface of a sports-club platform into {$target} ({$native}).";
        $lines[] = '';
        $lines[] = 'These are INTERFACE STRINGS: button labels, headings, table columns, empty states, error messages, tooltips. They are read by athletes, coaches, club staff and parents while using the product, not as prose.';
        $lines[] = '';
        $lines[] = 'What this software is about: '.$context;
        $lines[] = '';
        $lines[] = 'Rules:';
        $lines[] = '• Write what a '.$target.' product would actually put on that control. Match the register and the LENGTH of a UI label — a button says "Save", not "Please save your changes now".';
        $lines[] = '• Use the conventional '.$target.' term for common software actions (save, cancel, delete, back, search, settings, sign in). Do not invent a literal translation where the language already has a standard one.';
        $lines[] = '• ⚠️ `:word` tokens like :name, :count, :club, :date are PLACEHOLDERS that the software replaces with real values. Reproduce each one exactly, unchanged, in a position that reads naturally. Never translate, rename or drop one. (Inside a plural form you may omit one where that form does not need the number — Arabic\'s dual, for example.)';
        $lines[] = '• ⚠️ Strings containing `|` are PLURAL forms — `{1}one thing|[2,*]:count things`. Give the forms '.$target.' actually needs, each with its own `{n}` or `[n,*]` range label, even if that is more forms than the English has. Never return a plural string as a single form.';
        $lines[] = '• Keep any HTML tags exactly as they are.';
        $lines[] = '• Do not change a number into a different number. Spelling a number out as a word is fine.';
        $lines[] = '• Leave these exactly as written, in Latin script: TAKEONE, Gi, No-Gi, ippon, wazari, kata, kumite, poomsae, dan, kyu, BHD and other currency codes, WhatsApp, IBAN, CPR, QR, PDF, GPS, BMI, BMR, VAT.';
        $lines[] = '• Belt colours, weight classes and sport vocabulary take the form used by that sport in '.$target.', which is often the original word.';

        if ($rtl) {
            $lines[] = '• '.$target.' is right-to-left. Write natural right-to-left text; do not insert directional marks and do not reorder numbers by hand — the page handles direction itself.';
        }

        $lines[] = '';
        $lines[] = 'You will receive a JSON object whose keys are numbers and whose values are English interface strings.';
        $lines[] = 'Reply with a JSON object and nothing else: the SAME numeric keys, each mapped to your '.$target.' version.';
        $lines[] = 'Return every key you were given. Do not add keys, do not nest, do not use code fences, do not comment.';
        $lines[] = 'If a string is a code, an acronym or a brand with no '.$target.' form, return it unchanged.';

        return implode("\n", $lines);
    }

    /** JSON out of a reply that may be wrapped despite being told not to. */
    private function decode(string $raw): mixed
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        if (is_array($direct = json_decode($raw, true))) {
            return $direct;
        }

        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        return json_decode(substr($raw, $start, $end - $start + 1), true);
    }
}
