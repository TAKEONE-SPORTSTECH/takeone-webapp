<?php

namespace App\Translation\Services;

/**
 * How much of a rendered page is still English — counted, not eyeballed.
 *
 * ── Why a machine has to answer this ─────────────────────────────────────────
 *
 * "Mostly translated" is not a state anybody can act on. The acceptance bar
 * agreed for this work is: pick any locale, walk any screen, and there is not
 * one English word that is not a proper noun. A human cannot check that on
 * eight hundred strings across sixty-eight languages, and every attempt to do
 * it by looking produced the same result — the strings somebody happened to
 * notice got fixed, and the page stayed half English.
 *
 * ── How it decides ───────────────────────────────────────────────────────────
 *
 * `lang/en` is the corpus of every string the interface can say. If one of
 * those English values appears VERBATIM in a page rendered in Japanese, exactly
 * one of two things is true: the key has no Japanese entry and Laravel fell
 * back, or somebody wrote the English into a template as a literal. Both are
 * the same defect to the reader, and both are what this counts.
 *
 * Three things are deliberately NOT counted:
 *
 *   · A string the target locale translates to ITSELF. "OK" is "OK" in
 *     Portuguese, "Email" is "Email" in French. Present in both files with the
 *     same value means translated, not missing.
 *   · Proper nouns — the platform's name, sport vocabulary a rule book fixes
 *     (`Gi`, `No-Gi`, `ippon`), currency codes. The allowlist, and it is
 *     deliberately short: every entry is a claim that a word is the same in
 *     sixty-eight languages, and most such claims are wrong.
 *   · Anything under three characters or with no letter in it. "—", "5", "%s"
 *     read the same everywhere and matching on them produces noise that buries
 *     the real answer.
 *
 * ── What it cannot see ───────────────────────────────────────────────────────
 *
 * An English literal that is not in `lang/en` at all — a hardcoded `'Adult'` in
 * a model, a toast written in a script — is invisible to the corpus method,
 * because there is nothing to match it against. `literals()` is the second
 * pass for those: it reads the visible text and flags runs of Latin words on a
 * page whose language does not use the Latin alphabet. Crude on purpose; it is
 * a lead, not a verdict.
 */
class ParityAudit
{
    /**
     * Words that are the same in every language, or are names.
     *
     * ⚠️ Short by design. Adding a word here asserts that no language on earth
     * writes it differently, and the honest length of that list is about this.
     */
    public const ALLOWLIST = [
        'TAKEONE', 'Gi', 'No-Gi', 'BHD', 'Email', 'e-mail', 'OK', 'PDF', 'QR', 'SMS',
        'WhatsApp', 'Instagram', 'Facebook', 'YouTube', 'TikTok', 'X', 'Snapchat',
        'ippon', 'waza-ari', 'kata', 'kumite', 'dan', 'kg', 'cm', 'ID', 'URL',
    ];

    /** Scripts whose languages do not use the Latin alphabet at all. */
    private const NON_LATIN = ['ar', 'fa', 'ur', 'he', 'ku', 'ps', 'hi', 'bn', 'ta', 'te', 'ml', 'mr',
        'pa', 'gu', 'ne', 'si', 'zh', 'zh-TW', 'ja', 'ko', 'th', 'km', 'my', 'ka', 'hy', 'el',
        'ru', 'uk', 'bg', 'sr', 'mn', 'am'];

    public function __construct(private LangFileCatalog $catalog, private ContentLocales $locales) {}

