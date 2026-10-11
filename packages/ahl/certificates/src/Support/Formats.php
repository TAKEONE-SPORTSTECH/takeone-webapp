<?php

namespace Ahl\Certificates\Support;

/**
 * The page formats, and which one each template type is drawn in.
 *
 *   certificate  A4, landscape unless stood upright — the original, and the default
 *                for every type that names no format, so certificates are untouched.
 *   badge        A6 (105 × 148), upright — an official's lanyard card, printed four
 *                to an A4 sheet with cut marks.
 *
 * A host maps its own types onto a format in config `certificates.formats`
 * (type => format key). While a page is being drawn, the format in force is set with
 * using(), so the shared helpers that measure the page (Layout::page, Contrast, the
 * background fitting) read the right size without each being handed it.
 */
final class Formats
{
    public const CERTIFICATE = 'certificate';

    public const BADGE = 'badge';

    /** @var array<string, Format> */
    private static array $made = [];

    private static ?Format $current = null;

    public static function get(?string $key): Format
    {
        $key = in_array($key, [self::CERTIFICATE, self::BADGE], true) ? $key : self::CERTIFICATE;

        return self::$made[$key] ??= match ($key) {
            self::BADGE => self::badge(),
            default => self::certificate(),
        };
    }

    public static function forType(?string $type): Format
    {
        return self::get($type !== null ? (config('certificates.formats.'.$type) ?? null) : null);
    }

    /** The format a page is being drawn in right now (the certificate when none is set). */
    public static function current(): Format
    {
        return self::$current ?? self::get(self::CERTIFICATE);
    }

    /** Run $fn with $format in force, and put back whatever was in force before. */
    public static function using(Format $format, callable $fn): mixed
    {
        $before = self::$current;
        self::$current = $format;
        try {
            return $fn();
        } finally {
            self::$current = $before;
        }
    }

    private static function certificate(): Format
    {
        return new Format(
            key: self::CERTIFICATE,
            long: Layout::PAGE_W,
            short: Layout::PAGE_H,
            defaultOrientation: 'landscape',
            elements: Layout::ELEMENTS,
            defaults: fn (bool $placed, string $orientation) => Layout::certificateDefaults($placed, $orientation),
            customBox: ['landscape' => ['x' => 48.5, 'y' => 132, 'w' => 200, 'h' => 10], 'portrait' => ['x' => 25, 'y' => 168, 'w' => 160, 'h' => 10]],
            eraseBox: ['landscape' => ['x' => 98.5, 'y' => 90, 'w' => 100, 'h' => 25], 'portrait' => ['x' => 55, 'y' => 130, 'w' => 100, 'h' => 25]],
            view: 'certificates::pdf.certificate',
        );
    }

    /**
     * The lanyard card. Upright: the event across the top, the face (3:4, as every
     * picture of a person on the platform), the name, the role on a band in the event's
     * colour, the club, and the code a steward scans at the door.
     */
    private static function badge(): Format
    {
        return new Format(
            key: self::BADGE,
            long: 148,
            short: 105,
            defaultOrientation: 'portrait',
            elements: [
                'panel' => ['shape', 0, 0, 'center'],
                'logo_start' => ['image', 0, 0, 'start'],
                'logo_end' => ['image', 0, 0, 'end'],
                'headline' => ['text', 9, 11, 'center'],
                'title' => ['text', 15, 15, 'center'],
                'photo' => ['image', 0, 0, 'center'],
                'name' => ['text', 20, 18, 'center'],
                'role' => ['text', 13, 14, 'center'],
                'club' => ['text', 9.5, 11, 'center'],
                'qr' => ['image', 0, 0, 'center'],
                'meta' => ['block', 6.5, 8, 'center'],
            ],
            defaults: fn (bool $placed, string $orientation) => $orientation === 'portrait' ? [
                'panel' => ['x' => 7, 'y' => 7, 'w' => 91, 'h' => 134, 'hidden' => true, 'opacity' => 0.82],
                'logo_start' => ['x' => 8, 'y' => 8, 'w' => 14, 'h' => 14],
                'logo_end' => ['x' => 83, 'y' => 8, 'w' => 14, 'h' => 14],
                'headline' => ['x' => 24, 'y' => 10, 'w' => 57, 'h' => 10],
                'title' => ['x' => 8, 'y' => 24, 'w' => 89, 'h' => 8],
                'photo' => ['x' => 33.5, 'y' => 35, 'w' => 38, 'h' => 50.7],
                'name' => ['x' => 6, 'y' => 89, 'w' => 93, 'h' => 10],
                'role' => ['x' => 17.5, 'y' => 101, 'w' => 70, 'h' => 9],
                'club' => ['x' => 8, 'y' => 113, 'w' => 89, 'h' => 6],
                'qr' => ['x' => 41, 'y' => 121, 'w' => 23, 'h' => 23],
                'meta' => ['x' => 66, 'y' => 136, 'w' => 32, 'h' => 8, 'hidden' => true],
            ] : [
                'panel' => ['x' => 7, 'y' => 7, 'w' => 134, 'h' => 91, 'hidden' => true, 'opacity' => 0.82],
                'logo_start' => ['x' => 8, 'y' => 8, 'w' => 13, 'h' => 13],
                'logo_end' => ['x' => 127, 'y' => 8, 'w' => 13, 'h' => 13],
                'headline' => ['x' => 23, 'y' => 9, 'w' => 102, 'h' => 10],
                'title' => ['x' => 52, 'y' => 24, 'w' => 89, 'h' => 8],
                'photo' => ['x' => 9, 'y' => 26, 'w' => 39, 'h' => 52],
                'name' => ['x' => 52, 'y' => 36, 'w' => 89, 'h' => 10],
                'role' => ['x' => 61.5, 'y' => 50, 'w' => 70, 'h' => 9],
                'club' => ['x' => 52, 'y' => 62, 'w' => 89, 'h' => 6],
                'qr' => ['x' => 120, 'y' => 75, 'w' => 21, 'h' => 21],
                'meta' => ['x' => 52, 'y' => 86, 'w' => 60, 'h' => 8, 'hidden' => true],
            ],
            customBox: ['landscape' => ['x' => 52, 'y' => 70, 'w' => 89, 'h' => 6], 'portrait' => ['x' => 8, 'y' => 70, 'w' => 89, 'h' => 6]],
            eraseBox: ['landscape' => ['x' => 54, 'y' => 40, 'w' => 40, 'h' => 12], 'portrait' => ['x' => 32.5, 'y' => 60, 'w' => 40, 'h' => 12]],
            view: 'certificates::pdf.badge',
            sheet: ['paper' => 'a4', 'cols' => 2, 'rows' => 2],
            paper: 'a6',
        );
    }
}
