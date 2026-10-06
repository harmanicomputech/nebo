<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Services\System\SystemUpdater;
use App\Support\ProductionChecks;
use App\Support\SampleData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'sample' => SampleData::exists() ? ['counts' => SampleData::counts(), 'accounts' => SampleData::accounts(), 'password' => SampleData::PASSWORD, 'blockers' => SampleData::blockers()] : null,
        ]);
    }

    /** Removes every sample record (D71); signs out a sample account that did it. */
    public function clearSample(Request $request): RedirectResponse
    {
        $user = $request->user();
        $wasSample = SampleData::accounts()->contains('id', $user->id);
        $result = SampleData::clear($user, $request->boolean('include_mine'));

        if (! $result['ok']) {
            return back()->with('error', $result['message']);
        }
        if ($wasSample) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', $result['message'].' The sample account you used was removed too; sign in with your own account.');
        }

        return back()->with('success', $result['message']);
    }

    public function update(SystemUpdater $updater): RedirectResponse
    {
        $result = $updater->apply(auth()->user());

        return $result['ok']
            ? back()->with('success', $result['message'])
            : back()->with('error', $result['message']);
    }
}
