<?php

namespace Ahl\Certificates\Support;

/**
 * dompdf neither joins Arabic letters nor orders right-to-left text. This does both,
 * with khaled.alshamaa/ar-php when it is installed, and returns the text as VISUAL
 * lines ready to be printed left to right — so a view prints each line as-is and never
 * lets dompdf wrap it (a wrap after shaping would put the words back in the wrong order).
 *
 * Text with no Arabic in it is returned untouched as one line, and dompdf wraps it.
 */
class TextShaper
{
    private ?object $arabic = null;

    public function available(): bool
    {
        return class_exists(\ArPHP\I18N\Arabic::class);
    }

    public static function hasArabic(string $text): bool
    {
        return (bool) preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}]/u', $text);
    }

    /**
     * @return list<string> visual lines
     */
    public function lines(string $text, int $maxChars = 80): array
    {
        $text = trim($text);
        if ($text === '' || ! self::hasArabic($text) || ! $this->available()) {
            return [$text];
        }

        $this->arabic ??= new \ArPHP\I18N\Arabic;
        // Western digits: a certificate number, a weight and a date read the same in both languages.
        $shaped = (string) $this->arabic->utf8Glyphs($text, $maxChars, false, true);

        $lines = array_map(fn ($l) => $this->spaceRuns($l), preg_split('/\R/u', $shaped) ?: [$shaped]);

        return array_values(array_filter($lines, fn ($l) => $l !== ''));
    }

    /**
     * ar-php moves the space between an Arabic word and a Latin run (a name, a
     * division like "Kids [Gi]") to the far side of the run, so the two touch.
     * Put one space back wherever the scripts meet, and tidy the rest.
     */
    private function spaceRuns(string $line): string
    {
        $arabic = '\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}';
        $line = preg_replace('/([A-Za-z0-9\.\)\]])(?=['.$arabic.'])/u', '$1 ', $line) ?? $line;
        // …but never after Arabic punctuation (، ؛ ؟), which sits hard against what it follows.
        $line = preg_replace('/(?<=['.$arabic.'])(?<![\x{060C}\x{061B}\x{061F}])([A-Za-z0-9\(\[])/u', ' $1', $line) ?? $line;

        return trim(preg_replace('/ {2,}/u', ' ', $line) ?? $line);
    }

    /** Arabic letters in any form, joined (presentation forms) or not: the text needs an Arabic face. */
    public static function isArabicScript(string $text): bool
    {
        return (bool) preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text);
    }

    /** Whether the text reads right to left: its first letter is Arabic. */
    public static function readsRtl(string $text): bool
    {
        return preg_match('/\p{L}/u', $text, $m) === 1 && self::hasArabic($m[0]);
    }

    /**
     * Arabic inside a left-to-right line — an Arabic name or club in an English
     * sentence. Each Arabic run is joined and turned to visual order where it stands;
     * the Latin around it keeps its order, so dompdf may still wrap the line — but never
     * inside an Arabic run (its spaces become no-break spaces), which would swap its words.
     */
    public function runs(string $text): string
    {
        if (! self::hasArabic($text) || ! $this->available()) {
            return $text;
        }
        $ar = '\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}';

        return preg_replace_callback('/['.$ar.']+(?:[\s\x{060C}\x{061B}\x{061F}]+['.$ar.']+)*/u', fn ($m) => str_replace(' ', "\u{00A0}", $this->line($m[0])), $text) ?? $text;
    }

    /**
     * Any text, ready to print on a page: on an Arabic page, or when the text itself
     * starts in Arabic, it is one right-to-left line; otherwise only its Arabic runs
     * are shaped. Text with no Arabic comes back as it went in.
     */
    public function visual(string $text, bool $rtl = false): string
    {
        return $rtl || self::readsRtl($text) ? $this->line($text) : $this->runs($text);
    }

    /** One line, shaped (for short strings that must never wrap: a name, a title). */
    public function line(string $text): string
    {
        return implode(' ', array_reverse($this->lines($text, 10000)));
    }
}
