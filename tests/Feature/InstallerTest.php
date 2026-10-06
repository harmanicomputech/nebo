<?php

namespace Tests\Feature;

use App\Http\Requests\Setup\InstallRequest;
use App\Models\User;
use App\Services\System\InstallSteps;
use App\Support\BackgroundTasks;
use App\Support\Installer;
use App\Support\Permissions\PermissionCatalog;
use Carbon\CarbonImmutable;
use Dotenv\Dotenv;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/nebo-install-'.uniqid();
        mkdir($this->dir);
        file_put_contents($this->dir.'/.env', "APP_NAME=\"Nebo Stage\"\nAPP_KEY=base64:abc\n# DB_HOST=127.0.0.1\nNEBO_INSTALLER=true\n");
        $this->app->useEnvironmentPath($this->dir);
        Installer::$lockFile = $this->dir.'/installed.lock';
    }

    protected function tearDown(): void
    {
        Installer::$lockFile = null;
        array_map('unlink', glob($this->dir.'/{,.}[!.]*', GLOB_BRACE));
        rmdir($this->dir);

        parent::tearDown();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'app_url' => 'https://ops.example.ng', 'company' => 'Nebo Stage',
            'db_host' => 'localhost', 'db_port' => 3306, 'db_database' => 'nebo', 'db_username' => 'nebo', 'db_password' => 'p@ss "$word',
            'admin_name' => 'Ada Obi', 'admin_email' => 'ada@example.ng', 'admin_password' => 'Stage-Lights-2026', 'admin_password_confirmation' => 'Stage-Lights-2026',
            'mail_mailer' => 'smtp', 'mail_host' => 'mail.example.ng', 'mail_port' => 465, 'mail_username' => 'bookings@example.ng', 'mail_password' => 'secret', 'mail_from' => '',
            'demo' => 0,
        ], $overrides);
    }

    public function test_the_installer_is_off_unless_enabled(): void
    {
        $this->get('/install')->assertNotFound();
        $this->get('/')->assertOk();
    }

    public function test_when_enabled_every_page_leads_to_the_installer(): void
    {
        config(['nebo.installer' => true]);

        $this->get('/')->assertRedirect('/install');
        $this->get('/app')->assertRedirect('/install');
        $this->get('/install')->assertOk()->assertSee('Install Nebo Stage')->assertSee('Server check')->assertSee('checks passed');
    }

    public function test_bad_database_details_are_reported(): void
    {
        config(['nebo.installer' => true]);

        $this->post('/install', $this->payload(['db_host' => '127.0.0.1', 'db_port' => 1]))
            ->assertSessionHasErrors('db_host');
        $this->assertStringNotContainsString('ops.example.ng', file_get_contents($this->dir.'/.env'));
    }

    public function test_the_form_writes_the_settings_file_and_plans_the_steps(): void
    {
        config(['nebo.installer' => true]);
        $this->app->bind(InstallRequest::class, ConnectingInstallRequest::class);

        $this->post('/install', $this->payload(['demo' => 1]))->assertRedirect(route('install.run'));

        $env = Dotenv::parse(file_get_contents($this->dir.'/.env'));
        $this->assertSame('https://ops.example.ng', $env['APP_URL']);
        $this->assertSame('p@ss "$word', $env['DB_PASSWORD']);
        $this->assertSame('true', $env['SESSION_SECURE_COOKIE']);
        $this->assertSame('ada@example.ng', $env['MAIL_FROM_ADDRESS']);
        $this->assertSame('base64:abc', $env['APP_KEY']);
        $this->assertSame('localhost', $env['DB_HOST']); // the commented line was replaced, not duplicated
        $this->assertSame(1, substr_count(file_get_contents($this->dir.'/.env'), 'DB_HOST='));

        $install = session('install');
        $this->assertTrue(Hash::check('Stage-Lights-2026', $install['admin']['password_hash']));
        $this->assertContains('history:0', array_column($install['steps'], 'key'));
    }

    public function test_email_can_be_left_for_later(): void
    {
        config(['nebo.installer' => true]);
        $this->app->bind(InstallRequest::class, ConnectingInstallRequest::class);
        $payload = array_diff_key($this->payload(['mail_mailer' => 'log']), array_flip(['mail_host', 'mail_port', 'mail_username', 'mail_password', 'mail_from']));

        $this->post('/install', $payload)->assertRedirect(route('install.run'));

        $env = Dotenv::parse(file_get_contents($this->dir.'/.env'));
        $this->assertSame('log', $env['MAIL_MAILER']);
        $this->assertSame('ada@example.ng', $env['MAIL_FROM_ADDRESS']);
    }

    public function test_the_steps_run_one_per_request_then_lock_the_installer(): void
    {
        config(['nebo.installer' => true]);
        $session = ['install' => [
            'steps' => app(InstallSteps::class)->plan(false), 'done' => 0, 'demo' => false,
            'admin' => ['name' => 'Ada Obi', 'email' => 'Ada@Example.ng', 'password_hash' => Hash::make('Stage-Lights-2026'), 'company' => 'Nebo Stage Ltd'],
        ]];

        $this->withSession($session)->get('/install/run')->assertOk()->assertSee('1 of 3 steps')->assertSee('http-equiv="refresh"', false);
        $this->get('/install/run')->assertOk()->assertSee('2 of 3 steps');
        $this->get('/install/run')->assertOk()->assertSee('3 of 3 steps');
        $this->get('/install/run')->assertRedirect(route('login'));

        $admin = User::where('email', 'ada@example.ng')->firstOrFail();
        $this->assertTrue($admin->hasRole(PermissionCatalog::SUPER_ADMIN));
        $this->assertTrue(Hash::check('Stage-Lights-2026', $admin->password));
        $this->assertFileExists(Installer::lockPath());
        $this->assertFalse(Installer::active());
        $this->get('/install')->assertNotFound();
        $this->get('/')->assertOk();
    }

    public function test_daily_jobs_run_once_per_day_after_their_time(): void
    {
        $tasks = app(BackgroundTasks::class);

        $this->travelTo(CarbonImmutable::parse('2026-10-06 06:00', 'Africa/Lagos'));
        $this->assertSame(['expire-quotations'], $tasks->run(0));
        $this->assertSame([], $tasks->run(0));

        $this->travelTo(CarbonImmutable::parse('2026-10-06 07:05', 'Africa/Lagos'));
        $this->assertSame(['maintenance-reminders'], $tasks->run(0));

        $this->travelTo(CarbonImmutable::parse('2026-10-07 09:00', 'Africa/Lagos'));
        $this->assertSame(['expire-quotations', 'maintenance-reminders'], $tasks->run(0));
    }

    public function test_page_visits_run_background_jobs_only_when_enabled(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-06 09:00', 'Africa/Lagos'));

        $this->get('/')->assertOk();
        $this->assertSame(['expire-quotations', 'maintenance-reminders'], app(BackgroundTasks::class)->run(0), 'nothing ran yet');

        $this->travelTo(CarbonImmutable::parse('2026-10-07 09:00', 'Africa/Lagos'));
        config(['nebo.web_cron' => true]);
        $this->get('/')->assertOk();
        $this->assertSame([], app(BackgroundTasks::class)->run(0), 'the visit already ran them');
    }

    public function test_the_system_page_applies_updates(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->get(route('app.settings.system'))->assertOk()->assertSee('Readiness checks')->assertSee('The database is up to date.');
        $this->actingAs($admin)->post(route('app.settings.system.update'))->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('audit_logs', ['event' => 'system_updated', 'user_id' => $admin->id]);

        $this->actingAs($this->userWithRole('Operations Manager'))->get(route('app.settings.system'))->assertForbidden();
    }
}

class ConnectingInstallRequest extends InstallRequest
{
    public function after(): array
    {
        return [];
    }
}
