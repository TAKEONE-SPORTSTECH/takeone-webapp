<?php

namespace Ahl\Certificates\Support;

/**
 * Where each element actually landed on the last page drawn — its INK, not its box
 * (a short name in a wide box is a small rectangle in the middle). Filled by the view
 * as it draws, read by an editor so its handles hug what is really there.
 * Millimetres, like Support\Layout. One render at a time (per request).
 */
class Drawn
{
    /** @var array<string, array{x:float,y:float,w:float,h:float}> */
    private static array $rects = [];

    public static function reset(): void
    {
        self::$rects = [];
    }

    public static function put(string $key, float $x, float $y, float $w, float $h): void
    {
        self::$rects[$key] = ['x' => round($x, 2), 'y' => round($y, 2), 'w' => round(max(1, $w), 2), 'h' => round(max(1, $h), 2)];
    }

    /** @return array<string, array{x:float,y:float,w:float,h:float}> */
    public static function all(): array
    {
        return self::$rects;
    }
}
