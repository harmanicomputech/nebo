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

    public static function naira(?int $kobo): string
    {
        return $kobo === null ? '—' : '₦'.number_format($kobo / 100, 2);
    }
}
