<?php

namespace App\Printables\Support;

use Illuminate\Validation\ValidationException;

/**
 * Every document is rebuilt here before it is stored — never saved as sent.
 *
 * The editor is a client; anything it sends could have been written by hand.
 * So the stored document is CONSTRUCTED from a whitelist: known element types,
 * known keys, numbers clamped, colours matched, fonts looked up, pictures named
 * only by an asset of THIS design (or a built-in graphic, or a live field the
 * kind offers). Text stays plain text — the renderer writes it as text nodes,
 * so there is no HTML anywhere in a design to sanitise later.
 *
 * Unknown keys are dropped silently; a structurally broken document (too big,
 * too many pages or elements) is refused with a 422.
 */
final class DocumentSanitizer
{
    private const TYPES = ['text', 'shape', 'line', 'image', 'group'];

    private const SHAPES = ['rect', 'ellipse', 'triangle', 'polygon', 'star', 'diamond', 'arrow', 'heart'];

    private int $count = 0;

    /**
     * @param  list<string>  $assets  uuids of this design's pictures
     * @param  list<string>  $imageFields  keys of the kind's picture fields
     */
    public function __construct(
        private readonly array $assets,
        private readonly array $imageFields,
        private readonly array $fonts,
    ) {}

    /** @return array{version: int, pages: list<array>} */
    public function clean(mixed $doc): array
    {
        $limits = (array) config('printables.limits', []);
        if (! is_array($doc) || ! isset($doc['pages']) || ! is_array($doc['pages'])) {
            $this->fail('The design could not be read.');
        }
        if (strlen((string) json_encode($doc)) > (int) ($limits['document_bytes'] ?? 1_500_000)) {
            $this->fail('This design is too large to save.');
        }
        $pages = array_values(array_filter($doc['pages'], 'is_array'));
        if ($pages === [] || count($pages) > (int) ($limits['pages'] ?? 30)) {
            $this->fail('A design has between one and '.(int) ($limits['pages'] ?? 30).' pages.');
        }

        $out = [];
        foreach ($pages as $page) {
            $out[] = $this->page($page);
        }
        if ($this->count > (int) ($limits['elements'] ?? 400)) {
            $this->fail('A design may hold at most '.(int) ($limits['elements'] ?? 400).' elements.');
        }

        return ['version' => 1, 'pages' => $out];
    }

    private function page(array $p): array
    {
        $elements = [];
        foreach (array_values(array_filter((array) ($p['elements'] ?? []), 'is_array')) as $e) {
            if ($clean = $this->element($e, 0)) {
                $elements[] = $clean;
            }
        }

        return [
            'id' => $this->id($p['id'] ?? null),
            'background' => $this->color($p['background'] ?? null) ?? '#ffffff',
            'locked' => (bool) ($p['locked'] ?? false),
            'notes' => $this->text($p['notes'] ?? '', 5000),
            'title' => $this->text($p['title'] ?? '', 120),
            'elements' => $elements,
        ];
    }

    private function element(array $e, int $depth): ?array
    {
        $type = $e['type'] ?? null;
        if (! in_array($type, self::TYPES, true) || ($type === 'group' && $depth > 2)) {
            return null;
        }
        $this->count++;

        $out = [
            'id' => $this->id($e['id'] ?? null),
            'type' => $type,
            'name' => $this->text($e['name'] ?? '', 80),
            'x' => $this->num($e['x'] ?? 0, -100000, 100000),
            'y' => $this->num($e['y'] ?? 0, -100000, 100000),
            'width' => $this->num($e['width'] ?? 100, 0.5, 100000),
            'height' => $this->num($e['height'] ?? 100, 0.5, 100000),
            'rotation' => $this->num($e['rotation'] ?? 0, -360, 360),
            'flipX' => (bool) ($e['flipX'] ?? false),
            'flipY' => (bool) ($e['flipY'] ?? false),
            'opacity' => $this->num($e['opacity'] ?? 1, 0, 1, 3),
            'locked' => (bool) ($e['locked'] ?? false),
            'shadow' => $this->shadow($e['shadow'] ?? null),
        ];

        return match ($type) {
            'text' => $out + $this->textProps($e),
            'shape' => $out + $this->shapeProps($e),
            'line' => $out + $this->lineProps($e),
            'image' => ($img = $this->imageProps($e)) ? $out + $img : null,
            'group' => $out + ['children' => array_values(array_filter(array_map(
                fn ($c) => is_array($c) ? $this->element($c, $depth + 1) : null,
                (array) ($e['children'] ?? []),
            )))],
        };
    }

