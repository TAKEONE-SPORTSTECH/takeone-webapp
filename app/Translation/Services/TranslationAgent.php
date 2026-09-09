<?php

namespace App\Translation\Services;

use Illuminate\Support\Facades\Log;

/**
 * The one place that asks a model to write an event in another language.
 *
 * ── Why a whole document in one call ─────────────────────────────────────────
 *
 * Field-at-a-time translation is cheaper per call and worse at every one of
 * them. "Open" alone is ambiguous; "Open" beside "Adult · Brown & Black belt ·
 * Absolute" is a division. A title translated without the description drifts
 * from the wording the description uses for the same thing. So the agent is
 * handed the whole event and asked to write the whole event — the sentences
 * come out consistent with each other because they were written together.
 *
 * ── Why "rewrite", not "translate" ───────────────────────────────────────────
 *
 * The instruction the user asked for, and the one that makes the difference on
 * a public poster: produce what a native writer would have written to say this,
 * not what these words look like in that language. Arabic and English put
 * emphasis in different places; German compounds what English strings out;
 * Japanese omits the subject English insists on. A sentence carried across
 * word-for-word is grammatical and instantly foreign, and on a page whose job
 * is to convince a stranger to enter a competition, foreign reads as careless.
 *
 * ── The three things it must not do ──────────────────────────────────────────
 *
 *  1. Never translate a NAME. Not the club, not the venue, not a person, not
 *     the sport's own vocabulary. A competitor who cannot find "Victory BJJ
 *     Academy" on a map because it became something else in their language has
 *     been actively harmed by this feature.
 *  2. Never change a NUMBER — a date, a time, a price, a weight, an age, a
 *     currency code. It may translate the words around them.
 *  3. Never add, remove, soften or improve a FACT. A translator is not an
 *     editor, and an event's rules are not prose to be tightened.
 *
 * ── Which model ──────────────────────────────────────────────────────────────
 *
 * Whichever one answers. The agent asks a CHAIN (ProviderChain) rather than a
 * provider, so the platform is not tied to any single AI company: Claude, a
 * local Ollama, an LM Studio or vLLM server on the same network, Groq,
 * OpenRouter — configured under Admin → AI Providers, tried in order, first
 * usable answer wins. A dead key costs a plainer sentence, never the feature.
 *
 * ── Trust ────────────────────────────────────────────────────────────────────
 *
 * The output is untrusted text from a third party and is treated as such:
 * parsed as JSON, key-matched against what was sent, type-checked, length-
 * capped and control-stripped before a single character is stored. It is then
 * rendered through Blade's escaping like any other user content — never with
 * innerHTML, never into an href.
 */
class TranslationAgent
{
    /** Hard ceilings, so one absurd record cannot become an absurd bill. */
    private const MAX_FIELDS = 120;

    private const MAX_CHARS_PER_FIELD = 6000;

    private const MAX_TOTAL_CHARS = 40000;

    /**
     * A translation that is wildly longer than its source is a model that has
     * started explaining rather than translating. Generous, because some
     * languages genuinely run long (Arabic → German), but not unbounded.
     */
    private const MAX_EXPANSION = 8;

    /**
     * Above this length a field is prose, and its numbers are allowed to be
     * rephrased. Below it, they are structure and must survive verbatim.
     */
    private const NUMERIC_CHECK_LIMIT = 200;

    public function __construct(private ProviderChain $chain, private ContentLocales $locales) {}

    /**
     * Translate a document. Returns only the fields that came back usable —
     * a partial answer is kept, because five good fields beat none.
     *
     * @param  array<string, string>  $document  field => source text
     * @param  array{summary?: string, keep?: array<int,string>}  $context
     * @return array{values: array<string,string>, model: string, provider: string}
     *
     * @throws \RuntimeException when the provider is unreachable or the reply is unusable
     */
    public function translate(array $document, string $from, string $to, array $context = []): array
    {
        $document = $this->trim($document);

        if ($document === []) {
            return ['values' => [], 'model' => '', 'provider' => ''];
        }

        $messages = [
            ['role' => 'system', 'content' => $this->system($from, $to, $context)],
            ['role' => 'user', 'content' => $this->user($document)],
        ];

        $links = $this->chain->links();

        if ($links === []) {
            throw new \RuntimeException('No AI provider is configured. Add one under Admin → AI Providers.');
        }

        /*
         * Down the chain until one answers.
         *
         * This is the platform's independence from any single AI company, in
         * one loop: a dead key, an expired card, a rate limit or a lab having
         * an outage moves to the next model rather than failing the language.
         * See ProviderChain for how the order is decided, and
         * config/translation.php for how to pin it.
         *
         * Only a hard failure moves on — an exception, or a reply with nothing
         * usable in it. A PARTIAL answer is accepted from the first model that
         * gives one: falling through on a partial would pay two providers for
         * one translation every time a single field was refused, and a refusal
         * is usually the source text's doing, not the model's.
         */
        $failures = [];

        foreach ($links as $link) {
            try {
                $result = $this->attempt($link, $document, $from, $to, $context);

                if ($failures !== []) {
                    /*
                     * Somebody upstream failed and this one covered for it.
                     * Worth a line: a chain quietly always falling through to
                     * the free local model is a paid provider nobody has
                     * noticed is broken, and a drop in quality nobody ordered.
                     */
                    Log::warning('translation.fell_through', [
                        'answered' => $link->describe(),
                        'after' => $failures,
                    ]);
                }

                return $result;
            } catch (\Throwable $e) {
                $failures[] = $link->describe().': '.mb_substr($e->getMessage(), 0, 120);
            }
        }

        throw new \RuntimeException('Every configured translator failed. '.implode(' | ', $failures));
    }

