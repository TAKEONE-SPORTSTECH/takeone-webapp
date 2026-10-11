<?php

namespace Ahl\Certificates\Support;

use GdImage;

/**
 * Draws the built-in certificate backgrounds as PNG, one A4 page — landscape, or the
 * same drawing stood upright for a portrait page.
 *
 * Why images and not CSS: dompdf has no gradients, no blend modes and no repeating
 * patterns, so a security pattern or a watermark cannot be drawn by the view. GD draws
 * it once, and the result is cached by its inputs (mode, accent, watermark bytes), so
 * a thousand certificates for one event share one file.
 *
 * Every background is quiet by design: the name and the text sit on top, and nothing
 * here may compete with them for contrast.
 */
class Backgrounds
{
    /** Bump when a drawing changes, so cached pages are redrawn. */
    public const VERSION = 2;

    public const WIDTH = 1403;   // 297 mm at 120 dpi

    public const HEIGHT = 992;   // 210 mm at 120 dpi

    public const PAPER = [251, 248, 242];

    /** The page being drawn, in px (WIDTH × HEIGHT, or the other way up for portrait). */
    private int $w = self::WIDTH;

    private int $h = self::HEIGHT;

    public function __construct(private ?string $cacheDir = null) {}

    /**
     * @param  string  $mode  plain | guilloche | watermark | motif | paper
     * @param  string|null  $watermark  image bytes (PNG/JPEG/WebP) for the watermark mode
     */
    public function png(string $mode, string $accent, ?string $watermark = null, string $orientation = 'landscape'): string
    {
        $portrait = Layout::orientation($orientation) === 'portrait';
        [$this->w, $this->h] = $portrait ? [self::HEIGHT, self::WIDTH] : [self::WIDTH, self::HEIGHT];
        // Landscape keeps its old cache key, so the pages already drawn are still found.
        $key = sha1(implode('|', [self::VERSION, $mode, strtolower($accent), $watermark !== null ? sha1($watermark) : '-', ...($portrait ? ['portrait'] : [])]));
        $file = $this->cacheDir ? rtrim($this->cacheDir, '/').'/bg-'.$key.'.png' : null;

        if ($file && is_file($file) && ($cached = @file_get_contents($file)) !== false) {
            return $cached;
        }

        $png = $this->draw($mode, $this->rgb($accent), $watermark);

        if ($file) {
            if (! is_dir(dirname($file))) {
                @mkdir(dirname($file), 0775, true);
            }
            @file_put_contents($file, $png, LOCK_EX);
        }

        return $png;
    }

    public function dataUri(string $mode, string $accent, ?string $watermark = null, string $orientation = 'landscape'): string
    {
        return 'data:image/png;base64,'.base64_encode($this->png($mode, $accent, $watermark, $orientation));
    }

    private function draw(string $mode, array $accent, ?string $watermark): string
    {
        $im = imagecreatetruecolor($this->w, $this->h);
        imagealphablending($im, true);
        imagefill($im, 0, 0, imagecolorallocate($im, ...self::PAPER));

        match ($mode) {
            'guilloche' => $this->guilloche($im, $accent),
            'watermark' => $this->watermark($im, $accent, $watermark),
            'motif' => $this->motif($im, $accent),
            'paper' => null,   // the paper itself, nothing drawn on it
            default => $this->plain($im, $accent),
        };

        ob_start();
        imagepng($im, null, 6);
        $png = (string) ob_get_clean();
        imagedestroy($im);

        return $png;
    }

    /** Two faint rings behind the name — the classic sheet. */
    private function plain(GdImage $im, array $c): void
    {
        [$cx, $cy] = [$this->w / 2, $this->h / 2 + 10];
        $this->ring($im, $cx, $cy, 780, 2, $this->alpha($im, $c, 116));
        $this->ring($im, $cx, $cy, 650, 1, $this->alpha($im, $c, 118));
    }

    /**
     * A banknote-style pattern: two families of interlaced waves across the page, in
     * the accent and in gold, fading out behind the text so the name stays clean.
     */
    private function guilloche(GdImage $im, array $c): void
    {
        $gold = [184, 137, 43];
        imagesetthickness($im, 1);

        for ($k = 0, $rows = $this->w >= $this->h ? 34 : (int) ceil($this->h / 29); $k < $rows; $k++) {
            $y0 = 20 + $k * 29;
            $this->wave($im, $y0, 18, 140, $k * 0.55, $this->alpha($im, $c, 112));
            $this->wave($im, $y0 + 14, 14, 110, -$k * 0.42, $this->alpha($im, $gold, 114));
        }

        // Rosettes in the four corners, the mark of a document meant to be hard to copy.
        foreach ([[70, 70], [$this->w - 70, 70], [70, $this->h - 70], [$this->w - 70, $this->h - 70]] as [$x, $y]) {
            $this->rosette($im, $x, $y, 120, $this->alpha($im, $c, 100));
        }

        $this->clearing($im, $this->w / 2, $this->h / 2 + 20, ...($this->w >= $this->h ? [980, 640] : [700, 1000]));
    }

