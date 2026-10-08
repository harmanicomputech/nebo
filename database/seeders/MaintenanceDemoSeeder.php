<?php

namespace Database\Seeders;

use App\Models\Equipment;
use App\Models\EquipmentAsset;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceSchedule;
use App\Models\Staff;
use App\Models\User;
use App\Services\Maintenance\InspectionService;
use App\Services\Maintenance\MaintenanceScheduler;
use App\Services\Maintenance\MaintenanceService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/** Demo maintenance jobs, schedules and inspections (never production). */
class MaintenanceDemoSeeder extends Seeder
{
    public function run(MaintenanceService $jobs, MaintenanceScheduler $scheduler, InspectionService $inspections): void
    {
        if (MaintenanceRecord::where('issue', 'Dead pixel row on the bottom module')->exists()) {
            return;
        }

        $admin = User::where('email', 'ada.okafor@nebostage.com')->firstOrFail();
        auth()->setUser($admin);
        $tech = Staff::where('role', 'lighting_technician')->first();
        $lagos = CarbonImmutable::now(config('nebo.display_timezone'))->startOfDay();
        // Units in the store and not booked for any show, so repairs don't clash with bookings.
        $free = fn (string $sku) => EquipmentAsset::with(['status', 'equipment'])
            ->whereHas('equipment', fn ($q) => $q->where('sku', $sku))
            ->whereHas('status', fn ($q) => $q->where('code', 'available'))
            ->whereDoesntHave('allocations', fn ($q) => $q->active())
            ->orderByDesc('asset_tag')->get();
        $panels = $free('LED-P391-S');

        // A panel already on the bench.
        if ($a = $panels->get(0)) {
            $job = $jobs->report($admin, $a, ['type' => 'repair', 'priority' => 'high', 'issue' => 'Dead pixel row on the bottom module', 'description' => 'Seen during the last screen test. Swap the module and check the data cable.', 'technician_id' => $tech?->id]);
            $jobs->start($admin, $job);
        }

        // Damaged at load-out, waiting for a technician.
        if ($a = $panels->get(1)) {
            $jobs->report($admin, $a, ['type' => 'repair', 'priority' => 'urgent', 'issue' => 'Corner cracked after a drop at load-out', 'description' => 'Fell off the dolly at the ramp. Check the frame locks and the power connector too.']);
        }

        // Inspection booked for the day after tomorrow.
        $inStore = fn (string $sku) => EquipmentAsset::with(['status', 'equipment'])->whereHas('equipment', fn ($q) => $q->where('sku', $sku))
            ->whereHas('status', fn ($q) => $q->where('code', 'available'))->orderByDesc('asset_tag')->first();
        if ($a = $inStore('VID-VX600')) {
            $jobs->report($admin, $a, ['type' => 'inspection', 'priority' => 'normal', 'issue' => 'Intermittent signal loss on output 3', 'technician_id' => $tech?->id,
                'scheduled_starts_at' => $lagos->addDays(2)->setTime(9, 0)->utc(), 'scheduled_ends_at' => $lagos->addDays(2)->setTime(13, 0)->utc()]);
        }

        // A completed hoist inspection.
        if ($a = $free('RIG-HOIST')->first()) {
            $inspections->record($admin, $a, 'fair', 'Light surface rust on the load chain; brake holds under load.');
            $done = $jobs->report($admin, $a->refresh(), ['type' => 'inspection', 'priority' => 'low', 'issue' => 'Clean and lubricate the load chain', 'technician_id' => $tech?->id]);
            $jobs->complete($admin, $done, ['work_done' => 'Cleaned and lubricated the load chain, checked hook latch and brake, tested with 500 kg.', 'outcome_condition' => 'good', 'cost_kobo' => 1_500_000, 'parts_used' => 'Chain lubricant']);
        }

        // Recurring schedules: panels every 90 days, processors twice a year, hoists yearly.
        $scheduler->createForEquipment($admin, Equipment::where('sku', 'LED-P391-S')->firstOrFail(), ['type' => 'preventive', 'interval_days' => 90, 'next_due_on' => $lagos->addDays(45)->toDateString(), 'notes' => 'Clean module faces, check power and data cables, run a full-white test.']);
        $scheduler->createForEquipment($admin, Equipment::where('sku', 'VID-VX600')->firstOrFail(), ['type' => 'firmware', 'interval_days' => 180, 'next_due_on' => $lagos->addDays(5)->toDateString()]);
        $scheduler->createForEquipment($admin, Equipment::where('sku', 'RIG-HOIST')->firstOrFail(), ['type' => 'safety_test', 'interval_days' => 365, 'next_due_on' => $lagos->addDays(20)->toDateString(), 'notes' => 'Thorough examination of chain, hook and brake.']);

        // Two panels are overdue.
        MaintenanceSchedule::whereHas('asset', fn ($q) => $q->whereIn('asset_tag', ['LP5-003', 'LP5-004']))->get()
            ->each(fn (MaintenanceSchedule $s) => $scheduler->update($s, ['interval_days' => 90, 'next_due_on' => $lagos->subDays(4)->toDateString(), 'notes' => $s->notes, 'is_active' => true]));

        auth()->forgetUser();
    }
}