    /**
     * Translate with ONE named model.
     *
     * Public for `translate:compare`, which measures each model on its own
     * rather than on the chain's first success — the whole point of a
     * comparison being to see what each one actually produces on your content
     * before you trust it with the page.
     *
     * @param  array<string, string>  $document
     * @return array{values: array<string,string>, model: string, provider: string}
     */
    public function translateWith(Link $link, array $document, string $from, string $to, array $context = []): array
    {
        return $this->attempt($link, $this->trim($document), $from, $to, $context);
    }

    /**
     * One model, one attempt. Throws when the reply cannot be used at all,
     * which is what lets the chain move on.
     *
     * @param  array<string, string>  $document
     * @return array{values: array<string,string>, model: string, provider: string}
     */
    private function attempt(Link $link, array $document, string $from, string $to, array $context): array
    {
        $reply = $link->driver->chat(
            [
                ['role' => 'system', 'content' => $this->system($from, $to, $context)],
                ['role' => 'user', 'content' => $this->user($document)],
            ],
            [],
            [
                // Room for the answer plus the script expansion of a language
                // like Thai or Amharic, which cost far more tokens per word
                // than the Latin alphabet.
                'max_tokens' => (int) config('translation.max_tokens', 8000),
                'temperature' => (float) config('translation.temperature', 0.3),
                /*
                 * Constrained decoding where the driver has it. Decisive for a
                 * self-hosted model, which will otherwise wrap its JSON in a
                 * helpful sentence; ignored by the drivers that are asked in
                 * the prompt instead.
                 */
                'json' => config('translation.json_mode', true) && $link->supportsJsonMode(),
                /*
                 * Translation is not a reasoning problem. The document arrives
                 * whole, every key is given, and the job is to write the same
                 * facts in another language — the kind of task the model's own
                 * guidance calls "simple". Left unsaid, Claude reasons about it
                 * at HIGH effort and spends ~25s per language on deliberation
                 * nobody reads.
                 */
                'effort' => config('translation.effort', 'low'),
            ],
        );

        $values = $this->parse((string) ($reply['content'] ?? ''), $document);

        if ($values === []) {
            throw new \RuntimeException('returned nothing usable');
        }

        return [
            'values' => $values,
            // Which model wrote it, stored on every row. The only way to answer
            // "why does the Portuguese read badly" without guessing, and to
            // find what an older model produced after a provider swap.
            'provider' => $link->driverName,
            'model' => $link->model,
        ];
    }

