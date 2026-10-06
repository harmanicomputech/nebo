<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Services\System\SystemUpdater;
use App\Support\ProductionChecks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** System health and updates without a terminal (D69). */
class SystemController extends Controller
{
    public function show(): View
    {
        $version = is_file(base_path('VERSION')) ? trim((string) file_get_contents(base_path('VERSION'))) : null;

        return view('internal.settings.system', [
            'checks' => ProductionChecks::run(),
            'pending' => ProductionChecks::pendingMigrations(),
            'version' => $version,
            'php' => PHP_VERSION,
            'database' => DB::connection()->getDriverName().' '.DB::connection()->getServerVersion(),
            'queued' => DB::table('jobs')->count(),
            'failed' => DB::table('failed_jobs')->count(),
        ]);
    }

    public function update(SystemUpdater $updater): RedirectResponse
    {
        $result = $updater->apply(auth()->user());

        return $result['ok']
            ? back()->with('success', $result['message'])
            : back()->with('error', $result['message']);
    }
}
