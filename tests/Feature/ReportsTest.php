<?php

namespace Tests\Feature;

use App\Http\Controllers\Internal\ReportController;
use App\Services\Allocation\AllocationService;
use App\Services\Commercial\QuotationService;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\ReportService;
use App\Support\Charts;
use Illuminate\Http\Request;
use Tests\Concerns\InteractsWithEvents;
use Tests\Concerns\InteractsWithInventory;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use InteractsWithEvents, InteractsWithInventory;

    public function test_utilisation_is_booked_unit_days_over_owned_unit_days(): void
    {
        $admin = $this->superAdmin();
        $lights = $this->serializedItem();
        [$a] = $this->addUnits($lights, 2)->all();
        // One unit held for exactly 3 days inside the period.
        $event = $this->event(5, 1, [
            'setup_starts_at' => now()->addDays(5)->startOfDay(), 'starts_at' => now()->addDays(5)->startOfDay()->addHours(10),
            'ends_at' => now()->addDays(7)->startOfDay(), 'breakdown_ends_at' => now()->addDays(8)->startOfDay(),
        ]);
        app(AllocationService::class)->reserveAssets($admin, $event, $lights, [$a->id]);

        $period = ReportPeriod::fromRequest(Request::create('/', 'GET', ['period' => 'custom', 'from' => now('Africa/Lagos')->toDateString(), 'to' => now('Africa/Lagos')->addDays(29)->toDateString()]));
        $row = app(ReportService::class)->utilisation($period)['rows']->firstWhere('id', $lights->id);

        $this->assertSame(2, $row['units']);
        $this->assertEqualsWithDelta(3.0, $row['booked_days'], 0.05);
        $this->assertEqualsWithDelta(5.0, $row['pct'], 0.1); // 3 / (2 × 30)
    }

    public function test_every_report_renders_for_the_right_people(): void
    {
        $this->event(5, 1);
        $admin = $this->superAdmin();
        foreach (array_keys(ReportController::REPORTS) as $report) {
            $this->actingAs($admin)->get("/app/reports/{$report}")->assertOk();
        }
        $this->actingAs($admin)->get('/app/reports/events?period=custom&from=2026-01-01&to=2026-12-31')->assertOk()->assertSee('1 Jan 2026')->assertSee('View as table');
        $this->actingAs($admin)->get('/app/reports/nope')->assertNotFound();

        // Inventory managers see operational reports, not commercial ones.
        $inventory = $this->userWithRole('Inventory Manager');
        $this->actingAs($inventory)->get('/app/reports')->assertOk()->assertSee('Equipment utilisation')->assertDontSee('Quotations sent and accepted');
        $this->actingAs($inventory)->get('/app/reports/commercial')->assertForbidden();
        $this->actingAs($this->userWithRole('Finance / Commercial'))->get('/app/reports/commercial')->assertOk();
        $this->actingAs($this->userWithRole('Crew'))->get('/app/reports')->assertForbidden();
    }

    public function test_csv_export_needs_permission_and_neutralises_formulas(): void
    {
        $customer = $this->customer();
        $customer->update(['company' => '=HYPERLINK("http://evil.test","x")']);
        $admin = $this->superAdmin();
        $quotes = app(QuotationService::class);
        $quote = $quotes->create($admin, $customer, ['title' => '@SUM(A1)', 'valid_until' => now()->addDays(5)->toDateString(),
            'items' => [['section' => 'services', 'description' => 'x', 'quantity' => 1, 'days' => 1, 'unit_price_kobo' => 1000_00]]]);
        $quotes->send($admin, $quote);

        $response = $this->actingAs($admin)->get('/app/reports/commercial?export=csv&period=30d');
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString("'@SUM(A1)", $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertDatabaseHas('audit_logs', ['event' => 'report_exported']);

        $viewer = $this->userWithRole('Viewer');
        $this->actingAs($viewer)->get('/app/reports/events')->assertOk();
        $this->actingAs($viewer)->get('/app/reports/events?export=csv')->assertForbidden();
    }

    public function test_dashboard_shows_trends(): void
    {
        $this->event(1, 1);
        $this->actingAs($this->superAdmin())->get('/app')->assertOk()->assertSee('Trends')->assertSee('Accepted quotation value')->assertDontSee('Module roadmap');
        $this->actingAs($this->userWithRole('Crew'))->get('/app')->assertOk()->assertDontSee('Accepted quotation value');
    }

    public function test_chart_scale_and_labels(): void
    {
        $this->assertSame(['max' => 20.0, 'ticks' => [0.0, 5.0, 10.0, 15.0, 20.0]], Charts::scale(17));
        $this->assertSame([0, 1], Charts::scale(0)['ticks']);
        $this->assertSame('₦1.5M', Charts::compact(150_000_000, 'naira'));
        $this->assertSame('12.5K', Charts::compact(12_500));
        $this->assertSame('5%', Charts::full(5.0, 'percent'));
    }
}