    /**
     * The instruction. Written as prose rather than a list of rules because the
     * job it describes is a judgement, and a model given a checklist translates
     * like a checklist.
     */
    private function system(string $from, string $to, array $context): string
    {
        $source = $this->locales->name($from);
        $target = $this->locales->name($to);
        $native = $this->locales->native($to);
        $rtl = $this->locales->dir($to) === 'rtl';

        $keep = array_values(array_filter(array_map(
            fn ($v) => is_string($v) ? trim($v) : '',
            (array) ($context['keep'] ?? [])
        )));

        $lines = [];

        $lines[] = "You are a professional translator and copywriter working from {$source} into {$target} ({$native}).";
        $lines[] = '';
        $lines[] = "You are localising the page for a real sports competition. Your job is NOT word-for-word translation. Write what a native {$target} writer would have written to say the same thing to the same reader: restructure a sentence, split or join clauses, choose the idiom {$target} actually uses, and follow {$target} grammar, punctuation, capitalisation and number formatting conventions. The result must read as though it was written in {$target} first — never as though it was translated.";
        $lines[] = '';
        $lines[] = 'Hold to these, without exception:';
        $lines[] = '• Never add, drop, soften or "improve" a fact. You are translating an announcement, not editing it. If the source is blunt, be blunt.';
        $lines[] = '• Never alter numbers, dates, times, weights, ages, prices or currency codes. Translate the words around them and reformat them the way '.$target.' writes them, but the values themselves do not change.';
        $lines[] = '• A symbol attached to a number is PART of that number. A leading or trailing +, -, <, >, ≤, ≥ marks a bound: "60-" is under 60kg and "60+" is over 60kg, and they are different divisions that different athletes enter. Never drop, move, normalise or tidy one, however untidy the spacing around it looks.';
        $lines[] = '• Never translate a proper name: clubs, venues, people, federations, brands, event names that are names. Leave them exactly as written, in their original script.';
        $lines[] = '• Keep the sport\'s own vocabulary in the form that sport uses in '.$target.'. Terms like ippon, gi, no-gi, kata, kumite, guard, sweep, and belt ranks are the language of the sport and are usually left as they are; translate one only if '.$target.' speakers of that sport genuinely use a translated word.';
        $lines[] = '• Match the register of the source: a title stays a title (short, no full stop), a bullet stays a bullet, a warning stays a warning.';
        $lines[] = '• Keep any line breaks and list structure the source has.';
        $lines[] = '• The source may not be entirely in '.$source.'. Organisers mix languages — an English title above Arabic fee lines is normal here. Translate every value into '.$target.' whatever language you find it in.';

        if ($rtl) {
            $lines[] = '• '.$target.' is written right-to-left. Write natural right-to-left text and do not insert directional marks or reorder numbers by hand — the page handles direction itself.';
        }

        if (! empty($context['summary'])) {
            $lines[] = '';
            $lines[] = 'What this page is: '.trim((string) $context['summary']);
        }

        if ($keep !== []) {
            $lines[] = '';
            $lines[] = 'These are names. Reproduce each one character-for-character wherever it appears, and never translate or transliterate it:';
            foreach (array_slice($keep, 0, 40) as $name) {
                $lines[] = '  - '.mb_substr($name, 0, 120);
            }
        }

        $lines[] = '';
        $lines[] = 'You will be given a JSON object of field name → text.';
        $lines[] = 'Reply with a JSON object and nothing else: the same keys, in the same order, each mapped to your '.$target.' version of that value.';
        $lines[] = 'Do not add keys, drop keys, rename keys, nest objects, wrap the JSON in code fences, or write any commentary before or after it.';
        $lines[] = 'If a value is a name, a code or a number and has nothing to translate, return it unchanged.';

        return implode("\n", $lines);
    }

