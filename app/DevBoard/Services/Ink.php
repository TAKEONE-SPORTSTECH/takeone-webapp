<?php

namespace App\DevBoard\Services;

use App\DevBoard\Models\Asset;
use App\DevBoard\Models\Card;
use App\Members\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Drawings: a sketch on a blank page, or marks on a photo.
 *
 * What the note SHOWS is a picture — the drawing as the phone rendered it,
 * stored through Pictures (real bytes checked, re-encoded to WebP) like any
 * other. Beside it the strokes are kept, so the drawing can be opened again
 * and changed; and a marked-up photo remembers its original (`base`), so marks
 * are never burnt into the only copy.
 *
 * The strokes are never rendered by the server or put into HTML, but they are
 * stored and handed back, so they are validated strictly: known types only,
 * hex colours, numbers inside the page, and hard caps on how much one drawing
 * may carry.
 */
class Ink
{
    public const TYPES = ['pen', 'marker', 'arrow', 'rect', 'ellipse', 'text', 'bubble', 'step', 'blur'];

    /** How an arrow is drawn. */
    public const ARROWS = ['line', 'dash', 'double', 'curve', 'block'];

    /** How many points each kind of object is made of (null = any). */
    private const POINTS = ['arrow' => [2, 3], 'rect' => [2, 2], 'ellipse' => [2, 2], 'text' => [1, 1], 'bubble' => [3, 3], 'step' => [1, 1], 'blur' => [2, 2]];

    public function __construct(private Pictures $pictures) {}

    /**
     * @param  array  $ink  ['kind' => sketch|markup, 'w', 'h', 'strokes' => [...], 'base' => ?uuid]
     */
    public function store(Card $card, User $by, UploadedFile $rendered, array $ink): ?Asset
    {
        $asset = $this->pictures->store($card, $by, $rendered);
        if (! $asset) {
            return null;
        }
        $asset->forceFill(['meta' => ['ink' => $ink]])->save();

        return $asset;
    }

    /**
     * The strokes as sent, made safe — or null when anything is out of shape.
     *
     * @return array<int,array>|null
     */
    public static function clean(mixed $strokes, int $w, int $h): ?array
    {
        if (! is_array($strokes) || count($strokes) > (int) config('devboard.limits.ink_strokes', 3000)) {
            return null;
        }

        $perStroke = (int) config('devboard.limits.stroke_points', 5000);
        $budget = (int) config('devboard.limits.ink_points', 60000);
        $out = [];

        foreach ($strokes as $s) {
            if (! is_array($s) || ! in_array($s['t'] ?? null, self::TYPES, true)) {
                return null;
            }
            $colour = strtolower((string) ($s['c'] ?? ''));
            $width = $s['w'] ?? null;
            if (! preg_match('/^#[0-9a-f]{6}$/', $colour) || ! is_numeric($width) || $width < 0.5 || $width > 160) {
                return null;
            }

            $points = $s['p'] ?? null;
            if (! is_array($points) || ! $points || count($points) > $perStroke) {
                return null;
            }
            if (isset(self::POINTS[$s['t']]) && (count($points) < self::POINTS[$s['t']][0] || count($points) > self::POINTS[$s['t']][1])) {
                return null;
            }
            $budget -= count($points);
            if ($budget < 0) {
                return null;
            }

            $clean = [];
            foreach ($points as $pt) {
                if (! is_array($pt) || ! array_is_list($pt) || count($pt) < 2 || count($pt) > 3) {
                    return null;
                }
                foreach ($pt as $v) {
                    if (! is_int($v) && ! is_float($v)) {
                        return null;
                    }
                }
                [$x, $y] = $pt;
                if ($x < -$w || $x > 2 * $w || $y < -$h || $y > 2 * $h) {
                    return null;
                }
                $p = [round($x, 1), round($y, 1)];
                if (isset($pt[2])) {
                    $p[] = round(max(0, min(1, $pt[2])), 2);
                }
                $clean[] = $p;
            }

            $stroke = ['t' => $s['t'], 'c' => $colour, 'w' => round((float) $width, 1), 'p' => $clean];
            if (isset($s['r'])) { // rotation, radians
                if (! is_int($s['r']) && ! is_float($s['r']) || abs($s['r']) > 7) {
                    return null;
                }
                $stroke['r'] = round((float) $s['r'], 4);
            }
            if ($s['t'] === 'arrow') {
                $style = $s['s'] ?? 'line';
                if (! in_array($style, self::ARROWS, true)) {
                    return null;
                }
                $stroke['s'] = $style;
            }
            if ($s['t'] === 'step') {
                if (! is_int($s['n'] ?? null) || $s['n'] < 1 || $s['n'] > 999) {
                    return null;
                }
                $stroke['n'] = $s['n'];
            }
            if ($s['t'] === 'bubble') {
                $text = trim(preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/u', ' ', (string) ($s['x'] ?? '')));
                if (mb_strlen($text) > 600) {
                    return null;
                }
                $stroke['x'] = $text;
            }
            if ($s['t'] === 'text') {
                $text = trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string) ($s['x'] ?? '')));
                if ($text === '' || mb_strlen($text) > 300) {
                    return null;
                }
                $stroke['x'] = $text;
            }
            $out[] = $stroke;
        }

        return $out;
    }
}
