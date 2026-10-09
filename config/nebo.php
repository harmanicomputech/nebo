<?php

return [

    /*
    | Brand. Colours are also defined as CSS tokens in resources/css/app.css.
    */
    'brand' => [
        'name' => env('APP_NAME', 'Nebo Stage'),
        'tagline' => 'Event production & equipment rental — nationwide',
        'primary' => '#CC1F1F',
        'dark' => '#1A1A1A',
    ],

    // Display timezone. Timestamps are stored in UTC (config/app.php timezone).
    'display_timezone' => env('NEBO_DISPLAY_TIMEZONE', 'Africa/Lagos'),

    'currency' => 'NGN',

    /*
    | Defaults for settings that administrators can change in the console
    | (App\Support\Settings). The console value wins once it is saved.
    */
    'defaults' => [
        'company.name' => 'Nebo Stage',
        'company.email' => env('NEBO_COMPANY_EMAIL') ?: 'hello@nebostage.example', // placeholder: set the real address in Settings
        'company.phone' => env('NEBO_COMPANY_PHONE') ?: '',
        'company.address' => '',
        'company.coverage' => 'Nationwide — Nigeria',
        'references.request' => 'NEBO-REQ-{YYYY}-{SEQ:5}',
        'references.event' => 'NEBO-EVT-{YYYY}-{SEQ:5}',
        'references.quotation' => 'NEBO-QUO-{YYYY}-{SEQ:5}',
        'references.load_list' => 'NEBO-LL-{YYYY}-{SEQ:5}',
        'references.maintenance' => 'NEBO-MNT-{YYYY}-{SEQ:5}',
        'references.trip' => 'NEBO-TRP-{YYYY}-{SEQ:5}',
        'notifications.request_recipients' => '',
        'availability.buffer_hours' => 0, // turnaround added before setup and after breakdown
        'maintenance.reminder_days' => 7, // remind this many days before a schedule falls due
        'quotations.validity_days' => 14,
        'quotations.vat_percent' => '7.5', // Nigerian VAT; 0 to quote without tax
        'quotations.terms' => "50% deposit confirms the booking; the balance is due before load-in.\nPrices are in Naira and valid until the date shown.\nTransport outside Lagos, accommodation and venue power are quoted separately unless listed.\nEquipment damaged or lost through the client's negligence is charged at replacement cost.",
    ],

    // Initial administrator created by `php artisan nebo:create-admin` when no options are given.
    'admin' => [
        'email' => env('NEBO_ADMIN_EMAIL'),
        'password' => env('NEBO_ADMIN_PASSWORD'),
    ],

    // Browser installer for hosts without a terminal (D69). The upload package
    // turns it on; it switches itself off by writing storage/app/installed.lock.
    'installer' => (bool) env('NEBO_INSTALLER', false),

    // Run the daily jobs and drain the queue after page responses, for hosts
    // without cron (D69). A cron running `schedule:run` works with it or without it.
    'web_cron' => (bool) env('NEBO_WEB_CRON', false),

    'auth' => [
        'max_login_attempts' => 5, // per email + IP per minute
    ],
];