    /**
     * Every English string worth checking for, keyed by a representative key.
     *
     * ⚠️ DEDUPED BY VALUE, not by key, and that is the whole correctness of
     * this class.
     *
     * "Cancel" is the value of `admin.cancel`, `member.cancel`, `copilot.cancel`
     * and four more. Keying by key means a page that renders one translated
     * "Cancel" is reported as leaking six English ones — the count is nonsense,
     * the list is noise, and the real leaks are buried under it. A reader does
     * not see keys; they see a word. So the unit is the WORD, and it counts as
     * translated the moment ANY file in the target locale gives it a different
     * rendering.
     *
     * @return array<string, string> representative key => English value
     */
    public function corpus(string $locale): array
    {
        $allow = array_map('mb_strtolower', self::ALLOWLIST);

        /** @var array<string, string> $candidates  lowercased value => key */
        $candidates = [];
        /** @var array<string, string> $originals   lowercased value => value */
        $originals = [];
        /** @var array<string, bool> $sameInBoth  lowercased value => some file renders it identically */
        $sameInBoth = [];

        foreach ($this->catalog->files($locale) as $id => $file) {
            $english = $this->catalog->strings($file['source']);
            $target = is_file($file['target']) ? $this->catalog->strings($file['target']) : [];

            foreach ($english as $key => $value) {
                if (! is_string($value)) {
                    continue;
                }

                $value = trim($value);

                // Too short, or nothing in it that a language could change.
                if (mb_strlen($value) < 3 || ! preg_match('/\p{L}/u', $value)) {
                    continue;
                }

                if (in_array(mb_strtolower($value), $allow, true)) {
                    continue;
                }

                /*
                 * ⚠️ Not everything in `lang/en` is English.
                 *
                 * `admin.club_details_index_arabic` is the word "العربية" — the
                 * label on the Arabic tab of a bilingual editor, correctly the
                 * same in both files. Matching it against an Arabic page
                 * reported the one string on it that was MOST certainly right
                 * as a fallback. A value with no Latin letter in it cannot be
                 * evidence of English leaking.
                 */
                if (! preg_match('/[A-Za-z]/', $value)) {
                    continue;
                }

                $slot = mb_strtolower($value);

                $candidates[$slot] ??= $id.'.'.$key;
                $originals[$slot] ??= $value;

                /*
                 * ⚠️ The ONLY reason to drop a value: this locale renders it
                 * IDENTICALLY. "OK" is "OK" in Portuguese, "Email" is "Email"
                 * in French — present and correct, not missing.
                 *
                 * It must NOT be dropped merely because some other file
                 * translates the same word. That was the rule until
                 * 2026-09-09 and it under-counted badly: `member.…_championship`
                 * rendering the English "Championship" on a Chinese page was
                 * excused because `events.php` had a Chinese entry for the same
                 * word elsewhere. The reader still sees an English word. Value
                 * DEDUPING (counting it once) is right; value FORGIVING is not.
                 */
                if (isset($target[$key]) && trim((string) $target[$key]) === $value) {
                    $sameInBoth[$slot] = true;
                }
            }
        }

        $out = [];

        foreach ($candidates as $slot => $key) {
            if (! isset($sameInBoth[$slot])) {
                $out[$key] = $originals[$slot];
            }
        }

        return $out;
    }

    /**
     * What this page did with the interface's strings.
     *
     * ⚠️ The denominator is the only interesting decision here. Measuring
     * against the WHOLE corpus — every string the app can say — makes any page
     * look 98% translated, because a poster shows a few hundred of nine
     * thousand strings and the nine thousand it does not show count as
     * successes. That number is worse than no number: it is reassuring and it
     * is measuring nothing.
     *
     * So the denominator is what this page ACTUALLY RENDERED: a string is
     * counted when either its English or its translation appears in the visible
     * text. `resolved + fell_back` is therefore the population, and the
     * percentage means what a reader would say it means.
     *
     * @return array{rendered: int, resolved: int, fell_back: int, leaked: array<string, string>}
     */
    public function report(string $html, string $locale, array $names = []): array
    {
        $text = $this->withoutNames($this->visibleText($html), $names);
        $leaked = $this->fallbacks($html, $locale, $text);
        $resolved = 0;

        // Deduped by value, for the same reason `corpus()` is: one word the
        // reader sees is one string, however many keys happen to spell it.
        $seen = [];

        foreach ($this->catalog->files($locale) as $file) {
            if (! is_file($file['target'])) {
                continue;
            }

            foreach ($this->catalog->strings($file['target']) as $value) {
                if (! is_string($value)) {
                    continue;
                }

                $value = trim($value);
                $slot = mb_strtolower($value);

                if (isset($seen[$slot])) {
                    continue;
                }

                $needle = $this->longestLiteralRun($value);

                if ($needle !== null && str_contains($text, $needle)) {
                    $seen[$slot] = true;
                    $resolved++;
                }
            }
        }

        return [
            'rendered' => $resolved + count($leaked),
            'resolved' => $resolved,
            'fell_back' => count($leaked),
            'leaked' => $leaked,
        ];
    }

    /**
     * The English strings that actually reached this page.
     *
     * @return array<string, string> lang key => the English that leaked
     */
    public function fallbacks(string $html, string $locale, ?string $text = null, array $names = []): array
    {
        $text ??= $this->withoutNames($this->visibleText($html), $names);
        $out = [];

        foreach ($this->corpus($locale) as $key => $english) {
            /*
             * A placeholder string can never match verbatim — ":count entries"
             * is rendered as "12 entries" — so it is compared on its longest
             * literal run instead. Without this, every counted, dated or named
             * sentence on the platform is invisible to the audit, and those are
             * most of the ones a reader notices.
             */
            $needle = $this->longestLiteralRun($english);

            if ($needle === null) {
                continue;
            }

            if (str_contains($text, $needle)) {
                $out[$key] = $english;
            }
        }

        return $out;
    }

    /**
     * Runs of Latin words on a page whose language is not written in Latin.
     *
     * The second pass, for English that never went through `__()` and so is not
     * in the corpus. A lead to investigate, not a verdict: brand names, URLs and
     * sport vocabulary land here too.
     *
     * @return array<int, string>
     */
    public function literals(string $html, string $locale): array
    {
        if (! in_array($locale, self::NON_LATIN, true)) {
            return [];   // can't tell English from French by its alphabet
        }

        preg_match_all('/\b[A-Za-z][A-Za-z\'’\-]{2,}(?:\s+[A-Za-z][A-Za-z\'’\-]{1,}){0,6}\b/u',
            $this->visibleText($html), $m);

        $allow = array_map('mb_strtolower', self::ALLOWLIST);

        return array_values(array_unique(array_filter(
            array_map('trim', $m[0] ?? []),
            fn ($s) => mb_strlen($s) >= 3 && ! in_array(mb_strtolower($s), $allow, true),
        )));
    }

