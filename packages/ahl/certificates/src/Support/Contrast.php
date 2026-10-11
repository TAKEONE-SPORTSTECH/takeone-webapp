<?php

namespace Ahl\Certificates\Support;

use GdImage;

/**
 * Picks ink that can be read against whatever is behind it — a built-in pattern or an
 * organiser's own artwork, which may be anything from pale paper to a dark photograph.
 *
 * The background is sampled under each element's box (its average colour), and an
 * element's intended colour is kept when it reaches the WCAG contrast a reader needs
 * (4.5:1 for small type, 3:1 for large); otherwise it switches to the dark or the light
 * ink, whichever stands out more. A colour the organiser chose by hand is never changed.
 */
class Contrast
{
    public const DARK = '#1f1a2e';

    public const LIGHT = '#ffffff';

    private ?GdImage $image = null;

    /** @param string|null $background raw image bytes (null = plain paper) */
    public function __construct(?string $background, private string $paper = '#fbf8f2', private string $orientation = 'landscape')
    {
        if ($background !== null) {
            $im = @imagecreatefromstring($background);
            $this->image = $im instanceof GdImage ? $im : null;
        }
    }

    public function __destruct()
    {
        if ($this->image) {
            imagedestroy($this->image);
        }
    }

    /** The average colour behind a box given in millimetres. */
    public function behind(float $x, float $y, float $w, float $h): string
    {
        if (! $this->image) {
            return $this->paper;
        }
        $iw = imagesx($this->image);
        $ih = imagesy($this->image);
        [$pageW, $pageH] = Layout::page($this->orientation);
        $sx = $iw / $pageW;
        $sy = $ih / $pageH;
        $px = (int) max(0, min($iw - 1, $x * $sx));
        $py = (int) max(0, min($ih - 1, $y * $sy));
        $pw = (int) max(1, min($iw - $px, $w * $sx));
        $ph = (int) max(1, min($ih - $py, $h * $sy));

        $one = imagecreatetruecolor(1, 1);
        imagecopyresampled($one, $this->image, 0, 0, $px, $py, 1, 1, $pw, $ph);
        $rgb = imagecolorat($one, 0, 0);
        imagedestroy($one);

        return sprintf('#%02x%02x%02x', ($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF);
    }

    /**
     * The colours behind a box, cell by cell (a grid of averages). A photograph is rarely
     * one colour: an ink can read against the average and vanish over its brightest or
     * darkest part, so readability is judged against the worst cell, not the mean.
     *
     * @return list<string>
     */
    public function cells(float $x, float $y, float $w, float $h, int $cols = 8, int $rows = 3): array
    {
        if (! $this->image) {
            return [$this->paper];
        }
        $out = [];
        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $out[] = $this->behind($x + $w * $c / $cols, $y + $h * $r / $rows, $w / $cols, $h / $rows);
            }
        }

        return $out;
    }

    /** The lowest contrast $ink makes against any of $cells. */
    public static function worst(string $ink, array $cells): float
    {
        return min(array_map(fn ($c) => self::ratio($ink, $c), $cells ?: ['#ffffff']));
    }

    /** $wanted if it reads against $behind, otherwise the better of dark and light ink. */
    public static function ink(string $wanted, string $behind, bool $large = false): string
    {
        $need = $large ? 3.0 : 4.5;
        if (self::ratio($wanted, $behind) >= $need) {
            return $wanted;
        }

        return self::ratio(self::DARK, $behind) >= self::ratio(self::LIGHT, $behind) ? self::DARK : self::LIGHT;
    }

    public static function ratio(string $a, string $b): float
    {
        [$la, $lb] = [self::luminance($a), self::luminance($b)];

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    public static function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $c = array_map(function ($i) use ($hex) {
            $v = hexdec(substr($hex, $i, 2)) / 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, [0, 2, 4]);

        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }
}
