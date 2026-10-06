<?php

use App\Services\Commercial\QuotationService;
use App\Services\Maintenance\MaintenanceReminders;
use App\Support\BackgroundTasks;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('nebo:maintenance-reminders', function (MaintenanceReminders $reminders) {
    $sent = $reminders->send();
    $this->info("Maintenance reminders: {$sent['overdue']} overdue, {$sent['due_soon']} due soon.");
})->purpose('Notify maintenance managers about scheduled maintenance that is due');

Artisan::command('nebo:expire-quotations', function (QuotationService $quotes) {
    $this->info($quotes->expireOverdue().' quotation(s) expired.');
})->purpose('Mark sent quotations past their validity date as expired');

Artisan::command('nebo:tick', function (BackgroundTasks $tasks) {
    $ran = $tasks->run(50);
    $this->info($ran ? 'Ran: '.implode(', ', $ran).'.' : 'Nothing due.');
})->purpose('Run due daily jobs (once per day each) and send queued emails');

// Shared hosting can't keep a queue worker running: one cron line running
// schedule:run calls the tick every minute (D67). The daily jobs inside it
// run once per Lagos day, so the web fallback (NEBO_WEB_CRON) never doubles them (D69).
Schedule::command('nebo:tick')->everyMinute()->withoutOverlapping();
