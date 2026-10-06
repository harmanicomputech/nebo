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
        if (MaintenanceRecord::where('issue', 'Pan motor grinding at speed')->exists()) {
            return;
        }

        $admin = User::where('email', 'admin@nebostage.test')->firstOrFail();
        auth()->login($admin);
        $tech = Staff::where('role', 'lighting_technician')->first();
        $asset = fn (string $tag) => EquipmentAsset::with(['status', 'equipment'])->where('asset_tag', $tag)->first();
        $lagos = CarbonImmutable::now(config('nebo.display_timezone'))->startOfDay();

        // A unit already on the bench.
        if ($a = $asset('ML-012')) {
            $job = $jobs->report($admin, $a, ['type' => 'repair', 'priority' => 'high', 'issue' => 'Pan motor grinding at speed', 'description' => 'Noticed during the last rig check. Swap the pan belt and test.', 'technician_id' => $tech?->id]);
            $jobs->start($admin, $job);
        }

        // Damaged after a show, waiting for a technician.
        if ($a = $asset('MW-007')) {
            $jobs->report($admin, $a, ['type' => 'repair', 'priority' => 'urgent', 'issue' => 'Cracked front lens after a drop', 'description' => 'Fell from the truss during breakdown. Check the zoom assembly too.']);
        }

        // Inspection booked for the day after tomorrow.
        if ($a = $asset('HZ-002')) {
            $jobs->report($admin, $a, ['type' => 'inspection', 'priority' => 'normal', 'issue' => 'Fluid pump intermittent', 'technician_id' => $tech?->id,
                'scheduled_starts_at' => $lagos->addDays(2)->setTime(9, 0)->utc(), 'scheduled_ends_at' => $lagos->addDays(2)->setTime(13, 0)->utc()]);
        }

        // A completed lamp change with an inspection before it.
        if ($a = $asset('S4-010')) {
            $inspections->record($admin, $a, 'fair', 'Lamp output visibly low; reflector dusty.');
            $done = $jobs->report($admin, $a->refresh(), ['type' => 'lamp_replacement', 'priority' => 'low', 'issue' => 'Replace HPL lamp and clean reflector', 'technician_id' => $tech?->id]);
            $jobs->complete($admin, $done, ['work_done' => 'Fitted a new HPL 750W lamp, cleaned the reflector and lens train, re-focused.', 'outcome_condition' => 'good', 'cost_kobo' => 4_500_000, 'parts_used' => '1 × HPL 750/230 lamp']);
        }

        // Recurring schedules: every MegaPointe every 90 days, consoles twice a year.
        $scheduler->createForEquipment($admin, Equipment::where('sku', 'DEMO-ML-01')->firstOrFail(), ['type' => 'preventive', 'interval_days' => 90, 'next_due_on' => $lagos->addDays(45)->toDateString(), 'notes' => 'Clean optics and fans, check belts, update firmware.']);
        $scheduler->createForEquipment($admin, Equipment::where('sku', 'DEMO-GMA-05')->firstOrFail(), ['type' => 'firmware', 'interval_days' => 180, 'next_due_on' => $lagos->addDays(5)->toDateString()]);
        $scheduler->createForEquipment($admin, Equipment::where('sku', 'DEMO-HST-10')->firstOrFail(), ['type' => 'safety_test', 'interval_days' => 365, 'next_due_on' => $lagos->addDays(20)->toDateString(), 'notes' => 'LOLER thorough examination.']);

        // A few MegaPointes are overdue.
        MaintenanceSchedule::whereHas('asset', fn ($q) => $q->whereIn('asset_tag', ['ML-003', 'ML-004']))->get()
            ->each(fn (MaintenanceSchedule $s) => $scheduler->update($s, ['interval_days' => 90, 'next_due_on' => $lagos->subDays(4)->toDateString(), 'notes' => $s->notes, 'is_active' => true]));

        auth()->logout();
    }
}
