<?php

namespace Ahl\Certificates\Support;

/**
 * Where each element of a page sits, in millimetres — for a certificate an A4 page,
 * landscape (297 × 210) or portrait (210 × 297); other formats (Support\Formats, a
 * badge) bring their own size, elements and defaults through the same calls — how big its type is, what colour it prints in
 * and whether it shows. Each orientation has its own defaults and its own stored changes
 * (`layout` / `layout_portrait`), so turning the page never scrambles an arrangement.
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

    public const ORIENTATIONS = ['landscape', 'portrait'];

    /** The organiser's own text elements: `text_` and six letters/digits, at most MAX_CUSTOM. */
    public const CUSTOM_KEY = '/^text_[a-z0-9]{6}$/';

    public const MAX_CUSTOM = 10;

    /**
     * Erased areas: a box the organiser draws over the page's picture to paint out what
     * is printed in it (Support\Eraser). `erase_` and six letters/digits, at most
     * MAX_ERASE, each belonging to the way up it was drawn on — the artwork is fitted
     * separately for each, so an area on one page means nothing on the other.
     */
    public const ERASE_KEY = '/^erase_[a-z0-9]{6}$/';

    public const MAX_ERASE = 12;

    /** style => [base pt LTR, base pt RTL] — the faces are chosen by the view. */
    public const CUSTOM_STYLES = ['heading' => [20, 20], 'body' => [11.5, 12], 'script' => [14.5, 14], 'small' => [8.5, 10]];

    /**
     * The organiser's own text elements as they may be stored: a key, the words per
     * locale (plain text, at most 300 characters) and a style. Anything else is dropped.
     *
     * @param  list<string>  $locales
     * @return list<array{key:string, text:array<string,string>, style:string}>
     */
    public static function customs(mixed $input, array $locales): array
    {
        $out = [];
        $erases = [];
        foreach (array_values(is_array($input) ? $input : []) as $el) {
            if (is_array($el) && is_string($el['key'] ?? null) && preg_match(self::ERASE_KEY, $el['key'])) {
                if (! isset($erases[$el['key']]) && count($erases) < self::MAX_ERASE) {
                    $erases[$el['key']] = ['key' => $el['key'], 'kind' => 'erase', 'page' => self::orientation(is_string($el['page'] ?? null) ? $el['page'] : null)];
                }

                continue;
            }
            if (! is_array($el) || ! is_string($el['key'] ?? null) || ! preg_match(self::CUSTOM_KEY, $el['key']) || isset($out[$el['key']])) {
                continue;
            }
            if (count($out) >= self::MAX_CUSTOM) {
                continue;
            }
            $text = [];
            foreach ($locales as $l) {
                $v = $el['text'][$l] ?? '';
                $text[$l] = is_string($v) ? mb_substr(trim(strip_tags($v)), 0, 300) : '';
            }
            $style = is_string($el['style'] ?? null) && isset(self::CUSTOM_STYLES[$el['style']]) ? $el['style'] : 'body';
            $out[$el['key']] = ['key' => $el['key'], 'text' => $text, 'style' => $style];
        }

        return array_merge(array_values($out), array_values($erases));
    }

    /** Where a new erased area starts: the middle of the page (of the format in force — Formats). */
    public static function eraseDefault(?string $orientation, ?Format $format = null): array
    {
        return ($format ?? Formats::current())->eraseDefault(self::orientation($orientation));
    }

    /** Where a new text element starts: across the middle of the page, below the name. */
    public static function customDefault(?string $orientation, ?Format $format = null): array
    {
        return ($format ?? Formats::current())->customDefault(self::orientation($orientation));
    }

    public static function orientation(?string $value): string
    {
        return $value === 'portrait' ? 'portrait' : 'landscape';
    }

    /** @return array{0:float,1:float} page width and height in mm (of the format in force — Formats) */
    public static function page(?string $orientation, ?Format $format = null): array
    {
        return ($format ?? Formats::current())->page(self::orientation($orientation));
    }

    /** The elements a page of this format can carry: key => [kind, base pt LTR, base pt RTL, align]. */
    public static function elements(?Format $format = null): array
    {
        return ($format ?? Formats::current())->elements;
    }

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

    /** The starting arrangement of the format in force (Formats) — for a certificate, the classic one. */
    public static function defaults(bool $placed, ?string $orientation = 'landscape', ?Format $format = null): array
    {
        return ($format ?? Formats::current())->defaults($placed, self::orientation($orientation));
    }

    /** The classic certificate arrangement, unfinished. Placed certificates (a medal) push the middle down. */
    public static function certificateDefaults(bool $placed, string $orientation): array
    {
        if ($orientation === 'portrait') {
            return self::portrait($placed);
        }

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

        return $d;
    }

    /** The same sheet stood upright: the text stacked down the middle, signatures across the foot. */
    private static function portrait(bool $placed): array
    {
        $d = [
            'panel' => ['x' => 18, 'y' => 32, 'w' => 174, 'h' => 140, 'hidden' => true, 'opacity' => 0.82],
            'logo_start' => ['x' => 20, 'y' => 20, 'w' => 17, 'h' => 17],
            'logo_end' => ['x' => 173, 'y' => 20, 'w' => 17, 'h' => 17],
            'photo' => ['x' => 16, 'y' => 15, 'w' => 23, 'h' => 30],
            'medal' => ['x' => 97, 'y' => 34, 'w' => 16, 'h' => 21],
            'headline' => ['x' => 25, 'y' => 44, 'w' => 160, 'h' => 6],
            'title' => ['x' => 16, 'y' => 56, 'w' => 178, 'h' => 15],
            'ribbon' => ['x' => 55, 'y' => 86, 'w' => 100, 'h' => 7],
            'lead' => ['x' => 16, 'y' => 82, 'w' => 178, 'h' => 7],
            'name' => ['x' => 16, 'y' => 93, 'w' => 178, 'h' => 16],
            'divider' => ['x' => 65, 'y' => 115, 'w' => 80, 'h' => 3],
            'body' => ['x' => 25, 'y' => 124, 'w' => 160, 'h' => 40],
            'signatures' => ['x' => 20, 'y' => 214, 'w' => 170, 'h' => 28],
            'seal' => ['x' => 92, 'y' => 180, 'w' => 26, 'h' => 26],
            'meta' => ['x' => 100, 'y' => 262, 'w' => 64, 'h' => 16],
            'qr' => ['x' => 166, 'y' => 254, 'w' => 24, 'h' => 24],
        ];

        if ($placed) {
            $d['headline']['y'] = 60;
            $d['title']['y'] = 68;
            $d['ribbon']['y'] = 86;
            $d['lead']['y'] = 97;
            $d['name']['y'] = 106;
            $d['divider']['y'] = 126;
            $d['body']['y'] = 134;
        }

        return $d;
    }

    /**
     * The arrangement to draw: defaults (mirrored for RTL) with the stored changes on top.
     *
     * @return array<string, array{x:float,y:float,w:float,h:float,scale:float,color:?string,align:string,hidden:bool,moved:bool}>
     */
    public static function resolve(?array $stored, bool $placed, bool $rtl, ?string $orientation = 'landscape', array $customKeys = [], ?Format $format = null): array
    {
        $format ??= Formats::current();
        $stored = self::sanitize((array) $stored, $orientation, $customKeys, $format);
        [$pageW] = self::page($orientation, $format);
        $out = [];
        $defaults = self::defaults($placed, $orientation, $format);
        foreach ($customKeys as $k) {
            $defaults[$k] = preg_match(self::ERASE_KEY, $k) ? self::eraseDefault($orientation, $format) : self::customDefault($orientation, $format);
        }

        foreach ($defaults as $key => $box) {
            $mine = $stored[$key] ?? [];
            $moved = isset($mine['x']) || isset($mine['y']);

            // An erased area is a place on the picture, which does not turn round in Arabic.
            if ($rtl && ! $moved && ! preg_match(self::ERASE_KEY, $key)) {
                $box['x'] = round($pageW - $box['x'] - $box['w'], 2);
                $box['align'] = match ($box['align']) { 'start' => 'end', 'end' => 'start', default => 'center' };
            }

            $out[$key] = array_merge($box, $mine, ['moved' => $moved]);
        }

        return $out;
    }

    /** What may be stored: known elements and fields only, numbers kept on the page. */
    public static function sanitize(array $input, ?string $orientation = 'landscape', array $customKeys = [], ?Format $format = null): array
    {
        $format ??= Formats::current();
        [$pageW, $pageH] = self::page($orientation, $format);
        $elements = $format->elements;
        $clean = [];
        foreach ($input as $key => $box) {
            $known = isset($elements[$key]) || in_array($key, $customKeys, true) || (is_string($key) && (preg_match(self::CUSTOM_KEY, $key) || preg_match(self::ERASE_KEY, $key)));
            if (! $known || ! is_array($box)) {
                continue;
            }
            $b = [];
            foreach (['x' => [-20, $pageW], 'y' => [-20, $pageH], 'w' => [3, $pageW], 'h' => [2, $pageH]] as $f => [$min, $max]) {
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
            if (isset($box['valign']) && in_array($box['valign'], ['top', 'middle', 'bottom'], true)) {
                $b['valign'] = $box['valign'];
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
