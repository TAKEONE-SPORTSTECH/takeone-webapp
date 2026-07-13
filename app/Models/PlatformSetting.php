<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Global platform-wide key/value settings controlled by the super-admin.
 * Values are cached forever and flushed on write.
 */
class PlatformSetting extends Model
{
    protected $fillable = ['key', 'value'];

    private const CACHE_PREFIX = 'platform_setting:';

    /**
     * Read a setting, cast to a sensible type. Booleans are stored as "1"/"0".
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::rememberForever(self::CACHE_PREFIX.$key, function () use ($key) {
            return static::query()->where('key', $key)->value('value');
        });

        return $value === null ? $default : $value;
    }

    public static function getBool(string $key, bool $default = true): bool
    {
        $value = static::get($key, null);

        return $value === null ? $default : (string) $value === '1';
    }

    public static function set(string $key, mixed $value): void
    {
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        static::query()->updateOrCreate(['key' => $key], ['value' => (string) $value]);
        Cache::forget(self::CACHE_PREFIX.$key);
    }
}
