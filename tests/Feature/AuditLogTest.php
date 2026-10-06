<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    public function test_model_changes_record_before_and_after_values(): void
    {
        $user = User::factory()->create(['name' => 'Before Name']);
        $user->update(['name' => 'After Name']);

        $log = AuditLog::where(['event' => 'updated', 'auditable_type' => 'User', 'auditable_id' => (string) $user->id])->firstOrFail();
        $this->assertSame(['name' => 'Before Name'], $log->old_values);
        $this->assertSame(['name' => 'After Name'], $log->new_values);
    }

    public function test_actions_record_user_ip_and_device(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->withHeader('User-Agent', 'NeboTest/1.0')->put('/app/settings', $this->validSettings(['company_name' => 'Nebo Stage Ltd']));

        $log = AuditLog::where('event', 'settings_changed')->firstOrFail();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($admin->name, $log->user_name);
        $this->assertSame('127.0.0.1', $log->ip_address);
        $this->assertSame('NeboTest/1.0', $log->user_agent);
    }

    public function test_audit_entries_cannot_be_changed_or_deleted(): void
    {
        Audit::record('test', 'Something happened');
        $log = AuditLog::latest('id')->firstOrFail();

        try {
            $log->update(['description' => 'Rewritten']);
            $this->fail('Audit log update was allowed.');
        } catch (LogicException) {
        }

        try {
            $log->delete();
            $this->fail('Audit log delete was allowed.');
        } catch (LogicException) {
        }

        $this->assertSame('Something happened', $log->fresh()->description);
    }

    public function test_there_are_no_routes_that_modify_audit_entries(): void
    {
        $admin = $this->superAdmin();
        Audit::record('test', 'Entry');
        $id = AuditLog::latest('id')->value('id');

        $this->actingAs($admin)->delete("/app/audit/{$id}")->assertStatus(405);
        $this->actingAs($admin)->put("/app/audit/{$id}")->assertStatus(405);
    }

    public function test_audit_log_viewer_filters_and_shows_entries(): void
    {
        $admin = $this->superAdmin();
        $user = User::factory()->create(['name' => 'Filter Target']);
        $user->update(['job_title' => 'Rigger']);

        $this->actingAs($admin)->get('/app/audit?event=updated&type=User')->assertOk()->assertSee('User Filter Target updated');
        $this->actingAs($admin)->get('/app/audit?event=logout')->assertSee('No audit entries found');

        $log = AuditLog::where('event', 'updated')->where('auditable_id', (string) $user->id)->first();
        $this->actingAs($admin)->get("/app/audit/{$log->id}")->assertOk()->assertSee('job_title')->assertSee('Rigger');
    }

    public function test_recording_never_breaks_the_action(): void
    {
        Schema::drop('audit_logs');

        $user = User::factory()->create();
        $user->update(['name' => 'Still saved']);

        $this->assertSame('Still saved', $user->fresh()->name);
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function validSettings(array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'Nebo Stage',
            'company_email' => 'hello@nebostage.example',
            'references_request' => 'NEBO-REQ-{YYYY}-{SEQ:5}',
            'references_event' => 'NEBO-EVT-{YYYY}-{SEQ:5}',
            'references_quotation' => 'NEBO-QUO-{YYYY}-{SEQ:5}',
            'references_load_list' => 'NEBO-LL-{YYYY}-{SEQ:5}',
            'availability_buffer_hours' => 0,
        ], $overrides);
    }
}
