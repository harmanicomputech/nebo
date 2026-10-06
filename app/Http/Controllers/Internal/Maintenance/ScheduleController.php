<?php

namespace App\Http\Controllers\Internal\Maintenance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Maintenance\ScheduleRequest;
use App\Models\Equipment;
use App\Models\EquipmentAsset;
use App\Models\MaintenanceSchedule;
use App\Services\Maintenance\MaintenanceScheduler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function __construct(private MaintenanceScheduler $scheduler) {}

    public function store(ScheduleRequest $request): RedirectResponse
    {
        $data = $request->safe()->only(['type', 'interval_days', 'next_due_on', 'notes']);

        if ($request->validated('scope') === 'equipment') {
            $equipment = Equipment::findOrFail($request->validated('equipment_id'));
            $count = $this->scheduler->createForEquipment($request->user(), $equipment, $data);

            return back()->with('success', $count ? "Schedule added to {$count} unit(s) of {$equipment->name}." : "Every unit of {$equipment->name} already has this schedule.");
        }

        $asset = EquipmentAsset::findOrFail($request->validated('asset_id'));
        $this->scheduler->create($request->user(), $asset, $data);

        return back()->with('success', "Schedule added to {$asset->asset_tag}.");
    }

    public function update(ScheduleRequest $request, MaintenanceSchedule $schedule): RedirectResponse
    {
        $this->scheduler->update($schedule, $request->safe()->only(['interval_days', 'next_due_on', 'notes', 'is_active']));

        return back()->with('success', 'Schedule updated.');
    }

    public function openJob(Request $request, MaintenanceSchedule $schedule): RedirectResponse
    {
        abort_unless($request->user()->can('maintenance.manage'), 403);
        $record = $this->scheduler->openJob($request->user(), $schedule->load('asset'));

        return redirect()->route('app.maintenance.show', $record)->with('success', "{$record->reference} opened. Schedule a window or start the work.");
    }
}
