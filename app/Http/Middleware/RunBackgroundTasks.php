<?php

namespace App\Http\Middleware;

use App\Support\BackgroundTasks;
use App\Support\Installer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cron for hosts that have none (D69): after the response has been sent, at
 * most once a minute, run the daily jobs and send queued emails.
 */
class RunBackgroundTasks
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! config('nebo.web_cron') || Installer::active() || ! Cache::add('nebo:web-cron:'.now()->format('YmdHi'), true, 120)) {
            return;
        }

        ignore_user_abort(true);
        @set_time_limit(120);
        try {
            app(BackgroundTasks::class)->run();
        } catch (\Throwable $e) {
            Log::error('Background tasks failed', ['exception' => $e]);
        }
    }
}
