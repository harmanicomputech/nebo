<?php

namespace App\Support;

use App\Services\Commercial\QuotationService;
use App\Services\Maintenance\MaintenanceReminders;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Periodic work that must happen even on hosts without cron (D69): the daily
 * jobs run once per Lagos day once their time has passed, and queued emails
 * and notifications are sent. Called every minute by the scheduler
 * (`nebo:tick`) and, when NEBO_WEB_CRON is on, after page responses.
 */
class BackgroundTasks
{
    /** Daily jobs: name => Lagos time from which they may run. */
    public const DAILY = ['expire-quotations' => '00:15', 'maintenance-reminders' => '07:00'];

    /** @return list<string> the daily jobs that ran */
    public function run(int $queueSeconds = 20): array
    {
        $ran = [];
        $now = CarbonImmutable::now(config('nebo.display_timezone'));

        foreach (self::DAILY as $job => $from) {
            if ($now->format('H:i') < $from || ! Cache::add("nebo:daily:{$job}:".$now->toDateString(), true, now()->addDays(2))) {
                continue;
            }
            try {
                match ($job) {
                    'expire-quotations' => app(QuotationService::class)->expireOverdue(),
                    'maintenance-reminders' => app(MaintenanceReminders::class)->send(),
                };
                $ran[] = $job;
            } catch (\Throwable $e) {
                Log::error("Daily job {$job} failed", ['exception' => $e]);
            }
        }

        if (config('queue.default') !== 'sync' && $queueSeconds > 0) {
            Artisan::call('queue:work', ['--stop-when-empty' => true, '--max-time' => $queueSeconds, '--tries' => 3, '--quiet' => true]);
        }

        return $ran;
    }
}
