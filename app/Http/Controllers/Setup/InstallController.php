<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\InstallRequest;
use App\Services\System\InstallSteps;
use App\Support\Installer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Browser installer for hosts without a terminal (D69): checks the server,
 * writes .env, then runs the install one short step per request.
 */
class InstallController extends Controller
{
    public function __construct()
    {
        abort_unless(Installer::active(), 404);
    }

    public function show(Request $request): View
    {
        return view('install.show', [
            'requirements' => Installer::requirements(),
            'ready' => Installer::ready(),
            'appUrl' => rtrim($request->root(), '/'),
        ]);
    }

    public function store(InstallRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $https = str_starts_with($data['app_url'], 'https://');

        Installer::writeEnv([
            'APP_ENV' => 'production',
            'APP_DEBUG' => false,
            'APP_URL' => rtrim($data['app_url'], '/'),
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $data['db_host'],
            'DB_PORT' => $data['db_port'],
            'DB_DATABASE' => $data['db_database'],
            'DB_USERNAME' => $data['db_username'],
            'DB_PASSWORD' => $data['db_password'] ?? '',
            'SESSION_SECURE_COOKIE' => $https,
            'MAIL_MAILER' => $data['mail_mailer'],
            'MAIL_HOST' => $data['mail_host'] ?? '127.0.0.1',
            'MAIL_PORT' => $data['mail_port'] ?? 587,
            'MAIL_USERNAME' => $data['mail_username'] ?? '',
            'MAIL_PASSWORD' => $data['mail_password'] ?? '',
            'MAIL_SCHEME' => 'null', // port 465 switches to implicit TLS by itself
            'MAIL_FROM_ADDRESS' => ($data['mail_from'] ?? null) ?: $data['admin_email'],
        ]);

        $request->session()->put('install', [
            'steps' => app(InstallSteps::class)->plan(),
            'done' => 0,
            'admin' => ['name' => $data['admin_name'], 'email' => $data['admin_email'], 'password_hash' => Hash::make($data['admin_password']), 'company' => $data['company'] ?? null],
        ]);

        return redirect()->route('install.run');
    }

    /** Runs the next step, then reloads itself until every step is done. */
    public function run(Request $request, InstallSteps $installSteps): View|RedirectResponse
    {
        $install = $request->session()->get('install');
        if (! $install) {
            return redirect()->route('install.show');
        }

        $steps = $install['steps'];
        if ($install['done'] >= count($steps)) {
            Installer::lock();
            $request->session()->forget('install');

            return redirect()->route('login')->with('status', 'Nebo Stage is installed. Sign in with the administrator account you just created.');
        }

        $lock = Cache::lock('nebo:install', 310);
        if (! $lock->get()) {
            return view('install.run', ['steps' => $steps, 'done' => $install['done'], 'busy' => true, 'error' => null]);
        }

        $error = null;
        try {
            $installSteps->run($steps[$install['done']]['key'], $install['admin']);
            $install['done']++;
            $request->session()->put('install', $install);
        } catch (\Throwable $e) {
            Log::error('Install step failed: '.$steps[$install['done']]['key'], ['exception' => $e]);
            $error = $e->getMessage();
        } finally {
            $lock->release();
        }

        return view('install.run', ['steps' => $steps, 'done' => $install['done'], 'busy' => false, 'error' => $error]);
    }
}
