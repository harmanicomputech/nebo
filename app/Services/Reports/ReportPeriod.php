<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;

/**
 * The date range a report covers, chosen in Lagos time (presets or a custom
 * range) and exposed in UTC for queries.
 */
final class ReportPeriod
{
    public const PRESETS = ['30d' => 'Last 30 days', '90d' => 'Last 90 days', 'ytd' => 'This year', '12m' => 'Last 12 months', 'next90' => 'Next 90 days', 'custom' => 'Custom'];

    private function __construct(public readonly string $preset, public readonly CarbonImmutable $from, public readonly CarbonImmutable $to) {}

    public static function fromRequest(Request $request, string $default = '12m'): self
    {
        $tz = config('nebo.display_timezone');
        $now = CarbonImmutable::now($tz);
        $preset = array_key_exists((string) $request->query('period'), self::PRESETS) ? $request->query('period') : $default;

        if ($preset === 'custom') {
            try {
                $from = CarbonImmutable::parse((string) $request->query('from'), $tz)->startOfDay();
                $to = CarbonImmutable::parse((string) $request->query('to'), $tz)->endOfDay();
                if ($to->gte($from) && $from->diffInDays($to) <= 1100) {
                    return new self('custom', $from, $to);
                }
            } catch (\Throwable) {
                // fall through to the default
            }
            $preset = $default;
        }

        [$from, $to] = match ($preset) {
            '30d' => [$now->subDays(29)->startOfDay(), $now->endOfDay()],
            '90d' => [$now->subDays(89)->startOfDay(), $now->endOfDay()],
            'ytd' => [$now->startOfYear(), $now->endOfDay()],
            'next90' => [$now->startOfDay(), $now->addDays(89)->endOfDay()],
            default => [$now->subMonths(11)->startOfMonth(), $now->endOfMonth()],
        };

        return new self($preset, $from, $to);
    }

    public function fromUtc(): CarbonImmutable
    {
        return $this->from->utc();
    }

    public function toUtc(): CarbonImmutable
    {
        return $this->to->utc();
    }

    public function days(): float
    {
        return max(1, $this->from->diffInSeconds($this->to) / 86400);
    }

    public function label(): string
    {
        return $this->from->format('j M Y').' – '.$this->to->format('j M Y');
    }

    /**
     * Month buckets covering the period: key Y-m => label "Oct 26".
     *
     * @return array<string, string>
     */
    public function months(): array
    {
        $months = [];
        for ($m = $this->from->startOfMonth(); $m->lte($this->to); $m = $m->addMonth()) {
            $months[$m->format('Y-m')] = $m->format('M y');
        }

        return $months;
    }

    /** Month bucket key for a UTC timestamp. */
    public static function monthOf(?CarbonInterface $at): ?string
    {
        return $at?->copy()->setTimezone(config('nebo.display_timezone'))->format('Y-m');
    }

    /**
     * @return array<string, string>
     */
    public function query(): array
    {
        return $this->preset === 'custom'
            ? ['period' => 'custom', 'from' => $this->from->toDateString(), 'to' => $this->to->toDateString()]
            : ['period' => $this->preset];
    }
}
