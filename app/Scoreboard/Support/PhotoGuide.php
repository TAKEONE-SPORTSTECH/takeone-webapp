<?php

namespace App\Scoreboard\Support;

use App\Platform\Models\PlatformSetting;

/**
 * Where a face belongs in a 3:4 competitor photo — the guide drawn over every
 * console's photo cropper, set once for the platform by a super-admin from the
 * console's Settings → Photo guide (owner, 2026-10-24).
 *
 * Every value is a FRACTION of the photo: x of its width, y of its height, so
 * the guide is the same at any size it is drawn. Out-of-range or missing
 * values fall back to the defaults rather than to a broken guide.
 */
final class PhotoGuide
{
    public const KEY = 'scoreboard_photo_guide';

    /** The first guide, worked out from the VS screen's framing. */
    public const DEFAULTS = [
        'cx' => 0.5,        // head centre, across
        'cy' => 0.2955,     // head centre, down
        'rx' => 0.1424,     // head half-width
        'ry' => 0.1455,     // head half-height
        'eye' => 0.2955,    // eye line
        'shoulder' => 0.5045,
        'cut' => 0.78,      // below this the VS screen shows nothing
    ];

    private const LIMITS = [
        'cx' => [0.2, 0.8], 'cy' => [0.05, 0.8], 'rx' => [0.05, 0.45], 'ry' => [0.05, 0.45],
        'eye' => [0.02, 0.95], 'shoulder' => [0.05, 0.98], 'cut' => [0.3, 1.0],
    ];

    public static function get(): array
    {
        $raw = json_decode((string) PlatformSetting::get(self::KEY, ''), true);

        return self::clean(is_array($raw) ? $raw : []);
    }

    /** Only the known keys, each a number inside its own range. */
    public static function clean(array $in): array
    {
        $out = [];
        foreach (self::DEFAULTS as $key => $default) {
            $v = $in[$key] ?? $default;
            $v = is_numeric($v) ? (float) $v : $default;
            [$lo, $hi] = self::LIMITS[$key];
            $out[$key] = round(min($hi, max($lo, $v)), 4);
        }

        return $out;
    }

    public static function save(array $in): array
    {
        $guide = self::clean($in);
        PlatformSetting::set(self::KEY, json_encode($guide));

        return $guide;
    }

    public static function reset(): array
    {
        PlatformSetting::set(self::KEY, '');

        return self::DEFAULTS;
    }
}
