<?php

namespace App\Support;

use App\Models\User;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * Production readiness checks (D67), shared by `nebo:check-production` and
 * the System page (D69). Fail = would leak data or break the app; warn =
 * only reduces features.
 */
class ProductionChecks
{
    /**
     * @return list<array{0: 'ok'|'warn'|'fail', 1: string, 2: string}>
     */
    public static function run(): array
    {
        $checks = [];
        $add = function (string $status, string $check, string $detail = '') use (&$checks) {
            $checks[] = [$status, $check, $detail];
        };

        $add(app()->isProduction() ? 'ok' : 'fail', 'APP_ENV is production', (string) config('app.env'));
        $add(config('app.debug') ? 'fail' : 'ok', 'APP_DEBUG is off', 'Debug pages show secrets and data.');
        $add(str_starts_with((string) config('app.url'), 'https://') ? 'ok' : 'fail', 'APP_URL uses HTTPS', (string) config('app.url'));
        $add(config('session.secure') ? 'ok' : 'fail', 'Session cookie is HTTPS-only', 'SESSION_SECURE_COOKIE=true');
        $add(config('session.encrypt') ? 'ok' : 'warn', 'Session data is encrypted', 'SESSION_ENCRYPT=true');
        $add(filled(config('app.key')) ? 'ok' : 'fail', 'APP_KEY is set', 'php artisan key:generate');

        try {
            DB::connection()->getPdo();
            $add('ok', 'Database connection', DB::connection()->getDriverName());
            $pending = collect(app('migrator')->getMigrationFiles(database_path('migrations')))->keys()
                ->diff(Schema::hasTable('migrations') ? DB::table('migrations')->pluck('migration') : []);
            $add($pending->isEmpty() ? 'ok' : 'fail', 'Migrations are up to date', $pending->isEmpty() ? '' : $pending->count().' pending: php artisan migrate --force');
            $missing = collect(PermissionCatalog::all())->diff(Permission::pluck('name'));
            $add($missing->isEmpty() ? 'ok' : 'fail', 'Permissions and reference data seeded', $missing->isEmpty() ? '' : 'php artisan db:seed --class=ReferenceDataSeeder --force');
            $admins = User::query()->active()->role(PermissionCatalog::SUPER_ADMIN)->count();
            $add($admins > 0 ? 'ok' : 'fail', 'An active super administrator exists', $admins ? '' : 'php artisan nebo:create-admin');
            $sample = SampleData::exists();
            $add($sample ? 'fail' : 'ok', 'No sample data', $sample ? 'Sample records are still loaded: Settings → System → Clear sample data.' : '');
        } catch (\Throwable $e) {
            $add('fail', 'Database connection', $e->getMessage());
        }

        foreach ([storage_path('app/private'), storage_path('framework'), storage_path('logs'), base_path('bootstrap/cache')] as $dir) {
            $add(is_dir($dir) && is_writable($dir) ? 'ok' : 'fail', 'Writable: '.str_replace(base_path().'/', '', $dir));
        }
        $add(is_file(public_path('build/manifest.json')) ? 'ok' : 'fail', 'Front-end assets are built', 'npm run build');
        $add(! is_file(public_path('hot')) ? 'ok' : 'fail', 'Vite dev server is not referenced', 'delete public/hot');
        $add(! in_array(config('mail.default'), ['log', 'array', null], true) ? 'ok' : 'warn', 'Real mailer configured', 'Customer emails and password resets need MAIL_* settings.');
        $add(! str_ends_with(Settings::string('company.email'), '.example') ? 'ok' : 'warn', 'Company email set in Settings', Settings::string('company.email'));
        $add(config('queue.default') !== 'sync' ? 'ok' : 'warn', 'Queue is not synchronous', 'Emails are sent during the request. Use QUEUE_CONNECTION=database with cron or NEBO_WEB_CRON.');

        return $checks;
    }

    /** Migration files not yet run. */
    public static function pendingMigrations(): int
    {
        try {
            return collect(app('migrator')->getMigrationFiles(database_path('migrations')))->keys()
                ->diff(Schema::hasTable('migrations') ? DB::table('migrations')->pluck('migration') : [])->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
