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
