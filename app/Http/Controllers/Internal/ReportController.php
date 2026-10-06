<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\ReportService;
use App\Support\Audit\Audit;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /** key => [title, description, icon, permission, uses a period?, default period] */
    public const REPORTS = [
        'utilisation' => ['Equipment utilisation', 'How much of each item\'s time was booked.', 'gauge', 'reports.view', true, '90d'],
        'events' => ['Events & pipeline', 'Events by month and type, top customers, request conversion.', 'calendar-range', 'reports.view', true, '12m'],
        'maintenance' => ['Maintenance', 'Faults and repairs over time, turnaround and the items that break most.', 'wrench', 'reports.view', true, '12m'],
        'logistics' => ['Logistics', 'Trips by month, vehicle use, on-time arrivals.', 'truck', 'reports.view', true, '90d'],
        'inventory' => ['Inventory', 'The fleet by status and category, with value.', 'boxes', 'reports.view', false, null],
        'commercial' => ['Commercial', 'Quotations sent and accepted, conversion and top customers.', 'receipt', 'reports.financial', true, '12m'],
    ];

    public function __construct(private ReportService $reports) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('reports.view'), 403);

        return view('internal.reports.index', ['reports' => collect(self::REPORTS)->filter(fn ($r) => $request->user()->can($r[3]))]);
    }

    public function show(Request $request, string $report): View|StreamedResponse
    {
        abort_unless(isset(self::REPORTS[$report]), 404);
        [$title, $description, $icon, $permission, $usesPeriod, $default] = self::REPORTS[$report];
        abort_unless($request->user()->can('reports.view') && $request->user()->can($permission), 403);

        $period = ReportPeriod::fromRequest($request, $default ?? '12m');
        $costs = $request->user()->can('inventory.costs');
        $data = match ($report) {
            'utilisation' => $this->reports->utilisation($period),
            'events' => $this->reports->events($period),
            'maintenance' => $this->reports->maintenance($period, $costs),
            'logistics' => $this->reports->logistics($period),
            'inventory' => $this->reports->inventory($costs),
            'commercial' => $this->reports->commercial($period),
        };

        if ($request->query('export') === 'csv') {
            abort_unless($request->user()->can('reports.export'), 403);

            return $this->csv($report, $usesPeriod ? $period : null, $data['table']);
        }

        return view("internal.reports.{$report}", [
            'report' => $report, 'title' => $title, 'description' => $description,
            'period' => $usesPeriod ? $period : null, 'data' => $data, 'costs' => $costs,
        ]);
    }

    /**
     * @param  array{columns: list<string>, rows: list<list<mixed>>}  $table
     */
    private function csv(string $report, ?ReportPeriod $period, array $table): StreamedResponse
    {
        $name = 'nebo-'.$report.($period ? '-'.$period->from->format('Ymd').'-'.$period->to->format('Ymd') : '-'.now(config('nebo.display_timezone'))->format('Ymd')).'.csv';
        Audit::record('report_exported', "Exported the {$report} report".($period ? ' ('.$period->label().')' : ''));

        return response()->streamDownload(function () use ($table) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows ₦ and accents
            fputcsv($out, $table['columns']);
            foreach ($table['rows'] as $row) {
                fputcsv($out, array_map([self::class, 'cell'], $row));
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Neutralise spreadsheet formulas in exported text (CSV injection). */
    public static function cell(mixed $value): mixed
    {
        return is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
