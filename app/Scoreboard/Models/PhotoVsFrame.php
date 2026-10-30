<?php

namespace App\Scoreboard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Where a photo sits on the Karate VS screen: a zoom and a focal point over
 * the ORIGINAL picture, which is never cut (see the migration). The VS panel
 * draws the photo `{zoom*100}% auto` at `{pos_x}% {pos_y}%`; zoom 1 at
 * 50% 12% is exactly the panel's own `cover` / `center 12%` for a 3:4 photo,
 * so an unframed photo and a framed-at-default one look the same.
 */
class PhotoVsFrame extends Model
{
    protected $fillable = ['path', 'zoom', 'pos_x', 'pos_y', 'updated_by'];

    protected $casts = ['zoom' => 'float', 'pos_x' => 'float', 'pos_y' => 'float'];

    public const LIMITS = ['zoom' => [1, 3], 'pos_x' => [0, 100], 'pos_y' => [0, 100]];

    /** @var array<string, array|null> one read per photo per request */
    private static array $memo = [];

    /** {z, x, y} for the VS screen, or null when this photo was never framed. */
    public static function payloadFor(?string $path): ?array
    {
        if (! $path) {
            return null;
        }
        if (array_key_exists($path, self::$memo)) {
            return self::$memo[$path];
        }
        // Before the table exists (a deploy mid-migration) the screen simply
        // draws the photo as it always did.
        if (! Schema::hasTable('photo_vs_frames')) {
            return self::$memo[$path] = null;
        }
        $f = static::where('path', $path)->first();

        return self::$memo[$path] = $f ? ['z' => $f->zoom, 'x' => $f->pos_x, 'y' => $f->pos_y] : null;
    }

    public static function forget(?string $path): void
    {
        unset(self::$memo[(string) $path]);
    }
}
