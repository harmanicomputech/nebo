<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Services\System\InstallSteps;
use App\Services\System\SystemUpdater;
use App\Support\Audit\Audit;
use App\Support\ProductionChecks;
use App\Support\SampleData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

    /** Starts loading sample data in short steps (D73), like the installer does. */
    public function loadSample(Request $request, InstallSteps $steps): RedirectResponse
    {
        if (SampleData::exists()) {
            return back()->with('error', 'Sample data is already loaded.');
        }
        $plan = array_values(array_filter($steps->plan(true), fn (array $s) => str_starts_with($s['key'], 'demo:') || str_starts_with($s['key'], 'history:')));
        $request->session()->put('sample_load', ['steps' => $plan, 'done' => 0]);

        return redirect()->route('app.settings.system.sample.run');
    }

    /** Runs the next step and reloads itself until all are done. */
    public function runSample(Request $request, InstallSteps $steps): View|RedirectResponse
    {
        $load = $request->session()->get('sample_load');
        if (! $load) {
            return redirect()->route('app.settings.system');
        }
        if ($load['done'] >= count($load['steps'])) {
            $request->session()->forget('sample_load');
            Audit::record('sample_data_loaded', "{$request->user()->name} loaded the sample data");

            return redirect()->route('app.settings.system')->with('success', 'Sample data loaded. Sign-in accounts and the password are listed below; clear it all here when you are done.');
        }

        $error = null;
        $lock = Cache::lock('nebo:sample-load', 310);
        if ($lock->get()) {
            $user = $request->user();
            try {
                $steps->run($load['steps'][$load['done']]['key'], []);
                $load['done']++;
                $request->session()->put('sample_load', $load);
            } catch (\Throwable $e) {
                Log::error('Sample data step failed', ['exception' => $e]);
                $error = $e->getMessage();
            } finally {
                $lock->release();
                auth()->setUser($user); // the sample seeders act as the sample administrator
            }
        }

        return view('internal.settings.sample-load', ['steps' => $load['steps'], 'done' => $load['done'], 'error' => $error]);
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