    private function textProps(array $e): array
    {
        return [
            'text' => $this->text($e['text'] ?? '', 5000),
            'fontFamily' => in_array($e['fontFamily'] ?? null, $this->fonts, true) ? $e['fontFamily'] : Fonts::DEFAULT,
            'fontSize' => $this->num($e['fontSize'] ?? 16, 1, 2000, 1),
            'fontWeight' => (int) $this->num($e['fontWeight'] ?? 400, 100, 1000, 0),
            'italic' => (bool) ($e['italic'] ?? false),
            'underline' => (bool) ($e['underline'] ?? false),
            'strike' => (bool) ($e['strike'] ?? false),
            'uppercase' => (bool) ($e['uppercase'] ?? false),
            'color' => $this->color($e['color'] ?? null) ?? '#000000',
            'align' => in_array($e['align'] ?? null, ['left', 'center', 'right', 'justify'], true) ? $e['align'] : 'left',
            'lineHeight' => $this->num($e['lineHeight'] ?? 1.4, 0.5, 5, 2),
            'letterSpacing' => $this->num($e['letterSpacing'] ?? 0, -200, 1000, 0),
            'list' => in_array($e['list'] ?? null, ['bullet', 'number'], true) ? $e['list'] : 'none',
            'valign' => in_array($e['valign'] ?? null, ['top', 'middle', 'bottom'], true) ? $e['valign'] : null,
            'outline' => is_array($e['outline'] ?? null) && ($oc = $this->color($e['outline']['color'] ?? null))
                ? ['width' => $this->num($e['outline']['width'] ?? 1, 0, 200, 1), 'color' => $oc] : null,
            'background' => $this->color($e['background'] ?? null),
            // A text box set taller than its text by its top/bottom handles; 0 = follows the text.
            'minHeight' => $this->num($e['minHeight'] ?? 0, 0, 100000, 1),
            // Too long for its box: squeezed sideways or shrunk to fit; null = it wraps.
            'fit' => in_array($e['fit'] ?? null, ['squeeze', 'shrink'], true) ? $e['fit'] : null,
            // A {name} cut to this many names (first, second, last); null = the whole name.
            'nameWords' => in_array((int) ($e['nameWords'] ?? 0), [2, 3], true) ? (int) $e['nameWords'] : null,
        ];
    }

    private function shapeProps(array $e): array
    {
        $shape = in_array($e['shape'] ?? null, self::SHAPES, true) ? $e['shape'] : 'rect';

        return [
            'shape' => $shape,
            'sides' => (int) $this->num($e['sides'] ?? 5, 3, 12, 0),
            'fill' => $this->color($e['fill'] ?? null),
            'fill2' => $this->color($e['fill2'] ?? null),
            'gradientAngle' => $this->num($e['gradientAngle'] ?? 90, 0, 360, 0),
            'radius' => $this->num($e['radius'] ?? 0, 0, 10000, 1),
            'stroke' => $this->stroke($e['stroke'] ?? null),
        ] + $this->textProps($e);
    }

    private function lineProps(array $e): array
    {
        return [
            'stroke' => $this->stroke($e['stroke'] ?? null, default: ['color' => '#000000', 'width' => 2, 'style' => 'solid']),
            'capStart' => in_array($e['capStart'] ?? null, ['arrow', 'circle'], true) ? $e['capStart'] : 'none',
            'capEnd' => in_array($e['capEnd'] ?? null, ['arrow', 'circle'], true) ? $e['capEnd'] : 'none',
        ];
    }

    private function imageProps(array $e): ?array
    {
        $src = null;
        $field = null;
        $graphic = null;
        if (isset($e['asset']) && in_array($e['asset'], $this->assets, true)) {
            $src = $e['asset'];
        } elseif (isset($e['field']) && in_array($e['field'], $this->imageFields, true)) {
            $field = $e['field'];
        } elseif (isset($e['graphic']) && is_string($e['graphic']) && preg_match('/^[a-z0-9-]{1,40}$/', $e['graphic'])) {
            $graphic = $e['graphic'];
        } else {
            // A picture whose file is not in this design is dropped, not stored as a dangling link.
            return null;
        }
        $colors = [];
        foreach (array_slice((array) ($e['colors'] ?? []), 0, 24, true) as $from => $to) {
            if (($f = $this->color($from)) && ($t = $this->color($to))) {
                $colors[$f] = $t;
            }
        }

        $props = [
            'asset' => $src,
            'field' => $field,
            'graphic' => $graphic,
            'colors' => (object) $colors,
            'fit' => in_array($e['fit'] ?? null, ['cover', 'contain', 'fill'], true) ? $e['fit'] : 'cover',
            'radius' => $this->num($e['radius'] ?? 0, 0, 10000, 1),
            'stroke' => $this->stroke($e['stroke'] ?? null),
        ];
        // A styled QR code. Absent = the plain code, exactly as before, so the key is only written when set.
        if ($field === 'qr' && is_array($e['qrStyle'] ?? null)) {
            $props['qrStyle'] = $this->qrStyle($e['qrStyle']);
        }

        return $props;
    }

    private const QR_BODY = ['square', 'rounded', 'extra-rounded', 'dots', 'classy', 'classy-rounded', 'liquid', 'small-square', 'diamond', 'star', 'plus', 'v-bars', 'h-bars'];

    private const QR_FRAME = ['square', 'rounded', 'extra-rounded', 'circle', 'leaf', 'teardrop', 'cushion', 'octagon', 'shield'];

    private const QR_BALL = ['square', 'rounded', 'circle', 'leaf', 'teardrop', 'cushion', 'octagon'];

