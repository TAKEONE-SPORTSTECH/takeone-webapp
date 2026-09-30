<?php

namespace Ahl\Certificates\Support;

/**
 * Where each element of the certificate sits, in millimetres on an A4-landscape page
 * (297 × 210), how big its type is, what colour it prints in and whether it shows.
 *
 * A template stores only what the organiser CHANGED (`certificate_templates.layout`);
 * everything else comes from the defaults below, which reproduce the classic design.
 * A default is mirrored for a right-to-left language; an element the organiser has
 * placed stays exactly where they put it, in every language.
 *
 * Per element: x, y, w, h (mm, top-left corner and box), scale (type size, 1 = default),
 * color ('#rrggbb', or null = automatic contrast against what is behind it), align
 * (start | center | end), hidden. The text panel also has opacity.
 */
class Layout
{
    public const PAGE_W = 297;

    public const PAGE_H = 210;

    /**
     * key => [kind, base pt LTR, base pt RTL, default align]
     * kind: text (one line, shrunk to fit its box) · para (wraps) · image · block · shape
     */
    public const ELEMENTS = [
        'panel' => ['shape', 0, 0, 'center'],
        'logo_start' => ['image', 0, 0, 'start'],
        'logo_end' => ['image', 0, 0, 'end'],
        'photo' => ['image', 0, 0, 'center'],
        'medal' => ['image', 0, 0, 'center'],
        'headline' => ['text', 8.5, 12, 'center'],
        'title' => ['text', 33, 28, 'center'],
        'ribbon' => ['text', 8.5, 12, 'center'],
        'lead' => ['text', 14.5, 13, 'center'],
        'name' => ['text', 40, 32, 'center'],
        'divider' => ['shape', 0, 0, 'center'],
        'body' => ['para', 11.5, 12, 'center'],
        'signatures' => ['block', 8.5, 11, 'start'],
        'seal' => ['block', 13, 12, 'center'],
        'meta' => ['block', 7.5, 9, 'end'],
        'qr' => ['image', 0, 0, 'center'],
    ];

    /** The classic arrangement. Placed certificates (a medal) push the middle down. */
    public static function defaults(bool $placed): array
    {
        $d = [
            'panel' => ['x' => 30, 'y' => 24, 'w' => 237, 'h' => 104, 'hidden' => true, 'opacity' => 0.82],
            'logo_start' => ['x' => 24, 'y' => 20, 'w' => 17, 'h' => 17],
            'logo_end' => ['x' => 256, 'y' => 20, 'w' => 17, 'h' => 17],
            'photo' => ['x' => 16, 'y' => 15, 'w' => 23, 'h' => 30],
            'medal' => ['x' => 140.5, 'y' => 17, 'w' => 16, 'h' => 21],
            'headline' => ['x' => 60, 'y' => 23, 'w' => 177, 'h' => 6],
            'title' => ['x' => 24, 'y' => 39.5, 'w' => 249, 'h' => 15],
            'ribbon' => ['x' => 98.5, 'y' => 62, 'w' => 100, 'h' => 7],
            'lead' => ['x' => 24, 'y' => 59.5, 'w' => 249, 'h' => 7],
            'name' => ['x' => 24, 'y' => 69, 'w' => 249, 'h' => 16],
            'divider' => ['x' => 108.5, 'y' => 88, 'w' => 80, 'h' => 3],
            'body' => ['x' => 46, 'y' => 94, 'w' => 205, 'h' => 22],
            'signatures' => ['x' => 20, 'y' => 164, 'w' => 110, 'h' => 28],
            'seal' => ['x' => 135.5, 'y' => 167, 'w' => 26, 'h' => 26],
            'meta' => ['x' => 185, 'y' => 179, 'w' => 64, 'h' => 16],
            'qr' => ['x' => 252, 'y' => 169, 'w' => 24, 'h' => 24],
        ];

        if ($placed) {
            $d['headline']['y'] = 37.5;
            $d['title']['y'] = 46;
            $d['ribbon']['y'] = 64;
            $d['lead']['y'] = 75;
            $d['name']['y'] = 84;
            $d['divider']['y'] = 102;
            $d['body']['y'] = 108;
        }

        foreach ($d as $k => $box) {
            $d[$k] += ['scale' => 1.0, 'color' => null, 'align' => self::ELEMENTS[$k][3], 'hidden' => false];
        }

        return $d;
    }

    /**
     * The arrangement to draw: defaults (mirrored for RTL) with the stored changes on top.
     *
     * @return array<string, array{x:float,y:float,w:float,h:float,scale:float,color:?string,align:string,hidden:bool,moved:bool}>
     */
    public static function resolve(?array $stored, bool $placed, bool $rtl): array
    {
        $stored = self::sanitize((array) $stored);
        $out = [];

        foreach (self::defaults($placed) as $key => $box) {
            $mine = $stored[$key] ?? [];
            $moved = isset($mine['x']) || isset($mine['y']);

            if ($rtl && ! $moved) {
                $box['x'] = round(self::PAGE_W - $box['x'] - $box['w'], 2);
                $box['align'] = match ($box['align']) { 'start' => 'end', 'end' => 'start', default => 'center' };
            }

            $out[$key] = array_merge($box, $mine, ['moved' => $moved]);
        }

        return $out;
    }

    /** What may be stored: known elements and fields only, numbers kept on the page. */
    public static function sanitize(array $input): array
    {
        $clean = [];
        foreach ($input as $key => $box) {
            if (! isset(self::ELEMENTS[$key]) || ! is_array($box)) {
                continue;
            }
            $b = [];
            foreach (['x' => [-20, self::PAGE_W], 'y' => [-20, self::PAGE_H], 'w' => [3, self::PAGE_W], 'h' => [2, self::PAGE_H]] as $f => [$min, $max]) {
                if (isset($box[$f]) && is_numeric($box[$f])) {
                    $b[$f] = round(max($min, min($max, (float) $box[$f])), 2);
                }
            }
            if (isset($box['scale']) && is_numeric($box['scale'])) {
                $b['scale'] = round(max(0.3, min(3.0, (float) $box['scale'])), 3);
            }
            if (array_key_exists('color', $box)) {
                $b['color'] = is_string($box['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $box['color']) ? strtolower($box['color']) : null;
            }
            if (isset($box['align']) && in_array($box['align'], ['start', 'center', 'end'], true)) {
                $b['align'] = $box['align'];
            }
            if (isset($box['hidden'])) {
                $b['hidden'] = (bool) $box['hidden'];
            }
            if ($key === 'panel' && isset($box['opacity']) && is_numeric($box['opacity'])) {
                $b['opacity'] = round(max(0.2, min(1.0, (float) $box['opacity'])), 2);
            }
            if ($b !== []) {
                $clean[$key] = $b;
            }
        }

        return $clean;
    }
}
