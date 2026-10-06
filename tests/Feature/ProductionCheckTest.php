<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProductionCheckTest extends TestCase
{
    public function test_the_production_check_flags_an_unsafe_setup(): void
    {
        config(['app.debug' => true, 'app.url' => 'http://example.test', 'session.secure' => false]);

        $this->artisan('nebo:check-production')
            ->expectsOutputToContain('APP_DEBUG is off')
            ->expectsOutputToContain('check(s) failed')
            ->assertExitCode(1);
    }
}
