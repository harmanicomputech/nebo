<?php

namespace Tests\Feature;

use App\Services\ReferenceGenerator;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Tests\TestCase;

class ReferenceGeneratorTest extends TestCase
{
    public function test_generates_sequential_references_in_the_default_format(): void
    {
        $gen = app(ReferenceGenerator::class);
        $at = CarbonImmutable::parse('2026-03-01', 'Africa/Lagos');

        $this->assertSame('NEBO-REQ-2026-00001', $gen->next('request', $at));
        $this->assertSame('NEBO-REQ-2026-00002', $gen->next('request', $at));
        $this->assertSame('NEBO-EVT-2026-00001', $gen->next('event', $at));
    }

    public function test_counter_restarts_each_year(): void
    {
        $gen = app(ReferenceGenerator::class);

        $gen->next('request', CarbonImmutable::parse('2026-12-31'));
        $this->assertSame('NEBO-REQ-2027-00001', $gen->next('request', CarbonImmutable::parse('2027-01-01')));
    }

    public function test_format_is_configurable(): void
    {
        Settings::set('references.request', 'NS/{YY}{MM}/{SEQ:3}');

        $this->assertSame('NS/2610/001', app(ReferenceGenerator::class)->next('request', CarbonImmutable::parse('2026-10-06')));
    }

    public function test_format_without_a_counter_is_refused(): void
    {
        Settings::set('references.request', 'NEBO-{YYYY}');

        $this->expectException(InvalidArgumentException::class);
        app(ReferenceGenerator::class)->next('request');
    }
}
