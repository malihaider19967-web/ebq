<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * System-wide key/value settings — first use case is the global feature
 * kill-switch map that overrides per-website flags.
 *
 * Reads go through Laravel's cache layer (rememberForever) so a hot
 * setting hits memory, not the DB. Writes invalidate the cache for that
 * key. Model-level access is via the static `get()` / `set()` helpers;
 * direct Eloquent CRUD also works but bypasses cache invalidation, so
 * prefer the helpers.
 *
 * Naming convention for cache keys: `settings:<key>`. Add new settings
 * by simply calling `Setting::set('my_thing', $value)` — no schema
 * change needed (value is JSON).
 */
class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    /**
     * Read a setting. Returns `$default` when the row is missing or its
     * `value` is null. Cached forever; invalidated on `set()`.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever(
            self::cacheKey($key),
            static function () use ($key, $default): mixed {
                $row = self::query()->find($key);
                if ($row === null) {
                    return $default;
                }
                return $row->value ?? $default;
            }
        );
    }

    /**
     * Write a setting. Upserts the row and invalidates the cache so the
     * next `get()` returns the fresh value.
     */
    public static function set(string $key, mixed $value): void
    {
        self::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
        Cache::forget(self::cacheKey($key));
    }

    /**
     * Wipe a setting entirely.
     */
    public static function unset(string $key): void
    {
        self::query()->where('key', $key)->delete();
        Cache::forget(self::cacheKey($key));
    }

    private static function cacheKey(string $key): string
    {
        return 'settings:'.$key;
    }
}
