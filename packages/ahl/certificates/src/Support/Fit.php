<?php

namespace Ahl\Certificates\Support;

/**
 * The type size at which one line fits its box. A name or a title is never cut short
 * or run off the page: when it is longer than its box, it is set smaller — down to a
 * floor, below which it would stop reading as a name.
 */
class Fit
{
    /** @param float $tracking letter-spacing in pt */
    public static function size(string $text, string $fontFile, float $pt, float $widthMm, float $tracking = 0, float $floor = 0.5): float
    {
        if ($text === '' || ! is_file($fontFile) || ! function_exists('imagettfbbox')) {
            return $pt;
        }
        $box = @imagettfbbox($pt, 0, $fontFile, $text);
        if (! $box) {
            return $pt;
        }
        // GD measures in pixels at 96 dpi for a size given in points.
        $widthPt = (abs($box[2] - $box[0]) * 72 / 96) + $tracking * mb_strlen($text);
        $availPt = $widthMm * 72 / 25.4;

        return $widthPt <= $availPt ? $pt : max($pt * $floor, $pt * $availPt / $widthPt);
    }

    /** How wide a line is, in millimetres, at a given size. */
    public static function widthMm(string $text, string $fontFile, float $pt, float $tracking = 0): float
    {
        if ($text === '' || ! is_file($fontFile) || ! function_exists('imagettfbbox')) {
            return 0;
        }
        $box = @imagettfbbox($pt, 0, $fontFile, $text);

        return $box ? ((abs($box[2] - $box[0]) * 72 / 96) + $tracking * mb_strlen($text)) * 25.4 / 72 : 0;
    }
}
