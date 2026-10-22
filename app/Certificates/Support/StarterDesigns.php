<?php

namespace App\Certificates\Support;

/**
 * Where a NEW certificate or badge design starts in the designer — a complete,
 * printable page the organiser then makes their own, never a blank sheet.
 * Everything is placed as a fraction of the page, so the starter fits whatever
 * size the design starts at. Lengths are CSS px at 96 dpi.
 */
final class StarterDesigns
{
    private const NAVY = '#1e3a8a';

    private const GOLD = '#c9a227';

    /** @param  array{0: float, 1: float}  $size */
    public static function certificate(array $size, bool $podium): array
    {
        [$w, $h] = $size;

        return [
            self::shape('frame', 0.03 * $w, 0.04 * $h, 0.94 * $w, 0.92 * $h, null, ['color' => self::NAVY, 'width' => 3, 'style' => 'solid']),
            self::shape('frame-inner', 0.045 * $w, 0.065 * $h, 0.91 * $w, 0.87 * $h, null, ['color' => self::GOLD, 'width' => 1, 'style' => 'solid']),
            self::field('logo', 0.075 * $w, 0.09 * $h, 0.11 * $h, 0.11 * $h, 'contain'),
            self::text('title', 'CERTIFICATE', 0.1 * $w, 0.12 * $h, 0.8 * $w, 0.1 * $h, 'Cinzel', 700, self::NAVY, ['letterSpacing' => 120]),
            self::text('subtitle', $podium ? 'OF ACHIEVEMENT' : 'OF PARTICIPATION', 0.1 * $w, 0.255 * $h, 0.8 * $w, 0.03 * $h, 'Montserrat', 600, self::GOLD, ['letterSpacing' => 400]),
            self::text('presented', 'This certificate is proudly presented to', 0.1 * $w, 0.335 * $h, 0.8 * $w, 0.028 * $h, 'Montserrat', 400, '#334155'),
            self::text('name', '{name}', 0.08 * $w, 0.4 * $h, 0.84 * $w, 0.1 * $h, 'Great Vibes', 400, self::NAVY),
            self::line('name-rule', 0.25 * $w, 0.565 * $h, 0.5 * $w, self::GOLD, 2),
            self::text('body', $podium ? 'for winning {medal} in {division} at {event}' : 'for taking part in {division} at {event}', 0.18 * $w, 0.6 * $h, 0.64 * $w, 0.026 * $h, 'Montserrat', 400, '#334155'),
            self::text('when', '{date}', 0.18 * $w, 0.685 * $h, 0.64 * $w, 0.022 * $h, 'Montserrat', 500, '#64748b'),
            self::field('qr', 0.5 * $w - 0.065 * $h, 0.765 * $h, 0.13 * $h, 0.13 * $h, 'contain'),
            self::line('sign-left', 0.12 * $w, 0.84 * $h, 0.22 * $w, '#334155', 1),
            self::text('sign-left-label', 'Signature', 0.12 * $w, 0.85 * $h, 0.22 * $w, 0.02 * $h, 'Montserrat', 500, '#64748b'),
            self::line('sign-right', 0.66 * $w, 0.84 * $h, 0.22 * $w, '#334155', 1),
            self::text('sign-right-label', 'Signature', 0.66 * $w, 0.85 * $h, 0.22 * $w, 0.02 * $h, 'Montserrat', 500, '#64748b'),
        ];
    }

