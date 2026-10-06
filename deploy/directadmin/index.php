<?php

/*
 | Nebo Stage front door for shared hosting (DirectAdmin, cPanel).
 | This folder is the website (public_html); the application lives in the
 | "nebo" folder next to it, out of reach of browsers. If you put that folder
 | somewhere else, change the path below.
 */
$appPath = __DIR__.'/../nebo';

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$stop = function (string $title, string $message) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Nebo Stage</title>'
        .'<div style="font-family:system-ui,sans-serif;max-width:36rem;margin:4rem auto;padding:0 1rem;color:#1A1A1A">'
        .'<h1 style="font-size:1.4rem">'.$title.'</h1><p style="line-height:1.6">'.$message.'</p></div>';
    exit;
};

if (version_compare(PHP_VERSION, '8.3.0', '<')) {
    $stop('PHP 8.3 or newer is needed', 'This website runs PHP '.PHP_VERSION.'. In DirectAdmin open <b>Domain Setup</b> (or <b>Select PHP version</b>) and choose PHP 8.3 or 8.4 for this domain, then reload.');
}

if (! is_file($appPath.'/vendor/autoload.php') || ! is_file($appPath.'/bootstrap/app.php')) {
    $stop('Application folder not found', 'Upload the <b>nebo</b> folder next to this website folder (so both sit in the same parent folder), or correct <code>$appPath</code> at the top of index.php.');
}

// First visit: create the settings file with a fresh encryption key. The
// installer at /install fills in the database and the rest.
if (! is_file($appPath.'/.env') && is_file($appPath.'/.env.directadmin')) {
    $https = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    $env = strtr((string) file_get_contents($appPath.'/.env.directadmin'), [
        'APP_KEY=' => 'APP_KEY=base64:'.base64_encode(random_bytes(32)),
        'APP_URL=' => 'APP_URL='.($https ? 'https://' : 'http://').$host,
    ]);
    if (@file_put_contents($appPath.'/.env', $env, LOCK_EX) === false) {
        $stop('Cannot write the settings file', 'Give the <b>nebo</b> folder write permission (755) in the File Manager, then reload.');
    }
    @chmod($appPath.'/.env', 0640);
}

if (file_exists($maintenance = $appPath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $appPath.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $appPath.'/bootstrap/app.php';
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
