<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Key-value settings editable from the console, cached briefly. Unsaved keys
 * fall back to config('nebo.defaults').
 */
class Settings
{
    private const CACHE_KEY = 'nebo:settings';

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();

        if (array_key_exists($key, $all)) {
            return $all[$key];
        }

        // Keys contain dots, so read the defaults array directly rather than via dot notation.
        return config('nebo.defaults')[$key] ?? $default;
    }

    public static function string(string $key, string $default = ''): string
    {
        return (string) (self::get($key, $default) ?? $default);
    }

    public static function set(string $key, mixed $value): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => $key],
            ['value' => json_encode($value), 'updated_by' => auth()->id(), 'updated_at' => now(), 'created_at' => now()],
        );

        self::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    private static function all(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, 300, fn () => DB::table('settings')->pluck('value', 'key')
                ->map(fn (?string $value) => $value === null ? null : json_decode($value, true))
                ->all());
        } catch (Throwable) {
            return []; // Before migrations have run.
        }
    }
}
