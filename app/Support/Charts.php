<?php

namespace App\Support;

/**
 * Helpers for the server-rendered charts (D64). Colours are the validated
 * categorical order (blue, orange, aqua) — checked for colour-blind
 * separation on the white card surface. Aqua is under 3:1 contrast, so
 * every chart ships a legend and a table view.
 */
class Charts
{
    /** Fixed categorical order. Series take slots in this order, never cycled. */
    public const SERIES = ['#2a78d6', '#eb6834', '#1baf7a'];

    public const GRID = '#e1e0d9';

    /**
     * A clean axis maximum and evenly spaced ticks (0 included).
     *
     * @return array{max: float, ticks: list<float>}
     */
    public static function scale(float $max, int $count = 4): array
    {
        if ($max <= 0) {
            return ['max' => 1, 'ticks' => [0, 1]];
        }

        $raw = $max / $count;
        $magnitude = 10 ** floor(log10($raw));
        $step = collect([1, 2, 2.5, 5, 10])->map(fn ($m) => $m * $magnitude)->first(fn ($s) => $s >= $raw);
        $top = $step * ceil($max / $step);

        return ['max' => $top, 'ticks' => array_map(fn ($i) => $i * $step, range(0, (int) round($top / $step)))];
    }

    /** Short axis labels: 1,200 → 1.2K; kobo → ₦4.2M. */
    public static function compact(float $value, string $format = 'number'): string
    {
        if ($format === 'naira') {
            $value /= 100;
        }

        $out = match (true) {
            abs($value) >= 1_000_000_000 => rtrim(rtrim(number_format($value / 1_000_000_000, 1), '0'), '.').'B',
            abs($value) >= 1_000_000 => rtrim(rtrim(number_format($value / 1_000_000, 1), '0'), '.').'M',
            abs($value) >= 10_000 => rtrim(rtrim(number_format($value / 1_000, 1), '0'), '.').'K',
            default => number_format($value, $value == floor($value) ? 0 : 1),
        };

        return ($format === 'naira' ? '₦' : '').$out;
    }

    /** Full value for tooltips and tables. */
    public static function full(float $value, string $format = 'number'): string
    {
        return match ($format) {
            'naira' => Format::naira((int) $value),
            'percent' => rtrim(rtrim(number_format($value, 1), '0'), '.').'%',
            default => number_format($value, $value == floor($value) ? 0 : 1),
        };
    }
}
