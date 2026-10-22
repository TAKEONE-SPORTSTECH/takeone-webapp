<?php

namespace App\Printables\Support;

/** The typefaces the designer offers — config('printables.fonts'), files in public/fonts/designer. */
final class Fonts
{
    public const DEFAULT = 'Inter';

    /** @return list<array{family: string, group: string, faces: list<array{url: string, weight: string, style: string}>}> */
    public static function forClient(): array
    {
        $out = [];
        foreach ((array) config('printables.fonts', []) as $f) {
            $faces = [];
            foreach ((array) ($f['faces'] ?? []) as $face) {
                $file = (string) ($face['file'] ?? '');
                // A config typo must never become a path outside the font folder.
                if (! preg_match('#^[a-z0-9-]+/[A-Za-z0-9_-]+\.(ttf|otf|woff2?)$#', $file)) {
                    continue;
                }
                $faces[] = [
                    'url' => asset('fonts/designer/'.$file),
                    'weight' => (string) ($face['weight'] ?? '400'),
                    'style' => (string) ($face['style'] ?? 'normal'),
                ];
            }
            if ($faces) {
                $out[] = ['family' => (string) $f['family'], 'group' => (string) ($f['group'] ?? 'sans'), 'faces' => $faces];
            }
        }

        return $out;
    }

    /** The same list with file:// addresses, for the printer (it may not fetch over the network). */
    public static function forFiles(): array
    {
        $base = asset('fonts/designer/');

        return array_map(fn ($f) => ['faces' => array_map(
            fn ($face) => ['url' => 'file://'.public_path('fonts/designer/'.ltrim(substr($face['url'], strlen($base)), '/'))] + $face,
            $f['faces'],
        )] + $f, self::forClient());
    }

    /** @return list<string> */
    public static function families(): array
    {
        return array_values(array_filter(array_map(fn ($f) => (string) ($f['family'] ?? ''), (array) config('printables.fonts', []))));
    }
}
