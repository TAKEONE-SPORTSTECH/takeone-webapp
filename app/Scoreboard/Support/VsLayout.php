<?php

namespace App\Scoreboard\Support;

use App\Platform\Models\PlatformSetting;

/**
 * How the Karate mat's VS screen places each competitor's details (badge,
 * country, name, club) over their photo — set once for the platform by a
 * super-admin from the console's Settings → VS screen (owner, 2026-10-25:
 * "control the height of the player's information so it doesn't cover the
 * face").
 *
 *   info_bottom  the block's distance from the bottom of the screen, in the
 *                VS screen's own 1920x1080 pixels (it was always 64.8)
 *   info_scale   the block's size; 1 is the size it was designed at
 *   show_stats   1 shows the age · height · weight chip, 0 hides it
 *
 * Out-of-range or missing values fall back to those originals, so an unset
 * platform draws the VS screen exactly as it always was.
 */
final class VsLayout
{
    public const KEY = 'scoreboard_vs_layout';

    public const DEFAULTS = ['info_bottom' => 64.8, 'info_scale' => 1.0, 'show_stats' => 1];

    public const LIMITS = ['info_bottom' => [0, 700], 'info_scale' => [0.5, 1.3], 'show_stats' => [0, 1]];

    public static function get(): array
    {
        $raw = json_decode((string) PlatformSetting::get(self::KEY, ''), true);

        return self::clean(is_array($raw) ? $raw : []);
    }

    public static function clean(array $in): array
    {
        $out = [];
        foreach (self::DEFAULTS as $key => $default) {
            $v = $in[$key] ?? $default;
            $v = is_bool($v) ? (int) $v : $v;   // a switch arrives as true/false
            $v = is_numeric($v) ? (float) $v : $default;
            [$lo, $hi] = self::LIMITS[$key];
            $out[$key] = $key === 'show_stats' ? (int) round(min($hi, max($lo, $v))) : round(min($hi, max($lo, $v)), 3);
        }

        return $out;
    }

    public static function save(array $in): array
    {
        $layout = self::clean($in);
        PlatformSetting::set(self::KEY, json_encode($layout));

        return $layout;
    }

    /** The two CSS custom properties the VS screen reads. */
    public static function cssVars(?array $layout = null): string
    {
        $l = $layout ?? self::get();

        return '--vs-info-bottom:'.$l['info_bottom'].'px; --vs-info-scale:'.$l['info_scale'].';';
    }
}