    /**
     * A participating club's thank-you: the certificate page, worded for a club
     * — the CLUB is thanked, not a person in it (owner, 2026-10-20) — so the
     * club's name is set as a name of an institution, not in a signature
     * script, and the club's logo stands opposite the host's logo.
     *
     * @param  array{0: float, 1: float}  $size
     */
    public static function clubThanks(array $size): array
    {
        [$w, $h] = $size;
        $page = array_map(fn (array $el) => match ($el['id']) {
            's-subtitle' => ['text' => 'OF APPRECIATION'] + $el,
            's-presented' => ['text' => 'This certificate is presented to the club'] + $el,
            's-name' => ['fontFamily' => 'Cinzel', 'fontWeight' => 700, 'fontSize' => round(0.06 * $h, 1), 'uppercase' => true, 'letterSpacing' => 60,
                'y' => round(0.42 * $h, 2), 'height' => round(0.06 * $h * 1.3, 2)] + $el,
            's-name-rule' => ['y' => round(0.515 * $h, 2)] + $el,
            's-body' => ['text' => 'in appreciation of the club\'s participation in {event} with {athletes}, and its contribution to the success of the championship',
                'y' => round(0.545 * $h, 2)] + $el,
            's-when' => ['y' => round(0.715 * $h, 2)] + $el,
            default => $el,
        }, self::certificate($size, false));
        $page[] = self::field('club_logo', 0.925 * $w - 0.11 * $h, 0.09 * $h, 0.11 * $h, 0.11 * $h, 'contain');

        return $page;
    }

    /** @param  array{0: float, 1: float}  $size */
    public static function badge(array $size, string $event): array
    {
        [$w, $h] = $size;
        $photoW = 0.42 * $w;

        return [
            self::shape('band', 0, 0, $w, 0.16 * $h, self::NAVY, null),
            self::text('event', '{event}', 0.06 * $w, 0.05 * $h, 0.88 * $w, 0.034 * $h, 'Montserrat', 700, '#ffffff'),
            self::field('photo', ($w - $photoW) / 2, 0.2 * $h, $photoW, $photoW * 4 / 3, 'cover', 10),
            self::text('name', '{name}', 0.05 * $w, 0.6 * $h, 0.9 * $w, 0.05 * $h, 'Montserrat', 800, '#0f172a'),
            self::shape('role-band', 0.15 * $w, 0.695 * $h, 0.7 * $w, 0.065 * $h, self::GOLD, null, 999),
            self::text('role', '{role}', 0.15 * $w, 0.708 * $h, 0.7 * $w, 0.03 * $h, 'Montserrat', 700, '#ffffff', ['uppercase' => true, 'letterSpacing' => 100]),
            self::text('club', '{club}', 0.05 * $w, 0.785 * $h, 0.9 * $w, 0.026 * $h, 'Montserrat', 500, '#475569'),
            self::field('qr', ($w - 0.12 * $h) / 2, 0.845 * $h, 0.12 * $h, 0.12 * $h, 'contain'),
        ];
    }

    private static function base(string $id, string $type, float $x, float $y, float $w, float $h): array
    {
        return ['id' => 's-'.$id, 'type' => $type, 'x' => round($x, 2), 'y' => round($y, 2), 'width' => round($w, 2), 'height' => round($h, 2), 'rotation' => 0, 'opacity' => 1];
    }

    private static function text(string $id, string $text, float $x, float $y, float $w, float $size, string $font, int $weight, string $color, array $extra = []): array
    {
        return self::base($id, 'text', $x, $y, $w, $size * 1.3) + $extra + [
            'text' => $text, 'fontFamily' => $font, 'fontSize' => round($size, 1), 'fontWeight' => $weight,
            'color' => $color, 'align' => 'center', 'lineHeight' => 1.3,
        ];
    }

    private static function shape(string $id, float $x, float $y, float $w, float $h, ?string $fill, ?array $stroke, float $radius = 0): array
    {
        return self::base($id, 'shape', $x, $y, $w, $h) + ['shape' => 'rect', 'fill' => $fill ?? 'transparent', 'stroke' => $stroke, 'radius' => $radius, 'text' => ''];
    }

    private static function line(string $id, float $x, float $y, float $w, string $color, float $width): array
    {
        return self::base($id, 'line', $x, $y, $w, max(1, $width)) + ['stroke' => ['color' => $color, 'width' => $width, 'style' => 'solid']];
    }

    private static function field(string $key, float $x, float $y, float $w, float $h, string $fit, float $radius = 0): array
    {
        return self::base('f-'.$key, 'image', $x, $y, $w, $h) + ['field' => $key, 'fit' => $fit, 'radius' => $radius];
    }
}
