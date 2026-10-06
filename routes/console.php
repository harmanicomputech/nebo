<?php

use App\Services\Commercial\QuotationService;
use App\Services\Maintenance\MaintenanceReminders;
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

Schedule::command('nebo:maintenance-reminders')->dailyAt('07:00')->timezone('Africa/Lagos');

Artisan::command('nebo:expire-quotations', function (QuotationService $quotes) {
    $this->info($quotes->expireOverdue().' quotation(s) expired.');
})->purpose('Mark sent quotations past their validity date as expired');

Schedule::command('nebo:expire-quotations')->dailyAt('00:15')->timezone('Africa/Lagos');

// Shared hosting can't keep a queue worker running: the scheduler drains the
// queue (customer emails, notifications) every minute instead (D67).
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping()->runInBackground();
