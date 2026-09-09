<?php

namespace App\Support;

use DateTimeInterface;
use DateTimeZone;
use IntlDateFormatter;
use IntlDatePatternGenerator;
use NumberFormatter;

/**
 * Dates, times, numbers and money in the reader's own language — and in the
 * reader's own ORDER.
 *
 * ── What was wrong with what this replaces ───────────────────────────────────
 *
 * The platform formatted every date as `->translatedFormat('l, j F Y')` or
 * `->translatedFormat('g:i A')`. Carbon translates the TOKENS and keeps the
 * PATTERN, so a Chinese reader was shown:
 *
 *     周五 18 9月          ← Chinese words, English word order
 *     3:00 下午            ← Chinese meridiem, English 12-hour clock
 *
 * Chinese writes that date `9月18日星期五` and that time `15:00`. Japanese
 * agrees. Hungarian puts the year first. Arabic wants its own digits. There is
 * no pattern string that is right for sixty-eight languages, which is why ICU
 * ships one per language and why picking one by hand is always a bug in the
 * sixty-seven you did not think about.
 *
 * ── The idea: describe the FIELDS, not the layout ────────────────────────────
 *
 * A caller says WHICH pieces it wants — a day and an abbreviated month — as an
 * ICU skeleton, and CLDR decides the order, the separators, the digits and
 * whether the clock is 12- or 24-hour:
 *
 *     Cldr::skeleton($date, 'dMMM')    en → "Sep 18"    zh → "9月18日"
 *     Cldr::time($start)               en → "3:00 PM"   zh → "15:00"
 *     Cldr::money(5, 'BHD')            en → "BHD 5.000" fr → "5,000 BHD"
 *
 * Skeleton letters are the ICU ones: `d` day, `MMM` short month, `MMMM` full,
 * `EEEE` weekday, `y` year, `j` hour in whatever clock the locale uses (never
 * `h` or `H`, which force one). Order inside the skeleton is irrelevant — that
 * is the point.
 *
 * ── Falls back, never fails ──────────────────────────────────────────────────
 *
 * `ext-intl` is required for the real thing and was NOT installed on this box
 * until 2026-09-09. Every method degrades to the old Carbon behaviour when the
 * extension is missing, so a server that has not been updated renders exactly
 * what it rendered before rather than a stack trace (RULE #1).
 */
class Cldr
{
    /** Cheap per-request caches — one formatter per locale+shape. */
    private static array $formatters = [];

    private static array $patterns = [];

    public static function available(): bool
    {
        return class_exists(IntlDateFormatter::class);
    }

    /**
     * A date rendered from the FIELDS you want, in this locale's own order.
     *
     * The workhorse, and the replacement for every `translatedFormat('…')` on
     * this platform. `$fallback` is the Carbon pattern to use when ext-intl is
     * absent — pass the pattern the call site used before, so removing the
     * extension is a downgrade rather than a break.
     */
    public static function skeleton(?DateTimeInterface $date, string $skeleton, string $fallback = 'j M Y'): string
    {
        if ($date === null) {
            return '';
        }

        if (! self::available()) {
            return self::carbon($date, $fallback);
        }

        $locale = self::locale();
        $key = $locale.'|'.$skeleton;

        $pattern = self::$patterns[$key] ??= (new IntlDatePatternGenerator($locale))->getBestPattern($skeleton);

        return self::formatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, $pattern)
            ->format($date) ?: self::carbon($date, $fallback);
    }

    /** The long, spoken form of a date — "Friday, 18 September 2026". */
    public static function fullDate(?DateTimeInterface $date): string
    {
        return self::skeleton($date, 'EEEEdMMMMy', 'l, j F Y');
    }

    /** Weekday, day and month, no year — the form a poster uses. */
    public static function dayMonth(?DateTimeInterface $date): string
    {
        return self::skeleton($date, 'EEEEdMMMM', 'l, j F');
    }

    /** Day and short month — "18 Sep", "9月18日". */
    public static function shortDate(?DateTimeInterface $date): string
    {
        return self::skeleton($date, 'dMMM', 'j M');
    }

    /**
     * A time of day, on the clock this locale actually uses.
     *
     * `j` rather than `h`/`H` is the whole trick: it resolves to a 12-hour
     * clock with a meridiem in English and Arabic, and a 24-hour clock in
     * Chinese, Japanese, French and German — which is what those readers expect
     * and what "3:00 下午" was getting wrong.
     */
    public static function time(?DateTimeInterface $date): string
    {
        return self::skeleton($date, 'jmm', 'g:i A');
    }

    /** A number, with this locale's own grouping, decimal mark and digits. */
    public static function number(float|int|null $value, int $decimals = 0): string
    {
        if ($value === null) {
            return '';
        }

        if (! self::available()) {
            return number_format((float) $value, $decimals);
        }

        $f = self::$formatters['n|'.self::locale().'|'.$decimals] ??= (function () use ($decimals) {
            $f = new NumberFormatter(self::locale(), NumberFormatter::DECIMAL);
            $f->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
            $f->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);

            return $f;
        })();

        return (string) $f->format((float) $value);
    }

    /**
     * An amount of money, placed and punctuated the way this locale writes it.
     *
     * ⚠️ The CURRENCY CODE is never translated — "BHD" is an identifier, and a
     * competitor reading a fee needs to be able to match it against their bank.
     * ICU handles that itself; this note is here so nobody "fixes" it later.
     */
    public static function money(float|int|null $amount, ?string $currency): string
    {
        if ($amount === null) {
            return '';
        }

        $currency = strtoupper(trim((string) $currency)) ?: 'BHD';

        if (! self::available()) {
            return $currency.' '.number_format((float) $amount, 3);
        }

        $f = self::$formatters['c|'.self::locale()] ??= new NumberFormatter(self::locale(), NumberFormatter::CURRENCY);

        return (string) $f->formatCurrency((float) $amount, $currency);
    }

    /** "in 3 days" / "3 天后", in the reader's language. */
    public static function relative(?DateTimeInterface $date): string
    {
        return $date === null ? '' : \Illuminate\Support\Carbon::instance($date)->locale(self::locale())->diffForHumans();
    }

    /**
     * The ICU locale for the active app locale.
     *
     * `zh-Hans` and `zh_Hans` both mean the same thing to ICU; the app stores
     * `zh`, which ICU resolves to Simplified Chinese on its own.
     */
    private static function locale(): string
    {
        return str_replace('-', '_', app()->getLocale());
    }

    private static function formatter(string $locale, int $dateType, int $timeType, ?string $pattern): IntlDateFormatter
    {
        $key = 'd|'.$locale.'|'.$dateType.'|'.$timeType.'|'.$pattern;

        return self::$formatters[$key] ??= new IntlDateFormatter(
            $locale,
            $dateType,
            $timeType,
            // The reader's own clock, not UTC: an event at 15:00 in Bahrain is
            // 15:00 on the poster wherever it is read from, because the time
            // printed is the time you turn up at the hall.
            new DateTimeZone(config('app.timezone', 'UTC')),
            null,
            $pattern,
        );
    }

    /** The pre-ICU behaviour, kept as the fallback. */
    private static function carbon(DateTimeInterface $date, string $pattern): string
    {
        return \Illuminate\Support\Carbon::instance($date)->translatedFormat($pattern);
    }
}
