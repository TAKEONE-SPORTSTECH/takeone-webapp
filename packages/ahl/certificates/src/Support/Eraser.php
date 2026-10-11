<?php

namespace Ahl\Certificates\Support;

/**
 * Paints out areas of the page's picture: whatever is printed inside an erased box —
 * wording, a logo, a date line baked into the organiser's artwork — is replaced by the
 * colours around it, so it can be "removed" like any element the engine draws itself.
 *
 * Each area is filled with a Coons patch stretched between its four edges: every pixel
 * blends the colour just outside the box on the left and right, above and below. On a
 * smooth or smoky ground that reads as the ground carrying on. The edges are sampled
 * from a thin band outside the box and smoothed along their length, so a stroke of the
 * old text crossing the edge does not drag a streak across the fill. The patch is
 * worked out on a quarter-size grid and scaled up, which keeps it smooth and cheap.
 */
class Eraser
{
    /**
     * @param  array{contents:string, mime:string}  $bg  the page's picture, covering the whole sheet
     * @param  list<array{x:float,y:float,w:float,h:float}>  $areas  in millimetres on the page
     * @return array{contents:string, mime:string}
     */
    public static function apply(array $bg, array $areas, float $pageW, float $pageH, ?string $cacheDir = null): array
    {
        if ($areas === []) {
            return $bg;
        }
        $file = $cacheDir ? rtrim($cacheDir, '/').'/erase-'.sha1($bg['contents'].json_encode($areas)).'.jpg' : null;
        if ($file && is_file($file) && ($hit = @file_get_contents($file)) !== false) {
            return ['contents' => $hit, 'mime' => 'image/jpeg'];
        }
        $im = @imagecreatefromstring($bg['contents']);
        if (! $im instanceof \GdImage) {
            return $bg;
        }
        if (! imageistruecolor($im)) {
            imagepalettetotruecolor($im);
        }
        $W = imagesx($im);
        $H = imagesy($im);
        $sx = $W / $pageW;
        $sy = $H / $pageH;

        foreach ($areas as $a) {
            self::fill($im, $W, $H,
                (int) floor($a['x'] * $sx), (int) floor($a['y'] * $sy),
                (int) ceil(($a['x'] + $a['w']) * $sx) - 1, (int) ceil(($a['y'] + $a['h']) * $sy) - 1,
                max(2, (int) round(1.2 * $sx)));
        }

        ob_start();
        imagejpeg($im, null, 90);
        $bytes = (string) ob_get_clean();
        imagedestroy($im);
        if ($file) {
            @is_dir(dirname($file)) || @mkdir(dirname($file), 0775, true);
            @file_put_contents($file, $bytes, LOCK_EX);
        }

        return ['contents' => $bytes, 'mime' => 'image/jpeg'];
    }

    private static function fill(\GdImage $im, int $W, int $H, int $x0, int $y0, int $x1, int $y1, int $depth): void
    {
        $x0 = max(0, $x0);
        $y0 = max(0, $y0);
        $x1 = min($W - 1, $x1);
        $y1 = min($H - 1, $y1);
        $w = $x1 - $x0 + 1;
        $h = $y1 - $y0 + 1;
        if ($w < 2 || $h < 2 || ($w >= $W && $h >= $H)) {
            return;
        }
        $gw = max(2, (int) ceil($w / 4));
        $gh = max(2, (int) ceil($h / 4));

        // The four edges, one colour per grid step. A box against the sheet's edge has
        // nothing outside it there, so that side borrows from the opposite one.
        $top = $y0 > 0 ? self::row($im, $x0, $w, $gw, $y0 - 1, -1, $depth, $H) : null;
        $bottom = $y1 < $H - 1 ? self::row($im, $x0, $w, $gw, $y1 + 1, 1, $depth, $H) : null;
        $left = $x0 > 0 ? self::col($im, $y0, $h, $gh, $x0 - 1, -1, $depth, $W) : null;
        $right = $x1 < $W - 1 ? self::col($im, $y0, $h, $gh, $x1 + 1, 1, $depth, $W) : null;
        $top ??= $bottom;
        $bottom ??= $top;
        $left ??= $right;
        $right ??= $left;
        if ($top === null && $left === null) {
            return;
        }
        if ($top === null) {   // only left and right are known: the sides set the rows
            $top = $bottom = array_fill(0, $gw, null);
        }
        if ($left === null) {
            $left = $right = array_fill(0, $gh, null);
        }

        $patch = imagecreatetruecolor($gw, $gh);
        $lastU = $gw - 1;
        $lastV = $gh - 1;
        $c = fn (?array $a, ?array $b) => $a === null ? $b : ($b === null ? $a : [($a[0] + $b[0]) / 2, ($a[1] + $b[1]) / 2, ($a[2] + $b[2]) / 2]);
        $c00 = $c($top[0], $left[0]);
        $c10 = $c($top[$lastU], $right[0]);
        $c01 = $c($bottom[0], $left[$lastV]);
        $c11 = $c($bottom[$lastU], $right[$lastV]);

        for ($j = 0; $j < $gh; $j++) {
            $v = $j / $lastV;
            for ($i = 0; $i < $gw; $i++) {
                $u = $i / $lastU;
                $rgb = [0, 0, 0];
                for ($k = 0; $k < 3; $k++) {
                    $vert = $top[$i] === null ? null : (1 - $v) * $top[$i][$k] + $v * $bottom[$i][$k];
                    $horz = $left[$j] === null ? null : (1 - $u) * $left[$j][$k] + $u * $right[$j][$k];
                    if ($vert === null || $horz === null) {
                        $val = $vert ?? $horz;
                    } else {
                        $corner = (1 - $u) * (1 - $v) * $c00[$k] + $u * (1 - $v) * $c10[$k] + (1 - $u) * $v * $c01[$k] + $u * $v * $c11[$k];
                        $val = $vert + $horz - $corner;
                    }
                    $rgb[$k] = (int) max(0, min(255, round($val)));
                }
                imagesetpixel($patch, $i, $j, ($rgb[0] << 16) | ($rgb[1] << 8) | $rgb[2]);
            }
        }

        imagecopyresampled($im, $patch, $x0, $y0, 0, 0, $w, $h, $gw, $gh);
        imagedestroy($patch);
    }

