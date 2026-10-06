<?php

namespace App\Services\System;

use App\Models\User;
use App\Support\Audit\Audit;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Applies an uploaded new version (D69): runs pending migrations, syncs
 * permissions and reference data (idempotent) and clears compiled caches.
 * The same as the deploy commands in docs/DEPLOYMENT.md.
 */
class SystemUpdater
{
    /** @return array{ok: bool, message: string} */
    public function apply(User $by): array
    {
        @set_time_limit(300);

        try {
            foreach ([
                ['migrate', ['--force' => true]],
                ['db:seed', ['--class' => ReferenceDataSeeder::class, '--force' => true]],
                ['view:clear', []],
                ['config:clear', []],
                ['route:clear', []],
            ] as [$command, $options]) {
                if (Artisan::call($command, $options) !== 0) {
                    throw new \RuntimeException(trim(Artisan::output()) ?: "{$command} failed.");
                }
            }
        } catch (\Throwable $e) {
            Log::error('System update failed', ['exception' => $e]);
            Audit::record('system_update_failed', "{$by->name} ran the system update; it failed: ".str($e->getMessage())->limit(200));

            return ['ok' => false, 'message' => 'The update stopped: '.str($e->getMessage())->limit(300)];
        }

        Audit::record('system_updated', "{$by->name} applied the system update (migrations, reference data, caches)");

        return ['ok' => true, 'message' => 'Update applied: the database and reference data are up to date.'];
    }
}
