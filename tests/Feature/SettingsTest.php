<?php

namespace Tests\Feature;

use App\Support\Settings;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    /**
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'Nebo Stage',
            'company_email' => 'hello@nebostage.example',
            'company_phone' => '+234 800 000 0000',
            'company_address' => 'Lagos',
            'company_coverage' => 'Nationwide — Nigeria',
            'references_request' => 'NEBO-REQ-{YYYY}-{SEQ:5}',
            'references_event' => 'NEBO-EVT-{YYYY}-{SEQ:5}',
            'references_quotation' => 'NEBO-QUO-{YYYY}-{SEQ:5}',
            'references_load_list' => 'NEBO-LL-{YYYY}-{SEQ:5}',
            'availability_buffer_hours' => 0,
            'references_maintenance' => 'NEBO-MNT-{YYYY}-{SEQ:5}',
            'maintenance_reminder_days' => 7,
            'notifications_request_recipients' => '',
        ], $overrides);
    }

    public function test_defaults_come_from_config_until_saved(): void
    {
        $this->assertSame('NEBO-REQ-{YYYY}-{SEQ:5}', Settings::string('references.request'));
    }

    public function test_admin_updates_settings(): void
    {
        $this->actingAs($this->superAdmin())->put('/app/settings', $this->payload([
            'company_phone' => '+234 901 234 5678',
            'references_request' => 'NS-{YY}{MM}-{SEQ:4}',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('+234 901 234 5678', Settings::string('company.phone'));
        $this->assertSame('NS-{YY}{MM}-{SEQ:4}', Settings::string('references.request'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'settings_changed']);
    }

    public function test_invalid_values_are_rejected(): void
    {
        $this->actingAs($this->superAdmin())->put('/app/settings', $this->payload([
            'references_request' => 'NO-COUNTER-{YYYY}',
            'company_email' => 'not-an-email',
            'notifications_request_recipients' => 'ok@example.com, broken',
        ]))->assertSessionHasErrors(['references_request', 'company_email', 'notifications_request_recipients']);

        $this->assertSame('NEBO-REQ-{YYYY}-{SEQ:5}', Settings::string('references.request'));
    }

    public function test_reference_formats_reject_markup(): void
    {
        $this->actingAs($this->superAdmin())->put('/app/settings', $this->payload(['references_event' => '<b>{SEQ:5}</b>']))
            ->assertSessionHasErrors('references_event');
    }
}