    /** One colour per grid column, from a band of $depth rows starting at $y and running away from the box. */
    private static function row(\GdImage $im, int $x0, int $w, int $n, int $y, int $dir, int $depth, int $H): array
    {
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $a = $x0 + (int) floor($i * $w / $n);
            $b = max($a, $x0 + (int) floor(($i + 1) * $w / $n) - 1);
            $out[] = self::mean($im, $a, $b, min($y, $y + $dir * ($depth - 1)), max($y, $y + $dir * ($depth - 1)), $H, true);
        }

        return self::smooth($out);
    }

    private static function col(\GdImage $im, int $y0, int $h, int $n, int $x, int $dir, int $depth, int $W): array
    {
        $out = [];
        for ($j = 0; $j < $n; $j++) {
            $a = $y0 + (int) floor($j * $h / $n);
            $b = max($a, $y0 + (int) floor(($j + 1) * $h / $n) - 1);
            $out[] = self::mean($im, min($x, $x + $dir * ($depth - 1)), max($x, $x + $dir * ($depth - 1)), $a, $b, $W, false);
        }

        return self::smooth($out);
    }

    /** The average colour of a block, clipped to the picture; sparse sampling keeps a big block cheap. */
    private static function mean(\GdImage $im, int $xa, int $xb, int $ya, int $yb, int $limit, bool $vertical): array
    {
        if ($vertical) {
            $ya = max(0, $ya);
            $yb = min($limit - 1, $yb);
        } else {
            $xa = max(0, $xa);
            $xb = min($limit - 1, $xb);
        }
        $step = max(1, (int) floor(max($xb - $xa, $yb - $ya) / 6));
        $sum = [0, 0, 0];
        $n = 0;
        for ($y = $ya; $y <= $yb; $y += $step) {
            for ($x = $xa; $x <= $xb; $x += $step) {
                $p = imagecolorat($im, $x, $y);
                $sum[0] += ($p >> 16) & 255;
                $sum[1] += ($p >> 8) & 255;
                $sum[2] += $p & 255;
                $n++;
            }
        }

        return $n ? [$sum[0] / $n, $sum[1] / $n, $sum[2] / $n] : [255, 255, 255];
    }

    /** A running average along the edge, so one dark stroke crossing it is spread thin. */
    private static function smooth(array $edge): array
    {
        $n = count($edge);
        $r = max(1, (int) round($n / 25));
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $sum = [0, 0, 0];
            $c = 0;
            for ($k = max(0, $i - $r); $k <= min($n - 1, $i + $r); $k++) {
                $sum[0] += $edge[$k][0];
                $sum[1] += $edge[$k][1];
                $sum[2] += $edge[$k][2];
                $c++;
            }
            $out[] = [$sum[0] / $c, $sum[1] / $c, $sum[2] / $c];
        }

        return $out;
    }
}
