<?php

namespace App\Services;

use App\Support\Settings;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Issues reference numbers such as NEBO-REQ-2026-00001.
 *
 * Formats are settings (references.<key>) using these tokens:
 *   {YYYY} {YY} {MM}  — date parts; a format containing them restarts its count each period
 *   {SEQ:n}           — the counter, zero-padded to n digits (required)
 *
 * The counter row is locked for the duration of the surrounding transaction,
 * so concurrent requests never receive the same number.
 */
class ReferenceGenerator
{
    public function next(string $key, ?CarbonInterface $at = null): string
    {
        $format = Settings::string('references.'.$key);

        if (! preg_match('/\{SEQ:(\d+)\}/', $format, $seq)) {
            throw new InvalidArgumentException("Reference format for [{$key}] must contain {SEQ:n}.");
        }

        $at ??= now(config('nebo.display_timezone'));
        $period = $this->period($format, $at);

        $number = DB::transaction(function () use ($key, $period) {
            $row = DB::table('sequences')->where(['key' => $key, 'period' => $period])->lockForUpdate()->first();

            if (! $row) {
                DB::table('sequences')->insertOrIgnore(['key' => $key, 'period' => $period, 'next_value' => 1, 'created_at' => now(), 'updated_at' => now()]);
                $row = DB::table('sequences')->where(['key' => $key, 'period' => $period])->lockForUpdate()->first();
            }

            DB::table('sequences')->where('id', $row->id)->update(['next_value' => $row->next_value + 1, 'updated_at' => now()]);

            return (int) $row->next_value;
        });

        return strtr($format, [
            '{YYYY}' => $at->format('Y'),
            '{YY}' => $at->format('y'),
            '{MM}' => $at->format('m'),
            $seq[0] => str_pad((string) $number, (int) $seq[1], '0', STR_PAD_LEFT),
        ]);
    }

    public static function isValidFormat(string $format): bool
    {
        return (bool) preg_match('/\{SEQ:([1-9]|1[0-2])\}/', $format)
            && ! preg_match('/[^A-Za-z0-9\-\/_{}:]/', $format);
    }

    private function period(string $format, CarbonInterface $at): string
    {
        return match (true) {
            str_contains($format, '{MM}') => $at->format('Y-m'),
            str_contains($format, '{YYYY}'), str_contains($format, '{YY}') => $at->format('Y'),
            default => '',
        };
    }
}