    /** The owner's logo, very faint, in the middle — with rings when there is no logo. */
    private function watermark(GdImage $im, array $c, ?string $bytes): void
    {
        [$cx, $cy] = [$this->w / 2, $this->h / 2 + 10];
        $this->ring($im, $cx, $cy, 640, 18, $this->alpha($im, $c, 121));
        $this->ring($im, $cx, $cy, 560, 3, $this->alpha($im, $c, 121));

        $logo = $bytes !== null ? @imagecreatefromstring($bytes) : false;
        if (! $logo instanceof GdImage) {
            return;
        }

        $size = 440;
        $w = imagesx($logo);
        $h = imagesy($logo);
        $scale = min($size / max(1, $w), $size / max(1, $h));
        [$tw, $th] = [max(1, (int) ($w * $scale)), max(1, (int) ($h * $scale))];

        $tile = imagecreatetruecolor($tw, $th);
        imagealphablending($tile, false);
        imagesavealpha($tile, true);
        imagefill($tile, 0, 0, imagecolorallocatealpha($tile, 0, 0, 0, 127));
        imagecopyresampled($tile, $logo, 0, 0, 0, 0, $tw, $th, $w, $h);
        imagedestroy($logo);

        // Fade every pixel to ~7% of its own opacity. GD has no layer opacity, so this
        // walks the (small) tile once; imagecopymerge would flatten the transparency.
        for ($y = 0; $y < $th; $y++) {
            for ($x = 0; $x < $tw; $x++) {
                $rgba = imagecolorat($tile, $x, $y);
                $a = ($rgba >> 24) & 0x7F;
                $opacity = (127 - $a) / 127 * 0.07;
                imagesetpixel($tile, $x, $y, imagecolorallocatealpha($tile, ($rgba >> 16) & 0xFF, ($rgba >> 8) & 0xFF, $rgba & 0xFF, (int) round(127 - $opacity * 127)));
            }
        }

        imagealphablending($im, true);
        imagecopy($im, $tile, (int) ($cx - $tw / 2), (int) ($cy - $th / 2), 0, 0, $tw, $th);
        imagedestroy($tile);
    }

    /**
     * A brush circle (ensō) in the trailing top corner, a quiet ring in the leading one,
     * and a band of hairlines along the foot. Kept clear of the name and the text.
     */
    private function motif(GdImage $im, array $c): void
    {
        $ink = [31, 26, 46];
        [$cx, $cy] = [$this->w - 170, 190];

        // A brush stroke thickens and thins: arcs of a slowly growing radius, drawn
        // thick and very faint, overlap into one stroke with a tapering tail.
        imagesetthickness($im, 3);
        for ($i = 0; $i < 26; $i++) {
            $d = 330 + $i * 2;
            imagearc($im, (int) $cx, (int) $cy, $d, $d, 120 + $i, 420 - (int) ($i * 2.6), $this->alpha($im, $ink, 122));
        }
        imagesetthickness($im, 1);

        for ($x = 0; $x < $this->w; $x += 16) {
            imagefilledrectangle($im, $x, $this->h - 96, $x + 1, $this->h, $this->alpha($im, $ink, 122));
        }

        $this->ring($im, 190, 190, 250, 6, $this->alpha($im, $c, 118));
        $this->ring($im, 190, 190, 210, 1, $this->alpha($im, $c, 116));
    }

    private function wave(GdImage $im, float $y0, float $amp, float $len, float $phase, int $color): void
    {
        $prev = null;
        for ($x = 0; $x <= $this->w; $x += 4) {
            $y = $y0 + $amp * sin(2 * M_PI * $x / $len + $phase) + ($amp / 3) * sin(2 * M_PI * $x / ($len * 2.7));
            if ($prev) {
                imageline($im, (int) $prev[0], (int) $prev[1], $x, (int) $y, $color);
            }
            $prev = [$x, $y];
        }
    }

    private function rosette(GdImage $im, float $cx, float $cy, float $r, int $color): void
    {
        $prev = null;
        for ($i = 0; $i <= 720; $i++) {
            $t = $i / 720 * 2 * M_PI;
            $rr = $r * (0.55 + 0.45 * cos(7 * $t));
            [$x, $y] = [$cx + $rr * cos($t), $cy + $rr * sin($t)];
            if ($prev) {
                imageline($im, (int) $prev[0], (int) $prev[1], (int) $x, (int) $y, $color);
            }
            $prev = [$x, $y];
        }
    }

    /** Paper coloured ellipses of rising opacity: the pattern fades out under the text. */
    private function clearing(GdImage $im, float $cx, float $cy, int $w, int $h): void
    {
        for ($i = 0; $i < 18; $i++) {
            $f = 1 - $i / 18;
            imagefilledellipse($im, (int) $cx, (int) $cy, (int) ($w * $f + 120), (int) ($h * $f + 80), imagecolorallocatealpha($im, ...[...self::PAPER, 112]));
        }
        imagefilledellipse($im, (int) $cx, (int) $cy, (int) ($w * 0.72), (int) ($h * 0.72), imagecolorallocate($im, ...self::PAPER));
    }

    private function ring(GdImage $im, float $cx, float $cy, int $d, int $thickness, int $color): void
    {
        imagesetthickness($im, 1);
        for ($i = 0; $i < $thickness; $i++) {
            imageellipse($im, (int) $cx, (int) $cy, $d + $i * 2, $d + $i * 2, $color);
        }
    }

    private function alpha(GdImage $im, array $rgb, int $alpha): int
    {
        return imagecolorallocatealpha($im, $rgb[0], $rgb[1], $rgb[2], $alpha);
    }

    /** @return array{0:int,1:int,2:int} */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            $hex = '1f4e79';
        }

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
