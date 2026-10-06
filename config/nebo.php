<?php

return [

    /*
    | Brand. Colours are also defined as CSS tokens in resources/css/app.css.
    */
    'brand' => [
        'name' => env('APP_NAME', 'Nebo Stage'),
        'tagline' => 'Event production & technical services — nationwide',
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
        'notifications.request_recipients' => '',
        'availability.buffer_hours' => 0, // turnaround added before setup and after breakdown
    ],

    // Initial administrator created by `php artisan nebo:create-admin` when no options are given.
    'admin' => [
        'email' => env('NEBO_ADMIN_EMAIL'),
        'password' => env('NEBO_ADMIN_PASSWORD'),
    ],

    'auth' => [
        'max_login_attempts' => 5, // per email + IP per minute
    ],
];
