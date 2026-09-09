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

    public function __construct(
        private ProviderChain $chain,
        private ContentLocales $locales,
        private LangFileValidator $validator,
    ) {}

    /**
     * Translate a group of strings into one language.
     *
     * @param  array<string,string>  $strings  dotted key => English
     * @param  callable|null  $progress  called with (done, total) after each batch
     * @return array{values: array<string,string>, model: string, refused: array<string,string>}
     */
    public function translate(array $strings, string $locale, string $context, ?callable $progress = null): array
    {
        $links = $this->fitToTranslate($this->chain->links());

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

            /*
             * ⚠️ SECOND PASS. Asked for on 2026-09-09 after a Japanese speaker
             * read the result and said it was "precise but wrong grammar" —
             * the same verdict, in different words, as the Albanian one.
             *
             * That is exactly what a bulk pass produces and it is not a sign of
             * a bad model. Sixty disconnected labels arrive with no sentence
             * around them, and the model spends its attention on choosing the
             * right WORD for each. What suffers is everything that only exists
             * between words: Japanese particles and politeness level, Albanian
             * definiteness and case, German gender, Arabic construct state.
             * Meaning right, morphology wrong.
             *
             * So the batch goes back for editing, with the English beside it
             * and one instruction: fix the language, change nothing else. It is
             * a different task from translating and gets a different prompt,
             * for the same reason this class exists separately from
             * TranslationAgent at all.
             */
            $answer = $this->refine($indexed, $answer, $locale, $context, $links);

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
     * Hand the batch back for proofreading, and keep only what survives.
     *
     * ⚠️ AN EDIT PASS, NOT A SECOND TRANSLATION. The editor sees its own
     * output next to the English and is asked to correct the language — never
     * to reconsider the wording. That distinction is the whole value: asked to
     * translate again, a model produces a different translation and the
     * meaning drifts; asked to edit, it fixes agreement and leaves the choice
     * of words alone.
     *
     * ⚠️ EVERY CORRECTION IS CHECKED BEFORE IT IS KEPT. An editor that
     * "tidies" `:count` into `:number`, drops a plural form or changes a price
     * has broken the string, and the un-edited version is better. Each returned
     * value is put through the same validator the command uses, against the
     * same English source, and anything it refuses is discarded in favour of
     * the first pass.
     *
     * Failing entirely is not an error either: the first pass is a usable
     * translation, so a refusal, a timeout or an unparseable reply leaves the
     * batch exactly as it was.
     *
     * @param  array<string,string>  $indexed   index => English
     * @param  array<string,string>  $draft     index => first-pass translation
     * @param  array<int,Link>  $links
     * @return array<string,string>
     */
    private function refine(array $indexed, array $draft, string $locale, string $context, array $links): array
    {
        if (! config('translation.refine', true) || $draft === []) {
            return $draft;
        }

        // Only what we actually asked about, paired for the editor.
        $pairs = [];

        foreach ($draft as $index => $text) {
            if (isset($indexed[$index]) && is_string($text) && trim($text) !== '') {
                $pairs[$index] = ['en' => $indexed[$index], $locale => $text];
            }
        }

        if ($pairs === []) {
            return $draft;
        }

        foreach ($links as $link) {
            try {
                $reply = $link->driver->chat(
                    [
                        ['role' => 'system', 'content' => $this->editor($locale, $context)],
                        ['role' => 'user', 'content' => json_encode($pairs, JSON_UNESCAPED_UNICODE)],
                    ],
                    [],
                    [
                        'max_tokens' => (int) config('translation.max_tokens', 8000),
                        // Lower than the translation pass: editing is not a
                        // creative task and a warm model rewrites rather than
                        // corrects.
                        'temperature' => (float) config('translation.refine_temperature', 0.1),
                        'json' => config('translation.json_mode', true) && $link->supportsJsonMode(),
                        'effort' => config('translation.effort', 'low'),
                    ],
                );

                $edited = $this->decode((string) ($reply['content'] ?? ''));

                if (! is_array($edited) || $edited === []) {
                    continue;
                }

                $kept = $draft;
                $changed = 0;

                foreach ($edited as $index => $text) {
                    if (! isset($indexed[$index]) || ! is_string($text) || trim($text) === '') {
                        continue;
                    }

                    $text = trim($text);

                    if ($text === $draft[$index]) {
                        continue;
                    }

                    // The guard: an edit that breaks the string is not an edit.
                    if ($this->validator->reject((string) $index, $indexed[$index], $text) !== null) {
                        continue;
                    }

                    $kept[$index] = $text;
                    $changed++;
                }

                if ($changed > 0) {
                    Log::info('translation.refined', [
                        'locale' => $locale,
                        'corrected' => $changed,
                        'of' => count($pairs),
                        'model' => $link->model,
                    ]);
                }

                return $kept;
            } catch (\Throwable $e) {
                // Fall to the next link; the draft stands if none answer.
                continue;
            }
        }

        return $draft;
    }

    /**
     * The editor's instruction — a different job from the translator's.
     */
    private function editor(string $locale, string $context): string
    {
        $target = $this->locales->name($locale);
        $native = $this->locales->native($locale);
        $rtl = $this->locales->dir($locale) === 'rtl';

        $lines = [];

        $lines[] = "You are a native {$target} ({$native}) editor proofreading the user interface of a sports-club platform.";
        $lines[] = '';
        $lines[] = 'Each entry gives you the English source and a proposed '.$target.' translation of it. The translation was produced in bulk and is usually right about MEANING and often wrong about GRAMMAR.';
        $lines[] = '';
        $lines[] = 'What this software is about: '.$context;
        $lines[] = '';
        $lines[] = 'Your job, in this order:';
        /*
         * ⚠️ Deliberately named rather than listed as "grammar". A generic
         * instruction gets generic attention; naming the machinery is what
         * makes a model check it. The list spans language families on purpose —
         * whichever items do not apply to this language cost nothing, and the
         * ones that do are exactly what a bulk pass got wrong.
         */
        $lines[] = '1. Correct the '.$target.' itself — everything a word-by-word rendering misses. Whichever of these '.$target.' has: agreement, case, gender, number, definiteness, verb form and tense, particles, classifiers/counters, honorifics and politeness level, and word order. A UI label must be in the register a '.$target.' product actually uses for that control.';
        $lines[] = '2. Correct spelling and orthography to the standard written norm of '.$target.', in its own writing system — the right script and script mixture, and every diacritic, vowel mark or special character it requires. Never an ASCII approximation, never a character that merely resembles the right one, never a transliteration where the native script is used.';
        $lines[] = '3. Correct typography to '.$target.' convention: its own quotation marks, separators and spacing around punctuation.';
        $lines[] = '4. Replace any coined, transliterated or half-translated word with the ordinary '.$target.' one. If there is no established term, use a plain correct phrase.';
        $lines[] = '5. Only if the translation states something the English does not, or misses something it does, correct the meaning.';
        $lines[] = '';
        $lines[] = 'What you must NOT do:';
        $lines[] = '• Do not retranslate. If a line is already correct '.$target.', return it unchanged.';
        $lines[] = '• Do not restyle, lengthen, shorten, or make it more formal or friendly for its own sake. These are UI labels and their length matters.';
        $lines[] = '• Do not touch `:word` placeholders (:name, :count, :date). Same spelling, same number of them.';
        $lines[] = '• Do not touch plural syntax: `{1}…|[2,*]…` forms and their range labels stay.';
        $lines[] = '• Do not change any number, and do not change HTML tags.';
        $lines[] = '• Do not translate: TAKEONE, Gi, No-Gi, ippon, wazari, kata, kumite, poomsae, dan, kyu, currency codes, WhatsApp, IBAN, CPR, QR, PDF, GPS, BMI, BMR, VAT.';

        if ($rtl) {
            $lines[] = '• '.$target.' is right-to-left. Do not insert directional marks or reorder numbers by hand.';
        }

        $lines[] = '';
        $lines[] = 'Reply with a JSON object and nothing else: the SAME keys you were given, each mapped to the corrected '.$target.' string only (not the pair).';
        $lines[] = 'Return every key. No code fences, no commentary.';

        return implode("\n", $lines);
    }

    /**
     * Only the models that have any business translating a language.
     *
     * ⚠️ THE FAILURE THIS EXISTS TO STOP, and it was not hypothetical.
     *
     * On 2026-09-09 the Anthropic account ran out of credit. Every call to
     * Claude returned HTTP 400 "Your credit balance…", the chain fell through
     * exactly as designed, and `qwen3-coder:30b` — a CODE model — translated
     * 19,666 interface strings across sixty-seven languages. Nothing failed.
     * The command reported success. A native speaker read the result and said
     * the letters were wrong and the grammar was broken, which is how anybody
     * found out. In Albanian it had rendered the GOLD medal as "Medalja e
     * zezë" (the black medal) and SILVER as "Medalja e bardhë" (the white
     * one) — on a competition platform.
     *
     * A fallback is right for RENDERING, where a page must never fail. It is
     * wrong for GENERATION: silently swapping the translator produces
     * confident, plausible, wrong text that is then stored and served for
     * months. Better to translate nothing and say so.
     *
     * A code model is refused by name, because that is the one property that is
     * legible from the outside. This is a floor, not a quality bar — passing it
     * only means the model is not obviously the wrong tool.
     *
     * @param  array<int,Link>  $links
     * @return array<int,Link>
     */
    private function fitToTranslate(array $links): array
    {
        $unfit = (array) config('translation.unfit_models', []);

        $kept = [];

        foreach ($links as $link) {
            $reason = null;

            foreach ($unfit as $pattern) {
                if (@preg_match($pattern, $link->model) === 1) {
                    $reason = $pattern;

                    break;
                }
            }

            if ($reason === null) {
                $kept[] = $link;

                continue;
            }

            Log::warning('translation.model_refused', [
                'model' => $link->model,
                'label' => $link->label,
                'matched' => $reason,
                'why' => 'not a translation model — see InterfaceAgent::fitToTranslate()',
            ]);
        }

        return $kept;
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
                /*
                 * ⚠️ Long enough to keep the sentence that says WHY.
                 *
                 * At 100 characters "Anthropic request failed: HTTP 400
                 * {"type":"error","error":{"type":"invalid_request_error"…"
                 * used the whole budget and cut off "Your credit balance is too
                 * low" — the one phrase the command greps for to tell an
                 * operator to top the account up. The diagnosis was in the
                 * message and the truncation threw it away.
                 */
                $failures[] = $link->describe().': '.mb_substr($e->getMessage(), 0, 300);
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
        /*
         * ⚠️ THE QUALITY BLOCK COMES FIRST, and it is here because a native
         * speaker read the Albanian and said the letters were wrong and the
         * grammar was broken (2026-09-09). The proximate cause was a code model
         * doing the translating — now refused outright, see fitToTranslate() —
         * but the prompt never actually ASKED for correct language either. It
         * asked for the right register, the right length, intact placeholders,
         * and said nothing about spelling, diacritics or agreement.
         *
         * These strings are read by people who cannot check them against the
         * English, in languages nobody on this team speaks. Fluent-and-wrong is
         * the failure mode, so the standard has to be stated, not assumed.
         */
        $lines[] = 'QUALITY — this is the part that matters most:';
        $lines[] = '• Write as an educated native speaker of '.$target.' writing for publication. A reader must not be able to tell it was translated.';
        $lines[] = '• Spelling and orthography must be correct by the standard written norm of '.$target.'. Use the language\'s own alphabet in full, including every diacritic and special letter it requires — never an ASCII approximation, never a letter that merely looks similar. If '.$target.' has more than one accepted orthography, use the standard/literary one.';
        $lines[] = '• Grammar must be correct: agreement, case, gender, number, definiteness, verb form and word order as '.$target.' actually requires them, not as English arranges them.';
        $lines[] = '• Use real words. Never coin a word, transliterate an English one, or leave a half-translated form. If you do not know the established '.$target.' term for something, use a plain, correct, ordinary phrase in '.$target.' rather than inventing one.';
        $lines[] = '• Get the MEANING right before the style. Colours, metals, directions, numbers and states of a thing are facts — gold is the metal gold, not a colour you associate with it.';
        $lines[] = '• Typography follows '.$target.': its own quotation marks, its own decimal and thousands separators, its own spacing around punctuation.';
        $lines[] = '• If a string is ambiguous in English, choose the reading that fits a sports-club interface, and still return correct '.$target.'.';
        $lines[] = '';
        $lines[] = 'Rules:';
        $lines[] = '• Write what a product in '.$target.' would actually put on that control. Match the register and the LENGTH of a UI label — a button says "Save", not "Please save your changes now".';
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
