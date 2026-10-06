<?php

namespace App\Support;

/**
 * Nigerian phone numbers to E.164: 0803 123 4567, 234803…, +234 803… → +2348031234567.
 * Other international numbers keep their + and digits.
 */
class PhoneNumber
{
    public static function normalize(?string $input): ?string
    {
        if ($input === null || trim($input) === '') {
            return null;
        }

        $plus = str_starts_with(trim($input), '+');
        $digits = preg_replace('/\D+/', '', $input);

        return match (true) {
            $digits === '' => null,
            ! $plus && str_starts_with($digits, '0') && strlen($digits) === 11 => '+234'.substr($digits, 1),
            ! $plus && str_starts_with($digits, '234') && strlen($digits) === 13 => '+'.$digits,
            ! $plus && strlen($digits) === 10 => '+234'.$digits,
            default => '+'.$digits,
        };
    }

    public static function isValid(?string $input): bool
    {
        $e164 = self::normalize($input);

        return $e164 !== null && (bool) preg_match('/^\+\d{8,15}$/', $e164)
            && (! str_starts_with($e164, '+234') || strlen($e164) === 14);
    }
}