    private function user(array $document): string
    {
        return json_encode($document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    /**
     * Turn whatever came back into a clean field => string map, keeping only
     * what is recognisably an answer to what was asked.
     *
     * @param  array<string,string>  $document
     * @return array<string,string>
     */
    private function parse(string $raw, array $document): array
    {
        $decoded = $this->decode($raw);

        if (! is_array($decoded)) {
            return [];
        }

        $out = [];

        foreach ($decoded as $field => $value) {
            // Key-matched against what was SENT. A key we did not ask about is
            // either a hallucination or an injection attempt; either way it has
            // nowhere to be stored and is dropped.
            if (! is_string($field) || ! isset($document[$field])) {
                continue;
            }

            if (! is_string($value)) {
                continue;
            }

            $value = $this->clean($value);

            if ($value === '') {
                continue;
            }

            // A "translation" identical to the source is usually the model
            // giving up on a name or a code — which is correct, and worth
            // storing so we do not ask again.
            if (mb_strlen($value) > max(80, mb_strlen($document[$field]) * self::MAX_EXPANSION)) {
                continue;
            }

            // The numbers must survive. See numbersSurvived().
            if (! $this->numbersSurvived($document[$field], $value)) {
                continue;
            }

            $out[$field] = $value;
        }

        return $out;
    }


    /**
     * Did every number come through unchanged?
     *
     * ⚠️ The prompt asks for this and a model still gets it wrong. Observed on
     * the first real run: the division "-Group (A) No-Gi 60" — under 60kg —
     * came back as "Groupe A (No-Gi) 60", the leading minus read as a bullet
     * and dropped. Under-60 and over-60 are different divisions that different
     * athletes enter, and the page would have looked perfectly fluent while
     * telling a competitor to enter the wrong one.
     *
     * So it is checked rather than asked for. A field whose numbers changed is
     * REFUSED and left in the source language — visibly untranslated beats
     * confidently wrong, every time.
     *
     * Applied to SHORT fields only. In a division, a fee line or a title, a
     * number is structural. In a paragraph it is prose: a language may spell
     * "twenty" out, write a date as words, or merge "60 kg and 70 kg" into a
     * range, and refusing those would reject good translations of exactly the
     * text this feature exists to translate.
     */
    private function numbersSurvived(string $source, string $translated): bool
    {
        if (mb_strlen($source) > self::NUMERIC_CHECK_LIMIT) {
            return true;
        }

        return $this->fingerprint($source) === $this->fingerprint($translated);
    }

    /**
     * The numeric fingerprint of a string: its numbers, plus the bound symbols
     * that qualify one.
     *
     * The bound symbols are counted SEPARATELY from the numbers, and that is
     * the whole trick. The real-world failure looked like this:
     *
     *     source        "-Group ( A) No-Gi 60"      ← under 60kg
     *     translation   "Groupe A (No-Gi) 60"       ← no bound at all
     *
     * A check that only looked at digits saw "60" on both sides and passed it.
     * The minus is nowhere near the 60 in the source — an organiser typed it in
     * front of the word — so adjacency cannot be required.
     *
     * A symbol counts when it is attached to a digit, or sits at either END of
     * the string. That deliberately ignores the hyphen inside "No-Gi" and the
     * dash in "Adult - Brown belt", which are punctuation and may legitimately
     * become an em dash, a comma or nothing at all in another language.
     *
     * Digits are normalised first: Arabic-Indic (٦٠) and Persian (۶۰) write the
     * same value in another script, and a correct Arabic translation must not
     * be thrown away for doing so.
     *
     * @return array{numbers: array<int,string>, bounds: array<int,string>}
     */
    private function fingerprint(string $text): array
    {
        $ascii = strtr($text, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);

        // − (U+2212) and ‑ (U+2011) are a minus sign to a reader; treat them so.
        $ascii = strtr($ascii, ['−' => '-', '‑' => '-', '＋' => '+']);

        // "1,000" and "1 000" are one number written two ways.
        $ascii = preg_replace('/(?<=\d)[,\x{00A0}\x{202F} ](?=\d{3}\b)/u', '', $ascii) ?? $ascii;

        preg_match_all('/\d+(?:\.\d+)?/u', $ascii, $m);
        $numbers = $m[0];
        sort($numbers);

        $bounds = [];
        $trimmed = trim($ascii);
        $chars = preg_split('//u', $trimmed, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $last = count($chars) - 1;

        foreach ($chars as $i => $char) {
            if (! in_array($char, ['+', '-', '<', '>', '≤', '≥'], true)) {
                continue;
            }

            $before = $chars[$i - 1] ?? '';
            $after = $chars[$i + 1] ?? '';

            $touchesDigit = preg_match('/\d/u', $before) || preg_match('/\d/u', $after);
            $atEdge = $i === 0 || $i === $last;

            // A space between the symbol and its number is formatting: "60 +"
            // is the same division as "60+".
            if (! $touchesDigit && ! $atEdge) {
                $near = ($before === ' ' && preg_match('/\d/u', $chars[$i - 2] ?? ''))
                    || ($after === ' ' && preg_match('/\d/u', $chars[$i + 2] ?? ''));

                if (! $near) {
                    continue;
                }
            }

            $bounds[] = $char;
        }

        sort($bounds);

        return ['numbers' => $numbers, 'bounds' => $bounds];
    }

    /**
     * JSON out of a reply that may be wrapped in prose or a code fence despite
     * being told not to. Two attempts, then give up — never a regex that
     * "repairs" JSON, which is how malformed input becomes stored data.
     */
    private function decode(string $raw): mixed
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        $direct = json_decode($raw, true);

        if (is_array($direct)) {
            return $direct;
        }

        // ```json … ``` or a sentence either side of the object.
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        return json_decode(substr($raw, $start, $end - $start + 1), true);
    }

    /**
     * Strip what has no business in a stored string: control characters (a
     * model echoing a stray   would corrupt output), and the zero-width
     * and bidi-override characters that can make rendered text read differently
     * from the text that was stored.
     */
    private function clean(string $value): string
    {
        $value = preg_replace('/[\x{202A}-\x{202E}\x{2066}-\x{2069}\x{200B}\x{FEFF}]/u', '', $value) ?? '';
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';

        return trim(mb_substr($value, 0, self::MAX_CHARS_PER_FIELD));
    }

    /**
     * Cap what is sent. A record with a thousand fields, or one field holding a
     * pasted PDF, is a cost incident waiting to happen; the overflow simply
     * stays in the source language, which is a visible, recoverable outcome.
     *
     * @param  array<string,string>  $document
     * @return array<string,string>
     */
    private function trim(array $document): array
    {
        $out = [];
        $total = 0;

        foreach ($document as $field => $text) {
            if (count($out) >= self::MAX_FIELDS || $total >= self::MAX_TOTAL_CHARS) {
                break;
            }

            $text = mb_substr((string) $text, 0, self::MAX_CHARS_PER_FIELD);
            $total += mb_strlen($text);
            $out[(string) $field] = $text;
        }

        return $out;
    }
}