    /** Only known shapes, real colours and clamped numbers survive (docs/qr-designer-study.md). */
    private function qrStyle(array $q): array
    {
        $pick = fn ($v, array $list, string $default) => in_array($v, $list, true) ? $v : $default;
        $ink = fn ($v) => ($c = $this->color($v)) && $c !== 'transparent' ? $c : null;

        $gradient = null;
        if (is_array($q['gradient'] ?? null) && is_array($q['gradient']['stops'] ?? null)) {
            $stops = [];
            foreach (array_slice($q['gradient']['stops'], 0, 4) as $stop) {
                if (is_array($stop) && ($c = $ink($stop[0] ?? null))) {
                    $stops[] = [$c, $this->num($stop[1] ?? 0, 0, 1, 3)];
                }
            }
            if (count($stops) >= 2) {
                $gradient = [
                    'type' => ($q['gradient']['type'] ?? null) === 'radial' ? 'radial' : 'linear',
                    'angle' => $this->num($q['gradient']['angle'] ?? 135, 0, 360, 1),
                    'stops' => $stops,
                ];
            }
        }

        $eyes = [];
        foreach (['tl', 'tr', 'bl'] as $corner) {
            $e = $q['eyes'][$corner] ?? null;
            if (! is_array($e)) {
                continue;
            }
            $eye = array_filter([
                'frame' => in_array($e['frame'] ?? null, self::QR_FRAME, true) ? $e['frame'] : null,
                'ball' => in_array($e['ball'] ?? null, self::QR_BALL, true) ? $e['ball'] : null,
                'frameColor' => $ink($e['frameColor'] ?? null),
                'ballColor' => $ink($e['ballColor'] ?? null),
                'flip' => ! empty($e['flip']) ? true : null,
            ], fn ($v) => $v !== null);
            if ($eye) {
                $eyes[$corner] = $eye;
            }
        }

        $bg = $this->color($q['bg'] ?? null);

        return [
            'v' => 1,
            'body' => $pick($q['body'] ?? null, self::QR_BODY, 'square'),
            'eyeFrame' => $pick($q['eyeFrame'] ?? null, self::QR_FRAME, 'square'),
            'eyeBall' => $pick($q['eyeBall'] ?? null, self::QR_BALL, 'square'),
            'bodyColor' => $ink($q['bodyColor'] ?? null) ?? '#000000',
            'eyeFrameColor' => $ink($q['eyeFrameColor'] ?? null),
            'eyeBallColor' => $ink($q['eyeBallColor'] ?? null),
            'bg' => $bg ?? '#ffffff',
            'gradient' => $gradient,
            'gradientOnEyes' => ! empty($q['gradientOnEyes']),
            'margin' => (int) $this->num($q['margin'] ?? 1, 0, 6, 0),
            'ecLevel' => $pick($q['ecLevel'] ?? null, ['auto', 'L', 'M', 'Q', 'H'], 'auto'),
            'eyes' => (object) $eyes,
        ];
    }

    private function stroke(mixed $s, ?array $default = null): ?array
    {
        if (! is_array($s)) {
            return $default;
        }
        $style = in_array($s['style'] ?? null, ['solid', 'dashed', 'dotted'], true) ? $s['style'] : null;
        $color = $this->color($s['color'] ?? null);
        if (! $style || ! $color) {
            return $default;
        }

        return ['color' => $color, 'width' => $this->num($s['width'] ?? 1, 0, 400, 1), 'style' => $style];
    }

    /** A drop shadow: offsets and blur in px, a colour and how strong it is (0–1). */
    private function shadow(mixed $s): ?array
    {
        if (! is_array($s) || ! ($color = $this->color($s['color'] ?? null))) {
            return null;
        }

        return [
            'x' => $this->num($s['x'] ?? 0, -2000, 2000, 1),
            'y' => $this->num($s['y'] ?? 0, -2000, 2000, 1),
            'blur' => $this->num($s['blur'] ?? 0, 0, 2000, 1),
            'color' => $color,
            'opacity' => $this->num($s['opacity'] ?? 0.35, 0, 1, 2),
        ];
    }

    private function color(mixed $c): ?string
    {
        if (! is_string($c)) {
            return null;
        }
        $c = strtolower(trim($c));
        if ($c === 'transparent') {
            return $c;
        }

        return preg_match('/^#([0-9a-f]{6}|[0-9a-f]{8})$/', $c) ? $c : null;
    }

    private function num(mixed $v, float $min, float $max, int $precision = 2): float
    {
        $n = is_numeric($v) ? (float) $v : $min;
        if (! is_finite($n)) {
            $n = $min;
        }

        return round(max($min, min($max, $n)), $precision);
    }

    private function text(mixed $v, int $max): string
    {
        $s = is_string($v) ? $v : '';
        // Control characters other than newline and tab never belong in a printed line.
        $s = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s);

        return mb_substr($s, 0, $max);
    }

    private function id(mixed $v): string
    {
        return is_string($v) && preg_match('/^[A-Za-z0-9_-]{1,40}$/', $v) ? $v : 'e'.bin2hex(random_bytes(5));
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['document' => $message]);
    }
}
