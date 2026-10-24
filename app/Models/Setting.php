<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;

#[Fillable(['key', 'value'])]
class Setting extends Model
{
    public const CACHE_KEY = 'settings.all';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * Every stored setting, keyed by name.
     *
     * The marketing pages read this on every render, so an unreachable or
     * not-yet-migrated database falls back to an empty set rather than taking
     * the public site down. That failure is deliberately not cached.
     *
     * @return array<string, string|null>
     */
    public static function values(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $values = static::query()->pluck('value', 'key')->all();
        } catch (QueryException) {
            return [];
        }

        Cache::forever(self::CACHE_KEY, $values);

        return $values;
    }

    public static function read(string $key, ?string $default = null): ?string
    {
        return static::values()[$key] ?? $default;
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public static function write(array $values): void
    {
        foreach ($values as $key => $value) {
            static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::forget(self::CACHE_KEY);
    }

    public static function erase(string ...$keys): void
    {
        static::query()->whereIn('key', $keys)->delete();

        Cache::forget(self::CACHE_KEY);
    }
}
