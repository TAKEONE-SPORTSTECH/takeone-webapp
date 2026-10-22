<?php

namespace App\Printables\Support;

/**
 * The page sizes a design may take — config('printables.formats'), plus a
 * custom size inside config('printables.custom'). Sizes are millimetres; the
 * document itself is in CSS pixels at 96 dpi.
 */
final class Formats
{
    public const CUSTOM = 'custom';

    public const PX_PER_MM = 96 / 25.4;

    /** @return array<string, array{label: string, group: string, w: float, h: float}> */
    public static function all(): array
    {
        $out = [];
        foreach ((array) config('printables.formats', []) as $key => $f) {
            if (is_string($key) && isset($f['w'], $f['h'])) {
                $out[$key] = [
                    'label' => (string) ($f['label'] ?? $key),
                    'group' => (string) ($f['group'] ?? 'paper'),
                    'w' => (float) min($f['w'], $f['h']),
                    'h' => (float) max($f['w'], $f['h']),
                ];
            }
        }

        return $out;
    }

    /**
     * The formats a kind may use, for the size picker. An empty allow-list means
     * every format AND a custom size.
     *
     * @param  list<string>  $allowed
     * @return array{formats: array<string, array>, custom: array{min: float, max: float}|null}
     */
    public static function offered(array $allowed): array
    {
        $all = self::all();
        $custom = in_array(self::CUSTOM, $allowed, true) || $allowed === [];

        return [
            'formats' => $allowed === [] ? $all : array_intersect_key($all, array_flip($allowed)),
            'custom' => $custom ? self::customRange() : null,
        ];
    }

    /** @return array{min: float, max: float} */
    public static function customRange(): array
    {
        $c = (array) config('printables.custom', []);

        return ['min' => (float) ($c['min'] ?? 20), 'max' => (float) ($c['max'] ?? 1200)];
    }

    /**
     * Width and height in mm for a format turned to $orientation, or for a custom
     * size. Null when the request names something not offered.
     *
     * @param  list<string>  $allowed
     * @return array{0: float, 1: float}|null
     */
    public static function resolve(string $format, string $orientation, ?float $w, ?float $h, array $allowed): ?array
    {
        $offered = self::offered($allowed);
        if ($format === self::CUSTOM) {
            $range = $offered['custom'];
            if (! $range || $w === null || $h === null) {
                return null;
            }
            foreach ([$w, $h] as $v) {
                if ($v < $range['min'] || $v > $range['max']) {
                    return null;
                }
            }

            return [round($w, 2), round($h, 2)];
        }
        $f = $offered['formats'][$format] ?? null;
        if (! $f) {
            return null;
        }

        return $orientation === 'landscape' ? [$f['h'], $f['w']] : [$f['w'], $f['h']];
    }

    public static function px(float $mm): float
    {
        return round($mm * self::PX_PER_MM, 2);
    }
}