    /**
     * Take the proper nouns out of the text before looking for English in it.
     *
     * ⚠️ Without this the audit accuses the translator of the one thing it did
     * RIGHT. A club called "Victory BJJ Academy" running a "Victory BJJ
     * Championship" puts the words "Victory" and "Championship" on a Chinese
     * poster — correctly, because a name that gets translated sends a
     * competitor to the wrong building, and refusing to translate names is a
     * deliberate rule of App\Translation. But `challenge.personal_duel_show_
     * victory` is the English string "Victory", and a naive substring match
     * cannot tell the two apart.
     *
     * So the names come out first. The caller supplies them from the same place
     * the translator gets its own `keep` list — the record's own words — which
     * is the only source that actually knows what is a name on THIS page.
     *
     * Longest first, so "Victory BJJ Championship" is removed before "Victory"
     * can match half of it.
     *
     * @param  array<int, string>  $names
     */
    public function withoutNames(string $text, array $names): string
    {
        /*
         * The names of LANGUAGES are proper nouns too, and every page carries
         * the language picker. It lists each language twice — "简体中文" and,
         * beneath it, "Chinese (Simplified)" — because a reader scanning sixty
         * eight rows needs a script they can read to find their own. So
         * "English" appears on a Chinese poster on purpose, and matching it
         * against `admin.club_details_index_english` accused the picker of the
         * thing it exists to do.
         */
        foreach ($this->locales->all() as $meta) {
            $names[] = (string) ($meta['name'] ?? '');
            $names[] = (string) ($meta['native'] ?? '');
        }

        $names = array_filter(array_map('trim', $names), fn ($n) => mb_strlen($n) >= 3);

        usort($names, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        foreach ($names as $name) {
            $text = str_replace($name, ' ', $text);
        }

        return $text;
    }

    /**
     * What a reader actually SEES — text plus the attributes that are read out.
     *
     * `aria-label`, `title`, `placeholder` and `alt` are counted because a
     * screen reader speaks them and a pointer shows them; an untranslated one
     * is exactly as broken as an untranslated heading, and they are the ones
     * that get missed.
     */
    public function visibleText(string $html): string
    {
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', ' ', $html) ?? $html;
        $html = preg_replace('#<style\b[^>]*>.*?</style>#is', ' ', $html) ?? $html;
        $html = preg_replace('#<!--.*?-->#s', ' ', $html) ?? $html;

        $spoken = '';

        /*
         * ⚠️ The lookbehind is load-bearing: STATIC attributes only.
         *
         * `\b` matches straight after a colon, so the first version of this
         * also harvested Alpine BINDINGS — `:placeholder="tab === 'athletes' ?
         * … : …"` — and reported the English identifier `athletes` inside that
         * expression as a leaked string, on a page that was fully translated
         * in both Arabic and Chinese. An attribute whose value is code is not
         * something a reader sees.
         */
        if (preg_match_all('/(?<![:\w-])(?:aria-label|title|placeholder|alt|value)="([^"]*)"/i', $html, $m)) {
            $spoken = ' '.implode(' ', $m[1]);
        }

        /*
         * ⚠️ Alpine attributes are NOT harvested, and that is deliberate.
         *
         * It was tried on 2026-09-09 and reverted the same hour: `x-data` and
         * `data-*` hold executable code as well as data, so
         * `x-data="publicGallery([], false)"` matched the lang value "Gallery"
         * and the audit reported a false leak on a page that was fully
         * translated. Alpine identifiers are English by nature and always will
         * be.
         *
         * Text that only ever reaches the screen through JS is therefore a
         * blind spot of the corpus method. `literals()` is the second pass for
         * it, and the real answer is that a string a reader sees should come
         * from `__()` in the markup, not from a payload.
         */
        $text = html_entity_decode(strip_tags($html).$spoken, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * The longest run of a message that survives placeholder substitution.
     *
     * ":count of :total entered" → " of " is useless, "entered" is the answer.
     * Returns null when nothing long enough survives, which is the honest
     * outcome for a string that is almost entirely placeholder.
     */
    private function longestLiteralRun(string $message): ?string
    {
        $parts = preg_split('/:\w+|\{[^}]*\}|%s|%d/u', $message) ?: [];

        $best = '';

        foreach ($parts as $part) {
            $part = trim($part, " \t\n\r\0\x0B.,;:!?—–-");

            if (mb_strlen($part) > mb_strlen($best)) {
                $best = $part;
            }
        }

        return (mb_strlen($best) >= 6 && preg_match('/\p{L}/u', $best)) ? $best : null;
    }
}
