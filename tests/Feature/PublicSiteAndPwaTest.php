<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicSiteAndPwaTest extends TestCase
{
    public function test_public_home_page_renders_without_internal_data(): void
    {
        $this->superAdmin(['name' => 'Hidden Admin Name']);

        $this->get('/')->assertOk()
            ->assertSee('Nationwide — Nigeria')
            ->assertSee('Event Lighting')
            ->assertDontSee('Ilorin')
            ->assertDontSee('Hidden Admin Name')
            ->assertDontSee('href="mailto:"', false);
    }

    public function test_pages_link_the_web_manifest_and_icons(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false)
            ->assertSee('apple-touch-icon', false)
            ->assertSee('<meta name="theme-color" content="#1A1A1A">', false);
    }

    public function test_manifest_is_installable(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertStringStartsWith('/app', $manifest['start_url']);

        $sizes = collect($manifest['icons'])->pluck('sizes')->all();
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);
        $this->assertContains('maskable', collect($manifest['icons'])->pluck('purpose')->all());

        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
            [$w, $h] = getimagesize(public_path(ltrim($icon['src'], '/')));
            $this->assertSame($icon['sizes'], "{$w}x{$h}");
        }
    }

    public function test_service_worker_never_caches_pages(): void
    {
        $sw = file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("request.mode === 'navigate'", $sw);
        $this->assertStringContainsString("const OFFLINE_URL = '/offline'", $sw);
        // Only static assets are written to the cache at runtime.
        $this->assertSame(1, substr_count($sw, 'cache.put(request'));
    }

    public function test_offline_page_renders(): void
    {
        $this->get('/offline')->assertOk()->assertSee("You're offline");
    }

    public function test_security_headers_are_sent(): void
    {
        $this->get('/login')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_internal_pages_are_not_cached_by_browsers(): void
    {
        $response = $this->actingAs($this->userWithRole('Crew'))->get('/app');

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_branded_error_pages(): void
    {
        $this->get('/does-not-exist')->assertNotFound()->assertSee('Page not found')->assertDontSee('Symfony');
        $this->actingAs($this->userWithRole('Crew'))->get('/app/users')->assertForbidden()->assertSee('Access denied');
    }
}
