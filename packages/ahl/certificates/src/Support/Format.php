<?php

namespace Ahl\Certificates\Support;

/**
 * A kind of printed page the engine can design and draw: its size, the elements that
 * can sit on it, where they start, the view that draws it, and how small pages are laid
 * out on a sheet for printing. The certificate (A4) is one; an official's badge (A6) is
 * another. Everything else — the design room, artwork, erased areas, the organiser's
 * own texts, Arabic, contrast, one-PDF bundles — is shared by every format.
 *
 * Sizes are millimetres for the LANDSCAPE way up (long side across); portrait swaps them.
 */
final class Format
{
    /**
     * @param  array<string, array{0:string,1:float,2:float,3:string}>  $elements  key => [kind, base pt LTR, base pt RTL, align]
     * @param  \Closure(bool $placed, string $orientation): array  $defaults  the starting arrangement, unfinished
     * @param  array{landscape: array, portrait: array}  $customBox  where a new text element starts
     * @param  array{landscape: array, portrait: array}  $eraseBox  where a new erased area starts
     * @param  array{paper: string, cols: int, rows: int}|null  $sheet  several to an A4 sheet when printing, or one per page
     */
    public function __construct(
        public readonly string $key,
        public readonly int|float $long,
        public readonly int|float $short,
        public readonly string $defaultOrientation,
        public readonly array $elements,
        private readonly \Closure $defaults,
        public readonly array $customBox,
        public readonly array $eraseBox,
        public readonly string $view,
        public readonly ?array $sheet = null,
        public readonly string $paper = 'a4',
    ) {}

    /** @return array{0:int|float,1:int|float} width and height in mm, the page turned this way */
    public function page(string $orientation): array
    {
        return $orientation === 'portrait' ? [$this->short, $this->long] : [$this->long, $this->short];
    }

    /** The paper dompdf draws one page on: a named size, or this format's own in points. */
    public function paperFor(string $orientation): string|array
    {
        if ($this->paper !== 'custom') {
            return $this->paper;
        }
        [$w, $h] = $this->page('landscape');

        // Upright, in points: dompdf turns it round itself when asked for landscape.
        return [0, 0, $h * 72 / 25.4, $w * 72 / 25.4];
    }

    /**
     * This format on a page of another size (the design room's "Page size", 1.4): the
     * same elements and view, every starting box moved and sized in proportion — and
     * text scaled by the smaller of the two — so a design started on A5 or 4 × 6 in
     * opens laid out, not as an A4 arrangement spilling off the page. Drawn on a paper
     * of exactly that size; small cards still go several to an A4 sheet when they fit.
     */
    public function resized(float $long, float $short): self
    {
        [$long, $short] = [max($long, $short), min($long, $short)];
        if (abs($long - $this->long) < 0.05 && abs($short - $this->short) < 0.05) {
            return $this;
        }
        $fx = ['landscape' => $long / $this->long, 'portrait' => $short / $this->short];
        $fy = ['landscape' => $short / $this->short, 'portrait' => $long / $this->long];
        $box = function (array $b, string $o) use ($fx, $fy) {
            foreach (['x' => $fx[$o], 'w' => $fx[$o], 'y' => $fy[$o], 'h' => $fy[$o]] as $k => $f) {
                if (isset($b[$k]) && is_numeric($b[$k])) {
                    $b[$k] = round((float) $b[$k] * $f, 1);
                }
            }
            $b['scale'] = round((float) ($b['scale'] ?? 1.0) * min($fx[$o], $fy[$o]), 3);

            return $b;
        };
        $base = $this->defaults;
        $scaledBoxes = fn (array $pair) => ['landscape' => $box($pair['landscape'], 'landscape'), 'portrait' => $box($pair['portrait'], 'portrait')];

        // Several to an A4 sheet when at least two fit (the badge's way); otherwise one per page.
        $sheet = null;
        if ($this->sheet) {
            $fits = fn ($w, $h) => max(0, (int) floor(210 / $w)) * max(0, (int) floor(297 / $h));
            if (max($fits($short, $long), $fits($long, $short)) >= 2) {
                $sheet = ['paper' => $this->sheet['paper'] ?? 'a4', 'fit' => true, 'cols' => 0, 'rows' => 0];
            }
        }

        return new self(
            key: $this->key,
            long: round($long, 1),
            short: round($short, 1),
            defaultOrientation: $this->defaultOrientation,
            elements: $this->elements,
            defaults: function (bool $placed, string $orientation) use ($base, $box) {
                $o = $orientation === 'portrait' ? 'portrait' : 'landscape';

                return array_map(fn ($b) => is_array($b) ? $box($b, $o) : $b, ($base)($placed, $orientation));
            },
            customBox: $scaledBoxes($this->customBox),
            eraseBox: $scaledBoxes($this->eraseBox),
            view: $this->view,
            sheet: $sheet,
            paper: 'custom',
        );
    }

    public function defaults(bool $placed, string $orientation): array
    {
        $d = ($this->defaults)($placed, $orientation);
        foreach ($d as $k => $box) {
            $d[$k] += ['scale' => 1.0, 'color' => null, 'align' => $this->elements[$k][3] ?? 'center', 'hidden' => false];
        }

        return $d;
    }

    public function customDefault(string $orientation): array
    {
        return $this->customBox[$orientation === 'portrait' ? 'portrait' : 'landscape'] + ['scale' => 1.0, 'color' => null, 'align' => 'center', 'hidden' => false];
    }

    public function eraseDefault(string $orientation): array
    {
        return $this->eraseBox[$orientation === 'portrait' ? 'portrait' : 'landscape'] + ['scale' => 1.0, 'color' => null, 'align' => 'center', 'hidden' => false];
    }
}
