<?php

namespace App\Translation\Services;

/**
 * The gate a generated language file has to get through.
 *
 * ⚠️ THE STAKES ARE HIGHER HERE THAN FOR CONTENT. A badly translated event
 * description is an embarrassment on one page. A badly translated LANG FILE is
 * a fatal error on EVERY page for that locale — a dropped `:name` placeholder
 * renders as literal ":name" in front of every reader, a mangled plural picks
 * the wrong branch forever, and a file that does not parse takes the whole
 * locale down.
 *
 * So the posture is the one `TranslationAgent::numbersSurvived()` already
 * takes, applied harder: ask in the prompt, then CHECK, and refuse the string
 * if the check fails. A refused key falls back to English, which is the
 * outcome we have today and therefore cannot be a regression.
 *
 * Acceptance test for the validator itself, and it costs nothing: run it over
 * the hand-written `lang/ar/`. It must pass. A validator that rejects real
 * Arabic written by a person is a validator that will reject good Portuguese.
 */
class LangFileValidator
{
    /**
     * Check one translated string against its English source.
     *
     * @return string|null  the reason to refuse it, or null if it is good
     */
    public function reject(string $key, string $source, string $translated): ?string
    {
        $translated = trim($translated);

        if ($translated === '') {
            return 'empty';
        }

        $isPlural = str_contains($source, '|');

        /*
         * PLACEHOLDERS. `:name` is substituted by Laravel at render time;
         * translate or drop one and the substitution silently stops happening,
         * leaving ":name" on the screen.
         *
         * ⚠️ Asymmetric on purpose, and the hand-written Arabic is why. A
         * placeholder may never be INVENTED — that substitutes nothing. But in
         * a plural it may legitimately be DROPPED: Arabic's dual form is
         * "لاعبان" ("two athletes"), one word, no numeral, and demanding
         * `:count` there would reject the correct translation and keep the
         * wrong one. Outside a plural, dropping is still a defect.
         */
        $sourceHolders = $this->placeholders($source);
        $targetHolders = $this->placeholders($translated);

        if (array_diff($targetHolders, $sourceHolders) !== []) {
            return 'invented a placeholder';
        }

        if (! $isPlural && array_diff($sourceHolders, $targetHolders) !== []) {
            return 'dropped a placeholder';
        }

        /*
         * PLURALS. English has two forms; Arabic has six, Polish three, Welsh
         * four. A CORRECT translation of a two-segment English string routinely
         * has four segments with different range labels — the hand-written
         * `errors.429_retry` goes from 2 to 4 — so comparing counts or labels
         * would reject exactly the work we want.
         *
         * What must hold is that the result is still well-formed: every
         * segment carries a label Laravel's MessageSelector can read, and no
         * segment is empty. An unlabelled multi-segment string is the real
         * hazard — the selector then picks by INDEX using the target locale's
         * plural rules and silently falls back to segment 0 when the index
         * overruns, which is wrong forever and invisible.
         */
        if ($isPlural) {
            $segments = explode('|', $translated);

            if (count($segments) < 2) {
                return 'plural collapsed to one form';
            }

            foreach ($segments as $segment) {
                if (trim($segment) === '') {
                    return 'empty plural segment';
                }
            }

            $sourceLabelled = $this->pluralLabels($source) !== [];

            if ($sourceLabelled && count($this->pluralLabels($translated)) !== count($segments)) {
                return 'plural segment without a range label';
            }
        }

        /*
         * HTML. Only a handful of strings carry any, but a dropped closing tag
         * is a broken page rather than a bad sentence.
         */
        if ($this->tags($source) !== $this->tags($translated)) {
            return 'html tags changed';
        }

        /*
         * NUMBERS — but only when the translation KEEPS numerals.
         *
         * "Duration must be at least 1 month" is correctly Arabic'd as
         * "شهرًا واحدًا" — the numeral becomes a word, and the same is true of
         * "3rd Dan" and "1v1". Refusing that would reject good work. What is
         * never acceptable is a numeral that comes back as a DIFFERENT
         * numeral: 1 month must not become 3.
         *
         * So: no digits in the translation is fine; digits that disagree with
         * the source is not. Note this is looser than the content agent's
         * check, deliberately — there, a number in a division label is
         * structure and a dropped bound sends an athlete to the wrong mat.
         * Here every string is chrome.
         */
        $sourceDigits = $this->digits($source);
        $targetDigits = $this->digits($translated);

        /*
         * ⚠️ The other direction: a WORD becoming a NUMERAL.
         *
         * The rule above allows "1 month" → "شهرًا واحدًا" (numeral to word) but
         * refused "One per person" → "1人につき1つ" (word to numeral) — and that
         * is how most CJK, and plenty of other languages, correctly write a
         * small number. It refused THIRTY-EIGHT Japanese strings in one run,
         * every single one of them containing a spelled-out number: "One per
         * person", "the six characters", "Put two people on a mat", "at least
         * two options", "Two or three points". All were good translations, and
         * all stayed English (2026-09-09).
         *
         * The guard's real job is narrow: a numeral must not come back as a
         * DIFFERENT numeral, because that is a price or a count silently
         * changed. That job only exists when the source HAS a numeral.
         *
         * So when the source has no digits, digits in the translation are
         * accepted — but only if the source actually spells a number out. A
         * source with no number at all whose translation grows one is still an
         * invention, and still refused. The source is always English, so the
         * word list is a fixed, safe test.
         */
        $wordBecameNumeral = $sourceDigits === []
            && $targetDigits !== []
            && $this->spellsANumber($source);

        // ⚠️ Skip only the NUMBER rule. An early `return null` here would also
        // skip the length and control-character checks below, which have
        // nothing to do with numbers and are what stop a model that has started
        // explaining rather than translating.
        if (! $wordBecameNumeral && $targetDigits !== [] && $sourceDigits !== $targetDigits) {
            return 'numbers changed';
        }

        /*
         * LENGTH. Only an absolute sanity cap, to catch a model that has begun
         * explaining rather than translating.
         *
         * The tight per-label ceiling that seemed obvious was removed after the
         * hand-written Arabic failed it: "VAT" is "ضريبة القيمة المضافة", seven
         * times longer and exactly right, and "BMR (cal)" is "معدل الأيض
         * الأساسي (سعرة)". Acronyms expand in every language that does not
         * share the acronym. A layout that cannot take that is a layout bug,
         * not a translation bug, and it is not this class's business.
         */
        if (mb_strlen($translated) > 120 && mb_strlen($translated) > mb_strlen($source) * 10) {
            return 'far longer than the original';
        }

        // Control and bidi-override characters have no business in a label.
        if (preg_match('/[\x{202A}-\x{202E}\x{2066}-\x{2069}\x{200B}\x{FEFF}\x00-\x08\x0B\x0C\x0E-\x1F]/u', $translated)) {
            return 'contains control characters';
        }

        return null;
    }

