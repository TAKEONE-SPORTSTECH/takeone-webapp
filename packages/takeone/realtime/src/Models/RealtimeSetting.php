<?php

namespace Takeone\Realtime\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Tiny key/value store for runtime-editable plugin settings.
 *
 * These override the static config/realtime.php values so a super-admin can
 * point the plugin at a different broker (or flip the master switch) from the
 * admin UI without redeploying. Values are cached to keep the hot path cheap.
 */
class RealtimeSetting extends Model
{
    protected $fillable = ['key', 'value'];

    private const CACHE_KEY = 'realtime.settings';

    /** All overrides as a flat key => value array (cached forever, busted on write). */
    public static function all(...$args): mixed
    {
        if (! empty($args)) {
            return parent::all(...$args);
        }

        return Cache::rememberForever(self::CACHE_KEY, function () {
            return static::query()->pluck('value', 'key')->all();
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::CACHE_KEY);
    }

    public static function putMany(array $pairs): void
    {
        foreach ($pairs as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Cache::forget(self::CACHE_KEY);
    }
}
