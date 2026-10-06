<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Display helpers: everything is stored in UTC and shown in Lagos time.
 */
class Format
{
    public static function datetime(?CarbonInterface $value, string $format = 'j M Y, g:i a'): string
    {
        return $value ? $value->copy()->setTimezone(config('nebo.display_timezone'))->format($format) : '—';
    }

    public static function date(?CarbonInterface $value): string
    {
        return self::datetime($value, 'j M Y');
    }

    public static function naira(?int $kobo, bool $decimals = false): string
    {
        return $kobo === null ? '—' : '₦'.number_format($kobo / 100, $decimals ? 2 : 0);
    }

    /** "1,250,000.50" (naira, as typed) → 125000050 kobo. Null for blank. */
    public static function toKobo(mixed $naira): ?int
    {
        if ($naira === null || trim((string) $naira) === '') {
            return null;
        }

        return (int) round(((float) str_replace([',', '₦', ' '], '', (string) $naira)) * 100);
    }

    /** Kobo → plain naira for form inputs. */
    public static function nairaInput(?int $kobo): string
    {
        return $kobo === null ? '' : rtrim(rtrim(number_format($kobo / 100, 2, '.', ''), '0'), '.');
    }
}