    /**
     * Check a whole generated file against its English source.
     *
     * @param  array<string,string>  $source
     * @param  array<string,string>  $translated
     * @return array{ok: bool, problems: array<string,string>, missing: array<int,string>, extra: array<int,string>}
     */
    public function file(array $source, array $translated): array
    {
        $problems = [];

        foreach ($translated as $key => $value) {
            if (! isset($source[$key])) {
                continue;   // reported as `extra` below
            }

            if ($reason = $this->reject($key, $source[$key], $value)) {
                $problems[$key] = $reason;
            }
        }

        return [
            'ok' => $problems === [] && array_diff(array_keys($translated), array_keys($source)) === [],
            'problems' => $problems,
            // Missing is NOT a failure: Laravel falls back per key, so a
            // partial file renders with English in the gaps. It is reported so
            // a person can see how complete a language is.
            'missing' => array_values(array_diff(array_keys($source), array_keys($translated))),
            'extra' => array_values(array_diff(array_keys($translated), array_keys($source))),
        ];
    }

    /** @return array<int,string> */
    private function placeholders(string $text): array
    {
        /*
         * ⚠️ `(?<![A-Za-z0-9_])` — the colon must not follow a word character.
         *
         * Without it, "Bout duration (mm:ss)" reads `:ss` as a placeholder, and
         * the correct Arabic "(دد:ثث)" is rejected for "dropping" it. A time
         * format is not a placeholder, and neither is a ratio or a URL scheme.
         * Found by running this validator over the hand-written lang/ar.
         */
        preg_match_all('/(?<![A-Za-z0-9_]):[a-zA-Z_][a-zA-Z0-9_]*/', $text, $m);

        $out = array_values(array_unique($m[0]));
        sort($out);

        return $out;
    }

    /** @return array<int,string> */
    private function pluralLabels(string $text): array
    {
        preg_match_all('/(\{\s*\d+\s*\}|\[\s*\d+\s*,\s*(?:\d+|\*)\s*\])/', $text, $m);

        return array_map(fn ($l) => preg_replace('/\s+/', '', $l), $m[0]);
    }

    /** @return array<int,string> */
    private function tags(string $text): array
    {
        preg_match_all('/<\/?[a-zA-Z][a-zA-Z0-9]*/', $text, $m);

        return array_map('strtolower', $m[0]);
    }

    /**
     * Every number, with Arabic-Indic and Persian digits folded to ASCII so a
     * correct Arabic label that writes 60 as ٦٠ is not thrown away for it.
     *
     * @return array<int,string>
     */
    /**
     * Does this English source write a number out in words?
     *
     * Only cardinals and low ordinals, and only as whole words — "one" must not
     * match "money" or "someone". Deliberately short: this decides whether a
     * translation is ALLOWED to contain a numeral, and a longer list is a wider
     * hole.
     */
    private function spellsANumber(string $text): bool
    {
        $words = 'one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve'
            .'|dozen|half|single|double|triple'
            .'|first|second|third|fourth|fifth|sixth|seventh|eighth|ninth|tenth';

        return (bool) preg_match('/\b(?:'.$words.')\b/i', $text);
    }

    private function digits(string $text): array
    {
        $ascii = strtr($text, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);

        // A placeholder's own digits are not the string's numbers.
        $ascii = preg_replace('/:[a-zA-Z_][a-zA-Z0-9_]*/', '', $ascii) ?? $ascii;
        $ascii = preg_replace('/(\{\s*\d+\s*\}|\[\s*\d+\s*,\s*(?:\d+|\*)\s*\])/', '', $ascii) ?? $ascii;

        preg_match_all('/\d+/', $ascii, $m);
        sort($m[0]);

        return $m[0];
    }
}
