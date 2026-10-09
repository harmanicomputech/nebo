<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Phase 10 audit: every internal route is closed to guests and to a signed-in
 * user with no module permissions, except their own pages.
 */
class AccessAuditTest extends TestCase
{
    /** Pages any signed-in user may open. */
    private const OPEN = ['app', 'app/profile', 'app/notifications', 'app/notifications/poll', 'app/search'];

    /** @return list<Route> */
    private function appRoutes(): array
    {
        return array_values(array_filter(Router::getRoutes()->getRoutes(), fn (Route $r) => str_starts_with($r->uri(), 'app')));
    }

    private function url(Route $route): string
    {
        // Constrained parameters need a value that matches (reports take a name).
        return '/'.preg_replace('/\{[^}]+\}/', '1', strtr($route->uri(), ['{report}' => 'events', '{token}' => str_repeat('0', 26)]));
    }

    public function test_guests_are_sent_to_sign_in_from_every_internal_route(): void
    {
        foreach ($this->appRoutes() as $route) {
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $response = $this->call($method, $this->url($route));
                $this->assertContains($response->getStatusCode(), [302, 419], "{$method} {$route->uri()} answered {$response->getStatusCode()} to a guest.");
                if ($response->getStatusCode() === 302) {
                    $this->assertStringEndsWith('/login', $response->headers->get('Location'), "{$method} {$route->uri()}");
                }
            }
        }
    }

    public function test_a_user_without_permissions_is_refused_everywhere_else(): void
    {
        $role = Role::create(['name' => 'Audit Nobody', 'guard_name' => 'web']);
        $role->givePermissionTo('dashboard.view');
        $user = User::factory()->create();
        $user->assignRole($role);

        $checked = 0;
        foreach ($this->appRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true) || str_contains($route->uri(), '{') || in_array($route->uri(), self::OPEN, true)) {
                continue;
            }
            $status = $this->actingAs($user)->get($this->url($route))->getStatusCode();
            $this->assertSame(403, $status, "GET {$route->uri()} answered {$status} to a user with no permissions.");
            $checked++;
        }
        $this->assertGreaterThan(30, $checked);

        foreach (self::OPEN as $uri) {
            $this->actingAs($user)->get('/'.$uri.($uri === 'app/search' ? '?q=test' : ''))->assertOk();
        }
    }

    public function test_security_headers_and_csp(): void
    {
        $response = $this->get('/');
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringNotContainsString("'unsafe-inline'", explode(';', explode('script-src', $csp)[1])[0], 'Scripts must not allow inline code.');
        $response->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff');

        // No inline scripts or handlers anywhere in the views (the CSP would block them).
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'))) as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $html = file_get_contents($file->getPathname());
                $this->assertDoesNotMatchRegularExpression('/\son(click|load|submit|change|input)=/i', $html, $file->getPathname());
                $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\bsrc=)[^>]*>/i', $html, $file->getPathname());
            }
        }
    }
}
