<?php

namespace App\Translation\Services;

/**
 * What alphabet a piece of text is written in — and, where the alphabet gives
 * it away, what language.
 *
 * ⚠️ WHY THIS IS NEEDED. An event has ONE `source_locale`, and real events are
 * not written in one language. Event 65 on stage has an English title, an
 * Arabic description and Arabic fee labels, and the column says "English". So
 * an English reader was shown the Arabic paragraph raw — the platform believed
 * it was already looking at English.
 *
 * Detection therefore has to be PER FIELD. This is the cheap half of that: one
 * pass of regex over a few hundred characters, no API call, no latency, and
 * deterministic — which matters because it runs on the write path of a page
 * that must never block.
 *
 * ── What it can and cannot do ────────────────────────────────────────────────
 *
 * Script pins the language outright for about fifteen of the served locales:
 * Greek, Hebrew, Korean, Thai, Georgian, Armenian, Bengali, Tamil, Telugu,
 * Malayalam, Gujarati, Punjabi, Sinhala, Khmer, Burmese, Amharic. Arabic script
 * narrows to one of five by looking for letters only some of them use.
 *
 * It CANNOT tell English from Portuguese, French or Spanish — they share an
 * alphabet, and no amount of regex changes that. For Latin text the caller
 * falls back to the event's declared source locale, which is the best guess
 * available and the behaviour that already exists. That limit is real and is
 * documented in FieldLanguage rather than hidden.
 */
class ScriptDetector
{
    /**
     * Scripts we test for, in the order they are checked, mapped to the served
     * locales that use them. A script listed against exactly ONE locale is
     * proof of that language; against several it only narrows the field.
     */
    private const SCRIPTS = [
        'Arabic' => ['ar', 'fa', 'ur', 'ps', 'ku'],
        'Han' => ['zh', 'zh-TW', 'ja'],
        'Hiragana' => ['ja'],
        'Katakana' => ['ja'],
        'Hangul' => ['ko'],
        'Cyrillic' => ['ru', 'uk', 'bg', 'sr', 'mk', 'kk', 'mn'],
        'Greek' => ['el'],
        'Hebrew' => ['he'],
        'Devanagari' => ['hi', 'mr', 'ne'],
        'Bengali' => ['bn'],
        'Tamil' => ['ta'],
        'Telugu' => ['te'],
        'Malayalam' => ['ml'],
        'Gujarati' => ['gu'],
        'Gurmukhi' => ['pa'],
        'Sinhala' => ['si'],
        'Thai' => ['th'],
        'Khmer' => ['km'],
        'Myanmar' => ['my'],
        'Ethiopic' => ['am'],
        'Georgian' => ['ka'],
        'Armenian' => ['hy'],
        'Latin' => [],   // too many to be evidence of anything
    ];

    /**
     * Letters that only some Arabic-script languages use. Finding one is proof;
     * finding none means Arabic, which is the common case here.
     */
    private const ARABIC_TELLS = [
        'ps' => ['ښ', 'ړ', 'ډ', 'ټ', 'ږ', 'ڼ'],
        'ur' => ['ٹ', 'ڈ', 'ڑ', 'ں', 'ے', 'ھ'],
        'ku' => ['ڵ', 'ڕ', 'ۆ', 'ێ', 'ھ'],
        'fa' => ['پ', 'چ', 'ژ', 'گ', 'ک', 'ی'],
    ];

    /**
     * The dominant script of a string, or 'mixed'.
     *
     * 'mixed' is the SAFE answer: the caller treats it as "translate this",
     * so an ambiguous field is never wrongly left alone. A proper noun inside
     * a foreign paragraph — "Victorian Academy" inside Arabic — is a handful of
     * Latin letters against hundreds of Arabic ones and does not move the
     * dominant script, which is exactly what should happen.
     */
    public function scriptOf(string $text): string
    {
        $counts = [];
        $letters = 0;

        foreach (self::SCRIPTS as $script => $_) {
            $n = preg_match_all('/\p{'.$script.'}/u', $text);

            if ($n) {
                $counts[$script] = $n;
                $letters += $n;
            }
        }

        // Too little to judge. A two-letter label tells nobody anything.
        if ($letters < 3) {
            return 'mixed';
        }

        arsort($counts);
        $top = array_key_first($counts);

        // Japanese is Han plus kana; kana anywhere settles it.
        if (isset($counts['Hiragana']) || isset($counts['Katakana'])) {
            return 'Japanese';
        }

        return ($counts[$top] / $letters) >= 0.6 ? $top : 'mixed';
    }

    /**
     * The served locales a script could be, narrowed where the letters allow.
     *
     * @return array<int, string>  empty when the script proves nothing
     */
    public function localesFor(string $script, string $text = ''): array
    {
        if ($script === 'Japanese') {
            return ['ja'];
        }

        $locales = self::SCRIPTS[$script] ?? [];

        if ($script === 'Arabic' && $text !== '') {
            foreach (self::ARABIC_TELLS as $locale => $letters) {
                foreach ($letters as $letter) {
                    if (mb_strpos($text, $letter) !== false) {
                        return [$locale];
                    }
                }
            }

            // No tells: Arabic itself, which is the overwhelming case on this
            // platform and the one that matters for the bug this fixes.
            return ['ar'];
        }

        return $locales;
    }
}
